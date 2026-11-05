<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Domain\BetterplaceEvent;
use Cor\Mgmt\Infrastructure\Betterplace\PublicOpinions;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Atomic compatibility writer. External observation happens outside the shared lock. */
final class DonationLedger
{
    private bool $writing = false;
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly RandomSource $random, private readonly PublicOpinions $provider)
    {
    }
    /** @param callable():array<string,string|null> $operation
     * @return array<string,string|null>
     */
    private function atomic(callable $operation): array
    {
        return (new LegacyEventStore($this->db))->exclusive(function () use ($operation): array {
            if (!DonationSchema::ready($this->db)) {
                throw new DonationException('donation_setup_required');
            }
            foreach (DonationSchema::definitions() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->db->table('h3_mgmt_sponsors'))) !== 'InnoDB') {
                throw new DonationException('transactional_storage_required');
            }
            $this->writing = true;
            try {
                return $operation();
            } finally {
                $this->writing = false;
            }
        }, true);
    }
    /** @return array<string,mixed> */
    private function rules(int $race): array
    {
        $raw = $this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), $race) . ($this->writing ? ' FOR UPDATE' : ''));
        $rules = json_decode($raw ?? '', true);
        if (!is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,'0','1','2','3'], true) || !in_array($rules['donation'] ?? null, [1,'1'], true)) {
            throw new DonationException('donations_closed');
        }
        return $rules;
    }
    /** @return array<string,string|null> */
    public function find(int $race, string $token, string $session): array
    {
        if (!DonationSchema::ready($this->db) || $session === '' || strlen($session) > 128 || !preg_match('/^t[1-9][0-9]*-[a-f0-9]{32}$/D', $token)) {
            throw new DonationException('invalid_reference');
        }
        $rows = $this->db->rows($this->db->prepare('SELECT d.*,s.type,s.display_name,s.message,s.owner_pic,s.owner_link,s.var_show FROM %i d JOIN %i s ON s.id=d.sponsor_id AND s.race_id=d.race_id AND s.team_id=d.team_id WHERE d.race_id=%d AND d.token=%s', $this->db->table('cor_mgmt_donations'), $this->db->table('h3_mgmt_sponsors'), $race, $token) . ($this->writing ? ' FOR UPDATE' : ''));
        $row = $rows[0] ?? [];
        if (!$row || !hash_equals($row['session_hash'] ?? '', hash('sha256', $session))) {
            throw new DonationException('invalid_reference');
        }
        return $row;
    }
    /** @return array<string,string|null> */
    public function begin(int $race, int $team, string $type, string $session, string $language, bool $new = false): array
    {
        return $this->atomic(function () use ($race, $team, $type, $session, $language, $new): array {
            $rules = $this->rules($race);
            if ($session === '' || strlen($session) > 128 || !in_array($type, ['owner','sponsor'], true) || ($type === 'owner' && (int) ($rules['kind_of_donation_tool'] ?? 0) === 1)) {
                throw new DonationException('invalid_input');
            }
            $event = new BetterplaceEvent(is_string($rules['betterplace_redirect_link'] ?? null) ? $rules['betterplace_redirect_link'] : '');
            if ($this->db->scalar($this->db->prepare('SELECT race_id FROM %i WHERE id=%d FOR UPDATE', $this->db->table('h3_mgmt_teams'), $team)) !== (string) $race) {
                throw new DonationException('invalid_team');
            }
            if (!$new) {
                $old = $this->db->rows($this->db->prepare('SELECT token FROM %i WHERE race_id=%d AND team_id=%d AND session_hash=%s ORDER BY sponsor_id DESC LIMIT 1 FOR UPDATE', $this->db->table('cor_mgmt_donations'), $race, $team, hash('sha256', $session)))[0] ?? [];
                if ($old && is_string($old['token'])) {
                    return $this->find($race, $old['token'], $session);
                }
            }
            if ($type === 'owner' && $this->db->scalar($this->db->prepare("SELECT COUNT(*) FROM %i WHERE team_id=%d AND type='owner' AND paid=1 FOR UPDATE", $this->db->table('h3_mgmt_sponsors'), $team)) !== '0') {
                throw new DonationException('owner_unavailable');
            }
            $token = 't' . $team . '-' . bin2hex($this->random->bytes(16));
            $reference = 'cm2-' . bin2hex($this->random->bytes(16));
            $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,team_id,type,method,language,donation,paid,var_show,donation_token,donation_client_reference) VALUES (%d,%d,%s,%s,%s,0,0,0,%s,%s)', $this->db->table('h3_mgmt_sponsors'), $race, $team, $type, 'Betterplace', $language === 'de' ? 'de' : 'en', $token, $reference));
            $id = (int) $this->db->wpdb->insert_id;
            $this->db->execute($this->db->prepare("INSERT INTO %i (sponsor_id,race_id,team_id,session_hash,token,event_id,created_at,state,currency) VALUES (%d,%d,%d,%s,%s,%s,%d,'pending','EUR')", $this->db->table('cor_mgmt_donations'), $id, $race, $team, hash('sha256', $session), $token, $event->id, $this->clock->now()));
            $this->journal($race, $id, 'created', ['type' => $type, 'event' => $event->id]);
            return $this->find($race, $token, $session);
        });
    }
    /** @return array<string,string|null> */
    public function verify(int $race, string $token, string $session): array
    {
        $this->rules($race);
        $snapshot = $this->find($race, $token, $session);
        if ($snapshot['state'] === 'confirmed') {
            return $snapshot;
        }
        if ($snapshot['state'] !== 'pending') {
            throw new DonationException('unsupported_donation_state');
        }
        $proof = $this->provider->find($snapshot['event_id'] ?? '', $token, (int) $snapshot['created_at']);
        return $this->atomic(function () use ($race, $token, $session, $proof): array {
            $rules = $this->rules($race);
            $row = $this->find($race, $token, $session);
            if ($row['state'] === 'confirmed') {
                return $row;
            }
            $event = new BetterplaceEvent(is_string($rules['betterplace_redirect_link'] ?? null) ? $rules['betterplace_redirect_link'] : '');
            if ($event->id !== $row['event_id'] || $proof->confirmedAt < (int) $row['created_at'] || $proof->confirmedAt > $this->clock->now() || intdiv($proof->amount->cents, 100) > 4294967295) {
                throw new DonationException('invalid_evidence');
            }
            $claimed = $this->db->scalar($this->db->prepare('SELECT sponsor_id FROM %i WHERE opinion_id=%s FOR UPDATE', $this->db->table('cor_mgmt_donations'), $proof->opinion));
            if ($claimed !== null) {
                throw new DonationException('opinion_already_used');
            }
            $type = $row['type'] === 'owner' && (int) ($rules['kind_of_donation_tool'] ?? 0) !== 1 && $proof->amount->cents >= 10000 ? 'owner' : 'sponsor';
            if ($type === 'owner' && $this->db->scalar($this->db->prepare("SELECT COUNT(*) FROM %i WHERE team_id=%d AND type='owner' AND paid=1 AND id<>%d FOR UPDATE", $this->db->table('h3_mgmt_sponsors'), (int) $row['team_id'], (int) $row['sponsor_id'])) !== '0') {
                $type = 'sponsor';
            }
            $this->db->execute($this->db->prepare("UPDATE %i SET state='confirmed',amount_minor=%d,opinion_id=%s,confirmed_at=%d WHERE sponsor_id=%d", $this->db->table('cor_mgmt_donations'), $proof->amount->cents, $proof->opinion, $proof->confirmedAt, (int) $row['sponsor_id']));
            // Only a compatibility projection. Exact cents remain authoritative in the module.
            $this->db->execute($this->db->prepare('UPDATE %i SET paid=1,donation=%d,type=%s WHERE id=%d', $this->db->table('h3_mgmt_sponsors'), intdiv($proof->amount->cents, 100), $type, (int) $row['sponsor_id']));
            $this->journal($race, (int) $row['sponsor_id'], 'confirmed', ['opinion' => $proof->opinion, 'amount_minor' => $proof->amount->cents, 'currency' => 'EUR', 'type' => $type]);
            return $this->find($race, $token, $session);
        });
    }
    /** @param array<string,string> $values
     * @return array<string,string|null>
     */
    public function profile(int $race, string $token, string $session, array $values): array
    {
        return $this->atomic(function () use ($race, $token, $session, $values): array {
            $this->rules($race);
            $row = $this->find($race, $token, $session);
            if ($row['state'] !== 'confirmed') {
                throw new DonationException('payment_not_confirmed');
            }
            $set = [];
            $changed = [];
            foreach (['display_name','message','owner_pic','owner_link'] as $field) {
                if (!isset($values[$field]) || ($row['type'] !== 'owner' && str_starts_with($field, 'owner_')) || $values[$field] === ($row[$field] ?? '')) {
                    continue;
                }
                $changed[$field] = ['old' => $row[$field] ?? null, 'new' => $values[$field]];
                $set[] = $this->db->prepare('%i=%s', $field, $values[$field]);
            }
            if ($set || $row['var_show'] !== '1') {
                $set[] = 'var_show=1';
                $this->db->execute($this->db->prepare('UPDATE %i SET ', $this->db->table('h3_mgmt_sponsors')) . implode(',', $set) . $this->db->prepare(' WHERE id=%d', (int) $row['sponsor_id']));
                $this->journal($race, (int) $row['sponsor_id'], 'profile_saved', $changed);
            }
            return $this->find($race, $token, $session);
        });
    }
    /** @param array<string,mixed> $payload */
    private function journal(int $race, int $sponsor, string $kind, array $payload): void
    {
        $this->db->assertLock();
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,sponsor_id,created_at,kind,payload) VALUES (%d,%d,%d,%s,%s)', $this->db->table('cor_mgmt_donation_history'), $race, $sponsor, $this->clock->now(), $kind, json_encode($payload, JSON_THROW_ON_ERROR)));
    }
}
