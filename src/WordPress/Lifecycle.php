<?php

/** Data-preserving activation, deactivation and setup reporting. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Requirements;
use Cor\Mgmt\Domain\InstallationStatus;
use Cor\Mgmt\Infrastructure\WordPress\SchemaInspector;
use WP_Error;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue;
use Cor\Mgmt\Application\Migration\MigrationException;

final class Lifecycle
{
    public const CRON_HOOKS = ['cor_mgmt_process_mail_queue', 'cor_mgmt_run_scheduled_tasks'];

    /** @return list<string> */
    public static function prerequisiteErrors(): array
    {
        global $wpdb, $wp_version;
        return Requirements::errors(PHP_VERSION, (string) $wp_version, $wpdb->db_server_info(), get_loaded_extensions(), is_multisite());
    }

    public static function register(string $plugin): void
    {
        SchemaTools::register();
        DonationAdmin::register();
        CommunicationAdmin::register();
        ProfileAvatar::register();
        ResultBoundary::register();
        register_activation_hook($plugin, [self::class, 'activationHook']);
        register_deactivation_hook($plugin, [self::class, 'deactivate']);
        add_action('admin_notices', [self::class, 'notice']);
        add_action('admin_menu', static function (): void {

            add_management_page(__('cor-mgmt system status', 'h3-mgmt'), __('cor-mgmt system status', 'h3-mgmt'), 'manage_options', 'cor-mgmt-system', [self::class, 'page']);
        });
    }

    public static function activationHook(bool $network_wide = false): void
    {
        $result = self::activate($network_wide);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), '', ['response' => 400, 'back_link' => true]);
        }
    }

    public static function activate(bool $network_wide = false): bool|WP_Error
    {
        if ($network_wide || self::prerequisiteErrors()) {
            return new WP_Error('cor_mgmt_requirements', __('Activation requirements are not met. Check the system status.', 'h3-mgmt'));
        }
        global $wpdb;
        $status = (new SchemaInspector($wpdb))->status();
        $administrator = get_role('administrator');
        if (!$administrator) {
            return new WP_Error('cor_mgmt_role', __('The administrator role is unavailable.', 'h3-mgmt'));
        }
        $database = new Database($wpdb);
        $storage = $database->option('cor_mgmt_storage_version');
        $generation = $database->option('cor_mgmt_active_generation');
        if (($storage !== null && $storage !== Catalogue::STORAGE_VERSION) || ($generation !== null && $generation !== '')) {
            return new WP_Error('cor_mgmt_storage_review', __('This installation requires its matching migrated plugin version.', 'h3-mgmt'));
        }
        $installer = new Installer($database);
        if ($status === InstallationStatus::Fresh || $installer->pendingFresh()) {
            try {
                $installer->fresh();
            } catch (MigrationException) {
                return new WP_Error('cor_mgmt_installation_failed', __('Database setup could not finish. Review the system status; existing data is retained.', 'h3-mgmt'));
            }
        } elseif ($status !== InstallationStatus::Ready) {
            return new WP_Error('cor_mgmt_schema_review', __('The existing installation needs a controlled schema review. No data was changed.', 'h3-mgmt'));
        }
        try {
            $installer->participation();
            $installer->results();
            $installer->donations();
            $installer->communication();
            $installer->mailQueue();
        } catch (MigrationException) {
            return new WP_Error('cor_mgmt_participation_setup', __('Additional module setup requires review. Existing data is retained.', 'h3-mgmt'));
        }
        foreach (Capabilities::all() as $capability) {
            $administrator->add_cap($capability);
        }
        return true;
    }

    public static function deactivate(): void
    {
        foreach (self::CRON_HOOKS as $hook) {
            wp_unschedule_hook($hook);
        }
    }

    public static function ready(): bool
    {
        global $wpdb;
        $database = new Database($wpdb);
        $storage = $database->option('cor_mgmt_storage_version');
        $generation = $database->option('cor_mgmt_active_generation');
        return ($storage === null || $storage === Catalogue::STORAGE_VERSION) && ($generation === null || $generation === '')
            && !self::prerequisiteErrors() && !(new Installer(new Database($wpdb)))->pendingFresh()
            && (new SchemaInspector($wpdb))->status() === InstallationStatus::Ready;
    }

    public static function notice(): void
    {
        global $wpdb;
        if (!current_user_can('manage_options') || (self::ready() && \Cor\Mgmt\Infrastructure\WordPress\Schema\ParticipationSchema::ready(new Database($wpdb)) && \Cor\Mgmt\Infrastructure\WordPress\Schema\ResultsSchema::ready(new Database($wpdb)) && \Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema::ready(new Database($wpdb)) && \Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema::ready(new Database($wpdb)) && \Cor\Mgmt\Infrastructure\WordPress\Schema\MailQueueSchema::ready(new Database($wpdb)))) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('cor-mgmt requires setup or a controlled schema review. Open Tools > cor-mgmt system status.', 'h3-mgmt') . '</p></div>';
    }

    private static function statusMessage(InstallationStatus $status): string
    {
        return match ($status) {
            InstallationStatus::Ready => __('The existing schema is recognized. No migration has been performed.', 'h3-mgmt'),
            InstallationStatus::Fresh => __('This site has no cor-mgmt database yet. Set up the plugin tables below.', 'h3-mgmt'),
            InstallationStatus::Incomplete => __('The existing database is incomplete or could not be checked. Review it before continuing.', 'h3-mgmt'),
            InstallationStatus::UpgradeRequired => __('The existing database requires a controlled upgrade.', 'h3-mgmt'),
            InstallationStatus::NewerSchema => __('This database belongs to a newer plugin version. Install a compatible plugin package.', 'h3-mgmt'),
        };
    }

    private static function requirementMessage(string $error): string
    {
        return match ($error) {
            'php_version' => __('PHP 8.2 or newer is required.', 'h3-mgmt'),
            'wordpress_version' => __('WordPress 6.3 or newer is required.', 'h3-mgmt'),
            'database_version' => __('MariaDB 10.11 or MySQL 8.0.13 or newer is required.', 'h3-mgmt'),
            'mbstring' => __('The PHP mbstring extension is required.', 'h3-mgmt'),
            'multisite' => __('Multisite setup is not available in this development version.', 'h3-mgmt'),
            default => __('A system requirement needs review.', 'h3-mgmt'),
        };
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response' => 403]);
        }
        global $wpdb;
        $status = (new SchemaInspector($wpdb))->status();
        echo '<div class="wrap"><h1>' . esc_html__('cor-mgmt system status', 'h3-mgmt') . '</h1><p>' . esc_html(self::statusMessage($status)) . '</p>';
        foreach (self::prerequisiteErrors() as $error) {
            echo '<p>' . esc_html(self::requirementMessage($error)) . '</p>';
        }
        if ($status !== InstallationStatus::Ready) {
            echo '<p>' . esc_html__('Use the database tools below. Unrecognized existing data requires review; it is never overwritten.', 'h3-mgmt') . '</p>';
        }
        SchemaTools::render($status);
        echo '</div>';
    }
}
