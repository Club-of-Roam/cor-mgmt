<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Teams;

use Closure;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Application\Events\EventEditor;
use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Domain\TeamPolicy;

/** One writer for participant, profile and organizer changes. No rendering or transport. */
final class TeamManager
{
    public const TEAM_FIELDS = ['team_name','description','team_pic','team_phone','donation_goal','meta_1','meta_2','meta_3','meta_4','meta_5'];
    /** @var list<array<string,mixed>> */
    private array $events = [];
    /** @param Closure(string,int):bool $authorize */
    public function __construct(private readonly ParticipationStore $store, private readonly Clock $clock, private readonly RandomSource $random, private readonly Closure $authorize)
    {
    }
    /** @return array<string,string|null> */
    private function race(int $id): array
    {
        $row = $this->store->find('race', $id);
        if (!$row) {
            throw new ParticipationException('missing_race');
        }
        $flat = EventEditor::flatten($row);
        if (!in_array($flat['status'] ?? '', ['0','1','2','3','4'], true)) {
            throw new ParticipationException('invalid_phase');
        }
        return $flat;
    }
    /** @param array<string,string|null> $race */
    private function permit(string $action, array $race, bool $organizer = false): void
    {
        if (!TeamPolicy::allows($action, RacePhase::from((int) $race['status']), $organizer)) {
            throw new ParticipationException('phase_locked');
        }
    }
    /** @return array<string,string|null> */
    public function owned(int $race, int $user): array
    {
        $teams = [];
        foreach ($this->store->related('teammate', 'user_id', (string) $user) as $member) {
            $team = $this->store->find('team', (int) $member['team_id']);
            if ($team && (int) $team['race_id'] === $race) {
                if (isset($teams[(int) $team['id']])) {
                    throw new ParticipationException('duplicate_membership_review');
                }
                $teams[(int) $team['id']] = $team;
            }
        }
        if (count($teams) > 1) {
            throw new ParticipationException('duplicate_membership_review');
        }
        return array_values($teams)[0] ?? [];
    }
    public function revision(int $race, int $team = 0): string
    {
        return hash('sha256', serialize([$this->store->find('race', $race), $team ? $this->store->find('team', $team) : [], $team ? $this->members($team) : []]));
    }
    /** @return list<array<string,string|null>> */
    private function members(int $team): array
    {
        return $this->store->related('teammate', 'team_id', (string) $team);
    }
    /** @return array<string,string|null> */
    public function invitation(string $code, int $race): array
    {
        $settings = $this->race($race);
        $this->permit('accept', $settings);
        if (preg_match('/^v2-[a-f0-9]{64}$/D', $code)) {
            $hash = hash('sha256', $code);
            $rows = $this->store->related('token', 'token_hash', $hash);
            if (count($rows) !== 1) {
                throw new ParticipationException('invalid_invitation');
            }
            $token = $rows[0];
        } elseif (preg_match('/^[0-9]{1,19}$/D', $code)) {
            $rows = $this->store->related('invitation', 'code', ltrim($code, '0') ?: '0');
            if (count($rows) !== 1) {
                throw new ParticipationException('invalid_invitation');
            }
            $legacy = $rows[0];
            $hash = hash('sha256', 'legacy:' . $legacy['code']);
            $consumption = $this->store->related('token', 'token_hash', $hash);
            $token = $consumption[0] ?? ['token_hash' => $hash,'team_id' => $legacy['team_id'],'email' => $legacy['email'],'created_at' => (string) $this->clock->now(),'consumed_at' => null,'consumed_by' => null,'revoked_at' => null];
        } else {
            throw new ParticipationException('invalid_invitation');
        }
        $team = $this->store->find('team', (int) $token['team_id']);
        if (!$team || (int) $team['race_id'] !== $race || $token['consumed_at'] !== null || $token['revoked_at'] !== null) {
            throw new ParticipationException('invalid_invitation');
        }
        return $token;
    }
    /**
     * @param array<string,string|null> $race
     * @return array<string,string|null>
     */
    private function refresh(array $race, int $teamId): array
    {
        $team = $this->store->find('team', $teamId);
        if ((int) $race['status'] >= 3) {
            return $team;
        }
        $members = $this->members($teamId);
        $profiles = [];
        foreach ($members as $member) {
            $this->owned((int) $race['id'], (int) $member['user_id']);
            $profile = $this->store->profile((int) $member['user_id']);
            if ($profile) {
                $profiles[(int) $member['user_id']] = $profile;
            }
        }
        $complete = TeamPolicy::eligible($race, $members, $profiles);
        $values = ['complete' => $complete ? '1' : '0'];
        if (!$complete) {
            $values['route_id'] = '0';
        } elseif ($team['complete'] !== '1' && (int) $team['route_id'] > 0 && !$this->available($race, $team, (int) $team['route_id'])) {
            $values['route_id'] = '0';
        }
        if ($complete && ($race['startingpoint'] ?? '1') === '0' && (int) ($values['route_id'] ?? $team['route_id']) === 0 && (int) ($race['start_route_id'] ?? 0) > 0) {
            $target = (int) $race['start_route_id'];
            if ($this->available($race, $team, $target)) {
                $values['route_id'] = (string) $target;
            }
        }
        $this->store->update('team', $team, $values);
        if ($complete && $team['complete'] !== '1') {
            $this->events[] = ['action' => 'publishable','team' => $teamId,'users' => array_map(static fn (array $member): int => (int) $member['user_id'], $members)];
        }
        return array_replace($team, $values);
    }
    /**
     * @param array<string,string|null> $race
     * @param array<string,string|null> $team
     */
    private function available(array $race, array $team, int $routeId): bool
    {
        $route = $this->store->find('route', $routeId);
        if (!$route || $route['race_id'] !== $race['id']) {
            throw new ParticipationException('invalid_route');
        }
        $occupied = 0;
        foreach ($this->store->related('team', 'route_id', (string) $routeId) as $other) {
            if ($other['id'] !== $team['id'] && $other['complete'] === '1') {
                $occupied++;
            }
        }
        return $occupied < (int) $route['max_teams'];
    }
    /**
     * @param array<string,string|null> $race
     * @param array<string,string|null> $team
     */
    private function route(array $race, array $team, int $route, bool $organizer): void
    {
        if ($route === (int) $team['route_id']) {
            return;
        }
        $this->permit('route', $race, $organizer);
        if (!$organizer && ((int) $race['status'] === 2 && (int) $team['route_id'] !== 0)) {
            throw new ParticipationException('route_change_locked');
        }
        if (!$organizer && ($race['startingpoint'] ?? '1') === '0' && $route !== (int) ($race['start_route_id'] ?? 0)) {
            throw new ParticipationException('fixed_start_route');
        }
        if ($route) {
            if ($organizer && !($this->authorize)('route', $route)) {
                throw new ParticipationException('not_permitted');
            }
            $available = $this->available($race, $team, $route);
            if (!$organizer && $team['complete'] !== '1') {
                throw new ParticipationException('incomplete_team');
            }
            if ($team['complete'] === '1' && !$available) {
                throw new ParticipationException('route_full');
            }
        }
        $this->store->update('team', $team, ['route_id' => (string) $route]);
    }
    /**
     * @param array<string,string> $teamValues
     * @param array<string,string> $profile
     * @param list<string> $emails
     * @return array<string,mixed>
     */
    public function save(int $raceId, int $user, array $teamValues, array $profile, string $code = '', array $emails = [], ?int $route = null, string $revision = ''): array
    {
        $this->events = [];
        return $this->store->atomic(function () use ($raceId, $user, $teamValues, $profile, $code, $emails, $route, $revision): array {
            $race = $this->race($raceId);
            $this->permit('profile', $race);
            if (!$user || !($this->authorize)('self', $user) || !$this->store->find('user', $user)) {
                throw new ParticipationException('not_permitted');
            }
            $team = $this->owned($raceId, $user);
            if ($revision !== '' && !hash_equals($this->revision($raceId, (int) ($team['id'] ?? 0)), $revision)) {
                throw new ParticipationException('stale_record');
            }
            $created = false;
            $joined = false;
            if (!$team) {
                if ($code !== '') {
                    $token = $this->invitation($code, $raceId);
                    $team = $this->store->find('team', (int) $token['team_id']);
                    if (count($this->members((int) $team['id'])) >= (int) ($race['num_teammember'] ?? 3)) {
                        throw new ParticipationException('team_full');
                    }
                    $this->store->insert('teammate', ['team_id' => $team['id'],'user_id' => (string) $user,'language' => $profile['language'] ?? 'en']);
                    if (isset($token['id'])) {
                        $this->store->update('token', $token, ['consumed_at' => (string) $this->clock->now(),'consumed_by' => (string) $user]);
                    } else {
                        $this->store->insert('token', array_replace($token, ['consumed_at' => (string) $this->clock->now(),'consumed_by' => (string) $user]));
                    }
                    $joined = true;
                    $this->events[] = ['action' => 'joined','team' => (int) $team['id'],'user' => $user];
                } else {
                    $this->permit('create', $race);
                    if (!isset($teamValues['team_name'])) {
                        throw new ParticipationException('invalid_team_name');
                    }
                    $this->validateTeam($teamValues);
                    $id = $this->store->insert('team', ['race_id' => (string) $raceId,'team_name' => $teamValues['team_name'], 'complete' => '0','route_id' => '0']);
                    $team = $this->store->find('team', $id);
                    $this->store->insert('teammate', ['team_id' => (string) $id,'user_id' => (string) $user,'language' => $profile['language'] ?? 'en']);
                    $created = true;
                    $this->events[] = ['action' => 'team-creation','team' => $id,'user' => $user];
                }
            }
            if (!$joined) {
                $this->changeTeam($race, $team, $teamValues, false);
            }
            $this->profileValues($user, $profile, $created || $joined);
            $this->refreshUser($user);
            $team = $this->refresh($race, (int) $team['id']);
            if ($route !== null && !$joined) {
                $this->route($race, $team, $route, false);
            }
            if ($emails) {
                if ($joined) {
                    throw new ParticipationException('not_permitted');
                }
                $this->invite($race, (int) $team['id'], $emails, $user);
            }
            return ['team' => (int) $team['id'],'events' => $this->events];
        });
    }
    /** @return array<string,mixed> */
    public function reconcileRace(int $raceId): array
    {
        $this->events = [];
        return $this->store->atomic(function () use ($raceId): array {
            if (!($this->authorize)('reconcile', $raceId)) {
                throw new ParticipationException('not_permitted');
            }
            $race = $this->race($raceId);
            if ((int) $race['status'] < 3) {
                foreach ($this->store->related('team', 'race_id', (string) $raceId) as $team) {
                    $this->refresh($race, (int) $team['id']);
                }
            }
            return ['events' => $this->events];
        });
    }
    /** @param array<string,string> $values */
    private function validateTeam(array $values): void
    {
        if (isset($values['team_name']) && (trim($values['team_name']) === '' || mb_strlen($values['team_name']) > 255)) {
            throw new ParticipationException('invalid_team_name');
        }
        if (isset($values['donation_goal']) && (!preg_match('/^[0-9]{1,10}$/D', $values['donation_goal']) || (float) $values['donation_goal'] > 4294967295)) {
            throw new ParticipationException('invalid_donation_goal');
        }
    }
    /**
     * @param array<string,string|null> $race
     * @param array<string,string|null> $team
     * @param array<string,string> $values
     */
    private function changeTeam(array $race, array $team, array $values, bool $organizer): void
    {
        $this->validateTeam($values);
        $changes = [];
        foreach (array_intersect_key($values, array_flip(self::TEAM_FIELDS)) as $field => $value) {
            if (($team[$field] ?? '') === $value) {
                continue;
            }
            $this->permit($field === 'team_name' ? 'name' : 'content', $race, $organizer);
            $changes[$field] = $value;
        }
        $this->store->update('team', $team, $changes);
    }
    /** @param array<string,string> $values */
    private function profileValues(int $user, array $values, bool $required): void
    {
        unset($values['language']);
        $profile = array_replace($this->store->profile($user), $values);
        if ($required && (trim($profile['first_name'] ?? '') === '' || trim($profile['last_name'] ?? '') === '')) {
            throw new ParticipationException('required_names');
        }
        $this->store->saveProfile($user, $values);
    }
    private function refreshUser(int $user): void
    {
        $seen = [];
        foreach ($this->store->related('teammate', 'user_id', (string) $user) as $member) {
            $id = (int) $member['team_id'];
            if (!isset($seen[$id])) {
                $team = $this->store->find('team', $id);
                if ($team) {
                    $this->refresh($this->race((int) $team['race_id']), $id);
                }
                $seen[$id] = true;
            }
        }
    }
    /**
     * @param array<string,string|null> $race
     * @param list<string> $emails
     */
    private function invite(array $race, int $team, array $emails, int $user): void
    {
        $this->permit('invite', $race);
        if (count($emails) > 100) {
            throw new ParticipationException('invitation_batch_too_large');
        }
        $pending = [];
        foreach ($this->store->related('token', 'team_id', (string) $team) as $token) {
            if ($token['consumed_at'] === null && $token['revoked_at'] === null) {
                $pending[strtolower((string) $token['email'])] = true;
            }
        }
        foreach ($this->store->related('invitation', 'team_id', (string) $team) as $legacy) {
            if (!$this->store->related('token', 'token_hash', hash('sha256', 'legacy:' . $legacy['code']))) {
                $pending[strtolower((string) $legacy['email'])] = true;
            }
        }
        foreach (array_unique($emails) as $email) {
            $key = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ParticipationException('invalid_email');
            }
            if (isset($pending[$key])) {
                continue;
            }
            $code = 'v2-' . bin2hex($this->random->bytes(32));
            $hash = hash('sha256', $code);
            if ($this->store->related('token', 'token_hash', $hash)) {
                throw new ParticipationException('token_collision');
            }
            $this->store->insert('token', ['token_hash' => $hash,'team_id' => (string) $team,'email' => $email,'created_at' => (string) $this->clock->now()]);
            $this->events[] = ['action' => 'invitation','team' => $team,'user' => $user,'email' => $email,'code' => $code];
            $pending[$key] = true;
        }
    }
    /** @param array<string,string|null> $row */
    private function history(string $kind, array $row, int $race, int $team): void
    {
        $hex = [];
        foreach ($row as $key => $value) {
            $hex[$key] = $value === null ? null : bin2hex($value);
        }
        $this->store->insert('history', ['race_id' => (string) $race,'team_id' => (string) $team,'created_at' => (string) $this->clock->now(),'kind' => $kind,'payload' => json_encode($hex, JSON_THROW_ON_ERROR)]);
    }
    /** @return array<string,mixed> */
    public function leave(int $raceId, int $user): array
    {
        $this->events = [];
        return $this->store->atomic(function () use ($raceId, $user): array {
            $race = $this->race($raceId);
            $this->permit('leave', $race);
            if (!($this->authorize)('self', $user)) {
                throw new ParticipationException('not_permitted');
            }
            $team = $this->owned($raceId, $user);
            if (!$team) {
                throw new ParticipationException('missing_team');
            }
            foreach ($this->members((int) $team['id']) as $member) {
                if ((int) $member['user_id'] === $user) {
                    $this->history('member_removed', $member, $raceId, (int) $team['id']);
                    $this->store->remove('teammate', $member);
                }
            }
            $this->refresh($race, (int) $team['id']);
            if (!$this->members((int) $team['id'])) {
                $this->revoke((int) $team['id']);
            }
            return ['team' => (int) $team['id'],'events' => $this->events];
        });
    }
    /** @return list<array{email:string,legacy:bool}> */
    public function pending(int $race, int $user): array
    {
        if (!($this->authorize)('self', $user)) {
            throw new ParticipationException('not_permitted');
        }
        $team = $this->owned($race, $user);
        if (!$team) {
            return [];
        }
        $pending = [];
        foreach ($this->store->related('token', 'team_id', (string) $team['id']) as $token) {
            if ($token['consumed_at'] === null && $token['revoked_at'] === null) {
                $pending[] = ['email' => (string) $token['email'], 'legacy' => false];
            }
        }
        foreach ($this->store->related('invitation', 'team_id', (string) $team['id']) as $legacy) {
            if (!$this->store->related('token', 'token_hash', hash('sha256', 'legacy:' . $legacy['code']))) {
                $pending[] = ['email' => (string) $legacy['email'], 'legacy' => true];
            }
        }
        return $pending;
    }
    /** @return array<string,mixed> */
    public function cancelInvitation(int $raceId, int $user, string $email): array
    {
        return $this->store->atomic(function () use ($raceId, $user, $email): array {
            $race = $this->race($raceId);
            $this->permit('invite', $race);
            if (!($this->authorize)('self', $user)) {
                throw new ParticipationException('not_permitted');
            }
            $team = $this->owned($raceId, $user);
            if (!$team || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ParticipationException('invalid_invitation');
            }
            foreach ($this->store->related('token', 'team_id', (string) $team['id']) as $token) {
                if (strcasecmp((string) $token['email'], $email) === 0 && $token['consumed_at'] === null && $token['revoked_at'] === null) {
                    $this->store->update('token', $token, ['revoked_at' => (string) $this->clock->now()]);
                }
            }
            foreach ($this->store->related('invitation', 'team_id', (string) $team['id']) as $legacy) {
                $hash = hash('sha256', 'legacy:' . $legacy['code']);
                if (strcasecmp((string) $legacy['email'], $email) === 0 && !$this->store->related('token', 'token_hash', $hash)) {
                    $this->store->insert('token', ['token_hash' => $hash, 'team_id' => $team['id'], 'email' => $legacy['email'], 'created_at' => (string) $this->clock->now(), 'revoked_at' => (string) $this->clock->now()]);
                }
            }
            return ['events' => []];
        });
    }
    private function revoke(int $team): void
    {
        foreach ($this->store->related('token', 'team_id', (string) $team) as $token) {
            if ($token['consumed_at'] === null && $token['revoked_at'] === null) {
                $this->store->update('token', $token, ['revoked_at' => (string) $this->clock->now()]);
            }
        }
        foreach ($this->store->related('invitation', 'team_id', (string) $team) as $legacy) {
            $hash = hash('sha256', 'legacy:' . $legacy['code']);
            if (!$this->store->related('token', 'token_hash', $hash)) {
                $this->store->insert('token', ['token_hash' => $hash,'team_id' => (string) $team,'email' => $legacy['email'],'created_at' => (string) $this->clock->now(),'revoked_at' => (string) $this->clock->now()]);
            }
        }
    }
    /**
     * @param array<string,string> $profile
     * @return array<string,mixed>
     */
    public function profile(int $user, array $profile): array
    {
        $this->events = [];
        return $this->store->atomic(function () use ($user, $profile): array {
            if (!($this->authorize)('profile', $user) || !$this->store->find('user', $user)) {
                throw new ParticipationException('not_permitted');
            }
            $this->profileValues($user, $profile, false);
            $this->refreshUser($user);
            return ['events' => $this->events];
        });
    }
    /**
     * @param list<int> $members
     * @param array<string,string> $values
     * @param array<int,string> $languages
     * @return array<string,mixed>
     */
    public function administer(int $raceId, int $id, array $values, array $members, array $languages, ?int $route, string $revision = ''): array
    {
        $this->events = [];
        return $this->store->atomic(function () use ($raceId, $id, $values, $members, $languages, $route, $revision): array {
            if (!($this->authorize)('team', $id)) {
                throw new ParticipationException('not_permitted');
            }
            $team = $id ? $this->store->find('team', $id) : [];
            if ($id && (!$team || (int) $team['race_id'] !== $raceId)) {
                throw new ParticipationException('cross_race_review_required');
            }
            $race = $this->race($raceId);
            $this->permit('membership', $race, true);
            if ($revision !== '' && !hash_equals($this->revision($raceId, $id), $revision)) {
                throw new ParticipationException('stale_record');
            }
            if (!$team) {
                if (!isset($values['team_name'])) {
                    throw new ParticipationException('invalid_team_name');
                }
                $this->validateTeam($values);
                $id = $this->store->insert('team', ['race_id' => (string) $raceId,'team_name' => $values['team_name'], 'complete' => '0','route_id' => '0']);
                $team = $this->store->find('team', $id);
            }
            $previousMembers = array_map(static fn (array $member): int => (int) $member['user_id'], $this->members($id));
            $membershipChanged = array_diff($members, $previousMembers) || array_diff($previousMembers, $members);
            if (count($members) !== count(array_unique($members)) || ($membershipChanged && count($members) > (int) ($race['num_teammember'] ?? 3))) {
                throw new ParticipationException('invalid_members');
            }
            foreach ($members as $user) {
                $existing = $this->owned($raceId, $user);
                if (!$this->store->find('user', $user) || ($existing && (int) $existing['id'] !== $id)) {
                    throw new ParticipationException('duplicate_membership_review');
                }
            }
            $kept = [];
            foreach ($this->members($id) as $member) {
                $user = (int) $member['user_id'];
                if (!in_array($user, $members, true)) {
                    $this->history('member_removed', $member, $raceId, $id);
                    $this->store->remove('teammate', $member);
                } else {
                    if (isset($languages[$user])) {
                        $this->store->update('teammate', $member, ['language' => $languages[$user]]);
                    }
                    $kept[] = $user;
                }
            }
            foreach (array_diff($members, $kept) as $user) {
                $this->store->insert('teammate', ['team_id' => (string) $id,'user_id' => (string) $user,'paid' => '0','waiver' => '0','language' => $languages[$user] ?? 'en']);
            }
            $this->changeTeam($race, $team, $values, true);
            $team = $this->refresh($race, $id);
            if ($route !== null) {
                $this->route($race, $team, $route, true);
            }
            return ['team' => $id,'events' => $this->events];
        });
    }
    /**
     * @param list<int> $ids
     * @return array<string,mixed>
     */
    public function confirm(array $ids, string $field, bool $value): array
    {
        $this->events = [];
        if (!in_array($field, ['paid','waiver'], true) || !$ids || count($ids) > 500) {
            throw new ParticipationException('invalid_confirmation');
        }
        return $this->store->atomic(function () use ($ids, $field, $value): array {
            $teams = [];
            foreach (array_unique($ids) as $id) {
                if (!($this->authorize)('member', $id)) {
                    throw new ParticipationException('not_permitted');
                }
                $member = $this->store->find('teammate', $id);
                $team = $this->store->find('team', (int) ($member['team_id'] ?? 0));
                if (!$member || !$team) {
                    throw new ParticipationException('missing_member');
                }
                $race = $this->race((int) $team['race_id']);
                $this->permit('confirm', $race, true);
                $this->store->update('teammate', $member, [$field => $value ? '1' : '0']);
                $teams[(int) $team['id']] = $race;
            }
            foreach ($teams as $id => $race) {
                $this->refresh($race, $id);
            }
            return ['events' => $this->events];
        });
    }
    /** @return array<string,mixed> */
    public function delete(int $id): array
    {
        return $this->store->atomic(function () use ($id): array {
            if (!($this->authorize)('delete', $id)) {
                throw new ParticipationException('not_permitted');
            }
            $team = $this->store->find('team', $id);
            if (!$team || (int) $this->race((int) $team['race_id'])['status'] === 4) {
                throw new ParticipationException('phase_locked');
            }
            if ($this->store->related('sponsor', 'team_id', (string) $id)) {
                throw new ParticipationException('dependent_records');
            }
            foreach ($team as $field => $value) {
                if (($field === 'total_points' || str_contains($field, '_stage_')) && (int) $value !== 0) {
                    throw new ParticipationException('dependent_records');
                }
            }
            $this->history('team_removed', $team, (int) $team['race_id'], $id);
            foreach ($this->members($id) as $member) {
                $this->history('member_removed', $member, (int) $team['race_id'], $id);
                $this->store->remove('teammate', $member);
            }
            $this->revoke($id);
            $this->store->remove('team', $team);
            return ['events' => []];
        });
    }
}
