<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;
use Cor\Mgmt\Application\TransportException;
use Cor\Mgmt\Domain\InstallationStatus;
use Cor\Mgmt\Infrastructure\WordPress\SchemaInspector;
use Cor\Mgmt\Infrastructure\WordPress\WordPressHttpClient;
use Cor\Mgmt\Infrastructure\WordPress\WordPressMailTransport;
use Cor\Mgmt\WordPress\Capabilities;
use Cor\Mgmt\WordPress\Lifecycle;
use PHPUnit\Framework\TestCase;

final class FoundationTest extends TestCase {
    public function testFreshFixtureAlwaysHasAnAbsoluteUploadUrl(): void {
        $uploads = wp_upload_dir();
        self::assertSame('https', wp_parse_url($uploads['baseurl'], PHP_URL_SCHEME));
        self::assertSame('example.invalid', wp_parse_url($uploads['baseurl'], PHP_URL_HOST));
        self::assertFalse($uploads['error']);
    }
    public function testActivationAddsCapabilitiesWithoutChangingSchemaOrRecords(): void {
        global $wpdb;
        $before = $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'h3_mgmt_races');
        self::assertTrue(Lifecycle::activate());
        self::assertSame('2.8', get_option('h3_mgmt_db_version'));
        self::assertSame($before, $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'h3_mgmt_races'));
        foreach (Capabilities::all() as $capability) { self::assertTrue(get_role('administrator')->has_cap($capability)); }
        self::assertTrue(Lifecycle::ready());
    }
    public function testNetworkActivationIsRejectedWithoutVersionChanges(): void {
        self::assertWPError(Lifecycle::activate(true));
        self::assertSame('2.8', get_option('h3_mgmt_db_version'));
    }
    private static function assertWPError($value): void { self::assertInstanceOf(\WP_Error::class, $value); }
    public function testSchemaRecognitionHandlesFreshPartialOlderAndNewerWithoutCreatingTables(): void {
        global $wpdb;
        $version = get_option('h3_mgmt_db_version');
        $database = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $prefix = 'cortest_foundation_'.bin2hex(random_bytes(4)).'_';
        $database->set_prefix($prefix);
        $inspector = new SchemaInspector($database);
        try {
            delete_option('h3_mgmt_db_version');
            self::assertSame(InstallationStatus::Fresh, $inspector->status());
            self::assertSame([], $database->get_col($database->prepare('SHOW TABLES LIKE %s', $database->esc_like($prefix).'%')));
            foreach (SchemaInspector::TABLES as $suffix) {
                self::assertNotFalse($database->query('CREATE TABLE '.$prefix.'h3_mgmt_'.$suffix.' (id bigint PRIMARY KEY) ENGINE=InnoDB'));
            }
            self::assertSame(InstallationStatus::Incomplete, $inspector->status());
            update_option('h3_mgmt_db_version','2.7');
            self::assertSame(InstallationStatus::UpgradeRequired, $inspector->status());
            update_option('h3_mgmt_db_version','2.8');
            self::assertSame(InstallationStatus::Ready, $inspector->status());
            $database->query('DROP TABLE '.$prefix.'h3_mgmt_stages');
            self::assertSame(InstallationStatus::Incomplete, $inspector->status());
            update_option('h3_mgmt_db_version','9.0');
            self::assertSame(InstallationStatus::NewerSchema, $inspector->status());
            self::assertSame('9.0', get_option('h3_mgmt_db_version'));
            self::assertWPError(Lifecycle::activate());
            self::assertSame('9.0', get_option('h3_mgmt_db_version'));
        } finally {
            foreach (SchemaInspector::TABLES as $suffix) { $database->query('DROP TABLE IF EXISTS '.$prefix.'h3_mgmt_'.$suffix); }
            update_option('h3_mgmt_db_version', $version);
            $database->close();
        }
    }
    public function testDeactivationPreservesOtherJobsAndData(): void {
        wp_schedule_single_event(time()+3600, Lifecycle::CRON_HOOKS[0]);
        wp_schedule_single_event(time()+3600, Lifecycle::CRON_HOOKS[0], ['synthetic-batch']);
        wp_schedule_single_event(time()+3600, 'cor_test_other_plugin_job');
        try {
            Lifecycle::deactivate();
            self::assertFalse(wp_next_scheduled(Lifecycle::CRON_HOOKS[0]));
            self::assertFalse(wp_next_scheduled(Lifecycle::CRON_HOOKS[0], ['synthetic-batch']));
            self::assertIsInt(wp_next_scheduled('cor_test_other_plugin_job'));
            self::assertSame('2.8', get_option('h3_mgmt_db_version'));
            self::assertTrue(Lifecycle::ready());
        } finally { wp_clear_scheduled_hook('cor_test_other_plugin_job'); }
    }
    public function testLegacyUninstallCannotDeleteData(): void {
        h3_mgmt_uninstall();
        self::assertSame(InstallationStatus::Ready, h3_mgmt_update_db_check());
        self::assertSame('2.8',get_option('h3_mgmt_db_version'));
    }
    public function testWordPressUninstallEntryPreservesSchemaCapabilitiesAndUnrelatedJobs(): void {
        if (!defined('WP_UNINSTALL_PLUGIN')) { define('WP_UNINSTALL_PLUGIN', 'cor-mgmt/h3-management.php'); }
        wp_schedule_single_event(time()+3600, Lifecycle::CRON_HOOKS[0]);
        wp_schedule_single_event(time()+3600, Lifecycle::CRON_HOOKS[0], ['synthetic-batch']);
        wp_schedule_single_event(time()+3600, 'cor_test_uninstall_other_job');
        try {
            require dirname(__DIR__,2).'/uninstall.php';
            self::assertFalse(wp_next_scheduled(Lifecycle::CRON_HOOKS[0]));
            self::assertFalse(wp_next_scheduled(Lifecycle::CRON_HOOKS[0], ['synthetic-batch']));
            self::assertIsInt(wp_next_scheduled('cor_test_uninstall_other_job'));
            self::assertSame('2.8',get_option('h3_mgmt_db_version'));
            self::assertTrue(get_role('administrator')->has_cap(Capabilities::all()[0]));
            self::assertTrue(Lifecycle::ready());
        } finally { wp_clear_scheduled_hook('cor_test_uninstall_other_job'); }
    }
    public function testHttpOptionsAreBoundedAndErrorsDoNotExposeResponseDetails(): void {
        global $wp_filter;
        $saved = clone $wp_filter['pre_http_request'];
        remove_all_filters('pre_http_request');
        $captured = [];
        add_filter('pre_http_request', static function ($pre,$args,$url) use (&$captured) {
            $captured = $args;
            return ['headers'=>[],'body'=>'synthetic response','response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];
        }, 10, 3);
        try {
            $response = (new WordPressHttpClient())->get('https://example.invalid/api',['timeout'=>999,'redirection'=>99,'limit_response_size'=>99999999]);
            self::assertSame(200, $response->status);
            self::assertSame('synthetic response', $response->body);
            self::assertSame(10, $captured['timeout']);
            self::assertSame(0, $captured['redirection']);
            self::assertSame(2097152, $captured['limit_response_size']);
            remove_all_filters('pre_http_request');
            add_filter('pre_http_request',static fn()=>new \WP_Error('timeout','synthetic secret details'));
            try { (new WordPressHttpClient())->get('https://example.invalid/api'); self::fail('Transport exception expected'); }
            catch (TransportException $error) { self::assertSame('HTTP transport failed.', $error->getMessage()); }
        } finally { $wp_filter['pre_http_request'] = $saved; }
    }
    public function testMailTransportKeepsWordPressInterception(): void {
        $GLOBALS['cor_test_mail'] = [];
        self::assertTrue((new WordPressMailTransport())->send('synthetic@example.invalid','Synthetic','Test','Content-Type: text/plain'));
        self::assertCount(1,$GLOBALS['cor_test_mail']);
        self::assertSame('synthetic@example.invalid',$GLOBALS['cor_test_mail'][0]['to']);
    }
}
