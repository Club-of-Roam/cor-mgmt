<?php
require_once __DIR__ . "/class-h3-mgmt-security.php";

/**
 * H3_MGMT_Teams class.
 *
 * This class contains properties and methods for the team post type.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Teams' ) ) :

class H3_MGMT_Teams {

	/*************** UTILITY METHODS ***************/

/* 	/**
	 * Returns data on all teams
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_teams( $args = array() ) {
		return $this->get_teams_meta( $args );
	}
	public function get_teams_meta( $args = array() ) {
		global $current_user, $wpdb, $h3_mgmt_races, $h3_mgmt_sponsors, $h3_mgmt_utilities;

		$default_args = array(
			'orderby' => 'id',
			'order' => 'ASC',
			'exclude_incomplete' => false,	//true
			'extra_fields' => array(),
			'parent' => 'all',     		//13
			'parent_type' => 'route'	//route
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if( ! in_array( $orderby, array( 'team_name', 'complete', 'total_points' ) ) ) {
			$query_orderby = 'id';
		} else {
			$query_orderby = $orderby;
		}
		if( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'ASC';
		}

		if( $parent_type === 'route' && is_numeric( $parent ) ) {
			$where = "WHERE route_id = " . $parent . " ";
		} elseif( $parent_type === 'race' && is_numeric( $parent ) ) {
			$where = "WHERE race_id = " . $parent . " ";
		} else {
			$where = '';
		}

		$teams_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teams " .
			$where .
			"ORDER BY " . $query_orderby . " " . $order,
			ARRAY_A
		);
		
		$teams = array();
		$team_count = 0;
		$complete_count = 0;

		foreach( $teams_query as $key => $team ) {
			if( $exclude_incomplete === false || ( $exclude_incomplete === true && $team['complete'] == 1 ) ) {
				$mates = $this->get_mate_names( $team['id'], false );
				$teams[$key] = stripslashes_deep( $team );
				if( empty( $teams[$key]['team_pic'] ) ) {
					$teams[$key]['team_pic'] = H3_MGMT_RELPATH . 'img/team-profile-pic.png';
				}
				foreach( $extra_fields as $field_id ) {
					if( $field_id == 'mates' ) {
						$teams[$key][$field_id] = array();
						foreach( $mates as $mate_id => $mate ) {
							$age = $h3_mgmt_utilities->date_diff( time(), intval( get_user_meta( $mate_id, 'birthday', true ) ) );
							$teams[$key][$field_id][$mate_id] = array (
								'name' => $mate,
								'age' => $age['year'],
								'city' => get_user_meta( $mate_id, 'city', true )
							);
						}
					} elseif( $field_id == 'route' ) {
						if( ! empty( $team['route_id'] ) && is_numeric( $team['route_id'] ) ) {
							$teams[$key][$field_id] = $h3_mgmt_races->get_name( $team['route_id'], 'route' );
						} else {
							$teams[$key][$field_id] = _x( 'Not chosen yet...', 'Route', 'h3-mgmt' );
						}
					} elseif( $field_id == 'sponsor-count' ) {
						$sponsors = $h3_mgmt_sponsors->list_sponsors( 'all', $team['id'] );
						$teams[$key][$field_id] = count( $sponsors );
					} elseif( $field_id == 'race' ) {
						$teams[$key][$field_id] = $h3_mgmt_races->get_name( $teams[$key]['race_id'] );
					} else {
						$teams[$key][$field_id] = array();
						foreach( $mates as $user_id => $mate_name ) {
							$teams[$key][$field_id][$user_id] = get_user_meta( $user_id, $field_id, true );
						}
					}
				}
			}
			if( $team['complete'] == 1 ) {
				$complete_count++;
			}
			$team_count++;
		}

		$incomplete_count = $team_count - $complete_count;

		if( ! in_array( $orderby, array( 'team_id', 'team_name', 'complete', 'total_points' ) ) ) {
			$teams = $h3_mgmt_utilities->sort_by_key( $teams, $orderby, $order );
		}

		return array( $team_count, $complete_count, $incomplete_count, $teams );
	}

	/**
	 * Returns data on a single team
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_team_data( $team_id, $extra_fields = array() ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb, $h3_mgmt_races, $h3_mgmt_utilities;

		if( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return false;
		}

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d LIMIT 1", $team_id );

		$team_query = $wpdb->get_results(
			$sql,
			ARRAY_A
		);

		$team = $team_query[0] ?? false;

		if( empty( $team ) ) {
			return false;
		}

		$mates = $this->get_mate_names( $team['id'], false );

		if( empty( $team['team_pic'] ) ) {
			$team['team_pic'] = H3_MGMT_RELPATH . 'img/team-profile-pic.png';
		}

		foreach( $extra_fields as $field_id ) {
			if( $field_id == 'mates' ) {
				$team[$field_id] = array ();
				foreach( $mates as $mate_id => $mate_name ) {
					$age = $h3_mgmt_utilities->date_diff( time(), intval( get_user_meta( $mate_id, 'birthday', true ) ) );
					$team[$field_id][$mate_id]['name'] = $mate_name;
					$team[$field_id][$mate_id]['age'] = $age['year'];
					$team[$field_id][$mate_id]['city'] = get_user_meta( $mate_id, 'city', true );
				}
			} elseif( $field_id == 'route' ) {
				if( ! empty( $team['route_id'] ) ) {
					$team[$field_id] = $h3_mgmt_races->get_route_name( $team['route_id'] );
				} else {
					$team[$field_id] = _x( 'Not chosen yet...', 'Route', 'h3-mgmt' );
				}
			} else {
				$team[$field_id] = array();
				foreach( $mates as $user_id => $mate_name ) {
					$team[$field_id][$user_id] = get_user_meta( $user_id, $field_id, true );
				}
			}
		}

		return $team;
	}

	/**
	 * Returns ids of all participants
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_participant_ids( $race_id = 1 ) {
            $race_id = H3_MGMT_Security::id($race_id);
		global $wpdb;

		$participants_query = $wpdb->get_results(
			"SELECT user_id FROM {$wpdb->prefix}h3_mgmt_teammates",
			ARRAY_A
		);
                
		$participant_ids = array();
		foreach( $participants_query as $participant ) {
			if ( $this->user_has_team( $race_id, $participant['user_id'] ) && ! in_array( $participant['user_id'], $participant_ids ) ) {
				$participant_ids[] = $participant['user_id'];
			}
		}

		return $participant_ids;
	}
	
	/**
	 * Returns ids of all user without team
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_user_ids_without_team( $race_id = 1 ) {
            $race_id = H3_MGMT_Security::id($race_id);
		global $wpdb;

		//get all user who could join a team
		$all_user = get_users( array( 	'fields' => 'ID' ,
									'role' => 'administrator') );
		$all_user = array_merge( $all_user, get_users( array( 	'fields' => 'ID' ,
                                                                        'role' => 'editor') ) );
		$all_user = array_merge( $all_user, get_users( array( 	'fields' => 'ID' ,
                                                                        'role' => 'author') ) );
		$all_user = array_merge( $all_user, get_users( array( 	'fields' => 'ID' ,
                                                                        'role' => 'subscriber') ) );
														
		$participant_ids = $this->get_participant_ids( $race_id );
		$users = array();
                
		foreach( $all_user as $user ) {
			if ( ! in_array( $user, $participant_ids ) ) {
				$users[] = $user;
			}
		}

		return $users;
	}
        
        
        
         /**
	 * Returns team id of user_id and race_id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_team_by_user_and_race( $user_id, $race_id ) {
            $user_id = H3_MGMT_Security::id($user_id);
            $race_id = H3_MGMT_Security::id($race_id);
		global $wpdb;

		if ( empty( $user_id ) || empty( $race_id ) || ! is_numeric( $user_id ) || ! is_numeric( $race_id ) ) {
			return 0;
		}

		$team_ids = $wpdb->get_col(
			"SELECT team_id FROM " .
			$wpdb->prefix . "h3_mgmt_teammates " .
			"WHERE user_id = " . $user_id .
			" ORDER BY id DESC"
		);

		$team_ids_race = $wpdb->get_col(
			"SELECT id FROM " .
			$wpdb->prefix . "h3_mgmt_teams " .
			"WHERE race_id = " . $race_id .
			" ORDER BY id DESC"
		);

		foreach ( $team_ids as $team_id ) {
			if ( in_array( $team_id, $team_ids_race ) ) {
				return $team_id;
			}
		}

		return 0;
	}

	/**
	 * Returns the preferred language of a participant
	 *
	 * @param int $user_id
	 *
	 * @return string $language
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_participant_language( $user_id = NULL ) {
		global $current_user, $wpdb;

		if ( ! is_numeric( $user_id ) ) {
			$user_id = $current_user->ID;
		}

		$language_query = $wpdb->get_results(
			"SELECT language FROM " .
			$wpdb->prefix."h3_mgmt_teammates " .
			"WHERE user_id = " . $user_id . " order by id DESC LIMIT 1",
			ARRAY_A
		);

		$language = isset( $language_query[0]['language'] ) ? $language_query[0]['language'] : 'en';

		return $language;
	}

	/**
	 * Returns the preferred language of a team
	 *
	 * @param int $team_id
	 *
	 * @return string $language
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_team_language( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);

		$mates = $this->get_teammates( $team_id );

		if ( ! empty( $mates ) ) {
			foreach ( $mates as $mate ) {
				$language = $this->get_participant_language( $mate );
				if ( 'en' === $language ) {
					break;
				}
			}
		}

		return in_array( $language, array( 'en', 'de' ) ) ? $language : 'en';
	}

	/**
	 * Returns data on all participants
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_participants_meta( $args = array() ) {
		global $current_user, $wpdb, $h3_mgmt_races, $h3_mgmt_ticker, $h3_mgmt_utilities;

		$default_args = array(
			'orderby' => 'id',
			'order' => 'ASC',
			'exclude_incomplete' => false,
			'extra_fields' => array(),
			'parent' => 'all',
			'parent_type' => 'race'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$query_orderby = 'id';
		if( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'ASC';
		}

		$participants_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teammates " .
			"ORDER BY " . $query_orderby . " " . $order,
			ARRAY_A
		);

		$participants = array();
		$participants_count = 0;
		$participants_complete_count = 0;

		foreach( $participants_query as $key => $participant ) {
			$team_query = $wpdb->get_results(
				"SELECT complete, race_id, route_id FROM " .
				$wpdb->prefix."h3_mgmt_teams " .
				"WHERE id = " . $participant['team_id'] ." LIMIT 1",
				ARRAY_A
			);
			if (
				! is_numeric( $parent ) ||
				is_numeric( $parent ) && 'race' === $parent_type && isset( $team_query[0]['race_id'] ) && $parent === $team_query[0]['race_id'] ||
				is_numeric( $parent ) && 'route' === $parent_type && isset( $team_query[0]['route_id'] ) && $parent == $team_query[0]['route_id']
			) {
				$complete = isset( $team_query[0]['complete'] ) ? $team_query[0]['complete'] : 0;
				$user_obj = new WP_User( $participant['user_id'] );
				if( $exclude_incomplete === false || ( $exclude_incomplete === true && $complete == 1 ) ) {
					$participants[$key] = $participant;
					foreach( $extra_fields as $field_id ) {
						if( $field_id == 'team' ) {
							if( ! empty( $participant['team_id'] ) ) {
								$participants[$key][$field_id] = $this->get_team_name( $participant['team_id'] );
							} else {
								$participants[$key][$field_id] = __( 'Ooops, no team...', 'h3-mgmt' );
							}
						} elseif( $field_id == 'email' ) {
							$participants[$key][$field_id] = $user_obj->user_email;
						} elseif( $field_id == 'race' ) {
							$participants[$key][$field_id] = isset( $team_query[0]['race_id'] ) ? $h3_mgmt_races->get_name( $team_query[0]['race_id'] ) : __( 'not set...', 'h3-mgmt' );
						} elseif( $field_id == 'mobile' ) {
							$participants[$key][$field_id] = $h3_mgmt_ticker->normalize_phone_number( get_user_meta( $participant['user_id'], 'mobile', true ), true );
						} elseif( $field_id == 'shirt' ) {
							$participants[$key][$field_id] = get_user_meta( $participant['user_id'], 'shirt_size', true );
						} elseif( $field_id == 'InfMobile' ) {
							$participants[$key][$field_id] = get_user_meta( $participant['user_id'], 'public_mobile_inf', true );
						} else {
							$participants[$key][$field_id] = get_user_meta( $participant['user_id'], $field_id, true );
						}
					}
				}
				if( $complete == 1 ) {
					$participants_complete_count++;
				}
				$participants_count++;
			}
		}

		$participants_incomplete_count = $participants_count - $participants_complete_count;

		$participants = $h3_mgmt_utilities->sort_by_key( $participants, $orderby, $order );

		return array( $participants_count, $participants_complete_count, $participants_incomplete_count, $participants );
	}

	/**
	 * Read-only eligibility check; explicit commands own persisted status and notifications
	 *
	 * @since 1.0
	 * @access public
	 */
	public function is_complete( $team_id ) {
		global $wpdb, $h3_mgmt_mailer, $h3_mgmt_utilities, $h3_mgmt_races;

		if ( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return false;
		}

		$teammates_sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}h3_mgmt_teammates WHERE team_id = %d", $team_id);

		$hitchmates_query = $wpdb->get_results(
			$teammates_sql,
			ARRAY_A
		);

		$complete_sql = $wpdb->prepare("SELECT complete FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d", $team_id);

		$current_status_query = $wpdb->get_results(
			$complete_sql,
			ARRAY_A
		);

        $race_id = $this->get_team_race( $team_id );
        $race_setting = $h3_mgmt_races->get_race_setting( $race_id );

		$current_status = $current_status_query[0]['complete'] ?? 0;
        if ((int) ($race_setting['status'] ?? 0) >= 3) { return (int) $current_status; }

        $profiles = [];
        foreach ($hitchmates_query as $member) {
            $user = (int) ($member['user_id'] ?? 0);
            if ($user) {
                $shirt = get_user_meta($user, 'shirt_size', true);
                $partner = get_user_meta($user, 'public_mobile_inf', true);
                $profiles[$user] = [
                    'shirt_size' => is_scalar($shirt) ? (string) $shirt : '',
                    'public_mobile_inf' => is_scalar($partner) ? (string) $partner : '',
                ];
            }
        }
        $settings = array_map(static fn($value) => is_scalar($value) ? (string) $value : null, $race_setting);
        $members = array_map(static fn($row) => array_map(static fn($value) => $value === null ? null : (string) $value, $row), $hitchmates_query);
        return \Cor\Mgmt\Domain\TeamPolicy::eligible($settings, $members, $profiles) ? 1 : 0;
	}

	/**
	 * Checks whether a team_id exists
	 *
	 * @since 1.0
	 * @access public
	 */
	public function team_exists( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return false;
		}

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d LIMIT 1", $team_id );

		$team_query = $wpdb->get_results(
			$sql,
			ARRAY_A
		);

		if( empty( $team_query ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether a team has an owner
	 * returns false if not
	 * and owner array if so
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_owner( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return false;
		}

		$owner_sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}h3_mgmt_sponsors WHERE team_id = %d AND type = 'owner'", $team_id );

		$owner_query = $wpdb->get_results(
			$owner_sql,
			ARRAY_A
		);

        date_default_timezone_set('Europe/Berlin');

		if( ! empty( $owner_query ) ) {
            foreach( $owner_query as $owner ){
	            if (
		            ( $owner['paid'] == 1 && $owner['var_show'] == 1 ) ||
		            time() - strtotime( $owner['timestamp'] ) < 600 ||
		            '0000-00-00 00:00:00' == $owner['timestamp']
	            ) {
		            return $owner['id'];
	            }
            }
		}

		return false;
	}

	/**
	 * Checks whether a team has sponsors
	 * returns false if not
	 * and sponsors array if so
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_sponsors( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return false;
		}

		$sponsors_sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}h3_mgmt_sponsors WHERE team_id = %d AND type = 'sponsor' AND paid = 1", $team_id );

		$sponsors_query = $wpdb->get_results(
			$sponsors_sql,
			ARRAY_A
		);

        $sponsors_query = \Cor\Mgmt\WordPress\DonationFrontend::overlay($sponsors_query);
		if( ! empty( $sponsors_query ) ) {
			return $sponsors_query;
		}

		return false;
	}

	/**
	 * Converts team_id to Team Name
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_team_name( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return __( 'Ooops, no team...', 'h3-mgmt' );
		}

		$team_name_sql = $wpdb->prepare( "SELECT team_name FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d LIMIT 1", $team_id );

		$team_query = $wpdb->get_results(
			$team_name_sql,
			ARRAY_A
		);

		$team_name = isset( $team_query[0]['team_name'] ) ? stripslashes( $team_query[0]['team_name'] ) : '';

		if( empty( $team_name ) ) {
			$team_name = __( 'Ooops, no team...', 'h3-mgmt' );
		}

		return $team_name;
	}

	/**
	 * Returns an array of team phone numbers
	 * Jonas change get Number in Team separated by space bar 
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_team_phones( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if ( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return [];
		}

		$phone_sql = $wpdb->prepare( "SELECT team_phone FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d LIMIT 1", $team_id );

		$phone_query = $wpdb->get_results(
			$phone_sql,
			ARRAY_A
		);

		if ( empty( $phone_query ) || empty( $phone_query[0]['team_phone'] ) ) {
			return [];
		}

		$phones = [];

		$team_phones = $phone_query[0]['team_phone'];
		$pos         = stripos( $team_phones, ' ', 3 );
		while ( $pos !== false ) {
			$phones[]    = substr( $team_phones, 0, $pos );
			$team_phones = strpbrk( $team_phones, ' ' );
			$team_phones = ltrim( $team_phones );
			$pos         = stripos( $team_phones, ' ', 3 );
		}
		$phones[] = $team_phones;

		$teammates = $this->get_teammates( $team_id );

		foreach( $teammates as $mate ) {
			$mate_phone = get_user_meta( $mate, 'mobile', true );
			if( ! empty( $mate_phone ) ) {
				$phones[] = $mate_phone;
			}
		}
		return $phones;
	}

	/**
	 * Returns route color code based on team ID
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_color_by_team( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if ( empty( $team_id ) || ! is_numeric( $team_id ) ) {
			return null;
		}

		$color_sql = $wpdb->prepare(
			"SELECT r.color_code FROM {$wpdb->prefix}h3_mgmt_routes r " .
			"INNER JOIN {$wpdb->prefix}h3_mgmt_teams t ON r.id = t.route_id WHERE t.id = %d LIMIT 1",
			$team_id
		);

		$color_code = $wpdb->get_var( $color_sql );

		if ( empty( $color_code ) ) {
			return '4e4e4e';
		}

		return $color_code;
	}

	/**
	 * Retrieves teammates ID's
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_teammates( $team_id, $exclude_current = false ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $current_user, $wpdb;

		if ( ! is_numeric( $team_id ) ) {
			return [];
		}

		$raw_sql = "SELECT user_id FROM {$wpdb->prefix}h3_mgmt_teammates WHERE team_id = %d";

		if ( $exclude_current === true ) {
			wp_get_current_user();

			$sql = $wpdb->prepare(
				$raw_sql . " AND user_id != %d",
				$team_id,
				$current_user->ID
			);
		} else {
			$sql = $wpdb->prepare(
				$raw_sql,
				$team_id
			);
		}

		return $wpdb->get_col( $sql );
	}

	/**
	 * Returns an array of mate name(s)
	 *
	 * @since 1.0
	 * @access private
	 */
	private function get_mate_names( $team_id, $exclude_current = true ) {
            $team_id = H3_MGMT_Security::id($team_id);
		$mate_ids = $this->get_teammates( $team_id, $exclude_current );
		$mate_names = array();

		foreach( $mate_ids as $mate_id ) {
			$mate_obj = new WP_User( $mate_id );
			$mate_names[$mate_id] = $mate_obj->first_name;
		}

		return $mate_names;
	}

	/**
	 * Returns a string of mate name(s)
	 * concatenated by " & "
	 *
	 * @since 1.0
	 * @access public
	 */
	public function mate_name_string( $team_id, $delimiter = ' &amp; ', $exclude_current = true ) {
		$mate_names = $this->get_mate_names( $team_id, $exclude_current );

		return implode( $delimiter, $mate_names );
	}

	/**
	 * Returns an array of team data to be used in a dropdown menu
	 *
	 * @since 1.0
	 * @access public
	 */
	public function options_array( $args = array() ) {
		return $this->select_options( $args );
	}
	public function select_options( $args = array() ) {
		global $wpdb;

		$default_args = array(
			'orderby' => 'team_name',
			'order' => 'ASC',
			'please_select' => false,
			'exclude_with_owner' => false,
			'owned_team_id' => 0,
			'exclude_incomplete' => false,
			'show_mates' => false,
			'select_text' => '',
			'race' => 1
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$team_query = $wpdb->get_results(
			"SELECT id, team_name FROM " .
			$wpdb->prefix."h3_mgmt_teams " .
			"WHERE race_id = " . $race . " " .
			"ORDER BY team_name", ARRAY_A
		);
		$teams = array();
		if( empty( $select_text ) ) {
			$select_text = __( 'Please select...', 'h3-mgmt' );
		}
		if( $please_select === true ) {
			$teams[0] = array(
				'label' => $select_text,
				'value' => 'please_select',
				'class' => 'please-select'
			);
		}			   
			   
		foreach ( $team_query as $team ) {
			if ( ( $exclude_with_owner != true || ! $this->get_owner( $team['id'] ) || $team['id'] === $owned_team_id ) &&
			   ( $exclude_incomplete != true || $this->is_complete( $team['id'] ) ) ) {
				$label = stripslashes( $team['team_name'] );
				if ( $show_mates === true ) {
					$label .= ' (' . $this->mate_name_string( $team['id'], ' &amp; ', false ) . ')';
				}
				$teams[] = array(
					'label' => $label,
					'value' => $team['id']
				);
			}
		}

		return $teams;
	}

	/**
	 * Returns the race associated with a team_id
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_team_race( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		if( empty( $team_id ) || ! is_numeric( $team_id) ) {
			return false;
		}

		$sql = $wpdb->prepare( "SELECT race_id FROM {$wpdb->prefix}h3_mgmt_teams WHERE id = %d LIMIT 1", $team_id );

		$race_query = $wpdb->get_results(
			$sql,
			ARRAY_A
		);

		return $race_query[0]['race_id'] ?? 0;
	}

	/***************  REGISTRATION: UTILITY ***************/

	/**
	 * Checks whether the current user has a team
	 * returns the team ID if he or she does
	 * returns false if no team exists yet
	 *
	 * @since 1.0
	 * @access public
	 */
	public function user_has_team( $race_id = 1, $user = NULL ) {
		global $current_user, $wpdb;
		wp_get_current_user();

		$user_id = is_numeric( $user ) ? $user : $current_user->ID;

		$team_id_query = $wpdb->get_results(
			"SELECT team_id FROM " .
			$wpdb->prefix."h3_mgmt_teammates " .
			"WHERE user_id = " . $user_id,
			ARRAY_A
		);

		foreach( $team_id_query as $tmp_id ) {
			$data = $this->get_team_data( $tmp_id['team_id'] );
			if ( $data && intval( $race_id ) === intval( $data['race_id'] ) ) {
				$team_id = $tmp_id['team_id'];
				break;
			}
		}

		if( empty( $team_id ) ) {
			return false;
		} else {
			return $team_id;
		}
	}

	/**
	 * Checks whether an invitation ought to be handled
	 *
	 * @since 1.0
	 * @access private
	 */
	private function is_invitation() {
		if (
			isset( $_GET['invitation'] ) && ! empty( $_GET['invitation'] ) ||
			isset( $_POST['invitation'] ) && ! empty( $_POST['invitation'] )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Determines whether invitations are still possible
	 * returns false if not,
	 * number of allowed invitations (integer) if so.
	 *
	 * @since 1.0
	 * @access private
	 */
    private function allow_invitations($team_id) {
        global $h3_mgmt_races;
        $settings = $h3_mgmt_races->get_race_setting($this->get_team_race($team_id));
        return (int) ($settings['status'] ?? -1) === 1 ? 1 : false;
    }

	/**
	 * Handles an invitation
	 *
	 * @since 1.0
	 * @access private
	 */
    private function handle_invitation() {
        $code = $_POST['invitation'] ?? $_GET['invitation'] ?? '';
        return is_string($code) ? (\Cor\Mgmt\WordPress\TeamFrontend::invitedTeam($code) ?: false) : false;
    }

	/**
	 * Generates invitation code
	 *
	 * @todo simplify querying for existing codes, save one iteration
	 * @since 1.0
	 * @access private
	 */
	private function generate_code() {
		global $wpdb;

		$code = \Cor\Mgmt\WordPress\Services::get()->random->integer(1000000000000000, 9999999999999999);
		$existing_codes_query = $wpdb->get_results(
			"SELECT code FROM " . $wpdb->prefix . "h3_mgmt_invitations",
			ARRAY_A
		);
		$existing_codes = array();
		foreach ( $existing_codes_query as $existing_code ) {
			$existing_codes[] = $existing_code['code'];
		}
		while ( in_array( $code, $existing_codes ) ) {
			$code = \Cor\Mgmt\WordPress\Services::get()->random->integer(1000000000000000, 9999999999999999);
		}
		return $code;
	}

	/**
	 * Returns a one-field-form to enter invitation code
	 *
	 * @since 1.0
	 * @access private
	 */
	private function invitation_code_form() {
		$output = '<form  name="h3_mgmt_invitation_code_form" method="get" action="">';

		$fields = array(
			array(
				'label' => _x( 'Code', 'Team Dashboard', 'h3-mgmt' ),
				'type' => 'text',
				'id' => 'invitation'
			)
		);
		require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

		$output .= '<div class="form-row">' .
			'<input type="submit" id="submit_form" value="' .
				_x( 'Submit Invitation Code', 'Team Dashboard', 'h3-mgmt' ) .
			'" /></div></form>';

		return $output;
	}

	/*************** TEAM REGISTRATION: FORM ***************/

	/**
	 * Returns an array of team fields
	 *
	 * @since 1.0
	 * @access public
	 */
	public function team_fields( $with_mb, $race_id  ) {

            global $information_text, $h3_mgmt_races;
		
            $race_setting = $h3_mgmt_races->get_race_setting( $race_id );

            $team_fields = array(
                    array (
                            'label'	=> _x( 'Team Name', 'Team Profile Form', 'h3-mgmt' ),
                            'desc'	=> _x( 'The name of your team (maximum 50 characters)', 'Team Profile Form', 'h3-mgmt' ),
                            'id'	=> 'team_name',
                            'type'	=> 'text'
                    ),
                    array (
                            'label'	=> _x( 'Description', 'Team Profile Form', 'h3-mgmt' ),
                            'desc'	=> _x( 'A description of you and your teammates. Maybe serious or funny. You have a maximum of 500 characters.', 'Team Profile Form', 'h3-mgmt' ),
                            'id'	=> 'description',
                            'type'	=> 'textarea'
                    ),
                    array(
                            'label'	=> _x( 'Team Picture', 'Team Profile Form', 'h3-mgmt' ),
                            'id'	=> 'team_pic',
                            'type'	=> 'single-pic-upload',
                            'desc'	=> _x( "This picture will appear in your team profile. You may upload .jpeg, .gif or .png files.", 'Team Dashboard', 'h3-mgmt' )
                    ),
                    array(
                            'label'	=> _x( 'Donation Goal', 'Team Profile Form', 'h3-mgmt' ),
                            'id'	=> 'donation_goal',
                            'type'	=> 'slider',
                            'desc'	=> _x( "The amount of donations you wish to collect this year.", 'Team Dashboard', 'h3-mgmt' ) . '<br />' . _x( "The common donation goal will be calculated from the sum of that of all teams.", 'Team Dashboard', 'h3-mgmt' ) . '<br />' . _x( "(&quot;Zero&quot; is a perfectly acceptable answer...)", 'Team Dashboard', 'h3-mgmt' ) . '<br />' . _x( "Please enter <strong>numbers only</strong> though.", 'Team Dashboard', 'h3-mgmt' )
                    )
                );
            
            if(!$race_setting['question_1_invisible'])
            {
                $team_fields[] = 
                        array (
                                'label'	=> stripcslashes( $information_text[1] ), //_x( 'Two weeks through Europe by thumb. Why?', 'Team Profile Form', 'h3-mgmt' ),
                                'desc'	=> stripcslashes( $information_text[6] ), //_x( 'What is your personal motivation to participate?', 'Team Profile Form', 'h3-mgmt' ),
                                'id'	=> 'meta_1',
                                'type'	=> 'textarea'
                        );
            }
            
            if(!$race_setting['question_2_invisible'])
            {
                $team_fields[] = 
                        array (
                                'label'	=> stripcslashes( $information_text[2] ), //_x( 'Why should a lift take us along?', 'Team Profile Form', 'h3-mgmt' ),
                                'desc'	=> stripcslashes( $information_text[7] ), //_x( 'Well...', 'Team Profile Form', 'h3-mgmt' ),
                                'id'	=> 'meta_2',
                                'type'	=> 'textarea'
                        );
            }
            
            if(!$race_setting['question_3_invisible'])
            {
                $team_fields[] = 
                        array (
                                'label'	=> stripcslashes( $information_text[3] ), //_x( 'Our best Autostop-experience so far', 'Team Profile Form', 'h3-mgmt' ),
                                'desc'	=> stripcslashes( $information_text[8] ), //_x( 'Tell the world about it!', 'h3-mgmt' ),
                                'id'	=> 'meta_3',
                                'type'	=> 'textarea'
                        );
            }
            
            if(!$race_setting['question_4_invisible'])
            {
                $team_fields[] = 
                        array (
                                'label'	=> stripcslashes( $information_text[4] ), //_x( 'Our goal for the race', 'Team Profile Form', 'h3-mgmt' ),
                                'desc'	=> stripcslashes( $information_text[9] ), //_x( 'What do you want to achieve?', 'Team Profile Form', 'h3-mgmt' ),
                                'id'	=> 'meta_4',
                                'type'	=> 'radio',
                                'options' => array(
                                        array(
                                                'label' => stripcslashes( $information_text[12] ), //_x( 'Reach the destination. Participation is everything!', 'Team Profile Form', 'h3-mgmt' ),
                                                'value' => 1
                                        ),
                                        array(
                                                'label' => stripcslashes( $information_text[13] ), //_x( 'Fun, Fun, Fun!', 'Team Profile Form', 'h3-mgmt' ),
                                                'value' => 2
                                        ),
                                        array(
                                                'label' => stripcslashes( $information_text[14] ), //_x( 'Upper Midfield. And a stage victory.', 'Team Profile Form', 'h3-mgmt' ),
                                                'value' => 3
                                        ),
                                        array(
                                                'label' => stripcslashes( $information_text[15] ), //_x( 'Win it! What else???', 'Team Profile Form', 'h3-mgmt' ),
                                                'value' => 4
                                        )
                                )
                        );
            }
            
            if(!$race_setting['question_5_invisible'])
            {
                $team_fields[] = 
                        array (
                                'label'	=> stripcslashes( $information_text[5] ), //_x( 'For a Donation we would:', 'Team Profile Form', 'h3-mgmt' ),
                                'desc'	=> stripcslashes( $information_text[10] ), //__( 'Thoughts?', 'h3-mgmt' ),
                                'id'	=> 'meta_5',
                                'type'	=> 'textarea'
                        );
            }

            if ( $with_mb ) {
                    $team_fields = array(
                            array(
                                    'title' => __( 'The Team' , 'h3-mgmt' ),
                                    'fields' => $team_fields
                            )
                    );
            }

            return $team_fields;
	}

	/**
	 * Adds values to team fields
	 *
	 * @since 1.0
	 * @access private
	 */
	private function team_section( $team_exists = false, $team_id = NULL, $race_id = NULL ) {
        $team_id = H3_MGMT_Security::id($team_id);
        $race_id = H3_MGMT_Security::id($race_id);
		global $wpdb;
                
		$fields = $this->team_fields(false, $race_id);
                
		$fcount = count($fields);

		if ( $team_exists === true && empty( $_POST['submitted'] ) ) {
			$team_query = $wpdb->get_results(
				"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_teams WHERE id = " . $team_id . " LIMIT 1",
				ARRAY_A
			);
			$team = $team_query[0];
			for ( $i = 0; $i < $fcount; $i++ ) {
				$fields[$i]['value'] = stripslashes( $team[$fields[$i]['id']] );
			}
		} else {
			for ( $i = 0; $i < $fcount; $i++ ) {
				if( $fields[$i]['id'] == 'team_pic' && is_numeric( $team_id ) ) {
					$team_query = $wpdb->get_results(
						"SELECT team_pic FROM " . $wpdb->prefix . "h3_mgmt_teams WHERE id = " . $team_id . " LIMIT 1",
						ARRAY_A
					);
					$fields[$i]['value'] = stripslashes( $team_query[0][$fields[$i]['id']] );
				} else {
					$fields[$i]['value'] = isset( $_POST[$fields[$i]['id']] ) ? stripslashes( $_POST[$fields[$i]['id']] ) : '';
				}
			}
		}
		
		return $fields;
	}

	/**
	 * Returns an array of user section fields
	 *
	 * @since 1.0
	 * @access private
	 */
	private function user_fields() {

		$mate_fields = array(
			array (
				'label'	=> _x( 'First Name', 'Team Profile Form', 'h3-mgmt' ) . '*',
				'desc'	=> _x( 'What is your name?', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'first_name',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Last Name', 'Team Profile Form', 'h3-mgmt' ) . '*',
				'desc'	=> _x( 'Your last name will not be visible online - this info is only important for the waiver forms.', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'last_name',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'City', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'Where are you from?', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'city',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Date of Birth', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'How old are you?', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'birthday',
				'type'	=> 'date'
			),
			array (
				'label'	=> _x( 'Mobile Phone', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'Your mobile phone number. This information is important for the SMS/MMS-Liveticker and will not be visible publicly.', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'mobile',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Shirt Size', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'Your T-Shirt size, important for the HitchPackage.', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'shirt_size',
				'type'	=> 'select',
				'options' => array(
					0 => array(
						'value' => 0,
						'label' => _x( 'Please select your size...', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'ms',
						'label' => _x( "Unisex S", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'mm',
						'label' => _x( "Unisex M", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'ml',
						'label' => _x( "Unisex L", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'mx',
						'label' => _x( "Unisex XL", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gs',
						'label' => _x( 'Slimfit S', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gm',
						'label' => _x( 'Slimfit M', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gl',
						'label' => _x( 'Slimfit L', 'Team Profile Form', 'h3-mgmt' )
					)
				)
			),
			array (
				'label'	=> _x( 'Could we give our partner Ortel Mobile your personal Information?', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'Do you accept if we send your personal information to our mobile partner to register your sim-card? They will delete them after 10 days and will use them only for registration of your sim-card!', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'public_mobile_inf',
				'type'	=> 'select',
				'options' => array(
					0 => array(
						'value' => 0,
						'label' => _x( 'Please select if it is ok or not...', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'yes',
						'label' => _x( "YES, give them my personal information", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'no',
						'label' => _x( "Please not (So you do not get your sponsored sim-card", 'Team Profile Form', 'h3-mgmt' )
					)					
				)
			),
			array (
				'label'	=> _x( 'Address for Ortel', 'Team Profile Form', 'h3-mgmt' ),
				'desc'	=> _x( 'Your address? Just if we are allowed to give your information to our partner Ortel Mobile!', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'addressMobile',
				'type'	=> 'textarea'
			)
		);

		return $mate_fields;
	}

	/**
	 * Returns primary user section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function primary_user_section( $hide_shirt = 0, $hide_mobile = 0) {
		global $current_user;
                
		$fields = $this->user_fields();

		$fcount = count($fields);
		if ( ! isset( $_POST['submitted'] ) ) {
                    wp_get_current_user();
                    for ( $i = 0; $i < $fcount; $i++ ) {
                        $fields[$i]['value'] = esc_attr( get_user_meta( $current_user->ID, $fields[$i]['id'], true ) );

                        if( $hide_shirt == 1){
                            if( $fields[$i]['id'] == 'shirt_size' ){
                                $fields[$i]['type'] = 'hidden';
                                $fields[$i]['label'] = '';
                                $fields[$i]['desc'] = '';
                            }
                        }
                            
                        if( $hide_mobile == 1){
                            if( $fields[$i]['id'] == 'public_mobile_inf' ){
                                $fields[$i]['type'] = 'hidden';
                                $fields[$i]['label'] = '';
                                $fields[$i]['desc'] = '';
                            }
                            if( $fields[$i]['id'] == 'addressMobile' ){
                                $fields[$i]['type'] = 'hidden';
                                $fields[$i]['label'] = '';
                                $fields[$i]['desc'] = '';
                            }
                        }
                    }
		} else {
                    for ( $i = 0; $i < $fcount; $i++ ) {
                        if ( 'date' === $fields[$i]['type'] ) {
                            $fields[$i]['value'] = mktime( 0, 0, 0,
                                    $_POST[ $fields[$i]['id'] . '-month' ],
                                    $_POST[ $fields[$i]['id'] . '-day' ],
                                    $_POST[ $fields[$i]['id'] . '-year' ]
                            );
                        } else {
                            $fields[$i]['value'] = $_POST[$fields[$i]['id']];

                            if( $hide_shirt == 1){
                                if( $fields[$i]['id'] == 'shirt_size' ){
                                    $fields[$i]['type'] = 'hidden';
                                    $fields[$i]['label'] = '';
                                    $fields[$i]['desc'] = '';
                                }
                            }
                            
                            if( $hide_mobile == 1){
                                if( $fields[$i]['id'] == 'public_mobile_inf' ){
                                    $fields[$i]['type'] = 'hidden';
                                    $fields[$i]['label'] = '';
                                    $fields[$i]['desc'] = '';
                                }
                                if( $fields[$i]['id'] == 'addressMobile' ){
                                    $fields[$i]['type'] = 'hidden';
                                    $fields[$i]['label'] = '';
                                    $fields[$i]['desc'] = '';
                                }
                            }
                        }
                    }
		}

		$flast = $fcount - 1;
		if( ! empty( $field[$flast]['value'] ) ) {
			$size_options = $field[$flast]['options'];
			array_shift( $size_options );
			$field[$flast]['options'] = $size_options;
		}

		return $fields;
	}

	/**
	 * Returns the invitation field
	 *
	 * @since 1.0
	 * @access private
	 */
	private function invitation_fields( $num_invitations ) {

		$invitation_fields = array(
			array(
				'label'	=> _x( 'Email', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'invitations',
				'type'	=> 'text-repeatable-no-js',
				'num'	=> $num_invitations
			)
		);

		return $invitation_fields;
	}

	/**
	 * Returns invitation section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function invitations_section( $team_id, $num_invitations = 2 ) {
        $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		$field = $this->invitation_fields( $num_invitations );

		if( $team_id == 0 )
		{
			return $field;
		}

		if ( empty( $_POST['form_submitted'] ) ) {
			$invitations_query = $wpdb->get_results(
				"SELECT * FROM ".$wpdb->prefix."h3_mgmt_invitations WHERE team_id = ".$team_id,
				ARRAY_A
			);
			$inv_count = count( $invitations_query );
			$field[0]['value'] = array();
			for ( $i = 0; $i < $inv_count; $i++ ) {
				if( ! empty( $invitations_query[$i]['email'] ) ) {
					$field[0]['value'][$i] = stripslashes( $invitations_query[$i]['email'] );
				}
			}
		} else {
			for ( $i = 0; $i < $num_invitations; $i++ ) {
				$field[0]['value'][$i] = stripslashes( $_POST[$field[0]['id']][$i] );
			}
		}

		return $field;
	}

	/**
	 * Returns the mate(s) (info) section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function mate_section( $team_id ) {
		global $wpdb, $h3_mgmt_races;
		
		$race_id = $this->get_team_race( $team_id );
		$race_setting = $h3_mgmt_races->get_race_setting( $race_id );
		$mate_name_string = $this->mate_name_string( $team_id );

		$output = '<h3 class="top-space-more">' . _x( 'Your Teammate(s)', 'Team Dashboard', 'h3-mgmt' ) . '</h3>';

		if( ! empty( $mate_name_string ) ) {
			$data = $this->get_team_data( $team_id );
			if ( isset( $data['race_id'] ) && $this->is_invitation() && ! $this->user_has_team( $data['race_id'] ) ) {
				$output .= '<p>' . _x( 'If you join this team, it will consist of you & ', 'Team Dashboard', 'h3-mgmt' );
			} else {
				$output .= '<p>' . _x( 'The team currently consists of you yourself & ', 'Team Dashboard', 'h3-mgmt' );
			}
			$output .= ' ' . $mate_name_string . '.</p>';
		} else {
			if( $race_setting['status'] >= 2 ){
				$output .= '<p>' . _x( "So far, you don't have any teammates. You need at least one to complete the team registration. The registration is already closed. If you haven't invited any yet, you can't do it any more...", 'Team Dashboard', 'h3-mgmt' ) . '</p>';
			}else{
				$output .= '<p>' . _x( "So far, you don't have any teammates. You need at least one to complete the team registration. If you haven't invited any yet, it might be a good idea to do so...", 'Team Dashboard', 'h3-mgmt' ) . '</p>';
			}
		}

		return $output;
	}

	/**
	 * Returns the route field
	 *
	 * @since 1.0
	 * @access public
	 */
	public function route_field( $race_id = 1, $show_if_full = NULL ) {
		global $h3_mgmt_races;

		$routes = $h3_mgmt_races->get_route_ids( $race_id );
		$route_options = array(
			0 => array(
				'value' => 0,
				'label' => _x( 'Please select your starting point...', 'Team Profile Form', 'h3-mgmt' )
			)
		);

		foreach( $routes as $route_id => $route_name ) {

			$free_slots = $h3_mgmt_races->get_free_slots( $route_id );
			if( 0 < $free_slots || ( is_numeric( $show_if_full ) && $show_if_full == $route_id ) ) {
				$route_options[] = array(
					'value' => $route_id,
					'label' => $route_name . ' (' . $free_slots . ')'
				);
			}
		}

		$route_field = array(
			array(
				'label'	=> _x( 'Starting Point', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'route_id',
				'type'	=> 'select',
				'options' => $route_options
			)
		);

		return $route_field;
	}

	/**
	 * Returns route section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function route_section( $team_id ) {
        $team_id = H3_MGMT_Security::id($team_id);
		global $wpdb;

		$race_id = $this->get_team_race( $team_id );

		$show_if_full = NULL;
		if ( empty( $_POST['submitted'] ) ) {
			$route_query = $wpdb->get_results(
				"SELECT route_id FROM ".$wpdb->prefix."h3_mgmt_teams WHERE id = " . $team_id . " LIMIT 1",
				ARRAY_A
			);
			$value = stripslashes( $route_query[0]['route_id'] );
			$show_if_full = $value;
		} else {
			$value = stripslashes( $_POST['route_id'] );
			$show_if_full = $value;
		}
		$field = $this->route_field( $race_id, $show_if_full );
		$field[0]['value'] = $value;

		if( ! empty( $field[0]['value'] ) ) {
			$route_options = $field[0]['options'];
			array_shift( $route_options );
			$field[0]['options'] = $route_options;
		}

		return $field;
	}

	/*************** TEAM REGISTRATION: SHORTCODE METHODS ***************/

	/**
	 * Team Dashboard controller,
	 * wordpress shortcode
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function team_dashboard_homework_control( $atts = '' ) {
		extract( shortcode_atts( array(
			'event' => 1,
			'race' => 0
		), $atts ) );
		
		global $current_user, $h3_mgmt_races, $wpdb, $information_text;
		
		$race_id = $race;
		
		if( $race == 'active'){
			$race_id = $h3_mgmt_races->get_active_race();
		}
		
		$information_text = $h3_mgmt_races->get_race_information_text( $race_id );
		
		$race_setting = $h3_mgmt_races->get_race_setting( $race_id );
		//if registration still isn't open return error message
		if( $race_setting['status'] == 0 ){
			$output .= '<p class="message" style="text-align: center;">' .
						stripcslashes( $information_text[22] ) .
						'</p>';
			$output .= '<br><br><br><br><br><br><br><br><br><br><br><br>';
			return $output;	
		}else{
			$output .= 	'<div style=" width: 47%; float: left; ">';
			$output .= do_shortcode( '[h3-team-dashboard race="' . $race_id . '"]' );
			$output .= 	'</div>';
			$output .=	'<div style=" width: 47%; float: right; ">';
			$output .= do_shortcode( '[h3-team-homework race="' . $race_id . '"]' );
	
			return $output;
		}
		
	}
	
	/**
	 * Team Dashboard controller,
	 * wordpress shortcode
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
    public function team_dashboard_control($atts = '') {
        global $h3_mgmt_races;
        $args = shortcode_atts(['event' => 1, 'race' => 0], $atts);
        $race = $args['race'] === 'active' ? $h3_mgmt_races->get_active_race() : ($args['race'] ?: $args['event']);
        return \Cor\Mgmt\WordPress\TeamFrontend::dashboard(H3_MGMT_Security::id($race));
    }

	/**
	 *  Outputs the team dashboard
	 *
	 * @since 1.0
	 * @access private
	 */
    private function team_dashboard($args = array()) {
        return \Cor\Mgmt\WordPress\TeamFrontend::dashboard((int) ($args['race_id'] ?? 1));
    }

	/**
	 *  Outputs the "homework" section
	 *
	 * @since 1.0
	 * @access public
	 */
	public function team_homework( $atts = '' ) {
		global $current_user, $wpdb, $h3_mgmt_mailer, $h3_mgmt_races, $h3_mgmt_utilities, $information_text, $h3_mgmt_sponsors;
		
		wp_get_current_user();

		extract( shortcode_atts( array(
			'event' => 0,
			'race' => 0
		), $atts ) );
		$language = $h3_mgmt_utilities->user_language();

		$race_id = $race;
		
		if( $race == 'active'){
			$race_id = $h3_mgmt_races->get_active_race();
		}

		$race_setting = $h3_mgmt_races->get_race_setting( $race_id );

		//if registration isn't already open return nothing
		if( $race_setting['status'] == 0 ){
			return;	
		}
		
        if (($_POST['todo'] ?? $_GET['todo'] ?? '') === 'resend') {
            if (!H3_MGMT_Security::post('cor-mgmt-team-' . (int) $race_id)) { return H3_MGMT_Security::error(); }
            try {
                \Cor\Mgmt\WordPress\TeamBoundary::manager()->resendConfirmation((int) $race_id, get_current_user_id(), \Cor\Mgmt\WordPress\TeamBoundary::value('confirmation_request'));
            } catch (\Throwable $error) {
                return '<p>' . esc_html(\Cor\Mgmt\WordPress\TeamBoundary::message($error)) . '</p>';
            }
        }

		$output = '';

		if( ! is_user_logged_in() ) {
			$output .= '<p class="error">' .
				_x( 'You have to be logged in to create or edit a team!', 'Error Message', 'h3-mgmt' ) .
				'</p>';
			return $output;
		}

		$team_id = $this->user_has_team( $race_id );

		if( $team_id === false ) {
			if( $this->is_invitation() ) {
				$team_id = $this->handle_invitation();
				if( ! empty( $team_id ) ) {
					$is_invitation = true;
				}
			}
		}

		if( $team_id !== false ) {
			$output .= '<h3>' . _x( 'Homework Assignments', 'Homework', 'h3-mgmt' ) . '</h3>';
			$team_query = $wpdb->get_results(
				"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_teams " .
				"WHERE id = " . $team_id, ARRAY_A
			);
			$team = $team_query[0];
		} else {
			//if race started no invitation possible so don't show it
			if( $race_setting['status'] == 3 ){
				return;
			}
			
			$output .= '<h3>' . _x( 'Or join a team...', 'Team Dashboard (Registration)', 'h3-mgmt' ) . '</h3>' .
				'<p>' . _x( 'If someone else has already invited you via email to join his/her team, you have received an invitation code. Enter or paste it here.', 'Team Dashboard (Registration)', 'h3-mgmt' ) . '</p>';
			$output .= $this->invitation_code_form();
			return $output;
		}

		$own_query = $wpdb->get_results(
			"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_teammates " .
			"WHERE user_id = " . $current_user->ID . " AND team_id = " . $team_id, ARRAY_A
		);
		$own_data = isset( $own_query[0] ) ? $own_query[0] : array();
		$own_data['shirt'] = get_user_meta( $current_user->ID, 'shirt_size', true );
		$own_data['public_mobile_inf'] = get_user_meta( $current_user->ID, 'public_mobile_inf', true );

		$others_query = $wpdb->get_results(
			"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_teammates " .
			"WHERE team_id = " . $team_id . " AND user_id != " . $current_user->ID, ARRAY_A
		);
		$others_waiver = 0;
		$others_paid = 0;
		$others_shirt = 0;
		$other_public_mobile_inf = 0;
		$invited = 0;
		if( ! empty( $others_query ) ) {
			$others_waiver = 1;
			$others_paid = 1;
			$others_shirt = 1;
			$other_public_mobile_inf = 1;
			foreach( $others_query as $hitchmate ) {
				if( $hitchmate['paid'] == 0 ) {
					$others_paid = 0;
				}
				if( $hitchmate['waiver'] == 0 ) {
					$others_waiver = 0;
				}
				$shirt = get_user_meta( $hitchmate['user_id'], 'shirt_size', true );
				if( empty( $shirt ) ) {
					$others_shirt = 0;
				}
				$public_mobile_inf = get_user_meta( $hitchmate['user_id'], 'public_mobile_inf', true );
				if( empty( $public_mobile_inf ) ) {
					$other_public_mobile_inf = 0;
				}
			}
			$invited = 1;
		} else {
			$invitations_query = $wpdb->get_results(
				"SELECT * FROM ".$wpdb->prefix."h3_mgmt_invitations WHERE team_id = ".$team_id,
				ARRAY_A
			);
			if( ! empty( $invitations_query ) ) {
				$invited = 1;
			}
		}
		
		$owner = $h3_mgmt_sponsors->list_sponsors( array(
			'type' => 'owner',
			'team_id' => $team_id
		));

		$sponsors = $this->get_sponsors( $team_id );
		
		$phones = $this->get_team_phones( $team_id ); 	
		
		
		$output .= '<p>' . stripcslashes( $information_text[32] ). '</p>';
			
		$output .= '<h3>' . _x( 'Before publishing', 'Homework', 'h3-mgmt' ) . '</h3>' .
			'<ul class="list homework-list">';

		if( $invited === 1 ) {
			$output .= '<li><span class="to-do-done">';
		} else {
			$output .= '<li><span class="to-do-do">';
		}
		$output .= stripcslashes( $information_text[33] ) . '</span>' .
		 '</li>';

                if( $race_setting['dis_waiver'] != 1 ){
                    if( isset( $own_data['waiver'] ) && $own_data['waiver'] == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[34] ) . ' *</span>' .
                     '</li>';

                    if( $others_waiver == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[35] ) . '</span>' .
                     '</li>';
                }

                if( $race_setting['dis_fee'] != 1 ){
                    if( isset( $own_data['paid'] ) && $own_data['paid'] == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[28] ) . ' **</span>' . 
                     '</li>';

                    if( $others_paid == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[29] ) . '</span>' . 
                            '</li>';
                }
                
                if( $race_setting['dis_shirt_size'] != 1 ){
                    if( ! empty( $own_data['shirt'] ) ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[36] ) . '</span>' .
                     '</li>';

                    if( $others_shirt == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[37] ) . '</span>' .
                            '</li>';
                }
		
                if( $race_setting['dis_mobile_inf'] != 1 ){
                    if( ! empty( $own_data['public_mobile_inf'] ) ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[38] ) . ' </span>' .
                     '</li>';

                    if( $other_public_mobile_inf == 1 ) {
                            $output .= '<li><span class="to-do-done">';
                    } else {
                            $output .= '<li><span class="to-do-do">';
                    }
                    $output .= stripcslashes( $information_text[39] ) . '</span>' .
                            '</li>';
                }
                $output .= '</ul>';

				$base_url = get_option( 'siteurl' );
				$downloads_url = $base_url . '/downloads/';
                if( $race_setting['dis_waiver'] != 1 ){
                    $output .= stripcslashes( $information_text[16] ); 
                }
                
                if( $race_setting['dis_fee'] != 1 ){
					$post     = get_post();
					$post_url = get_page_link( $post->ID );
					$output .= stripcslashes( $information_text[17] ) 
                        . '<form method="post" action="' . esc_url($post_url) . '">' . H3_MGMT_Security::nonce('cor-mgmt-team-' . (int) $race_id) .
                        '<input type="hidden" name="todo" value="resend"><input type="hidden" name="id" value="' . (int) $team['id'] . '">' .
                        '<input type="hidden" name="confirmation_request" value="' . esc_attr(bin2hex(random_bytes(16))) . '">' .
                        '<button type="submit">' . esc_html_x('Resend Mail.', 'Homework', 'h3-mgmt') . '</button></form>';

				}

		$output .= '<h3>' . _x( 'After Team is complete', 'Homework', 'h3-mgmt' ) . '</h3>' .
			'<ul class="list homework-list">';

		if( $team['complete'] == 1 ) {
			$output .= '<li><span class="to-do-confirm">' . _x( 'Your Team is fully registered!', 'Homework', 'h3-mgmt' ) . '</span></li>';
			$team_data = $this->get_team_data( $team_id );
		
			//is just one startingpoint for this race it dosen't dispplay the homework for choosing one
			if( $race_setting['startingpoint'] == 1 ){
				if( ! empty( $team['route_id'] ) ) {
					$output .= '<li><span class="to-do-route" style="text-shadow:0 0 2px #' .$h3_mgmt_races->get_route_color( $team['route_id'] ) . ';">' . _x( 'Starting point chosen', 'Homework', 'h3-mgmt' ) . ': ' . $h3_mgmt_races->get_route_name( $team['route_id'] ) . '</span></li>';
				} else {
					$output .= '<li><span class="to-do-can">' . _x( 'You may pick your starting point now!', 'Homework', 'h3-mgmt' ) . '</span></li>';
				}
			}
                        if( $race_setting['kind_of_donation_tool'] != 1 ){
                            if( $this->get_owner( $team_id ) ) {
                                    $output .= '<li><span class="to-do-positive">' . _x( 'You have a TeamOwner', 'Homework', 'h3-mgmt' ) . ': ' . $owner['names'] . '</span></li>';
                            } else {
                                    $output .= '<li><span class="to-do-can">' . _x( "You don't have a TeamOwner yet. You can hunt for one now!", 'Homework', 'h3-mgmt' ) . '</span></li>';
                            }
                        }
			if( $sponsors ) {
				$output .= '<li><span class="to-do-positive">' . sprintf( _x( 'You have %d TeamSponsors', 'Homework', 'h3-mgmt' ), count( $sponsors ) ) . '</span></li>';
			} else {
				$output .= '<li><span class="to-do-can">' . _x( "You don't have TeamSponsors yet. You can hunt for 'em now!", 'Homework', 'h3-mgmt' ) . '</span></li>';
			}
                        
                        if( $race_setting['show_donation_amount'] == 1 ){
                            $amount = \Cor\Mgmt\Domain\DonationAmounts::whole($h3_mgmt_sponsors->get_donation_amount( array('type'=>'team', 'id' => $team_id) ));
                            if( $amount > 0 ) {
                                    $output .= '<li><span class="to-do-positive">' . sprintf( _x( 'Your team donations counter: %s EUR ', 'Homework', 'h3-mgmt' ), $amount ) . '</span></li>';
                            } else {
                                    $output .= '<li><span class="to-do-can">' . sprintf( _x( 'Your team donations counter: %s EUR ', 'Homework', 'h3-mgmt' ), $amount ) . '</span></li>';
                            }
                        }
                        
		} else {
			$output .= '<li><span class="to-do-negative">' . _x( 'Your team has not yet completed the registration process...', 'Homework', 'h3-mgmt' ) . '</span></li>';
                        if( $race_setting['startingpoint'] == 1 ){
                            $output .= '<li><span class="to-do-inactive">' . _x( 'You cannot yet chose a starting point...', 'Homework', 'h3-mgmt' ) . '</span></li>';
                        }
			$output .= '<li><span class="to-do-inactive">' . _x( 'You cannot hunt for sponsors yet...', 'Homework', 'h3-mgmt' ) . '</span></li>';
		}

		$output .= '</ul>';

		if( $team['complete'] == 1 ) {
			$output .= stripcslashes( $information_text[18] ); //'<p>' . _x( 'Elvis has left the building!. You can go out and hunt for TeamSponsors to support the WASH-projects of Viva con Agua and the work of PRO ASYL!', 'Homework', 'h3-mgmt' ) . '</p>';
		}

		return $output;
	}

	/**
	 * Outputs a link for the user-panel
	 *
	 * @since 1.0
	 * @access public
	 */
	public function user_panel_link( $atts = '' ) {
		global $h3_mgmt_races;

		extract( shortcode_atts( array(
			'event' => 0,
			'race' => 0
		), $atts ) );

		if( $race == 'active'){
			$race = $h3_mgmt_races->get_active_race();
		}
		
		if( $this->user_has_team( $race ) ) {
			$link = '<a title="' .
				_x( 'Edit your team', 'User Panel', 'h3-mgmt' ) .
				'" href="' . get_site_url() . _x( '/participate/team-dashboard/', 'utility translation', 'h3-mgmt' ) . '">'.
					_x( 'Edit Team', 'User Panel', 'h3-mgmt' ) .
				'</a>';
		} else {
			$link = '<a title="' .
				sprintf(
					_x( 'Register or join a team for %s', 'User Panel', 'h3-mgmt' ),
					$h3_mgmt_races->get_name( $race, 'race' )
				).
				'" href="' . get_site_url() . _x( '/participate/team-dashboard/', 'utility translation', 'h3-mgmt' ) . '">'.
					_x( 'Register / Join Team', 'User Panel', 'h3-mgmt' ) .
				'</a>';
		}
		return $link;
	}

	/**
	 * Controls what is output in the team view
	 *
	 * @since 1.0
	 * @access public
	 */
	public function display_teams( $atts = '' ) {

		extract( shortcode_atts( array(
			'event' => 0,
			'race' => 0,
			'max' => 10
		), $atts ) );
		
		global $h3_mgmt_races, $information_text;
		
		$race_id = $race;
		
		if( $race == 'active'){
			$race_id = $h3_mgmt_races->get_active_race();
		}
		
		$information_text = $h3_mgmt_races->get_race_information_text( $race_id );
		
		$race_setting = $h3_mgmt_races->get_race_setting( $race_id );
		
		//if registration still isn't open return error message
		if( $race_setting['status'] == 0 ){
			$output .= '<p class="message" style="text-align: center;">' .
						stripcslashes( $information_text[25] ) .
						'</p>';
			$output .= '<br><br><br><br><br><br><br><br><br><br><br><br>';
			return $output;	
		}
		
		if( isset( $_GET['id'] ) && $this->team_exists( $_GET['id'] ) ) {
			return $this->team_profile( $_GET['id'], $max );
		}

		return $this->team_overview( $race_id );
	}

	/**
	 * Outputs a single team profile
	 *
	 * @since 1.0
	 * @access private
	 */
	private function team_profile( $team_id, $max ) {
        $team_id = H3_MGMT_Security::id($team_id);
		global $h3_mgmt_races, $h3_mgmt_sponsors, $h3_mgmt_ticker, $h3_mgmt_utilities, $information_text;

		$race_id = $this->get_team_race( $team_id );
		$race_setting = $h3_mgmt_races->get_race_setting( $race_id );

		[ $team_count, $complete_count, $incomplete_count, $teams ] = $this->get_teams_meta( array( 'orderby' => 'total_points', 'order' => 'DESC', 'exclude_incomplete' => true, 'parent' => intval( $race_id ), 'parent_type' => 'race' ) );

		$previous = array(
			'total' => 999
		);
		$current_rank = array(
			'total' => 0
		);
		$skip_rank = array(
			'total' => 1
		);

		for ( $i = 0; $i < 1000; $i++ ) {
			$previous[$i] = 999;
			$current_rank[$i] = 0;
			$skip_rank[$i] = 1;
		}

		$i = 0;
		foreach( $teams as $team ) {
			$i++;

			if( $previous['total'] > $team['total_points'] ) {
				$current_rank['total'] = $current_rank['total'] + $skip_rank['total'];
				$skip_rank['total'] = 1;
			} else {
				$skip_rank['total'] = $skip_rank['total'] + 1;
			}

			if( $previous[$team['route_id']] > $team['total_points'] ) {
				$current_rank[$team['route_id']] = $current_rank[$team['route_id']] + $skip_rank[$team['route_id']];
				$skip_rank[$team['route_id']] = 1;
			} else {
				$skip_rank[$team['route_id']] = $skip_rank[$team['route_id']] + 1;
			}

			if( $team['id'] == $team_id ) {
				$total_rank = $current_rank['total'];
				$route_rank = $current_rank[$team['route_id']];
			}
		}
		$total_rank = isset( $total_rank ) ? $total_rank : 0;
		$route_rank = isset( $route_rank ) ? $route_rank : 0;

		$sponsors = $h3_mgmt_sponsors->list_sponsors( array(
			'type' => 'sponsor',
			'team_id' => $team_id,
			'delimiter' => '<br />'
		));
		$owner = $h3_mgmt_sponsors->list_sponsors( array(
			'type' => 'owner',
			'team_id' => $team_id
		));

		 $owner['owcner_pic'] = $h3_mgmt_utilities->pic_resize( $owner['owner_pic'], 250 ); //test rausnehen

		$team = $this->get_team_data( $team_id, array( 'mates' ) );
		 $team['team_pic'] = $h3_mgmt_utilities->pic_resize( $team['team_pic'], 500 ); //test rausnehen

		$routes_data =  $h3_mgmt_races->get_routes( array( 'race' => $race_id ) );

		$output = '<h1>' . esc_html(stripslashes( $team['team_name'] )) . '</h1>';
		
		//enable if status is race started
		if( $race_setting['status'] == 3 && $race_id > 3){
			$output .= $h3_mgmt_ticker->team_ticker_Page_map( $team_id );			
		}

		$output .= '<div class="flex_column av_one_third first avia-builder-el-0  el_before_av_one_third  avia-builder-el-first">';

		$output .= '<img style="margin-bottom:2.3em;" class="no-bsl-adjust team-profile-pic clear-both" alt="Team Picture" src="' .
				$team['team_pic'] . '" />';

		if( ! empty( $team['route_id'] ) ) {
			$output .= '<img class="no-bsl-adjust team-profile-route-logo" alt="Route Logo" src="' .
				get_option( 'siteurl' ) . $routes_data[$team['route_id']]['logo_url'] . '" />';
		}
		
		$output .= '<p style="color:#666666;font-style:italic;font-size:1.2em;font-weight: bold;margin-bottom:0 !important;padding-bottom:0 !important;">' . stripcslashes( $information_text[1] ) . '</p>'; //_x( 'Two weeks through Europe by thumb. Why?', 'Team Profile Form', 'h3-mgmt' )
		$ddd = ! empty($team['meta_1']) ? $team['meta_1'] : '---';
		$output .= '<p>' . stripcslashes( $ddd )  . '</p>';

		$output .= '<p style="color:#666666;font-style:italic;font-size:1.2em;font-weight: bold;margin-bottom:0 !important;padding-bottom:0 !important;">' . stripcslashes( $information_text[2] ) . '</p>';//_x( 'Why should a lift take us along?', 'Team Profile Form', 'h3-mgmt' )
		$ddd = ! empty($team['meta_2']) ? $team['meta_2'] : '---';
		$output .= '<p>' . stripcslashes( $ddd )  . '</p>';

		$output .= '<p style="color:#666666;font-style:italic;font-size:1.2em;font-weight: bold;margin-bottom:0 !important;padding-bottom:0 !important;">' . stripcslashes( $information_text[3] ) . '</p>';//_x( 'Our best Autostop-experience so far', 'Team Profile Form', 'h3-mgmt' ) . ':</p>';
		$ddd = ! empty($team['meta_3']) ? $team['meta_3'] : '---';
		$output .= '<p>' . stripcslashes( $ddd )  . '</p>';

		$output .= '<p style="color:#666666;font-style:italic;font-size:1.2em;font-weight: bold;margin-bottom:0 !important;padding-bottom:0 !important;">' . stripcslashes( $information_text[4] ) . '</p>';//_x( 'Our goal for the race', 'Team Profile Form', 'h3-mgmt' ) . ':</p>';
		$ddd = ! empty($team['meta_4']) ? $team['meta_4'] : '---';
		if (! empty($team['meta_4'])) {
			if ($team['meta_4'] == 1) {
				$ddd = stripcslashes( $information_text[12] ); //_x( 'Reach the destination. Participation is everything!', 'Team Profile Form', 'h3-mgmt' );
			} elseif ($team['meta_4'] == 2) {
				$ddd = stripcslashes( $information_text[13] ); //_x( 'Fun, Fun, Fun!', 'Team Profile Form', 'h3-mgmt' );
			} elseif ($team['meta_4'] == 3) {
				$ddd = stripcslashes( $information_text[14] ); //_x( 'Upper Midfield. And a stage victory.', 'Team Profile Form', 'h3-mgmt' );
			} elseif ($team['meta_4'] == 4) {
				$ddd = stripcslashes( $information_text[15] ); //_x( 'Win it! What else???', 'Team Profile Form', 'h3-mgmt' );
			}
		}
		$output .= '<p>' . stripcslashes( $ddd ) . '</p>';

		$output .= '<p style="color:#666666;font-style:italic;font-size:1.2em;font-weight: bold;margin-bottom:0 !important;padding-bottom:0 !important;">' . stripcslashes( $information_text[5] ) . '</p>';
		$ddd = ! empty($team['meta_5']) ? $team['meta_5'] : '---';
		$output .= '<p>' . stripcslashes( $ddd ) . '</p>';

		$output .= '</div><div class="flex_column av_one_third avia-builder-el-0  el_before_av_one_third">';

		$output .= '<p>';
		foreach( $team['mates'] as $mate ) {
			$output .= stripcslashes( $mate['name'] ) . ' (' . $mate['age'] . ')<br />';
		}

		//enable if status is race started
		if( $race_setting['status'] == 3 ){
			$output .= '</p><p>' .     
					__( 'Current Race Rank', 'h3-mgmt' ) . ': <strong>' . $total_rank . '</strong><br />' .
					__( 'Current Route Rank', 'h3-mgmt' ) . ': <strong>' . $route_rank . '</strong></p>';
		}

		$output .= '<p>' . wp_kses_post(stripslashes( $h3_mgmt_utilities->p1_nl2br( $team['description'] ) )) . '</p>';

		$output .= '</p>';
		
		$output .= '<h3 class="topspace">' . _x( 'Ticker messages', 'Team Profile', 'h3-mgmt' ) . '</h3>';
		$output .= $h3_mgmt_ticker->team_ticker( $team_id, $max );

		$output .= '</div><div class="flex_column av_one_third  avia-builder-el-0  avia-builder-el-last">';
		
                if( $race_setting['kind_of_donation_tool'] != 1 ){
                    $output .= '<h3 class="no-margin-top">' . _x( 'Team Owner', 'Team Profile', 'h3-mgmt' ) . '</h3>' .
                            '<div class="owner-pic-wrap">';

                    //enable if donation tool is started
                    if( $race_setting['donation'] == 0 ){
                         if ( ! empty( $owner['owner_link_url'] ) ) {
                                $output .= '<a href="' . $owner['owner_link_url'] . '" title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '">';
                        }
                        $output .= '<img class="owner-pic" alt="OwnerPic" src="' . $owner['owner_pic'] . '" />';
                        if ( ! empty( $owner['owner_link_url'] ) ) {
                                $output .= '</a>';
                        }
                        if( $this->get_owner($team_id) == false){
                            $output .= '</div>' .
                                        '<p> '._x( 'No Owner yet. <br> You can be a Owner after the donation process has been started!', 'Team Profile', 'h3-mgmt' ) . '</p>';
                        }else{
                            $output .= '</div>' .
                                $owner['owner_link'];
                        }
                    }else{
                        if ( ! empty( $owner['owner_link_url'] ) ) {
                                $output .= '<a href="' . $owner['owner_link_url'] . '" title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '">';
                        }
                        $output .= '<img class="owner-pic" alt="OwnerPic" src="' . $owner['owner_pic'] . '" />';
                        if ( ! empty( $owner['owner_link_url'] ) ) {
                                $output .= '</a>';
                        }
                        $output .= '</div>' .
                                $owner['owner_link'];
                    }
                }
			
		$output .= '<h3 class="topspace">' . _x( 'Team Sponsors', 'Team Profile', 'h3-mgmt' ) . '</h3>';

		if( ! empty( $sponsors['names-tooltip'] ) || ! empty( $sponsors['anonymous'] ) ) {
			$output .= '<p class="team-profile-sponsors">';
			if( ! empty( $sponsors['names-tooltip'] ) ) {
				$output .= $sponsors['names-tooltip'];
			}
			if( ! empty( $sponsors['anonymous'] ) ) {
				$output .= '<br />' . $sponsors['anonymous'] . ' ' . _x( 'Anonymous Sponsor(s)', 'Team Profile', 'h3-mgmt' );
			}
			$output .= '</p>';
		}

		//enable if donation tool is started
		if( $race_setting['donation'] == 0 ){
			$output .= 	'<p> ' . _x( 'You can be a Sponsor after the donation process has been started!', 'Team Profile', 'h3-mgmt' ) . ' </p>';
		}else{
			$output .= '<p>' .
				'<a class="sponsors-link" title="' . _x( 'Become this team&apos;s TeamSponsor!', 'Team Profile', 'h3-mgmt' ) . '" ' .
					'href="'. get_site_url() . $race_setting['donation_link_link'] . '?id=' . $team_id . '&type=sponsor">' .
						_x( 'Become this team\'s TeamSponsor!', 'Team Profile', 'h3-mgmt' ) .
						'</a></p>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Returns a random team ID
	 *
	 * @since 1.0
	 * @access private
	 */
    private function random_team($race_id = 1) {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}h3_mgmt_teams WHERE race_id=%d AND complete=1", H3_MGMT_Security::id($race_id)));
        return $ids ? (int) $ids[\Cor\Mgmt\WordPress\Services::get()->random->integer(0, count($ids) - 1)] : 0;
    }

	/**
	 * Returns the quick_info
	 * of a random team
	 *
	 * @since 1.0
	 * @access public
	 */
	public function random_team_info( $atts = '' ) {
        global $h3_mgmt_races;

		extract( shortcode_atts( array(
			'event' => 0,
			'race' => 0
		), $atts ) );

		$race_id = $race;
		
		if( $race == 'active'){
			$race_id = $h3_mgmt_races->get_active_race();
		}

		$team_id = $this->random_team( $race_id );

		return $team_id ? $this->team_quick_info($team_id) : '';
	}

	/**
	 * Outputs a single team profile
	 *
	 * @since 1.0
	 * @access private
	 */
	private function team_quick_info( $team_id ) {
        $team_id = H3_MGMT_Security::id($team_id);
		global $h3_mgmt_races, $h3_mgmt_sponsors, $h3_mgmt_utilities;

		$sponsors = $h3_mgmt_sponsors->list_sponsors( array(
			'type' => 'sponsor',
			'team_id' => $team_id,
			'delimiter' => '<br />'
		));
		$owner = $h3_mgmt_sponsors->list_sponsors( array(
			'type' => 'owner',
			'team_id' => $team_id
		));

		$team = $this->get_team_data( $team_id, array( 'mates' ) );
		 $team['team_pic'] = $h3_mgmt_utilities->pic_resize( $team['team_pic'], 150 );		//test rausnehmen

		$race_id = $this->get_team_race( $team_id );
		$routes_data =  $h3_mgmt_races->get_routes( array( 'race' => $race_id ) );
                
                $race_setting = $h3_mgmt_races->get_race_setting( $race_id );

		$output = '<div class="quick-info-wrap">';

		if( ! empty( $team['route_id'] ) ) {
			$output .= '<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
				get_option( 'siteurl' ) . $routes_data[$team['route_id']]['logo_url'] . '" />';
		} else {
			$output .= '<span style="font-size: 2.1em; font-family: BebasNeue, sans-serif; float:left;">?</span>';
		}

		$output .= '<h2 class="first">' . esc_html(stripslashes( $team['team_name'] )) . '</h2>';

		$output .= '<img class="no-bsl-adjust team-profile-pic" alt="Team Picture" src="' .
				$team['team_pic'] . '" />';

		$mates = array();
		foreach( $team['mates'] as $mate ) {
			$mates[] = $mate['name'];
		}
		$output .= '<p>' . implode( ', ', $mates );

		$output .= '<br />' . _x( 'Team Owner', 'Team Profile', 'h3-mgmt' ) . ': ';
		if( $this->get_owner( $team_id ) ) {
			$output .= $owner['raw_owner_link'];
		} else {
			$output .= _x( 'none', 'Team Profile, TeamOwner', 'h3-mgmt' );
		}
		$output .= '<br />' .
			_x( 'Team Sponsors', 'Team Profile', 'h3-mgmt' ) . ': ' .
			$sponsors['count'];

		$output .= '<br /><a title="' .
				_x( 'Check the TeamProfile ...', 'Team', 'h3-mgmt' ) .
			'" href="' . get_site_url() . $race_setting['team_overview_link'] . '?id=' . $team_id . '">' .
			'&rarr; ' . _x( 'view TeamProfile', 'Team', 'h3-mgmt' ) .
			'</a></p>';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Outputs the team overview
	 *
	 * @since 1.0
	 * @access private
	 */
	private function team_overview( $race_id = 1 ) {
        $race_id = H3_MGMT_Security::id($race_id);
		global $h3_mgmt_races, $h3_mgmt_utilities;

		[ $team_count, $complete_count, $incomplete_count, $teams ] = $this->get_teams_meta( array(
			'orderby' => 'id',
			'oderder' => 'ASC',
			'exclude_incomplete' => true,
			'extra_fields' => array( 'mates', 'sponsor-count' ),
			'parent' => $race_id,
			'parent_type' => 'race'
		));
		$teams_html = array();

		$routes_data = $h3_mgmt_races->get_routes( array(
			'race' => $race_id,
			'orderby' => 'name',
			'order' => 'ASC'
		));

		if( isset( $_GET[ 'todo' ] ) ) {
			return do_shortcode( "[h3-ticker-message race=$race_id comment_get=false]" );
		}
		
		foreach( $teams as $team ) {
			$team['team_pic'] = $h3_mgmt_utilities->pic_resize( $team['team_pic'], 150 ); //test rausnehmen
			$mates_html = '';
			$mcount = count( $team['mates'] );
			$i = 0;
			foreach( $team['mates'] as $mate ) {
				$mates_html .= $mate['name'] . ' (' . $mate['age'] . ', ' . $mate['city'] . ')';
				$i++;
				if( $i < $mcount ) {
					$mates_html .= '<br />';
				}
			}
			if( $this->get_owner( $team['id'] ) ) {
				$owner_class = 'owner-yes';
			} else {
				$owner_class = 'owner-not';
			}
			if( ! empty( $team['route_id'] ) ) {
				$route_image = '<img class="no-bsl-adjust team-overview-route-logo" alt="Route Logo" src="' .
					get_option( 'siteurl' ) . $routes_data[$team['route_id']]['logo_url'] . '" />';
			}
			$teams_html[] = '<a class="team-overview-route-' . $team['route_id'] . ' ' . $owner_class . '" title="' .
				_x( "See this team's complete profile", 'Team Profile', 'h3-mgmt' ) .
				'" href="http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '?id=' . $team['id'] . '">' . //get_option( 'siteurl' ) . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI'] )
				'<div style="box-shadow:none !important;" class="team-island team-overview-route-island-' . $team['route_id'] . '" ' .
					'style="border: 2px solid #' . $routes_data[$team['route_id']]['color_code'] . '">' .
					'<span class="team-overview-route" style="display:none;visibility:hidden;">' .
						 $routes_data[$team['route_id']]['name'] . '</span>' .
					'<span class="sponsor-count" style="display:none;visibility:hidden;">' .
						$team['sponsor-count'] . '</span>' .
					'<div class="team-name-wrap">' . $route_image .
						'<h5 class="team-name">' . esc_html($team['team_name']) . '</h5>' .
					'</div>' .
					'<div class="team-overview-pic-wrap"><img class="no-bsl-adjust team-overview-pic" alt="Team Picture" src="' .
						$team['team_pic'] . '" /></div>' .
					'<p class="team-mates">' .
						$mates_html . '</p>' .
				'</div></a>';
		}

		$output = '<div class="grid-row"><div class="col12">' .
				$this->isotope_links( $race_id ) .
			'</div></div>' .
			'<div class="grid-row"><div class="col12">' .
				'<div id="teams-container">' . implode( '', $teams_html ) . '</div>' .
			'</div></div>';

		$output .= '<style>';
		foreach( $routes_data as $route_id => $route ) {
			[ $r, $g, $b ] = $h3_mgmt_utilities->hex2rgb( $route['color_code'] );
			$output .= '.no-touch .team-overview-route-island-' . $route_id . ':hover,' .
				'.team-overview-route-island-' . $route_id . ':active {' .
					'box-shadow: -2px 2px 5px 0 rgba(' . $r . ', ' . $g . ', ' . $b . ', .7);' .
				'}';
		}
		$output .= '.no-touch .team-overview-route-island-0:hover,' .
			'.team-overview-route-island-0:active {' .
				'box-shadow: -2px 2px 5px 0 rgba( 255, 255, 255, .7);' .
			'}';
		$output .= '</style>';
		
		return $output;
	}

	/**
	 * Returns formatted HTML output
	 * for isotope filtering links to be displayed
	 * via a shortcode
	 *
	 * @since 1.0
	 * @access private
	 */
	private function isotope_links( $race_id = 1 ) {
		$output = '<div class="isotope-wrap"><h2 style="width: 100%; text-align: left;" class="isotope-heading">' .
				__( 'Jump to TeamProfile', 'h3-mgmt' ) .
			'</h2>' .
			'<select class="team-selector" style="display:inline-block;margin-right:1.4em; width:auto; font-size: 1.5em;">';
		$options = $this->select_options( array(
			'please_select' => true,
			'exclude_with_owner' => false,
			'exclude_incomplete' => true,
			'show_mates' => true,
			'select_text' => __( 'Select team ...', 'h3-mgmt' ),
			'race' => $race_id
		));
		foreach( $options as $option ) {
			$output .= '<option value=' . $option['value'] . '>' . $option['label'] . '</option>';
		}
		$output .= '</select><a style="margin-top:1px;font-size:1.4em;display:inline-block;" class="jumper button" href="#" title="Show me the team!"> ' .
				__( 'Jump', 'h3-mgmt' ) .
			'</a></div>';

		return $output;
	}

	/*************** TEAM REGISTRATION: VALIDATION & SAVING ***************/

	/**
	 * Validates a submitted dashboard
	 *
	 * @since 1.0
	 * @access private
	 */
	private function validate_submit() {
		$errors = array();
		$valid = true;

		if( ! empty( $_POST['address'] ) ) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( 'You have been identified as a spam bot. Screw you!', 'Team Dashboard', 'h3-mgmt' )
			);
			$valid = false;
		}

		if( empty( $_POST['team_name'] ) || empty( $_POST['first_name'] ) || empty( $_POST['last_name'] ) ) {
			$errors[] = array(
				'type' => 'error',
				'message' => '<u>' . _x( 'The team could not be saved.', 'Team Dashboard', 'h3-mgmt' ) . '</u>' . _x( "You have not filled out all required fields.", 'Team Dashboard', 'h3-mgmt' )
			);
			$valid = false;
		}

		return array( $valid, $errors );
	}

	/**
	 * Saves dashboard data
	 * If applicable, triggers invitation(s)
	 * If also applicable, deletes previous invitation(s) from database
	 *
	 * @since 1.0
	 * @access private
	 */
    private function save_dashboard($race_id = 1) {
        try {
            \Cor\Mgmt\WordPress\TeamBoundary::save((int) $race_id);
            unset($_POST['submitted']);
            return \Cor\Mgmt\WordPress\TeamFrontend::dashboard((int) $race_id);
        } catch (\Throwable $error) {
            return H3_MGMT_Security::error();
        }
    }

	/*************** CONSTRUCTORS ***************/

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_shortcode( 'h3-team-dashboard-homework', array( $this, 'team_dashboard_homework_control' ) );
		add_shortcode( 'h3-team-dashboard', array( $this, 'team_dashboard_control' ) );
		add_shortcode( 'h3-team-homework', array( $this, 'team_homework' ) );
		add_shortcode( 'h3-user-panel-link', array( $this, 'user_panel_link' ) );
		add_shortcode( 'h3-teams', array( $this, 'display_teams' ) );
		add_shortcode( 'h3-rand-team', array( $this, 'random_team_info' ) );	
	}
} 

endif; // class exists
