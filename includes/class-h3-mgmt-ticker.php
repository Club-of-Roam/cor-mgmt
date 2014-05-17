<?php

/**
 * H3_MGMT_Ticker class.
 *
 * This class contains properties and methods for the SMS/MMS Live-Ticker.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Ticker' ) ) :

class H3_MGMT_Ticker {

	/*************** UTILITY METHODS ***************/

	/**
	 * Returns a phone number without whitespaces, zeroes or a plus sign
	 *
	 * @since 1.0
	 * @access public
	 */
	public function normalize_phone_number( $number, $nice = false ) {

	if( $nice === true ) {

			$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

			if( mb_substr( $number, 0, 1 ) == '+' ) {
				$number = $number;
			} elseif( mb_substr( $number, 0, 2 ) == '00' ) {
				$number = '+' . mb_substr( $number, 2 );
			} elseif( mb_substr( $number, 0, 1 ) == '0' ) {
				$number = '+49' . mb_substr( $number, 1 );
			}

			$number = mb_substr( $number, 0, 3 ) . ' ' . mb_substr( $number, 3, 3 ) . ' ' . mb_substr( $number, 6, 3 ) . ' ' . mb_substr( $number, 9, 3 ) . ' ' . mb_substr( $number, 12 );

	} else {

			$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

			if( mb_substr( $number, 0, 1 ) == '+' ) {
				$number = mb_substr( $number, 1 );
			} elseif( mb_substr( $number, 0, 2 ) == '00' ) {
				$number = mb_substr( $number, 2 );
			} elseif( mb_substr( $number, 0, 1 ) == '0' ) {
				$number = '49' . mb_substr( $number, 1 );
			}

	}

		return $number;
	}

	/**
	 * Returns an array of team phone numbers
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_phones( $team_id = 0, $route_id = 'all', $gimme = 'phones' ) {
		global $wpdb, $h3_mgmt_teams;

		if( $route_id === 'all' && $team_id === 0 ) {
			$where = '';
		} elseif( $team_id != 0 ) {
			$where = " WHERE id = " . $team_id;
		} else {
			$where = " WHERE route_id = " . $route_id;
		}

		$teams_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teams" . $where,
			ARRAY_A
		);

		$phones = array();
		$teams = array();

		foreach( $teams_query as $team ) {
			$phones[] = $this->normalize_phone_number( $team['team_phone'] );
			$teams[$this->normalize_phone_number( $team['team_phone'] )] = $team['id'];
			$user_ids = $h3_mgmt_teams->get_teammates( $team['id'] );
			foreach( $user_ids as $user_id ) {
				$phones[] = $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) );
			$teams[$this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) )] = $team['id'];
			}
		}

		if( $gimme === 'teams' ) {
			return $teams;
		} else {
			return $phones;
		}
	}

	/*************** TICKER OUTPUT ***************/

	/**
	 * Ticker shortcode handler
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function the_ticker( $atts = '' ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

		extract( shortcode_atts( array(
			'max' => 0,
			'route' => 'all',
			'race' => 2,
			'team' => 0,
			'show_nav' => 1
		), $atts ) );

		if( isset( $_GET['ticker_route'] ) ) {
			$route = $_GET['ticker_route'];
		}

		if( $team != 0 ) {
			$phones = $this->get_phones( $team );
		} elseif( $route != 'all' ) {
			$phones = $this->get_phones( 0, $route );
		} else {
			$phones = $this->get_phones();
		}

		$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."sms_ticker " .
			"ORDER BY id DESC",
			ARRAY_A
		);

		$messages = array();

		$phones2team = $this->get_phones( 0, 'all', 'teams' );

		$i = 0;
		foreach( $messages_query as $message ) {
			$norm_num = $this->normalize_phone_number( $message['from'] );
			if( in_array( $norm_num, $phones ) ) {
				$team_id = $phones2team[$norm_num];
				$display_data['team_name'] = '<a class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
					'" href="http://' . __( 'tramprennen.org/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
					$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
				$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
				$display_data['img_url'] = 'http://tramprennen.org/wp-content/uploads/mms/' . $message['img_url'];
				$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
				$display_data['message'] = stripslashes( $message['msg'] );
				$display_data['type'] = $message['type'];
				$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) + 3600 );
				$display_data['time'] = $message['timestamp'];
				$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
				$rgb = $h3_mgmt_utilities->hex2rgb( $h3_mgmt_teams->get_color_by_team( $team_id ) );
				$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
				$messages[] = $display_data;
				$i++;
			}
			if( $max != 0 && $i >= $max ) {
				break;
			}
		}

		if( $show_nav == 1 ) {
			$output .= '<div class="isotope-wrap">' .
					'<ul class="isotope-link-list">' .
						'<li><a href="' . get_site_url() .
									_x( '/follow-us/ticker/', 'utility translation', 'h3-mgmt' ) .
							'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

						$race_routes = $h3_mgmt_races->get_routes( array( 'race' => $race ) );

						foreach ( $race_routes as $race_route ) {
							$output .= '<li><a href="' . get_site_url() .
									_x( '/follow-us/ticker/', 'utility translation', 'h3-mgmt' ) .
									'?ticker_race=' . $race . '&ticker_route=' . $race_route['id'] .
								'">' . $race_route['name'] . '</a></li>';
						}

			$output .= '</ul>' .
				'</div>';
		}

		require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

		return $output;
	}

	/**
	 * Team specific Ticker
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function team_ticker( $team_id ) {
		global $wpdb, $h3_mgmt_teams, $h3_mgmt_utilities;

		$phones = $this->get_phones( $team_id );

		$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."sms_ticker " .
			"ORDER BY id DESC",
			ARRAY_A
		);

		$messages = array();

		$i = 0;
		foreach( $messages_query as $message ) {
			$norm_num = $this->normalize_phone_number( $message['from'] );
			if( in_array( $norm_num, $phones ) ) {
				$display_data['team_name'] = '<a class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
					'" href="http://' . __( 'tramprennen.org/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
					$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
				$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
				$display_data['img_url'] = 'http://tramprennen.org/wp-content/uploads/mms/' . $message['img_url'];
				$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
				$display_data['message'] = stripslashes( $message['msg'] );
				$display_data['type'] = $message['type'];
				$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) + 7200 );
				$display_data['time'] = $message['timestamp'];
				$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
				$rgb = $h3_mgmt_utilities->hex2rgb( $h3_mgmt_teams->get_color_by_team( $team_id ) );
				$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
				$messages[] = $display_data;
				$i++;
			}
			if( $max != 0 && $i >= $max ) {
				break;
			}
		}

		if( ! empty( $messages ) ) {
			require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
		} else {
			$output .= '<p>' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
		}

		return $output;
	}

	/**
	 * Shortcode callback to handle incoming messages
	 *
	 * @since 1.1
	 * @access public
	 */
	public function ticker_incoming_interface() {
		global $wpdb;

		if ( ! empty( $_POST['msisdn'] ) ) {

			$type = 'mms' === $_POST['type'] ? 2 : 1;
			$from = addslashes( urldecode( trim( $_POST['msisdn'] ) ) );
			$msg = ! empty( $_POST['msg'] ) ? addslashes( urldecode( trim( $_POST['msg'] ) ) ) : '';
			$img = '';

			if ( 2 === $type ) {
				if ( $_FILES['file'] && $_FILES['file']['error'] == UPLOAD_ERR_OK ) {
					$tmp_name = $_FILES['file']['tmp_name'];
					list($ext, $filename) = explode('.', strrev($_FILES['file']['name']));
					$name = md5($filename.time()).'.'.strrev($ext);
					move_uploaded_file($tmp_name, 'wp-content/uploads/mms/'.$name);
				}
				$img = $name;
			}

			$wpdb->insert(
				$wpdb->prefix.'sms_ticker',
				array(
					'from' => $from,
					'msg' => $msg,
					'type' => $type,
					'img_url' => $img,
					'timestamp' => time()
				),
				array('%s', '%s', '%d', '%s', '%d')
			);

			return '<p class="message ticker-response">' .
					'200 - OK' .
				'</p>';

		} else {
			return '<p class="error ticker-response">' .
					'400 - No or incomplete request' .
				'</p>';
		}
	}

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function H3_MGMT_Sponsors() {
		$this->__construct();
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_shortcode( 'h3-liveticker', array( $this, 'the_ticker' ) );
		add_shortcode( 'ticker-incoming-interface', array( $this, 'ticker_incoming_interface' ) );
	}

} // class

endif; // class exists

?>
