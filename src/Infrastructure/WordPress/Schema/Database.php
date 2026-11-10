<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\MigrationException;
use wpdb;

/** All dynamic identifiers use wpdb %i; callers pass prepared SQL or fixed statements. */
final class Database
{
    private ?string $lockName = null;
    private ?string $lockConnection = null;
    public function __construct(public readonly wpdb $wpdb)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $wpdb->prefix)) {
            throw new MigrationException('invalid_table_prefix');
        }
    }
    public function table(string $suffix): string
    {
        return $this->wpdb->prefix . $suffix;
    }
    /** @param literal-string $query */
    public function prepare(string $query, string|int ...$arguments): string
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed templates and %i/%s/%d arguments are supplied by the schema classes.
        $sql = $this->wpdb->prepare($query, ...$arguments);
        if (!is_string($sql)) {
            throw new MigrationException('invalid_sql_template');
        }
        return $sql;
    }
    public function execute(string $sql): void
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Only prepared statements or constant transaction commands enter this adapter.
        $result = $this->wpdb->query($sql);
        if ($result === false) {
            throw new MigrationException('database_query_failed');
        }
    }
    /** @return list<array<string,string|null>> */
    public function rows(string $sql): array
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared SQL from prepare(), including identifier placeholders.
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        if ($this->wpdb->last_error !== '' || $rows === null) {
            throw new MigrationException('database_query_failed');
        }
        $output = [];
        foreach ($rows as $row) {
            $normalized = [];
            foreach ($row as $key => $value) {
                if (!is_string($key) || ($value !== null && !is_scalar($value))) {
                    throw new MigrationException('invalid_database_result');
                }
                $normalized[$key] = $value === null ? null : (string)$value;
            }
            $output[] = $normalized;
        }
        return $output;
    }
    public function scalar(string $sql): ?string
    {
        $rows = $this->rows($sql);
        return $rows === [] ? null : array_values($rows[0])[0];
    }
    public function exists(string $table): bool
    {
        return $this->scalar($this->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === '1';
    }
    public function option(string $name): ?string
    {
        return $this->scalar($this->prepare('SELECT option_value FROM %i WHERE option_name=%s', $this->wpdb->options, $name));
    }
    public function setOption(string $name, string $value): void
    {
        $this->execute($this->prepare("INSERT INTO %i (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=%s,autoload='no'", $this->wpdb->options, $name, $value, $value));
        wp_cache_delete($name, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }
    public function hasLock(): bool
    {
        return $this->lockName !== null;
    }
    private function lockIdentifier(): string
    {
        return 'cor_mgmt_' . substr(hash('sha256', ($this->scalar('SELECT DATABASE()') ?? '') . ':' . $this->wpdb->prefix), 0, 48);
    }
    public function connectionHasLock(): bool
    {
        return $this->scalar($this->prepare('SELECT IS_USED_LOCK(%s)', $this->lockIdentifier())) === $this->scalar('SELECT CONNECTION_ID()');
    }
    public function assertLock(): void
    {
        if (
            $this->lockName === null || $this->scalar('SELECT CONNECTION_ID()') !== $this->lockConnection
            || $this->scalar($this->prepare('SELECT IS_USED_LOCK(%s)', $this->lockName)) !== $this->lockConnection
        ) {
            throw new MigrationException('migration_lock_lost');
        }
    }
    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public function locked(callable $operation, bool $maintenance = false): mixed
    {
        if ($this->lockName !== null) {
            throw new MigrationException('nested_migration_lock');
        }
        $name = $this->lockIdentifier();
        if ($this->scalar($this->prepare('SELECT GET_LOCK(%s,0)', $name)) !== '1') {
            throw new MigrationException('migration_locked');
        }
        $this->lockName = $name;
        $this->lockConnection = $this->scalar('SELECT CONNECTION_ID()');
        try {
            if (!$maintenance) {
                $rawState = $this->option('cor_mgmt_live_transition');
                $state = json_decode($rawState ?? '', true);
                if ($rawState !== null && (!is_array($state) || !in_array($state['state'] ?? '', ['active','rolled_back','abandoned'], true))) {
                    throw new MigrationException('maintenance_in_progress');
                }
                if ($rawState !== null && !(new Cutover($this, new \Cor\Mgmt\Infrastructure\SystemClock(), new \Cor\Mgmt\Infrastructure\SecureRandom()))->runtimeReady()) {
                    throw new MigrationException('runtime_generation_requires_review');
                }
            }
            return $operation();
        } finally {
            $this->lockName = null;
            $this->lockConnection = null;
            $this->scalar($this->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
