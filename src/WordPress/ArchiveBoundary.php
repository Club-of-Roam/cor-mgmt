<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Infrastructure\WordPress\Archive;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Cutover;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Preflight;

/** Administrator-only, explicitly confirmed archival and storage transition commands. */
final class ArchiveBoundary
{
    public static function archive(): Archive
    {
        global $wpdb;
        return new Archive(new Database($wpdb), Services::get()->clock, Services::get()->random);
    }

    public static function cutover(): Cutover
    {
        global $wpdb;
        return new Cutover(new Database($wpdb), Services::get()->clock, Services::get()->random);
    }

    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_management_page(__('Archives and storage transition', 'h3-mgmt'), __('Archives and storage transition', 'h3-mgmt'), 'manage_options', 'cor-mgmt-archives', [self::class, 'page']);
        });
        add_filter('pre_do_shortcode_tag', [ArchiveFrontend::class, 'shortcode'], 5, 4);
        add_action('admin_post_cor_mgmt_archive', static function (): void {
            try {
                $result = self::submit();
            } catch (\Throwable $error) {
                Services::get()->logger->error('archive_command_rejected', $error);
                wp_die(esc_html__('The archive command was rejected. Reload the current status and review the prerequisites. Existing data is retained.', 'h3-mgmt'), '', ['response' => 400, 'back_link' => true]);
            }
            wp_safe_redirect(add_query_arg(['page' => 'cor-mgmt-archives', 'snapshot' => $result['snapshot']], admin_url('tools.php')));
            exit;
        });
        add_action('wp_ajax_cor_mgmt_archive_step', static function (): void {
            try {
                if (!in_array($_POST['operation'] ?? '', ['archive-step','live-step'], true)) {
                    throw new \RuntimeException('invalid_archive_command');
                }
                wp_send_json_success(self::submit());
            } catch (\Throwable $error) {
                Services::get()->logger->error('archive_step_rejected', $error);
                wp_send_json_error(['message' => __('Processing stopped. Reload the status, review the source and resume explicitly.', 'h3-mgmt')], 400);
            }
        });
    }

    private static function text(string $key): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            throw new \RuntimeException('invalid_archive_request');
        }
        return wp_unslash($value);
    }

    /** @return array{state:string,copied:int,snapshot:string} */
    public static function submit(): array
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options')
            || !wp_verify_nonce(self::text('cor_mgmt_nonce'), 'cor-mgmt-archives')
        ) {
            throw new \RuntimeException('archive_command_forbidden');
        }
        if (self::text('backup') !== '1' || self::text('quiesced') !== '1') {
            throw new \RuntimeException('archive_maintenance_confirmation_required');
        }
        global $wpdb;
        $db = new Database($wpdb);
        $archive = self::archive();
        $cutover = self::cutover();
        $operation = self::text('operation');
        $id = str_starts_with($operation, 'live-') ? '' : self::text('snapshot');
        if ($operation === 'archive-setup') {
            (new Installer($db))->archive();
        } elseif (in_array($operation, ['archive-start-migration','archive-start-completion'], true)) {
            $race = self::text('race');
            if (!preg_match('/^[1-9][0-9]{0,8}$/D', $race)) {
                throw new \RuntimeException('invalid_archive_request');
            }
            $id = $archive->start((int) $race, get_current_user_id(), $operation === 'archive-start-migration' ? 'migration' : 'completion');
        } elseif ($operation === 'archive-step') {
            $archive->step($id);
        } elseif ($operation === 'archive-complete') {
            $archive->complete($id);
        } elseif ($operation === 'archive-abandon') {
            $archive->abandon($id);
        } elseif ($operation === 'live-start') {
            $cutover->start(self::text('shadow'), (new Preflight($db))->run()->fingerprint(), get_current_user_id());
        } elseif ($operation === 'live-step') {
            $cutover->step();
        } elseif ($operation === 'live-activate') {
            $cutover->activate();
        } elseif ($operation === 'live-recover') {
            $cutover->recover();
        } elseif ($operation === 'live-rollback') {
            $cutover->rollback();
        } elseif ($operation === 'live-abandon') {
            $cutover->abandon();
        } else {
            throw new \RuntimeException('invalid_archive_command');
        }
        if ($id !== '') {
            $run = $archive->run($id);
            return ['state' => $run['state'] ?? '', 'copied' => (int) $run['copied'], 'snapshot' => $id];
        }
        $state = $cutover->state();
        return ['state' => $state['state'] ?? 'setup', 'copied' => $state['copied'] ?? 0, 'snapshot' => ''];
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response' => 403]);
        }
        wp_enqueue_script('cor-mgmt-archive', plugins_url('js/cor-mgmt-archive.js', dirname(__DIR__, 2) . '/h3-management.php'), [], '1', true);
        $id = is_string($_GET['snapshot'] ?? null) && preg_match('/^[a-f0-9]{32}$/D', $_GET['snapshot']) ? $_GET['snapshot'] : '';
        echo '<div class="wrap"><h1>' . esc_html__('Archives and storage transition', 'h3-mgmt') . '</h1>';
        try {
            global $wpdb;
            $db = new Database($wpdb);
            if (\Cor\Mgmt\Infrastructure\WordPress\Schema\ArchiveSchema::ready($db)) {
                echo '<ul>';
                foreach ($db->rows($db->prepare('SELECT snapshot_id,race_id,mode,state,copied FROM %i ORDER BY created_at DESC,snapshot_id DESC LIMIT 50', $db->table('cor_mgmt_archive_runs'))) as $snapshot) {
                    echo '<li><a href="' . esc_url(add_query_arg(['page' => 'cor-mgmt-archives', 'snapshot' => $snapshot['snapshot_id']], admin_url('tools.php'))) . '">' . esc_html(($snapshot['race_id'] ?? '') . ' · ' . ($snapshot['mode'] ?? '') . ' · ' . ($snapshot['state'] ?? '') . ' · ' . ($snapshot['copied'] ?? '0')) . '</a></li>';
                }
                echo '</ul>';
            }
            if ($id !== '') {
                $run = self::archive()->run($id);
                echo '<p>' . esc_html(($run['race_id'] ?? '') . ' · ' . ($run['mode'] ?? '') . ' · ' . ($run['state'] ?? '') . ' · ' . ($run['copied'] ?? '0')) . '</p>';
                foreach ($db->rows($db->prepare('SELECT state,COUNT(*) AS media_count FROM %i WHERE snapshot_id=%s GROUP BY state', $db->table('cor_mgmt_archive_media'), $id)) as $media) {
                    echo '<p>' . esc_html(($media['state'] ?? '') . ': ' . ($media['media_count'] ?? '0')) . '</p>';
                }
            }
            $transition = self::cutover()->state();
            if ($transition !== null) {
                echo '<p>' . esc_html(($transition['generation'] ?? '') . ' · ' . ($transition['state'] ?? '')) . '</p>';
            }
        } catch (\Throwable) {
            echo '<p role="alert">' . esc_html__('Storage status needs review. No repair or cleanup runs automatically.', 'h3-mgmt') . '</p>';
        }
        echo '<h2>' . esc_html__('Archive storage setup', 'h3-mgmt') . '</h2>';
        echo '<p>' . esc_html__('Set up archive storage separately on the system status page. This does not freeze races, copy historical data or switch active tables.', 'h3-mgmt') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('tools.php?page=cor-mgmt-system')) . '">' . esc_html__('Open system status for archive storage setup', 'h3-mgmt') . '</a></p>';
        echo '<h2>' . esc_html__('Archive preparation and publication', 'h3-mgmt') . '</h2>';
        echo '<p>' . esc_html__('Stop every source writer and keep a restorable backup before proceeding. Snapshot creation freezes event changes. Account credentials are not copied into public archive views. Existing tables and original images are retained.', 'h3-mgmt') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-archive-form data-endpoint="' . esc_url(admin_url('admin-ajax.php')) . '"><input type="hidden" name="action" value="cor_mgmt_archive"><input type="hidden" name="snapshot" value="' . esc_attr($id) . '">';
        wp_nonce_field('cor-mgmt-archives', 'cor_mgmt_nonce');
        echo '<p><label><input type="checkbox" name="backup" value="1" required> ' . esc_html__('A verified backup is available.', 'h3-mgmt') . '</label></p><p><label><input type="checkbox" name="quiesced" value="1" required> ' . esc_html__('All source writers are stopped.', 'h3-mgmt') . '</label></p>';
        echo '<p><label>' . esc_html__('Event ID', 'h3-mgmt') . ' <input type="number" min="1" name="race"></label> <button class="button" name="operation" value="archive-start-completion">' . esc_html__('Prepare new race completion', 'h3-mgmt') . '</button> <button class="button" name="operation" value="archive-start-migration">' . esc_html__('Prepare historical migration archive', 'h3-mgmt') . '</button></p>';
        if ($id !== '') {
            echo '<p><button class="button" name="operation" value="archive-step">' . esc_html__('Process one archive step', 'h3-mgmt') . '</button> <button class="button" type="button" data-archive-run="archive-step">' . esc_html__('Process archive until verified', 'h3-mgmt') . '</button> <button class="button" name="operation" value="archive-complete">' . esc_html__('Publish verified race completion', 'h3-mgmt') . '</button> <button class="button" name="operation" value="archive-abandon">' . esc_html__('Abandon unfinished snapshot without deleting it', 'h3-mgmt') . '</button></p>';
        }
        echo '<h2>' . esc_html__('Active storage transition', 'h3-mgmt') . '</h2><p>' . esc_html__('Requires the verified shadow run and a verified migration archive for every historical started race. Switching retains original tables. Rollback is blocked after new plugin/account/profile writes.', 'h3-mgmt') . '</p><p><label>' . esc_html__('Verified shadow run ID', 'h3-mgmt') . ' <input name="shadow" pattern="[a-f0-9]{32}"></label> <button class="button" name="operation" value="live-start">' . esc_html__('Prepare active storage copy', 'h3-mgmt') . '</button></p><p>';
        foreach (['live-step' => __('Process one storage step', 'h3-mgmt'), 'live-activate' => __('Activate verified storage', 'h3-mgmt'), 'live-recover' => __('Recover interrupted switch', 'h3-mgmt'), 'live-rollback' => __('Return before new writes', 'h3-mgmt'), 'live-abandon' => __('Abandon storage preparation without deleting copies', 'h3-mgmt')] as $operation => $label) {
            echo '<button class="button" name="operation" value="' . esc_attr($operation) . '">' . esc_html($label) . '</button> ';
        }
        echo '<button class="button" type="button" data-archive-run="live-step">' . esc_html__('Process storage until verified', 'h3-mgmt') . '</button></p><button class="button" type="button" data-archive-stop disabled>' . esc_html__('Stop after current step', 'h3-mgmt') . '</button><p role="status" aria-live="polite" data-archive-status></p></form></div>';
    }
}
