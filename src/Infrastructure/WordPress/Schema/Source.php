<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Application\Migration\RawRow;

/** Read-only source access. Values leave MySQL as HEX of their original storage bytes. */
final class Source
{
    public function __construct(private readonly Database $db)
    {
    }
    /** @return list<RawRow> */
    public function batch(string $suffix, string $cursor = '', int $limit = 100): array
    {
        if (!isset(Catalogue::legacy()[$suffix]) || $limit < 1 || $limit > 500) {
            throw new MigrationException('invalid_batch');
        }
        if (
            $cursor !== '' && (!preg_match('/^(?:0|[1-9][0-9]{0,19})$/D', $cursor)
            || (strlen($cursor) === 20 && strcmp($cursor, '18446744073709551615') > 0))
        ) {
            throw new MigrationException('invalid_cursor');
        }
        $table = $this->db->table($suffix);
        $columns = $this->db->rows($this->db->prepare('SHOW FULL COLUMNS FROM %i', $table));
        $fields = [];
        foreach ($columns as $column) {
            $name = $column['Field'];
            if ($name === null) {
                throw new MigrationException('invalid_source_schema');
            }
            $fields[] = $this->db->prepare('HEX(CAST(%i AS BINARY)) AS %i', $name, $name);
        }
        if (!in_array('id', array_column($columns, 'Field'), true)) {
            throw new MigrationException('source_id_missing');
        }
        $sql = 'SELECT ' . implode(',', $fields) . ' ' . $this->db->prepare('FROM %i AS cor_source', $table);
        if ($cursor !== '') {
            $sql .= $this->db->prepare(' WHERE cor_source.id>CAST(%s AS UNSIGNED)', $cursor);
        }
        $sql .= $this->db->prepare(' ORDER BY cor_source.id ASC LIMIT %d', $limit);
        $rows = [];
        foreach ($this->db->rows($sql) as $hex) {
            $row = new RawRow($hex);
            $row->id();
            $rows[] = $row;
        }
        return $rows;
    }
    public function assertUniqueIds(string $suffix): void
    {
        $table = $this->db->table($suffix);
        $indexes = $this->db->rows($this->db->prepare('SHOW INDEX FROM %i', $table));
        $keys = [];
        foreach ($indexes as $index) {
            if ($index['Non_unique'] === '0') {
                $keys[$index['Key_name'] ?? ''][] = $index['Column_name'];
            }
        }
        if (!in_array(['id'], $keys, true)) {
            throw new MigrationException('source_id_not_unique');
        }
        if ($this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE id IS NULL', $table)) !== '0') {
            throw new MigrationException('source_id_null');
        }
    }
}
