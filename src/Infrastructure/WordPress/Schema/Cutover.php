<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Infrastructure\WordPress\Archive;

/** Parallel live copies, one atomic name swap and explicit recovery; no source table is dropped. */
final class Cutover
{
    public const VERSION = 'live-copy-v1';

    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random)
    {
    }

    /** @return array<string,mixed>|null */
    public function state(): ?array
    {
        $raw = $this->db->option('cor_mgmt_live_transition');
        if ($raw === null) {
            return null;
        }
        $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($state) || ($state['version'] ?? '') !== self::VERSION
            || !preg_match('/^[a-f0-9]{32}$/D', $state['generation'] ?? '')
            || !in_array($state['state'] ?? '', ['prepare','copy','verify','ready','switching','active','reverting','rolled_back','abandoned'], true)
            || !is_array($state['tables'] ?? null) || array_keys($state['tables']) !== array_keys(Catalogue::legacy())
        ) {
            throw new \RuntimeException('live_transition_state_invalid');
        }
        if (
            !is_int($state['index'] ?? null) || $state['index'] < 0 || $state['index'] > 9
            || !is_string($state['cursor'] ?? null) || !preg_match('/^[0-9]{0,20}$/D', $state['cursor'])
            || !is_int($state['copied'] ?? null) || $state['copied'] < 0 || !is_array($state['archives'] ?? null)
        ) {
            throw new \RuntimeException('live_transition_state_invalid');
        }
        foreach ($state['tables'] as $suffix => $table) {
            $short = substr($suffix, strlen('h3_mgmt_'));
            if (
                !is_array($table) || ($table['target'] ?? '') !== $this->db->table('cor_live_' . substr($state['generation'], 0, 8) . '_' . $short)
                || ($table['backup'] ?? '') !== $this->db->table('cor_old_' . substr($state['generation'], 0, 8) . '_' . $short)
                || !is_int($table['count'] ?? null) || $table['count'] < 0
                || !is_string($table['source_shape'] ?? null) || !is_string($table['target_shape'] ?? null)
                || !is_string($table['source_compatible_shape'] ?? null)
            ) {
                throw new \RuntimeException('live_transition_state_invalid');
            }
        }
        foreach ($state['archives'] as $snapshot) {
            if (!is_string($snapshot) || !preg_match('/^[a-f0-9]{32}$/D', $snapshot)) {
                throw new \RuntimeException('live_transition_state_invalid');
            }
        }
        return $state;
    }

    private function shape(string $table): string
    {
        $metadata = (new Inspector($this->db))->metadata($table);
        foreach ($metadata['indexes'] as &$index) {
            unset($index['Table'], $index['Cardinality']);
        }
        unset($index);
        return json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $state */
    private function save(array $state): void
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        $this->db->setOption('cor_mgmt_live_transition', $json);
        $this->db->setOption('cor_mgmt_live_generation_' . $state['generation'], $json);
    }

    public function start(string $shadow, string $fingerprint, int $actor): void
    {
        $this->db->locked(function () use ($shadow, $fingerprint, $actor): void {
            $previous = $this->state();
            if (!str_contains(strtolower($this->db->scalar('SELECT VERSION()') ?? ''), 'mariadb')) {
                throw new \RuntimeException('live_transition_database_not_verified');
            }
            $this->assertSourceDependencies();
            if ($previous !== null && !in_array($previous['state'], ['rolled_back','abandoned'], true)) {
                throw new \RuntimeException('live_transition_already_started');
            }
            if (
                $actor < 1 || $this->db->scalar('SELECT @@in_transaction') !== '0'
                || $this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->db->wpdb->options)) !== 'InnoDB'
            ) {
                throw new \RuntimeException('live_transition_requires_independent_request');
            }
            $report = (new Preflight($this->db))->run();
            if (!hash_equals($report->fingerprint(), $fingerprint) || (new Runner($this->db, $this->clock, $this->random))->status($shadow)['state'] !== 'verified') {
                throw new \RuntimeException('verified_shadow_required');
            }
            $record = $this->db->rows($this->db->prepare('SELECT manifest FROM %i WHERE run_id=%s', $this->db->table('cor_mgmt_migration_runs'), $shadow))[0] ?? [];
            if (hash('sha256', $record['manifest'] ?? '') !== $fingerprint) {
                throw new \RuntimeException('verified_shadow_changed');
            }
            $archive = new Archive($this->db, $this->clock, $this->random);
            $archives = [];
            foreach ($report->archiveIds as $race) {
                $id = $this->db->scalar($this->db->prepare("SELECT snapshot_id FROM %i WHERE race_id=CAST(%s AS UNSIGNED) AND mode='migration' AND state IN ('ready','sealed') ORDER BY created_at DESC,snapshot_id DESC LIMIT 1", $this->db->table('cor_mgmt_archive_runs'), $race));
                if ($id === null || !in_array($archive->run($id)['state'], ['ready','sealed'], true)) {
                    throw new \RuntimeException('migration_archives_required');
                }
                $archives[$race] = $id;
            }
            $generation = bin2hex($this->random->bytes(16));
            $tables = [];
            foreach ($report->tables as $suffix => $table) {
                $short = substr($suffix, strlen('h3_mgmt_'));
                $target = $this->db->table('cor_live_' . substr($generation, 0, 8) . '_' . $short);
                $backup = $this->db->table('cor_old_' . substr($generation, 0, 8) . '_' . $short);
                if (strlen($target) > 64 || strlen($backup) > 64 || $this->db->exists($target) || $this->db->exists($backup)) {
                    throw new \RuntimeException('live_table_name_collision');
                }
                $tables[$suffix] = ['target' => $target, 'backup' => $backup, 'count' => $table['count'], 'source_shape' => $table['schema'], 'source_compatible_shape' => $this->shape($this->db->table($suffix)), 'target_shape' => ''];
            }
            $this->save(['version' => self::VERSION, 'generation' => $generation, 'state' => 'prepare', 'shadow' => $shadow, 'fingerprint' => $fingerprint,
                'actor' => $actor, 'created_at' => $this->clock->now(), 'tables' => $tables, 'archives' => $archives, 'index' => 0, 'cursor' => '', 'copied' => 0]);
        }, true);
    }

    /** @return list<array<string,string|null>> */
    private function columns(string $source): array
    {
        return (new Inspector($this->db))->metadata($source)['columns'];
    }

    /** @return array<string,string> SQL projections with fixed source alias. */
    private function expressions(string $suffix): array
    {
        $expressions = [];
        foreach ($this->columns($this->db->table($suffix)) as $column) {
            $name = $column['Field'] ?? '';
            $field = $this->db->prepare('s.%i', $name);
            $expression = $column['Collation'] === null ? $field : 'CONVERT(' . $field . ' USING utf8mb4)';
            if ($suffix === 'h3_mgmt_races') {
                $setting = 'CONVERT(s.setting USING utf8mb4)';
                $started = "JSON_VALID($setting) AND JSON_UNQUOTE(JSON_EXTRACT($setting,'$.status'))='3'";
                if ($name === 'setting') {
                    $expression = "CASE WHEN $started THEN JSON_SET($setting,'$.status',4) ELSE $expression END";
                } elseif ($name === 'active') {
                    $expression = "CASE WHEN $started THEN 0 ELSE $expression END";
                }
            }
            $expressions[$name] = $expression;
        }
        return $expressions;
    }

    /** @return array<string,mixed> */
    public function step(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \RuntimeException('invalid_live_batch');
        }
        return $this->db->locked(function () use ($limit): array {
            $state = $this->state();
            if ($state === null || !in_array($state['state'], ['prepare','copy','verify','ready'], true) || $this->db->scalar('SELECT @@in_transaction') !== '0') {
                throw new \RuntimeException('live_transition_not_resumable');
            }
            if ($state['state'] === 'ready') {
                return $state;
            }
            $index = $state['index'];
            if ($index === count($state['tables'])) {
                $state['state'] = $state['state'] === 'prepare' ? 'copy' : ($state['state'] === 'copy' ? 'verify' : 'ready');
                $state['index'] = 0;
                $state['cursor'] = '';
                $this->save($state);
                return $state;
            }
            $suffix = array_keys($state['tables'])[$index];
            if (!is_string($suffix)) {
                throw new \RuntimeException('live_transition_state_invalid');
            }
            $table = $state['tables'][$suffix];
            $source = $this->db->table($suffix);
            if ((new Inspector($this->db))->signature($source) !== $table['source_shape']) {
                throw new \RuntimeException('live_source_schema_changed');
            }
            $owner = 'cor-mgmt:' . self::VERSION . ':' . $state['generation'];
            if ($state['state'] === 'prepare') {
                if (!$this->db->exists($table['target'])) {
                    $this->db->execute($this->db->prepare('CREATE TABLE %i LIKE %i', $table['target'], $source));
                }
                // The persisted random-name creation intent permits recovery between CREATE LIKE and COMMENT.
                if (
                    $this->shape($table['target']) === $table['source_compatible_shape']
                    && $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $table['target'])) === '0'
                ) {
                    $this->db->execute($this->db->prepare('ALTER TABLE %i COMMENT=%s', $table['target'], $owner));
                }
                if (
                    (new Inspector($this->db))->metadata($table['target'])['table']['TABLE_COMMENT'] !== $owner
                    || $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $table['target'])) !== '0'
                ) {
                    throw new \RuntimeException('live_target_ownership');
                }
                $this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $table['target']));
                if ($this->db->rows('SHOW WARNINGS')) {
                    throw new \RuntimeException('live_character_conversion_requires_review');
                }
                $columns = array_column($this->columns($table['target']), 'Field');
                $indexes = (new Inspector($this->db))->metadata($table['target'])['indexes'];
                foreach (['race_id','route_id','team_id','user_id'] as $field) {
                    $present = array_filter($indexes, static fn (array $row): bool => $row['Column_name'] === $field && $row['Seq_in_index'] === '1');
                    if (in_array($field, $columns, true) && !$present) {
                        $this->db->execute($this->db->prepare('ALTER TABLE %i ADD INDEX %i (%i)', $table['target'], 'cor_relation_' . $field, $field));
                    }
                }
                $state['tables'][$suffix]['target_shape'] = $this->shape($table['target']);
                ++$state['index'];
                $this->save($state);
                return $state;
            }
            if ($this->shape($table['target']) !== $table['target_shape']) {
                throw new \RuntimeException('live_target_schema_changed');
            }
            $batch = (new Source($this->db))->batch($suffix, $state['cursor'], $limit);
            $cursor = $batch ? $batch[count($batch) - 1]->id() : $state['cursor'];
            $this->db->execute('START TRANSACTION');
            try {
                if ($state['state'] === 'copy' && $batch) {
                    $expressions = $this->expressions($suffix);
                    $fields = implode(',', array_map(fn (string $field): string => $this->db->prepare('%i', $field), array_keys($expressions)));
                    $this->db->execute($this->db->prepare('INSERT INTO %i (', $table['target']) . $fields . ') SELECT ' . implode(',', $expressions)
                        . $this->db->prepare(' FROM %i s', $source)
                        . ($state['cursor'] === '' ? '' : $this->db->prepare(' WHERE s.id>CAST(%s AS UNSIGNED)', $state['cursor']))
                        . $this->db->prepare(' ORDER BY s.id LIMIT %d', $limit));
                    if ($this->db->rows('SHOW WARNINGS')) {
                        throw new \RuntimeException('live_character_conversion_requires_review');
                    }
                    $state['copied'] += count($batch);
                } elseif ($state['state'] === 'verify') {
                    $this->verifyBatch($suffix, $table['target'], $state['cursor'], $limit);
                }
                $state['cursor'] = $cursor;
                if (count($batch) < $limit) {
                    if ((int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $table['target'])) !== $table['count']) {
                        throw new \RuntimeException('live_target_count_changed');
                    }
                    ++$state['index'];
                    $state['cursor'] = '';
                }
                $this->save($state);
                $this->db->assertLock();
                $this->db->execute('COMMIT');
            } catch (\Throwable $error) {
                $this->db->execute('ROLLBACK');
                throw $error;
            }
            return $state;
        }, true);
    }

    private function verifyBatch(string $suffix, string $target, string $cursor, int $limit): void
    {
        $expected = [];
        $actual = [];
        foreach ($this->expressions($suffix) as $name => $expression) {
            $expected[] = 'HEX(CAST(' . $expression . ' AS BINARY)) ' . $this->db->prepare('AS %i', $name);
            $actual[] = $this->db->prepare('HEX(CAST(s.%i AS BINARY)) AS %i', $name, $name);
        }
        $end = ' s' . ($cursor === '' ? '' : $this->db->prepare(' WHERE s.id>CAST(%s AS UNSIGNED)', $cursor))
            . $this->db->prepare(' ORDER BY s.id LIMIT %d', $limit);
        $left = $this->db->rows('SELECT ' . implode(',', $expected) . $this->db->prepare(' FROM %i', $this->db->table($suffix)) . $end);
        $right = $this->db->rows('SELECT ' . implode(',', $actual) . $this->db->prepare(' FROM %i', $target) . $end);
        if ($left !== $right) {
            throw new \RuntimeException('live_target_content_changed');
        }
    }

    public function activate(): void
    {
        $this->db->locked(function (): void {
            $state = $this->state();
            if ($state === null || $state['state'] !== 'ready') {
                throw new \RuntimeException('verified_live_copy_required');
            }
            $this->assertSourceDependencies();
            if ((new Preflight($this->db))->run()->fingerprint() !== $state['fingerprint']) {
                throw new \RuntimeException('live_source_changed');
            }
            $renames = [];
            $archive = new Archive($this->db, $this->clock, $this->random);
            foreach ($state['archives'] as $snapshot) {
                $archive->verifySource($snapshot);
            }
            foreach ($state['tables'] as $suffix => $table) {
                if ($this->db->exists($table['backup']) || $this->shape($table['target']) !== $table['target_shape']) {
                    throw new \RuntimeException('live_target_schema_changed');
                }
                $cursor = '';
                do {
                    $batch = (new Source($this->db))->batch($suffix, $cursor, 100);
                    $this->verifyBatch($suffix, $table['target'], $cursor, 100);
                    if ($batch) {
                        $cursor = $batch[count($batch) - 1]->id();
                    }
                } while (count($batch) === 100);
                if ((int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $table['target'])) !== $table['count']) {
                    throw new \RuntimeException('live_target_count_changed');
                }
                $renames[] = $this->db->prepare('%i TO %i', $this->db->table($suffix), $table['backup']);
                $renames[] = $this->db->prepare('%i TO %i', $table['target'], $this->db->table($suffix));
            }
            $state['state'] = 'switching';
            $this->save($state);
            $this->db->execute('RENAME TABLE ' . implode(',', $renames));
            $this->finish($state);
        }, true);
    }

    /** @param array<string,mixed> $state */
    private function finish(array $state): void
    {
        foreach ($state['tables'] as $suffix => $table) {
            if (
                !$this->db->exists($table['backup']) || $this->db->exists($table['target'])
                || $this->shape($this->db->table($suffix)) !== $table['target_shape']
            ) {
                throw new \RuntimeException('live_switch_requires_review');
            }
        }
        $this->db->execute('START TRANSACTION');
        try {
            $archive = new Archive($this->db, $this->clock, $this->random);
            foreach ($state['archives'] as $snapshot) {
                $archive->seal($snapshot, true);
            }
            $state['state'] = 'active';
            $state['rollback_guard'] = $this->guard();
            $this->db->setOption('cor_mgmt_runtime_generation', $state['generation']);
            $this->save($state);
            $this->db->execute('COMMIT');
        } catch (\Throwable $error) {
            $this->db->execute('ROLLBACK');
            throw $error;
        }
    }

    public function recover(): void
    {
        $this->db->locked(function (): void {
            $state = $this->state();
            if ($state === null || !in_array($state['state'], ['switching','reverting'], true)) {
                throw new \RuntimeException('live_recovery_not_required');
            }
            if ($state['state'] === 'reverting') {
                $reverted = 0;
                foreach ($state['tables'] as $suffix => $table) {
                    $reverted += $this->db->exists($table['target']) && !$this->db->exists($table['backup'])
                        && $this->shape($this->db->table($suffix)) === $table['source_compatible_shape'] ? 1 : 0;
                }
                if ($reverted === count($state['tables'])) {
                    $this->finishRollback($state);
                } elseif ($reverted === 0) {
                    foreach ($state['tables'] as $suffix => $table) {
                        if (!$this->db->exists($table['backup']) || $this->db->exists($table['target']) || $this->shape($this->db->table($suffix)) !== $table['target_shape']) {
                            throw new \RuntimeException('live_rollback_requires_review');
                        }
                    }
                    $state['state'] = 'active';
                    $this->save($state);
                } else {
                    throw new \RuntimeException('live_rollback_requires_review');
                }
                return;
            }
            $renamed = 0;
            foreach ($state['tables'] as $table) {
                $renamed += $this->db->exists($table['backup']) && !$this->db->exists($table['target']) ? 1 : 0;
            }
            if ($renamed === count($state['tables'])) {
                $this->finish($state);
            } elseif ($renamed === 0) {
                foreach ($state['tables'] as $suffix => $table) {
                    if (!$this->db->exists($table['target']) || $this->db->exists($table['backup']) || (new Inspector($this->db))->signature($this->db->table($suffix)) !== $table['source_shape']) {
                        throw new \RuntimeException('live_switch_requires_review');
                    }
                }
                $state['state'] = 'ready';
                $this->save($state);
            } else {
                throw new \RuntimeException('live_switch_requires_review');
            }
        }, true);
    }

    /** Hashes only: all plugin data plus accounts/profiles protect the no-new-writes rollback contract. */
    private function guard(): string
    {
        $tables = array_keys(Catalogue::legacy() + ParticipationSchema::definitions() + ResultsSchema::definitions() + DonationSchema::definitions()
            + CommunicationSchema::definitions() + MailQueueSchema::definitions() + BroadcastSchema::definitions() + ExchangeSchema::definitions() + TickerSchema::definitions() + ArchiveSchema::definitions());
        $tables = array_map(fn (string $suffix): string => $this->db->table($suffix), $tables);
        $tables[] = $this->db->wpdb->users;
        $tables[] = $this->db->wpdb->usermeta;
        $context = hash_init('sha256');
        foreach ($tables as $table) {
            if (!$this->db->exists($table)) {
                continue;
            }
            hash_update($context, (new Inspector($this->db))->signature($table));
            $columns = [];
            $details = [];
            foreach ($this->columns($table) as $column) {
                if (!is_string($column['Field'])) {
                    throw new \RuntimeException('live_source_schema_changed');
                }
                $columns[] = $column['Field'];
                $details[$column['Field']] = $column;
            }
            $fields = array_map(fn (string $field): string => $this->db->prepare('HEX(CAST(s.%i AS BINARY)) AS %i', $field, $field), $columns);
            $primary = array_values(array_filter((new Inspector($this->db))->metadata($table)['indexes'], static fn (array $index): bool => $index['Key_name'] === 'PRIMARY'));
            usort($primary, static fn (array $a, array $b): int => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);
            $orderColumns = $primary ? array_column($primary, 'Column_name') : $columns;
            $keys = [];
            $orderParts = [];
            foreach ($orderColumns as $field) {
                if (!is_string($field)) {
                    throw new \RuntimeException('live_source_schema_changed');
                }
                $orderParts[] = $this->db->prepare('s.%i', $field);
                $keys[] = $field;
            }
            $order = implode(',', $orderParts);
            $offset = 0;
            $cursor = [];
            do {
                $where = $primary && $cursor ? ' WHERE ' . $this->after($keys, $details, $cursor) : '';
                $paging = $primary ? ' LIMIT 100' : $this->db->prepare(' LIMIT 100 OFFSET %d', $offset);
                $batch = $this->db->rows('SELECT ' . implode(',', $fields) . $this->db->prepare(' FROM %i s', $table) . $where . ' ORDER BY ' . $order . $paging);
                foreach ($batch as $row) {
                    hash_update($context, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
                }
                $offset += 100;
                $cursor = $batch ? $batch[count($batch) - 1] : [];
            } while (count($batch) === 100);
        }
        return hash_final($context);
    }

    /**
     * Exact keyset pagination avoids rescanning all previous account/archive rows per batch.
     * @param list<string> $keys
     * @param array<string,array<string,string|null>> $details
     * @param array<string,string|null> $last Original storage bytes encoded as HEX.
     */
    private function after(array $keys, array $details, array $last): string
    {
        $alternatives = $equal = [];
        foreach ($keys as $key) {
            $hex = $last[$key] ?? null;
            if ($hex === null || !preg_match('/^(?:[a-fA-F0-9]{2})*$/D', $hex)) {
                throw new \RuntimeException('live_source_key_invalid');
            }
            $column = $details[$key];
            $value = $this->db->prepare('UNHEX(%s)', $hex);
            if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)\b/i', $column['Type'] ?? '')) {
                $value = 'CAST(' . $value . (str_contains(strtolower($column['Type']), 'unsigned') ? ' AS UNSIGNED)' : ' AS SIGNED)');
            } elseif ($column['Collation'] !== null) {
                $collation = $column['Collation'];
                $charset = explode('_', $collation, 2)[0];
                if (!preg_match('/^[a-zA-Z0-9_]+$/D', $collation) || !preg_match('/^[a-zA-Z0-9]+$/D', $charset)) {
                    throw new \RuntimeException('live_source_key_invalid');
                }
                $value = 'CONVERT(' . $value . ' USING ' . $charset . ') COLLATE ' . $collation;
            }
            $field = $this->db->prepare('s.%i', $key);
            $alternatives[] = '(' . implode(' AND ', [...$equal, $field . '>' . $value]) . ')';
            $equal[] = $field . '=' . $value;
        }
        return '(' . implode(' OR ', $alternatives) . ')';
    }

    public function rollback(): void
    {
        $this->db->locked(function (): void {
            $state = $this->state();
            if ($state === null || $state['state'] !== 'active' || !hash_equals($state['rollback_guard'] ?? '', $this->guard())) {
                throw new \RuntimeException('new_writes_prevent_rollback');
            }
            $rename = [];
            foreach ($state['tables'] as $suffix => $table) {
                if ($this->db->exists($table['target']) || $this->shape($table['backup']) !== $table['source_compatible_shape']) {
                    throw new \RuntimeException('live_rollback_requires_review');
                }
                $rename[] = $this->db->prepare('%i TO %i', $this->db->table($suffix), $table['target']);
                $rename[] = $this->db->prepare('%i TO %i', $table['backup'], $this->db->table($suffix));
            }
            $state['state'] = 'reverting';
            $this->save($state);
            $this->db->execute('RENAME TABLE ' . implode(',', $rename));
            $this->finishRollback($state);
        }, true);
    }

    /** @param array<string,mixed> $state */
    private function finishRollback(array $state): void
    {
        $this->db->execute('START TRANSACTION');
        try {
            foreach ($state['archives'] as $snapshot) {
                $run = (new Archive($this->db, $this->clock, $this->random))->run($snapshot);
                $this->db->setOption('cor_mgmt_archive_active_' . $run['race_id'], '');
            }
            $this->db->setOption('cor_mgmt_runtime_generation', '');
            $state['state'] = 'rolled_back';
            $this->save($state);
            $this->db->execute('COMMIT');
        } catch (\Throwable $error) {
            $this->db->execute('ROLLBACK');
            throw $error;
        }
    }

    public function runtimeReady(): bool
    {
        $state = $this->state();
        if ($state === null || in_array($state['state'], ['rolled_back','abandoned'], true)) {
            return ($this->db->option('cor_mgmt_runtime_generation') ?? '') === '';
        }
        if ($state['state'] !== 'active' || $this->db->option('cor_mgmt_runtime_generation') !== $state['generation']) {
            return false;
        }
        foreach ($state['tables'] as $suffix => $table) {
            if ($this->shape($this->db->table($suffix)) !== $table['target_shape']) {
                return false;
            }
        }
        return true;
    }

    /** Stop preparation without deleting copies or changing the authoritative tables. */
    public function abandon(): void
    {
        $this->db->locked(function (): void {
            $state = $this->state();
            if ($state === null || !in_array($state['state'], ['prepare','copy','verify','ready'], true)) {
                throw new \RuntimeException('live_transition_cannot_abandon');
            }
            foreach ($state['tables'] as $table) {
                if ($this->db->exists($table['backup'])) {
                    throw new \RuntimeException('live_switch_requires_review');
                }
            }
            $state['state'] = 'abandoned';
            $this->save($state);
        }, true);
    }

    /** CREATE LIKE does not preserve triggers or external FK relationships: never silently detach them. */
    private function assertSourceDependencies(): void
    {
        foreach (Catalogue::legacy() as $suffix => $_) {
            $table = $this->db->table($suffix);
            $triggers = $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=%s', $table));
            $relations = $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE (TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL) OR (REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=%s)', $table, $table));
            if ($triggers !== '0' || $relations !== '0') {
                throw new \RuntimeException('live_source_dependencies_require_review');
            }
        }
    }
}
