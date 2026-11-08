<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ExchangeSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Global notice board; membership only filters the public list, not ownership.
 * @phpstan-type ExchangeContext array{row:array<string,string|null>,revision:string}
 */
final class Exchange
{
    private readonly \Closure $moderator;
    /** @param callable(int):bool $moderator */
    public function __construct(private readonly Database $db, private readonly Clock $clock, callable $moderator)
    {
        $this->moderator = \Closure::fromCallable($moderator);
    }
    /** @return list<array<string,string|null>> */
    public function messages(int $race, int $page = 1, bool $all = false, int $actor = 0): array
    {
        if ($all && !($this->moderator)($actor)) {
            throw new CommunicationException('forbidden');
        }
        $base = $this->db->prepare('SELECT x.* FROM %i x LEFT JOIN %i u ON u.ID=x.user_id WHERE ', $this->db->table('h3_mgmt_xchange'), $this->db->wpdb->users);
        $where = $all ? '1=1' : $this->db->prepare('u.ID IS NOT NULL AND x.time>%s AND NOT EXISTS (SELECT 1 FROM %i m JOIN %i t ON t.id=m.team_id WHERE m.user_id=x.user_id AND t.race_id=%d)', gmdate('Y-01-01', $this->clock->now()), $this->db->table('h3_mgmt_teammates'), $this->db->table('h3_mgmt_teams'), $race);
        return $this->db->rows($base . $where . $this->db->prepare(' ORDER BY x.time DESC,x.id DESC LIMIT 30 OFFSET %d', (max(1, $page) - 1) * 30));
    }
    /** @return ExchangeContext */
    public function context(int $actor, int $id = 0, bool $moderation = false): array
    {
        if ($actor < 1 || !get_userdata($actor) || ($moderation && !($this->moderator)($actor))) {
            throw new CommunicationException('forbidden');
        }
        $row = $id > 0 ? ($this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->db->table('h3_mgmt_xchange'), $id))[0] ?? []) : ($this->db->rows($this->db->prepare('SELECT * FROM %i WHERE user_id=%d ORDER BY time DESC,id DESC LIMIT 1', $this->db->table('h3_mgmt_xchange'), $actor))[0] ?? []);
        if (($id > 0 && !$row) || ($row && (int) $row['user_id'] !== $actor && !$moderation)) {
            throw new CommunicationException('forbidden');
        }
        $keyId = (int) ($row['id'] ?? 0);
        $generation = ExchangeSchema::ready($this->db) ? $this->db->scalar($this->db->prepare('SELECT MAX(id) FROM %i WHERE message_id=%d OR (%d=0 AND actor_id=%d)', $this->db->table('cor_mgmt_exchange_history'), $keyId, $keyId, $actor)) : '';
        return ['row' => $row, 'revision' => hash('sha256', json_encode([$actor, $keyId, $moderation, $row, $generation], JSON_THROW_ON_ERROR))];
    }
    /** @return list<array<string,string|null>> */
    public function history(int $actor, int $id): array
    {
        if (!($this->moderator)($actor)) {
            throw new CommunicationException('forbidden');
        }
        return $this->db->rows($this->db->prepare('SELECT id,message_id,actor_id,created_at,operation FROM %i WHERE message_id=%d ORDER BY id DESC LIMIT 30', $this->db->table('cor_mgmt_exchange_history'), $id));
    }
    /** @return list<array<string,string|null>> */
    public function recentHistory(int $actor): array
    {
        if (!($this->moderator)($actor)) {
            throw new CommunicationException('forbidden');
        }
        return $this->db->rows($this->db->prepare('SELECT id,message_id,actor_id,created_at,operation FROM %i ORDER BY id DESC LIMIT 30', $this->db->table('cor_mgmt_exchange_history')));
    }
    public function save(int $actor, int $id, string $message, string $revision, bool $moderation = false): int
    {
        if (strlen($message) > 10000 || str_contains($message, "\0") || $id < 0) {
            throw new CommunicationException('invalid_exchange_message');
        }
        return $this->db->locked(function () use ($actor, $id, $message, $revision, $moderation): int {
            if (!ExchangeSchema::ready($this->db)) {
                throw new CommunicationException('exchange_setup_required');
            }
            foreach (ExchangeSchema::definitions() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->db->table('h3_mgmt_xchange'))) !== 'InnoDB') {
                throw new CommunicationException('transactional_exchange_storage_required');
            }
            $nested = $this->db->scalar('SELECT @@in_transaction') === '1';
            $this->db->execute($nested ? 'SAVEPOINT cor_exchange' : 'START TRANSACTION');
            try {
                $context = $this->context($actor, $id, $moderation);
                if (!hash_equals($context['revision'], $revision)) {
                    throw new CommunicationException('stale_exchange');
                }
                if ($id === 0 && $context['row']) {
                    throw new CommunicationException('existing_exchange_message');
                }
                $old = $context['row'];
                $operation = $id === 0 ? 'created' : (trim($message) === '' ? 'deleted' : 'updated');
                if ($id === 0 && trim($message) === '') {
                    $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_exchange' : 'COMMIT');
                    return 0;
                }
                $table = $this->db->table('h3_mgmt_xchange');
                if ($id === 0) {
                    $this->db->execute($this->db->prepare('INSERT INTO %i (user_id,message) VALUES (%d,%s)', $table, $actor, $message));
                    $id = (int) $this->db->wpdb->insert_id;
                } elseif (trim($message) === '') {
                    $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id=%d AND user_id=%d', $table, $id, (int) $old['user_id']));
                } else {
                    $this->db->execute($this->db->prepare('UPDATE %i SET message=%s WHERE id=%d AND user_id=%d', $table, $message, $id, (int) $old['user_id']));
                }
                $this->db->execute($this->db->prepare('INSERT INTO %i (message_id,actor_id,created_at,operation,payload) VALUES (%d,%d,%d,%s,%s)', $this->db->table('cor_mgmt_exchange_history'), $id, $actor, $this->clock->now(), $operation, json_encode(['before' => $old, 'message' => $message, 'moderation' => $moderation], JSON_THROW_ON_ERROR)));
                $this->db->assertLock();
                $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_exchange' : 'COMMIT');
                return $id;
            } catch (\Throwable $error) {
                $this->db->execute($nested ? 'ROLLBACK TO SAVEPOINT cor_exchange' : 'ROLLBACK');
                throw $error;
            }
        });
    }
}
