<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Integration;

use Error;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/** Real WordPress hooks, roles, users, metadata and wpdb, on synthetic fixtures only. */
final class LegacyWordPressTest extends TestCase
{
    private array $post;
    private array $get;

    protected function setUp(): void
    {
        global $wpdb;
        self::assertStringStartsWith('cor_mgmt_test_', DB_NAME);
        $this->post = $_POST;
        $this->get = $_GET;
        $_POST = $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $wpdb->query('START TRANSACTION');
        wp_cache_flush();
        wp_set_current_user(0);
        $GLOBALS['cor_test_mail'] = [];
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();
        wp_set_current_user(0);
        $_POST = $this->post;
        $_GET = $this->get;
        self::assertSame('', $wpdb->last_error);
    }

    private function user(string $name = 'synthetic_member', string $role = 'subscriber'): int
    {
        $id = wp_insert_user(['user_login' => $name, 'user_email' => $name . '@example.invalid',
            'user_pass' => 'synthetic-not-a-production-password', 'role' => $role, 'first_name' => 'Example']);
        self::assertIsInt($id);
        return $id;
    }

    private function race(int $id = 9, array $overrides = []): void
    {
        global $wpdb;
        $settings = $overrides + ['status' => 1, 'num_teammember_min' => 1, 'startingpoint' => 1,
            'dis_fee' => 1, 'dis_waiver' => 1, 'dis_shirt_size' => 1, 'dis_mobile_inf' => 1,
            'liveticker' => 1, 'donation' => 0, 'betterplace_redirect_link' => ''];
        $texts = [];
        for ($i = 1; $i <= 40; $i++) { $texts[$i . 'en'] = 'Synthetic notice ' . $i; }
        self::assertSame(1, $wpdb->insert($wpdb->prefix . 'h3_mgmt_races', [
            'id' => $id, 'name' => 'Synthetic event', 'setting' => wp_json_encode($settings),
            'information_text' => wp_json_encode($texts), 'active' => 1,
        ]));
    }

    private function team(int $user, int $id = 42, int $complete = 1): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teams', [
            'id' => $id, 'race_id' => 9, 'route_id' => 7, 'complete' => $complete, 'team_name' => 'Synthetic team',
        ]);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teammates', [
            'team_id' => $id, 'user_id' => $user, 'paid' => 1, 'waiver' => 1,
        ]);
    }

    public function testAllInventoriedShortcodesAreRegisteredWithRealWordPress(): void
    {
        global $shortcode_tags;
        $inventory = json_decode(file_get_contents(dirname(__DIR__, 2) . '/docs/inventory.json'), true);
        foreach ($inventory['shortcodes'] as $shortcode) {
            self::assertTrue(shortcode_exists($shortcode['tag']), $shortcode['tag']);
            self::assertSame($shortcode['handler'], $shortcode_tags[$shortcode['tag']][1]);
            self::assertTrue(is_callable($shortcode_tags[$shortcode['tag']]));
        }
    }

    public function testAdminReceivesCustomCapabilitiesButSubscriberDoesNot(): void
    {
        $caps = $GLOBALS['h3_mgmt']->extra_caps([]);
        self::assertCount(18, $caps);
        wp_set_current_user($this->user('synthetic_organizer', 'administrator'));
        foreach ($caps as $cap) { self::assertTrue(current_user_can($cap), $cap); }
        wp_set_current_user($this->user());
        foreach ($caps as $cap) { self::assertFalse(current_user_can($cap), $cap); }
    }

    public function testProfileHooksAreAttached(): void
    {
        global $wp_filter;
        foreach (['show_user_profile', 'edit_user_profile', 'personal_options_update', 'edit_user_profile_update'] as $hook) {
            $found = false;
            foreach ($wp_filter[$hook]->callbacks as $callbacks) {
                foreach ($callbacks as $callback) {
                    $function = $callback['function'];
                    if (is_array($function) && $function[0] instanceof \H3_MGMT_Profile) { $found = true; }
                }
            }
            self::assertTrue($found, $hook);
        }
    }

    public function testExchangeGuestCannotWrite(): void
    {
        global $wpdb;
        $exchange = new \H3_MGMT_XChange();
        self::assertNull($exchange->insertUpdateDeleteMessage('Synthetic', null));
        self::assertSame('0', $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_xchange"));
    }

    public function testExchangeOwnInsertUpdateDeleteWithRealSql(): void
    {
        global $wpdb;
        $id = $this->user();
        wp_set_current_user($id);
        $this->postNonce('cor-mgmt-xchange');
        $exchange = new \H3_MGMT_XChange();
        self::assertSame(1, $exchange->insertUpdateDeleteMessage('First synthetic text', null));
        $messageId = (int) $wpdb->insert_id;
        self::assertSame(1, $exchange->insertUpdateDeleteMessage('Second synthetic text', $messageId));
        self::assertSame('Second synthetic text', $wpdb->get_var("SELECT message FROM {$wpdb->prefix}h3_mgmt_xchange WHERE id=$messageId"));
        self::assertSame(1, $exchange->insertUpdateDeleteMessage('', $messageId));
        self::assertNull($wpdb->get_var("SELECT id FROM {$wpdb->prefix}h3_mgmt_xchange WHERE id=$messageId"));
    }

    public function testExchangeWriterCannotModifyForeignId(): void
    {
        global $wpdb;
        $owner = $this->user('synthetic_owner');
        $other = $this->user('synthetic_other');
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_xchange', ['user_id' => $owner, 'message' => 'Owner text']);
        $messageId = (int) $wpdb->insert_id;
        wp_set_current_user($other);
        $this->postNonce('cor-mgmt-xchange');
        self::assertSame(0, (new \H3_MGMT_XChange())->insertUpdateDeleteMessage('Foreign replacement', $messageId));
        self::assertSame((string) $owner, $wpdb->get_var("SELECT user_id FROM {$wpdb->prefix}h3_mgmt_xchange WHERE id=$messageId"));
        self::assertSame('Owner text', $wpdb->get_var("SELECT message FROM {$wpdb->prefix}h3_mgmt_xchange WHERE id=$messageId"));
    }

    public function testExchangeListExcludesOldYearAndParticipantsOfActiveRace(): void
    {
        global $wpdb;
        $this->race();
        $free = $this->user('synthetic_free');
        $joined = $this->user('synthetic_joined');
        $old = $this->user('synthetic_old');
        $this->team($joined);
        foreach ([[$free, date('Y') . '-06-01 12:00:00'], [$joined, date('Y') . '-06-01 12:00:00'],
            [$old, ((int) date('Y') - 1) . '-06-01 12:00:00']] as [$id, $time]) {
            $wpdb->insert($wpdb->prefix . 'h3_mgmt_xchange', ['user_id' => $id, 'message' => 'Synthetic', 'time' => $time]);
        }
        $rows = (new ReflectionMethod(\H3_MGMT_XChange::class, 'get_messages'))->invoke(new \H3_MGMT_XChange());
        self::assertSame([(string) $free], array_column($rows, 'user_id'));
    }

    public function testMailDisabledTemplateDoesNotSend(): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_auto_responses', [
            'action' => 'team-creation', 'language' => 'en', 'switch' => 0, 'subject' => '', 'message' => '',
        ]);
        self::assertFalse($GLOBALS['h3_mgmt_mailer']->auto_response('person@example.invalid', 'team-creation', [], 'email'));
        self::assertSame([], $GLOBALS['cor_test_mail']);
    }

    public function testMailLanguageTemplatePlaceholdersAndRecipients(): void
    {
        global $wpdb;
        $id = $this->user();
        foreach (['en' => 'English', 'de' => 'Deutsch'] as $language => $label) {
            $wpdb->insert($wpdb->prefix . 'h3_mgmt_auto_responses', [
                'action' => 'team-creation', 'language' => $language, 'switch' => 1,
                'subject' => $label . ' %team_name%', 'message' => '%name% %code% %link%',
            ]);
        }
        $GLOBALS['h3_mgmt_mailer']->auto_response($id, 'team-creation', [
            'team_name' => 'Synthetic', 'code' => '1234', 'link' => 'https://example.invalid/invitation',
        ], 'id', 'de');
        self::assertCount(1, $GLOBALS['cor_test_mail']);
        $mail = $GLOBALS['cor_test_mail'][0];
        self::assertSame('synthetic_member@example.invalid', $mail['to']);
        self::assertSame('Deutsch Synthetic', $mail['subject']);
        self::assertStringContainsString('Example 1234 https://example.invalid/invitation', $mail['message']);
    }

    public function testMailerSendsSeparatelyAndDoesNotDeduplicateAddresses(): void
    {
        $GLOBALS['h3_mgmt_mailer']->send(['a@example.invalid', 'a@example.invalid', 'b@example.invalid'], 'Synthetic', 'Body');
        self::assertSame(['a@example.invalid', 'a@example.invalid', 'b@example.invalid'], array_column($GLOBALS['cor_test_mail'], 'to'));
        self::assertStringContainsString('Content-Type: text/plain', $GLOBALS['cor_test_mail'][0]['headers']);
    }

    public function testDonationTotalsSeparatePaidHiddenAndUnpaidRows(): void
    {
        global $wpdb;
        foreach ([[1, 42, 10, 1, 1], [2, 42, 20, 1, 0], [3, 42, 50, 0, 1], [4, 43, 80, 1, 1]] as $row) {
            $wpdb->insert($wpdb->prefix . 'h3_mgmt_sponsors', array_combine(['id', 'team_id', 'donation', 'paid', 'var_show'], $row));
        }
        self::assertSame(30, $GLOBALS['h3_mgmt_sponsors']->get_donation_amount(['id' => 42]));
        self::assertSame(10, $GLOBALS['h3_mgmt_sponsors']->get_donation_amount(['id' => 42, 'show' => 1]));
        self::assertSame([], $GLOBALS['h3_mgmt_sponsors']->get_sponsor(999));
    }

    public function testRouteCountIncludesIncompleteTeamsKnownDifferenceToTarget(): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_routes', ['id' => 7, 'race_id' => 9, 'name' => 'Synthetic', 'max_teams' => 2]);
        $this->team($this->user('synthetic_a'), 42, 1);
        $this->team($this->user('synthetic_b'), 43, 0);
        self::assertSame(0, $GLOBALS['h3_mgmt_races']->get_free_slots(7));
    }

    public function testProfileCannotWriteOtherSubscriberMetadata(): void
    {
        $owner = $this->user('synthetic_owner');
        wp_set_current_user($this->user('synthetic_other'));
        $_POST['city'] = 'Foreign city';
        self::assertFalse((new \H3_MGMT_Profile())->save_extra_profile_fields($owner));
        self::assertSame('', get_user_meta($owner, 'city', true));
    }

    public function testProfileAuthorizedSaveSkipsSectionWithoutId(): void
    {
        $id = $this->user();
        wp_set_current_user($id);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_wpnonce' => wp_create_nonce('update-user_' . $id), 'city' => 'Synthetic city'];
        self::assertTrue((new \H3_MGMT_Profile())->save_extra_profile_fields($id));
        self::assertSame('Synthetic city', get_user_meta($id, 'city', true));
    }

    public function testTickerEmptyGatewayRequestReturns403WithoutWriting(): void
    {
        global $wpdb;
        ob_start();
        try { $GLOBALS['h3_mgmt_ticker']->ticker_incoming_interface(); $output = ob_get_contents(); }
        finally { ob_end_clean(); }
        self::assertSame('403', $output);
        self::assertSame('0', $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sms_ticker"));
    }

    public function testDisabledTickerAndDonationShortcodesReturnNotices(): void
    {
        $this->race(9, ['liveticker' => 0]);
        self::assertStringContainsString('Synthetic notice 23', do_shortcode('[h3-ticker-message race="9"]'));
        self::assertStringContainsString('Synthetic notice 24', do_shortcode('[h3-sponsoring-form race="9"]'));
        self::assertSame([], $GLOBALS['cor_test_mail']);
    }

    public function testHttpIsBlockedAndLanguageFallsBackWithoutWpml(): void
    {
        $result = wp_remote_get('https://example.invalid/provider');
        self::assertWPErrorCode('cor_test_http_blocked', $result);
        self::assertSame('en', $GLOBALS['h3_mgmt_utilities']->user_language());
    }

    public function testAdminMenusDeclareDistinctViewAndMailCapabilities(): void
    {
        global $menu, $submenu;
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        foreach (['', '-emails', '-races', '-sponsors', '-teams', '-statistics'] as $suffix) {
            require_once dirname(__DIR__, 2) . '/admin/class-h3-mgmt-admin' . $suffix . '.php';
        }
        $savedMenu = $menu ?? [];
        $savedSubmenu = $submenu ?? [];
        wp_set_current_user($this->user('synthetic_menu_admin', 'administrator'));
        $menu = $submenu = [];
        try {
            (new \H3_MGMT_Admin())->display_admin_menu();
            $bySlug = [];
            foreach ($menu as $row) { $bySlug[$row[2]] = $row[1]; }
            self::assertSame('h3_mgmt_view_races', $bySlug['h3-mgmt-races']);
            self::assertSame('h3_mgmt_view_teams', $bySlug['h3-mgmt-teams']);
            self::assertSame('h3_mgmt_send_emails', $bySlug['h3-mgmt-emails']);
            self::assertSame('h3-mgmt-view-statistics', $bySlug['h3-mgmt-statistics']);
            self::assertSame('h3_mgmt_edit_autoresponses', $submenu['h3-mgmt-emails'][1][1]);
        } finally {
            $menu = $savedMenu;
            $submenu = $savedSubmenu;
        }
    }

    #[DataProvider('fundraisingUrls')]
    public function testHistoricalFundraisingIdParser(string $url, ?string $expected): void
    {
        $method = new ReflectionMethod(\H3_MGMT_Sponsors::class, 'extractFundraisingId');
        self::assertSame($expected, $method->invoke($GLOBALS['h3_mgmt_sponsors'], $url));
    }

    public static function fundraisingUrls(): array
    {
        return [['https://www.betterplace.org/de/fundraising-events/12345-synthetic', '12345'],
            ['https://www.betterplace.org/en/fundraising-events/12-synthetic?x=1', '12'],
            ['https://example.invalid/fundraising-events/12-synthetic', null],
            ['https://www.betterplace.org/de/fundraising-events/12', null]];
    }

    public function testDonationValidationRejectsInvalidTeamTrapAndPlaceholder(): void
    {
        $method = new ReflectionMethod(\H3_MGMT_Sponsors::class, 'validate_submit');
        $_POST = ['team_id' => 'not-a-number', 'email' => 'invalid'];
        self::assertFalse($method->invoke($GLOBALS['h3_mgmt_sponsors'])[0]);
        $_POST = ['team_id' => 'please_select', 'address' => 'bot'];
        [$valid, $errors] = $method->invoke($GLOBALS['h3_mgmt_sponsors']);
        self::assertFalse($valid);
        self::assertCount(2, $errors);
    }

    public function testAdminRaceMailResolvesOnlyParticipantsOfChosenRace(): void
    {
        require_once dirname(__DIR__, 2) . '/admin/class-h3-mgmt-admin-emails.php';
        $id = $this->user();
        $this->team($id);
        wp_set_current_user($this->user('synthetic_mail_admin', 'administrator'));
        $_POST = ['receipient' => 'race-9', 'subject' => 'Synthetic', 'message' => 'Body', 'sender' => 'nr'];
        $this->postNonce('cor-mgmt-admin-emails');
        $method = new ReflectionMethod(\H3_MGMT_Admin_Emails::class, 'mail_send');
        self::assertTrue($method->invoke(new \H3_MGMT_Admin_Emails()));
        self::assertSame(['synthetic_member@example.invalid'], array_column($GLOBALS['cor_test_mail'], 'to'));
    }

    public function testAdminSelfMailUsesCurrentAddress(): void
    {
        require_once dirname(__DIR__, 2) . '/admin/class-h3-mgmt-admin-emails.php';
        wp_set_current_user($this->user('synthetic_self_admin', 'administrator'));
        $_POST = ['receipient' => 'self', 'subject' => 'Synthetic', 'message' => 'Body', 'sender' => 'nr'];
        $this->postNonce('cor-mgmt-admin-emails');
        (new ReflectionMethod(\H3_MGMT_Admin_Emails::class, 'mail_send'))->invoke(new \H3_MGMT_Admin_Emails());
        self::assertCount(1, $GLOBALS['cor_test_mail']);
        self::assertSame('synthetic_self_admin@example.invalid', $GLOBALS['cor_test_mail'][0]['to']);
    }

    #[DataProvider('tickerPosts')]
    public function testTickerParticipantWritesTextCommentLocationAndVideo(string $action, array $post, int $type, string $message, ?string $url): void
    {
        global $wpdb;
        $this->race();
        $id = $this->user();
        $this->team($id);
        wp_set_current_user($id);
        $_GET = ['todo' => $action];
        $_POST = $post;
        $this->postNonce('cor-mgmt-ticker-9');
        if ($action === 'send_message_comment') { $wpdb->insert($wpdb->prefix . 'sms_ticker', ['id'=>123,'race_id'=>9,'type'=>1,'send_from'=>'U_'.$id,'msg'=>'Parent']); }
        $html = do_shortcode('[h3-ticker-message race="9"]');
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sms_ticker ORDER BY id DESC LIMIT 1", ARRAY_A);
        self::assertIsArray($row);
        self::assertSame('U_' . $id, $row['send_from']);
        self::assertSame((string) $type, $row['type']);
        self::assertSame($message, $row['msg']);
        self::assertSame('9', $row['race_id']);
        if ($url !== null) { self::assertSame($url, $row['img_url']); }
        self::assertStringContainsString('has been sent', $html);
    }

    public static function tickerPosts(): array
    {
        return [
            ['send_message', ['message' => '<b>Synthetic</b>'], 1, '&lt;b&gt;Synthetic&lt;/b&gt;', null],
            ['send_message_comment', ['message' => 'Comment', 'ticker_id' => 123], 100, 'Comment', null],
            ['coordinates', ['coordinates' => '52.0,13.0', 'coordinates_informations' => 'Location'], 4, 'Location', '52.0,13.0'],
            ['send_video', ['video' => 'https://www.youtube.com/watch?v=synthetic', 'video_message' => 'Video'], 5, 'Video', 'synthetic'],
        ];
    }

    public function testTickerListScopesSenderRaceAndMessageTypes(): void
    {
        global $wpdb;
        foreach ([['U_12', 9, 1], ['U_12', 9, 100], ['U_13', 9, 1], ['U_12', 10, 1], ['U_12', 9, 4]] as [$sender, $race, $type]) {
            $wpdb->insert($wpdb->prefix . 'sms_ticker', ['send_from' => $sender, 'race_id' => $race, 'type' => $type, 'msg' => 'Synthetic']);
        }
        $rows = $GLOBALS['h3_mgmt_ticker']->get_messages_by_ids(['U_12'], 9);
        self::assertSame(['4', '1'], array_column($rows, 'type'));
        self::assertSame(['4'], array_column($GLOBALS['h3_mgmt_ticker']->get_messages_by_ids(['U_12'], 9, 1), 'type'));
    }

    public function testInvitationLookupPrefersCanonicalPostAndDoesNotConsumeCode(): void
    {
        global $wpdb;
        foreach ([[42, 111], [43, 222]] as [$team, $code]) {
            $wpdb->insert($wpdb->prefix . 'h3_mgmt_invitations', ['team_id' => $team, 'email' => 'invitee@example.invalid', 'code' => $code]);
        }
        $_GET['invitation'] = '111';
        $_POST['invitation'] = '222';
        $method = new ReflectionMethod(\H3_MGMT_Teams::class, 'handle_invitation');
        self::assertSame('43', $method->invoke($GLOBALS['h3_mgmt_teams']));
        $_GET = [];
        self::assertSame('43', $method->invoke($GLOBALS['h3_mgmt_teams']));
        $_POST['invitation'] = '999';
        self::assertFalse($method->invoke($GLOBALS['h3_mgmt_teams']));
        self::assertSame('2', $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_invitations"));
    }

    public function testTeamPromotionPersistsAndNotifiesOnceWithRealSql(): void
    {
        global $wpdb;
        $this->race(9, ['startingpoint' => 0, 'start_route_id' => 7]);
        $id = $this->user();
        $this->team($id, 42, 0);
        wp_set_current_user($id);
        $this->postNonce('cor-mgmt-team-9');
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_auto_responses', [
            'action' => 'publishable', 'language' => 'en', 'switch' => 1,
            'subject' => 'Synthetic complete', 'message' => '%team_name%',
        ]);
        self::assertSame(1, $GLOBALS['h3_mgmt_teams']->is_complete(42));
        self::assertSame('1', $wpdb->get_var("SELECT complete FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=42"));
        self::assertSame('7', $wpdb->get_var("SELECT route_id FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=42"));
        self::assertSame(1, $GLOBALS['h3_mgmt_teams']->is_complete(42));
        self::assertCount(1, $GLOBALS['cor_test_mail']);
    }

    public function testTeamReadingDoesNotPersistDowngradeInStartedRace(): void
    {
        global $wpdb;
        $this->race(9, ['status' => 3, 'dis_fee' => 0]);
        $this->team($this->user());
        $wpdb->update($wpdb->prefix . 'h3_mgmt_teammates', ['paid' => 0], ['team_id' => 42]);
        self::assertSame(1, $GLOBALS['h3_mgmt_teams']->is_complete(42));
        self::assertSame('1', $wpdb->get_var("SELECT complete FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=42"));
        self::assertSame('7', $wpdb->get_var("SELECT route_id FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=42"));
        self::assertSame([], $GLOBALS['cor_test_mail']);
    }

    private function postNonce(string $action): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['cor_mgmt_nonce'] = wp_create_nonce($action);
    }

    private static function assertWPErrorCode(string $code, $value): void
    {
        self::assertInstanceOf(\WP_Error::class, $value);
        self::assertSame($code, $value->get_error_code());
    }
}
