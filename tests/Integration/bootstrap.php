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
define('WP_HOME', 'https://example.invalid');
define('WP_SITEURL', 'https://example.invalid');
define('WP_DEBUG', false);
define('WP_CACHE', false);
define('WP_DEFAULT_THEME', 'cor-test-no-theme');
$_SERVER['HTTP_HOST'] = 'example.invalid';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$table_prefix = 'cortest_';
require ABSPATH . 'wp-settings.php';

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

// Only known load-time deprecations are baselined; runtime warnings remain test failures.
$GLOBALS['cor_test_load_deprecations'] = [];
set_error_handler(static function ($severity, $message, $file, $line): bool {
    $normalized = str_replace('\\', '/', $file);
    $known = ($severity === E_DEPRECATED) && (
        (str_ends_with($normalized, '/includes/class-h3-mgmt-utilities.php') && str_contains($message, 'Implicitly marking parameter'))
        || (str_ends_with($normalized, '/includes/class-h3-mgmt-xchange.php') && str_contains($message, '${var}'))
    );
    if ($known) { $GLOBALS['cor_test_load_deprecations'][] = [$message, basename($file), $line]; }
    return $known;
});
$_COOKIE['client_token'] = 'synthetic-cookie-no-provider';
require dirname(__DIR__, 2) . '/h3-management.php';
restore_error_handler();
// Core init already ran while installing the empty fixture; initialize this plugin once.
$GLOBALS['h3_mgmt']->init();

// Fixtures deliberately use only columns needed by these reference paths, not v2 DDL.
global $wpdb;
foreach ([
    'h3_mgmt_xchange' => 'id bigint unsigned AUTO_INCREMENT PRIMARY KEY, user_id bigint unsigned NOT NULL, message text NOT NULL, time timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'h3_mgmt_auto_responses' => 'id bigint unsigned AUTO_INCREMENT PRIMARY KEY, action varchar(80), language varchar(8), switch tinyint, subject text, message text',
    'h3_mgmt_invitations' => 'id bigint unsigned AUTO_INCREMENT PRIMARY KEY, team_id bigint unsigned, email varchar(200), code bigint',
    'h3_mgmt_races' => 'id bigint unsigned PRIMARY KEY, name varchar(100), setting longtext, information_text longtext, active tinyint',
    'h3_mgmt_teams' => 'id bigint unsigned PRIMARY KEY, race_id bigint unsigned, route_id bigint unsigned, complete tinyint, team_name varchar(100)',
    'h3_mgmt_teammates' => 'id bigint unsigned AUTO_INCREMENT PRIMARY KEY, team_id bigint unsigned, user_id bigint unsigned, paid tinyint, waiver tinyint',
    'h3_mgmt_routes' => 'id bigint unsigned PRIMARY KEY, race_id bigint unsigned, name varchar(100), max_teams int',
    'h3_mgmt_sponsors' => 'id bigint unsigned PRIMARY KEY, team_id bigint unsigned, donation int, paid tinyint, var_show tinyint',
    'sms_ticker' => 'id bigint unsigned AUTO_INCREMENT PRIMARY KEY, send_from varchar(80), msg text, type int, img_url text, img longtext, timestamp varchar(40), race_id bigint unsigned',
] as $suffix => $columns) {
    if ($wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$suffix} ($columns) ENGINE=InnoDB") === false) {
        throw new RuntimeException('Cannot create synthetic fixture table: ' . $suffix);
    }
}
$GLOBALS['cor_test_mail'] = [];
// WordPress changes error_reporting while loading; runtime reference tests stay strict.
error_reporting(E_ALL);
