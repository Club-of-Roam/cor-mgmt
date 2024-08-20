<?php

/*
Plugin Name: HitchHikingHub Management
Plugin URI: https://github.com/Club-of-Roam/cor-mgmt
Description: Core of the "HitchHikingHub", events/races, user profiles, team profiles
Version: 1.2.3
Author: Johannes Pilkahn
Author URI: http://nekkidgrandma.com
License: GPL3
*/

/*  Copyright 2012-2013  Johannes Pilkahn  (email : pille@nekkidgrandma.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 3, as
	published by the Free Software Foundation.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Holds the absolute location of HitchHikingHub Management
 *
 * @since 1.0
 */
if (!defined('H3_MGMT_ABSPATH')) {
    define('H3_MGMT_ABSPATH', dirname(__FILE__));
}

/**
 * Holds the URL of HitchHikingHub Management
 *
 * @since 1.0
 */
if (!defined('H3_MGMT_RELPATH')) {
    define('H3_MGMT_RELPATH', plugin_dir_url(__FILE__));
}

/**
 * Holds the name of the HitchHikingHub Management directory
 *
 * @since 1.0
 */
if (!defined('H3_MGMT_DIRNAME')) {
    define('H3_MGMT_DIRNAME', basename(H3_MGMT_ABSPATH));
}

/**
 * Admin UI
 *
 * @since 1.0
 */
if (is_admin()) {
    /* functional classes (usually insantiated only once) */
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin.php');
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-emails.php');
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-races.php');
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-sponsors.php');
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-teams.php');
    require_once(H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-statistics.php');

    /* template classes (non-OOP templates are included on the spot) */
    require_once(H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-form.php');
    require_once(H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-metaboxes.php');
    require_once(H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-page.php');
    require_once(H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-table.php');

    /**
     * h3_mgmt_admin object
     *
     * @since 1.0
     */
    $GLOBALS['h3_mgmt_admin'] = new H3_MGMT_Admin();
    $GLOBALS['h3_mgmt_admin_emails'] = new H3_MGMT_Admin_Emails();
    $GLOBALS['h3_mgmt_admin_races'] = new H3_MGMT_Admin_Races();
    $GLOBALS['h3_mgmt_admin_sponsors'] = new H3_MGMT_Admin_Sponsors();
    $GLOBALS['h3_mgmt_admin_teams'] = new H3_MGMT_Admin_Teams();
    $GLOBALS['h3_mgmt_admin_statistics'] = new H3_MGMT_Admin_Statistics();
}

/**
 * Enqueue the plugin's javascript
 *
 * @since 1.0
 */
function h3_mgmt_enqueue()
{
    /* register scripts */
    wp_register_script('isotope', H3_MGMT_RELPATH . 'js/jquery.isotope.min.js', ['jquery'], '1.0', true);
    wp_register_script('h3-mgmt-isotope', H3_MGMT_RELPATH . 'js/h3-mgmt-isotope.js', ['jquery', 'isotope'], '1.1.2', true);
    wp_register_script('h3-mgmt-donation-selector', H3_MGMT_RELPATH . 'js/h3-mgmt-donation-selector.js', ['jquery'], '1.2', true);
    wp_register_script('h3-mgmt-donation-counter', H3_MGMT_RELPATH . 'js/h3-mgmt-counter.js', ['jquery'], '1.1.3', true);
    wp_register_script('h3-mgmt-resize', H3_MGMT_RELPATH . 'js/resize.js', [], '1.0');
    wp_register_script('h3-mgmt-app', H3_MGMT_RELPATH . 'js/app.js', [], '1.0');
    wp_register_script('h3-mgmt-blob', H3_MGMT_RELPATH . 'js/vendor/canvas-to-blob.min.js', [], '1.0');
    wp_register_script('h3-mgmt-location', H3_MGMT_RELPATH . 'js/h3-mgmt-location.js', [], '1.0');
    wp_register_script('h3-mgmt-map', H3_MGMT_RELPATH . 'js/h3-mgmt-map.js', [], '1.1');
    wp_register_script('h3-mgmt-ticker', H3_MGMT_RELPATH . 'js/h3-mgmt-ticker.js', ['jquery'], '1.0');
    wp_register_script('h3-mgmt-sponsoring', H3_MGMT_RELPATH . 'js/h3-mgmt-sponsoring.js', ['jquery', 'wp-i18n'], '1.0', true);
    wp_register_script('h3-mgmt-loading', H3_MGMT_RELPATH . 'js/h3-mgmt-loading.js', [], '1.0');
    wp_register_script('h3-mgmt-redirect', H3_MGMT_RELPATH . 'js/h3-mgmt-redirect.js', [], '1.0');
    wp_register_script('googlemap', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDtdxfnAWhpou6zyzlRcMkZfxwbgrdvhnE&libraries=marker&v=quarterly');
    wp_register_script('google-jsapi', 'https://www.google.com/jsapi');
    /* register styles */
    wp_register_style('h3-mgmt-donation-counter-style', H3_MGMT_RELPATH . 'css/h3-mgmt-counter.css', [], '1.2');
    wp_register_style('h3-mgmt-sponsoring', H3_MGMT_RELPATH . 'css/h3-mgmt-sponsoring.css', [], '1.2');
    wp_register_style('h3-mgmt-stages', H3_MGMT_RELPATH . 'css/h3-mgmt-stages.css', [], '1.2');
    wp_register_style('h3-mgmt-teams', H3_MGMT_RELPATH . 'css/h3-mgmt-teams.css', [], '1.1.2');
    wp_register_style('h3-mgmt-ticker', H3_MGMT_RELPATH . 'css/h3-mgmt-ticker.css', [], '1.1.3');
    wp_register_style('h3-mgmt-xchange', H3_MGMT_RELPATH . 'css/h3-mgmt-xchange.css', [], '1.1');
    wp_register_style('h3-mgmt-ranking', H3_MGMT_RELPATH . 'css/h3-mgmt-ranking.css', [], '1.1');
    /* enqueue custom scripts */
    /* enqueue custom scripts */
    wp_enqueue_script('isotope');
    wp_enqueue_script('h3-mgmt-isotope');
    wp_enqueue_script('h3-mgmt-ticker');
    wp_enqueue_script('h3-mgmt-sponsoring');
    /* enqueue stylesheets */
    wp_enqueue_style('h3-mgmt-donation-counter-style');
    wp_enqueue_style('h3-mgmt-sponsoring');
    wp_enqueue_style('h3-mgmt-stages');
    wp_enqueue_style('h3-mgmt-teams');
    wp_enqueue_style('h3-mgmt-ticker');
    wp_enqueue_style('h3-mgmt-xchange');
    wp_enqueue_style('h3-mgmt-ranking');
}

add_action('wp_enqueue_scripts', 'h3_mgmt_enqueue');

function h3_mgmt_admin_enqueue()
{
    $jqui_params = [
        'monthNames' => [
            _x('January', 'Months', 'h3-mgmt'),
            _x('February', 'Months', 'h3-mgmt'),
            _x('March', 'Months', 'h3-mgmt'),
            _x('April', 'Months', 'h3-mgmt'),
            _x('May', 'Months', 'h3-mgmt'),
            _x('June', 'Months', 'h3-mgmt'),
            _x('July', 'Months', 'h3-mgmt'),
            _x('August', 'Months', 'h3-mgmt'),
            _x('September', 'Months', 'h3-mgmt'),
            _x('October', 'Months', 'h3-mgmt'),
            _x('November', 'Months', 'h3-mgmt'),
            _x('December', 'Months', 'h3-mgmt'),
        ],
        'dayNamesMin' => [
            _x('Sun', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Mon', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Tue', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Wed', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Thu', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Fri', 'Weekdays, Shortform', 'h3-mgmt'),
            _x('Sat', 'Weekdays, Shortform', 'h3-mgmt'),
        ],
    ];
    $admin_params = [
        'strings' => [
            'btnDeselect' => __('Deselect all', 'h3-mgmt'),
            'btnSelect' => __('Select all', 'h3-mgmt'),
        ],
    ];

    /* register scripts */
    wp_register_script('h3-mgmt-admin', H3_MGMT_RELPATH . 'js/h3-mgmt-admin.js', ['jquery'], '1.1', true);
    wp_register_script('h3-mgmt-ui', H3_MGMT_RELPATH . 'js/h3-mgmt-ui.js', ['jquery', 'jquery-ui-slider', 'jquery-ui-datepicker'], '1.1.1', true);
    wp_register_script('custom-field-instances', H3_MGMT_RELPATH . 'js/repeatable-custom-fields.js', ['jquery'], '1.1.2', true);
    wp_register_script('h3-mgmt-admin-sponsors', H3_MGMT_RELPATH . 'js/h3-mgmt-admin-sponsors.js', ['jquery'], '1.1.5', true);
    /* register styles */
    wp_register_style('jquery-ui-custom', H3_MGMT_RELPATH . 'css/jquery-ui-custom.css', [], '1.1');
    wp_register_style('h3-mgmt-admin-style', H3_MGMT_RELPATH . 'css/h3-mgmt-admin.css', [], '1.1.5');
    /* enqueue core scripts */
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('jquery-ui-slider');
    /* enqueue custom scripts */
    wp_enqueue_script('h3-mgmt-admin');
    wp_enqueue_script('h3-mgmt-ui');
    wp_enqueue_script('custom-field-instances');
    /* enqueue stylesheets */
    wp_enqueue_style('jquery-ui-custom');
    wp_enqueue_style('h3-mgmt-admin-style');

    /* localize */
    wp_localize_script('h3-mgmt-admin', 'genericParams', $admin_params);
    wp_localize_script('h3-mgmt-ui', 'jquiParams', $jqui_params);
}

add_action('admin_enqueue_scripts', 'h3_mgmt_admin_enqueue');

/**
 * Require needed files
 *
 * @since 1.0
 */
/* core of the plugin, frontend (usually insantiated only once)*/
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-mailer.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-profile.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-races.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-sponsors.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-teams.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-ticker.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-utilities.php');
require_once(H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-xchange.php');

/**
 * H3_MGMT Objects
 *
 * @global object $h3_mgmt
 * @since 1.0
 */
$GLOBALS['h3_mgmt'] = new H3_MGMT();
$GLOBALS['h3_mgmt_utilities'] = new H3_MGMT_utilities();

/**
 * Define globals
 *
 * @since 1.0
 */
$h3_mgmt_db_version = '2.8';

/**
 * Installation & Update Routines
 *
 * Creates and/or updates plugin's tables.
 * The install method is only triggered on plugin installation
 * and when the database version number
 * ( "$h3_mgmt_db_version", see above )
 * has changed.
 *
 * @since 1.0
 */
function h3_mgmt_install()
{
    global $wpdb, $h3_mgmt_db_version;

    $installed_ver = get_option('h3_mgmt_db_version');

    // if the plugin is not installed the db version is false
    if (false === $installed_ver) {

        /* SQL statements to create required tables */
        $sql = [];
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_auto_responses (
				id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               action tinytext NOT NULL ,
               language tinytext NOT NULL ,
               switch tinyint UNSIGNED ,
               subject text NOT NULL ,
               message longtext NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_invitations (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               team_id int UNSIGNED NOT NULL ,
               email varchar(255) NOT NULL ,
               code bigint NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_races (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               name varchar(255) NOT NULL ,
               start int UNSIGNED NOT NULL,
               end int UNSIGNED NOT NULL,
               logo_url varchar(255) NOT NULL,
               setting longtext DEFAULT NULL, 
               information_text longtext DEFAULT NULL,
               active int DEFAULT NULL,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_routes (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               race_id int UNSIGNED NOT NULL ,
               name varchar(255) NOT NULL ,
               color_code varchar(255) NOT NULL ,
               logo_url varchar(255) NOT NULL ,
               max_teams smallint NOT NULL ,
               user_id bigint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_stages (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               race_id int UNSIGNED NOT NULL ,
               route_id int UNSIGNED NOT NULL ,
               number tinyint UNSIGNED NOT NULL ,
               destination varchar(255) NOT NULL ,
               country varchar(255) NOT NULL ,
               meeting_point text NOT NULL ,
               country_3166_alpha_2 varchar(255) NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_sponsors (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               type tinytext NOT NULL ,
               method tinytext NOT NULL ,
               donation int UNSIGNED NOT NULL ,
               language tinytext NOT NULL, 
               display_name text NOT NULL ,
               first_name text NOT NULL ,
               last_name text NOT NULL ,
               account_id varchar(255) NOT NULL ,
               bank_id varchar(255) NOT NULL ,
               bank_name text NOT NULL ,
               paid tinyint UNSIGNED NOT NULL ,
               message text NOT NULL ,
               team_id int UNSIGNED NOT NULL ,
               var_show tinyint UNSIGNED NOT NULL ,
               race_id int UNSIGNED NOT NULL ,
               email varchar(255) NOT NULL ,
               owner_pic varchar(255) NOT NULL ,
               owner_link varchar(255) NOT NULL ,
               street text NOT NULL ,
               zip_code text NOT NULL ,
               city text NOT NULL ,
               country text NOT NULL ,
               address_additional text NOT NULL ,
               receipt tinyint UNSIGNED NOT NULL ,
               debit_confirmation tinyint UNSIGNED NOT NULL ,
               donation_client_reference varchar(255) NOT NULL ,
               donation_token varchar(255) NOT NULL ,
               timestamp datetime NOT NULL DEFAULT current_timestamp(), 
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_teams (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               description text NOT NULL ,
               race_id int UNSIGNED NOT NULL ,
               team_name text NOT NULL ,
               team_pic varchar(255) NOT NULL ,
               complete tinyint UNSIGNED NOT NULL ,
               route_id int UNSIGNED NOT NULL ,
               team_phone varchar(255) NOT NULL ,
               rank_stage_1 smallint UNSIGNED NOT NULL ,
               rank_stage_2 smallint UNSIGNED NOT NULL ,
               rank_stage_3 smallint UNSIGNED NOT NULL ,
               rank_stage_4 smallint UNSIGNED NOT NULL ,
               rank_stage_5 smallint UNSIGNED NOT NULL ,
               rank_stage_6 smallint UNSIGNED NOT NULL ,
               extra_stage_1 smallint UNSIGNED NOT NULL ,
               extra_stage_2 smallint UNSIGNED NOT NULL ,
               extra_stage_3 smallint UNSIGNED NOT NULL ,
               extra_stage_4 smallint UNSIGNED NOT NULL ,
               extra_stage_5 smallint UNSIGNED NOT NULL ,
               extra_stage_6 smallint UNSIGNED NOT NULL ,
               total_points int UNSIGNED NOT NULL ,
               meta_1 text NOT NULL,
               meta_2 text NOT NULL,
               meta_3 text NOT NULL,
               meta_4 text NOT NULL,
               meta_5 text NOT NULL,
               donation_goal int UNSIGNED NOT NULL,
               amount_extra_stage_1 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_2 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_3 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_4 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_5 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_6 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_1 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_2 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_3 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_4 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_5 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_6 tinyint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_teammates (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               team_id int UNSIGNED NOT NULL ,
               user_id int UNSIGNED NOT NULL ,
               paid tinyint UNSIGNED NOT NULL ,
               waiver tinyint UNSIGNED NOT NULL ,
               language tinyint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';
        $sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_xchange (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               user_id int UNSIGNED NOT NULL ,
               message text NOT NULL ,
               time timestamp on update CURRENT_TIMESTAMP ,
               UNIQUE KEY id (id)
		);';

        /* comparison of above with db, db adjustments */
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        /* works for now, but needs fixing */
        // $test = $wpdb->get_results(
        // 	'SELECT * FROM ' . $wpdb->prefix . 'h3_mgmt_auto_responses',
        // 	ARRAY_A
        // );
        // if ( ! isset( $test[0]['action'] ) ) {
        // 	$actions = array(
        // 		'team-creation',
        // 		'invitation',
        // 		'invitation-accepted-inviter',
        // 		'invitation-accepted-invitee',
        // 		'package-paid',
        // 		'waiver-reached',
        // 		'new-sponsor',
        // 		'new-owner',
        // 		'publishable',
        // 		'paypal-please-owner',
        // 		'paypal-please-sponsor',
        // 		//'paypal-please-patron',
        // 		//'paypal-please-structure',
        // 		'paypal-thanks',
        // 		'debit-thanks-owner',
        // 		'debit-thanks-sponsor',
        // 		//'debit-thanks-patron',
        // 		//'debit-thanks-structure'
        // 	);
        // 	foreach ( $actions as $action ) {
        // 		$wpdb->insert(
        // 			$wpdb->prefix . 'h3_mgmt_auto_responses',
        // 			array(
        // 				'action'   => $action,
        // 				'switch'   => 1,
        // 				'language' => 'en',
        // 			),
        // 			array( '%s', '%d' )
        // 		);
        // 		$wpdb->insert(
        // 			$wpdb->prefix . 'h3_mgmt_auto_responses',
        // 			array(
        // 				'action'   => $action,
        // 				'switch'   => 1,
        // 				'language' => 'de',
        // 			),
        // 			array( '%s', '%d' )
        // 		);
        // 	}
        // }
        add_option('h3_mgmt_db_version', $h3_mgmt_db_version);
    }
    update_option('h3_mgmt_db_version', $h3_mgmt_db_version);
}

register_activation_hook(__FILE__, 'h3_mgmt_install');

/**
 * Update Routine
 *
 * Checks if the databse is newer and will run the install routine again.
 *
 * @since 1.0
 */
function h3_mgmt_update_db_check()
{
    global $h3_mgmt_db_version;
    if (get_site_option('h3_mgmt_db_version') != $h3_mgmt_db_version) {
        h3_mgmt_install();
    }
}

add_action('plugins_loaded', 'h3_mgmt_update_db_check');

/**
 * Uninstall Routine
 *
 * Delete the added Database tables
 *
 * @since 1.0
 */
function h3_mgmt_uninstall()
{
    // drop a custom database table
    global $wpdb;

    delete_option('h3_mgmt_db_version');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_auto_responses');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_invitations');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_races');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_routes');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_stages');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_sponsors');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_teams');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_teammates');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'h3_mgmt_xchange');
}

register_uninstall_hook(__FILE__, 'h3_mgmt_uninstall');
