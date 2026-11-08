<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Domain\MailSender;
use Cor\Mgmt\Domain\MessageText;
use Cor\Mgmt\Infrastructure\WordPress\Schema\BroadcastSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\WordPress\TeamPortal;

/** Reviewed manual campaigns are independent of automatic event notifications.
 * @phpstan-type Recipient array{user:int,recipient:string,language:string,subject:string,message:string,headers:string}
 * @phpstan-type Preview array{selection:array<string,string>,sender:array{name:string,email:string},recipients:list<Recipient>,issues:list<string>,fingerprint:string}
 * @phpstan-type Campaign array{job:array<string,string|null>,selection:array<string,string>,sender:array{name:string,email:string},revision:string,rows:list<array<string,string|null>>,history:list<array<string,string|null>>,count:int,page:int,pages:int,server_enabled:bool}
 */
final class Broadcasts
{
    private readonly \Closure $authorize;
    private readonly \Closure $delivery;
    private readonly MailQueue $queue;
    /** @param callable(int,array<string,string>):bool $authorize
     * @param callable():bool $delivery
     */
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random, private readonly MailTransport $transport, callable $authorize, callable $delivery)
    {
        $this->authorize = \Closure::fromCallable($authorize);
        $this->delivery = \Closure::fromCallable($delivery);
        $this->queue = new MailQueue($db, $clock);
    }
    /** @param array<string,string> $selection */
    private function allowed(int $actor, array $selection): void
    {
        if ($actor < 1 || !($this->authorize)($actor, $selection)) {
            throw new CommunicationException('forbidden');
        }
    }
    private function ready(): void
    {
        if (!BroadcastSchema::ready($this->db)) {
            throw new CommunicationException('broadcast_setup_required');
        }
        foreach (BroadcastSchema::definitions() as $suffix => $definition) {
            (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
        }
    }
    /** @param array<string,string> $input
     * @return Preview
     */
    public function preview(int $actor, array $input, string $subject, string $message): array
    {
        $selection = ['mode' => $input['mode'] ?? 'self', 'id' => $input['id'] ?? '0', 'email' => $input['email'] ?? '', 'language' => $input['language'] ?? '', 'sender' => $input['sender'] ?? 'nr'];
        if (!in_array($selection['mode'], ['self','email','race','team','all'], true) || !ctype_digit($selection['id']) || !in_array($selection['sender'], ['nr','own'], true) || (in_array($selection['mode'], ['race','team'], true) && (int) $selection['id'] < 1)) {
            throw new CommunicationException('invalid_audience');
        }
        $this->allowed($actor, $selection);
        $text = new MessageText($subject, $message);
        $filter = $selection['language'] === '' ? '' : AutoResponseTemplate::language($selection['language']);
        $owner = get_userdata($actor);
        if (!$owner) {
            throw new CommunicationException('missing_sender');
        }
        $sender = $selection['sender'] === 'own' ? new MailSender($owner->display_name, $owner->user_email) : new MailSender('Tramprennen', 'no-reply@tramprennen.org');
        $people = [];
        $mode = $selection['mode'];
        if ($mode === 'self' || $mode === 'email') {
            $people[] = ['user_id' => $mode === 'self' ? (string) $actor : '0', 'email' => $mode === 'self' ? $owner->user_email : $selection['email'], 'language' => $filter ?: get_user_locale($owner)];
        } else {
            $condition = $mode === 'race' ? $this->db->prepare('t.race_id=%d', (int) $selection['id']) : ($mode === 'team' ? $this->db->prepare('t.id=%d', (int) $selection['id']) : '1=1');
            $sql = $this->db->prepare('SELECT m.user_id,m.language AS language,u.user_email AS email,t.team_name,t.race_id,r.name AS event_name,t.id AS team_id FROM %i m JOIN %i t ON t.id=m.team_id JOIN %i r ON r.id=t.race_id LEFT JOIN %i u ON u.ID=m.user_id WHERE ', $this->db->table('h3_mgmt_teammates'), $this->db->table('h3_mgmt_teams'), $this->db->table('h3_mgmt_races'), $this->db->wpdb->users);
            $people = $this->db->rows($sql . $condition . ' ORDER BY m.user_id,t.id LIMIT 10001');
        }
        $issues = [];
        if (count($people) > 10000) {
            $issues[] = 'audience_limit_10000';
        }
        $byUser = [];
        $byEmail = [];
        $bytes = 0;
        foreach ($people as $person) {
            $user = (int) $person['user_id'];
            $account = $user > 0 ? get_userdata($user) : false;
            if ($user > 0 && !$account) {
                $issues[] = 'missing_user:' . $user;
                continue;
            }
            try {
                $language = AutoResponseTemplate::language($mode === 'all' && $account ? get_user_locale($account) : ($person['language'] ?: 'und'));
                if ($filter !== '' && $language !== $filter && !str_starts_with($language, $filter . '_')) {
                    continue;
                }
                $email = $person['email'] ?? '';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || preg_match('/[\r\n\x00<>]/', $email)) {
                    throw new CommunicationException('invalid_recipient');
                }
                $values = $account ? ['name' => (string) get_user_meta($user, 'first_name', true)] : [];
                if (in_array($mode, ['race','team'], true)) {
                    $values += ['team_name' => $person['team_name'] ?? '', 'event_name' => $person['event_name'] ?? '', 'link' => TeamPortal::url((int) $person['race_id'])];
                }
                $rendered = $text->render($values);
                if (trim($rendered['subject']) === '' || trim($rendered['message']) === '') {
                    throw new CommunicationException('invalid_rendered_template');
                }
                $row = ['user' => $user, 'recipient' => $email, 'language' => $language, 'subject' => $rendered['subject'], 'message' => $rendered['message'], 'headers' => $sender->headers()];
                $key = strtolower($email);
                $content = hash('sha256', json_encode([$language, $rendered], JSON_THROW_ON_ERROR));
                if (isset($byUser[$user]) && $user > 0 && $byUser[$user] !== $content) {
                    throw new CommunicationException('ambiguous_membership');
                }
                if (isset($byEmail[$key]) && $byEmail[$key]['content'] !== $content) {
                    throw new CommunicationException('conflicting_shared_address');
                }
                if (!isset($byEmail[$key])) {
                    $bytes += strlen($rendered['subject']) + strlen($rendered['message']) + strlen($email) + strlen($sender->headers());
                    if ($bytes > 20000000) {
                        $issues[] = 'audience_content_limit_20mb';
                        break;
                    }
                }
                $byUser[$user] = $content;
                $byEmail[$key] ??= ['content' => $content, 'row' => $row];
            } catch (CommunicationException $error) {
                $issues[] = $error->reason . ':' . $user;
            }
        }
        ksort($byEmail);
        $recipients = array_values(array_map(static fn (array $item): array => $item['row'], $byEmail));
        if ($recipients === []) {
            $issues[] = 'empty_audience';
        }
        $snapshot = ['selection' => $selection, 'sender' => ['name' => $sender->name, 'email' => $sender->email], 'recipients' => $recipients, 'issues' => array_values(array_unique($issues))];
        return $snapshot + ['fingerprint' => hash('sha256', json_encode([$actor, $subject, $message, $snapshot], JSON_THROW_ON_ERROR))];
    }
    /** @param array<string,string> $selection */
    public function create(int $actor, string $request, array $selection, string $subject, string $message, string $fingerprint): int
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $request)) {
            throw new CommunicationException('invalid_request');
        }
        return $this->queue->controlled(function () use ($actor, $request, $selection, $subject, $message, $fingerprint): int {
            $this->ready();
            $this->allowed($actor, $selection);
            $key = hash('sha256', $actor . ':' . $request);
            $old = $this->db->rows($this->db->prepare('SELECT id,fingerprint FROM %i WHERE request_key=%s', $this->db->table('cor_mgmt_broadcasts'), $key))[0] ?? [];
            if ($old) {
                if (!hash_equals($old['fingerprint'] ?? '', $fingerprint)) {
                    throw new CommunicationException('request_conflict');
                }
                return (int) $old['id'];
            }
            $preview = $this->preview($actor, $selection, $subject, $message);
            if ($preview['issues'] || !hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new CommunicationException('stale_broadcast_preview');
            }
            $payload = json_encode(['selection' => $preview['selection'], 'sender' => $preview['sender']], JSON_THROW_ON_ERROR);
            $this->db->execute($this->db->prepare('INSERT INTO %i (actor_id,request_key,fingerprint,payload,created_at) VALUES (%d,%s,%s,%s,%d)', $this->db->table('cor_mgmt_broadcasts'), $actor, $key, $fingerprint, $payload, $this->clock->now()));
            $id = (int) $this->db->wpdb->insert_id;
            foreach ($preview['recipients'] as $row) {
                $this->db->execute($this->db->prepare('INSERT INTO %i (broadcast_id,recipient_key,user_id,recipient,language,subject,message,headers) VALUES (%d,%s,%d,%s,%s,%s,%s,%s)', $this->db->table('cor_mgmt_broadcast_recipients'), $id, hash('sha256', strtolower($row['recipient'])), $row['user'], $row['recipient'], $row['language'], $row['subject'], $row['message'], $row['headers']));
            }
            $this->journal($id, $actor, 'created');
            return $id;
        });
    }
    /** @return Campaign */
    public function context(int $id, int $actor, int $page = 1): array
    {
        $this->ready();
        $job = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->db->table('cor_mgmt_broadcasts'), $id))[0] ?? [];
        $payload = json_decode($job['payload'] ?? '', true);
        if (!is_array($payload) || !is_array($payload['selection'] ?? null) || !is_array($payload['sender'] ?? null)) {
            throw new CommunicationException('missing_campaign');
        }
        $selection = [];
        foreach ($payload['selection'] as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new CommunicationException('invalid_campaign');
            }
            $selection[$key] = $value;
        }
        $this->allowed($actor, $selection);
        if ((int) $job['actor_id'] !== $actor && !user_can($actor, 'manage_options')) {
            throw new CommunicationException('forbidden');
        }
        $sender = $payload['sender'];
        if (!is_string($sender['name'] ?? null) || !is_string($sender['email'] ?? null)) {
            throw new CommunicationException('invalid_campaign');
        }
        $history = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE broadcast_id=%d ORDER BY id DESC LIMIT 20', $this->db->table('cor_mgmt_broadcast_history'), $id));
        $generation = $this->db->scalar($this->db->prepare('SELECT MAX(h.id) FROM %i h JOIN %i r ON r.queue_id=h.message_id WHERE r.broadcast_id=%d', $this->db->table('cor_mgmt_mail_history'), $this->db->table('cor_mgmt_broadcast_recipients'), $id));
        $count = (int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE broadcast_id=%d', $this->db->table('cor_mgmt_broadcast_recipients'), $id));
        $pages = max(1, (int) ceil($count / 30));
        $page = max(1, min($pages, $page));
        $rows = $this->db->rows($this->db->prepare('SELECT r.*,q.status,q.attempts,q.reason FROM %i r LEFT JOIN %i q ON q.id=r.queue_id WHERE r.broadcast_id=%d ORDER BY r.id LIMIT 30 OFFSET %d', $this->db->table('cor_mgmt_broadcast_recipients'), $this->db->table('cor_mgmt_mail_queue'), $id, ($page - 1) * 30));
        return ['job' => $job, 'selection' => $selection, 'sender' => ['name' => $sender['name'], 'email' => $sender['email']], 'revision' => hash('sha256', json_encode([$job, $history[0]['id'] ?? '', $generation], JSON_THROW_ON_ERROR)), 'rows' => $rows, 'history' => $history, 'count' => $count, 'page' => $page, 'pages' => $pages, 'server_enabled' => ($this->delivery)()];
    }
    /** @return list<array<string,string|null>> */
    public function jobs(int $actor, int $page = 1): array
    {
        $this->ready();
        $rows = $this->db->rows($this->db->prepare('SELECT id,actor_id,created_at,enabled FROM %i WHERE actor_id=%d OR %d=1 ORDER BY id DESC LIMIT 30 OFFSET %d', $this->db->table('cor_mgmt_broadcasts'), $actor, user_can($actor, 'manage_options') ? 1 : 0, (max(1, $page) - 1) * 30));
        return array_values(array_filter($rows, function (array $row) use ($actor): bool {
            try {
                $this->context((int) $row['id'], $actor);
                return true;
            } catch (CommunicationException) {
                return false;
            }
        }));
    }
    /** @return Campaign */
    private function checked(int $id, int $actor, string $revision, int $message): array
    {
        $context = $this->context($id, $actor);
        if (!hash_equals($context['revision'], $revision)) {
            throw new CommunicationException('stale_campaign');
        }
        if ($message > 0 && $this->db->scalar($this->db->prepare("SELECT COUNT(*) FROM %i r JOIN %i q ON q.id=r.queue_id AND q.race_id=0 AND q.action='manual' JOIN %i e ON e.message_id=q.id AND e.broadcast_id=r.broadcast_id WHERE r.broadcast_id=%d AND q.id=%d", $this->db->table('cor_mgmt_broadcast_recipients'), $this->db->table('cor_mgmt_mail_queue'), $this->db->table('cor_mgmt_mail_envelopes'), $id, $message)) !== '1') {
            throw new CommunicationException('missing_campaign_message');
        }
        return $context;
    }
    private function journal(int $id, int $actor, string $operation, int $message = 0): void
    {
        $this->db->execute($this->db->prepare('INSERT INTO %i (broadcast_id,actor_id,created_at,operation,message_id) VALUES (%d,%d,%d,%s,%d)', $this->db->table('cor_mgmt_broadcast_history'), $id, $actor, $this->clock->now(), $operation, $message));
    }
    public function mutate(int $id, int $actor, string $revision, string $operation, int $message = 0): string
    {
        if ($operation === 'send') {
            if ($message < 1) {
                throw new CommunicationException('manual_message_required');
            }
            $gate = function (int $scope) use ($id, $actor): bool {
                $context = $this->context($id, $actor);
                return $scope === 0 && $context['job']['enabled'] === '1' && ($this->delivery)();
            };
            return (new MailQueueWorker($this->queue, $this->transport, $this->random, $gate))->runOne(0, $message, function () use ($id, $actor, $revision, $message): void {
                $context = $this->checked($id, $actor, $revision, $message);
                if ($context['job']['enabled'] !== '1' || !($this->delivery)()) {
                    throw new CommunicationException('mail_delivery_disabled');
                }
                $this->journal($id, $actor, 'send_claim', $message);
            });
        }
        return $this->queue->controlled(function () use ($id, $actor, $revision, $operation, $message): string {
            $context = $this->checked($id, $actor, $revision, $message);
            if (in_array($operation, ['enable','pause'], true)) {
                $this->db->execute($this->db->prepare('UPDATE %i SET enabled=%d WHERE id=%d', $this->db->table('cor_mgmt_broadcasts'), $operation === 'enable' ? 1 : 0, $id));
            } elseif ($operation === 'queue') {
                $rows = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE broadcast_id=%d AND queue_id=0 ORDER BY id LIMIT 100', $this->db->table('cor_mgmt_broadcast_recipients'), $id));
                $sender = new MailSender($context['sender']['name'], $context['sender']['email']);
                foreach ($this->queue->enqueueManualBatch($id, $rows, $sender) as $recipient => $queue) {
                    $this->db->execute($this->db->prepare('UPDATE %i SET queue_id=%d WHERE id=%d AND broadcast_id=%d AND queue_id=0', $this->db->table('cor_mgmt_broadcast_recipients'), $queue, $recipient, $id));
                }
            } elseif ($message > 0 && $operation === 'retry') {
                $this->queue->retryFailed(0, $message);
            } elseif ($message > 0 && $operation === 'recover') {
                $this->queue->recoverStale(0, $message);
            } elseif ($message > 0 && in_array($operation, ['cancel','confirm-delivered'], true)) {
                $this->queue->closeMessage(0, $message, $operation === 'confirm-delivered');
            } else {
                throw new CommunicationException('invalid_operation');
            }
            $this->journal($id, $actor, $operation, $message);
            return $operation;
        });
    }
    /** Explicit, bounded delivery; stops on an ambiguous or failed transport. */
    public function sendBatch(int $id, int $actor, string $revision): int
    {
        $this->checked($id, $actor, $revision, 0);
        $rows = $this->db->rows($this->db->prepare("SELECT q.id FROM %i q JOIN %i r ON r.queue_id=q.id WHERE r.broadcast_id=%d AND q.status='pending' ORDER BY r.id LIMIT 5", $this->db->table('cor_mgmt_mail_queue'), $this->db->table('cor_mgmt_broadcast_recipients'), $id));
        $sent = 0;
        foreach ($rows as $row) {
            $result = $this->mutate($id, $actor, $revision, 'send', (int) $row['id']);
            if ($result !== 'sent') {
                break;
            }
            $sent++;
            $revision = $this->context($id, $actor)['revision'];
        }
        return $sent;
    }

    /** @return array{unprepared:int,pending:int,sending:int,sent:int,failed:int,uncertain:int,cancelled:int} */
    public function progress(int $id, int $actor): array
    {
        $this->context($id, $actor);
        $counts = ['unprepared' => 0,'pending' => 0,'sending' => 0,'sent' => 0,'failed' => 0,'uncertain' => 0,'cancelled' => 0];
        $rows = $this->db->rows($this->db->prepare("SELECT CASE WHEN r.queue_id=0 THEN 'unprepared' WHEN q.id IS NULL OR q.race_id<>0 OR q.action<>'manual' OR e.message_id IS NULL THEN 'missing' ELSE q.status END AS state,COUNT(*) AS amount FROM %i r LEFT JOIN %i q ON q.id=r.queue_id LEFT JOIN %i e ON e.message_id=q.id AND e.broadcast_id=r.broadcast_id WHERE r.broadcast_id=%d GROUP BY state", $this->db->table('cor_mgmt_broadcast_recipients'), $this->db->table('cor_mgmt_mail_queue'), $this->db->table('cor_mgmt_mail_envelopes'), $id));
        foreach ($rows as $row) {
            $state = $row['state'] ?? '';
            if (!array_key_exists($state, $counts)) {
                throw new CommunicationException('invalid_campaign_queue_reference');
            }
            $counts[$state] = (int) $row['amount'];
        }
        return $counts;
    }

    /** One authorized HTTP step, never a server-side unbounded loop.
     * @return array{revision:string,done:bool,stop:bool,progress:array{unprepared:int,pending:int,sending:int,sent:int,failed:int,uncertain:int,cancelled:int}}
     */
    public function step(int $id, int $actor, string $revision, string $operation): array
    {
        if (!in_array($operation, ['queue','send-batch'], true)) {
            throw new CommunicationException('invalid_operation');
        }
        $context = $this->checked($id, $actor, $revision, 0);
        $before = $this->progress($id, $actor);
        $stopped = false;
        if ($operation === 'queue') {
            $this->mutate($id, $actor, $revision, 'queue');
        } else {
            if ($context['job']['enabled'] !== '1' || !($this->delivery)()) {
                throw new CommunicationException('mail_delivery_disabled');
            }
            $sent = $this->sendBatch($id, $actor, $revision);
            $stopped = $sent < min(5, $before['pending']);
        }
        $after = $this->progress($id, $actor);
        $done = $operation === 'queue' ? $after['unprepared'] === 0 : $after['pending'] === 0;
        return ['revision' => $this->context($id, $actor)['revision'], 'done' => $done, 'stop' => $stopped || $after['uncertain'] > $before['uncertain'] || $after['failed'] > $before['failed'], 'progress' => $after];
    }
}
