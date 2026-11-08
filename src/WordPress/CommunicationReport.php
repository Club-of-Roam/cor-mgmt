<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Read-only retention inventory. No implicit deletion or guessed retention periods. */
final class CommunicationReport
{
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_submenu_page('h3-mgmt-emails', __('Communication storage report', 'h3-mgmt'), __('Storage report', 'h3-mgmt'), 'manage_options', 'cor-mgmt-communication-report', [self::class, 'page']);
        });
    }
    /** @return list<array{module:string,count:string,bytes:string,oldest:string}> */
    public static function data(): array
    {
        if (!current_user_can('manage_options')) {
            throw new \Cor\Mgmt\Application\Communication\CommunicationException('forbidden');
        }
        global $wpdb;
        $db = new Database($wpdb);
        $output = [];
        foreach (['cor_mgmt_mail_queue','cor_mgmt_mail_history','cor_mgmt_communication_history','cor_mgmt_broadcasts','cor_mgmt_broadcast_recipients','cor_mgmt_broadcast_history','h3_mgmt_xchange','cor_mgmt_exchange_history'] as $suffix) {
            $table = $db->table($suffix);
            if (!$db->exists($table)) {
                continue;
            }
            $count = $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $table)) ?? '0';
            $size = $db->scalar($db->prepare('SELECT DATA_LENGTH+INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) ?? '0';
            $oldest = '';
            if ($suffix === 'h3_mgmt_xchange') {
                $oldest = $db->scalar($db->prepare('SELECT MIN(time) FROM %i', $table)) ?? '';
            } elseif ($suffix !== 'cor_mgmt_broadcast_recipients') {
                $oldest = $db->scalar($db->prepare('SELECT MIN(created_at) FROM %i', $table)) ?? '';
            }
            $output[] = ['module' => $suffix, 'count' => $count, 'bytes' => $size, 'oldest' => $oldest];
        }
        return $output;
    }
    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('Not permitted.', 'h3-mgmt') . '</p>';
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Communication storage report', 'h3-mgmt') . '</h1><p>' . esc_html__('Read-only inventory. Storage bytes are estimates. Retention periods and anonymization rules need an explicit decision before cleanup can be implemented. Nothing is deleted automatically.', 'h3-mgmt') . '</p><table class="widefat"><caption>' . esc_html__('Message snapshots and audit storage', 'h3-mgmt') . '</caption><thead><tr><th scope="col">' . esc_html__('Module', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Rows', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Estimated bytes', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Oldest timestamp', 'h3-mgmt') . '</th></tr></thead><tbody>';
        try {
            foreach (self::data() as $row) {
                echo '<tr><th scope="row">' . esc_html($row['module']) . '</th><td>' . esc_html($row['count']) . '</td><td>' . esc_html($row['bytes']) . '</td><td>' . esc_html($row['oldest']) . '</td></tr>';
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('communication_report_failed', $error);
            echo '<tr><td colspan="4">' . esc_html__('Storage could not be inspected. Review module setup.', 'h3-mgmt') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
