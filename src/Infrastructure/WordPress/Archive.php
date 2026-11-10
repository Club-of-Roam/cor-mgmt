<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ArchiveSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Explicit copy, independent second pass and immutable publication. No score calculation. */
final class Archive
{
    private readonly ArchiveMedia $media;

    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random, ?ArchiveMedia $media = null)
    {
        $this->media = $media ?? new ArchiveMedia();
    }

    /** @return array<string,string|null> */
    public function run(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !ArchiveSchema::ready($this->db)) {
            throw new \RuntimeException('archive_setup_required');
        }
        $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE snapshot_id=%s', $this->db->table('cor_mgmt_archive_runs'), $id))[0] ?? [];
        if (!$row || !in_array($row['state'], ['copy','verify','ready','sealed','abandoned'], true)) {
            throw new \RuntimeException('archive_state_invalid');
        }
        return $row;
    }

    public static function locked(Database $db, int $race): bool
    {
        if (!ArchiveSchema::ready($db)) {
            return false;
        }
        return $db->scalar($db->prepare("SELECT snapshot_id FROM %i WHERE race_id=%d AND state IN ('copy','verify','ready') LIMIT 1", $db->table('cor_mgmt_archive_runs'), $race)) !== null
            || ($db->option('cor_mgmt_archive_active_' . $race) ?? '') !== '';
    }

    public function start(int $race, int $actor, string $mode): string
    {
        if ($race < 1 || $actor < 1 || !in_array($mode, ['completion','migration'], true)) {
            throw new \RuntimeException('invalid_archive_request');
        }
        return $this->db->locked(function () use ($race, $actor, $mode): string {
            foreach (ArchiveSchema::definitions() as $table => $definition) {
                (new Inspector($this->db))->verify($this->db->table($table), $definition);
            }
            if ($this->db->scalar('SELECT @@in_transaction') !== '0') {
                throw new \RuntimeException('archive_requires_independent_request');
            }
            $existing = $this->db->rows($this->db->prepare("SELECT snapshot_id,mode,state FROM %i WHERE race_id=%d AND state<>'abandoned' ORDER BY created_at DESC,snapshot_id DESC LIMIT 1", $this->db->table('cor_mgmt_archive_runs'), $race))[0] ?? [];
            if (($existing['state'] ?? '') === 'sealed' && $this->published($race) === '') {
                $existing = []; // A rolled-back snapshot remains immutable; a new attempt gets a new identity.
            }
            if ($existing) {
                if ($existing['mode'] !== $mode) {
                    throw new \RuntimeException('archive_mode_conflict');
                }
                return $existing['snapshot_id'] ?? '';
            }
            $settings = json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), $race)) ?? '', true);
            if (!is_array($settings) || (int) ($settings['status'] ?? -1) !== 3) {
                throw new \RuntimeException('archive_started_race_required');
            }
            if (!(new Ticker($this->db, $this->clock, static fn (): bool => false))->native()) {
                throw new \RuntimeException('archive_ticker_transition_required');
            }
            $id = bin2hex($this->random->bytes(16));
            $plan = (new ArchiveSource($this->db, $race))->plan();
            $this->db->execute($this->db->prepare("INSERT INTO %i (snapshot_id,race_id,mode,state,plan,kind_index,`cursor`,copied,created_at,actor_id,digest) VALUES (%s,%d,%s,'copy',%s,0,'',0,%d,%d,'')", $this->db->table('cor_mgmt_archive_runs'), $id, $race, $mode, json_encode($plan, JSON_THROW_ON_ERROR), $this->clock->now(), $actor));
            return $id;
        });
    }

    /** @return array<string,string|null> */
    public function step(string $id, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \RuntimeException('invalid_archive_batch');
        }
        return $this->db->locked(function () use ($id, $limit): array {
            $run = $this->run($id);
            if (in_array($run['state'], ['ready','sealed'], true)) {
                return $run;
            }
            if ($run['state'] === 'abandoned' || $this->db->scalar('SELECT @@in_transaction') !== '0') {
                throw new \RuntimeException('archive_not_resumable');
            }
            $source = new ArchiveSource($this->db, (int) $run['race_id']);
            $plan = json_decode($run['plan'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            if ($plan !== $source->plan()) {
                throw new \RuntimeException('archive_source_changed');
            }
            $kinds = array_keys($plan);
            $index = (int) $run['kind_index'];
            if ($index === count($kinds)) {
                if ($run['state'] === 'copy') {
                    $this->db->execute($this->db->prepare("UPDATE %i SET state='verify',kind_index=0,`cursor`='' WHERE snapshot_id=%s", $this->db->table('cor_mgmt_archive_runs'), $id));
                } else {
                    $digest = $this->digest($id);
                    $this->db->execute($this->db->prepare("UPDATE %i SET state='ready',digest=%s WHERE snapshot_id=%s", $this->db->table('cor_mgmt_archive_runs'), $digest, $id));
                }
                return $this->run($id);
            }
            $kind = $kinds[$index];
            $batch = $source->batch($kind, $run['cursor'] ?? '', $limit);
            $this->db->execute('START TRANSACTION');
            try {
                $cursor = $run['cursor'] ?? '';
                foreach ($batch as $row) {
                    if ($run['state'] === 'copy') {
                        $this->db->execute($this->db->prepare('INSERT INTO %i (snapshot_id,kind,source_key,payload,row_hash) VALUES (%s,%s,%s,%s,%s)', $this->db->table('cor_mgmt_archive_rows'), $id, $kind, $row['key'], $row['payload'], $row['hash']));
                        $this->images($id, $kind, $row['view']);
                    } else {
                        $stored = $this->db->rows($this->db->prepare('SELECT payload,row_hash FROM %i WHERE snapshot_id=%s AND kind=%s AND source_key=%s', $this->db->table('cor_mgmt_archive_rows'), $id, $kind, $row['key']))[0] ?? [];
                        if (($stored['payload'] ?? '') !== $row['payload'] || ($stored['row_hash'] ?? '') !== $row['hash']) {
                            throw new \RuntimeException('archive_content_changed');
                        }
                    }
                    $cursor = $row['key'];
                }
                if (count($batch) < $limit) {
                    if ($run['state'] === 'verify' && (int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE snapshot_id=%s AND kind=%s', $this->db->table('cor_mgmt_archive_rows'), $id, $kind)) !== $plan[$kind]['count']) {
                        throw new \RuntimeException('archive_content_changed');
                    }
                    ++$index;
                    $cursor = '';
                }
                $this->db->execute($this->db->prepare('UPDATE %i SET kind_index=%d,`cursor`=%s,copied=copied+%d WHERE snapshot_id=%s', $this->db->table('cor_mgmt_archive_runs'), $index, $cursor, $run['state'] === 'copy' ? count($batch) : 0, $id));
                $this->db->assertLock();
                $this->db->execute('COMMIT');
            } catch (\Throwable $error) {
                $this->db->execute('ROLLBACK');
                throw $error;
            }
            return $this->run($id);
        });
    }

    /** @param array<string,string|null> $row */
    private function images(string $id, string $kind, array $row): void
    {
        foreach (['team_pic','logo_url','owner_pic','sponsor_pic','img_url'] as $field) {
            if (is_string($row[$field] ?? null) && $row[$field] !== '') {
                $this->image($id, $row[$field]);
            }
        }
        if ($kind === 'cor_mgmt_ticker_posts' && $row['kind'] === 'image' && !empty($row['media_ref'])) {
            $resolved = $row['media_ref'];
            $source = json_decode($row['source_payload'] ?? '', true);
            if (
                !str_contains($resolved, '/') && !str_contains($resolved, '\\') && is_array($source)
                && is_string($source['type'] ?? null) && base64_decode($source['type'], true) === '2'
            ) {
                $resolved = wp_upload_dir()['baseurl'] . '/mms/' . rawurlencode($resolved);
            }
            $this->image($id, $row['media_ref'], $resolved);
        }
        if ($kind === 'usermeta' && $row['meta_key'] === 'cor_mgmt_avatar') {
            if (!empty($row['meta_value']) && $row['meta_value'] !== 'none') {
                $this->image($id, $row['meta_value']);
            }
        }
        if ($kind === 'usermeta' && $row['meta_key'] === 'simple_local_avatar') {
            $value = $row['meta_value'] ?? '';
            if (is_serialized($value)) {
                $avatar = unserialize($value, ['allowed_classes' => false]);
                if (is_array($avatar) && is_string($avatar['full'] ?? null)) {
                    $this->image($id, $avatar['full']);
                }
            }
        }
    }

    private function image(string $id, string $reference, ?string $resolved = null): void
    {
        $hash = hash('sha256', $reference);
        if ($this->db->scalar($this->db->prepare('SELECT ref_hash FROM %i WHERE snapshot_id=%s AND ref_hash=%s', $this->db->table('cor_mgmt_archive_media'), $id, $hash)) !== null) {
            return;
        }
        $copy = $this->media->capture($resolved ?? $reference);
        $this->db->execute($this->db->prepare('INSERT INTO %i (snapshot_id,ref_hash,source_ref,file_name,file_hash,state) VALUES (%s,%s,%s,%s,%s,%s)', $this->db->table('cor_mgmt_archive_media'), $id, $hash, $reference, $copy['file_name'], $copy['file_hash'], $copy['state']));
    }

    private function digest(string $id): string
    {
        $context = hash_init('sha256');
        $cursor = '';
        do {
            $rows = $this->db->rows($this->db->prepare("SELECT CONCAT(kind,':',source_key) AS k,payload,row_hash FROM %i WHERE snapshot_id=%s AND CONCAT(kind,':',source_key)>%s ORDER BY k LIMIT 100", $this->db->table('cor_mgmt_archive_rows'), $id, $cursor));
            foreach ($rows as $row) {
                if (hash('sha256', $row['payload'] ?? '') !== $row['row_hash']) {
                    throw new \RuntimeException('archive_content_changed');
                }
                hash_update($context, $row['k'] . ':' . $row['row_hash'] . "\n");
                $cursor = $row['k'] ?? '';
            }
        } while (count($rows) === 100);
        $cursor = '';
        do {
            $rows = $this->db->rows($this->db->prepare('SELECT ref_hash,source_ref,file_name,file_hash,state FROM %i WHERE snapshot_id=%s AND ref_hash>%s ORDER BY ref_hash LIMIT 100', $this->db->table('cor_mgmt_archive_media'), $id, $cursor));
            foreach ($rows as $row) {
                if ($row['state'] === 'copied' && $this->media->url($row['file_name'] ?? '', $row['file_hash'] ?? '') === '') {
                    throw new \RuntimeException('archive_image_changed');
                }
                hash_update($context, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
                $cursor = $row['ref_hash'] ?? '';
            }
        } while (count($rows) === 100);
        return hash_final($context);
    }

    /** Called only under the shared maintenance lock, after verifying the complete snapshot. */
    public function seal(string $id, bool $migration = false): void
    {
        $this->db->assertLock();
        $run = $this->run($id);
        if ($run['state'] === 'sealed') {
            if (($run['mode'] === 'migration') !== $migration) {
                throw new \RuntimeException('archive_mode_conflict');
            }
            $this->db->setOption('cor_mgmt_archive_active_' . $run['race_id'], $id);
            return;
        }
        if ($run['state'] !== 'ready' || ($run['mode'] === 'migration') !== $migration || !hash_equals($run['digest'] ?? '', $this->digest($id))) {
            throw new \RuntimeException('archive_not_verified');
        }
        $race = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->db->table('h3_mgmt_races'), (int) $run['race_id']))[0] ?? [];
        $settings = json_decode($race['setting'] ?? '', true);
        if (!is_array($settings) || (int) ($settings['status'] ?? -1) !== ($migration ? 4 : 3)) {
            throw new \RuntimeException('archive_source_changed');
        }
        if (!$migration) {
            $settings['status'] = 4;
            $this->db->execute($this->db->prepare('UPDATE %i SET setting=%s,active=0 WHERE id=%d', $this->db->table('h3_mgmt_races'), json_encode($settings, JSON_THROW_ON_ERROR), (int) $run['race_id']));
        }
        $this->db->execute($this->db->prepare("UPDATE %i SET state='sealed' WHERE snapshot_id=%s", $this->db->table('cor_mgmt_archive_runs'), $id));
        $this->db->setOption('cor_mgmt_archive_active_' . $run['race_id'], $id);
    }

    public function complete(string $id): void
    {
        $this->db->locked(function () use ($id): void {
            $run = $this->run($id);
            $raceTable = $this->db->table('h3_mgmt_races');
            foreach ([$raceTable, $this->db->wpdb->options] as $table) {
                if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) !== 'InnoDB') {
                    throw new \RuntimeException('archive_transactional_storage_required');
                }
            }
            if ($run['mode'] !== 'completion' || $this->db->scalar('SELECT @@in_transaction') !== '0') {
                throw new \RuntimeException('invalid_archive_request');
            }
            if ($run['state'] !== 'sealed') {
                $this->verifySource($id);
            }
            $this->db->execute('START TRANSACTION');
            try {
                $this->seal($id);
                $this->db->execute('COMMIT');
            } catch (\Throwable $error) {
                $this->db->execute('ROLLBACK');
                throw $error;
            }
        });
    }

    /** Final source comparison under maintenance, immediately before publication/name swap. */
    public function verifySource(string $id): void
    {
        $this->db->assertLock();
        $run = $this->run($id);
        if (!in_array($run['state'], ['ready','sealed'], true)) {
            throw new \RuntimeException('archive_not_verified');
        }
        $source = new ArchiveSource($this->db, (int) $run['race_id']);
        $plan = json_decode($run['plan'] ?? '', true, 512, JSON_THROW_ON_ERROR);
        if ($plan !== $source->plan()) {
            throw new \RuntimeException('archive_source_changed');
        }
        foreach ($plan as $kind => $definition) {
            $cursor = '';
            $count = 0;
            do {
                $batch = $source->batch($kind, $cursor);
                foreach ($batch as $row) {
                    $stored = $this->db->rows($this->db->prepare('SELECT payload,row_hash FROM %i WHERE snapshot_id=%s AND kind=%s AND source_key=%s', $this->db->table('cor_mgmt_archive_rows'), $id, $kind, $row['key']))[0] ?? [];
                    if (($stored['payload'] ?? '') !== $row['payload'] || ($stored['row_hash'] ?? '') !== $row['hash']) {
                        throw new \RuntimeException('archive_content_changed');
                    }
                    $cursor = $row['key'];
                    ++$count;
                }
            } while (count($batch) === 100);
            if ($count !== $definition['count']) {
                throw new \RuntimeException('archive_source_changed');
            }
        }
    }

    public function abandon(string $id): void
    {
        $this->db->locked(function () use ($id): void {
            if ($this->run($id)['state'] === 'sealed') {
                throw new \RuntimeException('archive_immutable');
            }
            $this->db->execute($this->db->prepare("UPDATE %i SET state='abandoned' WHERE snapshot_id=%s", $this->db->table('cor_mgmt_archive_runs'), $id));
        });
    }

    public function published(int $race): string
    {
        $id = $this->db->option('cor_mgmt_archive_active_' . $race) ?? '';
        return $id !== '' && $this->run($id)['state'] === 'sealed' && (int) $this->run($id)['race_id'] === $race ? $id : '';
    }

    /** @return list<array<string,string|null>> */
    public function rows(string $id, string $kind, int $page = 1): array
    {
        if ($this->run($id)['state'] !== 'sealed' || !preg_match('/^[a-z_]{1,32}$/D', $kind)) {
            throw new \RuntimeException('archive_not_published');
        }
        $rows = $this->db->rows($this->db->prepare('SELECT payload,row_hash FROM %i WHERE snapshot_id=%s AND kind=%s ORDER BY source_key LIMIT 100 OFFSET %d', $this->db->table('cor_mgmt_archive_rows'), $id, $kind, (max(1, min(100000, $page)) - 1) * 100));
        $out = [];
        foreach ($rows as $row) {
            if (hash('sha256', $row['payload'] ?? '') !== $row['row_hash']) {
                throw new \RuntimeException('archive_content_changed');
            }
            $payload = json_decode($row['payload'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $out[] = $payload['view'];
        }
        return $out;
    }

    public function imageUrl(string $id, string $reference): string
    {
        $row = $this->db->rows($this->db->prepare('SELECT file_name,file_hash,state FROM %i WHERE snapshot_id=%s AND ref_hash=%s', $this->db->table('cor_mgmt_archive_media'), $id, hash('sha256', $reference)))[0] ?? [];
        return ($row['state'] ?? '') === 'copied' ? $this->media->url($row['file_name'] ?? '', $row['file_hash'] ?? '') : '';
    }

    /** @return list<array<string,string|null>> */
    public function all(string $id, string $kind): array
    {
        $out = [];
        for ($page = 1; $page <= 100000; ++$page) {
            $batch = $this->rows($id, $kind, $page);
            array_push($out, ...$batch);
            if (count($batch) < 100) {
                return $out;
            }
        }
        throw new \RuntimeException('archive_display_limit');
    }

    /** @return array<string,string|null> */
    public function row(string $id, string $kind, int $key): array
    {
        if ($this->run($id)['state'] !== 'sealed') {
            throw new \RuntimeException('archive_not_published');
        }
        $stored = $this->db->rows($this->db->prepare('SELECT payload,row_hash FROM %i WHERE snapshot_id=%s AND kind=%s AND source_key=%s', $this->db->table('cor_mgmt_archive_rows'), $id, $kind, str_pad((string) $key, 20, '0', STR_PAD_LEFT)))[0] ?? [];
        if (!$stored) {
            return [];
        }
        if (hash('sha256', $stored['payload'] ?? '') !== $stored['row_hash']) {
            throw new \RuntimeException('archive_content_changed');
        }
        return json_decode($stored['payload'] ?? '', true, 512, JSON_THROW_ON_ERROR)['view'];
    }

    /** @return list<array<string,string|null>> */
    public function tickerFeed(string $id, int $team, int $route, int $page, int $limit, int $parent, string $kind, bool $moderation = false): array
    {
        if ($this->run($id)['state'] !== 'sealed') {
            throw new \RuntimeException('archive_not_published');
        }
        if ($parent < 0 && !$moderation) {
            throw new \RuntimeException('forbidden');
        }
        $sql = $this->db->prepare("SELECT payload,row_hash FROM %i WHERE snapshot_id=%s AND kind='cor_mgmt_ticker_posts'", $this->db->table('cor_mgmt_archive_rows'), $id);
        if (!$moderation) {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.visibility'))='visible'";
        }
        if ($parent >= 0) {
            $sql .= $this->db->prepare(" AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.parent_id'))=%s", (string) $parent);
        }
        if ($parent === 0) {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.kind'))<>'comment'";
        } elseif ($parent > 0 && ($this->row($id, 'cor_mgmt_ticker_posts', $parent)['visibility'] ?? '') !== 'visible') {
            return [];
        }
        if ($kind !== '') {
            $sql .= $this->db->prepare(" AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.kind'))=%s", $kind);
        }
        if ($team > 0 || $route > 0) {
            $teams = array_column(array_filter($this->all($id, 'team'), static fn (array $row): bool => $team > 0 ? (int) $row['id'] === $team : (int) $row['route_id'] === $route), 'id');
            $actors = array_column(array_filter($this->all($id, 'teammate'), static fn (array $row): bool => in_array($row['team_id'], $teams, true)), 'user_id');
            $conditions = [];
            foreach ($teams as $memberTeam) {
                $conditions[] = $this->db->prepare("JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.team_id'))=%s", $memberTeam ?? '');
            }
            foreach ($actors as $actor) {
                $conditions[] = $this->db->prepare("(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.team_id'))='0' AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.view.actor_id'))=%s)", $actor ?? '');
            }
            if (!$conditions) {
                return [];
            }
            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
        $sql .= $this->db->prepare(' ORDER BY source_key DESC LIMIT %d OFFSET %d', max(1, min(100, $limit)), (max(1, min(100000, $page)) - 1) * max(1, min(100, $limit)));
        $out = [];
        foreach ($this->db->rows($sql) as $stored) {
            if (hash('sha256', $stored['payload'] ?? '') !== $stored['row_hash']) {
                throw new \RuntimeException('archive_content_changed');
            }
            $out[] = json_decode($stored['payload'] ?? '', true, 512, JSON_THROW_ON_ERROR)['view'];
        }
        return $out;
    }
}
