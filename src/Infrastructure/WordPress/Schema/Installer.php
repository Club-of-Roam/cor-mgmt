<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\MigrationException;

/** Explicit, resumable DDL; ownership manifests precede nontransactional CREATEs. */
final class Installer
{
    public function __construct(private readonly Database $db)
    {
    }
    public function fresh(): void
    {
        $this->db->locked(function (): void {
            $this->create(Catalogue::legacy() + Catalogue::shadow(), 'cor_mgmt_fresh_install', true);
        });
    }
    public function shadow(): void
    {
        $this->db->locked(function (): void {
            $this->create(Catalogue::shadow(), 'cor_mgmt_shadow_install', false);
        });
    }
    public function pendingFresh(): bool
    {
        $raw = $this->db->option('cor_mgmt_fresh_install');
        if ($raw === null) {
            return false;
        }
        $state = json_decode($raw, true);
        return !is_array($state) || ($state['state'] ?? '') !== 'complete';
    }
    /** @param array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> $definitions */
    private function create(array $definitions, string $owner, bool $fresh): void
    {
        $plan = hash('sha256', json_encode($definitions, JSON_THROW_ON_ERROR));
        $raw = $this->db->option($owner);
        $state = $raw === null ? null : json_decode($raw, true);
        if ($raw !== null && !is_array($state)) {
            throw new MigrationException('installation_plan_changed');
        }
        $storage = $this->db->option('cor_mgmt_storage_version');
        if ($storage !== null && $storage !== Catalogue::STORAGE_VERSION) {
            throw new MigrationException('unsupported_storage_version');
        }
        if (
            $state !== null && (($state['plan'] ?? '') !== $plan
            || !in_array($state['state'] ?? '', ['running','complete'], true))
        ) {
            throw new MigrationException('installation_plan_changed');
        }
        $inspector = new Inspector($this->db);
        // A verified shadow schema can be reused; a fresh install must never adopt a legacy site.
        $existingShadow = !$fresh && $storage === Catalogue::STORAGE_VERSION;
        if ($state === null && !$existingShadow) {
            if ($fresh && $this->db->option('h3_mgmt_db_version') !== null) {
                throw new MigrationException('existing_installation');
            }
            foreach ($definitions as $suffix => $_) {
                if ($this->db->exists($this->db->table($suffix))) {
                    throw new MigrationException('table_name_collision');
                }
            }
            $this->db->setOption($owner, json_encode(['state' => 'running','plan' => $plan], JSON_THROW_ON_ERROR));
        }
        foreach ($definitions as $suffix => $definition) {
            $this->db->assertLock();
            $table = $this->db->table($suffix);
            if (!$this->db->exists($table)) {
                if ($existingShadow || (is_array($state) && $state['state'] === 'complete')) {
                    throw new MigrationException('target_schema_mismatch');
                }
                $columns = [];
                foreach ($definition['columns'] as $name => $type) {
                    $columns[] = $this->db->prepare('%i', $name) . ' ' . $type;
                }
                foreach ($definition['indexes'] as $name => $fields) {
                    $identifiers = implode(',', array_map(fn(string $field): string=>$this->db->prepare('%i', $field), $fields));
                    $columns[] = ($name === 'PRIMARY' ? 'PRIMARY KEY' : ('KEY ' . $this->db->prepare('%i', $name))) . ' (' . $identifiers . ')';
                }
                $sql = $this->db->prepare('CREATE TABLE %i', $table) . ' (' . implode(',', $columns) . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ' . $this->db->prepare('COMMENT=%s', Catalogue::OWNER);
                $this->db->execute($sql);
            }
            $inspector->verify($table, $definition);
            if (
                $fresh && (!is_array($state) || $state['state'] !== 'complete')
                && $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $table)) !== '0'
            ) {
                throw new MigrationException('pending_installation_contains_data');
            }
        }
        $this->db->setOption('cor_mgmt_storage_version', Catalogue::STORAGE_VERSION);
        if ($fresh) {
            $this->db->setOption('h3_mgmt_db_version', '2.8');
        }
        $this->db->setOption($owner, json_encode(['state' => 'complete','plan' => $plan], JSON_THROW_ON_ERROR));
    }
}
