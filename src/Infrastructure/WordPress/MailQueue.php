<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Domain\MailRequest;
use Cor\Mgmt\Domain\MailSender;
use Cor\Mgmt\Infrastructure\WordPress\Schema\BroadcastSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\MailQueueSchema;

/** Trusted internal storage API. No HTTP boundary, scheduling or implicit delivery. */
final class MailQueue
{
    public const MAX_ATTEMPTS = 3;
    public const CLAIM_SECONDS = 900;

    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    public function assertDispatchable(): void
    {
        if ($this->db->hasLock() || $this->db->connectionHasLock() || $this->db->scalar('SELECT @@in_transaction') === '1') {
            throw new CommunicationException('mail_dispatch_inside_transaction');
        }
    }

    private function ready(): void
    {
        if (!MailQueueSchema::ready($this->db) || !CommunicationSchema::ready($this->db)) {
            throw new CommunicationException('mail_queue_setup_required');
        }
        foreach (MailQueueSchema::definitions() + CommunicationSchema::definitions() as $suffix => $definition) {
            (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
        }
        if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->db->table('h3_mgmt_races'))) !== 'InnoDB') {
            throw new CommunicationException('transactional_event_storage_required');
        }
    }

    private function transactionRequired(): void
    {
        $this->db->assertLock();
        if ($this->db->scalar('SELECT @@in_transaction') !== '1') {
            throw new CommunicationException('mail_transaction_required');
        }
    }

    /** @template T
     * @param callable():T $operation
     * @return T
     */
    private function atomic(callable $operation): mixed
    {
        // A producer must share the Database instance and transaction with its domain writer.
        if ($this->db->hasLock()) {
            $this->transactionRequired();
            $this->ready();
            return $operation();
        }
        return $this->db->locked(function () use ($operation): mixed {
            $nested = $this->db->scalar('SELECT @@in_transaction') === '1';
            $this->db->execute($nested ? 'SAVEPOINT cor_mail_queue' : 'START TRANSACTION');
            try {
                $this->ready();
                $result = $operation();
                $this->db->assertLock();
                $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_mail_queue' : 'COMMIT');
                return $result;
            } catch (\Throwable $error) {
                $this->db->execute($nested ? 'ROLLBACK TO SAVEPOINT cor_mail_queue' : 'ROLLBACK');
                throw $error;
            }
        });
    }

    /** Trusted orchestration API; callers must authorize before invoking mutations.
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public function controlled(callable $operation): mixed
    {
        return $this->atomic($operation);
    }

    public function closeMessage(int $race, int $id, bool $delivered): void
    {
        $this->atomic(function () use ($race, $id, $delivered): void {
            $this->eventOpen($race);
            $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d AND race_id=%d FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $id, $race))[0] ?? [];
            $allowed = $delivered ? ['uncertain'] : ['pending','blocked','skipped','failed','uncertain'];
            if (!in_array($row['status'] ?? '', $allowed, true)) {
                throw new CommunicationException('mail_resolution_not_allowed');
            }
            $this->change($row, $delivered ? 'sent' : 'cancelled', $delivered ? 'operator_confirmed_delivery' : 'operator_closed');
        });
    }

    /** Explicitly render a previously missing template; existing rendered snapshots never change. */
    public function prepareBlocked(int $race, int $id): void
    {
        $this->atomic(function () use ($race, $id): void {
            $this->eventOpen($race);
            $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d AND race_id=%d FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $id, $race))[0] ?? [];
            if (($row['status'] ?? '') !== 'blocked' || ($row['attempts'] ?? '') !== '0') {
                throw new CommunicationException('mail_preparation_not_allowed');
            }
            $decoded = json_decode($row['context'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new CommunicationException('invalid_mail_context');
            }
            $values = [];
            foreach ($decoded as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new CommunicationException('invalid_mail_context');
                }
                $values[$key] = $value;
            }
            $request = new MailRequest($race, $row['occurrence'] ?? '', $row['action'] ?? '', $row['recipient'] ?? '', $row['language'] ?? '', $values);
            if (!hash_equals($row['input_hash'] ?? '', $request->fingerprint()) || !hash_equals($row['message_key'] ?? '', $request->key())) {
                throw new CommunicationException('mail_occurrence_conflict');
            }
            $source = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d AND action=%s AND language=%s FOR UPDATE', $this->db->table('cor_mgmt_response_templates'), $race, $request->action, $request->language))[0] ?? [];
            if (($source['enabled'] ?? '') !== '1') {
                throw new CommunicationException('active_event_template_required');
            }
            $template = new AutoResponseTemplate($request->action, $request->language, true, $source['subject'] ?? '', $source['message'] ?? '');
            $content = $template->render($values);
            if (strlen($content['message']) > 200000) {
                throw new CommunicationException('rendered_message_too_large');
            }
            $this->db->execute($this->db->prepare('UPDATE %i SET subject=%s,message=%s,template_revision=%d WHERE id=%d', $this->db->table('cor_mgmt_mail_queue'), $content['subject'], $content['message'], (int) $source['revision'], $id));
            $this->change($row, 'pending', 'explicit_preparation');
        });
    }

    private function eventOpen(int $race): void
    {
        if ($race > 0 && Archive::locked($this->db, $race)) {
            throw new CommunicationException('archive_locked');
        }
        if ($race === 0) {
            $this->manualReady();
            return;
        }

        $row = $this->db->rows($this->db->prepare('SELECT setting FROM %i WHERE id=%d FOR UPDATE', $this->db->table('h3_mgmt_races'), $race))[0] ?? [];
        $rules = json_decode($row['setting'] ?? '', true);
        if (!is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,'0','1','2','3'], true)) {
            throw new CommunicationException('event_not_writable');
        }
    }

    /** Call before the domain transaction commits; a failure must roll back both writes. */
    public function enqueueWithinTransaction(MailRequest $request): int
    {
        $this->transactionRequired();
        $this->ready();
        $this->eventOpen($request->race);
        $existing = $this->db->rows($this->db->prepare('SELECT id,input_hash FROM %i WHERE message_key=%s FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $request->key()))[0] ?? null;
        if ($existing !== null) {
            if (!hash_equals($existing['input_hash'] ?? '', $request->fingerprint())) {
                throw new CommunicationException('mail_occurrence_conflict');
            }
            return (int) $existing['id'];
        }
        $source = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d AND action=%s AND language=%s FOR UPDATE', $this->db->table('cor_mgmt_response_templates'), $request->race, $request->action, $request->language))[0] ?? null;
        $status = 'blocked';
        $reason = 'missing_event_template';
        $content = ['subject' => '', 'message' => ''];
        if ($source !== null) {
            if (!in_array($source['enabled'], ['0','1'], true)) {
                throw new CommunicationException('invalid_template_switch');
            }
            $template = new AutoResponseTemplate($request->action, $request->language, $source['enabled'] === '1', $source['subject'] ?? '', $source['message'] ?? '');
            $status = $template->enabled ? 'pending' : 'skipped';
            $reason = $template->enabled ? '' : 'template_disabled';
            if ($template->enabled) {
                $content = $template->render($request->values);
                if (strlen($content['message']) > 200000) {
                    throw new CommunicationException('rendered_message_too_large');
                }
            }
        }
        $this->db->execute($this->db->prepare('INSERT INTO %i (message_key,input_hash,race_id,occurrence,action,recipient,language,template_revision,context,subject,message,status,claim_token,created_at,updated_at,reason) VALUES (%s,%s,%d,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%d,%d,%s)', $this->db->table('cor_mgmt_mail_queue'), $request->key(), $request->fingerprint(), $request->race, $request->occurrence, $request->action, $request->recipient, $request->language, (int) ($source['revision'] ?? 0), json_encode($request->values, JSON_THROW_ON_ERROR), $content['subject'], $content['message'], $status, '', $this->clock->now(), $this->clock->now(), $reason));
        $id = (int) $this->db->wpdb->insert_id;
        $this->history($id, $status, 0, $reason);
        return $id;
    }

    /** @return array<string,string|null>|null */
    public function claim(int $race, string $token, int $id = 0, ?callable $beforeClaim = null): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new CommunicationException('invalid_claim_token');
        }
        if ($race === 0 && $id < 1) {
            throw new CommunicationException('manual_message_required');
        }
        return $this->atomic(function () use ($race, $token, $id, $beforeClaim): ?array {
            if ($beforeClaim !== null) {
                $beforeClaim();
            }
            $this->eventOpen($race);
            $row = $this->db->rows($this->db->prepare("SELECT * FROM %i WHERE race_id=%d AND status='pending' AND attempts<%d AND (%d=0 OR id=%d) ORDER BY id LIMIT 1 FOR UPDATE", $this->db->table('cor_mgmt_mail_queue'), $race, self::MAX_ATTEMPTS, $id, $id))[0] ?? null;
            if ($row === null) {
                return null;
            }
            if ($race === 0) {
                $envelope = $this->db->rows($this->db->prepare('SELECT headers FROM %i WHERE message_id=%d', $this->db->table('cor_mgmt_mail_envelopes'), (int) $row['id']))[0] ?? [];
                if ($row['action'] !== 'manual' || !$envelope) {
                    throw new CommunicationException('invalid_manual_envelope');
                }
                $row['_headers'] = $envelope['headers'];
            }
            $attempt = (int) $row['attempts'] + 1;
            $this->db->execute($this->db->prepare("UPDATE %i SET status='sending',attempts=%d,claim_token=%s,claimed_at=%d,updated_at=%d,reason='' WHERE id=%d", $this->db->table('cor_mgmt_mail_queue'), $attempt, $token, $this->clock->now(), $this->clock->now(), (int) $row['id']));
            $this->history((int) $row['id'], 'sending', $attempt, '');
            return array_replace($row, ['status' => 'sending','attempts' => (string) $attempt,'claim_token' => $token]);
        });
    }

    public function finish(int $race, int $id, string $token, string $status): void
    {
        if (!in_array($status, ['sent','failed','uncertain'], true)) {
            throw new CommunicationException('invalid_delivery_outcome');
        }
        $this->atomic(function () use ($race, $id, $token, $status): void {
            $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d AND race_id=%d FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $id, $race))[0] ?? [];
            if (($row['status'] ?? '') !== 'sending' || !hash_equals($row['claim_token'] ?? '', $token)) {
                throw new CommunicationException('mail_claim_lost');
            }
            $reason = $status === 'sent' ? 'transport_accepted' : ($status === 'failed' ? 'transport_rejected' : 'transport_outcome_unknown');
            $this->change($row, $status, $reason);
        });
    }

    /** Internal explicit retry after authorization at the future administration boundary. */
    public function retryFailed(int $race, int $id): void
    {
        $this->atomic(function () use ($race, $id): void {
            $this->eventOpen($race);
            $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d AND race_id=%d FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $id, $race))[0] ?? [];
            if (($row['status'] ?? '') !== 'failed' || (int) ($row['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                throw new CommunicationException('mail_retry_not_allowed');
            }
            $this->change($row, 'pending', 'explicit_retry');
        });
    }

    /** A stale claim is uncertain, never automatically pending again. Bounded housekeeping. */
    public function recoverStale(int $race, int $id = 0): int
    {
        if ($race === 0 && $id < 1) {
            throw new CommunicationException('manual_message_required');
        }
        return $this->atomic(function () use ($race, $id): int {
            $rows = $this->db->rows($this->db->prepare("SELECT * FROM %i WHERE race_id=%d AND status='sending' AND claimed_at<=%d AND (%d=0 OR id=%d) ORDER BY id LIMIT 50 FOR UPDATE", $this->db->table('cor_mgmt_mail_queue'), $race, $this->clock->now() - self::CLAIM_SECONDS, $id, $id));
            foreach ($rows as $row) {
                $this->change($row, 'uncertain', 'claim_expired');
            }
            return count($rows);
        });
    }

    private function manualReady(): void
    {
        if (!BroadcastSchema::ready($this->db)) {
            throw new CommunicationException('broadcast_setup_required');
        }
        foreach (BroadcastSchema::definitions() as $suffix => $definition) {
            (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
        }
    }

    /** @param list<array<string,string|null>> $recipients
     * @return array<int,int> Recipient ID => queue ID.
     */
    public function enqueueManualBatch(int $campaign, array $recipients, MailSender $sender): array
    {
        $this->transactionRequired();
        $this->ready();
        $this->manualReady();
        if ($campaign < 1 || count($recipients) > 100) {
            throw new CommunicationException('invalid_manual_batch');
        }
        $result = [];
        foreach ($recipients as $row) {
            $id = (int) ($row['id'] ?? 0);
            $email = $row['recipient'] ?? '';
            $language = AutoResponseTemplate::language($row['language'] ?? '');
            $subject = $row['subject'] ?? '';
            $message = $row['message'] ?? '';
            if ($id < 1 || (int) ($row['broadcast_id'] ?? 0) !== $campaign || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || preg_match('/[\r\n\x00]/', $email) || trim($subject) === '' || trim($message) === '' || strlen($subject) > 998 || preg_match('/[\r\n\x00]/', $subject) || strlen($message) > 200000 || str_contains($message, "\0") || !hash_equals($sender->headers(), $row['headers'] ?? '')) {
                throw new CommunicationException('invalid_manual_snapshot');
            }
            $key = hash('sha256', 'broadcast:' . $campaign . ':' . $id);
            $hash = hash('sha256', json_encode([$email,$language,$subject,$message,$sender->headers()], JSON_THROW_ON_ERROR));
            $old = $this->db->rows($this->db->prepare('SELECT id,input_hash FROM %i WHERE message_key=%s FOR UPDATE', $this->db->table('cor_mgmt_mail_queue'), $key))[0] ?? [];
            if ($old) {
                if (!hash_equals($old['input_hash'] ?? '', $hash)) {
                    throw new CommunicationException('mail_occurrence_conflict');
                }
                $result[$id] = (int) $old['id'];
                continue;
            }
            $this->db->execute($this->db->prepare("INSERT INTO %i (message_key,input_hash,race_id,occurrence,action,recipient,language,template_revision,context,subject,message,status,claim_token,created_at,updated_at,reason) VALUES (%s,%s,0,%s,'manual',%s,%s,0,'{}',%s,%s,'pending','',%d,%d,'')", $this->db->table('cor_mgmt_mail_queue'), $key, $hash, 'broadcast:' . $campaign . ':' . $id, $email, $language, $subject, $message, $this->clock->now(), $this->clock->now()));
            $queue = (int) $this->db->wpdb->insert_id;
            $this->db->execute($this->db->prepare('INSERT INTO %i (message_id,broadcast_id,headers) VALUES (%d,%d,%s)', $this->db->table('cor_mgmt_mail_envelopes'), $queue, $campaign, $sender->headers()));
            $this->history($queue, 'pending', 0, 'manual_campaign');
            $result[$id] = $queue;
        }
        return $result;
    }

    /** @param array<string,string|null> $row */
    private function change(array $row, string $status, string $reason): void
    {
        $this->db->execute($this->db->prepare('UPDATE %i SET status=%s,claim_token=%s,updated_at=%d,reason=%s WHERE id=%d', $this->db->table('cor_mgmt_mail_queue'), $status, '', $this->clock->now(), $reason, (int) $row['id']));
        $this->history((int) $row['id'], $status, (int) $row['attempts'], $reason);
    }

    private function history(int $id, string $status, int $attempt, string $reason): void
    {
        $this->db->execute($this->db->prepare('INSERT INTO %i (message_id,created_at,status,attempt,reason) VALUES (%d,%d,%s,%d,%s)', $this->db->table('cor_mgmt_mail_history'), $id, $this->clock->now(), $status, $attempt, $reason));
    }
}
