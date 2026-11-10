<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\MailQueueSchema;

/** Authorized, revision-checked event administration; no implicit batch delivery.
 * @phpstan-type QueueContext array{event:array<string,string|null>,writable:bool,enabled:bool,server_enabled:bool,revision:string,rows:list<array<string,string|null>>,selected:array<string,string|null>,history:list<array<string,string|null>>,decisions:list<array<string,string|null>>,page:int,pages:int,count:int}
 */
final class MailQueueManagement
{
    private readonly \Closure $authorize;
    private readonly \Closure $delivery;
    private readonly MailQueue $queue;

    /** @param callable(int,int,string):bool $authorize
     * @param callable():bool $delivery
     */
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random, private readonly MailTransport $transport, callable $authorize, callable $delivery)
    {
        $this->authorize = \Closure::fromCallable($authorize);
        $this->delivery = \Closure::fromCallable($delivery);
        $this->queue = new MailQueue($db, $clock);
    }

    private function allowed(int $race, int $actor, string $operation): void
    {
        if ($actor < 1 || !($this->authorize)($race, $actor, $operation)) {
            throw new CommunicationException('forbidden');
        }
    }

    private function option(int $race): string
    {
        return 'cor_mgmt_mail_delivery_' . $race;
    }

    /** @return QueueContext */
    public function context(int $race, int $actor, int $page = 1, int $message = 0): array
    {
        $this->allowed($race, $actor, 'view');
        if (!MailQueueSchema::ready($this->db)) {
            throw new CommunicationException('mail_queue_setup_required');
        }
        $templates = new ResponseTemplates($this->db, $this->clock, $this->authorize);
        $event = $templates->context($race, $actor);
        $generation = $this->db->scalar($this->db->prepare('SELECT MAX(h.id) FROM %i h INNER JOIN %i q ON q.id=h.message_id WHERE q.race_id=%d', $this->db->table('cor_mgmt_mail_history'), $this->db->table('cor_mgmt_mail_queue'), $race));
        $administration = $this->db->scalar($this->db->prepare("SELECT MAX(id) FROM %i WHERE race_id=%d AND kind='queue_admin'", $this->db->table('cor_mgmt_communication_history'), $race));
        $setting = $this->db->option($this->option($race));
        $enabled = $setting === 'enabled';
        $count = (int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=%d', $this->db->table('cor_mgmt_mail_queue'), $race));
        $page = max(1, min(max(1, (int) ceil($count / 30)), $page));
        $rows = $this->db->rows($this->db->prepare('SELECT id,action,recipient,language,status,attempts,reason,created_at FROM %i WHERE race_id=%d ORDER BY id DESC LIMIT 30 OFFSET %d', $this->db->table('cor_mgmt_mail_queue'), $race, ($page - 1) * 30));
        $selected = [];
        $history = [];
        if ($message > 0) {
            $selected = $this->db->rows($this->db->prepare('SELECT id,race_id,occurrence,action,recipient,language,template_revision,subject,message,status,attempts,reason FROM %i WHERE id=%d AND race_id=%d', $this->db->table('cor_mgmt_mail_queue'), $message, $race))[0] ?? [];
            if (!$selected) {
                throw new CommunicationException('missing_event_message');
            }
            $history = $this->db->rows($this->db->prepare('SELECT created_at,status,attempt,reason FROM %i WHERE message_id=%d ORDER BY id', $this->db->table('cor_mgmt_mail_history'), $message));
        }
        $decisions = $this->db->rows($this->db->prepare("SELECT actor_id,created_at,payload FROM %i WHERE race_id=%d AND kind='queue_admin' ORDER BY id DESC LIMIT 10", $this->db->table('cor_mgmt_communication_history'), $race));
        return ['event' => $event['race'], 'writable' => $event['writable'], 'enabled' => $enabled, 'server_enabled' => ($this->delivery)(), 'revision' => hash('sha256', json_encode([$event['revision'], $generation, $administration, $setting], JSON_THROW_ON_ERROR)), 'rows' => $rows, 'selected' => $selected, 'history' => $history, 'decisions' => $decisions, 'page' => $page, 'pages' => max(1, (int) ceil($count / 30)), 'count' => $count];
    }

    /** @return QueueContext */
    private function checked(int $race, int $actor, string $revision, int $message): array
    {
        $this->allowed($race, $actor, 'edit');
        $context = $this->context($race, $actor, 1, $message);
        if (!$context['writable'] || Archive::locked($this->db, $race)) {
            throw new CommunicationException('archive_locked');
        }
        if (!hash_equals((string) $context['revision'], $revision)) {
            throw new CommunicationException('stale_mail_queue');
        }
        return $context;
    }

    private function journal(int $race, int $actor, string $operation, int $message): void
    {
        $this->db->execute($this->db->prepare("INSERT INTO %i (race_id,actor_id,created_at,kind,payload) VALUES (%d,%d,%d,'queue_admin',%s)", $this->db->table('cor_mgmt_communication_history'), $race, $actor, $this->clock->now(), json_encode(['operation' => $operation, 'message' => $message], JSON_THROW_ON_ERROR)));
    }

    /** @param array<string,string|null> $event */
    private function invitationValid(int $race, int $message, array $event): void
    {
        $raw = $this->db->scalar($this->db->prepare('SELECT context FROM %i WHERE id=%d AND race_id=%d', $this->db->table('cor_mgmt_mail_queue'), $message, $race));
        $values = json_decode($raw ?? '', true);
        if (!is_array($values) || !is_string($values['code'] ?? null)) {
            throw new CommunicationException('invitation_no_longer_valid');
        }
        $repo = new ParticipationRepository($this->db);
        $manager = new \Cor\Mgmt\Application\Teams\TeamManager($repo, $this->clock, $this->random, static fn (string $action, int $id): bool => false);
        try {
            $token = $manager->invitation($values['code'], $race);
        } catch (\Cor\Mgmt\Application\Teams\ParticipationException) {
            throw new CommunicationException('invitation_no_longer_valid');
        }
        $rules = json_decode($event['setting'] ?? '', true);
        if (!is_array($rules) || count($repo->related('teammate', 'team_id', (string) $token['team_id'])) >= (int) ($rules['num_teammember'] ?? 3)) {
            throw new CommunicationException('invitation_no_longer_valid');
        }
    }

    public function mutate(int $race, int $actor, string $revision, string $operation, int $message = 0): string
    {
        if ($operation === 'send') {
            $gate = function (int $target) use ($race, $actor): bool {
                return $target === $race && ($this->delivery)() && ($this->authorize)($race, $actor, 'edit') && $this->db->option($this->option($race)) === 'enabled';
            };
            return (new MailQueueWorker($this->queue, $this->transport, $this->random, $gate))->runOne($race, $message, function () use ($race, $actor, $revision, $message): void {
                $context = $this->checked($race, $actor, $revision, $message);
                $selected = $context['selected'];
                if ($message < 1 || ($selected['status'] ?? '') !== 'pending') {
                    throw new CommunicationException('mail_not_pending');
                }
                if (($selected['action'] ?? '') === 'invitation') {
                    $this->invitationValid($race, $message, $context['event']);
                }
                $this->journal($race, $actor, 'send', $message);
            });
        }
        return $this->queue->controlled(function () use ($race, $actor, $revision, $operation, $message): string {
            $this->checked($race, $actor, $revision, $message);
            if (in_array($operation, ['enable','disable'], true)) {
                $this->db->setOption($this->option($race), $operation === 'enable' ? 'enabled' : 'disabled');
            } elseif ($operation === 'retry' && $message > 0) {
                $this->queue->retryFailed($race, $message);
            } elseif ($operation === 'prepare' && $message > 0) {
                $this->queue->prepareBlocked($race, $message);
            } elseif ($operation === 'recover') {
                $this->queue->recoverStale($race);
            } elseif (in_array($operation, ['cancel','confirm-delivered'], true) && $message > 0) {
                $this->queue->closeMessage($race, $message, $operation === 'confirm-delivered');
            } else {
                throw new CommunicationException('invalid_operation');
            }
            $this->journal($race, $actor, $operation, $message);
            return 'saved';
        });
    }
}
