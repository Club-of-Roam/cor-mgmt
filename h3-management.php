<?php

/*
Plugin Name: HitchHikingHub Management
Plugin URI: https://github.com/Club-of-Roam/cor-mgmt
Description: Core of the "HitchHikingHub", events/races, user profiles, team profiles
Version: 1.2.10
Requires PHP: 8.2
Requires at least: 6.3
Author: Club of Roam – Autostop! e.V.
Author URI: https://tramprennen.org/
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Update URI: https://github.com/Club-of-Roam/cor-mgmt
Text Domain: h3-mgmt
Domain Path: /languages
*/

if (!defined('ABSPATH')) { exit; }

/**
 * Holds the absolute location of HitchHikingHub Management
 *
 * @since 1.0
 */
if ( !defined( 'H3_MGMT_ABSPATH' ) ) {
	define( 'H3_MGMT_ABSPATH', dirname( __FILE__ ) );
}

/**
 * Holds the URL of HitchHikingHub Management
 *
 * @since 1.0
 */
if ( !defined( 'H3_MGMT_RELPATH' ) ) {
	define( 'H3_MGMT_RELPATH', plugin_dir_url( __FILE__ ) );
}

/**
 * Holds the name of the HitchHikingHub Management directory
 *
 * @since 1.0
 */
if ( !defined( 'H3_MGMT_DIRNAME' ) ) {
	define( 'H3_MGMT_DIRNAME', basename( H3_MGMT_ABSPATH ) );
}

// The entry file remains parseable before loading PHP-8.2 implementation files.
if (version_compare(PHP_VERSION, '8.2', '<') || !is_file(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('cor-mgmt requires PHP 8.2 or newer and a complete plugin package. Reinstall the complete package if necessary.', 'h3-mgmt') . '</p></div>';
    });
    register_activation_hook(__FILE__, static function () {
        wp_die(esc_html__('cor-mgmt requires PHP 8.2 or newer and a complete plugin package.', 'h3-mgmt'));
    });
    return;
}
require_once __DIR__ . '/vendor/autoload.php';

$h3_mgmt_db_version = \Cor\Mgmt\Infrastructure\WordPress\SchemaInspector::LEGACY_SCHEMA;
\Cor\Mgmt\WordPress\Lifecycle::register(__FILE__);

// Compatibility APIs never run schema changes or erase data.
function h3_mgmt_install() {
    return \Cor\Mgmt\WordPress\Lifecycle::activate();
}
function h3_mgmt_update_db_check() {
    global $wpdb;
    return (new \Cor\Mgmt\Infrastructure\WordPress\SchemaInspector($wpdb))->status();
}
function h3_mgmt_uninstall() {
    // Retain data, including the schema marker, for later reinstallation.
}

if (!\Cor\Mgmt\WordPress\Lifecycle::ready()) {
    add_action('init', static function () {
        $tags = ['h3-donation-counter', 'h3-event-statistics', 'h3-handle-betterplace-redirect', 'h3-list-sponsors', 'h3-liveticker', 'h3-mate-xchange', 'h3-rand-team', 'h3-ranking-table', 'h3-recent-sponsors', 'h3-sponsoring-form', 'h3-stages', 'h3-stages-isotope', 'h3-submit-rankings', 'h3-team-dashboard', 'h3-team-dashboard-homework', 'h3-team-homework', 'h3-teams', 'h3-ticker-datenbank-change', 'h3-ticker-incoming-interface', 'h3-ticker-map', 'h3-ticker-message', 'h3-ticker-send-message-link', 'h3-user-panel-link'];
        foreach ($tags as $tag) {
            add_shortcode($tag, static function () {
                return '<p>' . esc_html__('This event service is not available yet.', 'h3-mgmt') . '</p>';
            });
        }
    });
    return;
}
require_once __DIR__ . '/runtime/legacy.php';
