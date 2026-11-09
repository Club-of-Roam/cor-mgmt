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
        self::assertSame($id,SitePages::submit());self::assertSame($fallback,TeamPortal::url(880081));
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
        $_GET=['race_id'=>'880081'];$count=wp_count_posts('page')->draft;
        ob_start();try{SitePages::page();$html=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('Create or reuse draft',$html);self::assertStringContainsString('race=&quot;880081&quot;',$html);
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
}
