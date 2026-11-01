<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-races.php';

/** F-RANK-01: historical race rules, without bootstrapping WordPress. */
final class RankingPointsTest extends TestCase
{
    #[DataProvider('placements')]
    public function testHistoricalPlacementPoints(int $rank, int $expected): void
    {
        $races = (new ReflectionClass(\H3_MGMT_Races::class))->newInstanceWithoutConstructor();
        self::assertSame($expected, $races->points_conversion($rank));
        // wpdb and browser inputs commonly supply the same value as a string.
        self::assertSame($expected, $races->points_conversion((string) $rank));
    }

    public static function placements(): array
    {
        return [
            'winner' => [1, 20], 'second' => [2, 17], 'third' => [3, 14],
            'fourth' => [4, 12], 'fifth' => [5, 10], 'sixth' => [6, 8],
            'seventh' => [7, 6], 'eighth' => [8, 5], 'ninth' => [9, 4],
            'tenth' => [10, 3], 'eleventh' => [11, 2], 'twelfth' => [12, 1],
            'unplaced' => [0, 0], 'disqualified' => [99, 0],
        ];
    }

    public function testDisqualificationLabelDoesNotChangeNumericMode(): void
    {
        $races = (new ReflectionClass(\H3_MGMT_Races::class))->newInstanceWithoutConstructor();
        self::assertSame('disq.', $races->points_conversion(99, true));
        self::assertSame(0, $races->points_conversion(99));
        self::assertSame(20, $races->points_conversion(1, true));
    }
}
