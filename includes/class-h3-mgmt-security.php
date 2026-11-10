<?php

/** Shared boundary checks for the legacy adapters. PHP 8.2 compatible. */
final class H3_MGMT_Security {
    public static function register(): void {
        add_filter('do_shortcode_tag', static function ($output, $tag) {
            global $shortcode_tags;
            $callback = $shortcode_tags[$tag] ?? null;
            return is_array($callback) && is_object($callback[0]) && str_starts_with(get_class($callback[0]), 'H3_MGMT_')
                ? self::html((string) $output) : $output;
        }, 99, 2);
    }
    private static array $admin_resources = [
        'h3-mgmt-races' => 'races', 'h3-mgmt-routes' => 'routes', 'h3-mgmt-stages' => 'stages',
        'h3-mgmt-teams' => 'teams', 'h3-mgmt-participants' => 'participants',
        'h3-mgmt-sponsors' => 'sponsors', 'h3-mgmt-sponsors-betterplace' => 'sponsors',
        'h3-mgmt-sponsors-paypal' => 'sponsors', 'h3-mgmt-sponsors-debit' => 'sponsors',
        'h3-mgmt-emails' => 'emails', 'h3-mgmt-emails-autoresponses' => 'autoresponses',
        'h3-mgmt-statistics' => 'statistics',
    ];

    public static function id($value): int {
        return is_scalar($value) && preg_match('/^[0-9]+$/D', (string) $value) ? (int) $value : 0;
    }

    public static function post(string $action): bool {
        global $wpdb;
        if ($wpdb instanceof wpdb) {
            try {
                $db = new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
                $runtime = \Cor\Mgmt\WordPress\Services::get();
                if (!(new \Cor\Mgmt\Infrastructure\WordPress\Schema\Cutover($db, $runtime->clock, $runtime->random))->runtimeReady()) { return false; }
            } catch (Throwable) { return false; }
        }
        $nonce = $_POST['cor_mgmt_nonce'] ?? '';
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_string($nonce)
            && (bool) wp_verify_nonce(wp_unslash($nonce), $action);
    }

    public static function nonce(string $action): string {
        return wp_nonce_field($action, 'cor_mgmt_nonce', false, false);
    }

    public static function donation_nonce(int $race): string {
        $cookie = is_string($_COOKIE['client_token'] ?? null) ? $_COOKIE['client_token'] : '';
        return 'cor-mgmt-donation-' . $race . '-' . hash_hmac('sha256', $cookie, wp_salt('nonce'));
    }

    public static function donation_request(int $race): bool {
        if (!$race || self::archived($race) || !self::post(self::donation_nonce($race))) { return false; }
        foreach ($_POST as $value) { if (!is_scalar($value)) { return false; } }
        foreach (['donation_token', 'donation_client_reference'] as $key) {
            $value = $_POST[$key] ?? $_GET[$key] ?? '';
            if (!is_scalar($value) || ($value !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', (string) $value))) { return false; }
        }
        if (isset($_GET['donation_client_reference']) && !hash_equals((string) ($_COOKIE['client_token'] ?? ''), (string) $_GET['donation_client_reference'])) { return false; }
        return true;
    }

    public static function error(): string {
        return '<p class="error">' . esc_html__('This action is not permitted. Reload the form and try again.', 'h3-mgmt') . '</p>';
    }

    public static function resource(string $page): string {
        return self::$admin_resources[$page] ?? '';
    }

    public static function admin_nonce(string $resource): string { return 'cor-mgmt-admin-' . $resource; }

    public static function can_object(string $resource, string $operation, int $id = 0): bool {
        global $wpdb;
        if (!is_user_logged_in()) { return false; }
        $group = match ($resource) {
            'routes', 'stages', 'races' => 'races', 'teams', 'participants' => 'teams',
            default => $resource,
        };
        $cap = 'h3_mgmt_' . $operation . '_' . $group;
        if ($group === 'sponsors' && $operation === 'delete') { $operation = 'edit'; $cap = 'h3_mgmt_edit_sponsors'; }
        if (current_user_can($cap)) { return true; }
        if (!$id || !in_array($group, ['races', 'teams', 'sponsors'], true)
            || !current_user_can('h3_mgmt_' . $operation . '_own_' . $group)) { return false; }
        if ($resource === 'routes') { $route = $id; }
        elseif ($resource === 'teams' || $resource === 'stages') {
            $route = (int) $wpdb->get_var($wpdb->prepare("SELECT route_id FROM {$wpdb->prefix}h3_mgmt_{$resource} WHERE id=%d", $id));
        } elseif ($resource === 'participants') {
            $route = (int) $wpdb->get_var($wpdb->prepare("SELECT t.route_id FROM {$wpdb->prefix}h3_mgmt_teams t JOIN {$wpdb->prefix}h3_mgmt_teammates m ON m.team_id=t.id WHERE m.id=%d", $id));
        } elseif ($resource === 'sponsors') {
            $route = (int) $wpdb->get_var($wpdb->prepare("SELECT t.route_id FROM {$wpdb->prefix}h3_mgmt_teams t JOIN {$wpdb->prefix}h3_mgmt_sponsors s ON s.team_id=t.id WHERE s.id=%d", $id));
        } else { return false; }
        return $route > 0 && (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}h3_mgmt_routes WHERE id=%d AND user_id=%d", $route, get_current_user_id()
        ));
    }

    public static function object_race(string $resource, int $id): int {
        global $wpdb;
        if ($resource === 'races') { return $id; }
        $table = match ($resource) {
            'routes' => 'routes', 'stages' => 'stages', 'teams' => 'teams', 'sponsors' => 'sponsors',
            default => '',
        };
        if ($resource === 'participants') {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT t.race_id FROM {$wpdb->prefix}h3_mgmt_teams t JOIN {$wpdb->prefix}h3_mgmt_teammates m ON m.team_id=t.id WHERE m.id=%d", $id
            ));
        }
        return $table ? (int) $wpdb->get_var($wpdb->prepare("SELECT race_id FROM {$wpdb->prefix}h3_mgmt_{$table} WHERE id=%d", $id)) : 0;
    }

    public static function archived(int $race): bool {
        global $wpdb;
        if (!$race) { return false; }
        $json = $wpdb->get_var($wpdb->prepare("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=%d", $race));
        $settings = json_decode((string) $json, true);
        return (int) ($settings['status'] ?? -1) === 4
            || ($wpdb instanceof wpdb && \Cor\Mgmt\Infrastructure\WordPress\Archive::locked(new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb), $race));
    }

    /** Validate before adapting POST routing for legacy controllers. GET never becomes a write. */
    public static function admin_request(string $resource): bool {
        $special = ['emails' => 'h3_mgmt_send_emails', 'autoresponses' => 'h3_mgmt_edit_autoresponses', 'statistics' => 'h3-mgmt-view-statistics'];
        if (isset($special[$resource])) {
            if (!current_user_can($special[$resource])) { return false; }
        } elseif (!self::can_object($resource, 'view')) { return false; }
        $action = $_POST['todo'] ?? $_GET['todo'] ?? '';
        if (!is_string($action)) { return false; }
        foreach ($_GET as $key => $value) { if (!is_scalar($value) && $key !== 'bulk') { return false; } }
        if (in_array($action, ['', 'edit', 'new'], true) && empty($_POST['submitted'])) {
            if (isset($_GET['id']) && !self::id($_GET['id'])) { return false; }
            if ($action === 'edit' && isset($_GET['id']) && !isset($special[$resource])
                && !self::can_object($resource, 'view', self::id($_GET['id']))) { return false; }
            return true;
        }
        if (!self::post(self::admin_nonce($resource))) { return false; }
        foreach ($_POST as $key => $value) {
            if (!is_scalar($value) && $key !== 'bulk') { return false; }
        }
        $allowed = match ($resource) {
            'races' => ['save','delete','active','liveticker-set','liveticker-unset','liveticker-front-set','liveticker-front-unset','donation-set','donation-unset'],
            'routes', 'stages' => ['save','delete','bulk-delete'],
            'teams' => ['save','delete'],
            'participants' => ['paid','unpaid','waiver-set','waiver-unset','bulk-paid','bulk-unpaid','bulk-waiver-set','bulk-waiver-unset'],
            'sponsors' => ['save','delete','paid','unpaid','show','hide','bulk-delete','bulk-paid','bulk-unpaid','bulk-show','bulk-hide'],
            'emails' => ['send'], 'autoresponses' => ['', 'save'], 'statistics' => [''], default => [],
        };
        if (!in_array($action, $allowed, true)) { return false; }
        $rawIds = $_POST['bulk'] ?? (isset($_POST['id']) ? [$_POST['id']] : (isset($_GET['id']) ? [$_GET['id']] : []));
        if (!is_array($rawIds) || count($rawIds) > 500) { return false; }
        $ids = [];
        foreach ($rawIds as $rawId) {
            if ($action === 'save' && $rawId === '') { continue; }
            $id = self::id($rawId);
            if (!$id) { return false; }
            $ids[] = $id;
        }
        $operation = str_contains($action, 'delete') ? 'delete' : 'edit';
        if (!isset($special[$resource])) {
            if (!$ids && !self::can_object($resource, $operation)) { return false; }
            foreach ($ids as $id) {
                if (!self::can_object($resource, $operation, $id) || self::archived(self::object_race($resource, $id))) { return false; }
                if ($resource === 'participants' && self::phase(self::object_race($resource, $id)) >= 3) { return false; }
            }
            foreach (['race_id', 'route_id'] as $parent) {
                if (isset($_POST[$parent]) && (!is_scalar($_POST[$parent]) || !preg_match('/^[0-9]+$/D', (string) $_POST[$parent]))) { return false; }
            }
            $newRace = self::id($_POST['race_id'] ?? 0);
            if ($newRace && self::archived($newRace)) { return false; }
            $globalEdit = current_user_can('h3_mgmt_edit_' . match ($resource) {
                'teams', 'participants' => 'teams', 'sponsors' => 'sponsors', default => 'races',
            });
            $route = self::id($_POST['route_id'] ?? 0);
            $raceOfRoute = $route ? self::object_race('routes', $route) : 0;
            if ($route && ((!$globalEdit && !self::assigned_route($route)) || !$raceOfRoute)) { return false; }
            if ($route && (($newRace && $raceOfRoute !== $newRace) || self::archived($raceOfRoute))) { return false; }
            if ($newRace && !$globalEdit
                && (!$ids || self::object_race($resource, $ids[0]) !== $newRace)) { return false; }
            if ($resource === 'sponsors' && isset($_POST['team_id'])) {
                $team = self::id($_POST['team_id']);
                $teamRace = self::object_race('teams', $team);
                if (!$teamRace || self::archived($teamRace) || ($newRace && $newRace !== $teamRace)) { return false; }
                if (!$globalEdit) {
                    global $wpdb;
                    $teamRoute = (int) $wpdb->get_var($wpdb->prepare("SELECT route_id FROM {$wpdb->prefix}h3_mgmt_teams WHERE id=%d", $team));
                    if (!self::assigned_route($teamRoute)) { return false; }
                }
            }
        }
        $_GET['todo'] = $action;
        if ($ids && str_starts_with($action, 'bulk-')) { $_GET['bulk'] = $ids; unset($_GET['id']); }
        elseif ($ids) { $_GET['id'] = $ids[0]; }
        return true;
    }

    /** Forms and data from old templates may contain intended markup, never scripts/events. */
    public static function html(string $html): string {
        $html = preg_replace_callback('~<iframe\b[^>]*>.*?</iframe>~is', static function ($match) {
            if (!preg_match('~\bsrc=["\x27](https://[^"\x27]+)["\x27]~i', $match[0], $source)) { return ''; }
            $parts = wp_parse_url(html_entity_decode($source[1], ENT_QUOTES));
            return in_array(strtolower($parts['host'] ?? ''), ['www.youtube.com','www.youtube-nocookie.com','www.google.com','maps.google.com','secure.betterplace.org'], true)
                && !isset($parts['user']) && !isset($parts['pass']) ? $match[0] : '';
        }, $html);
        $allowed = wp_kses_allowed_html('post');
        $allowed['iframe'] = ['src'=>true,'width'=>true,'height'=>true,'frameborder'=>true,'allowfullscreen'=>true,'title'=>true,'loading'=>true,'style'=>true];
        $allowed['fieldset'] = ['class'=>true,'disabled'=>true];
        $allowed['legend'] = ['class'=>true];
        $allowed['form'] = ['id'=>true,'name'=>true,'method'=>true,'action'=>true,'enctype'=>true,'class'=>true,'accept-charset'=>true];
        foreach (['input','button','select','option','textarea','label'] as $tag) {
            $allowed[$tag] = array_fill_keys(['type','id','name','value','class','for','form','checked','selected','disabled','readonly','multiple','required','min','max','step','maxlength','size','rows','cols','title','accept','data-confirm','aria-describedby','aria-label'], true);
        }
        return wp_kses($html, $allowed);
    }

    public static function admin_html(string $html, string $page): string {
        $resource = self::resource($page);
        $forms = '';
        $html = preg_replace_callback('~<a\b([^>]*?)href=["\x27]([^"\x27]+)["\x27]([^>]*)>(.*?)</a>~is', static function ($m) use (&$forms, $resource) {
            parse_str((string) parse_url(html_entity_decode($m[2], ENT_QUOTES), PHP_URL_QUERY), $query);
            if (empty($query['todo']) || in_array($query['todo'], ['edit','new'], true)) { return $m[0]; }
            $target = self::resource((string) ($query['page'] ?? ''));
            if (!$target || $target !== $resource) { return ''; }
            $id = wp_unique_id('cor-action-');
            $forms .= '<form id="' . esc_attr($id) . '" method="post" action="' . esc_url(add_query_arg('page', $query['page'], admin_url('admin.php'))) . '">' . self::nonce(self::admin_nonce($target));
            foreach (['page','todo','id','method'] as $key) {
                if (isset($query[$key]) && is_scalar($query[$key])) { $forms .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $query[$key]) . '">'; }
            }
            $forms .= '</form>';
            $confirm = str_contains((string) $query['todo'], 'delete') ? ' data-confirm="' . esc_attr__('Really delete this record?', 'h3-mgmt') . '"' : '';
            return '<button type="submit" class="button-link" form="' . esc_attr($id) . '"' . $confirm . '>' . wp_kses_post($m[4]) . '</button>';
        }, $html);
        return self::html($html . $forms);
    }

    public static function add_nonces(string $html, string $action, array $fields = []): string {
        $hidden = '';
        foreach ($fields as $key => $value) { $hidden .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">'; }
        return preg_replace_callback('~<form\b[^>]*\bmethod=["\x27]post["\x27][^>]*>~i',
            static fn ($match) => $match[0] . self::nonce($action) . $hidden, $html);
    }

    public static function ranking_request(): bool {
        global $wpdb;
        $route = self::id($_POST['route'] ?? 0);
        $stage = self::id($_POST['stage'] ?? 0);
        if (!is_user_logged_in() || !$route || $stage < 1 || $stage > 6 || !self::post('cor-mgmt-ranking-' . $route)) { return false; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}h3_mgmt_routes WHERE id=%d", $route), ARRAY_A);
        if (!$row || self::archived((int) $row['race_id'])
            || ((int) $row['user_id'] !== get_current_user_id() && !self::can_object('races', 'edit', (int) $row['race_id']))) { return false; }
        foreach ($_POST as $key => $value) {
            if (!is_string($key)) { return false; }
            if (preg_match('/^rank-[0-9]+$/D', $key) && (!is_scalar($value) || !in_array((string) $value, array_map('strval', array_merge(range(0, 12), [99])), true))) { return false; }
            if (preg_match('/^vary_extra-[0-9]+$/D', $key) && (!is_scalar($value) || !preg_match('/^[0-9]{1,3}$/D', (string) $value) || (int) $value > 255)) { return false; }
        }
        $teams = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}h3_mgmt_teams WHERE route_id=%d AND complete=1", $route)));
        $settings = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=%d", $row['race_id'])), true);
        foreach ($teams as $team) { if (!array_key_exists('rank-' . $team, $_POST)) { return false; } }
        foreach ($_POST as $key => $value) {
            if (preg_match('/^(rank|one_extra|amount_extra|vary_extra)-([0-9]+)$/D', $key, $match)
                && (!in_array((int) $match[2], $teams, true) || !is_scalar($value))) { return false; }
            if (preg_match('/^(one_extra|amount_extra|vary_extra)-[0-9]+$/D', $key, $match)) {
                $setting = ['one_extra'=>'one_extra_point','amount_extra'=>'amount_extra_point','vary_extra'=>'vary_extra_point_field'][$match[1]];
                if (empty($settings[$setting]) || ($match[1] === 'vary_extra' && $stage !== 6)) { return false; }
            }
        }
        return true;
    }

    public static function can_recalculate(int $team): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in()) { return false; }
        $race = self::object_race('teams', $team);
        if (self::phase($race) >= 3) { return false; }
        return (self::owns_team($team) && self::post('cor-mgmt-team-' . $race))
            || (self::can_object('teams', 'edit', $team) && (self::post(self::admin_nonce('teams')) || self::post(self::admin_nonce('participants'))));
    }

    public static function phase(int $race): int {
        global $wpdb;
        $settings = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT setting FROM {$wpdb->prefix}h3_mgmt_races WHERE id=%d", $race)), true);
        return (int) ($settings['status'] ?? -1);
    }

    public static function owns_team(int $team): bool {
        global $wpdb;
        return $team > 0 && is_user_logged_in() && (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}h3_mgmt_teammates WHERE team_id=%d AND user_id=%d LIMIT 1", $team, get_current_user_id()
        ));
    }

    public static function assigned_route(int $route): bool {
        global $wpdb;
        return is_user_logged_in() && (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}h3_mgmt_routes WHERE id=%d AND user_id=%d", $route, get_current_user_id()));
    }

    public static function upload(string $field) {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !is_scalar($file['name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
            || filesize($file['tmp_name']) > min(wp_max_upload_size(), 5 * 1024 * 1024)) {
            return new WP_Error('invalid_upload', __('Invalid image upload.', 'h3-mgmt'));
        }
        $image = @getimagesize($file['tmp_name']);
        $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!$image || !isset($types[$image['mime']]) || $image[0] * $image[1] > 24000000) { return new WP_Error('invalid_image', __('Invalid image.', 'h3-mgmt')); }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file['name'] = wp_generate_uuid4() . '.' . $types[$image['mime']];
        $result = wp_handle_upload($file, ['test_form'=>false, 'mimes'=>['jpg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp']]);
        return isset($result['error']) ? new WP_Error('upload_failed', $result['error']) : $result;
    }
}
