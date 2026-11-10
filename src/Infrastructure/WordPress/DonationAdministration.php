<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Domain\BetterplaceEvent;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Object-authorized metadata changes; never changes financial evidence or legacy amounts. */
final class DonationAdministration
{
    /** @var \Closure(int,int,string):bool */
    private readonly \Closure $authorize;
    /** @param callable(int,int,string):bool $authorize */
    public function __construct(private readonly Database $db, private readonly Clock $clock, callable $authorize)
    {
        $this->authorize = \Closure::fromCallable($authorize);
    }
    private function allowed(int $actor, int $id, string $operation): void
    {
        if ($actor < 1 || !($this->authorize)($actor, $id, $operation)) {
            throw new DonationException('forbidden');
        }
    }
    /** @return array<string,string|null> */
    private function record(int $id, bool $lock = false): array
    {
        if (!DonationSchema::ready($this->db)) {
            throw new DonationException('donation_setup_required');
        }
        $rows = $this->db->rows($this->db->prepare('SELECT s.*,d.sponsor_id AS managed_id,d.state,d.amount_minor,d.currency,d.opinion_id,d.race_id AS ledger_race,d.team_id AS ledger_team FROM %i s LEFT JOIN %i d ON d.sponsor_id=s.id WHERE s.id=%d', $this->db->table('h3_mgmt_sponsors'), $this->db->table('cor_mgmt_donations'), $id) . ($lock ? ' FOR UPDATE' : ''));
        $row = $rows[0] ?? [];
        if (!$row || ($row['managed_id'] !== null && ($row['race_id'] !== $row['ledger_race'] || $row['team_id'] !== $row['ledger_team']))) {
            throw new DonationException('invalid_reference');
        }
        return $row;
    }
    /** @param array<string,string|null> $row */
    private function revision(array $row): string
    {
        $generation = $this->db->scalar($this->db->prepare('SELECT MAX(id) FROM %i WHERE sponsor_id=%d', $this->db->table('cor_mgmt_donation_history'), (int) $row['id']));
        $rules = $this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), (int) $row['race_id']));
        $teamRace = $this->db->scalar($this->db->prepare('SELECT race_id FROM %i WHERE id=%d', $this->db->table('h3_mgmt_teams'), (int) $row['team_id']));
        return hash('sha256', json_encode([$row, $generation, $rules, $teamRace], JSON_THROW_ON_ERROR));
    }
    /** @return array{record:array<string,string|null>,revision:string,plan:array<string,mixed>,prepared:bool} */
    public function inspect(int $id, int $actor): array
    {
        $this->allowed($actor, $id, 'view');
        $row = $this->record($id);
        $plan = $this->plan($row);
        $latest = $this->db->scalar($this->db->prepare("SELECT payload FROM %i WHERE sponsor_id=%d AND kind='legacy_review_prepared' ORDER BY id DESC LIMIT 1", $this->db->table('cor_mgmt_donation_history'), $id));
        $previous = json_decode($latest ?? '', true);
        return ['record' => $row, 'revision' => $this->revision($row), 'plan' => $plan, 'prepared' => is_array($previous) && ($previous['plan'] ?? null) === $plan];
    }
    /** @param array<string,string|null> $row
     * @return array<string,mixed>
     */
    private function plan(array $row): array
    {
        $rules = json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), (int) $row['race_id'])) ?? '', true);
        $reason = null;
        if ($row['managed_id'] !== null || $row['method'] !== 'Betterplace' || $row['paid'] !== '0') {
            $reason = 'not_legacy_pending';
        } elseif (!is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,'0','1','2','3'], true)) {
            $reason = 'archive_or_unknown_phase';
        } elseif ($this->db->scalar($this->db->prepare('SELECT race_id FROM %i WHERE id=%d', $this->db->table('h3_mgmt_teams'), (int) $row['team_id'])) !== $row['race_id']) {
            $reason = 'invalid_team';
        }
        $event = null;
        if ($reason === null) {
            try {
                $event = (new BetterplaceEvent(is_string($rules['betterplace_redirect_link'] ?? null) ? $rules['betterplace_redirect_link'] : ''))->id;
            } catch (\InvalidArgumentException) {
                $reason = 'invalid_betterplace_url';
            }
        }
        return ['eligible' => $reason === null, 'reason' => $reason, 'source_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)), 'event_id' => $event, 'has_old_code' => ($row['donation_token'] ?? '') !== '', 'historical_units' => 'unresolved', 'next_action' => 'provider_reconciliation_required', 'payment_unchanged' => true, 'session_transfer' => false];
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
            return $operation();
        }, true);
    }
    /** @param array<string,string|null> $row */
    private function writable(array $row, string $revision): void
    {
        if (Archive::locked($this->db, (int) $row['race_id'])) {
            throw new DonationException('archive_locked');
        }
        if (!hash_equals($this->revision($row), $revision)) {
            throw new DonationException('stale_donation');
        }
        $raw = $this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=%d FOR UPDATE', $this->db->table('h3_mgmt_races'), (int) $row['race_id']));
        $rules = json_decode($raw ?? '', true);
        if (!is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,'0','1','2','3'], true)) {
            throw new DonationException('donations_closed');
        }
    }
    /** @param array<int,array{revision:string,values:array<string,string>}> $requests
     * @return array<string,string|null>
     */
    public function update(array $requests, int $actor): array
    {
        if (!$requests || count($requests) > 50) {
            throw new DonationException('invalid_input');
        }
        return $this->atomic(function () use ($requests, $actor): array {
            $changes = [];
            foreach ($requests as $id => $request) {
                $this->allowed($actor, $id, 'edit');
                $row = $this->record($id, true);
                $this->writable($row, $request['revision']);
                if ($row['managed_id'] === null) {
                    throw new DonationException('not_managed');
                }
                $values = [];
                foreach ($request['values'] as $field => $value) {
                    if (!in_array($field, ['display_name','message','owner_link','var_show','owner_pic'], true) || ($row['type'] !== 'owner' && str_starts_with($field, 'owner_'))) {
                        throw new DonationException('invalid_field');
                    }
                    if (($field === 'owner_pic' && $value !== '') || ($field === 'var_show' && !in_array($value, ['0','1'], true))) {
                        throw new DonationException('invalid_input');
                    }
                    $clean = match ($field) {
                        'display_name' => sanitize_text_field($value),
                        'message' => sanitize_textarea_field($value),
                        'owner_link' => esc_url_raw($value, ['http','https']),
                        default => $value,
                    };
                    if ($field === 'var_show' && $clean === '1' && $row['state'] !== 'confirmed') {
                        throw new DonationException('payment_not_confirmed');
                    }
                    if ($clean !== ($row[$field] ?? '')) {
                        $values[$field] = ['old' => $row[$field], 'new' => $clean];
                    }
                }
                $changes[$id] = [$row, $values];
            }
            foreach ($changes as $id => [$row, $values]) {
                if (!$values) {
                    continue;
                }
                $set = [];
                foreach ($values as $field => $value) {
                    $set[] = $this->db->prepare('%i=%s', $field, $value['new']);
                }
                $this->db->execute($this->db->prepare('UPDATE %i SET ', $this->db->table('h3_mgmt_sponsors')) . implode(',', $set) . $this->db->prepare(' WHERE id=%d', $id));
                $this->journal((int) $row['race_id'], $id, 'admin_profile_saved', ['actor' => $actor, 'changes' => $values]);
            }
            return ['updated' => (string) count($requests)];
        });
    }
    /** @return array<string,string|null> */
    public function prepareLegacy(int $id, int $actor, string $revision): array
    {
        return $this->atomic(function () use ($id, $actor, $revision): array {
            $this->allowed($actor, $id, 'edit');
            $row = $this->record($id, true);
            $this->writable($row, $revision);
            $plan = $this->plan($row);
            if ($plan['eligible'] !== true) {
                throw new DonationException('legacy_review_blocked');
            }
            $last = $this->db->scalar($this->db->prepare("SELECT payload FROM %i WHERE sponsor_id=%d AND kind='legacy_review_prepared' ORDER BY id DESC LIMIT 1", $this->db->table('cor_mgmt_donation_history'), $id));
            $previous = json_decode($last ?? '', true);
            if (!is_array($previous) || ($previous['plan'] ?? null) !== $plan) {
                $this->journal((int) $row['race_id'], $id, 'legacy_review_prepared', ['actor' => $actor, 'plan' => $plan]);
            }
            return ['status' => 'provider_reconciliation_required'];
        });
    }
    /** @param array<string,mixed> $payload */
    private function journal(int $race, int $id, string $kind, array $payload): void
    {
        $this->db->assertLock();
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,sponsor_id,created_at,kind,payload) VALUES (%d,%d,%d,%s,%s)', $this->db->table('cor_mgmt_donation_history'), $race, $id, $this->clock->now(), $kind, json_encode($payload, JSON_THROW_ON_ERROR)));
    }
}
