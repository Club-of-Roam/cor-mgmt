<?php

/**
 * H3_MGMT_Sponsors class.
 *
 * This class contains properties and methods for team sponsors and owners.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Sponsors' ) ) :

class H3_MGMT_Sponsors {

	/**
	 * Class properties
	 */
	private $form_submittable = true;

	/*************** UTILITY METHODS ***************/

	/**
	 * Returns data on a single sponsor
	 *
	 * @param int $id
	 *
	 * @return array $sponsor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_sponsor( $id ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

		$sponsors_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_sponsors " .
			"WHERE id = " . $id . " LIMIT 1",
			ARRAY_A
		);

		$sponsor = ! empty( $sponsors_query[0] ) ? $sponsors_query[0] : array();

		return $sponsor;
	}

	/**
	 * Returns data on all sponsors
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_sponsors_meta( $args = array() ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

		$default_args = array(
			'orderby' => 'id',
			'order' => 'ASC',
			'type' => 'all',
			'method' => 'all',
			'exclude_unpaid' => false,
			'extra_fields' => array(),
			'parent' => 'all',
			'parent_type' => 'race'
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		if( $parent_type === 'route' && is_numeric( $parent ) ) {
			$where = "WHERE route_id = " . $parent . " ";
		} elseif( $parent_type === 'race' && is_numeric( $parent ) ) {
			$where = "WHERE race_id = " . $parent . " ";
		} else {
			$where = '';
		}

		$query_orderby = 'id'; // legacy
		$sponsors_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_sponsors " .
			$where .
			"ORDER BY " . $query_orderby . " " . $order,
			ARRAY_A
		);

		$sponsors = array();
		$all_count = 0;
		$race_count = 0;
		$sponsor_count = 0;
		$owner_count = 0;
		$patron_count = 0;
		$structure_count = 0;
		$debit_count = 0;
		$paypal_count = 0;
		$paypal_unpaid_count = 0;
		$donations_all = 0;
		$donations_race = 0;
		$donations_sponsor = 0;
		$donations_owner = 0;
		$donations_patron = 0;
		$donations_structure = 0;
		$donations_debit = 0;
		$donations_paypal = 0;
		$donations_thumbs = 0;

		foreach( $sponsors_query as $key => $sponsor ) {
			if( $sponsor['method'] == 'debit' ) {
				$debit_count++;
				$all_count++;
				$donations_all = $donations_all + $sponsor['donation'];
				if( $sponsor['type'] == 'sponsor' ) {
					$sponsor_count++;
					$donations_sponsor = $donations_sponsor + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'owner' ) {
					$owner_count++;
					$donations_owner = $donations_owner + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'patron' ) {
					$patron_count++;
					$donations_patron = $donations_patron + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'structure' ) {
					$structure_count++;
					$donations_structure = $donations_structure + $sponsor['donation'];
				}
				if( $sponsor['type'] != 'structure' ) {
					$race_count++;
					$donations_race = $donations_race + $sponsor['donation'];
					$donations_debit = $donations_debit + $sponsor['donation'];
				}
			} elseif( $sponsor['method'] == 'paypal' && $sponsor['paid'] == 1 ) {
				$paypal_count++;
				$all_count++;
				$donations_all = $donations_all + $sponsor['donation'];
				if( $sponsor['type'] == 'sponsor' ) {
					$sponsor_count++;
					$donations_sponsor = $donations_sponsor + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'owner' ) {
					$owner_count++;
					$donations_owner = $donations_owner + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'patron' ) {
					$patron_count++;
					$donations_patron = $donations_patron + $sponsor['donation'];
				} elseif( $sponsor['type'] == 'structure' ) {
					$structure_count++;
					$donations_structure = $donations_structure + $sponsor['donation'];
				}
				if( $sponsor['type'] != 'structure' ) {
					$race_count++;
					$donations_race = $donations_race + $sponsor['donation'];
					$donations_paypal = $donations_paypal + $sponsor['donation'];
				}
			} elseif( $sponsor['method'] == 'paypal' && $sponsor['paid'] == 0 ) {
				$paypal_unpaid_count++;
			}
			if( $method === 'all' || $sponsor['method'] == $method ) {
				if( $exclude_unpaid === true && $sponsor['paid'] == 1 ) {
					$sponsors[$key] = $sponsor;
					$sponsors[$key]['team_name'] = $h3_mgmt_teams->get_team_name( $sponsor['team_id'] );
				} elseif( $exclude_unpaid != true ) {
					$sponsors[$key] = $sponsor;
					$sponsors[$key]['team_name'] = $h3_mgmt_teams->get_team_name( $sponsor['team_id'] );
				}
				if( ! empty( $extra_fields ) ) {
					foreach( $extra_fields as $field_id ) {
						if ( 'bank_info' === $field_id ) {
							$sponsors[$key][$field_id] = 'KTN: ' . $sponsor['account_id'] . '<br />' .
								'BLZ: ' . $sponsor['bank_id'] . '<br />' .
								$sponsor['bank_name'];
						} elseif ( 'race' === $field_id ) {
							$sponsors[$key][$field_id] = $h3_mgmt_races->get_name( $h3_mgmt_teams->get_team_race( $sponsor['team_id'] ), 'race' );
						}
					}
				}
			}
		}

		$sponsors = $h3_mgmt_utilities->sort_by_key( $sponsors, $orderby, $order );
		$donations_thumbs = $donations_race / 10;
		$counts = array(
			'all' => $all_count,
			'sponsor' => $sponsor_count,
			'owner' => $owner_count,
			'patron' => $patron_count,
			'structure' => $structure_count,
			'debit' => $debit_count,
			'paypal' => $paypal_count,
			'paypal-unpaid' => $paypal_unpaid_count
		);
		$donations = array(
			'all' => $donations_all,
			'race' => $donations_race,
			'thumbs' => $donations_thumbs,
			'sponsor' => $donations_sponsor,
			'owner' => $donations_owner,
			'patron' => $donations_patron,
			'structure' => $donations_structure,
			'debit' => $donations_debit,
			'paypal' => $donations_paypal
		);

		return array( $sponsors, $counts, $donations );
	}

	/**
	 * Returns a formatted list of sponsors
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function list_sponsors( $args = array() ) {
		global $wpdb, $h3_mgmt_teams, $h3_mgmt_utilities;

		$default_args = array(
			'type' => 'all',
			'team_id' => 'all',
			'race' => 'all',
			'delimiter' => ', '
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$where = '';
		$srated = false;
		if ( is_numeric( $team_id ) ) {
			$where = "WHERE team_id = " . $team_id . " ";
			$started = true;
		}
		if ( in_array( $type, array( 'sponsor', 'owner' ) ) ) {
			if( $started ) {
				$where .= "AND ";
			} else {
				$where .= "WHERE ";
			}
			$where .= "type = '" . $type . "' ";
		}
		if ( is_numeric( $race ) ) {
			if( $started ) {
				$where .= "AND ";
			} else {
				$where .= "WHERE ";
			}
			$where .= "race_id = " . $race . " ";
		}

		$sponsors_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix . "h3_mgmt_sponsors " .
			$where .
			"ORDER BY display_name ASC",
			ARRAY_A
		);

		$scount = count( $sponsors_query );
		$sponsors = array(
			'count' => $scount,
			'anonymous' => 0,
			'names' => '',
			'names-tooltip' => '',
			'names_arr' => array(),
			'owner_pic' => '',
			'owner_link' => '',
			'raw_owner_link' => ''
		);

		$exclude_unpaid_paypal_count = 0;
		for( $i = 0; $i < $scount; $i++ ) {
			$sponsors_query[$i]['display_name'] = stripslashes( $sponsors_query[$i]['display_name'] );
			if( empty( $sponsors_query[$i]['display_name'] ) && ( $sponsors_query[$i]['method'] !== 'paypal' || $sponsors_query[$i]['paid'] == 1 ) ) {
				$sponsors['anonymous']++;
				$exclude_unpaid_paypal_count++;
			} elseif( $sponsors_query[$i]['method'] !== 'paypal' || $sponsors_query[$i]['paid'] == 1 ) {
				$exclude_unpaid_paypal_count++;
				$sponsors['names-tooltip'] .= $sponsors_query[$i]['display_name'];
				if( ! empty( $sponsors_query[$i]['message'] ) ) {
					$sponsors['names-tooltip'] .= ' <span class="tip" onmouseover="tooltip(\'' .
						preg_replace( "/\w[-._\w]*\w@\w[-._\w]*\w\.\w{2,3}/", "", preg_replace( "/\r|\n/", "<br />", ( str_replace( '"', '&quot;', str_replace( "'", '&apos;', $sponsors_query[$i]['message'] ) ) ) ) ) .
						'\');" onmouseout="exit();">' .
							'<img class="comments-bubble no-bsl-adjust" alt="Comments Bubble" src="' . H3_MGMT_RELPATH . 'img/comments-bubble.png" />' .
						'</span>';
				}
				if( $sponsors_query[$i]['type'] == 'owner' && ! empty( $sponsors_query[$i]['owner_link'] ) ) {
					$sponsors['names'] .= '<a class="incognito-link" title="' . _x( 'Visit the TeamOwner\'s website', 'Team Profile', 'h3-mgmt' ) . '" ' . 'href="' . $h3_mgmt_utilities->fix_urls( $sponsors_query[$i]['owner_link'] ) . '">' . $sponsors_query[$i]['display_name'] .  '</a>';
				} else{
					$sponsors['names'] .= $sponsors_query[$i]['display_name'];
				}

				$sponsors['names_arr'][] = $sponsors_query[$i]['display_name'];
				if( $i != ( $scount - 1 ) ) {
					$sponsors['names'] .= $delimiter;
					$sponsors['names-tooltip'] .= $delimiter;
				}
			}
		}

		$sponsors['count'] = $exclude_unpaid_paypal_count;

		if( $team_id !== 'all' && $type === 'owner' ) {
			if( empty( $sponsors_query[0]['display_name']  ) ) {
				$sponsors['owner_pic'] = H3_MGMT_RELPATH . 'img/owner-pic.png';
				$sponsors['owner_link'] = '<p class="owner-link"><a title="' . _x( 'Become this team&apos;s TeamOwner!', 'Team Profile', 'h3-mgmt' ) . '" ' .
					'href="' . _x( 'https://tramprennen.org/support-team/become-sponsor/', 'Team Profile', 'h3-mgmt' ) . '?type=owner&id=' . $team_id . '">' .
						_x( 'No Owner yet.', 'Team Profile', 'h3-mgmt' ) . '<br />' .
						_x( 'Become this team&apos;s TeamOwner!', 'Team Profile', 'h3-mgmt' ) .
					'</a></p>';
			} else {
				if( ! empty( $sponsors_query[0]['owner_pic'] ) ) {
					$sponsors['owner_pic'] = $sponsors_query[0]['owner_pic'];
				} else {
					$sponsors['owner_pic'] = H3_MGMT_RELPATH . 'img/owner-pic.png';
				}

				$sponsors['owner_link'] .= '<p class="owner-link">';
				if( ! empty( $sponsors_query[0]['owner_link'] ) ) {
					$sponsors['owner_link'] .= '<a title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '" ' .
					'href="' . $h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] ) . '">';
					$sponsors['raw_owner_link'] .= '<a class="cursive-link" title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '" ' .
					'href="' . $h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] ) . '">';
					$sponsors['owner_link_url'] = $h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] );
				}
				$sponsors['owner_link'] .= $sponsors_query[0]['display_name'];
				$sponsors['raw_owner_link'] .= $sponsors_query[0]['display_name'];
				if( ! empty( $sponsors_query[0]['owner_link'] ) ) {
					$sponsors['owner_link'] .= '</a>';
					$sponsors['raw_owner_link'] .= '</a>';
				}
				if( ! empty( $sponsors_query[0]['message'] ) ) {
					$sponsors['owner_link'] .= ' <span class="tip" onmouseover="tooltip(\'' .
						preg_replace( "/\r|\n/", "<br />", ( str_replace( '"', '&quot;', str_replace( "'", '&apos;', $sponsors_query[0]['message'] ) ) ) ) .
						'\');" onmouseout="exit();"><img class="comments-bubble no-bsl-adjust" alt="Comments Bubble" src="' . H3_MGMT_RELPATH . 'img/comments-bubble.png" /></span>';
				}
				$sponsors['owner_link'] .= '</p>';
			}
		}

		return $sponsors;
	}



	/*************** SPONSORING (FRONT END) ***************/

	/**
	 * Sponsoring form shortcode handler
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function sponsoring_form_handler( $atts = '' ) {
		global $current_user, $wpdb;

		extract( shortcode_atts( array(
			'race' => 1
		), $atts ) );

		if( isset( $_GET['paypal'] ) && $_GET['paypal'] == 'success' && isset( $_GET['sponsor_id'] ) && ! empty( $_GET['sponsor_id'] ) ) {
			$wpdb->update(
				$wpdb->prefix.'h3_mgmt_sponsors',
				array( 'paid' => 1, 'show' => 1 ),
				array( 'id'=> $_GET['sponsor_id'] ),
				array( '%d', '%d' ),
				array( '%d' )
			);
			if( isset( $_GET['team_id'] ) && ! empty( $_GET['team_id'] ) ) {
				//wp_redirect(  get_site_url() . '/follow-us/teams/?id='.$_GET['team_id'], 301 );
			}
		}

		wp_enqueue_script( 'h3-mgmt-donation-selector' );
		$donation_params = array(
			'debitConfirm' => _x( 'Hereby you confirm to have entered the following data correctly and that the sum will be deductable from your account within a month:', 'Sponsoring Form', 'h3-mgmt' ),
			'accountID' => _x( 'Account ID', 'Sponsoring Form', 'h3-mgmt' ),
			'bankID' => _x( 'Bank ID', 'Sponsoring Form', 'h3-mgmt' ),
			'donation' => _x( 'Donation', 'Sponsoring Form', 'h3-mgmt' ),
			'euros' => _x( 'Euros', 'Sponsoring Form', 'h3-mgmt' )
		);
		wp_localize_script( 'h3-mgmt-donation-selector', 'donationParams', $donation_params );

		if ( isset( $_POST['submitted'] ) ) {
			list( $valid, $errors ) = $this->validate_submit();
			if ( $valid === true ) {
				return $this->save_donation( $race );
			} else {
				return $this->sponsoring_section_output( array( 'step' => 3, 'messages' => $errors, 'race' => $race ) );
			}
		}

		if( isset( $_GET['type'] ) && ( $_GET['type'] == 'owner' || $_GET['type'] == 'sponsor' ) && isset( $_GET['method'] ) && ( $_GET['method'] == 'debit' || $_GET['method'] == 'paypal' ) ) {
			return $this->sponsoring_section_output( array( 'step' => 3, 'race' => $race ) );
		} elseif( isset( $_GET['type'] ) && ( $_GET['type'] == 'owner' || $_GET['type'] == 'sponsor' || $_GET['type'] == 'patron' || $_GET['type'] == 'structure' ) ) {
			return $this->sponsoring_section_output( array( 'step' => 2, 'race' => $race ) );
		} else {
			return $this->sponsoring_section_output( array( 'step' => 1, 'race' => $race ) );
		}
	}

	/**
	 * Sponsoring Section Output generator
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	private function sponsoring_section_output( $args = array() ) {

		$default_args = array(
			'step' => 1,
			'messages' => array(),
			'method' => 'debit',
			'type' => 'sponsor',
			'team_id' => 0,
			'donation' => 20,
			'sponsor_id' => 0,
			'race' => 1
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$step_strings = array();

		$step_strings[1] = _x( 'Pick your sponsoring / donation type.<br />Choose to be a TeamSponsor or TeamOwner.<br />With both you support the WASH-project of Viva con Agua. Teams can have several TeamSponsors but only one TeamOwner, which can place a photo or a logo and a link in the team profile.', 'Sponsoring Form', 'h3-mgmt' );

		$step_strings[2] =	_x( 'You can donate via PayPal or have your donation deducted via direct debit.<br />The latter is only possible with German bank accounts.<br />In both cases, the donation is submitted directly to the Viva con Agua donations account.', 'Sponsoring Form', 'h3-mgmt' );

		$step_strings[3] =	_x( 'Choose a team.<br />Select the amount of thumbs you want to give.<br />By giving a thumb you support VcA on its mission for global water access with the donation of 10 Euros. TeamOwnerships go for a minimum of 10 thumbs (Hey, they are special!). Submit your data and choose if and how your donation should be shown on the site.', 'Sponsoring Form', 'h3-mgmt' );

		$step_strings[4] =	_x( 'Thanks for your donation!<br />If you picked the debit method, you can relax now. If you chose to donate via PayPal, please execute the PayPal transaction.', 'Sponsoring Form', 'h3-mgmt' );

		$output = '<div class="grid-row"><div class="grid-block island col5"><div class="island-inside">' .
			'<h2>' . _x( 'Sponsoring Steps', 'Sponsoring Form', 'h3-mgmt' ) . '</h2>';

		for( $i = 1; $i <= 4; $i++ ) {
			$output .= '<div class="overview-category">' .
				'<img class="baseline-adjustable';
			if ( 4 === $i ) {
				$output .= ' no-margin-bottom';
			}
			$output .= '" src="' . H3_MGMT_RELPATH . 'img/jerrycan-' . $i;

			if( $i == $step ) {
				$output .= '-active';
			}

			$output .= '.png" alt="Jerrycan" />' .
				'<div class="description';

			if( $i != $step ) {
				$output .= '';
			}

			$output .=	'">' .
				'<p><strong>';

			if( $step < 4 && $i != $step && $i < $step ) {
				$output .=	'<a title="' . _x( 'Navigate to step', 'Progressive Form Steps', 'h3-mgmt' ) . '" ' .
					'href="' . get_option( 'siteurl' ) . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI'] );

				if( $i == 2 ) {
					$output .=	'?type=' . $_GET['type'];
				}

				$output .=	'">';
			}

			$output .= '<span ';
			if( $i != $step ) {
				$output .= 'onmouseover="tooltip(\'' . $step_strings[$i] . '\');" onmouseout="exit();" class="tip ';
			} else {
				$output .= 'class="';
			}
			if( $i != $step && $i < $step ) {
				$output .= 'to-do-confirm-done';
			} elseif( $i == $step ) {
				$output .= 'to-do-positive';
			} else {
				$output .= 'to-do-can';
			}
			$output .= '">';

			if( $i === 1 ) {
				$output .=	_x( 'Select Sponsoring (Donation) Type', 'Sponsoring Form', 'h3-mgmt' );
			} elseif( $i === 2 ) {
				$output .=	_x( 'Select Donation Method', 'Sponsoring Form', 'h3-mgmt' );
			} elseif( $i === 3 ) {
				$output .=	_x( 'Fill out the form', 'Sponsoring Form', 'h3-mgmt' );
			} elseif( $i === 4 ) {
				$output .=	_x( 'Afterwards', 'Sponsoring Form', 'h3-mgmt' );
			}

			if( $step < 4 && $i != $step && $i < $step ) {
				$output .= '</a>';
			}

			$output .= '</span>';

			$output .= '</strong>';

			if( $i == $step ) {
				$output .= '<br /><em>' . $step_strings[$i] . '</em>';
			}

			$output .=	'</p></div></div>';
		}

		$output .= '</div>' .
			'<div class="island-inside headspace">' .
				'<h2>' . _x( 'Encryption', 'Sponsoring Form', 'h3-mgmt' ) . '</h2>' .
				'<p>' . _x( 'Your data is transferred securely via a 256-bit SSL encrypted connection.', 'Sponsoring Form', 'h3-mgmt' ) . '</p>' .
				'<a target="_blank" href="http://www.rapidssl.com/learn-ssl/ssl-faq/index.html" title="Rapid SSL"><img class="align-center no-margin-bottom" alt="' . _x( 'SSL Seal', 'Sponsoring Form', 'h3-mgmt' ) . '"' .
				' src="' . H3_MGMT_RELPATH . 'img/ssl-seal.png" /></a>' .
			'</div></div>';

		$output .= '<div class="grid-block col7 last';
		if( $step != 1 && $step != 2 ) {
			$output .= ' island';
		}
		$output .= '"><div class="island-inside">';

		if( $step === 1 ) {

			$output .= $this->base_selector();

		} elseif( $step === 2 ) {

			$output .= $this->method_selector();

		} elseif( $step === 3 ) {

			$output .= $this->make_form( array(
				'type' => isset( $_GET['type'] ) ? $_GET['type'] : 'sponsor',
				'method' => isset( $_GET['method'] ) ? $_GET['method'] : 'debit',
				'messages' => $messages,
				'race' => $race
			));

		} elseif( $step === 4 ) {

			$output .= $this->success_section( array(
				'method' => $method,
				'type' => $type,
				'team_id' => $team_id,
				'donation' => $donation,
				'sponsor_id' => $sponsor_id
			));

		}

		$output .= '</div></div></div>';

		return $output;
	}

	/**
	 * Returns success section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function success_section( $args = array() ) {
		global $current_user, $h3_mgmt_teams;

		$default_args = array(
			'method' => 'debit',
			'type' => 'sponsor',
			'team_id' => 0,
			'donation' => 20,
			'sponsor_id' => 0,
			'race' => 1
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$output = '<h2>' . _x( 'Thanks!', 'Sponsoring Form', 'h3-mgmt' ) . '</h2>';

		$thumbs = intval( $donation ) / 10;
		$team_name = $h3_mgmt_teams->get_team_name( $team_id );

		$titles = array(
			'sponsor' => _x( 'TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
			'owner' => _x( 'TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
			'patron' => _x( 'RacePatron', 'Sponsoring Form', 'h3-mgmt' ),
			'structure' => _x( 'Structural Sponsor', 'Sponsoring Form', 'h3-mgmt' )
		);

		$paypal_sum = number_format( $donation, 2, '.', '');
		if ( in_array( $current_user->ID, array( 1, 4, 2259 ) ) ) {
			$paypal_sum = $paypal_sum / 100;
		}

		$vca_paypal_form = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">' .
				'<input type="hidden" name="cmd" value="_xclick">' .
				'<input type="hidden" name="business" value="7FG37SLD3BEMU">' .
				'<input type="hidden" name="lc" value="' .
					_x( 'US', 'Locale', 'h3-mgmt' ) .
				'">' .
				'<input type="hidden" name="item_name" value="' .
					_x( 'Donation to the current water project in India and Nepal via Tramprennen 2013', 'Sponsoring Form', 'h3-mgmt' ) .
				'">' .
				'<input type="hidden" name="amount" value="' .
					$paypal_sum .
				'">' .
				'<input type="hidden" name="currency_code" value="EUR">' .
				'<input type="hidden" name="button_subtype" value="services">' .
				'<input type="hidden" name="no_note" value="1">' .
				'<input type="hidden" name="no_shipping" value="1">' .
				'<input type="hidden" name="return" value="' .
					_x( 'https://tramprennen.org/support-team/become-sponsor/', 'utility translation', 'h3-mgmt' ) . '?team_id=' . $team_id . '&paypal=success&sponsor_id=' . $sponsor_id .
				'">' .
				'<input type="hidden" name="cbt" value="' .
					_x( 'Return to tramprennen.org', 'Sponsoring Form', 'h3-mgmt' ) .
				'">' .
				'<input type="hidden" name="bn" value="PP-BuyNowBF:btn_donate_LG.gif:NonHosted">' .
				'<input type="image" src="https://www.paypalobjects.com/' .
					_x( 'en_US/', 'PayPal Button, double locale', 'h3-mgmt' ) .
					'i/btn/btn_donate_LG.gif" border="0" name="submit" alt="Jetzt einfach, schnell und sicher online spenden – mit PayPal.">' .
				'<img alt="" border="0" src="https://www.paypalobjects.com/' .
					_x( 'en_US/', 'PayPal Button, simple locale', 'h3-mgmt' ) .
					'i/scr/pixel.gif" width="1" height="1">' .
			'</form>';

		$type_strings = array(
			'sponsor' => array (
				'title' => $titles['sponsor'],
				'debit-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['sponsor'], _x( 'You have successfully become %sponsoring_type% of "%team_name%" and chosen to donate %thumbs% thumbs (%donation% Euros)', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					'<br />' .
					_x( 'The donation will be deducted from your bank account within a month.', 'Sponsoring Form', 'h3-mgmt' ) .
					'<br />' .
					_x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ),
				'paypal-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['sponsor'], _x( 'In order to become %sponsoring_type% of "%team_name%" by donating %thumbs% thumbs (%donation% Euros), be so kind as to transfer the chosen amount via PayPal by clicking the below button.', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					'</p>' . $vca_paypal_form . '<br /><p>' .
					_x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ) . '</p>'
			),
			'owner' => array (
				'title' => $titles['owner'],
				'debit-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['owner'], _x( 'You have successfully become %sponsoring_type% of "%team_name%" and chosen to donate %thumbs% thumbs (%donation% Euros)', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ).
					'<br />' .
					_x( 'The donation will be deducted from your bank account within a month.', 'Sponsoring Form', 'h3-mgmt' ) .
					'<br />' .
					_x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ),
				'paypal-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['owner'], _x( 'In order to become %sponsoring_type% of "%team_name%" by donating %thumbs% thumbs (%donation% Euros), be so kind as to transfer the chosen amount via PayPal by clicking the below button.', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					'</p>' . $vca_paypal_form . '<br /><p>' .
					_x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ) . '</p>'
			)
		);

		$output .= '<p class="message">';
		if( $method == 'paypal') {
			$output .= sprintf( _x( 'You are about to become a %1$s. Please complete the PayPal transaction:', 'Sponsoring Form', 'h3-mgmt' ), $type_strings[$type]['title'] );
		} else {
			$output .= sprintf( _x( 'You have successfully become a %1$s!', 'Sponsoring Form', 'h3-mgmt' ), $type_strings[$type]['title'] );
		}
		$output .= '</p><p>';
			if( $method == 'debit' ) {
				$output .= $type_strings[$type]['debit-message'];
			} elseif( $method == 'paypal' ) {
				$output .= $type_strings[$type]['paypal-message'];
			}
		$output .= '</p>';

		return $output;
	}

	/**
	 * Returns the form field for the method selector
	 *
	 * @since 1.0
	 * @access private
	 */
	private function method_field() {

		$method_field = array(
			array (
				'label'	=> _x( 'Donation Method', 'Sponsoring Form', 'h3-mgmt' ),
				'tooltip'	=> _x( 'Donations can be submitted either via PayPal or via direct debit from a German bank account.', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'method',
				'type'	=> 'radio',
				'options' => array(
					array(
						'label' => _x( 'Debit (Germany only)', 'Sponsoring Form', 'h3-mgmt' ),
						'value' => 'debit'
					),
					array(
						'label' => _x( 'PayPal', 'Sponsoring Form', 'h3-mgmt' ),
						'value' => 'paypal'
					)
				)
			)
		);

		return $method_field;
	}

	/**
	 * Returns the form field for the team selection
	 *
	 * @since 1.0
	 * @access private
	 */
	private function team_field( $args = array() ) {
		global $h3_mgmt_teams;

		$default_args = array(
			'orderby' => 'team_name',
			'order' => 'ASC',
			'please_select' => false,
			'exclude_with_owner' => false,
			'exclude_incomplete' => true,
			'show_mates' => true,
			'select_text' => '',
			'race' => 1
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$options = $h3_mgmt_teams->select_options( array(
			'orderby' => $orderby,
			'order' => $order,
			'please_select' => $please_select,
			'exclude_with_owner' => $exclude_with_owner,
			'exclude_incomplete' => $exclude_incomplete,
			'show_mates' => $show_mates,
			'select_text' => $select_text,
			'race' => $race
		));

		if ( empty( $options ) || ( true === $please_select && 1 >= count( $options ) ) ) {
			$team_field = array(
				array (
					'label'	=> _x( 'Choose Team', 'Sponsoring Form', 'h3-mgmt' ),
					'id'	=> 'team_id',
					'type'	=> 'note',
					'note' => $exclude_with_owner ? _x( 'Either no teams have completed the registration yet or all that have have a TeamOwner already...', 'Sponsoring Form', 'h3-mgmt' ) : _x( 'So far no teams have completed the registration yet...', 'Sponsoring Form', 'h3-mgmt' )
				)
			);
			$this->form_submittable = false;
		} else {
			$team_field = array(
				array (
					'label'	=> _x( 'Choose Team', 'Sponsoring Form', 'h3-mgmt' ),
					'id'	=> 'team_id',
					'type'	=> 'select',
					'options' => $options
				)
			);
		}

		return $team_field;
	}

	/**
	 * Returns the form fields for the donor section
	 *
	 * @since 1.0
	 * @access private
	 */
	public function donor_fields() {

		$donor_fields = array(
			array (
				'label'	=> _x( 'First Name', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'first_name',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Last Name', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'last_name',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'E-Mail', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'email',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Display Name', 'Sponsoring Form', 'h3-mgmt' ),
				'tooltip'	=> _x( 'Choose how your Name should be displayed on the website (and in the Team Profile, if applicable). If you do not want your name to show at all, simply leave this field blank.', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'display_name',
				'type'	=> 'text'
			)
		);

		return $donor_fields;
	}

	/**
	 * Returns the form fields for the debit section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function debit_fields() {

		$debit_fields = array(
			array (
				'label'	=> _x( 'Account ID (KTN)', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'account_id',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Bank ID (BLZ)', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'bank_id',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Name of Bank', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'bank_name',
				'type'	=> 'text'
			),
			array (
				'label'	=> _x( 'Debit confirmation', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'debit_confirmation',
				'type'	=> 'checkbox',
				'desc' => _x( 'By checking this box you confirm that Viva con Agua e.V. may debit the above sum from your above bank account. Thank you!', 'Sponsoring Form','h3-mgmt' )
			)
		);

		return $debit_fields;
	}

	/**
	 * Returns the form fields for the debit section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function address_fields() {

		$address_fields = array(
			array (
				'label'	=> _x( 'Receipt', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'receipt',
				'type'	=> 'select',
				'options' => array(
					array(
						'label' => __( 'Yes', 'h3-mgmt' ),
						'value' => 1
					),
					array(
						'label' => __( 'No', 'h3-mgmt' ),
						'value' => 0
					)
				)
			),
			array (
				'label'	=> _x( 'Street Address', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'street',
				'type'	=> 'text',
				'row-class' => 'address-row'
			),
			array (
				'label'	=> _x( 'Zip Code', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'zip_code',
				'type'	=> 'text',
				'row-class' => 'address-row'
			),
			array (
				'label'	=> _x( 'City', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'city',
				'type'	=> 'text',
				'row-class' => 'address-row'
			),
			array (
				'label'	=> _x( 'Country', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'country',
				'type'	=> 'text',
				'row-class' => 'address-row'
			),
			array (
				'label'	=> _x( 'Additional Adress Field', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'address_additional',
				'type'	=> 'text',
				'row-class' => 'address-row'
			)
		);

		return $address_fields;
	}

	/**
	 * Returns the form fields for the owner-specific section
	 *
	 * @since 1.0
	 * @access private
	 */
	private function owner_fields() {

		$owner_fields = array(
			array (
				'label'	=> _x( 'Owner Pic', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'owner_pic',
				'type'	=> 'single-pic-upload',
				'tooltip' => _x( "This picture/logo will appear in the sponsored team profile. You may upload .jpg, .gif or .png files.", 'Sponsoring Form', 'h3-mgmt' )
			),
			array (
				'label'	=> _x( 'Link', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'owner_link',
				'type'	=> 'text',
				'tooltip' => _x( "If you submit a link, your picture/logo will link to said site.", 'Sponsoring Form', 'h3-mgmt' )
			)
		);

		return $owner_fields;
	}

	/**
	 * Returns the form fields for the debit section
	 *
	 * @since 1.0
	 * @access private
	 */
	public function message_field( $scope = 'team' ) {

		$message_field = array(
			array (
				'label'	=> _x( 'Leave your team a note, if you&apos;d like', 'Sponsoring Form', 'h3-mgmt' ),
				'id'	=> 'message',
				'type'	=> 'textarea'
			)
		);
		if( $scope === 'global' ) {
			$message_field[0]['label'] = _x( 'Leave the HitchHikingHub-Crew a note, if you$apos;d like', 'Sponsoring Form', 'h3-mgmt' );
		}

		return $message_field;
	}

	/**
	 * Returns fields with values from $_POST
	 *
	 * @since 1.0
	 * @access private
	 */
	private function add_values( $fields ) {

		$fcount = count($fields);
		if ( isset( $_POST['submitted'] ) ) {
			for ( $i = 0; $i < $fcount; $i++ ) {
				$fields[$i]['value'] = isset( $_POST[$fields[$i]['id']] ) ? $_POST[$fields[$i]['id']] : '';
			}
		} elseif( isset( $_GET['id'] ) && $fields[0]['id'] === 'team_id' ) {
			$fields[0]['value'] = $_GET['id'];
		}

		return $fields;
	}

	/**
	 * Returns the donation type and method selector
	 *
	 * @since 1.0
	 * @access private
	 */
	private function base_selector() {

		$output = '';

		$strings = array(
			array(
				'id' => 'sponsor',
				'title' => _x( 'TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
				'call' => _x( 'Become a TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
				'image' => 'sponsoring-type-sponsor.png',
				'description' => _x( 'With one thumb&apos;s up you can morally support your team and financially support the current water project of Viva con Agua. One thumb translates to a donation of 10 Euros. Your name will be listed as a TeamSponsor in the sponsoring list and in the team&apos;s profile (unless you opt to remain anonymous).', 'Sponsoring Form', 'h3-mgmt' )
			),
			array(
				'id' => 'owner',
				'title' => _x( 'TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
				'call' => _x( 'Become a TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
				'image' => 'sponsoring-type-owner.png',
				'description' => _x( 'For those who are tired of Chealsea or Hoffenheim, but still wanna play a little Abramovitch: As a TeamOwner you give a minimum of 10 thumbs (a donation of 100 Euros) for clean drinking water. As a Thank You, you may place a photo, picture or logo in the team profile which can optionally be linked to a URL of your choice.', 'Sponsoring Form', 'h3-mgmt' )
			)
		);

		for( $i = 0; $i <= 1; $i++ ) {
			$output .= '<div class="overview-category toggle-wrapper">' .
				'<a href="' . get_option( 'siteurl' ) . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI'] ) . '?type=' . $strings[$i]['id'] . '">' .
				'<img src="' . H3_MGMT_RELPATH . 'img/' . $strings[$i]['image'] . '" ' .
					'alt="' . $strings[$i]['title'] . '"' .
					'title="' . '" class="no-bsl-adjust no-margin-bottom" />' .
				'<div class="description">' .
					'<h2 class="first">' .  $strings[$i]['title'] . '</h2>' .
						'<p><em>' . $strings[$i]['description'] . '</em></p>' .
						'<p class="no-margin-bottom"><a title="' .
								$strings[$i]['call'] .
							'" href="' .
								get_option( 'siteurl' ) . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI'] ) . '?type=' . $strings[$i]['id'] .
							'">' .
								$strings[$i]['call'] .
						'</a></p>' .
				'</div>' .
				'</a>' .
			'</div>';
			if( $i !== 1 ) {
				$output .= '<div class="inline-breaker inline-breaker-top"></div>' .
					'<div class="inline-breaker inline-breaker-bottom"></div>';
			}
		}

		return $output;
	}

	/**
	 * Returns the donation method selector
	 *
	 * @since 1.0
	 * @access private
	 */
	private function method_selector() {

		$output = '';

		$strings = array(
			array(
				'id' => 'debit',
				'title' => _x( 'Debit (Germany only)', 'Sponsoring Form', 'h3-mgmt' ),
				'call' => _x( 'Donate via direct debit', 'Sponsoring Form', 'h3-mgmt' ),
				'image' => 'sponsoring-method-debit.png',
				'description' => _x( 'If you have a German bank account, you can submit your donation via direct debit. The donation will be charged by Viva con Agua e.V. directly and that within a month from now.', 'Sponsoring Form', 'h3-mgmt' )
			),
			array(
				'id' => 'paypal',
				'title' => _x( 'PayPal', 'Sponsoring Form', 'h3-mgmt' ),
				'call' => _x( 'Donate via PayPal', 'Sponsoring Form', 'h3-mgmt' ),
				'image' => 'sponsoring-method-paypal.png',
				'description' => _x( 'If you are not from Germany or would rather use PayPal, choose this method.', 'Sponsoring Form', 'h3-mgmt' )
			)
		);

		for( $i = 0; $i <= 1; $i++ ) {
			$output .= '<div class="overview-category toggle-wrapper">' .
				'<a href="' . get_option( 'siteurl' ) . $_SERVER['REQUEST_URI'] . '&method=' . $strings[$i]['id'] . '">' .
				'<img src="' . H3_MGMT_RELPATH . 'img/' . $strings[$i]['image'] . '" ' .
					'alt="' . $strings[$i]['title'] . '"' .
					'title="' . '" class="no-bsl-adjust no-margin-bottom" />' .
				'<div class="description">' .
					'<h2 class="first">' .  $strings[$i]['title'] . '</h2>' .
						'<p><em>' . $strings[$i]['description'] . '</em></p>' .
						'<p class="no-margin-bottom"><a title="' .
								$strings[$i]['call'] .
							'" href="' .
								get_option( 'siteurl' ) . $_SERVER['REQUEST_URI'] . '&method=' . $strings[$i]['id'] .
							'">' .
								$strings[$i]['call'] .
						'</a></p>' .
				'</div>' .
				'</a>' .
			'</div>';
			if( $i !== 1 ) {
				$output .= '<div class="inline-breaker inline-breaker-top"></div>' .
					'<div class="inline-breaker inline-breaker-bottom"></div>';
			}
		}

		return $output;
	}

	/**
	 * Returns the donation sum selector
	 *
	 * @since 1.0
	 * @access private
	 */
	private function donation_selector( $type = 'normal', $min = .5 ) {

		if( $type === 'text' ) {
			$fields = array(
				array(
					'type' => 'text',
					'id' => 'donation',
					'label' => _x( 'Donation (in Euro)', 'Sponsoring Form', 'h3-mgmt' ),
					'value' => $_POST['donation']
				)
			);
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
		} else {
			if( isset( $_POST['donation'] ) ) {
				$start_val = $_POST['thumbs'];
			} elseif( $min <= 1 ) {
				$start_val = 1;
			} else {
				$start_val = $min;
			}
			$don_val = intval( $start_val * 10 );

			$output = '<input type="hidden" name="thumbs" id="thumbs" value="' . $start_val . '"/>' .
				'<input type="hidden" name="donation" id="donation" value="' . $don_val . '"/>'.
				'<input type="hidden" name="min" id="min" value="' . $min . '"/>';

			$output .= '<div class="form-row donation-selector-row">' .
					'<div class="arrow-wrap"><span class="less-arrow horiz-arrow arrow">&lt;&lt;</span></div>' .
					'<div class="donation-info-wrap">' .
						'<div class="thumbs-wrap">' .
							'<span class="thumbs-text">' .
								'<span class="thumbs">' . $start_val . '</span>'. ' ' .
								_x( 'Thumbs', 'Sponsoring Form', 'h3-mgmt' ) .
							'</span>' .
						'</div>' .
						'<span class="donation-text">' .
							_x( 'which are', 'Sponsoring Form', 'h3-mgmt' ) . ': ' .
							'<span class="donation">' . $don_val . '</span>' . ' Euros'.
						'</span>' .
					'</div>' .
					'<div class="arrow-wrap"><span class="more-arrow arrow-right horiz-arrow arrow">&gt;&gt;</span></div>' .
				'</div>';
		}

		return $output;
	}

	/**
	 * Returns the donation form
	 *
	 * @since 1.0
	 * @access private
	 */
	private function make_form( $args = array() ) {

		$default_args = array(
			'type' => 'sponsor',
			'method' => 'debit',
			'messages' => array(),
			'race' => 1
		);
		extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

		$output = '';

		if( ! empty( $messages ) ) {
			foreach( $messages as $message ) {
				$output .= '<p class="' . $message['type'] . '">' . $message['message'] . '</p>';
			}
		}

		$output .= '<form name="h3_mgmt_donation_form" method="post" enctype="multipart/form-data" action="">' .
			'<input type="hidden" name="submitted" value="y"/>' .
			'<input type="hidden" name="type" id="type" value="' . $type . '"/>' .
			'<input type="hidden" name="method" id="method" value="' . $method . '"/>' .
			'<div class="form-row trap-row"><label for="address">Please leave this blank...</label>' .
			'<input type="text" name="address" id="address" value=""></div>';

		if( $type == 'sponsor' || $type == 'owner' ) {
			$output .= '<h3 class="first">' . _x( 'The Team', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

			if( $type == 'owner' ) {
				$fields = $this->add_values( $this->team_field( array( 'exclude_with_owner' => true, 'race' => $race ) ) );
			} else {
				$fields = $this->add_values( $this->team_field( array( 'exclude_with_owner' => false, 'race' => $race ) ) );
			}
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
		}

		$output .= '<h3';
		if( $type !== 'sponsor' && $type !== 'owner' ) {
			$output .= ' class="first"';
		}
		$output .= '>' . _x( 'The Donation', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

		if( $type === 'owner' ) {
			$output .= $this->donation_selector( 'normal', 10 );
		} elseif( $type !== 'structure' ) {
			$output .= $this->donation_selector( 'normal', 1 );
		} else {
			$output .= $this->donation_selector( 'text' );
		}

		if( $type == 'owner' ) {
			$output .= '<h3>' . _x( 'Owner Priviliges', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
			$fields = $this->add_values( $this->owner_fields() );
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
		}

		$output .= '<h3>' . _x( 'About you', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

		$fields = $this->add_values( $this->donor_fields() );
		require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

		if( $method == 'debit' ) {
			$output .= '<h3>' . _x( 'Bank Details', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
			$fields = $this->add_values( $this->debit_fields() );
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
		}

		$output .= '<h3>' . _x( 'Donation Receipt', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>' .
			'<div id="no-donation-receipt-wrap">' .
				'<p>' .
					_x( 'Under a sum of 200 Euros, the German Tax Institution will accept a copy of the relevant bank statement as a valid proof of having donated. Should you choose to donate more, Viva con Agua e.V. will gladly mail you a formal receipt.', 'Sponsoring Form', 'h3-mgmt' ) .
				'</p>' .
			'</div>' .
			'<div id="donation-receipt-wrap">';
		$fields = $this->add_values( $this->address_fields() );
		require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
		$output .= '</div>';

		$output .= '<h3>' . _x( 'The Message', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
		if( $type == 'sponsor' || $type == 'owner' ) {
			$fields = $this->add_values( $this->message_field( 'team' ) );
		} else {
			$fields = $this->add_values( $this->message_field( 'global' ) );
		}
		require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

		if ( $this->form_submittable ) {
			$output .= '<div class="form-row">' .
				'<input type="submit" id="donation-submit-' . $method . '" name="donation-submit-' . $method . '" value="' .
					_x( 'Submit Donation', 'Team Dashboard', 'h3-mgmt' ) .
				'" /></div>';
		} else {
			$output .= '<div class="form-row">' .
				'<p><em>' .
					_x( 'This form is currently not submittable. Possbily due to the fact that currently no team can be chosen.', 'Team Dashboard', 'h3-mgmt' ) .
				'</em></p></div>';
		}

		$output .= '</form>';

		return $output;
	}

	/*************** SPONSORING: VALIDATION & SAVING ***************/

	/**
	 * Validates a submitted donation
	 *
	 * @since 1.0
	 * @access private
	 */
	private function validate_submit() {
		$errors = array();

		$valid = true;

		if ( ! empty( $_POST['address'] ) ) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( 'You have been identified as a spam bot. Screw you!', 'Form Validation Error', 'h3-mgmt' )
			);
			$valid = false;
		}

		if ( $_POST['team_id'] == 'please_select' ) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( "Please select a Team you want to sponsor...", 'Sponsoring Form Error', 'h3-mgmt' )
			);
			$valid = false;
		}

		if ( ! is_email( $_POST['email'] ) ) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( "The E-Mail address you entered appears to be invalid.", 'Form Validation Error', 'h3-mgmt' )
			);
			$valid = false;
		}

		if ( $_GET['method'] != 'paypal' && ( ! isset( $_POST['debit_confirmation'] ) || $_POST['debit_confirmation'] != 1 ) ) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( "You have chosen to donate via direct debit. Please check the debit confirmation. Thank you!", 'Sponsoring Form Error', 'h3-mgmt' )
			);
			$valid = false;
		}

		if (
			empty( $_POST['first_name'] ) ||
			empty( $_POST['last_name'] ) ||
			empty( $_POST['email'] ) ||
			( $_POST['method'] == 'debit' && (
				empty( $_POST['account_id'] ) ||
				empty( $_POST['bank_id'] ) ||
				empty( $_POST['bank_name'] )
			) )
		) {
			$errors[] = array(
				'type' => 'error',
				'message' => _x( "You have not filled out all required fields.", 'Sponsoring Form Error', 'h3-mgmt' )
			);
			$valid = false;
		}

		return array( $valid, $errors );
	}

	/**
	 * Saves a donation
	 *
	 * @since 1.0
	 * @access private
	 */
	private function save_donation( $race_id = 1 ) {
		global $wpdb, $h3_mgmt_mailer, $h3_mgmt_teams, $h3_mgmt_utilities;

		if( ! empty( $_POST['team_id'] ) ) {
			$team_id = $_POST['team_id'];
		} else {
			$team_id = 0;
		}
		$language = $h3_mgmt_utilities->user_language();
		$team_language = $h3_mgmt_teams->get_team_language( $team_id );

		$wpdb->insert(
			$wpdb->prefix . 'h3_mgmt_sponsors',
			array(
				'race_id' => $race_id,
				'team_id' => $team_id,
				'type' => $_POST['type'],
				'method' => $_POST['method'],
				'donation' => $_POST['donation'],
				'display_name' => $_POST['display_name'],
				'first_name' => $_POST['first_name'],
				'last_name' => $_POST['last_name'],
				'email' => $_POST['email'],
				'paid' => 0,
				'show' => 0,
				'message' => $_POST['message'],
				'language' => $language
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s'
			)
		);
		$sponsor_id = $wpdb->insert_id;

		if( $_POST['type'] == 'owner' ) {
			if( ! empty( $_FILES['owner_pic']['name'] ) ) {
				$owner_pic_data = wp_upload_bits(
					$_FILES['owner_pic']['name'],
					null,
					file_get_contents( $_FILES['owner_pic']['tmp_name'] )
				);
				$owner_pic = $owner_pic_data['url'];
			} else {
				$owner_pic = $_POST['owner_pic-tmp'];
			}
			$wpdb->update(
				$wpdb->prefix . 'h3_mgmt_sponsors',
				array( 'owner_pic' => $owner_pic, 'owner_link' => $_POST['owner_link'] ),
				array( 'id' => $sponsor_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		$ids = $h3_mgmt_teams->get_teammates( $_POST['team_id'] );
		$response_args = array(
			'team_name' => $h3_mgmt_teams->get_team_name( $_POST['team_id'] ),
			'donation' => $_POST['donation'],
			'thumbs' => $_POST['thumbs'],
			'name' => $_POST['first_name'] . ' ' . $_POST['last_name']
		);


		if( $_POST['donation'] >= 200 ) {
			$wpdb->update(
				$wpdb->prefix . 'h3_mgmt_sponsors',
				array(
					'receipt' => $_POST['receipt'],
					'street' => $_POST['street'],
					'zip_code' => $_POST['zip_code'],
					'city' => $_POST['city'],
					'country' => $_POST['country'],
					'address_additional' => $_POST['address_additional']
				),
				array( 'id' => $sponsor_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		if( $_POST['method'] == 'debit' ) {
			$wpdb->update(
				$wpdb->prefix . 'h3_mgmt_sponsors',
				array(
					'account_id' => $_POST['account_id'],
					'bank_id' => $_POST['bank_id'],
					'bank_name' => $_POST['bank_name'],
					'debit_confirmation' => $_POST['debit_confirmation'],
					'show' => 1
				),
				array( 'id' => $sponsor_id ),
				array( '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
			if( $_POST['type'] == 'owner' ) {
				$h3_mgmt_mailer->auto_response( $_POST['email'], 'debit-thanks-owner', $response_args, 'mail', $language );
				$h3_mgmt_mailer->auto_response( $ids, 'new-owner', $response_args, 'id', $team_language );
			} elseif( $_POST['type'] == 'sponsor' ) {
				$h3_mgmt_mailer->auto_response( $_POST['email'], 'debit-thanks-sponsor', $response_args, 'mail', $language );
				$h3_mgmt_mailer->auto_response( $ids, 'new-sponsor', $response_args, 'id', $team_language );
			}
		} else {
			if( $_POST['type'] == 'owner' ) {
				$h3_mgmt_mailer->auto_response( $_POST['email'], 'paypal-please-owner', $response_args, 'mail', $language );
			} elseif( $_POST['type'] == 'sponsor' ) {
				$h3_mgmt_mailer->auto_response( $_POST['email'], 'paypal-please-sponsor', $response_args, 'mail', $language );
			}
		}

		return $this->sponsoring_section_output( array(
			'step' => 4,
			'method' => $_POST['method'],
			'type' => $_POST['type'],
			'team_id' => $_POST['team_id'],
			'donation' => $_POST['donation'],
			'sponsor_id' => $sponsor_id,
			'race' => $race_id
		));
	}

	/*************** FURTHER SHORTCODES ***************/

	/**
	 * Returns most recent sponsors
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function recent_sponsors( $atts = '' ) {
		global $wpdb, $h3_mgmt_teams;

		extract( shortcode_atts( array(
			'number' => 3,
			'race' => 'all'
		), $atts ) );

		$where = '';
		if ( is_numeric( $race ) ) {
			$where = "WHERE race_id = " . $race . " ";
		}

		$sponsors_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_sponsors " .
			$where .
			"ORDER BY id DESC",
			ARRAY_A
		);

		$output = '';
		if ( ! empty( $sponsors_query ) ) {
			$output .= '<p>';
			$number = $number <= count( $sponsors_query ) ? $number : count( $sponsors_query );
			for( $i = 0; $i < $number; $i++ ) {
				$thumbs = intval( $sponsors_query[$i]['donation'] ) / 10;
				if( empty( $sponsors_query[$i]['display_name'] ) ) {
					$sponsors_query[$i]['display_name'] = _x( 'Anonymous Sponsor', 'Sponsoring', 'h3-mgmt' );
				}
				if( $i !== ($number - 1) ) {
					$output .= '<span class="item activity-stream bottom-border">';
				} else {
					$output .= '<span class="item activity-stream">';
				}
				$output .= str_replace( '%donor%', '<em>' . stripslashes( $sponsors_query[$i]['display_name'] ) . '</em>', str_replace( '%thumbs%', $thumbs, str_replace( '%team%', '<a class="cursive-link" title="' . _x( 'Check the TeamProfile ...', 'Team', 'h3-mgmt' ) . '" href="' . _x( 'http://tramprennen.org/tramprennen/follow-us/teams/', 'Team Link', 'h3-mgmt' ) . '?id=' . $sponsors_query[$i]['team_id'] . '">' . $h3_mgmt_teams->get_team_name( $sponsors_query[$i]['team_id'] ) . '</a>', _x( '%donor% sponsored %team% with %thumbs% thumb(s)', 'Recent Activity Stream', 'h3-mgmt' ) ) ) );
				if( ! empty( $sponsors_query[$i]['message'] ) ) {
					$output .= ' <span class="tip" onmouseover="tooltip(\'' .
							preg_replace( "/\w[-._\w]*\w@\w[-._\w]*\w\.\w{2,3}/", "", preg_replace( "/\r|\n/", "<br />", str_replace( '"', '&quot;', str_replace( "'", '&apos;', $sponsors_query[$i]['message'] ) ) ) ) .
							'\');" onmouseout="exit();">' .
								'<img class="comments-bubble no-bsl-adjust" alt="Comments Bubble" src="' . H3_MGMT_RELPATH . 'img/comments-bubble.png" />' .
						'</span>';
				}
				$output .= '</span>';
			}
			$output .= '</p>';
		} else {
			$output .= '<p>' . __( 'No donors for Tramprennen 2013 yet...', 'h3-mgmt' ) . '</p>';
		}

		return $output;
	}

	/**
	 * Returns a list of sponsors
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function sponsors_overview( $atts = '' ) {
		extract( shortcode_atts( array(
			'type' => 'sponsor',
			'delimiter' => ', ',
			'race' => 'all'
		), $atts ) );

		$sponsors = $this->list_sponsors( array(
			'type' => $type,
			'team_id' => 'all',
			'race' => $race,
			'delimiter' => $delimiter
		));

		return $sponsors['names'];
	}

	/*************** UTILITY METHODS ***************/

	/**
	 * Returns the preferred language of a sponsor
	 *
	 * @param int $sponsor_id
	 *
	 * @return string $language
	 *
	 * @since 1.1
	 * @access public
	 */
	public function get_sponsor_language( $sponsor_id = NULL ) {
		global $wpdb;

		if ( ! is_numeric( $sponsor_id ) ) {
			return 'en';
		}

		$language_query = $wpdb->get_results(
			"SELECT language FROM " .
			$wpdb->prefix."h3_mgmt_sponsors " .
			"WHERE id = " . $sponsor_id . " LIMIT 1",
			ARRAY_A
		);

		$language = isset( $language_query[0]['language'] ) ? $language_query[0]['language'] : 'en';

		return $language;
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
		add_shortcode( 'h3-sponsoring-form', array( &$this, 'sponsoring_form_handler' ) );
		add_shortcode( 'h3-recent-sponsors', array( &$this, 'recent_sponsors' ) );
		add_shortcode( 'h3-list-sponsors', array( &$this, 'sponsors_overview' ) );
	}

} // class

endif; // class exists

?>