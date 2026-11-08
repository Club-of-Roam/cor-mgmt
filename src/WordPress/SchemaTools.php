<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Domain\InstallationStatus;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Preflight;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Runner;
use Throwable;

/** Protected, explicit administration; normal requests never start schema work. */
final class SchemaTools
{
    public static function register(): void
    {
        add_action('wp_ajax_cor_mgmt_schema', [self::class,'request']);
        add_action('admin_enqueue_scripts', static function (string $hook): void {
            if ($hook === 'tools_page_cor-mgmt-system') {
                wp_enqueue_script('cor-mgmt-schema', plugins_url('js/h3-mgmt-schema.js', dirname(__DIR__, 2) . '/h3-management.php'), [], '1', true);
            }
        });
    }
    public static function request(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['code' => 'post_required'], 405);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['code' => 'forbidden'], 403);
        }
        $nonce = $_POST['nonce'] ?? null;
        if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'cor-mgmt-schema')) {
            wp_send_json_error(['code' => 'invalid_nonce'], 403);
        }
        if (Lifecycle::prerequisiteErrors()) {
            wp_send_json_error(['code' => 'requirements'], 400);
        }
        $operation = $_POST['operation'] ?? null;
        if (!is_string($operation) || !in_array($operation, ['install','participation-setup','results-setup','donation-setup','communication-setup','donation-audit','preflight','start','step','abandon'], true)) {
            wp_send_json_error(['code' => 'invalid_operation'], 400);
        }
        if (in_array($operation, ['start','step'], true) && (($_POST['backup'] ?? '') !== '1' || ($_POST['quiesced'] ?? '') !== '1')) {
            wp_send_json_error(['code' => 'maintenance_prerequisites'], 400);
        }
        global $wpdb;
        $suppressed = $wpdb->suppress_errors(true);
        try {
            $db = new Database($wpdb);
            $runtime = Services::get();
            $runner = new Runner($db, $runtime->clock, $runtime->random);
            if ($operation === 'install') {
                (new Installer($db))->fresh();
                $data = ['reload' => true];
            } elseif ($operation === 'participation-setup') {
                (new Installer($db))->participation();
                $data = ['reload' => true];
            } elseif ($operation === 'results-setup') {
                (new Installer($db))->results();
                $data = ['reload' => true];
            } elseif ($operation === 'donation-setup') {
                (new Installer($db))->donations();
                $data = ['reload' => true];
            } elseif ($operation === 'communication-setup') {
                (new Installer($db))->communication();
                $data = ['reload' => true];
            } elseif ($operation === 'donation-audit') {
                $data = \Cor\Mgmt\Infrastructure\WordPress\DonationAudit::report($db);
            } elseif ($operation === 'preflight') {
                $report = (new Preflight($db))->run();
                $tables = [];
                foreach ($report->tables as $name => $table) {
                    $tables[$name] = ['count' => $table['count'],'hash' => $table['hash']];
                }
                $data = ['fingerprint' => $report->fingerprint(),'tables' => $tables,'issues' => $report->issues,'archive_count' => count($report->archiveIds)];
            } elseif ($operation === 'start') {
                $fingerprint = $_POST['fingerprint'] ?? null;
                if (!is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
                    throw new MigrationException('invalid_fingerprint');
                }
                $data = $runner->status($runner->start($fingerprint));
            } else {
                $id = $_POST['run_id'] ?? null;
                if (!is_string($id)) {
                    throw new MigrationException('invalid_run_id');
                }
                if ($operation === 'abandon') {
                    $runner->abandon($id);
                    $data = ['reload' => true];
                } else {
                    $data = $runner->step($id, 100);
                }
            }
        } catch (Throwable $error) {
            $code = $error instanceof MigrationException ? $error->getMessage() : 'schema_operation_failed';
            if (!preg_match('/^[a-z_]{1,64}$/D', $code)) {
                $code = 'schema_operation_failed';
            }
            $wpdb->suppress_errors($suppressed);
            wp_send_json_error(['code' => $code], 400);
        }
        $wpdb->suppress_errors($suppressed);
        wp_send_json_success($data);
    }
    public static function render(InstallationStatus $status): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $db = new Database($wpdb);
        $run = null;
        try {
            $runtime = Services::get();
            $run = (new Runner($db, $runtime->clock, $runtime->random))->current();
        } catch (MigrationException) {
        /* Unrecognized target storage is reported by explicit preflight. */
        }
        echo '<section id="cor-mgmt-schema" data-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('cor-mgmt-schema')) . '">';
        if ($status === InstallationStatus::Ready && !\Cor\Mgmt\Infrastructure\WordPress\Schema\ParticipationSchema::ready($db)) {
            echo '<p><button type="button" class="button button-primary" data-operation="participation-setup">' . esc_html__('Set up team and invitation storage', 'h3-mgmt') . '</button></p>';
        }
        if ($status === InstallationStatus::Ready && !\Cor\Mgmt\Infrastructure\WordPress\Schema\ResultsSchema::ready($db)) {
            echo '<p><button type="button" class="button button-primary" data-operation="results-setup">' . esc_html__('Set up result storage', 'h3-mgmt') . '</button></p>';
        }
        if ($status === InstallationStatus::Ready && !\Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema::ready($db)) {
            echo '<p><button type="button" class="button button-primary" data-operation="donation-setup">' . esc_html__('Set up donation storage', 'h3-mgmt') . '</button></p>';
        }
        if ($status === InstallationStatus::Ready && !\Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema::ready($db)) {
            echo '<p><button type="button" class="button button-primary" data-operation="communication-setup">' . esc_html__('Set up event autoresponse storage', 'h3-mgmt') . '</button></p>';
        }
        if ($status === InstallationStatus::Ready) {
            echo '<p><button type="button" class="button" data-operation="donation-audit">' . esc_html__('Review donation storage (read only)', 'h3-mgmt') . '</button></p>';
        }
        echo '<h2>' . esc_html__('Database setup and migration', 'h3-mgmt') . '</h2>';
        echo '<p>' . esc_html__('Migration prepares a separate verified copy. Current data is retained. Switching to that copy and publishing frozen archives are not available yet.', 'h3-mgmt') . '</p>';
        if ($status === InstallationStatus::Fresh || (new Installer($db))->pendingFresh()) {
            echo '<p><button type="button" class="button button-primary" data-operation="install">' . esc_html__('Set up plugin tables', 'h3-mgmt') . '</button></p>';
        } elseif ($status === InstallationStatus::Ready) {
            echo '<p><button type="button" class="button" data-operation="preflight">' . esc_html__('Check existing data', 'h3-mgmt') . '</button></p>';
            echo '<p><label><input type="checkbox" data-backup> ' . esc_html__('A restorable backup is available.', 'h3-mgmt') . '</label></p>';
            echo '<p><label><input type="checkbox" data-quiesced> ' . esc_html__('Source writes, background jobs and external writers are stopped for the complete copy and verification.', 'h3-mgmt') . '</label></p>';
            echo '<p><button type="button" class="button button-primary" data-operation="start">' . esc_html__('Prepare migration copy', 'h3-mgmt') . '</button></p>';
            if ($run !== null) {
                echo '<p><button type="button" class="button" data-operation="step" data-run="' . esc_attr($run) . '">' . esc_html__('Resume existing copy', 'h3-mgmt') . '</button> ';
                echo '<button type="button" class="button" data-operation="abandon" data-run="' . esc_attr($run) . '">' . esc_html__('Close this run and retain its data', 'h3-mgmt') . '</button></p>';
            }
        }
        echo '<pre role="status" aria-live="polite" data-report></pre></section>';
    }
}
