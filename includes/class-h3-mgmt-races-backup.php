<?php

/**
 * H3_MGMT_Races class.
 *
 * This class contains properties and methods for
 * the display and handling of races, routes and stages
 * in the frontend
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Races' ) ) :

class H3_MGMT_Races {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	private $the_race = false;

	/*************** UTILITY METHODS ***************/

	/**
	 * Returns an array of raw race data
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_races( $args = array() ) {
		global $wpdb;

		$default_args = array(
			'race' => 'all',
			'orderby' => 'start',
			'order' => 'ASC',
			'data' => 'all'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if ( is_numeric( $race ) ) {
			$where = 'WHERE id = ' . $race . ' ';
		} else {
			$where = '';
		}

		$races_query = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "h3_mgmt_races " .
				$where .
				"ORDER BY " .
				$orderby . " " . $order, ARRAY_A
		);

		if ( 'all' !== $data ) {
			$races = array();
			if ( ! empty( $races_query ) ) {
				foreach ( $races_query as $race_data ) {
					if ( ! empty( $race_data[$data] ) ) {
						if ( $data === 'id' ) {
							$races[] = $race_data[$data];
						} else {
							$races[$race_data['id']] = $race_data[$data];
						}
					}
				}
			}
		} else {
			$races = $races_query;
		}

		return $races;
	}

	/**
	 * Returns an array of raw route data
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_routes( $args = array() ) {
		global $wpdb;

		$default_args = array(
			'race' => 1,
			'orderby' => 'name',
			'order' => 'ASC'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if ( is_numeric( $race ) ) {
			$where = 'WHERE race_id = ' . $race . ' ';
		} else {
			$where = '';
		}

		$routes = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				$where .
				"ORDER BY " .
				$orderby . " " . $order, ARRAY_A
		);

		$routes_by_id = array();
		foreach ( $routes as $route ) {
			$routes_by_id[$route['id']] = $route;
		}

		return $routes_by_id;
	}

	/**
	 * Returns an array of raw stage data
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_stages( $args = array() ) {
		global $wpdb;

		$default_args = array(
			'parent' => 1,
			'parent_type' => 'route',
			'omit_start' => false,
			'orderby' => 'destination',
			'order' => 'ASC'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if ( is_numeric( $parent ) && 'race' === $parent_type ) {
			$where = 'WHERE race_id = ' . $parent . ' ';
		} elseif ( is_numeric( $parent ) && 'route' === $parent_type ) {
			$where = 'WHERE route_id = ' . $parent . ' ';
		} else {
			$where = '';
		}

		if( $omit_start === true ) {
			if( empty( $where ) ) {
				$where = "WHERE number != 0 ";
			} else {
				$where .= "AND number != 0 ";
			}
		}

		$stages = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "h3_mgmt_stages " .
				$where .
				"ORDER BY " .
				$orderby . " " . $order, ARRAY_A
		);

		return $stages;
	}

	/**
	 * Returns the name/destination of an event, route or stage
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_route_name( $id ) {
		return $this->get_name( $id, 'route' );
	}
	public function get_name( $id, $type = 'race' ) {
		global $wpdb, $h3_mgmt_utilities;

		$key = 'stage' === $type ? 'destination' : 'name';

		$name_query = $wpdb->get_results(
				"SELECT " . $key . " FROM " .
				$wpdb->prefix . "h3_mgmt_" . $type . "s " .
				"WHERE id = " . $id . " " .
				"LIMIT 1",
				ARRAY_A
		);
		$name = isset( $name_query[0][$key] ) ? $name_query[0][$key] : sprintf( __( 'The selected %s does not exist...', 'h3-mgmt' ), $h3_mgmt_utilities->convert_strings( $type ) );

		return $name;
	}

	/**
	 * Returns the parent event of a route
	 *
	 * @since 1.1
	 * @access public
	 */

	/**
	 * Returns a route name (string)
	 * when fed a corresponding id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_route_parent( $id ) {
		global $wpdb;

		$race_query = $wpdb->get_results(
				"SELECT race_id FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				"WHERE id = " . $id . " " .
				"LIMIT 1",
				ARRAY_A
		);
		$race_id = isset( $race_query[0]['race_id'] ) ? $race_query[0]['race_id'] : false;

		return $race_id;
	}

	/**
	 * Returns an array of route user account IDs
	 *
	 * @since 1.0
	 * @access private
	 */
	private function get_route_users( $race_id = 2 ) {
		global $wpdb;

		$users_query = $wpdb->get_results(
			"SELECT user_id FROM " .
			$wpdb->prefix."h3_mgmt_routes " .
			"WHERE race_id = " . $race_id,
			ARRAY_A
		);

		$users = array();

		foreach( $users_query as $user ) {
			$users[] = $user['user_id'];
		}

		return $users;
	}

	/**
	 * Returns a route ID corresponding to a user ID
	 *
	 * @since 1.0
	 * @access private
	 */
	private function get_route_by_user( $user_id ) {
		global $wpdb;

		$route_query = $wpdb->get_results(
			"SELECT id FROM " .
			$wpdb->prefix."h3_mgmt_routes " .
			"WHERE user_id = " . $user_id,
			ARRAY_A
		);

		$route = $route_query[0]['id'];

		return $route;
	}

	/**
	 * Returns a route's number of free slots
	 * when fed a corresponding id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_free_slots( $id ) {
		global $wpdb;

		$route_query = $wpdb->get_results(
				"SELECT max_teams FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				"WHERE id = " . $id, ARRAY_A
		);
		$max_teams = $route_query[0]['max_teams'];

		$registered_teams = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . $wpdb->prefix . "h3_mgmt_teams " .
				"WHERE route_id = %d", $id
			)
		);

		$free_slots = $max_teams - $registered_teams;

		return $free_slots;
	}

	/**
	 * Returns a route's color (string)
	 * when fed a corresponding id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_route_color( $id ) {
		global $wpdb;

		$route_query = $wpdb->get_results(
				"SELECT color_code FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				"WHERE id = " . $id, ARRAY_A
		);
		if( ! empty( $route_query ) ) {
			$color = $route_query[0]['color_code'];
		} else {
			$color = '4e4e4e';
		}

		return $color;
	}

	/**
	 * Returns an array of routes with id as key and name as value
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_route_ids( $race = 1 ) {

		$raw = $this->get_routes( array( 'race' => $race ) );
		$routes = array();

		foreach( $raw as $route ) {
			$routes[$route['id']] = $route['name'];
		}

		return $routes;
	}

	/**
	 * Returns an array of countries
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_countries( $race = 1 ) {
		$raw = $this->get_stages( array( 'parent' => $race, 'parent_type' => 'race', 'order_by' => 'country', 'order' => 'ASC' ) );
		$countries = array();

		foreach( $raw as $stage ) {
			if( ! in_array( $stage['country'], $countries ) ) {
				$countries[] = $stage['country'];
			}
		}

		asort( $countries );

		return $countries;
	}

	/**
	 * Returns an array of options to be used in a select element
	 *
	 * @since 1.1
	 * @access public
	 */
	public function options_array( $args = array() ) {

		$default_args = array(
			'parent' => 1,
			'parent_type' => 'route',
			'data' => 'stage',
			'omit_start' => true,
			'orderby' => 'name',
			'order' => 'ASC',
			'value' => 'id',
			'label' => 'name'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if ( 'race' === $data ) {
			$raw = $this->get_races( array( 'orderby' => $orderby, 'order' => $order ) );
		} elseif ( 'route' === $data ) {
			$raw = $this->get_routes( array( 'race' => 'all', 'orderby' => $orderby, 'order' => $order ) );
		} else {
			$raw = $this->get_stages( array( 'parent' => $parent, 'parent_type' => $parent_type, 'orderby' => $orderby, 'order' => $order, 'omit_start' => $omit_start ) );
		}

		$options = array();

		foreach ( $raw as $single ) {
			$options[] = array(
				'value' => $single[$value],
				'label' => $single[$label]
			);
		}

		if ( empty( $options ) ) {
			$options[] = array(
				'label' => __( 'Please create a parent item first!', 'h3-mgmt' ),
				'value' => 0
			);
		}

		return $options;
	}

	/**
	 * Returns a list race statistics
	 * or a specific statistical value
	 *
	 * @since 1.0
	 * @access public
	 */
	public function race_stats( $atts = '' ) {
		global $h3_mgmt_teams, $h3_mgmt_sponsors;

		extract( shortcode_atts( array(
			'stat' => 'all',
			'race' => 0,
			'event' => 1,
			'offset' => 0
		), $atts ) );

		$race_id = $race === 0 ? $event : $race;

		list( $team_count, $complete_count, $incomplete_count, $rows ) = $h3_mgmt_teams->get_teams_meta( array(
			'orderby' => 'id',
			'order' => 'ASC',
			'exclude_incomplete' => false,
			'extra_fields' => array(),
			'parent' => $race_id,
			'parent_type' => 'race'
		));
		list( $participants_count, $participants_complete_count, $participants_incomplete_count, $rows ) = $h3_mgmt_teams->get_participants_meta( array(
			'orderby' => 'id',
			'order' => 'ASC',
			'exclude_incomplete' => false,
			'extra_fields' => array(),
			'parent' => $race_id,
			'parent_type' => 'race'
		));
		list( $sponsors, $sponsor_counts, $donations ) = $h3_mgmt_sponsors->get_sponsors_meta( array(
			'orderby' => 'id',
			'order' => 'ASC',
			'type' => 'all',
			'method' => 'all',
			'exclude_unpaid' => true,
			'extra_fields' => array(),
			'parent' => $race_id,
			'parent_type' => 'race'
		));


		if( $stat == 'team-count' ) {
			return $team_count;
		} elseif( $stat == 'complete-count' ) {
			return $complete_count;
		} elseif( $stat == 'participants-count' ) {
			return $participants_count;
		} elseif( $stat == 'participants-complete-count' ) {
			return $participants_complete_count;
		} elseif( $stat == 'race-sponsors-count' ) {
			$sponsor_counts['all'];
		} elseif( $stat == 'race-sponsors-count' ) {
			$sponsor_counts['race'];
		} elseif( $stat == 'sponsors-count' ) {
			return $sponsor_counts['sponsor'];
		} elseif( $stat == 'owners-count' ) {
			return $sponsor_counts['owner'];
		} elseif( $stat == 'patrons-count' ) {
			return $sponsor_counts['patron'];
		} elseif( $stat == 'structure-count' ) {
			return $sponsor_counts['structure'];
		} elseif( $stat == 'donations-total' ) {
			$donations['race'] = $donations['race'] - $offset;
			return $donations['race'];
		} elseif( $stat == 'thumbs' || $stat == 'cpk' ) {
			return $donations['thumbs'];
		} else {
			$output = '<p>' .
					_x( 'Registered Teams', 'Race Stats', 'h3-mgmt' ) . ': ' . $team_count . '<br />' .
					_x( 'Completed Teams', 'Race Stats', 'h3-mgmt' ) . ': ' . $complete_count. '<br />' .
					_x( 'Participants', 'Race Stats', 'h3-mgmt' ) . ': ' . $participants_complete_count . '<br />' .
					_x( 'TeamSponsors', 'Race Stats', 'h3-mgmt' ) . ': ' . $sponsor_counts['sponsor'] . '<br />' .
					_x( 'TeamOwners', 'Race Stats', 'h3-mgmt' ) . ': ' . $sponsor_counts['owner'] . '<br />' .
					_x( 'Donations (Thumbs)', 'Race Stats', 'h3-mgmt' ) . ': ' . $donations['race'] . ' (' . $donations['thumbs'] . ')<br />' .
				'</p>';
			return $output;
		}
	}

	/**
	 * Shortcode handler for
	 * donation counter
	 *
	 * @since 1.0
	 * @access public
	 */
	public function donation_counter( $atts ) {
		global $h3_mgmt_sponsors;

		extract( shortcode_atts( array(
			'race' => 1,
			'animation' => 0,
			'offset' => 0
		), $atts ) );

		list( $sponsors, $sponsor_counts, $donations ) = $h3_mgmt_sponsors->get_sponsors_meta( array( 'parent' => $race, 'exclude_unpaid' => false ) );
		
		$min = 0;
		$current = isset( $donations['thumbs'] ) ? ( $donations['thumbs'] * 10 ) : 1;
		$current = $current - $offset;
		$max = 20000;
		$counter_params = array(
			'min' => $min,
			'current' => $current,
			'max' => $max
		);

		/* for use in dynamic stylesheet */
		update_option( 'h3_min_counter_'.$race, $min );
		update_option( 'h3_current_counter_'.$race, $current );
		update_option( 'h3_max_counter_'.$race, $max );

		wp_enqueue_script( 'h3-mgmt-donation-counter' );
		wp_localize_script( 'h3-mgmt-donation-counter', 'counterParams', $counter_params );

		$output = '<div id="counter-wrap">' .
				'<div id="counter"';
		if ( 1 == $animation ) {
			$output .= ' class="animated"';
		}
		$output .= '>' .
					'<img id="counter-bg" alt="Donation Counter" src="' . H3_MGMT_RELPATH . 'img/counter-bg.png" />' .
					'<div id="counter-fill-wrap"';
					if ( 1 == $animation ) {
						$output .= ' class="animated"';
					} else {
						$output .= ' class="simple"';
					}
					$output .= '>' .
						'<img id="counter-fill" alt="Donation Counter" src="' . H3_MGMT_RELPATH . 'img/counter-fill.png" />' .
					'</div>' .
					'<div id="counter-value-wrap">' .
						'<span id="counter-value">' . $min . '</span>' .
						'<br /><span class="counter-thumbs">' . _x( 'Euros', 'Donation Counter', 'h3-mgmt' ) . '</span>' .
					'</div>' .
				'</div>' .
			'</div>';

		return $output;
	}

	/**
	 * Returns formatted HTML output
	 * of stages to be displayed in the front end
	 * via a shortcode
	 * (isotope ready)
	 *
	 * @since 1.0
	 * @access public
	 */
	public function display_stages( $atts ) {
		extract( shortcode_atts( array(
			'race' => 1,
			'class' => ''
		), $atts ) );

		$routes = $this->get_routes( array( 'race' => $race ) );
		$stages_html = array();

		foreach( $routes as $route ) {
			$stages = $this->get_stages( array( 'parent' => $route['id'], 'orderby' => 'number' ) );
			foreach( $stages as $stage ) {
				$stages_html[] = '<div class="stage-island route-' . $route['id'] . ' country-' . str_replace( ' ', '-', $stage['country'] ) . '" style="background-color:#' . $route['color_code'] . '">' .
						'<span class="route" style="display:none;visibility:hidden;">' .
							$route['name'] . '</span>' .
						'<img class="no-bsl-adjust route-logo" alt="Route ' . $route['name'] . '" ' .
							'src="' . get_option( 'siteurl' ) . $route['logo_url'] . '" />' .
						'<span class="stage-number stage">' . $stage['number'] . '</span>' .
						'<div class="stage-destination-wrap"><h5 class="stage-destination">' . $stage['destination'] . '</h5></div>' .
						'<span class="stage-country country">' . $stage['country'] . '</span>' .
					'</div>';
			}
		}

		$output = '<div id="stages-container">' . implode( '', $stages_html ) . '</div>';

		return $output;
	}

	/**
	 * Returns formatted HTML output
	 * for isotope filtering links to be displayed
	 * via a shortcode
	 *
	 * @since 1.0
	 * @access public
	 */
	public function isotope_links( $atts ) {
		extract( shortcode_atts( array(
			'race' => 1,
			'class' => ''
		), $atts ) );

		$output = '<div class="isotope-wrap"><h2 class="isotope-heading">' . __( 'Filter Routes', 'h3-mgmt' ) . '</h2>' .
			'<ul class="isotope-link-list" id="stages-filters"><li><a href="#" data-filter="*">' . __( 'All', 'h3-mgmt' ) . '</a></li>';

		$routes = $this->get_route_ids( $race );

		foreach( $routes as $rid => $rname ) {
			$output .= '<li><a href="#" data-filter=".route-' . $rid . '">' . $rname . '</a></li>';
		}

		$output .= '</ul><h2 class="isotope-heading">' . __( 'Filter Countries', 'h3-mgmt' ) . '</h2>' .
			'<ul class="isotope-link-list" id="country-filters"><li><a href="#" data-filter="*">' . __( 'All', 'h3-mgmt' ) . '</a></li>';

		$countries = $this->get_countries( $race );

		foreach( $countries as $country ) {
			$output .= '<li><a href="#" data-filter=".country-' . str_replace( ' ', '-', $country ) . '">' . $country . '</a></li>';
		}

		$output .= '</ul><h2 class="isotope-heading">' . __( 'Sort by', 'h3-mgmt' ) . '</h2>' .
			'<ul class="isotope-link-list" id="sort-by">' .
				'<li><a href="#route">' . __( 'Route', 'h3-mgmt' ) . '</a></li>' .
				'<li><a href="#stage">' . __( 'Stage', 'h3-mgmt' ) . '</a></li>' .
				'<li><a href="#country">' . __( 'Country', 'h3-mgmt' ) . '</a></li>' .
			'</ul></div>';

		return $output;
	}

	/**
	 * Stage Selector
	 *
	 * @since 1.0
	 * @access public
	 */
	public function stage_selector( $route_id ) {

		$stages = $this->get_stages( array( 'parent' => $route_id, 'parent_type' => 'route', 'omit_start' => true, 'orderby' => 'number', 'order' => 'ASC' ) );

		$output = '<form action="" method="GET">' .
				'<label>' . __( 'Stage', 'h3-mgmt' ) . '</label><br />' .
				'<select name="stage" id="stage">';

		foreach( $stages as $stage ) {
			$output .= '<option value="' . $stage['number'] . '">' . $stage['number'] . ' (' . $stage['destination'] . ')</option>';
		}


		$output .= '</select><br /><input type="submit" value="' . _x( 'Select Stage', 'Ranking Submission', 'h3-mgmt' ) . '"></form>';

		return $output;
	}

	/***** POINTS & RANKING (FRONTEND) *****/

	/**
	 * Ranking Submission Shortcode Handler
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ranking_submission_control() {
		global $current_user;

		$output = '';
		$users = $this->get_route_users( 4 );

		if( in_array( $current_user->ID, $users ) ) {
			$route_id = $this->get_route_by_user( $current_user->ID );
			if( isset( $_POST['submitted'] ) ) {
				$output .= $this->save_ranking();
			} elseif( isset( $_GET['stage'] ) ) {
				$output .= $this->ranking_submission_form( $route_id, $_GET['stage'] );
			} else {
				$output .= $this->stage_selector( $route_id );
			}
		} else {
			$output .= '<p>' . __( 'Rankings can only be submitted from a specific route user account!', 'h3-mgmt' ) . '</p>';
		}

		return $output;
	}

	/**
	 * Ranking Submission Form
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ranking_submission_form( $route_id, $stage_number, $message = '' ) {
		global $h3_mgmt_teams;

		list( $team_count, $complete_count, $incomplete_count, $teams ) = $h3_mgmt_teams->get_teams_meta( array(
			'orderby' => 'team_name',
			'exclude_incomplete' => true,
			'parent' => $route_id,
			'parent_type' => 'route'
		));

		$stage_rank_string = 'rank_stage_' . $stage_number;
		$extra_string = 'extra_stage_' . $stage_number;

		$output = '';

		if( ! empty( $message ) ) {
			$output .= '<p class="message" style="max-width: 500px; margin-left: auto; margin-right: auto;">' . $message . '</p>';
		}

		$output .= '<form class="ranking-submissions" action="" method="post">' .
				'<input type="hidden" name="submitted" value="y"/>' .
				'<input type="hidden" name="route" id="route" value="' . $route_id . '" />' .
				'<input type="hidden" name="stage" id="stage" value="' . $stage_number . '" />';

		$options = array(
			array(
				'value' => 0,
				'label' => '---------------------&nbsp;'
			),
			array(
				'value' => 1,
				'label' => __( '1st', 'h3-mgmt' )
			),
			array(
				'value' => 2,
				'label' => __( '2nd', 'h3-mgmt' )
			),
			array(
				'value' => 3,
				'label' => __( '3rd', 'h3-mgmt' )
			),
			array(
				'value' => 4,
				'label' => __( '4th', 'h3-mgmt' )
			),
			array(
				'value' => 5,
				'label' => __( '5th', 'h3-mgmt' )
			),
			array(
				'value' => 6,
				'label' => __( '6th', 'h3-mgmt' )
			),
			array(
				'value' => 7,
				'label' => __( '7th', 'h3-mgmt' )
			),
			array(
				'value' => 8,
				'label' => __( '8th', 'h3-mgmt' )
			),
			array(
				'value' => 9,
				'label' => __( '9th', 'h3-mgmt' )
			),
			array(
				'value' => 10,
				'label' => __( '10th', 'h3-mgmt' )
			),
			array(
				'value' => 11,
				'label' => __( '11th', 'h3-mgmt' )
			),
			array(
				'value' => 12,
				'label' => __( '12th', 'h3-mgmt' )
			),
			array(
				'value' => 99,
				'label' => __( 'disqualified', 'h3-mgmt' )
			),
		);

		foreach( $teams as $team ) {
			$output .= '<div style="max-width: 500px; margin-left: auto; margin-right: auto;">' .
					'<h3> Team: ' . $team['team_name'] . '</h3>' .
					'<label>' . __( 'Rank', 'h3-mgmt' ) . '</label>&nbsp;&nbsp;' .
					'<select style="width: 100%;" id="rank-' . $team['id'] . '" name="rank-' . $team['id'] . '">';

			foreach( $options as $option ) {
				$output .= '<option value="' . $option['value'] . '"';
				if( $option['value'] == $team[$stage_rank_string] ) {
					$output .= ' selected="selected"';
				}
				$output .= '>' . $option['label'] . '</option>';
			}

			$output .= '</select><br />' .
					'<input type="checkbox" name="extra-' . $team['id'] . '" id="extra-' . $team['id'] . '"';
			if( 1 == $team[$extra_string] ) {
				$output .= ' checked="checked"';
			}
			$output .= ' />' .
					'<label style="float: left;">' . __( 'Extra Point ?', 'h3-mgmt' ) . '</label>' .
					'<hr> </div>';
		}

		$output .= '<div class="form-row" style="max-width: 500px; margin-left: auto; margin-right: auto;">'.
					'<input type="submit" value="' . _x( 'Save Ranking', 'Ranking Submission', 'h3-mgmt' ) . '"></form>'.
					'</div>';

		return $output;
	}

	/**
	 * Saves ranking data
	 *
	 * @since 1.0
	 * @access public
	 */
	public function save_ranking() {
		global $wpdb, $h3_mgmt_teams;

		$message = _x( 'Ranking successfully saved!', 'Ranking Submission', 'h3-mgmt' );

		list( $team_count, $complete_count, $incomplete_count, $teams ) = $h3_mgmt_teams->get_teams_meta(
			array(
				'orderby' => 'team_name',
				'exclude_incomplete' => true,
				'parent' => $_POST['route']
			)
		);

		$stage_rank_string = 'rank_stage_' . $_POST['stage'];
		$extra_string = 'extra_stage_' . $_POST['stage'];

		foreach( $teams as $team ) {
			$rank_value_string = 'rank-' . $team['id'];
			$extra_value_string = 'extra-' . $team['id'];

			if( isset( $_POST[$extra_value_string] ) ) {
				$extra_value = 1;
			} else {
				$extra_value = 0;
			}

			$points = 0;

			for( $i = 1; $i <= 6; $i++ ) {
				$srs = 'rank_stage_' . $i;
				$ses = 'extra_stage_' . $i;
				if( $_POST['stage'] != $i ) {
					$temp_pts = $this->points_conversion( $team[$srs] );
					if( 1 == $team[$ses] ) {
						$points = $points + 1;
					}
				} else {
					$temp_pts = $this->points_conversion( $_POST[$rank_value_string] );
					$points = $points + $extra_value;
				}
				$points = $points + $temp_pts;
			}

			$wpdb->update(
				$wpdb->prefix . 'h3_mgmt_teams',
				array(
					$stage_rank_string => $_POST[$rank_value_string],
					$extra_string => $extra_value,
					'total_points' => $points
				),
				array( 'id' => $team['id'] ),
				array( '%d', '%d', '%d' ),
				array( '%d' )
			);

		}

		$output = $this->ranking_submission_form( $_POST['route'], $_POST['stage'], $message );

		return $output;
	}

	/**
	 * Ranking Table Output
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ranking_table( $atts='' ) {
		global $wpdb, $h3_mgmt_teams, $h3_mgmt_utilities;

		extract( shortcode_atts( array(
			'top' => 0,
			'route' => 'all',
			'race' => 1,
			'show_title' => 0,
			'show_stages' => 1,
			'show_nav' => 1
		), $atts ) );
		
		if ( isset( $_GET['ranking_route'] ) && $race === $this->get_route_parent( $_GET['ranking_route'] ) ) {
			$parent = $_GET['ranking_route'];
			$parent_type = 'route';
			$routes = array( $_GET['ranking_route'] => $_GET['ranking_route'] );
			
			// echo $race .' ';			//TEST ok
			 // echo $parent .' ';			//TEST ok
			 // echo $parent_type .' ';			//TEST ok
			// print_r($routes);			//TEST ok
			
		} else {
			$parent = $race;
			$parent_type = 'race';
			$routes = $this->get_routes( array( 'race' => $race ) );
		}
		
		if( $parent_type === 'race' ) {
			$route_name = _x( 'All Routes', 'Ranking', 'h3-mgmt' );
		} else {
			$route_name = $this->get_route_name( $parent );
		}

		$output = '';

		if ( $show_title == 1 ) {
			$output .= '<h3>' . str_replace( '%route_name%', $route_name, _x( 'Ranking Table: %route_name%', 'Ranking', 'h3-mgmt' ) ) . '</h3>';
		}

		list( $team_count, $complete_count, $incomplete_count, $teams ) = $h3_mgmt_teams->get_teams_meta( array( 
			'orderby' => 'total_points',
			'order' => 'DESC',
			'exclude_incomplete' => true,
			'extra_fields' => array(),
			'parent' => $parent,			//13
			'parent_type' => $parent_type   //route
		));
		
		// echo $complete_count;
		// print_r($teams);			//TEST
		
		if( $top === 0 ) {
			$max = count($teams);
		} else {
			$max = $top;
		}

		$table_head = '<tr class="trow-alt-1">' .
				'<th class="tal">' . _x( 'Team', 'Ranking', 'h3-mgmt' ) . '</th>' .
				'<th>' . _x( 'Total Rank', 'Ranking', 'h3-mgmt' ) . '</th>' .
				'<th>' . _x( 'Route Rank', 'Ranking', 'h3-mgmt' ) . '</th>' .
				'<th>' . _x( 'Total Points', 'Ranking', 'h3-mgmt' ) . '</th>';
		if( $show_stages == 1 ) {
			$table_head .= '<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 1</th>' .
				'<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 2</th>' .
				'<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 3</th>' .
				'<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 4</th>' .
				'<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 5</th>' .
				'<th>' . _x( 'Stage', 'Ranking', 'h3-mgmt' ) . ' 6</th>';
		}
		$table_head .= '</tr>';

		if( $show_nav == 1 ) {
			$output .= '<div class="isotope-wrap">' .
					'<ul class="isotope-link-list">' .
						'<li><a class="ranking_link" style="color:#333333;border-bottom: 1px dotted #333333;" href="' . get_site_url() .
									_x( '/follow-us/ranking/', 'utility translation', 'h3-mgmt' ) .
							'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

						$race_routes = $this->get_routes( array( 'race' => $race ) );

						foreach ( $race_routes as $race_route ) {
							$output .= '<li><a class="ranking_link" style="color:#' . $this->get_route_color( $race_route['id'] ) . ';border-bottom: 1px dotted #' . $this->get_route_color( $race_route['id'] ) . ';" href="' . get_site_url() .
									_x( '/follow-us/ranking/', 'utility translation', 'h3-mgmt' ) .
									'?ranking_race=' . $race . '&ranking_route=' . $race_route['id'] .
								'">' . $race_route['name'] . '</a></li>';
						}

			$output .= '</ul>' .
				'</div>';
		}

		$output .= '<div style="overflow-y:auto;">'.
			'<table class="ranking padded-table" cellspacing="0">'.
			'<thead>'. $table_head . '</thead>'.
			'<tfoot>'. $table_head . '</tfoot>'.
			'<tbody>'.
			'</div>';


		$previous = array(
			'total' => 999
		);
		$current_rank = array(
			'total' => 0
		);
		$skip_rank = array(
			'total' => 1
		);
		foreach ( $routes as $tmp_route_id => $tmp_route_name ) {
			$previous[$tmp_route_id] = 999;
			$current_rank[$tmp_route_id] = 0;
			$skip_rank[$tmp_route_id] = 1;
		}

		$i = 0;
		$flipper = false;
		foreach( $teams as $team ) {
			$i++;

			$extra = array(
				1 => '',
				2 => '',
				3 => '',
				4 => '',
				5 => '',
				6 => ''
			);

			if( $team['extra_stage_1'] == 1 ) {
				$extra[1] = ' (+1)';
			}
			if( $team['extra_stage_2'] == 1 ) {
				$extra[2] = ' (+1)';
			}
			if( $team['extra_stage_3'] == 1 ) {
				$extra[3] = ' (+1)';
			}
			if( $team['extra_stage_4'] == 1 ) {
				$extra[4] = ' (+1)';
			}
			if( $team['extra_stage_5'] == 1 ) {
				$extra[5] = ' (+1)';
			}
			if( $team['extra_stage_6'] == 1 ) {
				$extra[6] = ' (+1)';
			}

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

			$flip_val = $flipper ? '1' : '2';
			$output .= '<tr class="trow-alt-' . $flip_val . '">' .
				'<th><span style="text-shadow: 0 0 0.2em #696969, 0 0 0.2em #696969; font-weight:700;border-bottom: 2px solid #' . $this->get_route_color( $team['route_id'] ) . ';">' .
						'<a class="ranking_link" style="color:#' . $this->get_route_color( $team['route_id'] ) . ';" class="inkognito-link" title="' . __( 'View TeamProfile', 'h3-mgmt' ) . '" href="http://' . __( 'tramprennen.org/follow-us/teams/?id=', 'h3-mgmt' ) . $team['id'] . '">' .
							$team['team_name'] .
						'</a>' .
					'</span>' .
					'<br /><span style="font-size:.928571429em;">(' . $h3_mgmt_teams->mate_name_string( $team['id'], ' &amp; ', false ) . ')</span>' .
				'</th>' .
				'<th class="tac">' . $current_rank['total'] . '</th>' .
				'<th class="tac">' . $current_rank[$team['route_id']] . '</th>' .
				'<th class="tac">' . $team['total_points'] . '</th>';
			if( $show_stages == 1 ) {
				$output .= '<th class="tac">' . $this->points_conversion( $team['rank_stage_1'], true ) . $extra[1] .'</th>' .
					'<th class="tac">' . $this->points_conversion( $team['rank_stage_2'], true ) . $extra[2] .'</th>' .
					'<th class="tac">' . $this->points_conversion( $team['rank_stage_3'], true ) . $extra[3] .'</th>' .
					'<th class="tac">' . $this->points_conversion( $team['rank_stage_4'], true ) . $extra[4] .'</th>' .
					'<th class="tac">' . $this->points_conversion( $team['rank_stage_5'], true ) . $extra[5] .'</th>' .
					'<th class="tac">' . $this->points_conversion( $team['rank_stage_6'], true ) . $extra[6] .'</th>';
			}
			$output .= '</tr>';

			$flipper = $flipper ? false : true;

			$previous['total'] = $team['total_points'];
			$previous[$team['route_id']] = $team['total_points'];

			if( $max != 0 && $i >= $max ) {
				break;
			}
		}

		$output .= '</tbody></table>';

		return $output;
	}

	/***** UTILITY METHODS *****/

	/**
	 * Returns points according to rank
	 *
	 * @since 1.0
	 * @access public
	 */
	public function points_conversion( $rank, $string_format = false ) {
		$points = array(
			'1' => 20,
			'2' => 17,
			'3' => 14,
			'4' => 12,
			'5' => 10,
			'6' => 8,
			'7' => 6,
			'8' => 5,
			'9' => 4,
			'10' => 3,
			'11' => 2,
			'12' => 1,
			'0' => 0,
			'99' => 0
		);

		if( $string_format === true ) {
			$points['99'] = 'disq.';
		}

		return $points[$rank];
	}

	/***** CONSTRUCTORS *****/

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function H3_MGMT_Races() {
		$this->__construct();
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_shortcode( 'h3-stages', array( &$this, 'display_stages' ) );
		add_shortcode( 'h3-stages-isotope', array( &$this, 'isotope_links' ) );
		add_shortcode( 'h3-event-statistics', array( &$this, 'race_stats' ) );
		add_shortcode( 'h3-donation-counter', array( &$this, 'donation_counter' ) );
		add_shortcode( 'h3-submit-rankings', array( &$this, 'ranking_submission_control' ) );
		add_shortcode( 'h3-ranking-table', array( &$this, 'ranking_table' ) );
	}
}

endif; // class exists

?>