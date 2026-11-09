<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Domain\Ticker\Content;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\TickerSchema;
use Cor\Mgmt\Infrastructure\WordPress\TickerImport;
use Cor\Mgmt\WordPress\TickerBoundary;
use PHPUnit\Framework\TestCase;

/** Real SQL, WordPress users and hooks; never imports production data. */
final class TickerTest extends TestCase
{
    private Database $db;
    private int $actor;
    protected function setUp(): void
    {
        global $wpdb;
        $this->db = new Database($wpdb);
        self::assertTrue(TickerSchema::ready($this->db));
        $wpdb->query('START TRANSACTION');
        foreach (['sms_ticker','cor_mgmt_ticker_posts','cor_mgmt_ticker_history'] as $table) {
            $this->db->execute($this->db->prepare('DELETE FROM %i', $this->db->table($table)));
        }
        delete_option('cor_mgmt_ticker_transition');
        $this->actor = wp_create_user('synthetic_ticker_member', 'synthetic-password', 'ticker@example.invalid');
        self::assertGreaterThan(0, $this->actor);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_races', ['id' => 9909, 'name' => 'Synthetic ticker event', 'setting' => wp_json_encode(['status' => 1, 'num_teammember_min' => 1, 'dis_fee' => 1, 'dis_waiver' => 1, 'dis_shirt_size' => 1, 'dis_mobile_inf' => 1, 'liveticker' => 1, 'liveticker_front' => 1])]);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teams', ['id' => 9942, 'race_id' => 9909, 'route_id' => 9907, 'complete' => 1, 'team_name' => 'Synthetic ticker team']);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teammates', ['team_id' => 9942, 'user_id' => $this->actor, 'paid' => 1, 'waiver' => 1]);
        wp_set_current_user($this->actor);
        $_POST = $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();
        wp_set_current_user(0);
        $_POST = $_GET = [];
        self::assertSame('', $wpdb->last_error);
        self::assertSame([], $GLOBALS['cor_test_mail']);
    }
    private function native(): void
    {
        (new TickerImport($this->db))->start(true);
    }
    private function source(int $id, int $type = 1, string $text = 'Historical &amp; text', string $sender = '00491234'): void
    {
        global $wpdb;
        self::assertSame(1, $wpdb->insert($wpdb->prefix . 'sms_ticker', ['id' => $id, 'send_from' => $sender, 'msg' => $text, 'type' => $type, 'img_url' => $type === 4 ? '52,13' : '', 'img' => $type >= 100 ? '10' : '', 'timestamp' => '1700000000', 'race_id' => 9909]));
    }
    public function testInstallationIsIndependentAndIdempotent(): void
    {
        (new Installer($this->db))->ticker();
        self::assertTrue(TickerSchema::ready($this->db));
        self::assertFalse(class_exists('Mindo_SMS_Ticker', false));
        $this->native();
        self::assertTrue(TickerBoundary::manager()->native());
    }
    public function testHistoricalCopyRetainsIdsOriginalPayloadsAndComments(): void
    {
        foreach ([10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5, 60 => 100, 70 => 55] as $id => $type) {
            $this->source($id, $type);
        }
        $before = $this->db->rows($this->db->prepare('SELECT * FROM %i ORDER BY id', $this->db->table('sms_ticker')));
        $import = new TickerImport($this->db);
        $import->start(true);
        self::assertFalse(TickerBoundary::manager()->native());
        $import->step();
        self::assertSame('verify', $import->status()['state']);
        $import->step();
        $import->step();
        self::assertSame('native', $import->status()['state']);
        self::assertSame(7, $import->status()['verified']);
        self::assertGreaterThan(0, $import->status()['issues']);
        foreach ($before as $row) {
            $post = TickerBoundary::manager()->post((int) $row['id']);
            self::assertSame(Content::snapshot($row), $post['source_payload']);
            self::assertSame($row['id'], $post['id']);
        }
        self::assertSame($before, $this->db->rows($this->db->prepare('SELECT * FROM %i ORDER BY id', $this->db->table('sms_ticker'))));
        self::assertSame('10', TickerBoundary::manager()->post(60)['parent_id']);
        self::assertSame('0', TickerBoundary::manager()->post(10)['actor_id']);
        self::assertCount(1, TickerBoundary::manager()->feed(9909, 0, 0, 1, 100, false, 0, 10));
        self::assertGreaterThan(70, TickerBoundary::manager()->create($this->actor, 9909, 'text', 'New post', '', 0, 0, str_repeat('a', 32)));
    }
    public function testBoundedTransferResumesAndDetectsSameCountMutation(): void
    {
        for ($id = 1; $id <= 101; $id++) {
            $this->source($id);
        }
        $import = new TickerImport($this->db);
        $import->start(true);
        $import->step();
        self::assertSame(100, $import->status()['cursor']);
        self::assertSame('copy', $import->status()['state']);
        (new TickerImport($this->db))->step();
        self::assertSame('verify', $import->status()['state']);
        $this->db->execute($this->db->prepare('UPDATE %i SET msg=%s WHERE id=1', $this->db->table('sms_ticker'), 'Changed'));
        try {
            $import->step();
            self::fail('A changed source must not become native.');
        } catch (\RuntimeException $error) {
            self::assertSame('ticker_source_changed', $error->getMessage());
        }
        self::assertSame(0, $import->status()['verified']);
        $this->db->execute($this->db->prepare('UPDATE %i SET msg=%s WHERE id=1', $this->db->table('sms_ticker'), 'Historical &amp; text'));
        $import->step();
        $import->step();
        self::assertSame(101, $import->status()['verified']);
        self::assertTrue(TickerBoundary::manager()->native());
    }
    public function testReadBeforeTransferIsScopedAndHasNoMigrationSideEffects(): void
    {
        $this->source(10, 1, 'Visible', 'U_' . $this->actor);
        $this->source(11, 4, 'Position', 'T_9942');
        $manager = TickerBoundary::manager();
        self::assertCount(2, $manager->feed(9909, 9942));
        self::assertCount(2, $manager->feed(9909, 0, 9907));
        self::assertSame([], $manager->feed(9910));
        self::assertSame([], $manager->feed(9909, 9943));
        self::assertSame('pending', (new TickerImport($this->db))->status()['state']);
        self::assertSame('0', $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $this->db->table('cor_mgmt_ticker_posts'))));
        $this->expectExceptionMessage('ticker_transition_required');
        $manager->create($this->actor, 9909, 'text', 'Blocked', '', 0, 0, str_repeat('a', 32));
    }
    public function testTargetCorruptionPreventsCutoverEvenWhenOriginalPayloadIsIntact(): void
    {
        $this->source(10);
        $import = new TickerImport($this->db);
        $import->start(true);
        $import->step();
        $this->db->execute($this->db->prepare('UPDATE %i SET body=%s WHERE id=10', $this->db->table('cor_mgmt_ticker_posts'), 'Corrupted projection'));
        try {
            $import->step();
            self::fail('A corrupt display projection must not become authoritative.');
        } catch (\RuntimeException $error) {
            self::assertSame('ticker_target_content_mismatch', $error->getMessage());
        }
        self::assertSame('verify', $import->status()['state']);
        self::assertFalse(TickerBoundary::manager()->native());
        self::assertSame('Historical &amp; text', $this->db->scalar($this->db->prepare('SELECT msg FROM %i WHERE id=10', $this->db->table('sms_ticker'))));
    }
    public function testWritesAreEventScopedReplaySafeAndEscapedOnDisplay(): void
    {
        $this->native();
        $manager = TickerBoundary::manager();
        $id = $manager->create($this->actor, 9909, 'text', '<script>example</script>', '', 0, 0, str_repeat('b', 32));
        self::assertSame($id, $manager->create($this->actor, 9909, 'text', '<script>example</script>', '', 0, 0, str_repeat('b', 32)));
        self::assertCount(1, $manager->feed(9909, 9942));
        $html = TickerBoundary::feed(['race' => 9909]);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('Synthetic ticker team', $html);
        self::assertStringNotContainsString('<script>example', $html);
        self::assertStringNotContainsString('<iframe', $html);
        $this->expectExceptionMessage('ticker_request_conflict');
        $manager->create($this->actor, 9909, 'text', 'Different text', '', 0, 0, str_repeat('b', 32));
    }
    public function testForgedParentAndUnknownEventCannotReceivePosts(): void
    {
        $this->native();
        foreach ([[9910, 'text', 0], [9909, 'comment', 9999]] as [$race, $kind, $parent]) {
            try {
                TickerBoundary::manager()->create($this->actor, $race, $kind, 'Forged', '', 0, $parent, str_repeat('c', 32));
                self::fail('Forged object was accepted.');
            } catch (\RuntimeException $error) {
                self::assertContains($error->getMessage(), ['forbidden','invalid_ticker_parent']);
            }
        }
        self::assertSame([], TickerBoundary::manager()->feed(9909));
    }
    public function testHiddenParentHidesCommentsAndModerationChecksRevisionAndArchive(): void
    {
        global $wpdb;
        $this->native();
        $manager = TickerBoundary::manager();
        $id = $manager->create($this->actor, 9909, 'text', 'Root', '', 0, 0, str_repeat('d', 32));
        $manager->create($this->actor, 9909, 'comment', 'Reply', '', 0, $id, str_repeat('e', 32));
        $admin = wp_create_user('synthetic_ticker_admin', 'synthetic-password', 'ticker-admin@example.invalid');
        (new \WP_User($admin))->set_role('administrator');
        wp_set_current_user($admin);
        $manager->moderate($admin, $id, 1, false, str_repeat('f', 32));
        self::assertSame([], $manager->feed(9909));
        self::assertSame([], $manager->feed(9909, 0, 0, 1, 10, false, 0, $id));
        try {
            $manager->moderate($admin, $id, 1, true, str_repeat('1', 32));
            self::fail('Stale moderation was accepted.');
        } catch (\RuntimeException $error) {
            self::assertSame('stale_ticker_post', $error->getMessage());
        }
        $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting' => wp_json_encode(['status' => 4])], ['id' => 9909]);
        self::assertCount(1, $manager->feed(9909, 0, 0, 1, 10, true, $admin));
        $this->expectExceptionMessage('forbidden');
        $manager->moderate($admin, $id, 2, true, str_repeat('2', 32));
    }
    public function testNonceAndAnonymousSubmissionsAreRejected(): void
    {
        $this->native();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['ticker_kind' => 'text', 'message' => 'Synthetic', 'request_key' => str_repeat('a', 32), 'cor_mgmt_nonce' => 'forged'];
        $this->expectExceptionMessage('invalid_nonce');
        TickerBoundary::submit(9909);
    }
    public function testOriginalUtf32SchemaAndUnknownBinaryFieldsArePreserved(): void
    {
        $connection = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $prefix = 'cor_ticker_variant_' . bin2hex(random_bytes(6)) . '_';
        $connection->set_prefix($prefix);
        $db = new Database($connection);
        $tables = ['options','users','h3_mgmt_races','h3_mgmt_teams','sms_ticker','cor_mgmt_ticker_posts','cor_mgmt_ticker_history'];
        try {
            $db->execute($db->prepare('CREATE TABLE %i (option_id bigint AUTO_INCREMENT PRIMARY KEY,option_name varchar(191) UNIQUE,option_value longtext,autoload varchar(20)) ENGINE=InnoDB', $connection->options));
            $db->execute($db->prepare('CREATE TABLE %i (ID bigint PRIMARY KEY) ENGINE=InnoDB', $connection->users));
            $db->execute($db->prepare('CREATE TABLE %i (id bigint PRIMARY KEY) ENGINE=InnoDB', $db->table('h3_mgmt_races')));
            $db->execute($db->prepare('CREATE TABLE %i (id bigint PRIMARY KEY,race_id bigint) ENGINE=InnoDB', $db->table('h3_mgmt_teams')));
            (new Installer($db))->ticker();
            $source = $db->table('sms_ticker');
            $db->execute($db->prepare('CREATE TABLE %i (id int PRIMARY KEY,`from` varchar(30) CHARACTER SET utf32,msg text CHARACTER SET cp1250,type int,img_url text CHARACTER SET utf32,timestamp bigint,extra varbinary(8)) ENGINE=InnoDB', $source));
            $db->execute($db->prepare('INSERT INTO %i (id,`from`,msg,type,img_url,timestamp,extra) VALUES (17,%s,%s,1,%s,1700000000,UNHEX(%s))', $source, '00491234', 'Grüße &amp; Text', '', '00fffe'));
            self::assertSame('1', $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $source)));
            $import = new TickerImport($db);
            $import->start(true);
            self::assertSame('copy', $import->status()['state']);
            $rawBefore = $db->scalar($db->prepare('SELECT HEX(msg) FROM %i WHERE id=17', $source));
            try {
                $import->step();
                self::fail('An unreviewed source charset must not be guessed.');
            } catch (\RuntimeException $error) {
                self::assertSame('ticker_source_charset_review', $error->getMessage());
            }
            self::assertSame($rawBefore, $db->scalar($db->prepare('SELECT HEX(msg) FROM %i WHERE id=17', $source)));
            self::assertSame('0', $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $db->table('cor_mgmt_ticker_posts'))));
            // Explicit synthetic operator correction, not an automatic migration behavior.
            $db->execute($db->prepare('ALTER TABLE %i MODIFY msg text CHARACTER SET utf32', $source));
            $db->execute($db->prepare('DELETE FROM %i WHERE option_name=%s', $connection->options, 'cor_mgmt_ticker_transition'));
            $import = new TickerImport($db);
            $import->start(true);
            $import->step();
            $import->step();
            $row = $db->rows($db->prepare('SELECT * FROM %i WHERE id=17', $db->table('cor_mgmt_ticker_posts')))[0];
            self::assertSame('Grüße & Text', $row['body']);
            self::assertSame('0', $row['race_id']);
            $raw = json_decode($row['source_payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame("\x00\xff\xfe", base64_decode($raw['extra'], true));
            self::assertSame(mb_convert_encoding('Grüße &amp; Text', 'UTF-32BE', 'UTF-8'), base64_decode($raw['msg'], true));
            self::assertStringContainsString('orphan_event', $row['issues']);
            $db->execute($db->prepare('ALTER TABLE %i ENGINE=MyISAM', $connection->options));
            try {
                $import->step();
                self::fail('A nontransactional checkpoint must be rejected.');
            } catch (\RuntimeException $error) {
                self::assertSame('transactional_ticker_journal_required', $error->getMessage());
            }
            self::assertSame('1', $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $db->table('cor_mgmt_ticker_posts'))));
        } finally {
            self::assertMatchesRegularExpression('/^cor_ticker_variant_[a-f0-9]{12}_$/D', $prefix);
            foreach (array_reverse($tables) as $suffix) {
                $db->execute($db->prepare('DROP TABLE IF EXISTS %i', $db->table($suffix)));
            }
            $connection->close();
        }
    }
    public function testImageRotationRetainsOriginalAndRejectsForeignAttachments(): void
    {
        $this->native();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNg+M/wHwAEAQH/cetH5QAAAABJRU5ErkJggg==');
        $relative = \H3_MGMT_Media::gateway_image($bytes, 9909);
        self::assertIsString($relative);
        $attachment = \H3_MGMT_Media::owned_image($relative, 9909);
        self::assertGreaterThan(0, $attachment);
        $copy = 0;
        try {
            $path = get_attached_file($attachment);
            $hash = hash_file('sha256', $path);
            $id = TickerBoundary::manager()->create($this->actor, 9909, 'image', 'Synthetic image', wp_get_attachment_url($attachment), $attachment, 0, str_repeat('6', 32));
            $copy = \H3_MGMT_Media::rotated_copy($attachment, 9909, 90);
            self::assertNotSame($attachment, $copy);
            self::assertNotSame($path, get_attached_file($copy));
            TickerBoundary::manager()->replaceImage($this->actor, $id, 1, $copy, str_repeat('7', 32));
            self::assertSame($hash, hash_file('sha256', $path));
            self::assertSame((string) $copy, TickerBoundary::manager()->post($id)['attachment_id']);
            update_post_meta($copy, '_cor_mgmt_race', 9910);
            $this->expectExceptionMessage('foreign_ticker_image');
            TickerBoundary::manager()->replaceImage($this->actor, $id, 2, $copy, str_repeat('8', 32));
        } finally {
            if ($copy) { wp_delete_attachment($copy, true); }
            wp_delete_attachment($attachment, true);
        }
    }
    public function testConcurrentDuplicateCommandsHaveOnePostAndJournalEntry(): void
    {
        $connection = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $prefix = 'cor_ticker_parallel_' . bin2hex(random_bytes(6)) . '_';
        $connection->set_prefix($prefix);
        $db = new Database($connection);
        $tables = ['options','h3_mgmt_teams','h3_mgmt_teammates','cor_mgmt_ticker_posts','cor_mgmt_ticker_history'];
        $directory = sys_get_temp_dir() . '/cor-mgmt-ticker-' . bin2hex(random_bytes(10));
        self::assertTrue(mkdir($directory, 0700));
        $jobs = [];
        try {
            $db->execute($db->prepare('CREATE TABLE %i (option_id bigint AUTO_INCREMENT PRIMARY KEY,option_name varchar(191) UNIQUE,option_value longtext,autoload varchar(20)) ENGINE=InnoDB', $connection->options));
            $db->execute($db->prepare('CREATE TABLE %i (id bigint PRIMARY KEY,race_id bigint) ENGINE=InnoDB', $db->table('h3_mgmt_teams')));
            $db->execute($db->prepare('CREATE TABLE %i (team_id bigint,user_id bigint) ENGINE=InnoDB', $db->table('h3_mgmt_teammates')));
            (new Installer($db))->ticker();
            (new TickerImport($db))->start(true);
            $environment = getenv();
            $environment['COR_TEST_BARRIER_DIR'] = $directory;
            foreach ([0,1] as $index) {
                $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/tools/ticker-worker.php', $prefix, (string) $index], [0=>['pipe','r'],1=>['file',$directory . '/out-' . $index,'w'],2=>['file',$directory . '/err-' . $index,'w']], $pipes, dirname(__DIR__, 2), $environment);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $jobs[] = $process;
            }
            $until = microtime(true) + 45;
            while (count(glob($directory . '/ready-*') ?: []) !== 2) {
                if (microtime(true) > $until) { self::fail('Ticker workers did not reach the barrier.'); }
                usleep(20000); clearstatcache();
            }
            touch($directory . '/go');
            $results = [];
            foreach ($jobs as $index => $process) {
                self::assertSame(0, proc_close($process), file_get_contents($directory . '/err-' . $index));
                $jobs[$index] = null;
                $results[] = json_decode(file_get_contents($directory . '/out-' . $index), true, 512, JSON_THROW_ON_ERROR);
            }
            $successes = array_values(array_filter($results, static fn(array $row):bool => $row['success']));
            self::assertNotEmpty($successes);
            foreach ($results as $row) {
                if (!$row['success']) { self::assertSame('migration_locked', $row['code']); }
            }
            $manager = new \Cor\Mgmt\Infrastructure\WordPress\Ticker($db, new \Cor\Mgmt\Infrastructure\SystemClock(), static fn():bool => true);
            self::assertSame($successes[0]['id'], $manager->create(1234, 1, 'text', 'Concurrent synthetic post', '', 0, 0, str_repeat('9', 32)));
            self::assertSame('1', $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $db->table('cor_mgmt_ticker_posts'))));
            self::assertSame('1', $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $db->table('cor_mgmt_ticker_history'))));
        } finally {
            foreach ($jobs as $process) { if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
            self::assertMatchesRegularExpression('/^cor_ticker_parallel_[a-f0-9]{12}_$/D', $prefix);
            foreach (array_reverse($tables) as $suffix) { $db->execute($db->prepare('DROP TABLE IF EXISTS %i', $db->table($suffix))); }
            $connection->close();
            if (realpath($directory) !== false && str_starts_with(basename($directory), 'cor-mgmt-ticker-')) {
                foreach (glob($directory . '/*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
                rmdir($directory);
            }
        }
    }
    public function testMediaConsentAndFormDoNotSubmitFromFeed(): void
    {
        $this->native();
        TickerBoundary::manager()->create($this->actor, 9909, 'video', 'Video', 'https://youtu.be/abcdefghijk', 0, 0, str_repeat('a', 32));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['ticker_submit' => '9909', 'message' => ['malformed'], 'ticker_kind' => 'text'];
        $html = TickerBoundary::feed(['race' => 9909]);
        self::assertStringContainsString('data-ticker-video="abcdefghijk"', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('admin-post.php', $html);
        self::assertCount(1, TickerBoundary::manager()->feed(9909));
    }
    public function testShortcodesHandleNullEventTextsAndUnknownEventsWithoutWarnings(): void
    {
        $this->native();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (error_reporting() & $severity) { throw new \ErrorException($message, 0, $severity, $file, $line); }
            return false;
        });
        try {
            self::assertStringContainsString('No messages sent yet.', do_shortcode('[h3-liveticker race="9909"]'));
            self::assertStringContainsString('submissions are disabled', do_shortcode('[h3-ticker-message race="999999"]'));
            self::assertStringContainsString('display is disabled', do_shortcode('[h3-liveticker race="999999"]'));
        } finally {
            restore_error_handler();
        }
    }
    public function testInputAndDisplaySwitchesAreIndependent(): void
    {
        global $wpdb;
        $this->native();
        $manager = TickerBoundary::manager();
        $manager->create($this->actor, 9909, 'text', 'Retained visible text', '', 0, 0, str_repeat('3', 32));
        $settings = ['status'=>1,'liveticker'=>0,'liveticker_front'=>1];
        $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting'=>wp_json_encode($settings)], ['id'=>9909]);
        self::assertStringContainsString('Retained visible text', TickerBoundary::feed(['race'=>9909]));
        self::assertStringContainsString('submissions are disabled', TickerBoundary::form(['race'=>9909]));
        self::assertFalse(TickerBoundary::allowed($this->actor, 9909, 'write'));
        $settings['liveticker'] = 1;
        $settings['liveticker_front'] = 0;
        $wpdb->update($wpdb->prefix . 'h3_mgmt_races', ['setting'=>wp_json_encode($settings)], ['id'=>9909]);
        self::assertStringContainsString('display is disabled', TickerBoundary::feed(['race'=>9909]));
        self::assertTrue(TickerBoundary::allowed($this->actor, 9909, 'comment'));
        self::assertCount(1, $manager->feed(9909));
    }
    public function testAmbiguousLegacyMembershipCannotChooseAnArbitraryTeam(): void
    {
        global $wpdb;
        $this->native();
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teams', ['id'=>9943,'race_id'=>9909,'complete'=>1,'team_name'=>'Ambiguous synthetic team']);
        $wpdb->insert($wpdb->prefix . 'h3_mgmt_teammates', ['team_id'=>9943,'user_id'=>$this->actor,'paid'=>1,'waiver'=>1]);
        $this->expectExceptionMessage('ambiguous_ticker_team');
        TickerBoundary::manager()->create($this->actor, 9909, 'text', 'Must not select an arbitrary team', '', 0, 0, str_repeat('4', 32));
    }
    public function testLegacyQueryContextCannotOverrideAnExplicitEvent(): void
    {
        $_GET = ['ticker_race'=>'9909'];
        self::assertSame(9909, TickerBoundary::race(['race'=>0]));
        self::assertSame(9910, TickerBoundary::race(['race'=>9910]));
        $_GET['ticker_race'] = ['malformed'];
        self::assertSame(0, TickerBoundary::race(['race'=>0]));
    }
    public function testHistoricalImagePathsCannotRequestExecutableOrActiveContent(): void
    {
        global $wpdb;
        foreach (['//127.0.0.1/cor-mgmt-forbidden/photo.png', 'file:///outside/photo.png', ABSPATH . 'wp-includes/version.php'] as $outside) {
            self::assertSame('', \H3_MGMT_Media::local_path($outside));
        }
        $uploads = wp_upload_dir();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNg+M/wHwAEAQH/cetH5QAAAABJRU5ErkJggg==');
        $files = [];
        $attachment = 0;
        try {
            foreach (['.php','.php.png','.svg','.png'] as $extension) {
                $name = 'synthetic-' . wp_generate_uuid4() . $extension;
                $path = trailingslashit($uploads['path']) . $name;
                $payload = $extension === '.png' ? '<?php echo "not an image";' : $bytes . '<?php echo "must never execute";';
                self::assertNotFalse(file_put_contents($path, $payload));
                $files[] = $path;
                self::assertSame('', \H3_MGMT_Media::safe_image_path($path));
            }
            $this->source(10, 3);
            $url = trailingslashit($uploads['url']) . basename($files[1]);
            $wpdb->update($wpdb->prefix . 'sms_ticker', ['img_url'=>$url], ['id'=>10]);
            self::assertStringNotContainsString($url, TickerBoundary::feed(['race'=>9909]));
            $import = new TickerImport($this->db);
            $import->start(true);
            $import->step();
            $import->step();
            self::assertStringContainsString('unresolved_image', TickerBoundary::manager()->post(10)['issues']);
            self::assertStringNotContainsString($url, TickerBoundary::feed(['race'=>9909]));
            $attachment = wp_insert_attachment(['post_author'=>$this->actor,'post_mime_type'=>'image/png','post_title'=>'Synthetic forged metadata','post_status'=>'inherit'], $files[1], 0, true);
            self::assertIsInt($attachment);
            update_post_meta($attachment, '_cor_mgmt_race', 9909);
            self::assertFalse(TickerBoundary::allowed($this->actor, 9909, 'attachment:' . $attachment));
            $this->expectExceptionMessage('foreign_ticker_image');
            TickerBoundary::manager()->create($this->actor, 9909, 'image', 'Forged image metadata', wp_get_attachment_url($attachment), $attachment, 0, str_repeat('5', 32));
        } finally {
            if (is_int($attachment) && $attachment > 0) { wp_delete_attachment($attachment, true); }
            foreach ($files as $path) { if (is_file($path)) { unlink($path); } }
        }
    }
    public function testLegacyAndNativeAssetsRemainDistinctInEitherRegistrationOrder(): void
    {
        $scripts = $GLOBALS['wp_scripts'];
        $styles = $GLOBALS['wp_styles'];
        try {
            foreach ([true,false] as $legacyFirst) {
                $GLOBALS['wp_scripts'] = new \WP_Scripts();
                $GLOBALS['wp_styles'] = new \WP_Styles();
                if ($legacyFirst) { h3_mgmt_enqueue(); }
                TickerBoundary::form(['race'=>9909]);
                if (!$legacyFirst) { h3_mgmt_enqueue(); }
                foreach ([wp_scripts(),wp_styles()] as $registry) {
                    self::assertContains('h3-mgmt-ticker', $registry->queue);
                    self::assertContains('cor-mgmt-ticker', $registry->queue);
                    $legacy = $registry->registered['h3-mgmt-ticker']->src;
                    $native = $registry->registered['cor-mgmt-ticker']->src;
                    self::assertIsString($legacy);
                    self::assertIsString($native);
                    self::assertNotSame($legacy, $native);
                    self::assertStringContainsString('/cor-mgmt-ticker.', $native);
                    $copies = array_filter($registry->queue, static fn(string $handle):bool => $registry->registered[$handle]->src === $native);
                    self::assertCount(1, $copies);
                }
            }
        } finally {
            $GLOBALS['wp_scripts'] = $scripts;
            $GLOBALS['wp_styles'] = $styles;
        }
    }
}
