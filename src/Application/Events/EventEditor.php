<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Events;

use Closure;
use Cor\Mgmt\Domain\EventPolicy;
use Cor\Mgmt\Domain\RacePhase;

/** The application use case; capabilities are supplied by the WordPress boundary. */
final class EventEditor
{
    /**
     * @param Closure(string,string,int):bool $authorize
     * @param null|Closure():int $provisionAccount
     * @param null|Closure(int):array<string,mixed> $reconcileParticipation
     */
    public function __construct(private readonly EventStore $store, private readonly Closure $authorize, private readonly ?Closure $provisionAccount = null, private readonly ?Closure $reconcileParticipation = null)
    {
    }
    /**
     * @param array<string,string|null> $row
     * @return array<string,string|null>
     */
    public static function flatten(array $row): array
    {
        if (isset($row['setting'])) {
            $settings = json_decode($row['setting'], true);
            if (!is_array($settings)) {
                throw new EventException('invalid_settings');
            }
            foreach ($settings as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null) && !array_key_exists($key, $row)) {
                    $row[$key] = $value === null ? null : (string) $value;
                }
            }
        }
        return $row;
    }
    /** @param array<string,string|null> $race */
    private function phase(array $race): RacePhase
    {
        $value = self::flatten($race)['status'] ?? '';
        if (!in_array($value, ['0', '1', '2', '3', '4'], true)) {
            throw new EventException('invalid_phase');
        }
        return RacePhase::from((int) $value);
    }
    /**
     * @param array<string,string|null> $row
     * @return array<string,string|null>
     */
    private function parent(string $type, array $row): array
    {
        $race = $type === 'race' ? $row : $this->store->find('race', (int) ($row['race_id'] ?? 0));
        if ($race === []) {
            throw new EventException('missing_parent');
        }
        return $race;
    }
    public function revision(string $type, int $id): string
    {
        if ($id === 0) {
            return '';
        }
        $row = $this->store->find($type, $id);
        if ($row === []) {
            throw new EventException('missing_record');
        }
        return hash('sha256', serialize([$row, $this->parent($type, $row)]));
    }
    /**
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public function save(string $type, int $id, array $input, string $revision, bool $confirmed = false): array
    {
        return $this->store->exclusive(function () use ($type, $id, $input, $revision, $confirmed): array {
            $old = $id ? $this->store->find($type, $id) : [];
            $this->permission($type, 'edit', $id);
            if ($id && ($old === [] || !hash_equals($this->revision($type, $id), $revision))) {
                throw new EventException('stale_record');
            }
            $before = self::flatten($old);
            $values = array_intersect_key($input, EventPolicy::fields($type));
            if ($old === [] && $type === 'race') {
                $values += ['name' => '', 'start' => '0', 'end' => '0', 'logo_url' => '', 'active' => '0', 'status' => '0',
                    'information_text' => '{}', 'num_teammember_min' => '2', 'num_teammember' => '3',
                    'startingpoint' => '1', 'start_route_id' => '0', 'liveticker' => '0', 'liveticker_front' => '0', 'donation' => '0',
                    'dis_shirt_size' => '0', 'dis_mobile_inf' => '0', 'dis_waiver' => '0', 'dis_fee' => '0'];
            }
            if ($old === [] && $type === 'race') {
                foreach (array_keys(EventPolicy::fields('race')) as $field) {
                    $values[$field] ??= in_array($field, ['betterplace_redirect_link', 'donation_link_link', 'team_overview_link'], true) ? '' : '0';
                }
            }
            if ($type === 'stage' && isset($values['route_id'])) {
                $route = $this->store->find('route', $this->integer($values['route_id'], 1));
                if ($route === []) {
                    throw new EventException('missing_parent');
                }
                $this->permission('route', 'edit', (int) $route['id']);
                $values['race_id'] = (string) $route['race_id'];
            }
            if (!$id && $type !== 'race') {
                foreach ($type === 'route' ? ['race_id', 'name', 'max_teams'] : ['route_id', 'number', 'destination'] as $required) {
                    if (!isset($values[$required])) {
                        throw new EventException('missing_field');
                    }
                }
            }
            $next = array_replace($before, $values);
            $race = $old === [] ? ($type === 'race' ? ['setting' => '{"status":0}'] : $this->parent($type, $next)) : $this->parent($type, $old);
            $phase = $this->phase($race);
            if ($phase === RacePhase::Archived) {
                throw new EventException('archived');
            }
            if (!$id && $phase !== RacePhase::BeforeRegistration) {
                throw new EventException('structure_locked');
            }
            $changed = [];
            foreach ($values as $field => $value) {
                if ($id && ($before[$field] ?? null) === $value) {
                    continue;
                }
                if (!EventPolicy::allows($type, $field, $phase)) {
                    throw new EventException('field_locked');
                }
                $changed[$field] = $value;
            }
            if ($type === 'race' && isset($changed['status'])) {
                $target = $this->integer($changed['status'], 0, 4);
                if ($target === 4) {
                    // Full archive snapshots and all writers must be ready before exposing completion.
                    throw new EventException('archive_not_ready');
                }
                if (!$id && $target !== 0) {
                    throw new EventException('create_in_phase_zero');
                }
                foreach ($changed as $field => $value) {
                    if (!EventPolicy::allows('race', $field, RacePhase::from($target))) {
                        throw new EventException('change_phase_separately');
                    }
                }
            }
            if ($type !== 'race') {
                $targetRace = $this->parent($type, $next);
                if ($targetRace['id'] !== $race['id']) {
                    $this->permission('race', 'edit', (int) $targetRace['id']);
                    if ($phase !== RacePhase::BeforeRegistration || $this->phase($targetRace) !== RacePhase::BeforeRegistration) {
                        throw new EventException('structure_locked');
                    }
                    if ($type === 'route' && ($this->store->matching('stage', 'route_id', $id) || $this->store->matching('team', 'route_id', $id))) {
                        throw new EventException('dependent_records');
                    }
                }
            }
            $this->validate($type, $next, $changed);
            $warnings = [];
            if ($type === 'route' && isset($changed['max_teams'])) {
                $occupied = count(array_filter($this->store->matching('team', 'route_id', $id), static fn (array $row): bool => $row['complete'] === '1'));
                $warnings = EventPolicy::capacityWarnings((int) $changed['max_teams'], $occupied);
            }
            if ($type === 'race' && $id && (isset($changed['startingpoint']) || isset($changed['start_route_id'])) && $this->store->matching('team', 'race_id', $id)) {
                $warnings[] = 'starting_points_have_assignments';
            }
            if ($warnings !== [] && !$confirmed) {
                return ['id' => $id, 'warnings' => $warnings, 'confirmation_required' => true];
            }
            if ($type === 'race') {
                $settings = $old === [] ? [] : json_decode((string) $old['setting'], true);
                if (!is_array($settings)) {
                    throw new EventException('invalid_settings');
                }
                $columns = ['name', 'start', 'end', 'logo_url', 'active', 'information_text'];
                $write = [];
                foreach ($changed as $field => $value) {
                    if (in_array($field, $columns, true)) {
                        $write[$field] = $value;
                    } else {
                        $settings[$field] = $value;
                    }
                }
                if ($settings !== ($old === [] ? [] : json_decode((string) $old['setting'], true))) {
                    $write['setting'] = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
                if (($changed['active'] ?? '') === '1') {
                    foreach ($this->store->matching('race', 'active', 1) as $active) {
                        if ((int) $active['id'] !== $id) {
                            $this->permission('race', 'edit', (int) $active['id']);
                            if ($this->phase($active) === RacePhase::Archived) {
                                throw new EventException('archived');
                            }
                            $this->store->write('race', $active, ['active' => '0']);
                        }
                    }
                }
            } else {
                $write = $changed;
                if ($type === 'route' && $this->provisionAccount !== null) {
                    $write['user_id'] = (string) ($this->provisionAccount)();
                }
            }
            $saved = $this->store->write($type, $old, $write);
            $participation = [];
            if (
                $type === 'race' && $id && $this->reconcileParticipation !== null
                && array_intersect_key($changed, array_flip(['status','num_teammember','num_teammember_min','dis_fee','dis_waiver','dis_shirt_size','dis_mobile_inf'])) !== []
            ) {
                $participation = ($this->reconcileParticipation)($saved);
            }
            return ['id' => $saved, 'warnings' => $warnings, 'confirmation_required' => false] + $participation;
        }, ($type === 'race' && (($input['active'] ?? '') === '1' || isset($input['startingpoint']) || isset($input['start_route_id']) || ($this->reconcileParticipation !== null && array_intersect_key($input, array_flip(['status','num_teammember','num_teammember_min','dis_fee','dis_waiver','dis_shirt_size','dis_mobile_inf'])) !== [])))
            || ($type === 'route' && (isset($input['max_teams']) || $this->provisionAccount !== null)));
    }
    /**
     * @param list<int> $ids
     * @return array<string,mixed>
     */
    public function delete(string $type, array $ids): array
    {
        if ($ids === [] || count($ids) > 500) {
            throw new EventException('invalid_identifiers');
        }
        return $this->store->exclusive(function () use ($type, $ids): array {
            $rows = [];
            foreach (array_unique($ids) as $id) {
                $this->permission($type, 'delete', $id);
                $row = $this->store->find($type, $id);
                if ($row === [] || $this->phase($this->parent($type, $row)) !== RacePhase::BeforeRegistration) {
                    throw new EventException('structure_locked');
                }
                $children = match ($type) {
                    'race' => ['route', 'stage', 'team', 'sponsor'],
                    'route' => ['stage', 'team'], 'stage' => ['team'], default => throw new EventException('invalid_resource'),
                };
                foreach ($children as $child) {
                    $field = $type === 'race' ? 'race_id' : 'route_id';
                    $parent = $type === 'stage' ? (int) $row['route_id'] : $id;
                    if ($this->store->matching($child, $field, $parent)) {
                        throw new EventException('dependent_records');
                    }
                }
                if ($type === 'race' && $row['active'] === '1') {
                    throw new EventException('active_race');
                }
                if ($type === 'route' && (self::flatten($this->parent($type, $row))['start_route_id'] ?? '') === (string) $id) {
                    throw new EventException('dependent_records');
                }
                $rows[] = $row;
            }
            foreach ($rows as $row) {
                $this->store->remove($type, $row);
            }
            return ['deleted' => count($rows)];
        }, count($ids) > 1);
    }
    private function permission(string $type, string $action, int $id): void
    {
        if (!($this->authorize)($type, $action, $id)) {
            throw new EventException('not_permitted');
        }
    }
    private function integer(string $value, int $minimum, int $maximum = PHP_INT_MAX): int
    {
        if (
            !preg_match('/^(0|[1-9][0-9]*)$/D', $value) || strlen($value) > strlen((string) $maximum)
            || (strlen($value) === strlen((string) $maximum) && strcmp($value, (string) $maximum) > 0)
            || (int) $value < $minimum
        ) {
            throw new EventException('invalid_number');
        }
        return (int) $value;
    }
    /**
     * @param array<string,string|null> $next
     * @param array<string,string> $changed
     */
    private function validate(string $type, array $next, array $changed): void
    {
        foreach ($changed as $field => $value) {
            if (in_array($field, ['name', 'destination'], true) && (trim($value) === '' || mb_strlen($value) > 250)) {
                throw new EventException('invalid_name');
            }
            if (in_array($field, ['active', 'startingpoint', 'liveticker', 'liveticker_front', 'donation', 'dis_shirt_size', 'dis_mobile_inf', 'dis_waiver', 'dis_fee', 'one_extra_point', 'amount_extra_point', 'vary_extra_point_field', 'show_donation_amount', 'kind_of_donation_tool'], true) || str_starts_with($field, 'question_')) {
                $this->integer($value, 0, 1);
            }
            if (in_array($field, ['max_teams', 'extra_point_amount', 'number'], true)) {
                $this->integer($value, 0, $field === 'number' ? 6 : ($field === 'max_teams' ? 32767 : 65535));
            }
            if (in_array($field, ['start', 'end', 'start_route_id', 'race_id', 'route_id', 'user_id'], true)) {
                $this->integer($value, 0);
            }
            if ($field === 'color_code' && !preg_match('/^[0-9a-fA-F]{6}$/D', $value)) {
                throw new EventException('invalid_color');
            }
            if ($field === 'country_3166_alpha-2' && $value !== '' && !preg_match('/^[A-Z]{2}$/D', $value)) {
                throw new EventException('invalid_country');
            }
        }
        if ($type === 'race') {
            if (isset($changed['num_teammember']) || isset($changed['num_teammember_min'])) {
                $minimum = $this->integer((string) ($next['num_teammember_min'] ?? ''), 1, 100);
                $maximum = $this->integer((string) ($next['num_teammember'] ?? ''), 1, 100);
                if ($minimum > $maximum) {
                    throw new EventException('invalid_team_sizes');
                }
            }
            if ((isset($changed['start']) || isset($changed['end'])) && (int) ($next['start'] ?? 0) > (int) ($next['end'] ?? 0)) {
                throw new EventException('invalid_dates');
            }
            if (isset($changed['start_route_id']) && $changed['start_route_id'] !== '0') {
                $route = $this->store->find('route', (int) $changed['start_route_id']);
                if ($route === [] || ($route['race_id'] ?? '') !== ($next['id'] ?? '')) {
                    throw new EventException('invalid_start_route');
                }
            }
        }
        if ($type === 'stage' && (isset($changed['number']) || isset($changed['route_id']))) {
            foreach ($this->store->matching('stage', 'route_id', (int) ($next['route_id'] ?? 0)) as $stage) {
                if ($stage['id'] !== ($next['id'] ?? '') && $stage['number'] === ($next['number'] ?? '')) {
                    throw new EventException('duplicate_stage_number');
                }
            }
        }
        if (!$next || ($type !== 'race' && (int) ($next['race_id'] ?? 0) < 1)) {
            throw new EventException('missing_parent');
        }
    }
}
