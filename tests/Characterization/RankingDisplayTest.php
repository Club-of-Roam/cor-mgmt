<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/LegacyEnvironment.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-races.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-teams.php';

/** A renderer characterization, not a WordPress shortcode or database integration test. */
final class RankingDisplayTest extends TestCase
{
    private array $saved = [];
    private array $get;

    protected function setUp(): void
    {
        $this->get = $_GET;
        $_GET = [];
        foreach (['wpdb', 'h3_mgmt_teams', 'h3_mgmt_races', 'h3_mgmt_utilities', 'information_text'] as $key) {
            $this->saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
        foreach ($this->saved as $key => [$exists, $value]) {
            if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
        }
    }

    private function renderer(int $phase = 3, array $points = [100, 100, 90]): \H3_MGMT_Races
    {
        $teams = [];
        foreach ($points as $index => $total) {
            $team = ['id' => 42 + $index, 'team_name' => 'Synthetic ' . $index,
                'route_id' => $index === 1 ? 8 : 7, 'total_points' => $total];
            for ($i = 1; $i <= 6; $i++) {
                foreach (['rank_stage_', 'extra_stage_', 'amount_extra_stage_', 'vary_extra_stage_'] as $field) {
                    $team[$field . $i] = 0;
                }
            }
            $teams[] = $team;
        }
        $repository = $this->getMockBuilder(\H3_MGMT_Teams::class)->disableOriginalConstructor()
            ->onlyMethods(['get_teams_meta', 'mate_name_string'])->getMock();
        $repository->expects($phase < 3 ? self::never() : self::once())->method('get_teams_meta')->with([
            'orderby' => 'total_points', 'order' => 'DESC', 'exclude_incomplete' => true,
            'extra_fields' => [], 'parent' => 9, 'parent_type' => 'race',
        ])->willReturn([count($teams), count($teams), 0, $teams]);
        $repository->method('mate_name_string')->willReturn('Synthetic participants');
        $GLOBALS['h3_mgmt_teams'] = $repository;
        $races = $this->getMockBuilder(\H3_MGMT_Races::class)->disableOriginalConstructor()
            ->onlyMethods(['get_race_information_text', 'get_race_setting', 'get_routes', 'get_route_color'])->getMock();
        $races->method('get_race_information_text')->with(9)->willReturn([26 => 'Not started']);
        $races->method('get_race_setting')->with(9)->willReturn([
            'status' => $phase, 'extra_point_amount' => 5, 'team_overview_link' => '/teams',
        ]);
        $races->method('get_routes')->willReturn([7 => 'Route A', 8 => 'Route B']);
        $races->method('get_route_color')->willReturn('123456');
        $GLOBALS['h3_mgmt_races'] = $races;
        return $races;
    }

    public function testTiesUseCompetitionRanksAndSeparateRouteRanks(): void
    {
        $html = $this->renderer()->ranking_table(['race' => 9, 'top' => '0', 'show_nav' => 0, 'show_stages' => 0]);
        preg_match_all('~<th class="tac">(\d+)</th><th class="tac">(\d+)</th><th class="tac">(\d+)</th>~', $html, $rows, PREG_SET_ORDER);
        self::assertSame([['1', '1', '100'], ['1', '1', '100'], ['3', '2', '90']],
            array_map(static fn (array $row): array => array_slice($row, 1), $rows));
    }

    #[DataProvider('limits')]
    public function testLegacyTopLimit($top, int $expectedRows, int $teamCount = 3): void
    {
        $html = $this->renderer(3, array_fill(0, $teamCount, 100))->ranking_table([
            'race' => 9, 'top' => $top, 'show_nav' => 0, 'show_stages' => 0,
        ]);
        self::assertSame($expectedRows, substr_count($html, 'class="first_tac"'));
    }

    public static function limits(): array
    {
        return ['integer zero with three teams outputs one: known defect' => [0, 1], 'string zero' => ['0', 3],
            'top two outputs only one: known defect' => ['2', 1],
            'top eight outputs all ten teams: known defect' => ['8', 10, 10]];
    }

    #[DataProvider('closedPhases')]
    public function testPreStartPhasesDoNotFetchRankingTeams(int $phase): void
    {
        $html = $this->renderer($phase)->ranking_table(['race' => 9]);
        self::assertStringContainsString('Not started', $html);
        self::assertStringNotContainsString('<table', $html);
    }

    public static function closedPhases(): array { return [[0], [1], [2]]; }

    public function testFirstTotalAtSentinelValueReceivesRankZero(): void
    {
        $html = $this->renderer(3, [999])->ranking_table(['race' => 9, 'show_nav' => 0, 'show_stages' => 0]);
        self::assertStringContainsString('<th class="tac">0</th><th class="tac">0</th><th class="tac">999</th>', $html);
    }
}
