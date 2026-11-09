<?php

/** Protected replacement for the public ticker database rewrite shortcode. */
final class H3_MGMT_Maintenance {
    public static function register(): void {
        add_action('admin_menu', static function (): void {
            add_management_page(__('Ticker maintenance', 'h3-mgmt'), __('Ticker maintenance', 'h3-mgmt'), 'manage_options', 'cor-mgmt-maintenance', [self::class, 'page']);
        });
    }

    public static function repair(int $race, bool $apply = false) {
        global $wpdb, $h3_mgmt_teams, $h3_mgmt_ticker;
        if (!current_user_can('manage_options') || !$race || H3_MGMT_Security::archived($race)
            || !H3_MGMT_Security::post('cor-mgmt-maintenance')) { return new WP_Error('forbidden', 'Not permitted'); }
        if ($apply) { return new WP_Error('ticker_repair_retired', 'Historical sender rewrites are retired; original identities are retained.'); }
        $db = new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
        if (!$db->exists($db->table('sms_ticker'))) { return ['proposed'=>0,'updated'=>0,'ambiguous'=>0]; }
        $columns = array_column($db->rows($db->prepare('SHOW COLUMNS FROM %i', $db->table('sms_ticker'))), 'Field');
        if (!in_array('send_from', $columns, true) || !in_array('race_id', $columns, true)) { return new WP_Error('legacy_schema_review', 'Historical ticker schema needs review.'); }
        $mapping = [];
        foreach ($h3_mgmt_teams->get_participant_ids($race) as $user) {
            $phone = $h3_mgmt_ticker->normalize_phone_number(get_user_meta($user, 'mobile', true));
            if ($phone !== '') { $mapping[$phone][] = 'U_' . (int) $user; }
        }
        $teams = $wpdb->get_results($wpdb->prepare("SELECT id,team_phone FROM {$wpdb->prefix}h3_mgmt_teams WHERE race_id=%d", $race), ARRAY_A);
        foreach ($teams as $team) {
            $phone = $h3_mgmt_ticker->normalize_phone_number((string) $team['team_phone']);
            if ($phone !== '') { $mapping[$phone][] = 'T_' . (int) $team['id']; }
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,send_from FROM {$wpdb->prefix}sms_ticker WHERE race_id=%d", $race), ARRAY_A);
        $report = ['proposed'=>0, 'updated'=>0, 'ambiguous'=>0];
        foreach ($rows as $row) {
            if (!preg_match('/^[+0-9 ()-]+$/D', $row['send_from'])) { continue; }
            $targets = array_unique($mapping[$h3_mgmt_ticker->normalize_phone_number($row['send_from'])] ?? []);
            if (count($targets) !== 1) { $report['ambiguous']++; continue; }
            $report['proposed']++;

        }
        return $report;
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response'=>403]); }
        $report = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { $report = self::repair(H3_MGMT_Security::id($_POST['race'] ?? 0), isset($_POST['apply'])); }
        echo '<div class="wrap"><h1>' . esc_html__('Ticker maintenance', 'h3-mgmt') . '</h1>';
        if ($report !== null) { echo '<pre>' . esc_html(is_wp_error($report) ? $report->get_error_message() : wp_json_encode($report)) . '</pre>'; }
        echo '<form method="post">' . H3_MGMT_Security::nonce('cor-mgmt-maintenance') .
            '<label>' . esc_html__('Race ID', 'h3-mgmt') . ' <input type="number" name="race" min="1" required></label> ' .
            '<p>' . esc_html__('Read-only historical identity report. Sender rewrites are retired.', 'h3-mgmt') . '</p>';
        submit_button(__('Check / run', 'h3-mgmt'));
        echo '</form></div>';
    }
}
