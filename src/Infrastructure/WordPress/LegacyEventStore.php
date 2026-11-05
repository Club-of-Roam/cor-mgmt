<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Events\EventException;
use Cor\Mgmt\Application\Events\EventStore;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Compatibility repository: existing IDs/tables remain authoritative until module cutover. */
final class LegacyEventStore implements EventStore
{
    private bool $editing = false;

    public function __construct(private readonly Database $db)
    {
    }
    private function table(string $type): string
    {
        if (!in_array($type, ['race', 'route', 'stage', 'team', 'teammate', 'sponsor', 'invitation'], true)) {
            throw new EventException('invalid_resource');
        }
        return $this->db->table('h3_mgmt_' . $type . 's');
    }
    public function find(string $type, int $id): array
    {
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->table($type), $id) . ($this->editing ? ' FOR UPDATE' : ''))[0] ?? [];
    }
    public function matching(string $type, string $field, int $value): array
    {
        if (!in_array($field, ['race_id', 'route_id', 'active', 'user_id'], true)) {
            throw new EventException('invalid_filter');
        }
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE %i=%d ORDER BY id', $this->table($type), $field, $value) . ($this->editing ? ' FOR UPDATE' : ''));
    }
    /** @param array<string,string|null> $old */
    private function predicate(array $old): string
    {
        $parts = [];
        foreach ($old as $key => $value) {
            $parts[] = $value === null ? $this->db->prepare('%i IS NULL', $key)
                : $this->db->prepare('BINARY %i=BINARY %s', $key, $value);
        }
        if ($parts === []) {
            throw new EventException('missing_record');
        }
        return implode(' AND ', $parts);
    }
    public function write(string $type, array $old, array $values): int
    {
        $this->db->assertLock();
        $this->protectResults($type, $old, $values);
        $set = [];
        foreach ($values as $key => $value) {
            if ($old !== [] && array_key_exists($key, $old) && $old[$key] === $value) {
                continue;
            }
            $set[] = $value === null ? $this->db->prepare('%i=NULL', $key) : $this->db->prepare('%i=%s', $key, $value);
        }
        if ($set === []) {
            return (int) ($old['id'] ?? 0);
        }
        $query = $old === [] ? $this->db->prepare('INSERT INTO %i SET ', $this->table($type)) . implode(',', $set)
            : $this->db->prepare('UPDATE %i SET ', $this->table($type)) . implode(',', $set) . ' WHERE ' . $this->predicate($old);
        $this->db->execute($query);
        if ($this->db->wpdb->rows_affected !== 1) {
            throw new EventException('stale_record');
        }
        return $old === [] ? (int) $this->db->wpdb->insert_id : (int) $old['id'];
    }
    public function remove(string $type, array $old): void
    {
        $this->db->assertLock();
        $this->protectResults($type, $old, [], true);
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE ', $this->table($type)) . $this->predicate($old));
        if ($this->db->wpdb->rows_affected !== 1) {
            throw new EventException('stale_record');
        }
    }
    /** @param array<string,string|null> $old
     * @param array<string,string|null> $values
     */
    private function protectResults(string $type, array $old, array $values, bool $deleting = false): void
    {
        if ($type !== 'stage' || !$old || !$this->db->exists($this->db->table('cor_mgmt_results'))) {
            return;
        }
        $changed = $deleting;
        foreach (['race_id', 'route_id', 'number'] as $field) {
            $changed = $changed || (isset($values[$field]) && $values[$field] !== ($old[$field] ?? null));
        }
        if ($changed && $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE stage_id=%d', $this->db->table('cor_mgmt_results'), (int) $old['id'])) !== '0') {
            throw new EventException('stage_has_results');
        }
    }
    public function exclusive(callable $operation, bool $multirow): array
    {
        return $this->db->locked(function () use ($operation, $multirow): array {
            $transactional = true;
            foreach (['race', 'route', 'stage', 'team'] as $resource) {
                $engine = $this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table($resource)));
                $transactional = $transactional && $engine === 'InnoDB';
            }
            if (!$transactional) {
                if ($multirow) {
                    throw new EventException('transactional_storage_required');
                }
                return $operation();
            }
            $nested = $this->db->scalar('SELECT @@in_transaction') === '1';
            $this->db->execute($nested ? 'SAVEPOINT cor_event_edit' : 'START TRANSACTION');
            $this->editing = true;
            try {
                $result = $operation();
                $this->db->assertLock();
                $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_event_edit' : 'COMMIT');
                return $result;
            } catch (\Throwable $error) {
                $this->db->execute($nested ? 'ROLLBACK TO SAVEPOINT cor_event_edit' : 'ROLLBACK');
                throw $error;
            } finally {
                $this->editing = false;
            }
        });
    }
}
