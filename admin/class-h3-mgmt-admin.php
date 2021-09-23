<?php

/**
 * H3_MGMT_Admin class.
 *
 * This class contains properties and methods to set up
 * the administration backend.
 *
 * @package VcA Activity & Supporter Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin' ) ) :

	class H3_MGMT_Admin {

		/**
		 * Displays admin menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function display_admin_menu() {
			global $h3_mgmt_admin_emails, $h3_mgmt_admin_races, $h3_mgmt_admin_sponsors, $h3_mgmt_admin_teams, $h3_mgmt_admin_statistics;

			/* Routes Menu*/
			add_menu_page(
				__( 'Events / Races', 'h3-mgmt' ),
				__( 'Events / Races', 'h3-mgmt' ),
				'h3_mgmt_view_races',
				'h3-mgmt-races',
				array( &$h3_mgmt_admin_races, 'races_control' ),
				H3_MGMT_RELPATH . 'img/backend-icons/icon-races_32.png',
				'2.1'
			);
			add_submenu_page(
				'h3-mgmt-races',
				__( 'Events / Races', 'h3-mgmt' ),
				__( 'Events / Races', 'h3-mgmt' ),
				'h3_mgmt_view_races',
				'h3-mgmt-races',
				array( &$h3_mgmt_admin_races, 'races_control' )
			);
			add_submenu_page(
				'h3-mgmt-races',
				__( 'Routes', 'h3-mgmt' ),
				__( 'Routes', 'h3-mgmt' ),
				'h3_mgmt_view_races',
				'h3-mgmt-routes',
				array( &$h3_mgmt_admin_races, 'routes_control' )
			);
			add_submenu_page(
				'h3-mgmt-races',
				__( 'Stages', 'h3-mgmt' ),
				__( 'Stages', 'h3-mgmt' ),
				'h3_mgmt_view_races',
				'h3-mgmt-stages',
				array( &$h3_mgmt_admin_races, 'stages_control' )
			);

			/* Teams Menu*/
			add_menu_page(
				__( 'Teams', 'h3-mgmt' ),
				__( 'Teams', 'h3-mgmt' ),
				'h3_mgmt_view_teams',
				'h3-mgmt-teams',
				array( &$h3_mgmt_admin_teams, 'teams_control' ),
				H3_MGMT_RELPATH . 'img/backend-icons/icon-teams_32.png',
				'2.2'
			);
			add_submenu_page(
				'h3-mgmt-teams',
				__( 'Teams', 'h3-mgmt' ),
				__( 'Teams', 'h3-mgmt' ),
				'h3_mgmt_view_teams',
				'h3-mgmt-teams',
				array( &$h3_mgmt_admin_teams, 'teams_control' )
			);
			add_submenu_page(
				'h3-mgmt-teams',
				__( 'Participants', 'h3-mgmt' ),
				__( 'Participants', 'h3-mgmt' ),
				'h3_mgmt_view_teams',
				'h3-mgmt-participants',
				array( &$h3_mgmt_admin_teams, 'participants_control' )
			);

			/* Sponsors Menu*/
			add_menu_page(
				__( 'Sponsoring Method "Betterplace"', 'h3-mgmt' ),
				__( 'Donors', 'h3-mgmt' ),
				'h3_mgmt_view_sponsors',
				'h3-mgmt-sponsors-betterplace',
				array( &$h3_mgmt_admin_sponsors, 'betterplace_control' ),
				H3_MGMT_RELPATH . 'img/backend-icons/icon-donations_32.png',
				'2.3'
			);
			add_submenu_page(
				'h3-mgmt-sponsors-betterplace',
				__( 'Sponsoring Method "Betterplace"', 'h3-mgmt' ),
				__( 'Betterplace', 'h3-mgmt' ),
				'h3_mgmt_view_sponsors',
				'h3-mgmt-sponsors-betterplace',
				array( &$h3_mgmt_admin_sponsors, 'betterplace_control' )
			);
			add_submenu_page(
				'h3-mgmt-sponsors-betterplace',
				__( 'Sponsoring Method "Direct Debit"', 'h3-mgmt' ),
				__( 'Direct Debit', 'h3-mgmt' ),
				'h3_mgmt_view_sponsors',
				'h3-mgmt-sponsors-debit',
				array( &$h3_mgmt_admin_sponsors, 'debit_control' )
			);
			add_submenu_page(
				'h3-mgmt-sponsors-betterplace',
				__( 'Sponsoring Method "PayPal"', 'h3-mgmt' ),
				__( 'PayPal', 'h3-mgmt' ),
				'h3_mgmt_view_sponsors',
				'h3-mgmt-sponsors-paypal',
				array( &$h3_mgmt_admin_sponsors, 'paypal_control' )
			);
			add_submenu_page(
				'h3-mgmt-sponsors-betterplace',
				__( 'TeamSponsors &amp; TeamOwners', 'h3-mgmt' ),
				__( 'All Donors', 'h3-mgmt' ),
				'h3_mgmt_view_sponsors',
				'h3-mgmt-sponsors',
				array( &$h3_mgmt_admin_sponsors, 'sponsors_control' )
			);

			/* Emails Menu */
			add_menu_page(
				__( 'Emails', 'h3-mgmt' ),
				__( 'Emails', 'h3-mgmt' ),
				'h3_mgmt_send_emails',
				'h3-mgmt-emails',
				array( &$h3_mgmt_admin_emails, 'mail_form' ),
				H3_MGMT_RELPATH . 'img/backend-icons/icon-mails_32.png',
				'2.4'
			);
			add_submenu_page(
				'h3-mgmt-emails',
				__( 'Send an E-Mail', 'h3-mgmt' ),
				__( 'Send Mail', 'h3-mgmt' ),
				'h3_mgmt_send_emails',
				'h3-mgmt-emails',
				array( &$h3_mgmt_admin_emails, 'mail_form' )
			);
			add_submenu_page(
				'h3-mgmt-emails',
				__( 'Auto Responses', 'h3-mgmt' ),
				__( 'Auto Responses', 'h3-mgmt' ),
				'h3_mgmt_edit_autoresponses',
				'h3-mgmt-emails-autoresponses',
				array( &$h3_mgmt_admin_emails, 'autoresponses_edit' )
			);

			/* Statisticcs */
			add_menu_page(
				__( 'Statistics', 'h3-mgmt' ),
				__( 'Statistics', 'h3-mgmt' ),
				'h3-mgmt-view-statistics',
				'h3-mgmt-statistics',
				array( &$h3_mgmt_admin_statistics, 'statistics' ),
				H3_MGMT_RELPATH . 'img/backend-icons/icon-teams_32.png',
				'2.5'
			);
		}

		/**
		 * Converts message arrays into html output
		 *
		 * @since 1.1
		 * @access public
		 */
		public function convert_messages( $messages = array() ) {
			$output = '';

			foreach ( $messages as $message ) {
				$output .= '<div class="' . $message['type'] . '"><p>' .
					$message['message'] .
				'</p></div>';
			}

			return $output;
		}

		/**
		 * PHP5 style constructor
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'admin_menu', array( &$this, 'display_admin_menu' ) );
		}

	} // class

endif; // class exists


