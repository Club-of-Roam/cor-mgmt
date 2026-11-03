<?php
/** Existing entry points retained behind the runtime composition root. @package CorMgmt */
/**
 * Admin UI
 *
 * @since 1.0
 */
require_once H3_MGMT_ABSPATH . "/includes/class-h3-mgmt-security.php";
H3_MGMT_Security::register();
require_once H3_MGMT_ABSPATH . "/includes/class-h3-mgmt-media.php";
if ( is_admin() ) {
	/* functional classes (usually insantiated only once) */
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin.php' );
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-emails.php' );
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-races.php' );
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-sponsors.php' );
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-teams.php' );
	require_once( H3_MGMT_ABSPATH . '/admin/class-h3-mgmt-admin-statistics.php' );

	/* template classes (non-OOP templates are included on the spot) */
	require_once( H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-form.php' );
	require_once( H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-metaboxes.php' );
	require_once( H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-page.php' );
	require_once( H3_MGMT_ABSPATH . '/templates/class-h3-mgmt-admin-table.php' );

	/**
	 * h3_mgmt_admin object
	 *
	 * @since 1.0
	 */
	$GLOBALS['h3_mgmt_admin']            = new H3_MGMT_Admin();
	$GLOBALS['h3_mgmt_admin_emails']     = new H3_MGMT_Admin_Emails();
	$GLOBALS['h3_mgmt_admin_races']      = new H3_MGMT_Admin_Races();
	$GLOBALS['h3_mgmt_admin_sponsors']   = new H3_MGMT_Admin_Sponsors();
	$GLOBALS['h3_mgmt_admin_teams']      = new H3_MGMT_Admin_Teams();
	$GLOBALS['h3_mgmt_admin_statistics'] = new H3_MGMT_Admin_Statistics();
}

/**
 * Enqueue the plugin's javascript
 *
 * @since 1.0
 */
function h3_mgmt_enqueue() {
	/* register library scripts */
	wp_register_script( 'isotope', H3_MGMT_RELPATH . 'js/isotope.pkgd.min.js', [ 'jquery-core' ], '3.0.6', [
		'strategy'  => 'async',
		'in_footer' => true,
	] );
	wp_register_script( 'leaflet', H3_MGMT_RELPATH . 'lib/leaflet/leaflet.js', [], '1.9.4', [
		'strategy'  => 'async',
		'in_footer' => true,
	] );

	/* register custom scripts */
	wp_register_script( 'h3-mgmt-isotope', H3_MGMT_RELPATH . 'js/h3-mgmt-isotope.js', [
		'jquery-core',
		'isotope'
	], '1.1.3', [
		'strategy'  => 'defer',
		'in_footer' => true,
	] );
	wp_register_script( 'h3-mgmt-donation-selector', H3_MGMT_RELPATH . 'js/h3-mgmt-donation-selector.js', [ 'jquery-core' ], '1.2', true );
	wp_register_script( 'h3-mgmt-donation-counter', H3_MGMT_RELPATH . 'js/h3-mgmt-counter.js', [ 'jquery-core' ], '1.1.3', true );
	wp_register_script( 'h3-mgmt-resize', H3_MGMT_RELPATH . 'js/resize.js', [], '1.0' );
	wp_register_script( 'h3-mgmt-app', H3_MGMT_RELPATH . 'js/app.js', [], '1.2' );
wp_localize_script('h3-mgmt-app', 'corMgmtMedia', ['url'=>admin_url('admin-ajax.php')]);
	wp_register_script( 'h3-mgmt-blob', H3_MGMT_RELPATH . 'js/vendor/canvas-to-blob.min.js', [], '1.0' );
	wp_register_script( 'h3-mgmt-location', H3_MGMT_RELPATH . 'js/h3-mgmt-location.js', [], '1.1' );
	wp_register_script( 'h3-mgmt-map', H3_MGMT_RELPATH . 'js/h3-mgmt-map.js', [ 'leaflet' ], '1.3', [
		'strategy'  => 'defer',
		'in_footer' => true,
	] );
	wp_register_script( 'h3-mgmt-ticker', H3_MGMT_RELPATH . 'js/h3-mgmt-ticker.js', [ 'jquery-core' ], '1.0' );
	wp_register_script( 'h3-mgmt-sponsoring', H3_MGMT_RELPATH . 'js/h3-mgmt-sponsoring.js', [
		'jquery-core',
		'wp-i18n'
	], '1.0', true );
	wp_register_script( 'h3-mgmt-loading', H3_MGMT_RELPATH . 'js/h3-mgmt-loading.js', [], '1.0' );
	wp_register_script( 'h3-mgmt-redirect', H3_MGMT_RELPATH . 'js/h3-mgmt-redirect.js', [], '1.0' );

	/* set script translations */
	wp_set_script_translations( 'h3-mgmt-sponsoring', 'h3-mgmt', H3_MGMT_ABSPATH . '/languages' );

	/* register styles */
	wp_register_style( 'h3-mgmt-donation-counter-style', H3_MGMT_RELPATH . 'css/h3-mgmt-counter.css', [], '1.2' );
	wp_register_style( 'h3-mgmt-sponsoring', H3_MGMT_RELPATH . 'css/h3-mgmt-sponsoring.css', [], '1.2' );
	wp_register_style( 'h3-mgmt-stages', H3_MGMT_RELPATH . 'css/h3-mgmt-stages.css', [], '1.2' );
	wp_register_style( 'h3-mgmt-teams', H3_MGMT_RELPATH . 'css/h3-mgmt-teams.css', [], '1.1.2' );
	wp_register_style( 'h3-mgmt-ticker', H3_MGMT_RELPATH . 'css/h3-mgmt-ticker.css', [], '1.1.3' );
	wp_register_style( 'h3-mgmt-xchange', H3_MGMT_RELPATH . 'css/h3-mgmt-xchange.css', [], '1.1' );
	wp_register_style( 'h3-mgmt-ranking', H3_MGMT_RELPATH . 'css/h3-mgmt-ranking.css', [], '1.1' );
	wp_register_style('leaflet', H3_MGMT_RELPATH . 'lib/leaflet/leaflet.css', [], '1.9.4');

	/* enqueue custom scripts */
	wp_enqueue_script( 'h3-mgmt-isotope' );
	wp_enqueue_script( 'h3-mgmt-ticker' );
	wp_enqueue_script( 'h3-mgmt-sponsoring' );

	/* enqueue stylesheets */
	wp_enqueue_style( 'h3-mgmt-donation-counter-style' );
	wp_enqueue_style( 'h3-mgmt-sponsoring' );
	wp_enqueue_style( 'h3-mgmt-stages' );
	wp_enqueue_style( 'h3-mgmt-teams' );
	wp_enqueue_style( 'h3-mgmt-ticker' );
	wp_enqueue_style( 'h3-mgmt-xchange' );
	wp_enqueue_style( 'h3-mgmt-ranking' );
}

add_action( 'wp_enqueue_scripts', 'h3_mgmt_enqueue' );

function h3_mgmt_admin_enqueue() {
	$jqui_params  = [
		'monthNames'  => [
			_x( 'January', 'Months', 'h3-mgmt' ),
			_x( 'February', 'Months', 'h3-mgmt' ),
			_x( 'March', 'Months', 'h3-mgmt' ),
			_x( 'April', 'Months', 'h3-mgmt' ),
			_x( 'May', 'Months', 'h3-mgmt' ),
			_x( 'June', 'Months', 'h3-mgmt' ),
			_x( 'July', 'Months', 'h3-mgmt' ),
			_x( 'August', 'Months', 'h3-mgmt' ),
			_x( 'September', 'Months', 'h3-mgmt' ),
			_x( 'October', 'Months', 'h3-mgmt' ),
			_x( 'November', 'Months', 'h3-mgmt' ),
			_x( 'December', 'Months', 'h3-mgmt' ),
		],
		'dayNamesMin' => [
			_x( 'Sun', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Mon', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Tue', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Wed', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Thu', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Fri', 'Weekdays, Shortform', 'h3-mgmt' ),
			_x( 'Sat', 'Weekdays, Shortform', 'h3-mgmt' ),
		],
	];
	$admin_params = [
		'strings' => [
			'btnDeselect' => __( 'Deselect all', 'h3-mgmt' ),
			'btnSelect'   => __( 'Select all', 'h3-mgmt' ),
		],
	];

	/* register scripts */
	wp_register_script( 'h3-mgmt-admin', H3_MGMT_RELPATH . 'js/h3-mgmt-admin.js', [ 'jquery-core' ], '1.2', true );
	wp_register_script( 'h3-mgmt-ui', H3_MGMT_RELPATH . 'js/h3-mgmt-ui.js', [
		'jquery-core',
		'jquery-ui-slider',
		'jquery-ui-datepicker'
	], '1.1.1', true );
	wp_register_script( 'custom-field-instances', H3_MGMT_RELPATH . 'js/repeatable-custom-fields.js', [ 'jquery-core' ], '1.1.2', true );
	wp_register_script( 'h3-mgmt-admin-sponsors', H3_MGMT_RELPATH . 'js/h3-mgmt-admin-sponsors.js', [ 'jquery-core' ], '1.1.5', true );

	/* register styles */
	wp_register_style( 'jquery-ui-custom', H3_MGMT_RELPATH . 'css/jquery-ui-custom.css', [], '1.1' );
	wp_register_style( 'h3-mgmt-admin-style', H3_MGMT_RELPATH . 'css/h3-mgmt-admin.css', [], '1.1.5' );

	/* enqueue custom scripts */
	wp_enqueue_script( 'h3-mgmt-admin' );
	wp_enqueue_script( 'h3-mgmt-ui' );
	wp_enqueue_script( 'custom-field-instances' );

	/* enqueue stylesheets */
	wp_enqueue_style( 'jquery-ui-custom' );
	wp_enqueue_style( 'h3-mgmt-admin-style' );

	/* localize */
	wp_localize_script( 'h3-mgmt-admin', 'genericParams', $admin_params );
	wp_localize_script( 'h3-mgmt-ui', 'jquiParams', $jqui_params );
}

add_action( 'admin_enqueue_scripts', 'h3_mgmt_admin_enqueue' );

/**
 * Require needed files
 *
 * @since 1.0
 */
/* core of the plugin, frontend (usually insantiated only once)*/
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-mailer.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-profile.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-races.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-sponsors.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-teams.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-ticker.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-utilities.php' );
require_once( H3_MGMT_ABSPATH . '/includes/class-h3-mgmt-xchange.php' );

/**
 * H3_MGMT Objects
 *
 * @global object $h3_mgmt
 * @since 1.0
 */
$GLOBALS['h3_mgmt']           = new H3_MGMT();
$GLOBALS['h3_mgmt_utilities'] = new H3_MGMT_utilities();
H3_MGMT_Media::register();
require_once H3_MGMT_ABSPATH . "/includes/class-h3-mgmt-maintenance.php";
H3_MGMT_Maintenance::register();

