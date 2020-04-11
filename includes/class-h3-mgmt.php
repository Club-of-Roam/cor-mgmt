<?php

/**
 * H3_MGMT class.
 *
 * This class holds all H3_MGMT components.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT' ) ) :

class H3_MGMT {

	/**
	 * Initializes the plugin
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init() {
		/* add multilinguality support */
		load_plugin_textdomain( 'h3-mgmt', false, H3_MGMT_DIRNAME . '/languages/' );

		/* integrate into the "Members" plugin */
		if ( function_exists( 'members_get_capabilities' ) ) {
			add_filter( 'members_get_capabilities', array( &$this, 'extra_caps' ) );
		}

		/* HHH MGMT's @global objects (these need to be accessible from other classes) */
		$GLOBALS['h3_mgmt_mailer'] = new H3_MGMT_Mailer();
		$GLOBALS['h3_mgmt_races'] = new H3_MGMT_Races();
		$GLOBALS['h3_mgmt_sponsors'] = new H3_MGMT_Sponsors();
		$GLOBALS['h3_mgmt_teams'] = new H3_MGMT_Teams();
		$GLOBALS['h3_mgmt_ticker'] = new H3_MGMT_Ticker();

		/* HHH MGMT's objects */
		$h3_mgmt_profile = new H3_MGMT_Profile();
		$h3_mgmt_xchange = new H3_MGMT_XChange();
	}

	/**
	 * Adds plugin-specific user capabilities
	 *
	 * @since 1.0
	 * @access public
	 */
	public function extra_caps( $caps ) {
		$caps[] = 'h3_mgmt_send_emails';
		$caps[] = 'h3_mgmt_edit_autoresponses';
		$caps[] = 'h3_mgmt_view_races';
		$caps[] = 'h3_mgmt_edit_races';
		$caps[] = 'h3_mgmt_edit_own_races';
		$caps[] = 'h3_mgmt_delete_races';
		$caps[] = 'h3_mgmt_delete_own_races';
		$caps[] = 'h3_mgmt_view_teams';
		$caps[] = 'h3_mgmt_view_own_teams';
		$caps[] = 'h3_mgmt_edit_teams';
		$caps[] = 'h3_mgmt_edit_own_teams';
		$caps[] = 'h3_mgmt_view_sponsors';
		$caps[] = 'h3_mgmt_view_own_sponsors';
		$caps[] = 'h3_mgmt_edit_sponsors';
		$caps[] = 'h3_mgmt_edit_own_sponsors';
		$caps[] = 'h3_mgmt_delete_teams';
		$caps[] = 'h3_mgmt_delete_own_teams';
                $caps[] = 'h3-mgmt-view-statistics';
		return $caps;
	}

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function H3_MGMT() {
		$this->__construct();
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
	}
} // class

endif; // class exists

?>