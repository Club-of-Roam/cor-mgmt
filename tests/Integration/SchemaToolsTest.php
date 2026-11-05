<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;
use Cor\Mgmt\WordPress\SchemaTools;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class SchemaJsonExit extends \RuntimeException {}
final class SchemaToolsTest extends TestCase {
    private array $post;
    private array $server;
    private int $user;
    protected function setUp(): void {
        $this->post=$_POST; $this->server=$_SERVER; $this->user=get_current_user_id();
        $_POST=[]; $_SERVER['REQUEST_METHOD']='POST';
        wp_set_current_user(get_user_by('login','synthetic_admin')->ID);
    }
    protected function tearDown(): void { $_POST=$this->post; $_SERVER=$this->server; wp_set_current_user($this->user); }
    private function request(): array {
        $ajax=static fn()=>true;
        $die=static fn()=>static function () { throw new SchemaJsonExit(); };
        add_filter('wp_doing_ajax',$ajax); add_filter('wp_die_ajax_handler',$die);
        ob_start();
        try { SchemaTools::request(); self::fail('JSON response did not terminate'); }
        catch (SchemaJsonExit) { $body=ob_get_contents(); }
        finally { ob_end_clean(); remove_filter('wp_doing_ajax',$ajax); remove_filter('wp_die_ajax_handler',$die); }
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    }
    #[DataProvider('invalidRequests')]
    public function testSchemaBoundaryRejectsInvalidRequestsWithoutMigration(string $method,bool $admin,$nonce,$operation,string $expected): void {
        global $wpdb;
        $before=$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cor_mgmt_migration_runs");
        $_SERVER['REQUEST_METHOD']=$method;
        $_POST=['nonce'=>$nonce==='valid'?wp_create_nonce('cor-mgmt-schema'):$nonce,'operation'=>$operation];
        if (!$admin) { wp_set_current_user(0); }
        $result=$this->request();
        self::assertFalse($result['success']); self::assertSame($expected,$result['data']['code']);
        self::assertSame($before,$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cor_mgmt_migration_runs"));
    }
    public static function invalidRequests(): array {
        return [
            ['GET',true,'valid','results-setup','post_required'],['POST',false,'valid','results-setup','forbidden'],['POST',true,'wrong','results-setup','invalid_nonce'],
            ['GET',true,'valid','participation-setup','post_required'],['POST',false,'valid','participation-setup','forbidden'],['POST',true,'wrong','participation-setup','invalid_nonce'],
            ['GET',true,'valid','preflight','post_required'],['POST',false,'valid','preflight','forbidden'],
            ['POST',true,'wrong','preflight','invalid_nonce'],['POST',true,[],'preflight','invalid_nonce'],
            ['POST',true,'valid',[],'invalid_operation'],['POST',true,'valid','drop','invalid_operation'],
            ['POST',true,'valid','start','maintenance_prerequisites'],['POST',true,'valid','step','maintenance_prerequisites'],
        ];
    }
    public function testAuthorizedResultsSetupIsExplicitAndIdempotent(): void {
        $_POST=['nonce'=>wp_create_nonce('cor-mgmt-schema'),'operation'=>'results-setup'];
        $result=$this->request();self::assertTrue($result['success']);self::assertTrue($result['data']['reload']);
    }
    public function testAuthorizedPreflightReturnsOnlyAggregateReport(): void {
        $_POST=['nonce'=>wp_create_nonce('cor-mgmt-schema'),'operation'=>'preflight'];
        $result=$this->request();
        self::assertTrue($result['success']); self::assertCount(9,$result['data']['tables']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$result['data']['fingerprint']);
        foreach ($result['data']['tables'] as $table) { self::assertSame(['count','hash'],array_keys($table)); }
        self::assertArrayNotHasKey('schema',$result['data']); self::assertArrayNotHasKey('rows',$result['data']);
    }
    public function testCompletedFreshInstallRepeatsWithoutResetOverAjax(): void {
        $_POST=['nonce'=>wp_create_nonce('cor-mgmt-schema'),'operation'=>'install'];
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['team_name'=>'Synthetic retained team']);
        $id=$wpdb->insert_id;
        try { $result=$this->request();
        self::assertSame('Synthetic retained team',$wpdb->get_var($wpdb->prepare("SELECT team_name FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=%d",$id))); }
        finally { $wpdb->delete($wpdb->prefix.'h3_mgmt_teams',['id'=>$id]); }
        // The already completed fresh installation is safely repeatable, never a reset.
        self::assertTrue($result['success']); self::assertTrue($result['data']['reload']);
    }
    public function testLegacyRuntimeRejectsAnAlreadySelectedOrNewerStorageGeneration(): void {
        global $wpdb;
        $db=new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
        try {
            $db->setOption('cor_mgmt_active_generation',str_repeat('a',32));
            self::assertFalse(\Cor\Mgmt\WordPress\Lifecycle::ready());
            self::assertSame('cor_mgmt_storage_review',\Cor\Mgmt\WordPress\Lifecycle::activate()->get_error_code());
            $db->setOption('cor_mgmt_active_generation',''); $db->setOption('cor_mgmt_storage_version','999.0');
            self::assertFalse(\Cor\Mgmt\WordPress\Lifecycle::ready());
            self::assertSame('cor_mgmt_storage_review',\Cor\Mgmt\WordPress\Lifecycle::activate()->get_error_code());
        } finally {
            $db->setOption('cor_mgmt_storage_version',\Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue::STORAGE_VERSION);
            $db->execute($db->prepare('DELETE FROM %i WHERE option_name=%s',$wpdb->options,'cor_mgmt_active_generation')); wp_cache_flush();
        }
    }

    public function testParticipationSetupIsExplicitAndIdempotentOverAjax(): void {
        global $wpdb;
        $before=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}h3_mgmt_invitations ORDER BY id",ARRAY_A);
        $_POST=['nonce'=>wp_create_nonce('cor-mgmt-schema'),'operation'=>'participation-setup'];
        $result=$this->request();self::assertTrue($result['success']);self::assertTrue($result['data']['reload']);
        self::assertSame($before,$wpdb->get_results("SELECT * FROM {$wpdb->prefix}h3_mgmt_invitations ORDER BY id",ARRAY_A));
    }

}
