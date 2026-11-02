<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UnexpectedValueException;

require_once dirname(__DIR__) . '/Support/LegacyEnvironment.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-teams.php';
require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-races.php';

/** Actual legacy methods with in-memory boundaries, not WP integration tests. */
final class TeamRulesTest extends TestCase
{
    private array $saved = [];
    private array $post;

    protected function setUp(): void
    {
        foreach (['wpdb', 'h3_mgmt_races', 'h3_mgmt_mailer', 'h3_mgmt_utilities', 'cor_test_user_meta'] as $key) {
            $this->saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $this->post = $_POST;
        $GLOBALS['cor_test_user_meta'] = [101 => ['shirt_size' => 'M', 'public_mobile_inf' => 'yes'],
            102 => ['shirt_size' => 'L', 'public_mobile_inf' => 'no']];
        $GLOBALS['h3_mgmt_mailer'] = new class {
            public array $calls = [];
            public function auto_response(...$args): void { $this->calls[] = $args; }
        };
        $GLOBALS['h3_mgmt_utilities'] = new class {
            public function user_language(): string { return 'en'; }
        };
        $GLOBALS['wpdb'] = new TeamMemoryDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => [$exists, $value]) {
            if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
        }
        $_POST = $this->post;
    }

    private function teams(array $settings = [], int $members = 2): \H3_MGMT_Teams
    {
        $settings += ['num_teammember' => 3, 'dis_fee' => 0, 'dis_waiver' => 0,
            'dis_shirt_size' => 0, 'dis_mobile_inf' => 0, 'startingpoint' => 0, 'start_route_id' => 7];
        $races = $this->getMockBuilder(\H3_MGMT_Races::class)->disableOriginalConstructor()
            ->onlyMethods(['get_race_setting'])->getMock();
        $races->method('get_race_setting')->with(9)->willReturn($settings);
        $GLOBALS['h3_mgmt_races'] = $races;
        $teams = $this->getMockBuilder(\H3_MGMT_Teams::class)->disableOriginalConstructor()
            ->onlyMethods(['get_team_race', 'get_teammates', 'mate_name_string', 'get_team_name'])->getMock();
        $teams->method('get_team_race')->willReturn(9);
        $teams->method('get_teammates')->willReturn(array_slice([101, 102, 103, 104], 0, $members));
        $teams->method('mate_name_string')->willReturn('Ada &amp; Ben');
        $teams->method('get_team_name')->willReturn('Synthetic team');
        return $teams;
    }

    public function testPromotionWritesStatusAndRouteAndNotifiesOnlyOnce(): void
    {
        $teams = $this->teams();
        self::assertSame(1, $teams->is_complete(42));
        self::assertSame([['complete' => 1], ['route_id' => 7]], $GLOBALS['wpdb']->updates);
        self::assertSame([[[101, 102], 'publishable', ['names' => 'Ada &amp; Ben',
            'team_name' => 'Synthetic team'], 'id', 'en']], $GLOBALS['h3_mgmt_mailer']->calls);
        self::assertSame(1, $teams->is_complete('42'));
        self::assertCount(2, $GLOBALS['wpdb']->updates);
        self::assertCount(1, $GLOBALS['h3_mgmt_mailer']->calls);
    }

    #[DataProvider('requirements')]
    public function testEachRequirementCanBlockAndBeDisabled(string $field, string $setting): void
    {
        if ($field === 'paid' || $field === 'waiver') { $GLOBALS['wpdb']->members[1][$field] = 0; }
        else { $GLOBALS['cor_test_user_meta'][102][$field] = ''; }
        self::assertSame(0, $this->teams()->is_complete(42));
        self::assertSame([], $GLOBALS['wpdb']->updates);
        self::assertSame([], $GLOBALS['h3_mgmt_mailer']->calls);
        self::assertSame(1, $this->teams([$setting => 1])->is_complete(42));
    }

    public static function requirements(): array
    {
        return [['paid', 'dis_fee'], ['waiver', 'dis_waiver'],
            ['shirt_size', 'dis_shirt_size'], ['public_mobile_inf', 'dis_mobile_inf']];
    }

    public function testMinimumDefaultsToTwoAndCanBeConfigured(): void
    {
        $GLOBALS['wpdb']->members = [$GLOBALS['wpdb']->members[0]];
        self::assertSame(0, $this->teams([], 1)->is_complete(42));
        self::assertSame(1, $this->teams(['num_teammember_min' => 1], 1)->is_complete(42));
    }

    public function testDowngradeClearsRouteWithoutNotification(): void
    {
        $GLOBALS['wpdb']->status = 1;
        $GLOBALS['wpdb']->members[0]['paid'] = 0;
        self::assertSame(0, $this->teams()->is_complete(42));
        // Redundant intermediate assignment is legacy behavior, not a v2 requirement.
        self::assertSame([['complete' => 0], ['route_id' => 7], ['route_id' => 0]], $GLOBALS['wpdb']->updates);
        self::assertSame([], $GLOBALS['h3_mgmt_mailer']->calls);
    }

    public function testMultipleStartsDoNotAutomaticallyAssignRoute(): void
    {
        self::assertSame(1, $this->teams(['startingpoint' => 1])->is_complete(42));
        self::assertSame([['complete' => 1]], $GLOBALS['wpdb']->updates);
    }

    #[DataProvider('invitationLimits')]
    public function testInvitationLimit(int $members, int $maximum, $expected): void
    {
        $method = new ReflectionMethod(\H3_MGMT_Teams::class, 'allow_invitations');
        self::assertSame($expected, $method->invoke($this->teams(['num_teammember' => $maximum], $members), 42));
    }

    public static function invitationLimits(): array
    {
        return [[0, 3, 2], [1, 3, 2], [2, 3, 1], [3, 3, false], [4, 3, false], [0, 1, 0], [1, 1, false]];
    }

    #[DataProvider('routeSelections')]
    public function testFullRoutesAreHiddenExceptCurrentSelection($current, array $expected): void
    {
        $teams = $this->teams();
        $races = $this->getMockBuilder(\H3_MGMT_Races::class)->disableOriginalConstructor()
            ->onlyMethods(['get_route_ids', 'get_free_slots'])->getMock();
        $races->method('get_route_ids')->with(9)->willReturn([7 => 'Open', 8 => 'Full', 10 => 'Overbooked']);
        $races->method('get_free_slots')->willReturnMap([[7, 1], [8, 0], [10, -1]]);
        $GLOBALS['h3_mgmt_races'] = $races;
        self::assertSame($expected, array_column($teams->route_field(9, $current)[0]['options'], 'value'));
    }

    public static function routeSelections(): array
    {
        return [[null, [0, 7]], [8, [0, 7, 8]], ['10', [0, 7, 10]], ['unknown', [0, 7]]];
    }

    #[DataProvider('formInputs')]
    public function testLegacyValidation(array $overrides, bool $valid, int $errors): void
    {
        $_POST = $overrides + ['team_name' => 'Synthetic team', 'first_name' => 'Ada', 'last_name' => 'Example'];
        [$actual, $messages] = (new ReflectionMethod(\H3_MGMT_Teams::class, 'validate_submit'))->invoke($this->teams());
        self::assertSame($valid, $actual);
        self::assertCount($errors, $messages);
    }

    public static function formInputs(): array
    {
        return [[[], true, 0], [['team_name' => ''], false, 1], [['first_name' => ''], false, 1],
            [['last_name' => ''], false, 1], [['address' => 'bot'], false, 1],
            [['address' => 'bot', 'team_name' => ''], false, 2],
            [['team_name' => '   '], true, 0], [['team_name' => '0'], false, 1]];
    }
}

final class TeamMemoryDatabase
{
    public string $prefix = 'synthetic_';
    public int $status = 0;
    public array $members = [['paid' => 1, 'waiver' => 1], ['paid' => 1, 'waiver' => 1]];
    public array $updates = [];
    public function prepare(string $sql, $id): string { return sprintf($sql, (int) $id); }
    public function get_results(string $sql, $mode): array
    {
        return match ($sql) {
            'SELECT * FROM synthetic_h3_mgmt_teammates WHERE team_id = 42' => $this->members,
            'SELECT complete FROM synthetic_h3_mgmt_teams WHERE id = 42' => [['complete' => $this->status]],
            default => throw new UnexpectedValueException($sql),
        };
    }
    public function update($table, array $data, array $where, $format, $whereFormat): int
    {
        if ($table !== 'synthetic_h3_mgmt_teams' || (int) $where['id'] !== 42) {
            throw new UnexpectedValueException('Unexpected write target');
        }
        $this->updates[] = $data;
        $this->status = $data['complete'] ?? $this->status;
        return 1;
    }
}
