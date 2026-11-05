<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Results;

use Cor\Mgmt\Domain\Scoring;
use Cor\Mgmt\Application\Contracts\Clock;

final class ResultManager
{
    /** @param \Closure(string,int):bool $authorize */
    public function __construct(private readonly ResultStore $store, private readonly Clock $clock, private readonly \Closure $authorize)
    {
    }
    /** Corrected scoring can only be enabled before registration, on an empty result history.
     * @return array<string,mixed>
     */
    public function enable(int $race): array
    {
        return $this->store->atomic(function () use ($race): array {
            if (!(($this->authorize)('race', $race))) {
                throw new ResultException('not_permitted');
            }
            $context = $this->store->race($race);
            if ((int) $context->rules()['status'] !== 0) {
                throw new ResultException('phase_locked');
            }
            if ($context->version !== '' && $context->version !== Scoring::VERSION) {
                throw new ResultException('unsupported_scoring_version');
            }
            if ($context->results !== []) {
                throw new ResultException('historical_results_present');
            }
            foreach ($context->teams as $team) {
                foreach ($team as $field => $value) {
                    if (($field === 'total_points' || preg_match('/^(rank|extra|amount_extra|vary_extra)_stage_/', $field)) && (string) $value !== '0' && $value !== null && $value !== '') {
                        throw new ResultException('historical_results_present');
                    }
                }
            }
            $this->store->enable($race);
            return ['race' => $race, 'version' => Scoring::VERSION];
        });
    }
    public function view(int $route): ResultContext
    {
        $context = $this->store->context($route);
        if (!(($this->authorize)('route', $route))) {
            throw new ResultException('not_permitted');
        }
        if ($context->version !== Scoring::VERSION) {
            throw new ResultException('scoring_setup_required');
        }
        if ((int) $context->rules()['status'] !== 3) {
            throw new ResultException('phase_locked');
        }
        return $context;
    }
    /** @param array<int,array{rank:int,one:int,fixed:int,variable:int}> $entries
     * @return array<string,mixed>
     */
    public function submit(int $route, int $number, array $entries, string $revision, int $actor): array
    {
        return $this->store->atomic(function () use ($route, $number, $entries, $revision, $actor): array {
            $context = $this->view($route);
            if ($actor < 1 || !hash_equals($context->revision(), $revision)) {
                throw new ResultException('stale_results');
            }
            $stages = array_values(array_filter($context->stages, static fn (array $stage): bool => (int) $stage['number'] === $number && $number > 0));
            if (count($stages) !== 1) {
                throw new ResultException('ambiguous_stage');
            }
            $stageId = (int) $stages[0]['id'];
            $ids = array_map(static fn (array $team): int => (int) $team['id'], array_values(array_filter($context->teams, static fn (array $team): bool => (int) $team['complete'] === 1)));
            $posted = array_keys($entries);
            sort($ids);
            sort($posted);
            if ($ids === [] || $posted !== $ids) {
                throw new ResultException('team_selection_changed');
            }
            $scored = [];
            $totals = [];
            foreach ($entries as $id => $entry) {
                try {
                    $points = Scoring::points($entry, $context->rules(), $number);
                } catch (\InvalidArgumentException $error) {
                    throw new ResultException(match ($error->getMessage()) {
                        'invalid_rank' => 'invalid_rank', 'invalid_bonus' => 'invalid_bonus',
                        'invalid_rules' => 'invalid_rules', 'bonus_disabled' => 'bonus_disabled',
                        default => 'invalid_input',
                    });
                }
                $scored[$id] = $entry + ['points' => $points];
                $totals[$id] = $points;
                foreach ($context->results as $old) {
                    if ((int) $old['team_id'] === $id && (int) $old['stage_id'] !== $stageId) {
                        if ((int) $old['route_id'] !== $route) {
                            throw new ResultException('route_changed_with_results');
                        }
                        $totals[$id] += (int) $old['points'];
                    }
                }
            }
            if ($totals !== [] && max($totals) > 4294967295) {
                throw new ResultException('points_overflow');
            }
            // Every row is validated before the first write. The store commits rows, totals and journal together.
            $this->store->save($context, $stageId, $scored, $totals, $actor, $this->clock->now());
            return ['teams' => count($entries), 'route' => $route];
        });
    }
}
