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
		global $h3_mgmt_admin_emails, $h3_mgmt_admin_races, $h3_mgmt_admin_sponsors, $h3_mgmt_admin_teams;

		/* Routes Menu*/
		add_menu_page(
			__( 'Events / Races', 'h3-mgmt' ),
			__( 'Events / Races', 'h3-mgmt' ),
			'h3_mgmt_view_races',
			'h3-mgmt-races',
			array( &$h3_mgmt_admin_races, 'races_control' ),
			H3_MGMT_RELPATH . 'img/backend-icons/icon-races_32.png',
			'9999.1'
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
			'9999.2'
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
			__( 'TeamSponsors &amp; TeamOwners', 'h3-mgmt' ),
			__( 'Donors', 'h3-mgmt' ),
			'h3_mgmt_view_sponsors',
			'h3-mgmt-sponsors',
			array( &$h3_mgmt_admin_sponsors, 'sponsors_control' ),
			H3_MGMT_RELPATH . 'img/backend-icons/icon-donations_32.png',
			'9999.3'
		);
		add_submenu_page(
			'h3-mgmt-sponsors',
			__( 'TeamSponsors &amp; TeamOwners', 'h3-mgmt' ),
			__( 'All Donors', 'h3-mgmt' ),
			'h3_mgmt_view_sponsors',
			'h3-mgmt-sponsors',
			array( &$h3_mgmt_admin_sponsors, 'sponsors_control' )
		);
		add_submenu_page(
			'h3-mgmt-sponsors',
			__( 'Sponsoring Method "Direct Debit"', 'h3-mgmt' ),
			__( 'Direct Debit', 'h3-mgmt' ),
			'h3_mgmt_view_sponsors',
			'h3-mgmt-sponsors-debit',
			array( &$h3_mgmt_admin_sponsors, 'debit_control' )
		);
		add_submenu_page(
			'h3-mgmt-sponsors',
			__( 'Sponsoring Method "PayPal"', 'h3-mgmt' ),
			__( 'PayPal', 'h3-mgmt' ),
			'h3_mgmt_view_sponsors',
			'h3-mgmt-sponsors-paypal',
			array( &$h3_mgmt_admin_sponsors, 'paypal_control' )
		);

		/* Emails Menu */
		add_menu_page(
			__( 'Emails', 'h3-mgmt' ),
			__( 'Emails', 'h3-mgmt' ),
			'h3_mgmt_send_emails',
			'h3-mgmt-emails',
			array( &$h3_mgmt_admin_emails, 'mail_form' ),
			H3_MGMT_RELPATH . 'img/backend-icons/icon-mails_32.png',
			'9999.4'
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

		/* Shirts */
		add_menu_page(
			__( 'T-Shirts', 'h3-mgmt' ),
			__( 'T-Shirts', 'h3-mgmt' ),
			'h3_mgmt_send_emails',
			'h3-mgmt-shirts',
			array( $this, 'shirts' ),
			H3_MGMT_RELPATH . 'img/backend-icons/icon-teams_32.png',
			'9999.5'
		);
	}
	public function shirts() {
		global $h3_mgmt_teams;

		$pids = $h3_mgmt_teams->get_participant_ids(2);
		$pids2012 = $h3_mgmt_teams->get_participant_ids(1);

		foreach( $pids2012 as $key => $pid ) {
			if(in_array($pid, $pids)) {
				unset($pids2012[$key]);
			}
		}

		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', "Teilnehmer 2013\n\n");
		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', "Teilnehmer 2013\n\n");

		$output = '<h3>Im Moment<br />(inkl. Teilnehmer mit unvollständigen Teams, exclusive Teilnehmer, die die Shirtgröße noch nicht gewählt haben)</h3><ul>';
		$sizes = array( 'all' => 0 );

		$all_mails = array();

		foreach($pids as $pid) {
			$user = new WP_User($pid);
			$mail = ! empty ($user->user_email) ? $user->user_email : '';
			if(! empty ($user->user_email)){
				$all_mails[] = $mail;
			//	file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', $mail."\n", FILE_APPEND | LOCK_EX);
			//	file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', $mail."; ", FILE_APPEND | LOCK_EX);
			}
			$size = get_user_meta( $pid, 'shirt_size', true );

			if( in_array( $size, array('gl','gm','gs','mm','ml','mx') ) ) {
				if( ! array_key_exists( $size, $sizes ) ) {
					$sizes[$size] = 1;
				} else {
					$sizes[$size] = $sizes[$size] + 1;
				}
				$sizes['all'] = $sizes['all'] + 1;
			}
		}

		foreach( $sizes as $key => $count ) {
			$output .= '<li>' . $key . ':&nbsp;&nbsp;' . $count . '</li>';
		}
		$output .= '</ul><h3>Hochrechnung auf 150</h3><ul>';

		$factor = 150 / $sizes['all'];
		foreach( $sizes as $key => $count ) {
			$output .= '<li>' . $key . ':&nbsp;&nbsp;' . $count * $factor . '</li>';
		}
		$output .= '</ul><h3>Hochrechnung auf 174</h3><ul>';

		$factor = 175 / $sizes['all'];
		foreach( $sizes as $key => $count ) {
			$output .= '<li>' . $key . ':&nbsp;&nbsp;' . $count * $factor . '</li>';
		}
		$output .= '</ul><h3>Hochrechnung auf 200</h3><ul>';

		$factor = 200 / $sizes['all'];
		foreach( $sizes as $key => $count ) {
			$output .= '<li>' . $key . ':&nbsp;&nbsp;' . $count * $factor . '</li>';
		}
		$output .= '</ul>';

		echo $output;

		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', "\n\nTeilnehmer 2012 (ohne die, die auch 2013 dabei waren)\n\n", FILE_APPEND | LOCK_EX);
		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', "\n\n\nTeilnehmer 2012 (ohne die, die auch 2013 dabei waren)\n\n", FILE_APPEND | LOCK_EX);

		foreach($pids2012 as $pid) {
			$user = new WP_User($pid);
			$mail = ! empty ($user->user_email) ? $user->user_email : '';
			if(! empty ($user->user_email)){
				$all_mails[] = $mail;
				//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', $mail."\n", FILE_APPEND | LOCK_EX);
				//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', $mail."; ", FILE_APPEND | LOCK_EX);
			}
		}

		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', "\n\nTeilnehmer 2011 (ohne die, die auch 2013 oder 2012 dabei waren)\n\n", FILE_APPEND | LOCK_EX);
		//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', "\n\n\nTeilnehmer 2011 (ohne die, die auch 2013 oder 2012 dabei waren)\n\n", FILE_APPEND | LOCK_EX);

		global $wpdb;

		$mails2011 = $wpdb->get_results(
			"SELECT email_1, email_2, email_3 FROM " . $wpdb->prefix . "tr11_teams",
			ARRAY_A
		);

		foreach($mails2011 as $teams2011) {
			foreach($teams2011 as $mail) {
				if(! in_array($mail, $all_mails) && !empty($mail)) {
					$all_mails[] = $mail;
					//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', $mail."\n", FILE_APPEND | LOCK_EX);
					//file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', $mail."; ", FILE_APPEND | LOCK_EX);
				}
			}
		}

		$mails_cc = $wpdb->get_results(
			"SELECT email FROM " . $wpdb->prefix . "tr_cc",
			ARRAY_A
		);

		file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', "\n\nTeilnehmer Costume Competition (ohne die, die auch 2013 oder 2012 dabei waren)\n\n", FILE_APPEND | LOCK_EX);
		file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', "\n\n\nTeilnehmer Costume Competition (ohne die, die auch 2013 oder 2012 dabei waren)\n\n", FILE_APPEND | LOCK_EX);

		foreach($mails_cc as $mail) {
			if(! in_array($mail['email'], $all_mails) && !empty($mail['email'])) {
				$all_mails[] = $mail['email'];
				file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_new_line.txt', $mail['email']."\n", FILE_APPEND | LOCK_EX);
				file_put_contents('/var/www/clients/client0/web136/web/teilnehmer/teilnehmer_semicolon_separated.txt', $mail['email']."; ", FILE_APPEND | LOCK_EX);
			}
		}
	}

	/**
	 * Converts message arrays into html output
	 *
	 * @since 1.1
	 * @access public
	 */
	public function convert_messages( $messages = array() ) {
		$output = '';

		foreach( $messages as $message ) {
			$output .= '<div class="' . $message['type'] . '"><p>' .
					$message['message'] .
				'</p></div>';
		}

		return $output;
	}

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function H3_MGMT_Admin() {
		$this->__construct();
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

?>