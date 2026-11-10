<?php

/** Synthetic-only UI fixture. Never load this tool from an application web root. */
declare(strict_types=1);
$server = PHP_SAPI === 'cli-server';
if (!$server && PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$token = (string) getenv('COR_TEST_BROWSER_TOKEN');
$base = (string) getenv('COR_TEST_BROWSER_BASE');
if (strlen($token) < 32 || !preg_match('~^http://127\.0\.0\.1:[1-9][0-9]{3,4}$~D', $base)) { exit(2); }
if ($server && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || !is_string($_COOKIE['cor_test_browser'] ?? null) || !hash_equals($token, $_COOKIE['cor_test_browser']))) { http_response_code(403); exit; }
if ($server) { ob_start(); }
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
        if ($db->scalar($db->prepare('SELECT id FROM %i WHERE id=880090', $db->table('h3_mgmt_races'))) !== null) { throw new RuntimeException('Ticker browser fixture is already in use.'); }
        $db->setOption('cor_test_ticker_browser_prior', json_encode($db->option('cor_mgmt_ticker_transition'), JSON_THROW_ON_ERROR));
        (new \Cor\Mgmt\Infrastructure\WordPress\TickerImport($db))->start(true);
        $wpdb->insert($db->table('h3_mgmt_races'), ['id'=>880090,'name'=>'Synthetic browser ticker','setting'=>wp_json_encode(['status'=>1,'liveticker'=>1,'liveticker_front'=>1,'num_teammember_min'=>1,'dis_fee'=>1,'dis_waiver'=>1,'dis_shirt_size'=>1,'dis_mobile_inf'=>1])]);
        $wpdb->insert($db->table('h3_mgmt_teams'), ['id'=>880042,'race_id'=>880090,'route_id'=>880007,'complete'=>1,'team_name'=>'Synthetic browser ticker team']);
        $wpdb->insert($db->table('h3_mgmt_teammates'), ['team_id'=>880042,'user_id'=>$ids['member'],'paid'=>1,'waiver'=>1]);
        $wpdb->insert($db->table('h3_mgmt_routes'), ['id'=>880007,'race_id'=>880090,'name'=>'Synthetic route A','color_code'=>'b00020']);
        $wpdb->insert($db->table('h3_mgmt_routes'), ['id'=>880008,'race_id'=>880090,'name'=>'Synthetic route B','color_code'=>'005a9c']);
        $wpdb->insert($db->table('h3_mgmt_teams'), ['id'=>880043,'race_id'=>880090,'route_id'=>880008,'complete'=>1,'team_name'=>'Other synthetic ticker team']);
        $wpdb->insert($db->table('h3_mgmt_teammates'), ['team_id'=>880043,'user_id'=>$ids['other'],'paid'=>1,'waiver'=>1]);
        echo json_encode($ids, JSON_THROW_ON_ERROR);
        exit;
    }
    if (($argv[1] ?? '') !== 'cleanup') { exit(2); }
    $tickerPosts=$db->rows($db->prepare('SELECT id FROM %i WHERE race_id=880090', $db->table('cor_mgmt_ticker_posts')));
    foreach ($tickerPosts as $post) { $db->execute($db->prepare('DELETE FROM %i WHERE post_id=%d', $db->table('cor_mgmt_ticker_history'), (int)$post['id'])); }
    $db->execute($db->prepare('DELETE FROM %i WHERE race_id=880090', $db->table('cor_mgmt_ticker_posts')));
    $attachments=$db->rows($db->prepare("SELECT post_id FROM %i WHERE meta_key='_cor_mgmt_race' AND meta_value='880090'", $wpdb->postmeta));
    foreach ($attachments as $attachment) { wp_delete_attachment((int)$attachment['post_id'],true); }
    $db->execute($db->prepare('DELETE FROM %i WHERE team_id=880042', $db->table('h3_mgmt_teammates')));
    $db->execute($db->prepare('DELETE FROM %i WHERE team_id=880043', $db->table('h3_mgmt_teammates')));
    $db->execute($db->prepare('DELETE FROM %i WHERE id=880042 AND race_id=880090', $db->table('h3_mgmt_teams')));
    $db->execute($db->prepare('DELETE FROM %i WHERE id=880043 AND race_id=880090', $db->table('h3_mgmt_teams')));
    $db->execute($db->prepare('DELETE FROM %i WHERE race_id=880090', $db->table('h3_mgmt_routes')));
    $db->execute($db->prepare('DELETE FROM %i WHERE id=880090', $db->table('h3_mgmt_races')));
    $prior=json_decode($db->option('cor_test_ticker_browser_prior') ?? 'null',true);
    if (is_string($prior)) { $db->setOption('cor_mgmt_ticker_transition',$prior); }
    else { delete_option('cor_mgmt_ticker_transition'); }
    delete_option('cor_test_ticker_browser_prior');
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
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/assets/ticker.js') {
    header('Content-Type: text/javascript; charset=UTF-8');
    readfile(dirname(__DIR__) . '/js/cor-mgmt-ticker.js');
    exit;
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/assets/ticker.css') {
    header('Content-Type: text/css; charset=UTF-8'); readfile(dirname(__DIR__) . '/css/cor-mgmt-ticker.css'); exit;
}
$assetPath=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
$leafletAssets=['/assets/leaflet.js'=>'leaflet.js','/assets/leaflet.css'=>'leaflet.css','/assets/images/marker-icon.png'=>'images/marker-icon.png','/assets/images/marker-icon-2x.png'=>'images/marker-icon-2x.png','/assets/images/marker-shadow.png'=>'images/marker-shadow.png','/assets/images/layers.png'=>'images/layers.png','/assets/images/layers-2x.png'=>'images/layers-2x.png'];
if (is_string($assetPath) && isset($leafletAssets[$assetPath])) {
    $name=$leafletAssets[$assetPath];
    header('Content-Type: ' . (str_ends_with($name,'.js') ? 'text/javascript' : (str_ends_with($name,'.css') ? 'text/css' : 'image/png')));
    readfile(dirname(__DIR__) . '/lib/leaflet/' . $name); exit;
}
$uploadPath=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
if (is_string($uploadPath) && str_starts_with($uploadPath,'/wp-content/uploads/')) {
    $path=realpath(WP_CONTENT_DIR . '/uploads/' . substr(rawurldecode($uploadPath),strlen('/wp-content/uploads/')));
    $root=realpath(WP_CONTENT_DIR . '/uploads');
    if (!$path || !$root || !str_starts_with($path,$root . DIRECTORY_SEPARATOR) || !is_file($path) || !($image=@getimagesize($path))) { http_response_code(404); exit; }
    header('Content-Type: ' . $image['mime']); readfile($path); exit;
}
$role = is_string($_COOKIE['cor_test_actor'] ?? null) ? $_COOKIE['cor_test_actor'] : '';
$user = in_array($role, ['admin','member','other'], true) ? get_user_by('login', $prefix . $role) : false;
wp_set_current_user($user ? $user->ID : 0);
register_shutdown_function(static function (): void { if ($GLOBALS['cor_test_mail'] !== []) { throw new RuntimeException('Unexpected WordPress mail call.'); } });
if (($_POST['action'] ?? '') === 'cor_mgmt_ticker') {
    \Cor\Mgmt\WordPress\TickerBoundary::post();
    exit;
}
if (($_POST['action'] ?? '') === 'cor_mgmt_broadcast_step') {
    \Cor\Mgmt\WordPress\BroadcastAdmin::ajax();
    exit;
}
nocache_headers();
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; form-action 'self'; img-src 'self' https://tile.openstreetmap.org data:; frame-src https://www.youtube-nocookie.com https://www.openstreetmap.org");
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Synthetic communication acceptance</title><link rel="stylesheet" href="/assets/ticker.css"><style>body{font:18px Arial;line-height:1.5;color:#111;background:#fff;max-width:1000px;margin:2em auto}a{color:#004f91}input,textarea,select,button{font:inherit;max-width:95%}details{padding:1em;border:1px solid #777}summary{cursor:pointer}:focus{outline:3px solid #005a9c;outline-offset:3px}</style></head><body><main>';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$page = $_GET['page'] ?? '';
if ($path === '/board') { echo \Cor\Mgmt\WordPress\ExchangeBoundary::shortcode(); }
elseif ($path === '/ticker') { echo '<h1>Web ticker</h1>' . \Cor\Mgmt\WordPress\TickerBoundary::form(['race'=>880090]) . \Cor\Mgmt\WordPress\TickerBoundary::feed(['race'=>880090]); }
elseif ($path === '/ticker-map') { echo '<h1>Event positions</h1><link rel="stylesheet" href="/assets/leaflet.css"><style>.cor-ticker-map{height:500px}</style>' . \Cor\Mgmt\WordPress\TickerBoundary::feed(['race'=>880090,'max'=>100],true); }
elseif ($path === '/ticker-admin' || $page === 'cor-mgmt-ticker') { $_GET['race']='880090'; \Cor\Mgmt\WordPress\TickerBoundary::page(); }
elseif ($path === '/archives') { \Cor\Mgmt\WordPress\ArchiveBoundary::page(); }
elseif ($page === 'cor-mgmt-exchange' || $path === '/moderation') { \Cor\Mgmt\WordPress\ExchangeBoundary::page(); }
elseif ($page === 'cor-mgmt-communication-report' || $path === '/report') { \Cor\Mgmt\WordPress\CommunicationReport::page(); }
else { \Cor\Mgmt\WordPress\BroadcastAdmin::page(); }
echo '</main><script src="/assets/broadcast.js"></script>';
if ($path === '/ticker-map') { echo '<script src="/assets/leaflet.js"></script>'; }
echo '<script src="/assets/ticker.js"></script></body></html>';
if ($GLOBALS['cor_test_mail'] !== []) { throw new RuntimeException('Unexpected WordPress mail call.'); }
