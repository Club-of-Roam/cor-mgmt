<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\WordPress\SitePages;
use Cor\Mgmt\WordPress\TeamPortal;
use PHPUnit\Framework\TestCase;

final class SitePagesTest extends TestCase
{
    private int $admin;
    private int $subscriber;
    private array $posts = [];
    private array $server;
    protected function setUp(): void
    {
        global $wpdb;
        $this->server = [$_SERVER['REQUEST_METHOD'] ?? null, $_POST, $_GET, get_current_user_id()];
        $this->admin = self::user('page_setup_admin', 'administrator');
        $this->subscriber = self::user('page_setup_subscriber', 'subscriber');
        $wpdb->insert($wpdb->prefix.'h3_mgmt_races', ['id'=>880081,'name'=>'Synthetic page setup','setting'=>'{"status":1}']);
        wp_set_current_user($this->admin);
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['race_id'=>'880081','kind'=>'team','page_operation'=>'create','cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-pages')];
    }
    private static function user(string $login, string $role): int
    {
        $id=wp_insert_user(['user_login'=>$login,'user_email'=>$login.'@example.invalid','user_pass'=>bin2hex(random_bytes(16)),'role'=>$role]);
        self::assertIsInt($id);return $id;
    }
    protected function tearDown(): void
    {
        global $wpdb;
        foreach(array_unique($this->posts) as $id){wp_delete_post($id,true);}
        delete_option('cor_mgmt_pages_880081');
        $wpdb->delete($wpdb->prefix.'h3_mgmt_races',['id'=>880081]);
        wp_delete_user($this->admin);wp_delete_user($this->subscriber);
        [$_SERVER['REQUEST_METHOD'],$_POST,$_GET,$user]=$this->server;wp_set_current_user($user);
    }
    private function rejects(string $reason): void
    {
        try{SitePages::submit();self::fail('Expected rejection');}catch(\RuntimeException $e){self::assertSame($reason,$e->getMessage());}
    }
    public function testCreateIsDraftIdempotentAndOnlyPublishedPagesReplaceThePortal(): void
    {
        $fallback=TeamPortal::url(880081);self::assertStringContainsString('admin-post.php',$fallback);
        $id=SitePages::submit();$this->posts[]=$id;
        self::assertSame('draft',get_post_status($id));self::assertSame(SitePages::content(880081,'team'),get_post($id)->post_content);
        self::assertSame('Event 880081: Team Dashboard', get_post($id)->post_title);
        wp_update_post(['ID' => $id, 'post_title' => 'Reviewed title']);
        self::assertSame($id,SitePages::submit());self::assertSame('Reviewed title', get_post($id)->post_title);self::assertSame($fallback,TeamPortal::url(880081));
        wp_update_post(['ID'=>$id,'post_status'=>'publish']);self::assertSame(get_permalink($id),TeamPortal::url(880081));
        wp_update_post(['ID'=>$id,'post_password'=>'protected']);self::assertSame($fallback,TeamPortal::url(880081));
        wp_update_post(['ID'=>$id,'post_password'=>'','post_content'=>'Changed by editor']);
        self::assertSame($fallback,TeamPortal::url(880081));$this->rejects('page_content_requires_review');
        self::assertSame('Changed by editor',get_post($id)->post_content);
    }
    public function testAssignmentPreservesExistingContentsAndDoesNotClaimOwnership(): void
    {
        $body='Existing introduction '.SitePages::content(880081,'team').' Existing ending';
        $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Existing synthetic page','post_content'=>$body]);$this->posts[]=$id;
        $_POST['page_operation']='assign';$_POST['page_id']=(string)$id;
        self::assertSame($id,SitePages::submit());self::assertSame($body,get_post($id)->post_content);
        self::assertSame('',get_post_meta($id,'_cor_mgmt_page',true));self::assertSame(get_permalink($id),SitePages::url(880081,'team'));
    }
    public function testMissingNonceGuestSubscriberAndGetCannotWrite(): void
    {
        $count=wp_count_posts('page')->draft;$_POST['cor_mgmt_nonce']='forged';$this->rejects('page_setup_forbidden');
        $_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-pages');$_SERVER['REQUEST_METHOD']='GET';$this->rejects('page_setup_forbidden');
        $_SERVER['REQUEST_METHOD']='POST';foreach([$this->subscriber,0] as $id){wp_set_current_user($id);$_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-pages');$this->rejects('page_setup_forbidden');}
        self::assertSame($count,wp_count_posts('page')->draft);self::assertFalse(get_option('cor_mgmt_pages_880081'));
    }
    public function testUnknownEventsKindsAndOtherEventPagesAreRejected(): void
    {
        $_POST['race_id']='880082';$this->rejects('invalid_event');$_POST['race_id']='880081';$_POST['kind']='gateway';$this->rejects('invalid_page_operation');
        $_POST['kind']='team';$_POST['page_operation']='assign';$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_content'=>SitePages::content(880082,'team')]);$this->posts[]=$id;$_POST['page_id']=(string)$id;
        $this->rejects('page_content_requires_review');self::assertFalse(get_option('cor_mgmt_pages_880081'));
        $_POST['race_id']=['880081'];$this->rejects('invalid_page_reference');
    }
    public function testReadOnlySetupScreenDoesNotCreatePagesOrOptions(): void
    {
        $matching = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Matching page', 'post_content' => SitePages::content(880081, 'team')]);
        $wrong = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Other event page', 'post_content' => SitePages::content(880082, 'team')]);
        $this->posts[] = $matching; $this->posts[] = $wrong;
        $_GET=['race_id'=>'880081'];$count=wp_count_posts('page')->draft;
        ob_start();try{SitePages::render(880081);$html=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('Create or reuse draft',$html);self::assertStringContainsString('race=&quot;880081&quot;',$html);
        self::assertStringContainsString('data-search-picker', $html);
        self::assertStringContainsString('Matching page', $html);
        self::assertStringNotContainsString('Other event page', $html);
        $document = new \DOMDocument();
        $document->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        self::assertSame(6, $xpath->query('//form[input[@name="kind"] and not(.//select[@name="page_id"])]')->length);
        self::assertSame(6, $xpath->query('//form//select[@name="page_id" and @required]')->length);
        foreach (['Team Dashboard', 'Teams Overview', 'Results and Rankings', 'Live Ticker', 'Ticker Map', 'Donations'] as $label) {
            self::assertStringContainsString('<h3>' . $label . '</h3>', $html);
        }
        self::assertSame($count,wp_count_posts('page')->draft);self::assertFalse(get_option('cor_mgmt_pages_880081'));
    }
    public function testFreshEventAndMissingEventHaveSafeLegacyTextSlots(): void
    {
        global $h3_mgmt_races;
        set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{throw new \ErrorException($message,0,$severity,$file,$line);});
        try{
            self::assertSame(array_fill(1,27,''),$h3_mgmt_races->get_race_information_text(880081));
            self::assertSame(array_fill(1,27,''),$h3_mgmt_races->get_race_information_text(880082));
            self::assertSame([],$h3_mgmt_races->get_race_setting(880082));
        }finally{restore_error_handler();}
    }
    public function testEmptyAssignmentIsRejectedWithoutCreatingPagesAndExplainsTheRequiredId(): void
    {
        $count = wp_count_posts('page')->draft;
        $_POST['page_operation'] = 'assign';
        $_POST['page_id'] = '';
        $this->rejects('invalid_page_reference');
        self::assertSame($count, wp_count_posts('page')->draft);
        self::assertFalse(get_option('cor_mgmt_pages_880081'));
        self::assertStringContainsString('existing WordPress page', SitePages::problem(new \RuntimeException('invalid_page_reference')));
        self::assertStringContainsString('Create or reuse draft', SitePages::problem(new \RuntimeException('invalid_page_reference')));
        self::assertStringNotContainsString('private SQL', SitePages::problem(new \RuntimeException('private SQL')));
    }
    public function testPageManagementIsIndependentOfAllFivePhasesAndPreservesRaceData(): void
    {
        global $wpdb;
        foreach (range(0, 4) as $phase) {
            $settings = json_encode(['status' => $phase, 'team_overview_link' => '/original-teams/']);
            $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting' => $settings], ['id' => 880081]);
            $id = SitePages::submit();
            $this->posts[] = $id;
            ob_start();
            try { SitePages::render(880081); $html = ob_get_contents(); } finally { ob_end_clean(); }
            self::assertStringContainsString('Create or reuse draft', $html);
            self::assertSame($settings, $wpdb->get_var("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=880081"));
            self::assertSame('draft', get_post_status($id));
        }
        self::assertCount(1, array_unique($this->posts));
        self::assertStringContainsString('page=h3-mgmt-races', SitePages::settingsUrl(880081));
        self::assertStringContainsString('id=880081#cor-mgmt-event-pages', SitePages::settingsUrl(880081));
        self::assertStringNotContainsString('todo=', SitePages::settingsUrl(0));
    }
    public function testLegacyLinksRemainUntilPublishedReplacementAndPlainPermalinksKeepArguments(): void
    {
        global $wpdb;
        $legacy = site_url('/original-teams/');
        $settings = json_encode(['status' => 1, 'team_overview_link' => '/original-teams/', 'donation_link_link' => '/donations/']);
        $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting' => $settings], ['id' => 880081]);
        self::assertSame($legacy, SitePages::url(880081, 'teams'));
        self::assertSame(site_url('/donations/'), SitePages::url(880081, 'donations'));
        $_POST['kind'] = 'donations';
        $donationPage = SitePages::submit(); $this->posts[] = $donationPage;
        self::assertSame('Event 880081: Donations', get_post($donationPage)->post_title);
        self::assertSame('[h3-sponsoring-form race="880081"]', get_post($donationPage)->post_content);
        self::assertSame(site_url('/donations/'), SitePages::url(880081, 'donations'));
        $_POST['kind'] = 'teams';
        $id = SitePages::submit(); $this->posts[] = $id;
        self::assertSame($legacy, SitePages::url(880081, 'teams'));
        wp_update_post(['ID' => $id, 'post_status' => 'publish']);
        self::assertSame(get_permalink($id), SitePages::url(880081, 'teams'));
        parse_str((string) wp_parse_url(SitePages::link(880081, 'teams', ['id' => 42]), PHP_URL_QUERY), $query);
        self::assertSame('42', $query['id']);
        if (str_contains(get_permalink($id), 'page_id=')) { self::assertSame((string) $id, $query['page_id']); }
        self::assertSame($settings, $wpdb->get_var("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=880081"));
        foreach (['https://external.invalid/teams/', '//external.invalid/teams/', 'javascript:alert(1)', 'https://user:password@example.invalid/'] as $invalid) {
            $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting' => json_encode(['status' => 1, 'donation_link_link' => $invalid])], ['id' => 880081]);
            self::assertSame('', SitePages::url(880081, 'donations'));
        }
    }
    public function testNewEventGuidanceAndEmbeddedFormsDoNotReintroduceLegacyUrlFields(): void
    {
        $sections = [['fields' => [['id' => 'team_overview_link'], ['id' => 'donation_link_link'], ['id' => 'name']]]];
        ob_start();
        try { \Cor\Mgmt\WordPress\EventForm::render('race', 0, $sections, 'New event', '', false); $html = ob_get_contents(); } finally { ob_end_clean(); }
        self::assertStringContainsString('Save the new event first', $html);
        self::assertStringNotContainsString('name="team_overview_link"', $html);
        self::assertStringNotContainsString('name="donation_link_link"', $html);
        ob_start();
        try { \Cor\Mgmt\WordPress\EventForm::render('race', 880081, $sections, 'Event', '', false); $html = ob_get_contents(); } finally { ob_end_clean(); }
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        self::assertSame(0, (new \DOMXPath($document))->query('//form//form')->length);
        self::assertStringContainsString('id="cor-mgmt-event-pages"', $html);
    }
}
