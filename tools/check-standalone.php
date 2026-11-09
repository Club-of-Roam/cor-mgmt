<?php

/** ZIP lifecycle acceptance using only a guarded disposable WordPress installation. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$completed = false;
register_shutdown_function(static function () use (&$completed): void {
    // A WordPress wp_die()/exit must never turn an unfinished check into a green CI job.
    if (!$completed) { fwrite(STDERR, "Standalone acceptance did not complete.\n"); exit(1); }
});
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . "\n");
    exit(1);
});
$core = realpath((string) getenv('COR_TEST_STANDALONE_CORE'));
$database = (string) getenv('COR_TEST_DB_NAME');
$host = (string) getenv('COR_TEST_DB_HOST');
if (!$core || !is_file($core . '/.cor-mgmt-test-fixture')
    || trim(file_get_contents($core . '/.cor-mgmt-test-fixture')) !== 'synthetic-wordpress-only'
    || is_file($core . '/wp-config.php') || !is_file($core . '/wp-content/themes/twentytwentyfive/style.css')
    || !preg_match('/^cor_mgmt_test_[a-z0-9_]+$/D', $database)
    || !preg_match('/^127\.0\.0\.1:([0-9]{4,5})$/D', $host, $port) || (int)$port[1] === 3306
    || getenv('COR_TEST_DB_USER') !== 'cor_mgmt_runner' || !getenv('COR_TEST_DB_PASSWORD')) {
    throw new RuntimeException('An isolated synthetic database and marked standard-theme core fixture are required.');
}
$child = ($argv[1] ?? '') === '--exercise';
$directory = $child ? realpath($argv[2] ?? '') : sys_get_temp_dir() . '/cor-mgmt-standalone-' . bin2hex(random_bytes(12));
if (!$directory || !str_starts_with(basename($directory), 'cor-mgmt-standalone-')) { throw new RuntimeException('Invalid standalone directory.'); }
if (!$child) {
    mkdir($directory, 0700);
    file_put_contents($directory . '/.synthetic', "standalone-only\n");
    mkdir($directory . '/content/plugins', 0700, true);
    mkdir($directory . '/content/themes', 0700, true);
    $copy = static function (string $source, string $target) use (&$copy): void {
        if (is_link($source)) { throw new RuntimeException('No fixture symlinks allowed.'); }
        if (is_dir($source)) {
            mkdir($target, 0700);
            foreach (new DirectoryIterator($source) as $entry) { if (!$entry->isDot()) { $copy($entry->getPathname(), $target . '/' . $entry->getFilename()); } }
        } elseif (!copy($source, $target)) { throw new RuntimeException('Cannot copy standard theme.'); }
    };
    $copy($core . '/wp-content/themes/twentytwentyfive', $directory . '/content/themes/twentytwentyfive');
    $zip = new ZipArchive();
    if ($zip->open($argv[1] ?? '') !== true) { throw new RuntimeException('Expected a built plugin ZIP.'); }
    for ($i=0; $i<$zip->numFiles; ++$i) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || !str_starts_with($name, 'cor-mgmt/') || str_contains($name, '..') || str_contains($name, '\\')) { throw new RuntimeException('Unsafe ZIP entry.'); }
    }
    if (!$zip->extractTo($directory . '/content/plugins')) { throw new RuntimeException('Cannot extract package.'); }
    $zip->close();
    file_put_contents($directory . '/prefix', 'cortest_standalone_' . bin2hex(random_bytes(6)) . '_');
}
if (!is_file($directory . '/.synthetic') || trim(file_get_contents($directory . '/.synthetic')) !== 'standalone-only') { throw new RuntimeException('Fixture marker mismatch.'); }
$table_prefix = trim(file_get_contents($directory . '/prefix'));
if (!preg_match('/^cortest_standalone_[a-f0-9]{12}_$/D', $table_prefix)) { throw new RuntimeException('Invalid disposable prefix.'); }
define('ABSPATH', $core . '/');
define('WP_CONTENT_DIR', $directory . '/content');
define('WP_CONTENT_URL', 'https://example.invalid/subdirectory/wp-content');
define('WP_HOME', 'https://example.invalid/subdirectory');
define('WP_SITEURL', WP_HOME);
define('WP_DEFAULT_THEME', 'twentytwentyfive');
define('DB_NAME', $database); define('DB_HOST', $host);
define('DB_USER', getenv('COR_TEST_DB_USER')); define('DB_PASSWORD', getenv('COR_TEST_DB_PASSWORD'));
define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('WP_INSTALLING', !$child); define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true); define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISALLOW_FILE_MODS', true); define('WP_DEBUG', true); define('WP_DEBUG_LOG', false);
$_SERVER['SERVER_NAME']='example.invalid'; $_SERVER['HTTP_HOST']='example.invalid';
$_SERVER['REQUEST_URI']='/subdirectory/'; $_SERVER['SERVER_PROTOCOL']='HTTP/1.1'; $_SERVER['REQUEST_METHOD']='GET';
// A must-use fixture plugin installs transport guards before the packaged plugin is loaded.
if (!$child) {
    mkdir(WP_CONTENT_DIR . '/mu-plugins', 0700);
    file_put_contents(WP_CONTENT_DIR . '/mu-plugins/fixture-guards.php', <<<'PHP'
<?php
add_filter('pre_wp_mail',static function(){if(wp_installing()){return true;}throw new RuntimeException('Unexpected mail in standalone acceptance.');},PHP_INT_MAX);
add_filter('pre_http_request',static function(){return new WP_Error('synthetic_http_blocked');},PHP_INT_MAX);
add_filter('wp_hash_password_options',static fn(array $options):array=>['cost'=>4]+$options);
PHP);
}
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$entry = 'cor-mgmt/h3-management.php';
try {
    if (!$child) {
        wp_install('Synthetic standalone acceptance', 'standalone_admin', 'admin@example.invalid', false, '', bin2hex(random_bytes(20)));
        wp_installing(false);
        wp_set_current_user((int)get_user_by('login','standalone_admin')->ID);
        $result = activate_plugin($entry, '', false, false);
        $assert(!is_wp_error($result), 'Package activation failed.');
        $assert(get_option('h3_mgmt_db_version') === '2.8', 'Fresh schema missing.');
        $process = proc_open([PHP_BINARY, __FILE__, '--exercise', $directory], [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $pipes, dirname(__DIR__), getenv());
        if (!is_resource($process)) { throw new RuntimeException('Cannot start fresh request.'); }
        fclose($pipes[0]);
        $assert(proc_close($process) === 0, 'Standalone request failed.');
        $assert(is_file($directory . '/passed'), 'Standalone request exited before its final checks.');
        $completed = true;
    } else {
        error_reporting(E_ALL);
        set_error_handler(static function (int $severity,string $message,string $file,int $line): bool { if(error_reporting()&$severity){throw new ErrorException($message,0,$severity,$file,$line);}return false; });
        wp_set_current_user((int)get_user_by('login','standalone_admin')->ID);
        $assert(is_plugin_active($entry), 'Plugin did not survive a fresh request.');
        $assert(wp_get_theme()->get_stylesheet() === 'twentytwentyfive' && !wp_get_theme()->errors(), 'Standard theme unavailable.');
        $assert(count(get_plugins()) === 1, 'Unexpected optional plugins.');
        $assert(!class_exists('PHPUnit\Framework\TestCase'), 'Development autoloader leaked into package.');
        $assert(\Cor\Mgmt\WordPress\Lifecycle::ready(), 'Standalone runtime not ready.');
        $db = new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
        $assert((new \Cor\Mgmt\Infrastructure\WordPress\TickerImport($db))->status()['state'] === 'native', 'Fresh ticker requires cor-ticker.');
        $wpdb->insert($wpdb->prefix.'h3_mgmt_races',['id'=>91,'name'=>'Synthetic standalone event','setting'=>'{"status":1,"num_teammember_min":1,"num_teammember_max":3}']);
        $_SERVER['REQUEST_METHOD']='POST';
        $teamPage = 0;
        foreach(['team','teams','results','ticker','map'] as $kind){
            $_POST=['race_id'=>'91','kind'=>$kind,'page_operation'=>'create','cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-pages')];
            $id=\Cor\Mgmt\WordPress\SitePages::submit();
            if($kind==='team'){$teamPage=$id;}
            $assert($id===\Cor\Mgmt\WordPress\SitePages::submit(),'Duplicate setup page.');
            $assert(get_post_status($id)==='draft','Setup published content without review.');
            wp_update_post(['ID'=>$id,'post_status'=>'publish']);
            $assert(str_contains(\Cor\Mgmt\WordPress\SitePages::url(91,$kind),'/subdirectory/'),'Subdirectory page link lost.');
            wp_set_current_user(0);
            $html=do_shortcode(get_post($id)->post_content);
            $assert(!str_contains($html,'[h3-'),'Public shortcode not registered.');
            wp_set_current_user((int)get_user_by('login','standalone_admin')->ID);
        }
        $_POST=[];$_SERVER['REQUEST_METHOD']='GET';
        wp_set_current_user(0);
        wp(['page_id'=>$teamPage]);
        ob_start();
        try{include get_page_template();$document=(string)ob_get_contents();}finally{ob_end_clean();}
        $assert(str_contains($document,'<body')&&str_contains($document,'wp-login.php'),'Standard theme did not render the guest portal.');
        $assert(!str_contains($document,'[h3-team-dashboard'),'Theme left shortcode unprocessed.');
        wp_set_current_user((int)get_user_by('login','standalone_admin')->ID);
        $before=$wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'h3_mgmt_races');
        deactivate_plugins($entry,false);
        $assert(!is_plugin_active($entry),'Deactivation failed.');
        $assert(!is_wp_error(activate_plugin($entry,'',false,false)),'Reactivation failed.');
        $assert($before===$wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'h3_mgmt_races'),'Reactivation changed event data.');
        uninstall_plugin($entry);
        $assert($before===$wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'h3_mgmt_races'),'Uninstall deleted event data.');
        $assert(!is_wp_error(activate_plugin($entry,'',false,false)),'Reinstall failed.');
        $assert(file_put_contents($directory . '/passed', "passed\n") !== false, 'Cannot record completed acceptance.');
        $completed = true;
        echo "Standalone ZIP: fresh activation, standard theme, five page roles, replay, public rendering, subdirectory, deactivation, reactivation and data-preserving uninstall/reinstall passed.\n";
    }
} finally {
    if (!$child) {
        // Only this process's random synthetic prefix and marked temporary content are removed.
        foreach($wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table_prefix).'%')) as $table){$wpdb->query($wpdb->prepare('DROP TABLE %i',$table));}
        if(realpath($directory)!==false && str_starts_with(basename($directory),'cor-mgmt-standalone-') && is_file($directory.'/.synthetic')){
            $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($entries as $entry){if($entry->isDir()&&!$entry->isLink()){rmdir($entry->getPathname());}else{unlink($entry->getPathname());}}rmdir($directory);
        }
    }
}
