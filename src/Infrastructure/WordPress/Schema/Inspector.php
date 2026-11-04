<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\MigrationException;

final class Inspector
{
    public function __construct(private readonly Database $db)
    {
    }
    /** @return array{table:array<string,string|null>,columns:list<array<string,string|null>>,indexes:list<array<string,string|null>>} */
    public function metadata(string $table): array
    {
        $info = $this->db->rows($this->db->prepare('SELECT ENGINE,TABLE_COLLATION,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
        if (count($info) !== 1) {
            throw new MigrationException('missing_source_table');
        }
        return ['table' => $info[0],
            'columns' => $this->db->rows($this->db->prepare('SHOW FULL COLUMNS FROM %i', $table)),
            'indexes' => $this->db->rows($this->db->prepare('SHOW INDEX FROM %i', $table))];
    }
    public function signature(string $table): string
    {
        $metadata = $this->metadata($table);
        // Cardinality is estimated and volatile; it is not schema metadata.
        foreach ($metadata['indexes'] as &$index) {
            unset($index['Cardinality']);
        }
        unset($index);
        return json_encode($metadata, JSON_THROW_ON_ERROR);
    }
    /** @param array{columns:array<string,string>,indexes:array<string,list<string>>} $definition */
    public function verify(string $table, array $definition): void
    {
        $metadata = $this->metadata($table);
        if (
            strcasecmp($metadata['table']['ENGINE'] ?? '', 'InnoDB') !== 0
            || ($metadata['table']['TABLE_COLLATION'] ?? '') !== 'utf8mb4_unicode_ci'
            || ($metadata['table']['TABLE_COMMENT'] ?? '') !== Catalogue::OWNER
        ) {
            throw new MigrationException('target_schema_mismatch');
        }
        $actual = [];
        foreach ($metadata['columns'] as $column) {
            $name = $column['Field'] ?? '';
            $actual[$name] = $column;
        }
        if (array_diff(array_keys($actual), array_keys($definition['columns'])) || array_diff(array_keys($definition['columns']), array_keys($actual))) {
            throw new MigrationException('target_schema_mismatch');
        }
        foreach ($definition['columns'] as $name => $sql) {
            $parts = preg_split('/\s+(?:NOT\s+NULL|NULL|DEFAULT|AUTO_INCREMENT)\b/i', $sql);
            if ($parts === false || !isset($parts[0])) {
                throw new MigrationException('invalid_target_definition');
            }
            $type = $parts[0];
            $column = $actual[$name];
            $default = preg_match('/\bDEFAULT\s+(.+?)(?:\s+ON UPDATE.*)?$/i', $sql, $match) ? $match[1] : null;
            $normalDefault = static function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }
                $value = trim($value);
                if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
                    $value = substr($value, 1, -1);
                }
                if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                    return substr($value, 1, -1);
                }
                return strtolower(str_replace('()', '', $value));
            };
            if (
                self::type($column['Type'] ?? '') !== self::type($type)
                || ($column['Null'] === 'YES') !== !str_contains(strtoupper($sql), 'NOT NULL')
                || str_contains(strtolower($column['Extra'] ?? ''), 'auto_increment') !== str_contains($sql, 'AUTO_INCREMENT')
                || $normalDefault($column['Default']) !== $normalDefault($default)
                || str_contains(strtolower($column['Extra'] ?? ''), 'on update') !== str_contains(strtolower($sql), 'on update')
                || (($column['Collation'] ?? null) !== null && $column['Collation'] !== 'utf8mb4_unicode_ci')
            ) {
                throw new MigrationException('target_schema_mismatch');
            }
        }
        $indexes = [];
        foreach ($metadata['indexes'] as $index) {
            $key = $index['Key_name'] ?? '';
            if (($index['Non_unique'] ?? '') !== (($key === 'PRIMARY' || str_starts_with($key, 'UNIQUE_')) ? '0' : '1')) {
                throw new MigrationException('target_schema_mismatch');
            }
            $indexes[$key][] = $index['Column_name'] ?? '';
        }
        ksort($indexes);
        $expected = $definition['indexes'];
        ksort($expected);
        if ($indexes !== $expected) {
            throw new MigrationException('target_schema_mismatch');
        }
    }
    private static function type(string $type): string
    {
        return strtolower(trim(preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $type) ?? ''));
    }
}
