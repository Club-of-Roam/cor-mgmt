<?php

declare(strict_types=1);

// Separate process from Characterization: all functions below are real WordPress APIs.
$root = realpath((string) getenv('COR_TEST_WP_ROOT'));
$database = (string) getenv('COR_TEST_DB_NAME');
$host = (string) getenv('COR_TEST_DB_HOST');
$user = (string) getenv('COR_TEST_DB_USER');
if (!$root || !is_file($root . '/.cor-mgmt-test-fixture')
    || trim(file_get_contents($root . '/.cor-mgmt-test-fixture')) !== 'synthetic-wordpress-only'
    || is_file($root . '/wp-config.php')
    || !preg_match('/^cor_mgmt_test_[a-z0-9_]+$/D', $database)
    || !preg_match('/^127\.0\.0\.1:(\d+)$/D', $host, $port)
    || (int) $port[1] === 3306 || $user !== 'cor_mgmt_runner'
    || !getenv('COR_TEST_DB_PASSWORD')) {
    throw new RuntimeException('An explicitly isolated WordPress fixture and restricted test database are required.');
}
foreach (['plugins', 'mu-plugins', 'themes'] as $folder) {
    if (glob($root . '/wp-content/' . $folder . '/*')) {
        throw new RuntimeException('The WordPress fixture must not contain other plugins or themes.');
    }
}
define('ABSPATH', $root . '/');
define('WP_CONTENT_DIR', $root . '/wp-content');
define('DB_NAME', $database);
define('DB_USER', $user);
define('DB_PASSWORD', getenv('COR_TEST_DB_PASSWORD'));
define('DB_HOST', $host);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('WP_INSTALLING', true);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('DISALLOW_FILE_MODS', true);
$browserBase = (string) getenv('COR_TEST_BROWSER_BASE');
if ($browserBase !== '' && (!preg_match('~^http://127\.0\.0\.1:[1-9][0-9]{3,4}$~D', $browserBase) || strlen((string) getenv('COR_TEST_BROWSER_TOKEN')) < 32)) {
    throw new RuntimeException('Invalid synthetic browser origin.');
}
define('WP_HOME', $browserBase ?: 'https://example.invalid');
define('WP_SITEURL', WP_HOME);
// Core normally derives this from the installed siteurl. A fresh fixture has no
// options yet while wp-settings.php initializes constants, so define it explicitly.
define('WP_CONTENT_URL', WP_HOME . '/wp-content');
define('WP_DEBUG', false);
define('WP_CACHE', false);
define('WP_DEFAULT_THEME', 'cor-test-no-theme');
$_SERVER['HTTP_HOST'] = $browserBase !== '' ? substr($browserBase, 7) : 'example.invalid';
$_SERVER['REQUEST_URI'] = $browserBase !== '' ? ($_SERVER['REQUEST_URI'] ?? '/') : '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$table_prefix = 'cortest_';
require ABSPATH . 'wp-settings.php';

// Low bcrypt cost only inside this guarded synthetic fixture; no application passwords.
add_filter('wp_hash_password_options', static fn (array $options): array => ['cost' => 4] + $options);
$GLOBALS['cor_test_mail'] = [];
add_filter('pre_wp_mail', static function ($result, $attributes) {
    $GLOBALS['cor_test_mail'][] = $attributes;
    return true;
}, PHP_INT_MAX, 2);
add_filter('pre_http_request', static fn () => new WP_Error('cor_test_http_blocked', 'No external HTTP in reference tests.'), PHP_INT_MAX);
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if (!is_blog_installed()) {
    wp_install('Synthetic plugin reference', 'synthetic_admin', 'admin@example.invalid', false, '', bin2hex(random_bytes(20)));
}
wp_installing(false);

// Build the real fresh schema. A stale or partial legacy fixture is intentionally rejected.
global $wpdb;
if (!defined('COR_TEST_PARTICIPATION_WORKER')) {
(new \Cor\Mgmt\Infrastructure\WordPress\Schema\Installer(
    new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb)
))->fresh();
}
// Shared ticker storage is still an optional integration pending block 9.
if ($wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sms_ticker (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, send_from varchar(80), msg text, type int, img_url text, img longtext, timestamp varchar(40), race_id bigint unsigned) ENGINE=InnoDB") === false) {
    throw new RuntimeException('Cannot create synthetic ticker fixture.');
}

// Loading the real plugin is strict too; no deprecation baseline is suppressed.
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line): bool {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
    return false;
});
$_COOKIE['client_token'] = 'synthetic-cookie-no-provider';
require dirname(__DIR__, 2) . '/h3-management.php';
restore_error_handler();
$activation = defined('COR_TEST_PARTICIPATION_WORKER') ? \Cor\Mgmt\WordPress\Lifecycle::ready() : \Cor\Mgmt\WordPress\Lifecycle::activate();
if (is_wp_error($activation)) { throw new RuntimeException('Synthetic runtime activation failed.'); }
// Core init already ran while installing the empty fixture; initialize this plugin once.
$GLOBALS['h3_mgmt']->init();

$GLOBALS['cor_test_mail'] = [];
// WordPress changes error_reporting while loading; runtime reference tests stay strict.
error_reporting(E_ALL);
