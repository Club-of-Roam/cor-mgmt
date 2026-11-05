<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Results;

use Cor\Mgmt\Domain\Scoring;

/** Diagnostic only: an arithmetic difference is not evidence of an incorrect historical score. */
final class HistoricalComparison
{
    /** @return list<array{team:int,stored:string|null,candidate:int|null,delta:int|null,issues:list<string>,source_hash:string}> */
    public static function report(ResultContext $context): array
    {
        $output = [];
        $rules = $context->rules();
        foreach ($context->teams as $team) {
            $source = ['total_points' => $team['total_points'] ?? null];
            $issues = [];
            $candidate = 0;
            for ($slot = 1; $slot <= 6; $slot++) {
                $values = [];
                foreach (['rank', 'extra', 'amount_extra', 'vary_extra'] as $prefix) {
                    $field = $prefix . '_stage_' . $slot;
                    $source[$field] = $team[$field] ?? null;
                    $raw = $team[$field] ?? null;
                    if ($raw === null || !preg_match('/^[0-9]{1,5}$/D', $raw)) {
                        $issues[] = 'invalid_' . $field;
                        continue;
                    }
                    $values[$prefix] = (int) $raw;
                }
                if (!isset($values['rank'], $values['extra'], $values['amount_extra'], $values['vary_extra'])) {
                    continue;
                }
                $amount = $rules['extra_point_amount'] ?? null;
                if (
                    !isset(Scoring::PLACEMENTS[$values['rank']])
                    || !in_array($values['extra'], [0, 1], true) || !in_array($values['amount_extra'], [0, 1], true)
                    || $values['vary_extra'] > 255 || !is_scalar($amount) || !preg_match('/^[0-9]{1,5}$/D', (string) $amount) || (int) $amount > 65535
                ) {
                    $issues[] = 'unresolved_slot_' . $slot;
                    continue;
                }
                $candidate += Scoring::PLACEMENTS[$values['rank']] + $values['extra'] + $values['amount_extra'] * (int) $amount + $values['vary_extra'];
                $matches = array_filter($context->stages, static fn (array $stage): bool => (int) $stage['route_id'] === (int) $team['route_id'] && (int) $stage['number'] === $slot);
                if (array_sum($values) && count($matches) !== 1) {
                    $issues[] = 'unmapped_slot_' . $slot;
                }
            }
            $stored = $team['total_points'] ?? null;
            if ($stored === null || !preg_match('/^[0-9]{1,10}$/D', $stored)) {
                $issues[] = 'invalid_total';
            }
            $output[] = ['team' => (int) $team['id'], 'stored' => $stored, 'candidate' => $issues ? null : $candidate, 'delta' => $issues ? null : $candidate - (int) $stored, 'issues' => $issues, 'source_hash' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR))];
        }
        return $output;
    }
}
