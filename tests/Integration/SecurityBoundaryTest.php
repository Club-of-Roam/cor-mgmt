<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Integration;

use H3_MGMT_Security as Security;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Security regressions run against real WordPress nonces, roles and SQL. */
final class SecurityBoundaryTest extends TestCase
{
    private array $saved;
    private int $admin;
    private int $member;

    protected function setUp(): void
    {
        global $wpdb;
        $this->saved = [$_POST, $_GET, $_FILES, $_COOKIE, $_SERVER];
        $_POST = $_GET = $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $wpdb->query('START TRANSACTION');
        wp_cache_flush();
        $this->admin = wp_insert_user(['user_login'=>'security_admin', 'user_email'=>'security_admin@example.invalid', 'user_pass'=>'synthetic-password', 'role'=>'administrator']);
        $this->member = wp_insert_user(['user_login'=>'security_member', 'user_email'=>'security_member@example.invalid', 'user_pass'=>'synthetic-password', 'role'=>'subscriber']);
        self::assertIsInt($this->admin);
        self::assertIsInt($this->member);
        wp_set_current_user($this->admin);
        foreach ([9=>1, 10=>4] as $id=>$status) {
            $wpdb->insert($wpdb->prefix.'h3_mgmt_races', ['id'=>$id,'name'=>'Synthetic','active'=>$id===9?1:0,
                'setting'=>wp_json_encode(['status'=>$status,'num_teammember_min'=>1,'num_teammember'=>2,'dis_fee'=>1,'dis_waiver'=>1,'dis_shirt_size'=>1,'dis_mobile_inf'=>1,'startingpoint'=>1,'liveticker'=>1]),'information_text'=>wp_json_encode(['1en'=>'Synthetic notice'])]);
        }
        foreach ([[7,9,$this->member],[8,9,0],[11,10,$this->member]] as [$id,$race,$user]) {
            $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>$id,'race_id'=>$race,'user_id'=>$user,'name'=>'Synthetic route','max_teams'=>2]);
        }
        foreach ([[42,9,7],[43,9,8],[44,10,11]] as [$id,$race,$route]) {
            $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>$id,'race_id'=>$race,'route_id'=>$route,'team_name'=>'Synthetic','complete'=>1,'team_phone'=>'']);
        }
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teammates',['id'=>901,'team_id'=>42,'user_id'=>$this->member,'paid'=>1,'waiver'=>1]);
        $GLOBALS['cor_test_mail'] = [];
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $error = $wpdb->last_error;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();
        wp_set_current_user(0);
        [$_POST,$_GET,$_FILES,$_COOKIE,$_SERVER] = $this->saved;
        self::assertSame('', $error);
    }

    private function post(string $action, array $values=[]): void
    {
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=$values+['cor_mgmt_nonce'=>wp_create_nonce($action)];
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdentifiersCannotBecomeSqlFragments($id): void
    {
        self::assertSame(0, Security::id($id));
        self::assertSame([], $GLOBALS['h3_mgmt_sponsors']->get_sponsor($id));
    }

    public static function invalidIds(): array
    {
        return [["1 OR 1=1"],["1; DROP TABLE users"],["1'"],['-1'],['1.5'],['1e2'],[[]],[null]];
    }

    #[DataProvider('adminResources')]
    public function testAdminWritesRejectGetAndMissingOrForeignNonce(string $resource, string $action, int $id): void
    {
        $_GET=['todo'=>$action,'id'=>$id];
        self::assertFalse(Security::admin_request($resource));
        $_SERVER['REQUEST_METHOD']='POST';
        self::assertFalse(Security::admin_request($resource));
        $this->post('cor-mgmt-admin-wrong');
        self::assertFalse(Security::admin_request($resource));
        $this->post(Security::admin_nonce($resource));
        self::assertTrue(Security::admin_request($resource));
        wp_set_current_user($this->member);
        self::assertFalse(Security::admin_request($resource));
    }

    public static function adminResources(): array
    {
        return [['races','liveticker-set',9],['routes','delete',7],['teams','delete',42],['participants','paid',901]];
    }

    public function testMixedBulkRequestIsRejectedBeforeAnyRoutingOrWrite(): void
    {
        $this->post(Security::admin_nonce('teams'),['todo'=>'delete','bulk'=>[42,"43 OR 1=1"]]);
        self::assertFalse(Security::admin_request('teams'));
        self::assertArrayNotHasKey('todo', $_GET);
        global $wpdb;
        self::assertSame('3',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_teams"));
    }

    public function testGlobalEditAndDeleteCapabilitiesAreIndependent(): void
    {
        $user = new \WP_User($this->member);
        $user->add_cap('h3_mgmt_view_teams');
        $user->add_cap('h3_mgmt_edit_teams');
        wp_set_current_user($this->member);
        $this->post(Security::admin_nonce('teams'),['todo'=>'delete','id'=>42]);
        self::assertFalse(Security::admin_request('teams'));
        $_POST['todo']='save';
        $_POST['race_id']=9;
        $_POST['route_id']=7;
        self::assertTrue(Security::admin_request('teams'));
    }

    public function testOwnRightsAreScopedToAssignedRouteRatherThanWholeRace(): void
    {
        $user=new \WP_User($this->member);
        $user->add_cap('h3_mgmt_edit_own_teams');
        wp_set_current_user($this->member);
        self::assertTrue(Security::can_object('teams','edit',42));
        self::assertFalse(Security::can_object('teams','edit',43));
        self::assertTrue(Security::can_object('participants','edit',901));
        self::assertFalse(Security::can_object('teams','edit',999));
    }

    public function testArchivedRecordsCannotBeChangedByAdministrator(): void
    {
        $this->post(Security::admin_nonce('teams'),['todo'=>'save','id'=>44]);
        self::assertFalse(Security::admin_request('teams'));
        $this->post(Security::admin_nonce('routes'),['todo'=>'delete','id'=>11]);
        self::assertFalse(Security::admin_request('routes'));
        $this->post('cor-mgmt-maintenance');
        self::assertWPError(\H3_MGMT_Maintenance::repair(10,true));
    }

    public function testCannotMoveTeamToRouteFromDifferentRaceOrArchivedRace(): void
    {
        $this->post(Security::admin_nonce('teams'),['todo'=>'save','id'=>42,'race_id'=>9,'route_id'=>11]);
        self::assertFalse(Security::admin_request('teams'));
        $_POST['race_id']=10;
        self::assertFalse(Security::admin_request('teams'));
    }

    public function testNewRecordWithEmptyLegacyIdRequiresNonceAndEditCapability(): void
    {
        $_GET=['todo'=>'save','id'=>''];
        self::assertFalse(Security::admin_request('races'));
        $this->post(Security::admin_nonce('races'));
        self::assertTrue(Security::admin_request('races'));
    }

    public function testActionLinksBecomePostButtonsWithNonce(): void
    {
        $html=Security::admin_html('<a href="admin.php?page=h3-mgmt-teams&amp;todo=delete&amp;id=42">Delete</a>','h3-mgmt-teams');
        self::assertStringContainsString('method="post"',$html);
        self::assertStringContainsString('name="cor_mgmt_nonce"',$html);
        self::assertStringContainsString('<button',$html);
        self::assertStringNotContainsString('<a ',$html);
        self::assertStringContainsString('name="id" value="42"',$html);
    }

    public function testExchangeForeignDeleteAndForgedNonceCannotModifyData(): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'h3_mgmt_xchange',['id'=>707,'user_id'=>$this->admin,'message'=>'Original']);
        wp_set_current_user($this->member);
        $this->post('cor-mgmt-xchange');
        $exchange=new \H3_MGMT_XChange();
        self::assertSame(0,$exchange->insertUpdateDeleteMessage('',707));
        $_POST['cor_mgmt_nonce']='forged';
        self::assertFalse($exchange->insertUpdateDeleteMessage('Changed',707));
        self::assertSame('Original',$wpdb->get_var("SELECT message FROM {$wpdb->prefix}h3_mgmt_xchange WHERE id=707"));
    }

    #[DataProvider('badInvitations')]
    public function testInvitationLookupCannotInjectSqlOrAcceptStructuredInput($code): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'h3_mgmt_invitations',['team_id'=>42,'code'=>123456,'email'=>'synthetic@example.invalid']);
        $_GET['invitation']=$code;
        self::assertFalse((new ReflectionMethod(\H3_MGMT_Teams::class,'handle_invitation'))->invoke($GLOBALS['h3_mgmt_teams']));
        self::assertSame('1',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_invitations"));
    }

    public static function badInvitations(): array { return [["123456 OR 1=1"],["123456'"],[['123456']],['-1'],['123456e0']]; }

    public function testRankingNonceDoesNotGrantRouteOwnership(): void
    {
        wp_set_current_user($this->member);
        $this->post('cor-mgmt-ranking-8',['route'=>8,'stage'=>1,'rank-43'=>1]);
        self::assertFalse(Security::ranking_request());
        $this->post('cor-mgmt-ranking-7',['route'=>7,'stage'=>1,'rank-42'=>1]);
        self::assertTrue(Security::ranking_request());
        $_POST['rank-43']=1;
        self::assertFalse(Security::ranking_request());
    }

    #[DataProvider('invalidRankings')]
    public function testRankingRejectsInvalidColumnsRanksAndIncompleteSubmission(array $changes): void
    {
        $this->post('cor-mgmt-ranking-7',$changes+['route'=>7,'stage'=>1,'rank-42'=>1]);
        self::assertFalse(Security::ranking_request());
    }

    public static function invalidRankings(): array
    {
        return [[['stage'=>7]],[['stage'=>0]],[['rank-42'=>13]],[['rank-42'=>[1]]],
            [['vary_extra-42'=>256]],[['rank-42'=>null]],[['route'=>"7 OR 1=1"]],
            [['one_extra-42'=>'on']],[['amount_extra-42'=>'on']]];
    }

    #[DataProvider('controllers')]
    public function testRealAdminControllersRejectSubscriberBeforeAnyWrite(string $file, string $class, string $method): void
    {
        require_once dirname(__DIR__,2).'/admin/class-h3-mgmt-admin'.$file.'.php';
        wp_set_current_user($this->member);
        $_GET=['todo'=>'delete','id'=>42];
        $this->post('cor-mgmt-admin-teams');
        $writes=[];
        $capture=static function ($sql) use (&$writes) {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|DROP)\b/i',$sql)) { $writes[]=$sql; }
            return $sql;
        };
        add_filter('query',$capture);
        ob_start();
        try {
            $controller=(new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $controller->$method();
            self::assertStringContainsString('not permitted',ob_get_contents());
            self::assertSame([],$writes);
        } finally { ob_end_clean(); remove_filter('query',$capture); }
    }

    public static function controllers(): array
    {
        return [['-races','H3_MGMT_Admin_Races','races_control'],['-races','H3_MGMT_Admin_Races','routes_control'],
            ['-races','H3_MGMT_Admin_Races','stages_control'],['-teams','H3_MGMT_Admin_Teams','teams_control'],
            ['-teams','H3_MGMT_Admin_Teams','participants_control'],['-sponsors','H3_MGMT_Admin_Sponsors','sponsors_control'],
            ['-emails','H3_MGMT_Admin_Emails','mail_form'],['-emails','H3_MGMT_Admin_Emails','autoresponses_edit'],
            ['-statistics','H3_MGMT_Admin_Statistics','statistics']];
    }

    public function testMissingRankingTeamIsRejectedBeforeSaving(): void
    {
        $this->post('cor-mgmt-ranking-7',['route'=>7,'stage'=>1]);
        self::assertFalse(Security::ranking_request());
        self::assertStringContainsString('not permitted',$GLOBALS['h3_mgmt_races']->save_ranking());
    }

    public function testReadAndUnrelatedPostCannotRecalculateTeam(): void
    {
        self::assertFalse(Security::can_recalculate(42));
        $this->post('cor-mgmt-xchange');
        self::assertFalse(Security::can_recalculate(42));
        wp_set_current_user($this->member);
        $this->post('cor-mgmt-team-9');
        self::assertTrue(Security::can_recalculate(42));
        self::assertFalse(Security::can_recalculate(43));
    }

    public function testProfileNonceAndInvalidDateDoNotPartiallyWrite(): void
    {
        wp_set_current_user($this->member);
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['city'=>'Changed'];
        $profile=new \H3_MGMT_Profile();
        self::assertFalse($profile->save_extra_profile_fields($this->member));
        $_POST+=['_wpnonce'=>wp_create_nonce('update-user_'.$this->member),'birthday-year'=>'2000','birthday-month'=>'2','birthday-day'=>'31'];
        self::assertFalse($profile->save_extra_profile_fields($this->member));
        self::assertSame('',get_user_meta($this->member,'city',true));
    }

    public function testAccountDeletionNeedsSeparateNonce(): void
    {
        wp_set_current_user($this->member);
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['_wpnonce'=>wp_create_nonce('update-user_'.$this->member),'deleteme'=>'forever'];
        self::assertFalse((new \H3_MGMT_Profile())->save_extra_profile_fields($this->member));
        self::assertInstanceOf(\WP_User::class,get_userdata($this->member));
    }

    public function testUploadsRejectFakeTemporaryFilesScriptsAndTraversal(): void
    {
        $_FILES['photo']=['name'=>'../../exploit.php','tmp_name'=>__FILE__,'error'=>UPLOAD_ERR_OK,'size'=>filesize(__FILE__)];
        self::assertWPError(Security::upload('photo'));
        self::assertWPError(\H3_MGMT_Media::gateway_image('<?php echo "bad";',9));
        self::assertWPError(\H3_MGMT_Media::gateway_image('<svg onload="alert(1)"></svg>',9));
        foreach (['../../wp-config.php','https://example.invalid/private.jpg','file:///etc/passwd'] as $path) {
            self::assertSame(0,\H3_MGMT_Media::owned_image($path,9));
        }
    }

    public function testOutputRemovesScriptsEventsAndUntrustedEmbedsButKeepsForms(): void
    {
        $html=Security::html('<script>alert(1)</script><img src="x" onerror="bad()"><a href="javascript:bad()">Link</a><iframe src="https://evil.invalid/"></iframe><form method="post"><input name="safe" value="&quot;"></form>');
        self::assertStringNotContainsString('<script',$html);
        self::assertStringNotContainsString('onerror',$html);
        self::assertStringNotContainsString('javascript:',$html);
        self::assertStringNotContainsString('evil.invalid',$html);
        self::assertStringContainsString('<form method="post">',$html);
        self::assertStringContainsString('name="safe"',$html);
    }

    public function testGuestDonationNonceIsBoundToBrowserCookie(): void
    {
        wp_set_current_user(0);
        $_COOKIE['client_token']='synthetic-browser-one';
        $this->post(Security::donation_nonce(9));
        self::assertTrue(Security::donation_request(9));
        $_COOKIE['client_token']='synthetic-browser-two';
        self::assertFalse(Security::donation_request(9));
    }

    public function testLegacyBrowserPaymentConfirmationCannotMarkDonationPaid(): void
    {
        $this->post(Security::donation_nonce(9));
        $_GET=['amount'=>'100000','donation_token'=>'forged','donation_client_reference'=>(string) $_COOKIE['client_token']];
        $method=new ReflectionMethod(\H3_MGMT_Sponsors::class,'save_donation');
        self::assertStringContainsString('not permitted',$method->invoke($GLOBALS['h3_mgmt_sponsors'],9,2));
    }

    public function testPublicMaintenanceShortcodeDoesNotWrite(): void
    {
        $this->post('cor-mgmt-maintenance');
        self::assertStringContainsString('not permitted',$GLOBALS['h3_mgmt_ticker']->Ticker_Datenbank_Change());
        wp_set_current_user($this->member);
        self::assertWPError(\H3_MGMT_Maintenance::repair(9,true));
    }

    public function testProtectedMaintenanceDryRunAndApplyAreScopedAndRepeatable(): void
    {
        global $wpdb;
        update_user_meta($this->member,'mobile','+4912345678');
        foreach ([9,10] as $race) { $wpdb->insert($wpdb->prefix.'sms_ticker',['send_from'=>'004912345678','race_id'=>$race,'msg'=>'Synthetic','type'=>1]); }
        $this->post('cor-mgmt-maintenance');
        self::assertSame(['proposed'=>1,'updated'=>0,'ambiguous'=>0],\H3_MGMT_Maintenance::repair(9));
        self::assertSame(['proposed'=>1,'updated'=>1,'ambiguous'=>0],\H3_MGMT_Maintenance::repair(9,true));
        self::assertSame(['proposed'=>0,'updated'=>0,'ambiguous'=>0],\H3_MGMT_Maintenance::repair(9,true));
        self::assertSame('004912345678',$wpdb->get_var("SELECT send_from FROM {$wpdb->prefix}sms_ticker WHERE race_id=10"));
    }

    public function testGatewayRejectsUnauthenticatedForgedAndExpiredRequests(): void
    {
        if (!defined('COR_MGMT_TICKER_SECRET')) { define('COR_MGMT_TICKER_SECRET',str_repeat('synthetic-test-secret-',3)); }
        $this->post('unused',['message'=>'{}']);
        $_SERVER['HTTP_X_COR_TIMESTAMP']=(string)time();
        $_SERVER['HTTP_X_COR_REQUEST_ID']='synthetic-request';
        $_SERVER['HTTP_X_COR_SIGNATURE']='forged';
        self::assertSame('403',$this->gateway());
        $_SERVER['HTTP_X_COR_TIMESTAMP']=(string)(time()-600);
        $_SERVER['HTTP_X_COR_SIGNATURE']=hash_hmac('sha256',$_SERVER['HTTP_X_COR_TIMESTAMP']."\nsynthetic-request\n{}",COR_MGMT_TICKER_SECRET);
        self::assertSame('403',$this->gateway());
    }

    public function testSignedGatewayMessageWorksOnceAndReplayCannotDuplicate(): void
    {
        global $wpdb;
        if (!defined('COR_MGMT_TICKER_SECRET')) { define('COR_MGMT_TICKER_SECRET',str_repeat('synthetic-test-secret-',3)); }
        update_user_meta($this->member,'mobile','+4912345678');
        $raw=wp_json_encode(['number'=>'+4912345678','message'=>'<script>synthetic</script>','time'=>time(),'with_image'=>false]);
        $this->post('unused',['message'=>wp_slash($raw)]);
        $_SERVER['HTTP_X_COR_TIMESTAMP']=(string)time();
        $_SERVER['HTTP_X_COR_REQUEST_ID']='synthetic-valid-message';
        $_SERVER['HTTP_X_COR_SIGNATURE']=hash_hmac('sha256',$_SERVER['HTTP_X_COR_TIMESTAMP']."\nsynthetic-valid-message\n".$raw,COR_MGMT_TICKER_SECRET);
        self::assertSame('OK',$this->gateway());
        self::assertSame('409',$this->gateway());
        self::assertSame('1',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sms_ticker"));
        self::assertSame('U_'.$this->member,$wpdb->get_var("SELECT send_from FROM {$wpdb->prefix}sms_ticker"));
        self::assertStringNotContainsString('<script',(string)$wpdb->get_var("SELECT msg FROM {$wpdb->prefix}sms_ticker"));
    }

    public function testFrontendFormEscapesScalarAndRepeatedAttributeValues(): void
    {
        $fields=[['id'=>'team_name','label'=>'Name','type'=>'text','value'=>'"><input name="injected">'],
            ['id'=>'invitations','label'=>'Email','type'=>'text-repeatable-no-js','num'=>1,'value'=>['"><input name="injected">']]];
        $output='';
        require dirname(__DIR__,2).'/templates/frontend-form.php';
        self::assertStringNotContainsString('<input name="injected">',$output);
        self::assertSame(2,substr_count($output,'&lt;input name=&quot;injected&quot;&gt;'));
    }

    public function testRealAdminTableEscapesRowAndConvertsDeleteAction(): void
    {
        require_once dirname(__DIR__,2).'/templates/class-h3-mgmt-admin-table.php';
        $table=new \H3_MGMT_Admin_Table(['echo'=>false,'page_slug'=>'h3-mgmt-teams'],
            [['id'=>'team_name','title'=>'Team','actions'=>['delete']]],
            [['id'=>42,'team_name'=>'<img src=x onerror=alert(1)>']]);
        $html=$table->output();
        self::assertStringNotContainsString('<img src=x',$html);
        self::assertStringNotContainsString('onclick=',$html);
        self::assertStringContainsString('data-confirm=',$html);
        self::assertStringContainsString('method="post"',$html);
        self::assertStringContainsString('name="cor_mgmt_nonce"',$html);
    }

    public function testLegacyOverlayShortcodeUsesProtectedWordPressAdapter(): void
    {
        add_shortcode('profile_layer',static fn()=>'insecure legacy handler must not execute');
        try {
            wp_set_current_user(0);
            self::assertStringContainsString('log in',do_shortcode('[profile_layer]'));
            wp_set_current_user($this->member);
            $html=do_shortcode('[profile_layer]');
            self::assertStringContainsString('admin-post.php',$html);
            self::assertStringContainsString('cor_mgmt_profile_layer',$html);
            self::assertStringContainsString('name="cor_mgmt_nonce"',$html);
            self::assertStringNotContainsString('/uploads/Profile_Layer_Pics/upload.php',$html);
        } finally { remove_shortcode('profile_layer'); }
    }

    public function testGatewayValidImageGetsRandomNameAndOwnershipMetadata(): void
    {
        global $wpdb;
        // Synthetic one-pixel PNG, never a copied user image.
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNg+M/wHwAEAQH/cetH5QAAAABJRU5ErkJggg==');
        $url=\H3_MGMT_Media::gateway_image($bytes,9);
        self::assertIsString($url);
        $attachment=(int)$wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_cor_mgmt_race' AND meta_value='9' ORDER BY meta_id DESC LIMIT 1");
        try {
            self::assertGreaterThan(0,$attachment);
            self::assertMatchesRegularExpression('/[0-9a-f-]{36}\.png$/',$url);
            self::assertSame($attachment,\H3_MGMT_Media::owned_image($url,9));
            if (function_exists('imagecreatefromstring')) {
                $path=get_attached_file($attachment);
                $composed=\H3_MGMT_Media::compose_layer($path,$path);
                self::assertIsArray($composed);
                try { self::assertSame('image/png',getimagesize($composed['file'])['mime']); }
                finally { wp_delete_file($composed['file']); }
            }
            wp_set_current_user($this->member);
            self::assertSame(0,\H3_MGMT_Media::owned_image($url,9));
        } finally { wp_delete_attachment($attachment,true); }
    }

    public function testTeamMailCannotBeResentViaGet(): void
    {
        wp_set_current_user($this->member);
        $_GET=['todo'=>'resend','id'=>42];
        self::assertStringContainsString('not permitted',$GLOBALS['h3_mgmt_teams']->team_homework(['race'=>9]));
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }

    public function testDonationTokenCannotModifyAnotherRaceOrConfirmWithoutProvider(): void
    {
        global $wpdb;
        $settings=json_decode($wpdb->get_var("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=9"),true);
        $settings['donation']=1;
        $settings['betterplace_redirect_link']='https://www.betterplace.org/de/fundraising-events/123-synthetic';
        $wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>9]);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_sponsors',['id'=>810,'race_id'=>10,'team_id'=>44,'type'=>'sponsor',
            'donation_token'=>'synthetic-token','donation_client_reference'=>$_COOKIE['client_token'],'paid'=>0]);
        $this->post(Security::donation_nonce(9),['submitted'=>2,'type'=>'sponsor','donation_token'=>'synthetic-token']);
        self::assertStringContainsString('error',$GLOBALS['h3_mgmt_sponsors']->new_sponsoring_handler(['race'=>9]));
        self::assertSame('0',$wpdb->get_var("SELECT paid FROM {$wpdb->prefix}h3_mgmt_sponsors WHERE id=810"));
        $wpdb->update($wpdb->prefix.'h3_mgmt_sponsors',['race_id'=>9,'team_id'=>42],['id'=>810]);
        self::assertStringContainsString('error',$GLOBALS['h3_mgmt_sponsors']->new_sponsoring_handler(['race'=>9]));
        self::assertSame('0',$wpdb->get_var("SELECT paid FROM {$wpdb->prefix}h3_mgmt_sponsors WHERE id=810"));
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }

    public function testTeamFormRejectsMissingNonceAndMalformedInputBeforeWriting(): void
    {
        wp_set_current_user($this->member);
        $save=new ReflectionMethod(\H3_MGMT_Teams::class,'save_dashboard');
        self::assertStringContainsString('not permitted',$save->invoke($GLOBALS['h3_mgmt_teams'],9));
        $this->post('cor-mgmt-team-9',['invitations'=>'invalid shape']);
        self::assertStringContainsString('not permitted',$save->invoke($GLOBALS['h3_mgmt_teams'],9));
        $this->post('cor-mgmt-team-9',['birthday-year'=>2000,'birthday-month'=>2,'birthday-day'=>31]);
        self::assertStringContainsString('not permitted',$save->invoke($GLOBALS['h3_mgmt_teams'],9));
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }

    private function gateway(): string
    {
        ob_start();
        try { $GLOBALS['h3_mgmt_ticker']->ticker_incoming_interface(); return ob_get_contents(); }
        finally { ob_end_clean(); }
    }

    private static function assertWPError($value): void { self::assertInstanceOf(\WP_Error::class,$value); }
}
