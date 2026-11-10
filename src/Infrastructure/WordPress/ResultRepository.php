<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Results\ResultContext;
use Cor\Mgmt\Application\Results\ResultException;
use Cor\Mgmt\Application\Results\ResultStore;
use Cor\Mgmt\Domain\Scoring;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ResultsSchema;

final class ResultRepository implements ResultStore
{
    private bool $writing = false;
    public function __construct(private readonly Database $db)
    {
    }
    public function atomic(callable $operation): array
    {
        return (new LegacyEventStore($this->db))->exclusive(function () use ($operation): array {
            if (!ResultsSchema::ready($this->db)) {
                throw new ResultException('results_setup_required');
            }
            foreach (ResultsSchema::definitions() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            $this->writing = true;
            try {
                return $operation();
            } finally {
                $this->writing = false;
            }
        }, true);
    }
    /** @return list<array<string,string|null>> */
    private function rows(string $suffix, string $field, int $id): array
    {
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE %i=%d', $this->db->table($suffix), $field, $id) . ' ORDER BY ' . ($suffix === 'cor_mgmt_results' ? 'team_id,stage_id' : ($suffix === 'cor_mgmt_scoring_profiles' ? 'race_id' : 'id')) . ($this->writing ? ' FOR UPDATE' : ''));
    }
    public function context(int $route): ResultContext
    {
        $row = $this->rows('h3_mgmt_routes', 'id', $route)[0] ?? [];
        if (!$row) {
            throw new ResultException('missing_route');
        }
        $race = $this->race((int) $row['race_id']);
        return new ResultContext(
            $race->race,
            $row,
            array_values(array_filter($race->stages, static fn (array $stage): bool => (int) $stage['route_id'] === $route)),
            array_values(array_filter($race->teams, static fn (array $team): bool => (int) $team['route_id'] === $route)),
            $race->results,
            $race->version,
            $race->generation
        );
    }
    /** @return list<array<string,string|null>> */
    public function routes(int $race): array
    {
        return $this->rows('h3_mgmt_routes', 'race_id', $race);
    }
    public function race(int $race): ResultContext
    {
        if ($this->writing && Archive::locked($this->db, $race)) {
            throw new ResultException('archive_locked');
        }
        $row = $this->rows('h3_mgmt_races', 'id', $race)[0] ?? [];
        if (!$row) {
            throw new ResultException('missing_race');
        }
        $installed = ResultsSchema::ready($this->db);
        if (!$installed && $this->db->option('cor_mgmt_results_install') !== null) {
            throw new ResultException('results_setup_required');
        }
        $profile = $installed ? ($this->rows('cor_mgmt_scoring_profiles', 'race_id', $race)[0] ?? []) : [];
        return new ResultContext($row, [], $this->rows('h3_mgmt_stages', 'race_id', $race), $this->rows('h3_mgmt_teams', 'race_id', $race), $installed ? $this->rows('cor_mgmt_results', 'race_id', $race) : [], $profile['version'] ?? '', $installed ? ($this->db->scalar($this->db->prepare('SELECT COALESCE(MAX(id),0) FROM %i WHERE race_id=%d', $this->db->table('cor_mgmt_result_history'), $race)) ?? '0') : '0');
    }
    public function enable(int $race): void
    {
        $this->db->assertLock();
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,version) VALUES (%d,%s) ON DUPLICATE KEY UPDATE version=%s', $this->db->table('cor_mgmt_scoring_profiles'), $race, Scoring::VERSION, Scoring::VERSION));
    }
    public function save(ResultContext $context, int $stage, array $entries, array $totals, int $actor, int $time): void
    {
        $this->db->assertLock();
        $ruleValues = array_intersect_key($context->rules(), array_flip(['one_extra_point', 'amount_extra_point', 'extra_point_amount', 'vary_extra_point_field']));
        $rules = json_encode($ruleValues, JSON_THROW_ON_ERROR);
        $race = (int) $context->race['id'];
        $route = (int) $context->route['id'];
        $number = 0;
        foreach ($context->stages as $row) {
            if ((int) $row['id'] === $stage) {
                $number = (int) $row['number'];
            }
        }
        foreach ($entries as $team => $_) {
            $existing = $this->db->rows($this->db->prepare('SELECT race_id,route_id,version FROM %i WHERE team_id=%d AND stage_id=%d FOR UPDATE', $this->db->table('cor_mgmt_results'), $team, $stage))[0] ?? [];
            if ($existing && (($existing['race_id'] ?? '') !== (string) $race || ($existing['route_id'] ?? '') !== (string) $route || ($existing['version'] ?? '') !== Scoring::VERSION)) {
                throw new ResultException('result_identity_conflict');
            }
        }
        $same = true;
        foreach ($entries as $team => $entry) {
            $old = array_values(array_filter($context->results, static fn (array $row): bool => (int) $row['team_id'] === $team && (int) $row['stage_id'] === $stage))[0] ?? [];
            foreach (['rank' => 'rank_value', 'one' => 'extra_one', 'fixed' => 'extra_fixed', 'variable' => 'extra_variable', 'points' => 'points'] as $field => $column) {
                $same = $same && isset($old[$column]) && $old[$column] === (string) $entry[$field];
            }
            $same = $same && ($old['rules'] ?? '') === $rules && ($old['version'] ?? '') === Scoring::VERSION;
            foreach ($context->teams as $row) {
                if ((int) $row['id'] === $team) {
                    $same = $same && $row['total_points'] === (string) $totals[$team];
                }
            }
        }
        if ($same) {
            return;
        }
        foreach ($entries as $team => $entry) {
            $this->db->execute($this->db->prepare('INSERT INTO %i (team_id,stage_id,race_id,route_id,rank_value,extra_one,extra_fixed,extra_variable,points,rules,version) VALUES (%d,%d,%d,%d,%d,%d,%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE rank_value=VALUES(rank_value),extra_one=VALUES(extra_one),extra_fixed=VALUES(extra_fixed),extra_variable=VALUES(extra_variable),points=VALUES(points),rules=VALUES(rules),version=VALUES(version)', $this->db->table('cor_mgmt_results'), $team, $stage, $race, $route, $entry['rank'], $entry['one'], $entry['fixed'], $entry['variable'], $entry['points'], $rules, Scoring::VERSION));
            // Compatibility projection for team cards/statistics. Normalized rows are authoritative.
            $values = ['total_points' => $totals[$team]];
            if ($number >= 1 && $number <= 6) {
                $values += ['rank_stage_' . $number => $entry['rank'], 'extra_stage_' . $number => $entry['one'], 'amount_extra_stage_' . $number => $entry['fixed'], 'vary_extra_stage_' . $number => $entry['variable']];
            }
            $set = [];
            foreach ($values as $field => $value) {
                $set[] = $this->db->prepare('%i=%d', $field, $value);
            }
            $this->db->execute($this->db->prepare('UPDATE %i SET ', $this->db->table('h3_mgmt_teams')) . implode(',', $set) . $this->db->prepare(' WHERE id=%d AND race_id=%d AND route_id=%d AND complete=1', $team, $race, $route));
        }
        $payload = json_encode(['version' => Scoring::VERSION, 'stage' => $stage, 'before' => $context->results, 'after' => $entries, 'totals' => $totals, 'rules' => $ruleValues], JSON_THROW_ON_ERROR);
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,route_id,actor_id,created_at,payload) VALUES (%d,%d,%d,%d,%s)', $this->db->table('cor_mgmt_result_history'), $race, $route, $actor, $time, $payload));
    }
}
