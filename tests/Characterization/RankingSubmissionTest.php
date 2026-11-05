<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;
use UnexpectedValueException;

require_once dirname(__DIR__) . '/Support/LegacyEnvironment.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-races.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-teams.php';

require_once dirname(__DIR__) . '/Support/LegacyRankingReference.php';

/** Retained private legacy calculation with synthetic POST, repository and renderer doubles. */
final class RankingSubmissionTest extends TestCase
{
    private array $saved = [];
    private array $post;

    protected function setUp(): void
    {
        $this->post = $_POST;
        foreach (['wpdb', 'h3_mgmt_teams'] as $key) {
            $this->saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $GLOBALS['wpdb'] = new RankingMemoryDatabase();
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        foreach ($this->saved as $key => [$exists, $value]) {
            if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
        }
    }

    private static function team(array $overrides = [], int $id = 42): array
    {
        $team = ['id' => $id, 'total_points' => 999];
        for ($stage = 1; $stage <= 6; $stage++) {
            foreach (['rank_stage_', 'extra_stage_', 'amount_extra_stage_', 'vary_extra_stage_'] as $field) {
                $team[$field . $stage] = 0;
            }
        }
        return $overrides + $team;
    }

    private function races(array $teams): \H3_MGMT_Races
    {
        $repository = $this->getMockBuilder(\H3_MGMT_Teams::class)->disableOriginalConstructor()
            ->onlyMethods(['get_teams_meta'])->getMock();
        $repository->expects(self::once())->method('get_teams_meta')->with([
            'orderby' => 'team_name', 'exclude_incomplete' => true, 'parent' => 7,
        ])->willReturn([count($teams), count($teams), 0, $teams]);
        $GLOBALS['h3_mgmt_teams'] = $repository;
        $races = $this->getMockBuilder(\Cor\Mgmt\Tests\Support\LegacyRankingReference::class)->disableOriginalConstructor()
            ->onlyMethods(['get_route_parent', 'get_race_setting', 'ranking_submission_form'])->getMock();
        $races->method('get_route_parent')->with(7)->willReturn(9);
        // Deliberately disabled UI switches: the legacy writer still accepts posted flags.
        $races->method('get_race_setting')->with(9)->willReturn([
            'extra_point_amount' => 5, 'one_extra_point' => 0,
            'amount_extra_point' => 0, 'vary_extra_point_field' => 0,
        ]);
        $races->method('ranking_submission_form')->willReturn('synthetic form');
        return $races;
    }

    #[DataProvider('submissions')]
    public function testHistoricalTotalsAndStoredFields(
        int $stage, array $post, array $old, int $expectedTotal, int $one, int $amountFlag, int $vary
    ): void {
        $_POST = $post + ['route' => 7, 'stage' => $stage, 'rank-42' => 1];
        $result = (new \ReflectionMethod(\Cor\Mgmt\Tests\Support\LegacyRankingReference::class, 'persist_ranking'))->invoke($this->races([self::team($old)]));
        self::assertSame('synthetic form', $result);
        self::assertSame([[
            'table' => 'synthetic_h3_mgmt_teams',
            'data' => ['rank_stage_' . $stage => $_POST['rank-42'], 'extra_stage_' . $stage => $one,
                'amount_extra_stage_' . $stage => $amountFlag, 'vary_extra_stage_' . $stage => $vary,
                'total_points' => $expectedTotal],
            'where' => ['id' => 42],
        ]], $GLOBALS['wpdb']->writes);
    }

    public static function submissions(): array
    {
        return [
            'placement replaces stale total' => [1, [], [], 20, 0, 0, 0],
            'string placement and stage sum' => [2, ['rank-42' => '2'], ['rank_stage_1' => 1], 37, 0, 0, 0],
            'other-stage fixed bonuses' => [2, [], ['rank_stage_1' => 2, 'extra_stage_1' => 1,
                'amount_extra_stage_1' => 1], 43, 0, 0, 0],
            'current bonuses accepted despite disabled switches' => [1,
                ['one_extra-42' => 'on', 'amount_extra-42' => 'on'], [], 26, 1, 1, 0],
            'presence rather than truthiness enables flags' => [1,
                ['one_extra-42' => '0', 'amount_extra-42' => '0'], [], 26, 1, 1, 0],
            'variable input before sixth stage ignored' => [5, ['vary_extra-42' => 7], [], 20, 0, 0, 0],
            'sixth-stage variable plus fixed bonuses' => [6,
                ['vary_extra-42' => 7, 'one_extra-42' => 'on', 'amount_extra-42' => 'on'],
                ['rank_stage_1' => 2], 50, 1, 1, 7],
            'other-stage variable five silently omitted: known defect' => [1, [],
                ['rank_stage_6' => 2, 'vary_extra_stage_6' => 5], 37, 0, 0, 0],
            'disqualification still permits bonuses' => [6,
                ['rank-42' => 99, 'one_extra-42' => 'on', 'amount_extra-42' => 'on', 'vary_extra-42' => 7],
                [], 13, 1, 1, 7],
            'unplaced still permits bonuses' => [1,
                ['rank-42' => 0, 'one_extra-42' => 'on'], [], 1, 1, 0, 0],
            'posted stage seven accepted: missing bounds check' => [7, [], [], 0, 0, 0, 0],
        ];
    }

    public function testOtherStageVariableOneThrowsBeforeWritingTeam(): void
    {
        $_POST = ['route' => 7, 'stage' => 1, 'rank-42' => 1];
        $races = $this->races([self::team(['vary_extra_stage_6' => 1])]);
        try {
            (new \ReflectionMethod(\Cor\Mgmt\Tests\Support\LegacyRankingReference::class, 'persist_ranking'))->invoke($races);
            self::fail('Known legacy string addition must throw on PHP 8.');
        } catch (TypeError $error) {
            self::assertStringContainsString('Unsupported operand types', $error->getMessage());
            self::assertSame([], $GLOBALS['wpdb']->writes);
        }
    }

    public function testErrorOnLaterTeamLeavesEarlierWriteInPlace(): void
    {
        $_POST = ['route' => 7, 'stage' => 1, 'rank-42' => 1, 'rank-43' => 2];
        $races = $this->races([self::team(), self::team(['vary_extra_stage_6' => 1], 43)]);
        try {
            (new \ReflectionMethod(\Cor\Mgmt\Tests\Support\LegacyRankingReference::class, 'persist_ranking'))->invoke($races);
            self::fail('The second team must reproduce the legacy TypeError.');
        } catch (TypeError $error) {
            self::assertStringContainsString('Unsupported operand types', $error->getMessage());
            self::assertCount(1, $GLOBALS['wpdb']->writes);
            self::assertSame(['id' => 42], $GLOBALS['wpdb']->writes[0]['where']);
            self::assertSame(20, $GLOBALS['wpdb']->writes[0]['data']['total_points']);
        }
    }
}

/** No database or generic query method: unexpected boundaries fail the test. */
final class RankingMemoryDatabase
{
    public string $prefix = 'synthetic_';
    public array $writes = [];

    public function update($table, array $data, array $where, array $formats, array $whereFormats): int
    {
        if ($table !== 'synthetic_h3_mgmt_teams' || !in_array($where['id'], [42, 43], true)
            || $formats !== array_fill(0, 5, '%d') || $whereFormats !== ['%d']) {
            throw new UnexpectedValueException('Unexpected write boundary');
        }
        $this->writes[] = ['table' => $table, 'data' => $data, 'where' => $where];
        return 1;
    }
}
