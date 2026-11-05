<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Corrected scoring is opt-in; never use this calculator to rewrite historical totals. */
final class Scoring
{
    public const VERSION = '2.0.0';
    public const PLACEMENTS = [0 => 0, 1 => 20, 2 => 17, 3 => 14, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 5, 9 => 4, 10 => 3, 11 => 2, 12 => 1, 99 => 0];

    /** @param array{rank:int,one:int,fixed:int,variable:int} $entry
     * @param array<string,mixed> $rules
     */
    public static function points(array $entry, array $rules, int $stage): int
    {
        if (!isset(self::PLACEMENTS[$entry['rank']]) || $stage < 1) {
            throw new \InvalidArgumentException('invalid_rank');
        }
        foreach (['one', 'fixed'] as $flag) {
            if (!in_array($entry[$flag], [0, 1], true)) {
                throw new \InvalidArgumentException('invalid_bonus');
            }
        }
        if ($entry['variable'] < 0 || $entry['variable'] > 255) {
            throw new \InvalidArgumentException('invalid_bonus');
        }
        $amount = $rules['extra_point_amount'] ?? 0;
        if (!is_scalar($amount) || !preg_match('/^[0-9]{1,5}$/D', (string) $amount) || (int) $amount > 65535) {
            throw new \InvalidArgumentException('invalid_rules');
        }
        if (
            ($entry['one'] && empty($rules['one_extra_point']))
            || ($entry['fixed'] && empty($rules['amount_extra_point']))
            || ($entry['variable'] && (empty($rules['vary_extra_point_field']) || $stage !== 6))
        ) {
            throw new \InvalidArgumentException('bonus_disabled');
        }
        return self::PLACEMENTS[$entry['rank']] + $entry['one'] + $entry['fixed'] * (int) $amount + $entry['variable'];
    }

    /** Competition ranking: stable ID order within ties, independently of display limits.
     * @param list<array{id:int,points:int}> $teams
     * @return list<array{id:int,points:int,position:int}>
     */
    public static function ranking(array $teams, int $limit = 0): array
    {
        usort($teams, static fn (array $a, array $b): int => ($b['points'] <=> $a['points']) ?: ($a['id'] <=> $b['id']));
        $previous = null;
        $position = 0;
        $output = [];
        foreach ($teams as $index => $team) {
            if ($team['points'] !== $previous) {
                $position = $index + 1;
            }
            $output[] = $team + ['position' => $position];
            $previous = $team['points'];
        }
        return $limit > 0 ? array_slice($output, 0, $limit) : $output;
    }
}
