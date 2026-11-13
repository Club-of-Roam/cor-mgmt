<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\SitePages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventPageViewTest extends TestCase
{
    private array $saved;
    private int $member;

    protected function setUp(): void
    {
        global $wpdb;
        $this->saved = [$_GET, $_POST, $_SERVER['REQUEST_METHOD'] ?? null, get_current_user_id()];
        $_GET = $_POST = []; $_SERVER['REQUEST_METHOD'] = 'GET';
        $wpdb->query('START TRANSACTION'); wp_cache_flush();
        $id = wp_insert_user(['user_login' => 'event_page_reader', 'user_email' => 'event-page@example.invalid', 'user_pass' => 'synthetic-only', 'role' => 'subscriber']);
        self::assertIsInt($id); $this->member = $id;
        (new Database($wpdb))->setOption('cor_mgmt_ticker_transition', '{"state":"native"}');
        $GLOBALS['cor_test_mail'] = [];
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $error = $wpdb->last_error; $wpdb->query('ROLLBACK'); wp_cache_flush();
        [$_GET, $_POST, $_SERVER['REQUEST_METHOD'], $user] = $this->saved; wp_set_current_user($user);
        self::assertSame('', $error); self::assertSame([], $GLOBALS['cor_test_mail']);
    }

    private function event(int $phase, array $texts = [], bool $donations = false): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_races', ['id' => 880301, 'name' => 'Summer <em>event</em> & friends', 'active' => 1,
            'setting' => wp_json_encode(['status' => $phase, 'num_teammember_min' => 1, 'num_teammember' => 2, 'startingpoint' => 1,
                'dis_fee' => 1, 'dis_waiver' => 1, 'dis_shirt_size' => 1, 'dis_mobile_inf' => 1,
                'liveticker' => 0, 'liveticker_front' => $phase > 0 ? 1 : 0, 'donation' => (int) $donations,
                'betterplace_redirect_link' => 'https://www.betterplace.org/de/fundraising-events/123-synthetic']),
            'information_text' => wp_json_encode($texts)]);
    }

    public static function pages(): array
    {
        $cases = [];
        foreach (['team', 'teams', 'results', 'ticker', 'map', 'donations'] as $kind) {
            foreach (range(0, 4) as $phase) { $cases[$kind . '-phase-' . $phase] = [$kind, $phase]; }
        }
        return $cases;
    }

    #[DataProvider('pages')]
    public function testEveryPageHasTwoHeadingsAndUsefulContentForGuestsAndMembers(string $kind, int $phase): void
    {
        $this->event($phase);
        $writes = [];
        $capture = static function (string $sql) use (&$writes): string {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql)) { $writes[] = $sql; }
            return $sql;
        };
        add_filter('query', $capture);
        try {
            foreach ([0, $this->member] as $user) {
                wp_set_current_user($user);
                set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new \ErrorException($message, 0, $severity, $file, $line); });
                try { $html = do_shortcode(SitePages::content(880301, $kind)); } finally { restore_error_handler(); }
                self::assertSame(1, substr_count($html, '<h2>Summer &lt;em&gt;event&lt;/em&gt; &amp; friends</h2>'));
                self::assertSame(1, substr_count($html, '<h3>' . SitePages::label($kind) . '</h3>'));
                $message = match ($kind) {
                    'team' => $user ? ($phase === 1 ? 'Team name' : 'Team registration is closed.') : 'Log in to manage your team.',
                    'teams' => $phase === 0 ? 'Team registration has not opened yet.' : 'No teams meet the participation requirements yet.',
                    'results' => $phase < 3 ? 'The race has not started yet.' : 'No results are available yet.',
                    'ticker' => $phase === 0 ? 'Ticker display is disabled' : 'No messages sent yet.',
                    'map' => $phase === 0 ? 'Ticker display is disabled' : 'No locations have been shared yet.',
                    'donations' => 'Donations are currently disabled',
                };
                self::assertStringContainsString($message, $html);
                self::assertStringNotContainsString('<br><br>', $html);
            }
        } finally { remove_filter('query', $capture); }
        self::assertSame([], $writes);
    }

    public function testCustomNoticesAreRetainedAndEnabledDonationsExplainMissingTeams(): void
    {
        $this->event(0, ['25en' => '<strong>Custom registration notice</strong>', '26en' => 'Custom result notice', '24en' => 'Custom donation notice']);
        foreach (['teams' => 'Custom registration notice', 'results' => 'Custom result notice', 'donations' => 'Custom donation notice'] as $kind => $message) {
            self::assertStringContainsString($message, do_shortcode(SitePages::content(880301, $kind)));
        }
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'h3_mgmt_races', ['id' => 880301]);
        $this->event(1, [], true);
        self::assertStringContainsString('No teams are available.', do_shortcode(SitePages::content(880301, 'donations')));
    }

    public function testUnknownEventsAndInvalidAttributesHaveHeadingsAndAnExplanation(): void
    {
        foreach (['team', 'teams', 'results', 'ticker', 'map', 'donations'] as $kind) {
            $html = do_shortcode(SitePages::content(880302, $kind));
            self::assertStringContainsString('<h2>Event</h2><h3>' . SitePages::label($kind) . '</h3>', $html);
            self::assertStringContainsString('This event is unavailable.', $html);
        }
        self::assertStringContainsString('This event is unavailable.', do_shortcode('[h3-teams race="not-an-id"]'));
        self::assertSame('Unrelated content', \Cor\Mgmt\WordPress\EventPageView::after('Unrelated content', 'h3-stages', []));
    }
    public function testTeamPageDoesNotShowAnotherEventsTeamUnderItsHeading(): void
    {
        global $wpdb;
        $this->event(1);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teams', ['id' => 880303, 'race_id' => 880302, 'team_name' => 'Foreign event team', 'complete' => 1]);
        $_GET['id'] = '880303';
        $html = do_shortcode(SitePages::content(880301, 'teams'));
        self::assertStringContainsString('No teams meet the participation requirements yet.', $html);
        self::assertStringNotContainsString('Foreign event team', $html);
    }
}
