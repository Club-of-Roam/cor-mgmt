<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Application\Migration\Manifest;
use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Application\Migration\RawRow;
use Throwable;

/** Cooperative lock, atomic target batches and resumable journal; never selects a live generation. */
final class Runner
{
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random)
    {
    }
    public function start(string $fingerprint): string
    {
        // Reject stale or malformed plans before creating any target schema.
        $manifest = (new Preflight($this->db))->run();
        if (!hash_equals($manifest->fingerprint(), $fingerprint)) {
            throw new MigrationException('preflight_changed');
        }
        (new Installer($this->db))->shadow();
        return $this->db->locked(function () use ($manifest): string {
            // Recheck after waiting for schema work; advisory locks do not stop source writers.
            if ((new Preflight($this->db))->run()->fingerprint() !== $manifest->fingerprint()) {
                throw new MigrationException('source_changed');
            }
            $current = $this->current();
            if ($current !== null && $current !== '') {
                $run = $this->state($current);
                $old = Manifest::fromJson($run['manifest'] ?? '');
                if ($old->fingerprint() === $manifest->fingerprint() && $run['state'] !== 'abandoned') {
                    return $current;
                }
                throw new MigrationException('previous_run_needs_review');
            }
            $id = bin2hex($this->random->bytes(16));
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
                throw new MigrationException('invalid_run_id');
            }
            $now = $this->clock->now();
            $this->db->assertLock();
            $this->db->execute($this->db->prepare(
                "INSERT INTO %i (run_id,migration_version,state,manifest,table_index,`cursor`,copied,error_code,created_at,updated_at) VALUES (%s,%s,'running',%s,0,'',0,'',%d,%d)",
                $this->db->table('cor_mgmt_migration_runs'),
                $id,
                Catalogue::MIGRATION_VERSION,
                $manifest->json(),
                $now,
                $now
            ));
            $this->db->setOption('cor_mgmt_current_migration', $id);
            return $id;
        });
    }
    public function current(): ?string
    {
        if (!$this->db->exists($this->db->table('cor_mgmt_migration_runs'))) {
            return null;
        }
        return $this->db->scalar($this->db->prepare("SELECT run_id FROM %i WHERE state<>'abandoned' ORDER BY created_at DESC,run_id DESC LIMIT 1", $this->db->table('cor_mgmt_migration_runs')));
    }
    /** @return array{run_id:string,state:string,copied:int,total:int,error_code:string} */
    public function status(string $id): array
    {
        $run = $this->state($id);
        $manifest = Manifest::fromJson($run['manifest'] ?? '');
        $total = 0;
        foreach ($manifest->tables as $table) {
            $total += $table['count'];
        }
        return ['run_id' => $id,'state' => $run['state'] ?? '', 'copied' => (int)$run['copied'],'total' => $total,'error_code' => $run['error_code'] ?? ''];
    }
    /** @return array{run_id:string,state:string,copied:int,total:int,error_code:string} */
    public function step(string $id, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) {
            throw new MigrationException('invalid_batch');
        }
        return $this->db->locked(function () use ($id, $limit): array {
            foreach (Catalogue::shadow() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            $run = $this->state($id);
            if ($run['state'] === 'verified') {
                return $this->status($id);
            }
            if (!in_array($run['state'], ['running','failed'], true)) {
                throw new MigrationException('run_not_resumable');
            }
            $manifest = Manifest::fromJson($run['manifest'] ?? '');
            if (array_keys($manifest->tables) !== array_keys(Catalogue::legacy())) {
                throw new MigrationException('invalid_manifest');
            }
            $index = (int)$run['table_index'];
            try {
                if ($index === count($manifest->tables)) {
                    $this->verify($id, $manifest);
                    $this->db->execute($this->db->prepare("UPDATE %i SET state='verified',error_code='',updated_at=%d WHERE run_id=%s", $this->db->table('cor_mgmt_migration_runs'), $this->clock->now(), $id));
                    return $this->status($id);
                }
                $suffix = array_keys($manifest->tables)[$index];
                if ((new Inspector($this->db))->signature($this->db->table($suffix)) !== $manifest->tables[$suffix]['schema']) {
                    throw new MigrationException('source_schema_changed');
                }
                $rows = (new Source($this->db))->batch($suffix, $run['cursor'] ?? '', $limit);
                $this->db->assertLock();
                $this->db->execute('START TRANSACTION');
                try {
                    $cursor = $run['cursor'] ?? '';
                    foreach ($rows as $row) {
                        $this->db->execute($this->db->prepare(
                            'INSERT INTO %i (run_id,source_table,source_id,payload,row_hash) VALUES (%s,%s,CAST(%s AS UNSIGNED),%s,%s)',
                            $this->db->table('cor_mgmt_migration_rows'),
                            $id,
                            $suffix,
                            $row->id(),
                            $row->payload(),
                            $row->hash()
                        ));
                        (new Projection($this->db))->copy($id, $suffix, $row);
                        $cursor = $row->id();
                    }
                    if (count($rows) < $limit) {
                        ++$index;
                        $cursor = '';
                    }
                    $this->db->execute($this->db->prepare(
                        "UPDATE %i SET state='running',table_index=%d,`cursor`=%s,copied=copied+%d,error_code='',updated_at=%d WHERE run_id=%s",
                        $this->db->table('cor_mgmt_migration_runs'),
                        $index,
                        $cursor,
                        count($rows),
                        $this->clock->now(),
                        $id
                    ));
                    $this->db->assertLock();
                    $this->db->execute('COMMIT');
                } catch (Throwable $error) {
                    $this->db->execute('ROLLBACK');
                    throw $error;
                }
            } catch (Throwable $error) {
                $code = $error instanceof MigrationException ? $error->getMessage() : 'migration_step_failed';
                // Codes only; original SQL, exception messages and source values stay private.
                if (!preg_match('/^[a-z_]{1,64}$/D', $code)) {
                    $code = 'migration_step_failed';
                }
                $this->db->execute($this->db->prepare("UPDATE %i SET state='failed',error_code=%s,updated_at=%d WHERE run_id=%s", $this->db->table('cor_mgmt_migration_runs'), $code, $this->clock->now(), $id));
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validated internal error code; the HTTP boundary renders a separate message.
                throw new MigrationException($code);
            }
            return $this->status($id);
        });
    }
    public function abandon(string $id): void
    {
        $this->db->locked(function () use ($id): void {
            $this->state($id);
            if ($this->db->option('cor_mgmt_active_generation') === $id) {
                throw new MigrationException('active_generation_in_use');
            }
            $this->db->execute($this->db->prepare("UPDATE %i SET state='abandoned',updated_at=%d WHERE run_id=%s", $this->db->table('cor_mgmt_migration_runs'), $this->clock->now(), $id));
            if ($this->db->option('cor_mgmt_current_migration') === $id) {
                $this->db->setOption('cor_mgmt_current_migration', '');
            }
        });
    }
    /** @return array<string,string|null> */
    private function state(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new MigrationException('invalid_run_id');
        }
        $rows = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE run_id=%s', $this->db->table('cor_mgmt_migration_runs'), $id));
        if (count($rows) !== 1 || $rows[0]['migration_version'] !== Catalogue::MIGRATION_VERSION) {
            throw new MigrationException('unknown_migration_run');
        }
        $run = $rows[0];
        if (
            !in_array($run['state'] ?? '', ['running','failed','verified','abandoned'], true)
            || !is_string($run['table_index'] ?? null) || !preg_match('/^[0-9]$/D', $run['table_index'])
            || (int)$run['table_index'] > count(Catalogue::legacy())
            || !is_string($run['copied'] ?? null) || !preg_match('/^[0-9]{1,18}$/D', $run['copied'])
            || !is_string($run['cursor'] ?? null)
        ) {
            throw new MigrationException('invalid_run_state');
        }
        if ($run['cursor'] !== '') {
            (new RawRow(['id' => bin2hex($run['cursor'])]))->id();
        }
        return $run;
    }
    private function verify(string $id, Manifest $manifest): void
    {
        if ((new Preflight($this->db))->run()->fingerprint() !== $manifest->fingerprint()) {
            throw new MigrationException('source_changed');
        }
        $counts = [
            'cor_mgmt_migration_rows' => array_sum(array_column($manifest->tables, 'count')),
            'cor_mgmt_stage_keys' => $manifest->tables['h3_mgmt_stages']['count'],
            'cor_mgmt_stage_results' => $manifest->tables['h3_mgmt_teams']['count'] * 6,
            'cor_mgmt_race_states' => $manifest->tables['h3_mgmt_races']['count'],
            'cor_mgmt_legacy_amounts' => $manifest->tables['h3_mgmt_teams']['count'] + $manifest->tables['h3_mgmt_sponsors']['count'],
        ];
        foreach ($counts as $suffix => $expectedCount) {
            if ($this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE run_id=%s', $this->db->table($suffix), $id)) !== (string)$expectedCount) {
                throw new MigrationException('target_comparison_failed');
            }
        }
        foreach ($manifest->tables as $suffix => $expected) {
            $hash = hash_init('sha256');
            $count = 0;
            $cursor = '';
            do {
                $sql = $this->db->prepare('SELECT source_id,payload,row_hash FROM %i WHERE run_id=%s AND source_table=%s', $this->db->table('cor_mgmt_migration_rows'), $id, $suffix);
                if ($cursor !== '') {
                    $sql .= $this->db->prepare(' AND source_id>CAST(%s AS UNSIGNED)', $cursor);
                }
                $rows = $this->db->rows($sql . ' ORDER BY source_id ASC LIMIT 250');
                foreach ($rows as $copy) {
                    $row = RawRow::fromPayload($copy['payload'] ?? '');
                    if ($row->id() !== $copy['source_id'] || $row->hash() !== $copy['row_hash']) {
                        throw new MigrationException('target_row_changed');
                    }
                    (new Projection($this->db))->verify($id, $suffix, $row);
                    hash_update($hash, $row->id() . ':' . $row->hash() . "\n");
                    ++$count;
                    $cursor = $row->id();
                }
            } while ($rows !== []);
            if ($count !== $expected['count'] || hash_final($hash) !== $expected['hash']) {
                throw new MigrationException('target_comparison_failed');
            }
        }
    }
}
