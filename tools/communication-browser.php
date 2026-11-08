<?php

/** Synthetic-only UI fixture. Never load this tool from an application web root. */
declare(strict_types=1);
$server = PHP_SAPI === 'cli-server';
if (!$server && PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$token = (string) getenv('COR_TEST_BROWSER_TOKEN');
$base = (string) getenv('COR_TEST_BROWSER_BASE');
if (strlen($token) < 32 || !preg_match('~^http://127\.0\.0\.1:[1-9][0-9]{3,4}$~D', $base)) { exit(2); }
if ($server && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || !is_string($_COOKIE['cor_test_browser'] ?? null) || !hash_equals($token, $_COOKIE['cor_test_browser']))) { http_response_code(403); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
if ($server) { define('COR_TEST_PARTICIPATION_WORKER', true); }
require dirname(__DIR__) . '/tests/Integration/bootstrap.php';
global $wpdb;
$db = new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
$prefix = 'communication_browser_';
if (!$server) {
    if (($argv[1] ?? '') === 'prepare') {
        $ids = [];
        foreach (['admin','member','other'] as $role) {
            if (get_user_by('login', $prefix . $role)) { throw new RuntimeException('Browser fixture is already in use.'); }
            $id = wp_insert_user(['user_login' => $prefix . $role, 'user_email' => $prefix . $role . '@example.invalid', 'user_pass' => bin2hex(random_bytes(32)), 'first_name' => 'Synthetic ' . $role, 'display_name' => 'Synthetic ' . $role, 'role' => $role === 'admin' ? 'administrator' : 'subscriber']);
            if (!is_int($id)) { throw new RuntimeException('Synthetic user creation failed.'); }
            $ids[$role] = $id;
        }
        echo json_encode($ids, JSON_THROW_ON_ERROR);
        exit;
    }
    if (($argv[1] ?? '') !== 'cleanup') { exit(2); }
    foreach (['admin','member','other'] as $role) {
        $user = get_user_by('login', $prefix . $role);
        if (!$user) { continue; }
        $campaigns = $db->rows($db->prepare('SELECT id FROM %i WHERE actor_id=%d', $db->table('cor_mgmt_broadcasts'), $user->ID));
        foreach ($campaigns as $campaign) {
            $id = (int) $campaign['id'];
            $messages = $db->rows($db->prepare('SELECT queue_id FROM %i WHERE broadcast_id=%d', $db->table('cor_mgmt_broadcast_recipients'), $id));
            foreach ($messages as $message) {
                foreach (['cor_mgmt_mail_envelopes','cor_mgmt_mail_history'] as $table) { $db->execute($db->prepare('DELETE FROM %i WHERE message_id=%d', $db->table($table), (int) $message['queue_id'])); }
                $db->execute($db->prepare('DELETE FROM %i WHERE id=%d AND race_id=0', $db->table('cor_mgmt_mail_queue'), (int) $message['queue_id']));
            }
            foreach (['cor_mgmt_broadcast_recipients','cor_mgmt_broadcast_history'] as $table) { $db->execute($db->prepare('DELETE FROM %i WHERE broadcast_id=%d', $db->table($table), $id)); }
            $db->execute($db->prepare('DELETE FROM %i WHERE id=%d', $db->table('cor_mgmt_broadcasts'), $id));
        }
        $db->execute($db->prepare('DELETE FROM %i WHERE actor_id=%d', $db->table('cor_mgmt_exchange_history'), $user->ID));
        $db->execute($db->prepare('DELETE FROM %i WHERE user_id=%d', $db->table('h3_mgmt_xchange'), $user->ID));
        $db->wpdb->delete($db->wpdb->usermeta, ['user_id' => $user->ID]);
        $db->wpdb->delete($db->wpdb->users, ['ID' => $user->ID]);
    }
    echo 'Synthetic browser data removed.';
    exit;
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/assets/broadcast.js') {
    header('Content-Type: text/javascript; charset=UTF-8');
    readfile(dirname(__DIR__) . '/js/h3-mgmt-broadcast.js');
    exit;
}
$role = is_string($_COOKIE['cor_test_actor'] ?? null) ? $_COOKIE['cor_test_actor'] : '';
$user = in_array($role, ['admin','member','other'], true) ? get_user_by('login', $prefix . $role) : false;
wp_set_current_user($user ? $user->ID : 0);
register_shutdown_function(static function (): void { if ($GLOBALS['cor_test_mail'] !== []) { throw new RuntimeException('Unexpected WordPress mail call.'); } });
if (($_POST['action'] ?? '') === 'cor_mgmt_broadcast_step') {
    \Cor\Mgmt\WordPress\BroadcastAdmin::ajax();
    exit;
}
nocache_headers();
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; form-action 'self'");
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Synthetic communication acceptance</title><style>body{font:18px Arial;line-height:1.5;color:#111;background:#fff;max-width:1000px;margin:2em auto}a{color:#004f91}input,textarea,select,button{font:inherit;max-width:95%}details{padding:1em;border:1px solid #777}summary{cursor:pointer}:focus{outline:3px solid #005a9c;outline-offset:3px}</style></head><body><main>';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$page = $_GET['page'] ?? '';
if ($path === '/board') { echo \Cor\Mgmt\WordPress\ExchangeBoundary::shortcode(); }
elseif ($page === 'cor-mgmt-exchange' || $path === '/moderation') { \Cor\Mgmt\WordPress\ExchangeBoundary::page(); }
elseif ($page === 'cor-mgmt-communication-report' || $path === '/report') { \Cor\Mgmt\WordPress\CommunicationReport::page(); }
else { \Cor\Mgmt\WordPress\BroadcastAdmin::page(); }
echo '</main><script src="/assets/broadcast.js"></script></body></html>';
if ($GLOBALS['cor_test_mail'] !== []) { throw new RuntimeException('Unexpected WordPress mail call.'); }
