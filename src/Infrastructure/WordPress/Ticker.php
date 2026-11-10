<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Domain\Ticker\Content;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\TickerSchema;

/** One writer, explicit event identity, replay protection and append-only decisions. */
final class Ticker
{
    private readonly \Closure $access;
    /** @param callable(int,int,string):bool $access actor, race, operation */
    public function __construct(private readonly Database $db, private readonly Clock $clock, callable $access)
    {
        $this->access = \Closure::fromCallable($access);
    }

    public function native(): bool
    {
        return TickerSchema::ready($this->db) && (new TickerImport($this->db))->status()['state'] === 'native';
    }

    /** @return array<string,string|null> */
    public function post(int $id): array
    {
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->db->table('cor_mgmt_ticker_posts'), $id))[0] ?? [];
    }

    /** @return list<array<string,string|null>> */
    public function feed(int $race, int $team = 0, int $route = 0, int $page = 1, int $limit = 10, bool $moderation = false, int $actor = 0, int $parent = 0, string $kind = ''): array
    {
        $archive = new Archive($this->db, $this->clock, new \Cor\Mgmt\Infrastructure\SecureRandom());
        $snapshot = $archive->published($race);
        if ($snapshot !== '') {
            if ($moderation && !($this->access)($actor, $race, 'review')) {
                throw new \RuntimeException('forbidden');
            }
            return $archive->tickerFeed($snapshot, $team, $route, $page, $limit, $parent, $kind, $moderation);
        }
        if (!$this->native()) {
            if ($moderation) {
                throw new \RuntimeException('ticker_transition_required');
            }
            return $this->legacyFeed($race, $team, $route, $page, $limit, $parent, $kind);
        }
        if ($moderation && !($this->access)($actor, $race, 'review')) {
            throw new \RuntimeException('forbidden');
        }
        if ($parent < 0 && !$moderation) {
            throw new \RuntimeException('forbidden');
        }
        $sql = $this->db->prepare('SELECT p.* FROM %i p WHERE p.race_id=%d', $this->db->table('cor_mgmt_ticker_posts'), $race);
        if ($parent >= 0) {
            $sql .= $this->db->prepare(' AND p.parent_id=%d', $parent);
        }
        if ($parent > 0 && !$moderation) {
            $root = $this->post($parent);
            if (!$root || $root['visibility'] !== 'visible' || (int) $root['race_id'] !== $race) {
                return [];
            }
        }
        if ($kind !== '') {
            $sql .= $this->db->prepare(' AND p.kind=%s', $kind);
        }
        if ($parent === 0) {
            $sql .= " AND p.kind<>'comment'";
        }
        if (!$moderation) {
            $sql .= " AND p.visibility='visible'";
        }
        if ($team > 0 || $route > 0) {
            // Historical U_ references can be associated only by explicit memberships of this event.
            $sql .= $this->db->prepare(' AND (p.team_id IN (SELECT t.id FROM %i t WHERE t.race_id=%d', $this->db->table('h3_mgmt_teams'), $race);
            $sql .= $team > 0 ? $this->db->prepare(' AND t.id=%d)', $team) : $this->db->prepare(' AND t.route_id=%d)', $route);
            $sql .= $this->db->prepare(' OR (p.team_id=0 AND p.actor_id IN (SELECT m.user_id FROM %i m JOIN %i t ON t.id=m.team_id WHERE t.race_id=%d', $this->db->table('h3_mgmt_teammates'), $this->db->table('h3_mgmt_teams'), $race);
            $sql .= $team > 0 ? $this->db->prepare(' AND t.id=%d)))', $team) : $this->db->prepare(' AND t.route_id=%d)))', $route);
        }
        $limit = max(1, min(100, $limit));
        $sql .= $this->db->prepare(' ORDER BY p.id DESC LIMIT %d OFFSET %d', $limit, (max(1, min(100000, $page)) - 1) * $limit);
        return $this->db->rows($sql);
    }

    /** Read-only compatibility before explicit transfer, with no gateway or write side effects.
     * @return list<array<string,string|null>>
     */
    private function legacyFeed(int $race, int $team, int $route, int $page, int $limit, int $parent, string $kind): array
    {
        $table = $this->db->table('sms_ticker');
        if (!$this->db->exists($table)) {
            return [];
        }
        $columns = array_column($this->db->rows($this->db->prepare('SHOW COLUMNS FROM %i', $table)), 'Field');
        if (!in_array('race_id', $columns, true)) {
            return [];
        }
        if ($parent > 0 && !in_array('img', $columns, true)) {
            return [];
        }
        $sender = in_array('send_from', $columns, true) ? 'send_from' : 'from';
        $sql = $this->db->prepare('SELECT * FROM %i WHERE race_id=%d', $table, $race);
        $sql .= $parent > 0 ? $this->db->prepare(' AND type>=100 AND img=%s', (string) $parent) : ' AND type<100';
        if ($kind === 'location') {
            $sql .= ' AND type=4';
        }
        if ($team > 0 || $route > 0) {
            $teams = $this->db->prepare('SELECT t.id FROM %i t WHERE t.race_id=%d', $this->db->table('h3_mgmt_teams'), $race)
                . ($team > 0 ? $this->db->prepare(' AND t.id=%d', $team) : $this->db->prepare(' AND t.route_id=%d', $route));
            $sql .= $this->db->prepare(" AND (%i IN (SELECT CONCAT('T_',t.id) FROM %i t WHERE t.id IN (", $sender, $this->db->table('h3_mgmt_teams')) . $teams . '))'
                . $this->db->prepare(" OR %i IN (SELECT CONCAT('U_',m.user_id) FROM %i m WHERE m.team_id IN (", $sender, $this->db->table('h3_mgmt_teammates')) . $teams . ')))';
        }
        $limit = max(1, min(100, $limit));
        $sql .= $this->db->prepare(' ORDER BY id DESC LIMIT %d OFFSET %d', $limit, (max(1, min(100000, $page)) - 1) * $limit);
        $out = [];
        foreach ($this->db->rows($sql) as $row) {
            $post = Content::legacy($row);
            $actor = (string) Content::identity($post['author'], 'U');
            $teamId = (string) Content::identity($post['author'], 'T');
            $out[] = ['id' => $row['id'], 'race_id' => (string) $post['race'], 'actor_id' => $actor, 'team_id' => $teamId,
                'author_ref' => $post['author'], 'kind' => $post['kind'], 'body' => $post['body'], 'media_ref' => $post['media'], 'attachment_id' => '0',
                'parent_id' => (string) $post['parent'], 'created_at' => (string) $post['created'], 'visibility' => 'visible', 'revision' => '0',
                'source_hash' => '', 'source_payload' => Content::snapshot($row), 'issues' => json_encode($post['issues'], JSON_THROW_ON_ERROR)];
        }
        return $out;
    }

    public function create(int $actor, int $race, string $kind, string $body, string $media, int $attachment, int $parent, string $request): int
    {
        if (!in_array($kind, ['text','comment','image','location','video'], true) || strlen($body) > 10000 || str_contains($body, "\0") || !preg_match('/^[a-f0-9]{32}$/D', $request)) {
            throw new \InvalidArgumentException('invalid_ticker_content');
        }
        if ($kind === 'location') {
            $media = Content::coordinates($media);
        } elseif ($kind === 'video') {
            $media = Content::video($media);
        } elseif ($kind !== 'image') {
            $media = '';
        }
        if (in_array($kind, ['text','comment'], true) && trim($body) === '') {
            throw new \InvalidArgumentException('empty_ticker_content');
        }
        return $this->change(function () use ($actor, $race, $kind, $body, $media, $attachment, $parent, $request): int {
            $this->authorize($actor, $race, $kind === 'comment' ? 'comment' : 'write');
            if ($kind === 'image' && (!$attachment || !($this->access)($actor, $race, 'attachment:' . $attachment) || wp_get_attachment_url($attachment) !== $media)) {
                throw new \RuntimeException('foreign_ticker_image');
            }
            $key = $actor . ':' . $request;
            $payload = json_encode([$race, $kind, $body, $media, $attachment, $parent], JSON_THROW_ON_ERROR);
            $old = $this->db->rows($this->db->prepare('SELECT post_id,payload FROM %i WHERE request_key=%s', $this->db->table('cor_mgmt_ticker_history'), $key))[0] ?? null;
            if ($old !== null) {
                if ($old['payload'] !== $payload) {
                    throw new \RuntimeException('ticker_request_conflict');
                }
                return (int) $old['post_id'];
            }
            if ($kind === 'comment') {
                $root = $this->post($parent);
                if (!$root || (int) $root['race_id'] !== $race || (int) $root['parent_id'] !== 0 || $root['visibility'] !== 'visible') {
                    throw new \RuntimeException('invalid_ticker_parent');
                }
            } elseif ($parent !== 0) {
                throw new \RuntimeException('invalid_ticker_parent');
            }
            $teams = $this->db->rows($this->db->prepare('SELECT DISTINCT t.id FROM %i t JOIN %i m ON m.team_id=t.id WHERE t.race_id=%d AND m.user_id=%d ORDER BY t.id LIMIT 2', $this->db->table('h3_mgmt_teams'), $this->db->table('h3_mgmt_teammates'), $race, $actor));
            if (count($teams) > 1) {
                throw new \RuntimeException('ambiguous_ticker_team');
            }
            $team = (int) ($teams[0]['id'] ?? 0);
            $this->db->execute($this->db->prepare("INSERT INTO %i (race_id,actor_id,team_id,author_ref,kind,body,media_ref,attachment_id,parent_id,created_at,visibility,revision,source_hash,source_payload,issues) VALUES (%d,%d,%d,%s,%s,%s,%s,%d,%d,%d,'visible',1,'','','[]')", $this->db->table('cor_mgmt_ticker_posts'), $race, $actor, $team, 'U_' . $actor, $kind, $body, $media, $kind === 'image' ? $attachment : 0, $parent, $this->clock->now()));
            $id = (int) $this->db->wpdb->insert_id;
            $this->audit($id, $actor, 'created', $key, $payload);
            return $id;
        });
    }

    public function moderate(int $actor, int $id, int $revision, bool $visible, string $request): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $request)) {
            throw new \InvalidArgumentException('invalid_ticker_request');
        }
        $this->change(function () use ($actor, $id, $revision, $visible, $request): void {
            $row = $this->post($id);
            if (!$row) {
                throw new \RuntimeException('ticker_not_found');
            }
            $this->authorize($actor, (int) $row['race_id'], 'moderate');
            $key = $actor . ':' . $request;
            $payload = json_encode([$id, $revision, $visible], JSON_THROW_ON_ERROR);
            $old = $this->db->rows($this->db->prepare('SELECT payload FROM %i WHERE request_key=%s', $this->db->table('cor_mgmt_ticker_history'), $key))[0] ?? null;
            if ($old !== null) {
                if ($old['payload'] !== $payload) {
                    throw new \RuntimeException('ticker_request_conflict');
                }
                return;
            }
            if ((int) $row['revision'] !== $revision) {
                throw new \RuntimeException('stale_ticker_post');
            }
            $this->db->execute($this->db->prepare('UPDATE %i SET visibility=%s,revision=revision+1 WHERE id=%d', $this->db->table('cor_mgmt_ticker_posts'), $visible ? 'visible' : 'hidden', $id));
            $this->audit($id, $actor, $visible ? 'restored' : 'hidden', $key, $payload);
        });
    }

    private function authorize(int $actor, int $race, string $action): void
    {
        if ($action !== 'review' && Archive::locked($this->db, $race)) {
            throw new \RuntimeException('archive_locked');
        }
        if ($actor < 1 || $race < 1 || !($this->access)($actor, $race, $action)) {
            throw new \RuntimeException('forbidden');
        }
    }

    public function replaceImage(int $actor, int $id, int $revision, int $attachment, string $request): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $request)) {
            throw new \InvalidArgumentException('invalid_ticker_request');
        }
        $this->change(function () use ($actor, $id, $revision, $attachment, $request): void {
            $row = $this->post($id);
            $race = (int) ($row['race_id'] ?? 0);
            $this->authorize($actor, $race, 'write');
            if (!$row || $row['kind'] !== 'image' || ((int) $row['actor_id'] !== $actor && !($this->access)($actor, $race, 'moderate')) || !($this->access)($actor, $race, 'attachment:' . $attachment)) {
                throw new \RuntimeException('foreign_ticker_image');
            }
            $key = $actor . ':' . $request;
            $payload = json_encode([$id, $revision, $attachment, $row['media_ref']], JSON_THROW_ON_ERROR);
            if ($this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE request_key=%s', $this->db->table('cor_mgmt_ticker_history'), $key)) !== '0' || (int) $row['revision'] !== $revision) {
                throw new \RuntimeException('stale_ticker_post');
            }
            $url = wp_get_attachment_url($attachment);
            if (!$url) {
                throw new \RuntimeException('invalid_ticker_image');
            }
            $this->db->execute($this->db->prepare('UPDATE %i SET media_ref=%s,attachment_id=%d,revision=revision+1 WHERE id=%d', $this->db->table('cor_mgmt_ticker_posts'), $url, $attachment, $id));
            $this->audit($id, $actor, 'image_rotated', $key, $payload);
        });
    }

    private function audit(int $id, int $actor, string $operation, string $key, string $payload): void
    {
        $this->db->execute($this->db->prepare('INSERT INTO %i (post_id,actor_id,created_at,operation,request_key,payload) VALUES (%d,%d,%d,%s,%s,%s)', $this->db->table('cor_mgmt_ticker_history'), $id, $actor, $this->clock->now(), $operation, $key, $payload));
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function change(callable $operation): mixed
    {
        return $this->db->locked(function () use ($operation): mixed {
            if (!$this->native()) {
                throw new \RuntimeException('ticker_transition_required');
            }
            foreach (TickerSchema::definitions() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            $nested = $this->db->scalar('SELECT @@in_transaction') === '1';
            $this->db->execute($nested ? 'SAVEPOINT cor_ticker' : 'START TRANSACTION');
            try {
                $result = $operation();
                $this->db->assertLock();
                $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_ticker' : 'COMMIT');
                return $result;
            } catch (\Throwable $error) {
                $this->db->execute($nested ? 'ROLLBACK TO SAVEPOINT cor_ticker' : 'ROLLBACK');
                throw $error;
            }
        });
    }
}
