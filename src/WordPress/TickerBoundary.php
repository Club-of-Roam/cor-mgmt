<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Domain\Ticker\Content;
use Cor\Mgmt\Infrastructure\WordPress\Ticker;
use Cor\Mgmt\Infrastructure\WordPress\TickerImport;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;

/** Native web ticker. No theme templates, SMS identity matching or write-on-read. */
final class TickerBoundary
{
    public static function manager(): Ticker
    {
        global $wpdb;
        return new Ticker(new Database($wpdb), Services::get()->clock, self::allowed(...));
    }

    public static function register(): void
    {
        add_action('admin_post_cor_mgmt_ticker', [self::class, 'post']);
        add_action('admin_post_nopriv_cor_mgmt_ticker', [self::class, 'post']);
        // Shortcodes and team templates can render after wp_head; enqueue styles in time.
        add_action('wp_enqueue_scripts', static function (): void {
            self::assets();
        });
        add_action('admin_enqueue_scripts', static function (string $hook): void {
            if ($hook === 'tools_page_cor-mgmt-ticker') {
                self::assets();
            }
        });
        add_action('admin_menu', static function (): void {
            $capability = current_user_can('manage_options') ? 'manage_options' : (current_user_can('h3_mgmt_edit_races') ? 'h3_mgmt_edit_races' : 'h3_mgmt_edit_own_races');
            if (current_user_can($capability)) {
                add_management_page(__('Web ticker', 'h3-mgmt'), __('Web ticker', 'h3-mgmt'), $capability, 'cor-mgmt-ticker', [self::class, 'page']);
            }
        });
        add_action('admin_notices', static function (): void {
            if (current_user_can('manage_options') && !self::manager()->native()) {
                echo '<div class="notice notice-warning"><p><a href="' . esc_url(admin_url('tools.php?page=cor-mgmt-ticker')) . '">' . esc_html__('Web ticker setup or historical transfer is required. Open ticker tools.', 'h3-mgmt') . '</a></p></div>';
            }
        });
    }

    public static function allowed(int $actor, int $race, string $action): bool
    {
        global $wpdb, $h3_mgmt_races, $h3_mgmt_teams;
        if ($actor < 1 || $actor !== get_current_user_id() || !is_user_logged_in()) {
            return false;
        }
        if ($action === 'review' && current_user_can('manage_options')) {
            return true;
        }
        if ($race < 1 || (\H3_MGMT_Security::archived($race) && $action !== 'review')) {
            return false;
        }
        $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE id=%d', $wpdb->prefix . 'h3_mgmt_races', $race));
        if (!$exists) {
            return false;
        }
        $organizer = \H3_MGMT_Security::can_object('races', 'edit', $race);
        if ($action === 'moderate' || $action === 'review') {
            return $organizer;
        }
        $settings = self::settings($race);
        if (empty($settings['liveticker'])) {
            return false;
        }
        if (str_starts_with($action, 'attachment:')) {
            $id = (int) substr($action, strlen('attachment:'));
            return (int) get_post_field('post_author', $id) === $actor && (int) get_post_meta($id, '_cor_mgmt_race', true) === $race
                && wp_attachment_is_image($id) && \H3_MGMT_Media::safe_image_path((string) get_attached_file($id)) !== '';
        }
        if ($action === 'comment' || $organizer) {
            return true;
        }
        $team = (int) $h3_mgmt_teams->user_has_team($race);
        return $team > 0 && (bool) $h3_mgmt_teams->is_complete($team);
    }

    /** @param array<string,mixed>|string $attributes */
    public static function race(array|string $attributes): int
    {
        global $h3_mgmt_races;
        $value = is_array($attributes) ? ($attributes['race'] ?? '0') : '0';
        if (($value === 0 || $value === '0') && is_string($_GET['ticker_race'] ?? null)) {
            $value = wp_unslash($_GET['ticker_race']);
        }
        return $value === 'active' ? (int) $h3_mgmt_races->get_active_race() : (is_scalar($value) ? \H3_MGMT_Security::id($value) : 0);
    }

    private static function assets(): void
    {
        $plugin = dirname(__DIR__, 2) . '/h3-management.php';
        wp_enqueue_script('cor-mgmt-ticker', plugins_url('js/cor-mgmt-ticker.js', $plugin), [], '1', true);
        wp_enqueue_style('cor-mgmt-ticker', plugins_url('css/cor-mgmt-ticker.css', $plugin), [], '1');
        wp_enqueue_style('cor-mgmt-leaflet', plugins_url('lib/leaflet/leaflet.css', $plugin), [], '1.9.4');
    }

    public static function post(): void
    {
        try {
            self::submit(TeamBoundary::number(TeamBoundary::value('race')));
            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        } catch (\Throwable $error) {
            Services::get()->logger->error('ticker_post_rejected', $error);
            wp_die(esc_html__('Submission rejected. Return to the form to check event access and content.', 'h3-mgmt'), '', ['response' => 400, 'back_link' => true]);
        }
    }

    private static function key(): string
    {
        return bin2hex(Services::get()->random->bytes(16));
    }

    /** @return array<string,mixed> */
    private static function settings(int $race): array
    {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare('SELECT setting FROM %i WHERE id=%d', $wpdb->prefix . 'h3_mgmt_races', $race));
        $value = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($value) ? $value : [];
    }

    private static function notice(int $race, int $index, string $fallback = ''): string
    {
        global $wpdb, $h3_mgmt_utilities;
        $raw = $wpdb->get_var($wpdb->prepare('SELECT information_text FROM %i WHERE id=%d', $wpdb->prefix . 'h3_mgmt_races', $race));
        $texts = is_string($raw) ? json_decode($raw, true) : null;
        $language = $h3_mgmt_utilities->user_language();
        $value = is_array($texts) ? ($texts[$index . $language] ?? null) : null;
        return is_string($value) ? stripcslashes($value) : $fallback;
    }

    public static function submit(int $race): int
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-ticker-' . $race) || TeamBoundary::value('address') !== '') {
            throw new \RuntimeException('invalid_nonce');
        }
        $kind = TeamBoundary::value('ticker_kind', 'text');
        $actor = get_current_user_id();
        if (!self::manager()->native() || !self::allowed($actor, $race, $kind === 'comment' ? 'comment' : 'write')) {
            throw new \RuntimeException('forbidden');
        }
        $request = TeamBoundary::value('request_key');
        if (!preg_match('/^[a-f0-9]{32}$/D', $request)) {
            throw new \RuntimeException('invalid_ticker_request');
        }
        $body = sanitize_textarea_field(TeamBoundary::value('message'));
        $parent = TeamBoundary::number(TeamBoundary::value('parent_id', '0'));
        $attachment = TeamBoundary::number(TeamBoundary::value('attachment_id', '0'));
        $media = TeamBoundary::value('media');
        $newAttachment = 0;
        try {
            if ($kind === 'rotate') {
                $id = TeamBoundary::number(TeamBoundary::value('post_id'));
                $row = self::manager()->post($id);
                if ((int) ($row['race_id'] ?? 0) !== $race || ((int) ($row['actor_id'] ?? 0) !== $actor && !self::allowed($actor, $race, 'moderate'))) {
                    throw new \RuntimeException('foreign_ticker_image');
                }
                $newAttachment = \H3_MGMT_Media::rotated_copy((int) ($row['attachment_id'] ?? 0), $race, TeamBoundary::number(TeamBoundary::value('angle')));
                self::manager()->replaceImage($actor, $id, TeamBoundary::number(TeamBoundary::value('revision')), $newAttachment, $request);
                return $id;
            }
            if ($kind === 'image' && !$attachment) {
                $newAttachment = $attachment = \H3_MGMT_Media::ticker_upload($race);
                $media = (string) wp_get_attachment_url($attachment);
            }
            return self::manager()->create($actor, $race, $kind, $body, $media, $attachment, $parent, $request);
        } catch (\Throwable $error) {
            if ($newAttachment > 0) {
                wp_delete_attachment($newAttachment, true);
            }
            throw $error;
        }
    }

    /** @param array<string,mixed>|string $attributes */
    public static function form(array|string $attributes = ''): string
    {
        $race = self::race($attributes);
        $parent = is_array($attributes) ? (int) ($attributes['parent'] ?? 0) : 0;
        $comment = $parent > 0 || (is_array($attributes) && ((!empty($attributes['comment']) && $attributes['comment'] !== 'false') || (!empty($attributes['comment_get']) && $attributes['comment_get'] !== 'false')));
        ob_start();
        self::assets();
        if (empty(self::settings($race)['liveticker'])) {
            echo '<p>' . wp_kses_post(self::notice($race, 23, __('Ticker submissions are disabled for this event.', 'h3-mgmt'))) . '</p>';
            return (string) ob_get_clean();
        }
        if (!self::manager()->native()) {
            echo '<p>' . esc_html__('Ticker submissions are paused until the controlled ticker setup is complete.', 'h3-mgmt') . '</p>';
            return (string) ob_get_clean();
        }
        if (!self::allowed(get_current_user_id(), $race, $comment ? 'comment' : 'write')) {
            echo '<p>' . esc_html__('Log in with an eligible event account to submit. Archived events are read-only.', 'h3-mgmt') . '</p>';
            return (string) ob_get_clean();
        }
        $uid = wp_unique_id('ticker-form-');
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="cor-ticker-form"><input type="hidden" name="action" value="cor_mgmt_ticker"><input type="hidden" name="race" value="' . esc_attr((string) $race) . '">';
        wp_nonce_field('cor-mgmt-ticker-' . $race, 'cor_mgmt_nonce', false);
        echo '<input type="hidden" name="ticker_submit" value="' . esc_attr((string) $race) . '"><input type="hidden" name="request_key" value="' . esc_attr(self::key()) . '">';
        if ($comment && !$parent) {
            echo '<p><label for="' . esc_attr($uid . 'parent') . '">' . esc_html__('Message to comment on', 'h3-mgmt') . '</label><select name="parent_id" id="' . esc_attr($uid . 'parent') . '">';
            foreach (self::manager()->feed($race, 0, 0, 1, 100) as $row) {
                echo '<option value="' . esc_attr($row['id'] ?? '') . '">' . esc_html('#' . $row['id'] . ' · ' . mb_substr($row['body'] ?? '', 0, 80)) . '</option>';
            }
            echo '</select></p>';
        } else {
            echo '<input type="hidden" name="parent_id" value="' . esc_attr((string) $parent) . '">';
        }
        if ($comment) {
            echo '<input type="hidden" name="ticker_kind" value="comment">';
        } else {
            echo '<p><label for="' . esc_attr($uid . 'kind') . '">' . esc_html__('Message type', 'h3-mgmt') . '</label><select id="' . esc_attr($uid . 'kind') . '" name="ticker_kind">';
            foreach (['text' => __('Text', 'h3-mgmt'), 'image' => __('Image', 'h3-mgmt'), 'location' => __('Location', 'h3-mgmt'), 'video' => __('Video', 'h3-mgmt')] as $kind => $label) {
                echo '<option value="' . esc_attr($kind) . '">' . esc_html($label) . '</option>';
            }
            echo '</select></p>';
        }
        echo '<p><label for="' . esc_attr($uid . 'message') . '">' . esc_html__('Message', 'h3-mgmt') . '</label><textarea id="' . esc_attr($uid . 'message') . '" name="message" maxlength="10000" rows="4">' . esc_textarea(is_string($_POST['message'] ?? null) ? wp_unslash($_POST['message']) : '') . '</textarea></p>';
        if (!$comment) {
            echo '<p><label for="' . esc_attr($uid . 'media') . '">' . esc_html__('Coordinates (latitude,longitude) or YouTube URL', 'h3-mgmt') . '</label><input id="' . esc_attr($uid . 'media') . '" name="media" type="text" maxlength="500"></p><button type="button" disabled data-ticker-location data-error="' . esc_attr__('Location unavailable. Enter coordinates manually.', 'h3-mgmt') . '">' . esc_html__('Use my current location', 'h3-mgmt') . '</button><p data-ticker-location-status role="status"></p><p><label for="' . esc_attr($uid . 'photo') . '">' . esc_html__('Image, at most 5 MB (JPEG, PNG, GIF or WebP)', 'h3-mgmt') . '</label><input id="' . esc_attr($uid . 'photo') . '" type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"></p>';
        }
        echo '<div hidden><label>' . esc_html__('Leave blank', 'h3-mgmt') . '<input name="address" tabindex="-1" autocomplete="off"></label></div><button type="submit">' . esc_html__('Send message', 'h3-mgmt') . '</button></form>';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,string|null> $row
     * @return array<string,string|null>
     */
    private static function teamContext(array $row): array
    {
        global $wpdb;
        $team = (int) $row['team_id'];
        $actor = (int) $row['actor_id'];
        if ($team < 1 && $actor < 1) {
            return [];
        }
        $db = new Database($wpdb);
        $sql = $db->prepare('SELECT t.id AS team_id,t.team_name,r.id AS route_id,r.name AS route_name,r.color_code FROM %i t LEFT JOIN %i r ON r.id=t.route_id AND r.race_id=t.race_id WHERE t.race_id=%d', $db->table('h3_mgmt_teams'), $db->table('h3_mgmt_routes'), (int) $row['race_id']);
        $sql .= $team > 0 ? $db->prepare(' AND t.id=%d', $team) : $db->prepare(' AND t.id IN (SELECT team_id FROM %i WHERE user_id=%d)', $db->table('h3_mgmt_teammates'), $actor);
        $context = $db->rows($sql . ' ORDER BY t.id LIMIT 2');
        return count($context) === 1 ? $context[0] : [];
    }

    /** @param array<string,string|null> $row */
    private static function article(array $row, bool $moderation = false): void
    {
        $id = (int) $row['id'];
        $race = (int) $row['race_id'];
        $author = __('Historical sender', 'h3-mgmt');
        if ((int) $row['actor_id'] > 0) {
            $user = get_userdata((int) $row['actor_id']);
            $author = $user ? (string) $user->display_name : __('Former participant', 'h3-mgmt');
        } elseif ((int) $row['team_id'] > 0) {
            $author = __('Team', 'h3-mgmt') . ' #' . $row['team_id'];
        }
        echo '<article id="ticker-' . esc_attr((string) $id) . '" class="cor-ticker-post"><h3>' . esc_html($author) . ' · #' . esc_html((string) $id) . '</h3>';
        $context = self::teamContext($row);
        if ($context) {
            echo '<p>' . esc_html($context['team_name'] ?? '') . ' · ' . esc_html($context['route_name'] ?? '') . '</p>';
        }
        if ((int) $row['created_at'] > 0) {
            echo '<p><time datetime="' . esc_attr(gmdate('c', (int) $row['created_at'])) . '">' . esc_html(wp_date('Y-m-d H:i', (int) $row['created_at']) ?: '') . '</time></p>';
        }
        echo '<p>' . nl2br(esc_html($row['body'] ?? '')) . '</p>';
        $media = $row['media_ref'] ?? '';
        $caption = $row['body'] ?? '';
        if ($row['kind'] === 'image') {
            $url = self::imageUrl($media, $row['source_payload'] ?? '');
            if ($url !== '') {
                echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($caption !== '' ? $caption : __('Ticker image', 'h3-mgmt')) . '" loading="lazy" decoding="async">';
                if ((int) $row['actor_id'] === get_current_user_id() && (int) $row['attachment_id'] > 0 && self::allowed(get_current_user_id(), $race, 'write')) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cor_mgmt_ticker"><input type="hidden" name="ticker_kind" value="rotate"><input type="hidden" name="race" value="' . esc_attr((string) $race) . '"><input type="hidden" name="post_id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="revision" value="' . esc_attr($row['revision'] ?? '') . '"><input type="hidden" name="request_key" value="' . esc_attr(self::key()) . '">';
                    wp_nonce_field('cor-mgmt-ticker-' . $race, 'cor_mgmt_nonce', false);
                    foreach ([90,180,270] as $angle) {
                        echo '<button name="angle" value="' . esc_attr((string) $angle) . '">' . esc_html__('Rotate image', 'h3-mgmt') . ' ' . esc_html((string) $angle) . '°</button> ';
                    }
                    echo '</form>';
                }
            } else {
                echo '<p>' . esc_html__('Historical image reference needs review.', 'h3-mgmt') . '</p>';
            }
        } elseif ($row['kind'] === 'video') {
            try {
                $video = Content::video($media);
                echo '<div class="cor-ticker-embed"><button type="button" disabled data-ticker-video="' . esc_attr($video) . '">' . esc_html__('Load video from YouTube (external connection)', 'h3-mgmt') . '</button><noscript><a href="' . esc_url('https://www.youtube.com/watch?v=' . $video) . '">' . esc_html__('Open video on YouTube', 'h3-mgmt') . '</a></noscript></div>';
            } catch (\InvalidArgumentException) {
                echo '<p>' . esc_html__('Historical video reference needs review.', 'h3-mgmt') . '</p>';
            }
        } elseif ($row['kind'] === 'location') {
            try {
                $point = Content::coordinates($media);
                echo '<p>' . esc_html($point) . '</p><div class="cor-ticker-embed"><button type="button" disabled data-ticker-position="' . esc_attr($point) . '">' . esc_html__('Load OpenStreetMap (external connection)', 'h3-mgmt') . '</button><noscript><a href="' . esc_url('https://www.openstreetmap.org/?mlat=' . str_replace(',', '&mlon=', $point)) . '">' . esc_html__('Open location on OpenStreetMap', 'h3-mgmt') . '</a></noscript></div>';
            } catch (\InvalidArgumentException) {
                echo '<p>' . esc_html__('Historical location needs review.', 'h3-mgmt') . '</p>';
            }
        }
        if ($moderation) {
            echo '<p>' . esc_html(($row['visibility'] ?? '') . ' · ' . ($row['issues'] ?? '')) . '</p>';
            if (self::allowed(get_current_user_id(), $race, 'moderate')) {
                echo '<form method="post">';
                wp_nonce_field('cor-mgmt-ticker-admin', 'cor_mgmt_nonce', false);
                echo '<input type="hidden" name="ticker_action" value="moderate"><input type="hidden" name="race" value="' . esc_attr((string) $race) . '"><input type="hidden" name="post_id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="revision" value="' . esc_attr($row['revision'] ?? '') . '"><input type="hidden" name="request_key" value="' . esc_attr(self::key()) . '"><button name="visible" value="' . esc_attr($row['visibility'] === 'visible' ? '0' : '1') . '">' . esc_html($row['visibility'] === 'visible' ? __('Hide', 'h3-mgmt') : __('Restore', 'h3-mgmt')) . '</button></form>';
            }
        }
        echo '</article>';
    }

    /** Local uploads only; historic external references never trigger automatic browser requests. */
    private static function imageUrl(string $reference, string $payload): string
    {
        $uploads = wp_upload_dir();
        $old = json_decode($payload, true);
        $type = is_array($old) && is_string($old['type'] ?? null) ? base64_decode($old['type'], true) : '';
        if ($type === '2' && !str_contains($reference, '/') && !str_contains($reference, '\\')) {
            $reference = trailingslashit($uploads['baseurl']) . 'mms/' . rawurlencode($reference);
        }
        if (preg_match('~^https?://~i', $reference)) {
            $reference = (string) parse_url($reference, PHP_URL_PATH);
        }
        $reference = rawurldecode($reference);
        $path = \H3_MGMT_Media::safe_image_path($reference);
        $base = realpath($uploads['basedir']);
        return $path !== '' && $base ? trailingslashit($uploads['baseurl']) . implode('/', array_map('rawurlencode', explode('/', ltrim(substr(wp_normalize_path($path), strlen(wp_normalize_path($base))), '/')))) : '';
    }

    /** @param array<string,mixed>|string $attributes */
    public static function feed(array|string $attributes = '', bool $map = false): string
    {
        global $h3_mgmt_races;
        $race = self::race($attributes);
        $attributes = is_array($attributes) ? $attributes : [];
        $requested = (int) ($attributes['max'] ?? ($map ? 100 : 10));
        $limit = $requested < 1 ? 100 : min(100, $requested);
        $page = max(1, (int) (is_string($_GET['ticker_page'] ?? null) ? $_GET['ticker_page'] : '1'));
        if (!isset($_GET['ticker_page']) && is_string($_GET['min'] ?? null)) {
            $page = min(99999, intdiv(\H3_MGMT_Security::id($_GET['min']), $limit)) + 1;
        }
        $team = max(0, (int) ($attributes['team'] ?? 0));
        $route = max(0, (int) ($attributes['route'] ?? 0));
        if (($attributes['route'] ?? 'all') === 'all' && is_string($_GET['ticker_route'] ?? null)) {
            $route = \H3_MGMT_Security::id($_GET['ticker_route']);
        }
        ob_start();
        self::assets();
        echo '<section class="cor-ticker" aria-label="' . esc_attr__('Web ticker', 'h3-mgmt') . '">';
        try {
            $settings = self::settings($race);
            if (empty($settings['liveticker_front'])) {
                echo '<p>' . esc_html__('Ticker display is disabled for this event.', 'h3-mgmt') . '</p>';
            } else {
                if (!empty($attributes['show_nav']) && ($attributes['route'] ?? 'all') === 'all') {
                    global $wpdb;
                    echo '<form method="get" action="' . esc_url(get_permalink() ?: home_url('/')) . '">';
                    echo '<input type="hidden" name="ticker_race" value="' . esc_attr((string) $race) . '">';
                    if (isset($_GET['page_id'])) {
                        echo '<input type="hidden" name="page_id" value="' . esc_attr((string) \H3_MGMT_Security::id($_GET['page_id'])) . '">';
                    }
                    echo '<label>' . esc_html__('Route', 'h3-mgmt') . '<select name="ticker_route"><option value="0">' . esc_html__('All routes', 'h3-mgmt') . '</option>';
                    foreach ($wpdb->get_results($wpdb->prepare('SELECT id,name FROM %i WHERE race_id=%d ORDER BY id', $wpdb->prefix . 'h3_mgmt_routes', $race), 'ARRAY_A') as $item) {
                        echo '<option value="' . esc_attr((string) $item['id']) . '" ' . selected($route, (int) $item['id'], false) . '>' . esc_html($item['name']) . '</option>';
                    }
                    echo '</select></label><button>' . esc_html__('Filter', 'h3-mgmt') . '</button></form>';
                }
                if (!empty($attributes['show_warning'])) {
                    echo '<div>' . wp_kses_post(self::notice($race, 27)) . '</div>';
                }
                $rows = self::manager()->feed($race, $team, $route, $page, $limit, false, 0, 0, $map ? 'location' : '');
                if ($map) {
                    $plugin = dirname(__DIR__, 2) . '/h3-management.php';
                    wp_enqueue_script('cor-mgmt-leaflet', plugins_url('lib/leaflet/leaflet.js', $plugin), [], '1.9.4', true);
                    wp_enqueue_style('cor-mgmt-leaflet', plugins_url('lib/leaflet/leaflet.css', $plugin), [], '1.9.4');
                    $points = [];
                    foreach ($rows as $row) {
                        try {
                            $parts = array_map('floatval', explode(',', Content::coordinates($row['media_ref'] ?? '')));
                            $points[] = [$parts[0], $parts[1], '#' . $row['id'] . ' · ' . ($row['body'] ?? ''), self::teamContext($row)];
                        } catch (\InvalidArgumentException) {
                            // Raw invalid historical references remain in the import report.
                        }
                    }
                    try {
                        $lat = is_scalar($attributes['coord_center_lat'] ?? null) ? (string) $attributes['coord_center_lat'] : '54.0237934';
                        $lon = is_scalar($attributes['coord_center_lng'] ?? null) ? (string) $attributes['coord_center_lng'] : '9.3754401';
                        $center = Content::coordinates($lat . ',' . $lon);
                    } catch (\InvalidArgumentException) {
                        $center = '54.0237934,9.3754401';
                    }
                    echo '<div class="cor-ticker-embed" data-center="' . esc_attr($center) . '" data-points="' . esc_attr(wp_json_encode($points) ?: '[]') . '"><button type="button" disabled data-ticker-map>' . esc_html__('Load event positions on OpenStreetMap (external connection)', 'h3-mgmt') . '</button></div>';
                }
                foreach ($rows as $row) {
                    if (!$map || $row['kind'] === 'location') {
                        self::article($row);
                        $commentKey = 'ticker_comments_' . $row['id'];
                        $commentPage = max(1, (int) (is_string($_GET[$commentKey] ?? null) ? $_GET[$commentKey] : '1'));
                        $comments = self::manager()->feed($race, 0, 0, $commentPage, 10, false, 0, (int) $row['id']);
                        foreach ($comments as $comment) {
                            self::article($comment);
                        }
                        if ($commentPage > 1 || count($comments) === 10) {
                            echo '<nav aria-label="' . esc_attr__('Comment pages', 'h3-mgmt') . '">';
                            if ($commentPage > 1) {
                                echo '<a href="' . esc_url(add_query_arg($commentKey, $commentPage - 1)) . '">' . esc_html__('Previous comments', 'h3-mgmt') . '</a> ';
                            }
                            if (count($comments) === 10) {
                                echo '<a href="' . esc_url(add_query_arg($commentKey, $commentPage + 1)) . '">' . esc_html__('More comments', 'h3-mgmt') . '</a>';
                            }
                            echo '</nav>';
                        }
                        if (self::allowed(get_current_user_id(), $race, 'comment')) {
                            echo '<details><summary>' . esc_html__('Write a comment', 'h3-mgmt') . '</summary>';
                            // Rendering an input never processes a POST from a feed reader.
                            echo self::form(['race' => $race, 'parent' => (int) $row['id']]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native form; submissions use admin-post.
                            echo '</details>';
                        }
                    }
                }
                self::pagination($page, count($rows), $limit, false);
                if (!$rows) {
                    echo '<p>' . esc_html__('No messages sent yet.', 'h3-mgmt') . '</p>';
                }
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('ticker_read_failed', $error);
            echo '<p>' . esc_html__('Ticker setup requires review.', 'h3-mgmt') . '</p>';
        }
        echo '</section>';
        return (string) ob_get_clean();
    }

    private static function pagination(int $page, int $count, int $limit, bool $admin): void
    {
        $base = $admin ? add_query_arg('race', self::queryRace(), admin_url('tools.php?page=cor-mgmt-ticker')) : remove_query_arg('ticker_page');
        echo '<nav aria-label="' . esc_attr__('Ticker pages', 'h3-mgmt') . '">';
        if ($page > 1) {
            echo '<a href="' . esc_url(add_query_arg('ticker_page', $page - 1, $base)) . '">' . esc_html__('Previous', 'h3-mgmt') . '</a> ';
        }
        if ($count === $limit) {
            echo '<a href="' . esc_url(add_query_arg('ticker_page', $page + 1, $base)) . '">' . esc_html__('Next', 'h3-mgmt') . '</a>';
        }
        echo '</nav>';
    }

    private static function queryRace(): int
    {
        return \H3_MGMT_Security::id($_GET['race'] ?? $_POST['race'] ?? 0);
    }

    private static function problem(\Throwable $error): string
    {
        return match ($error->getMessage()) {
            'ticker_source_charset_review' => __('Source text encoding requires review. No encoding was guessed; original data was retained.', 'h3-mgmt'),
            'transactional_ticker_journal_required' => __('The WordPress options table must use InnoDB for the transfer checkpoint. No engine conversion was performed.', 'h3-mgmt'),
            'ticker_source_changed', 'ticker_target_content_mismatch', 'ticker_import_count_mismatch', 'ticker_import_missing_row' => __('Source or target differs from the reviewed transfer. Stop and review the checkpoint; no cutover was performed.', 'h3-mgmt'),
            'ticker_unknown_source_schema', 'ticker_invalid_source_id', 'ticker_target_not_empty' => __('The source layout, IDs or existing target require manual review. Existing records were retained.', 'h3-mgmt'),
            'migration_locked' => __('Another operation is running. Retry this step after it finishes.', 'h3-mgmt'),
            default => __('Operation rejected. Review access, setup, transfer state or a concurrent change. Existing data was retained.', 'h3-mgmt'),
        };
    }

    public static function page(): void
    {
        global $wpdb;
        $race = self::queryRace();
        $selection = $race === 0 && (current_user_can('h3_mgmt_edit_races') || current_user_can('h3_mgmt_edit_own_races'));
        if (!current_user_can('manage_options') && !$selection && !\H3_MGMT_Security::can_object('races', 'edit', $race)) {
            wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response' => 403]);
        }
        $db = new Database($wpdb);
        $import = new TickerImport($db);
        echo '<div class="wrap"><h1>' . esc_html__('Web ticker', 'h3-mgmt') . '</h1>';
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (!\H3_MGMT_Security::post('cor-mgmt-ticker-admin')) {
                    throw new \RuntimeException('invalid_nonce');
                }
                $action = TeamBoundary::value('ticker_action');
                if ($action === 'moderate') {
                    self::manager()->moderate(get_current_user_id(), TeamBoundary::number(TeamBoundary::value('post_id')), TeamBoundary::number(TeamBoundary::value('revision')), TeamBoundary::value('visible') === '1', TeamBoundary::value('request_key'));
                } elseif (current_user_can('manage_options')) {
                    if ($action === 'setup') {
                        (new Installer($db))->ticker();
                    } elseif ($action === 'start' && TeamBoundary::value('writers_stopped') === '1') {
                        require_once constant('ABSPATH') . 'wp-admin/includes/plugin.php';
                        deactivate_plugins('cor-ticker/sms_ticker.php');
                        $import->start(true);
                    } elseif ($action === 'step') {
                        $import->step();
                    } else {
                        throw new \RuntimeException('invalid_ticker_operation');
                    }
                } else {
                    throw new \RuntimeException('forbidden');
                }
                echo '<p role="status">' . esc_html__('Ticker operation completed.', 'h3-mgmt') . '</p>';
            }
            if (current_user_can('manage_options')) {
                echo '<h2>' . esc_html__('Setup and historical transfer', 'h3-mgmt') . '</h2><p>' . esc_html__('Stop all old ticker writers before starting. Historical tables and media are retained. Each step copies or verifies at most 100 records. New submissions remain paused until verification completes.', 'h3-mgmt') . '</p><pre>' . esc_html(wp_json_encode($import->status()) ?: '') . '</pre>';
                echo '<form method="post">';
                wp_nonce_field('cor-mgmt-ticker-admin', 'cor_mgmt_nonce', false);
                echo '<p><label><input type="checkbox" name="writers_stopped" value="1">' . esc_html__('I have stopped all old writers and retired the direct SMS/MMS endpoints. Starting deactivates the old cor-ticker plugin.', 'h3-mgmt') . '</label></p>';
                foreach (['setup' => __('Set up ticker tables', 'h3-mgmt'), 'start' => __('Start controlled transfer', 'h3-mgmt'), 'step' => __('Process next transfer step', 'h3-mgmt')] as $action => $label) {
                    echo '<button name="ticker_action" value="' . esc_attr($action) . '">' . esc_html($label) . '</button> ';
                }
                echo '</form>';
            }
            echo '<form method="get"><input type="hidden" name="page" value="cor-mgmt-ticker"><label>' . esc_html__('Event', 'h3-mgmt') . '<select name="race">';
            if (current_user_can('manage_options')) {
                echo '<option value="0">' . esc_html__('Unassigned historical posts', 'h3-mgmt') . '</option>';
            }
            foreach ($db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races'))) as $event) {
                if (current_user_can('manage_options') || \H3_MGMT_Security::can_object('races', 'edit', (int) $event['id'])) {
                    echo '<option value="' . esc_attr($event['id'] ?? '') . '" ' . selected($race, (int) $event['id'], false) . '>' . esc_html(($event['name'] ?? '') . ' #' . $event['id']) . '</option>';
                }
            }
            echo '</select></label><button>' . esc_html__('Filter', 'h3-mgmt') . '</button></form>';
            if (self::manager()->native() && ($race > 0 || current_user_can('manage_options'))) {
                $page = max(1, (int) (is_string($_GET['ticker_page'] ?? null) ? $_GET['ticker_page'] : '1'));
                $rows = self::manager()->feed($race, 0, 0, $page, 30, true, get_current_user_id(), -1);
                foreach ($rows as $row) {
                    self::article($row, true);
                }
                self::pagination($page, count($rows), 30, true);
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('ticker_admin_rejected', $error);
            echo '<p role="alert">' . esc_html(self::problem($error)) . '</p>';
        }
        echo '</div>';
    }
}
