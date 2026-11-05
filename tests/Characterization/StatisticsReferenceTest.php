<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__) . '/Support/LegacyEnvironment.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-races.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-teams.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-sponsors.php';

/** Public statistic selectors against independent, synthetic repository totals. */
final class StatisticsReferenceTest extends TestCase
{
    #[DataProvider('selectors')]
    public function testCountersKeepTeamParticipantAndDonationScopesSeparate(string $selector, int $expected): void
    {
        $keys = ['h3_mgmt_teams', 'h3_mgmt_sponsors'];
        $saved = [];
        foreach ($keys as $key) { $saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null]; }
        try {
            $teams = $this->getMockBuilder(\H3_MGMT_Teams::class)->disableOriginalConstructor()
                ->onlyMethods(['get_teams_meta', 'get_participants_meta'])->getMock();
            $teams->expects(self::once())->method('get_teams_meta')->with(self::callback(
                static fn ($args) => $args['parent'] === 9 && $args['parent_type'] === 'race' && !$args['exclude_incomplete']
            ))->willReturn([5, 3, 2, []]);
            $teams->expects(self::once())->method('get_participants_meta')->willReturn([11, 7, 4, []]);
            $sponsors = $this->getMockBuilder(\H3_MGMT_Sponsors::class)->disableOriginalConstructor()
                ->onlyMethods(['get_sponsors_meta'])->getMock();
            $sponsors->expects(self::once())->method('get_sponsors_meta')->with(self::callback(
                static fn ($args) => $args['parent'] === 9 && $args['parent_type'] === 'race' && $args['exclude_unpaid']
            ))->willReturn([[], ['race' => 8, 'sponsor' => 4, 'owner' => 2, 'patron' => 1, 'structure' => 1],
                ['race' => 123, 'thumbs' => 9]]);
            $GLOBALS['h3_mgmt_teams'] = $teams;
            $GLOBALS['h3_mgmt_sponsors'] = $sponsors;
            $races = (new ReflectionClass(\H3_MGMT_Races::class))->newInstanceWithoutConstructor();
            self::assertSame($expected, $races->race_stats(['race' => 9, 'stat' => $selector, 'offset' => 3]));
        } finally {
            foreach ($saved as $key => [$exists, $value]) {
                if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
            }
        }
    }

    public static function selectors(): array
    {
        return [['team-count', 5], ['complete-count', 3], ['participants-count', 11],
            ['participants-complete-count', 7], ['race-sponsors-count', 8], ['sponsors-count', 4],
            ['owners-count', 2], ['patrons-count', 1], ['structure-count', 1],
            ['donations-total', 120], ['thumbs', 9], ['cpk', 9]];
    }

    public function testLegacyDonationCountingConventionsRemainAndRaceCountKeyIsProvided(): void
    {
        $saved = [];
        foreach (['wpdb', 'h3_mgmt_teams', 'h3_mgmt_utilities'] as $key) {
            $saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        try {
            $GLOBALS['wpdb'] = new class {
                public string $prefix = 'synthetic_';
                public function get_results($sql, $format): array
                {
                    return [
                        ['id' => 1, 'team_id' => 42, 'method' => 'debit', 'paid' => 0, 'type' => 'sponsor', 'donation' => 10],
                        ['id' => 2, 'team_id' => 42, 'method' => 'paypal', 'paid' => 0, 'type' => 'sponsor', 'donation' => 20],
                        ['id' => 3, 'team_id' => 42, 'method' => 'Betterplace', 'paid' => 1, 'type' => 'owner', 'donation' => 100],
                    ];
                }
            };
            $teams = $this->getMockBuilder(\H3_MGMT_Teams::class)->disableOriginalConstructor()
                ->onlyMethods(['get_team_name'])->getMock();
            $teams->method('get_team_name')->willReturn('Synthetic team');
            $GLOBALS['h3_mgmt_teams'] = $teams;
            $GLOBALS['h3_mgmt_utilities'] = new class {
                public function sort_by_key($rows, $key, $order): array { return $rows; }
            };
            $sponsors = (new ReflectionClass(\H3_MGMT_Sponsors::class))->newInstanceWithoutConstructor();
            [$rows, $counts, $donations] = $sponsors->get_sponsors_meta(['exclude_unpaid' => true]);
            self::assertSame([3], array_column($rows, 'id'));
            self::assertSame(110, $donations['race']);
            self::assertSame(1, $counts['paypal-unpaid']);
            self::assertSame(1, $counts['debit']);
            self::assertSame(2, $counts['race']); // Previously absent; the existing computed count is now exposed.
        } finally {
            foreach ($saved as $key => [$exists, $value]) {
                if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
            }
        }
    }
}
