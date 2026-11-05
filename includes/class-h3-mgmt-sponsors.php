<?php
require_once __DIR__ . "/class-h3-mgmt-security.php";

/**
 * H3_MGMT_Sponsors class.
 *
 * This class contains properties and methods for team sponsors and owners.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( !class_exists( 'H3_MGMT_Sponsors' ) ) :

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
            $id = H3_MGMT_Security::id($id);
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

			$sponsors_query = $wpdb->get_results(
				'SELECT * FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				'WHERE id = ' . $id . ' LIMIT 1',
				ARRAY_A
			);

			$sponsors_query = \Cor\Mgmt\WordPress\DonationFrontend::overlay($sponsors_query);
            $sponsor = !empty( $sponsors_query[0] ) ? $sponsors_query[0] : [];

			return $sponsor;
		}

		/**
		 * Returns data about donation amount of Team.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_donation_amount( $args = [] ) {
			global $wpdb;

			$where = 'WHERE 1=0';
            $default_args = [
				'type' => 'team',
				'id'   => 0,
				'paid' => true,
				'show' => 'all',
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			if ( $type == 'team' && is_numeric( $id ) && $id != 0 ) {
				$where = $wpdb->prepare('WHERE team_id = %d AND paid = %d', H3_MGMT_Security::id($id), (int) (bool) $paid);
			}

			if ( $show != 'all' && ( $show == 1 || $show == 0 ) ) {
				$where .= ' AND var_show = ' . $show;
			}

			$sponsors_query = [];
			$sponsors_query = $wpdb->get_results(
				'SELECT id,donation FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				$where,
				ARRAY_A
			);

            $sponsors_query = \Cor\Mgmt\WordPress\DonationFrontend::overlay($sponsors_query);
            $amount = new \Cor\Mgmt\Domain\EuroAmount(0);
            foreach ($sponsors_query as $sponsor) {
                $amount = $amount->add(\Cor\Mgmt\Domain\EuroAmount::decimal((string) $sponsor['donation']));
            }
            return $amount->cents % 100 === 0 ? intdiv($amount->cents, 100) : $amount->format();
		}

		/**
		 * Returns data on all sponsors
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_sponsors_meta( $args = [] ) {
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

			$default_args = [
				'orderby'        => 'id',
				'order'          => 'ASC',
				'type'           => 'all',
				'method'         => 'all',
				'exclude_unpaid' => false,
				'extra_fields'   => [],
				'parent'         => 'all',
				'parent_type'    => 'race',
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			if ( $parent_type === 'route' && is_numeric( $parent ) ) {
				$where = $wpdb->prepare('WHERE team_id IN (SELECT id FROM %i WHERE route_id=%d)', $wpdb->prefix . 'h3_mgmt_teams', H3_MGMT_Security::id($parent));
			} elseif ( $parent_type === 'race' && is_numeric( $parent ) ) {
				$where = 'WHERE race_id = ' . $parent . ' ';
			} else {
				$where = '';
			}

			$query_orderby  = 'id'; // legacy
			$sponsors_query = $wpdb->get_results(
				'SELECT * FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				$where .
				'ORDER BY ' . $query_orderby . ' ' . $order,
				ARRAY_A
			);

            $sponsors_query = \Cor\Mgmt\WordPress\DonationFrontend::overlay($sponsors_query);
			$sponsors              = [];
			$all_count             = 0;
			$race_count            = 0;
			$sponsor_count         = 0;
			$owner_count           = 0;
			$patron_count          = 0;
			$structure_count       = 0;
			$debit_count           = 0;
			$paypal_count          = 0;
			$paypal_unpaid_count   = 0;
			$donations_all         = 0;
			$donations_race        = 0;
			$donations_sponsor     = 0;
			$donations_owner       = 0;
			$donations_patron      = 0;
			$donations_structure   = 0;
			$donations_debit       = 0;
			$donations_paypal      = 0;
			$donations_thumbs      = 0;
			$betterplace_count     = 0;
			$donations_betterplace = 0;

			foreach ( $sponsors_query as $key => $sponsor ) {
				if ( $sponsor['method'] == 'debit' ) {
					$debit_count ++;
					$all_count ++;
					$donations_all = $donations_all + $sponsor['donation'];
					if ( $sponsor['type'] == 'sponsor' ) {
						$sponsor_count ++;
						$donations_sponsor = $donations_sponsor + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'owner' ) {
						$owner_count ++;
						$donations_owner = $donations_owner + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'patron' ) {
						$patron_count ++;
						$donations_patron = $donations_patron + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'structure' ) {
						$structure_count ++;
						$donations_structure = $donations_structure + $sponsor['donation'];
					}
					if ( $sponsor['type'] != 'structure' ) {
						$race_count ++;
						$donations_race  = $donations_race + $sponsor['donation'];
						$donations_debit = $donations_debit + $sponsor['donation'];
					}
				} elseif ( $sponsor['method'] == 'paypal' && $sponsor['paid'] == 1 ) {
					$paypal_count ++;
					$all_count ++;
					$donations_all = $donations_all + $sponsor['donation'];
					if ( $sponsor['type'] == 'sponsor' ) {
						$sponsor_count ++;
						$donations_sponsor = $donations_sponsor + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'owner' ) {
						$owner_count ++;
						$donations_owner = $donations_owner + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'patron' ) {
						$patron_count ++;
						$donations_patron = $donations_patron + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'structure' ) {
						$structure_count ++;
						$donations_structure = $donations_structure + $sponsor['donation'];
					}
					if ( $sponsor['type'] != 'structure' ) {
						$race_count ++;
						$donations_race   = $donations_race + $sponsor['donation'];
						$donations_paypal = $donations_paypal + $sponsor['donation'];
					}
				} elseif ( $sponsor['method'] == 'Betterplace' && $sponsor['paid'] == 1 ) {
					$betterplace_count ++;
					$all_count ++;
					$donations_all = $donations_all + $sponsor['donation'];
					if ( $sponsor['type'] == 'sponsor' ) {
						$sponsor_count ++;
						$donations_sponsor = $donations_sponsor + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'owner' ) {
						$owner_count ++;
						$donations_owner = $donations_owner + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'patron' ) {
						$patron_count ++;
						$donations_patron = $donations_patron + $sponsor['donation'];
					} elseif ( $sponsor['type'] == 'structure' ) {
						$structure_count ++;
						$donations_structure = $donations_structure + $sponsor['donation'];
					}
					if ( $sponsor['type'] != 'structure' ) {
						$race_count ++;
						$donations_race        = $donations_race + $sponsor['donation'];
						$donations_betterplace = $donations_betterplace + $sponsor['donation'];
					}
				} elseif ( $sponsor['method'] == 'paypal' && $sponsor['paid'] == 0 ) {
					$paypal_unpaid_count ++;
				}
				if ( $method === 'all' || $sponsor['method'] == $method ) {
					if ( $exclude_unpaid === true && $sponsor['paid'] == 1 ) {
						$sponsors[ $key ]              = $sponsor;
						$sponsors[ $key ]['team_name'] = $h3_mgmt_teams->get_team_name( $sponsor['team_id'] );
					} elseif ( $exclude_unpaid != true ) {
						$sponsors[ $key ]              = $sponsor;
						$sponsors[ $key ]['team_name'] = $h3_mgmt_teams->get_team_name( $sponsor['team_id'] );
					}
					if ( !empty( $extra_fields ) ) {
						foreach ( $extra_fields as $field_id ) {
							if ( 'bank_info' === $field_id ) {
								$sponsors[ $key ][ $field_id ] = 'KTN: ' . $sponsor['account_id'] . '<br />' .
								                                 'BLZ: ' . $sponsor['bank_id'] . '<br />' .
								                                 $sponsor['bank_name'];
							} elseif ( 'race' === $field_id ) {
								$race_id = $h3_mgmt_teams->get_team_race( $sponsor['team_id'] );

								if ( empty( $race_id ) ) {
									$race_id = $sponsor['race_id'];
								}

								$sponsors[ $key ][ $field_id ] = $h3_mgmt_races->get_name( $race_id, 'race' );
							} elseif ( 'code' === $field_id ) {
								$sponsors[ $key ][ $field_id ] = $sponsor['team_id'] . '-' . $sponsor['id'];
							}
						}
					}
				}
			}

			$sponsors         = $h3_mgmt_utilities->sort_by_key( $sponsors, $orderby, $order );
			$donations_thumbs = $donations_race / 10;
			$counts           = [
				'all'           => $all_count,
				'sponsor'       => $sponsor_count,
				'owner'         => $owner_count,
				'patron'        => $patron_count,
				'structure'     => $structure_count,
				'debit'         => $debit_count,
				'paypal'        => $paypal_count,
				'Betterplace'   => $betterplace_count,
				'paypal-unpaid' => $paypal_unpaid_count,
			];
			$donations        = [
				'all'         => $donations_all,
				'race'        => $donations_race,
				'thumbs'      => $donations_thumbs,
				'sponsor'     => $donations_sponsor,
				'owner'       => $donations_owner,
				'patron'      => $donations_patron,
				'structure'   => $donations_structure,
				'debit'       => $donations_debit,
				'paypal'      => $donations_paypal,
				'Betterplace' => $donations_betterplace,
			];

			return [ $sponsors, $counts, $donations ];
		}

		/**
		 * Returns a formatted list of sponsors
		 *
		 * @since 1.0
		 * @access public
		 * @see constructor
		 */
		public function list_sponsors( $args = [] ) {
			global $wpdb, $h3_mgmt_teams, $h3_mgmt_races, $h3_mgmt_utilities;

			$default_args = [
				'type'      => 'all',
				'team_id'   => 'all',
				'race'      => 'all',
				'delimiter' => ', ',
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			$where   = 'WHERE ';
			$started = false;
			if ( is_numeric( $team_id ) ) {
				$where   = 'WHERE team_id = ' . $team_id . ' ';
				$started = true;
			}
			if ( in_array( $type, [ 'sponsor', 'owner' ] ) ) {
				if ( $started ) {
					$where .= 'AND ';
				} else {
					$where .= 'WHERE ';
				}
				$where .= "type = '" . $type . "' AND ";
			}
			if ( is_numeric( $race ) ) {
				if ( $started ) {
					$where .= 'AND ';
				} else {
					$where .= 'WHERE ';
				}
				$where .= 'race_id = ' . $race . ' AND ';
			}

			$sponsors_query = $wpdb->get_results(
				'SELECT * FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				$where . 'paid = 1 AND var_show = 1 ' .
				'ORDER BY display_name ASC',
				ARRAY_A
			);
			$scount         = count( $sponsors_query );
			$sponsors       = [
				'count'          => $scount,
				'anonymous'      => 0,
				'names'          => '',
				'names-tooltip'  => '',
				'names_arr'      => [],
				'owner_pic'      => '',
				'owner_link'     => '',
				'raw_owner_link' => '',
			];

			$exclude_unpaid_paypal_count = 0;
			for ( $i = 0; $i < $scount; $i ++ ) {
				$sponsors_query[ $i ]['display_name'] = esc_html(stripslashes( $sponsors_query[ $i ]['display_name'] ));
				if ( empty( $sponsors_query[ $i ]['display_name'] ) && ( $sponsors_query[ $i ]['method'] !== 'paypal' || $sponsors_query[ $i ]['paid'] == 1 ) ) {
					$sponsors['anonymous'] ++;
					$exclude_unpaid_paypal_count ++;
				} elseif ( $sponsors_query[ $i ]['method'] !== 'paypal' || $sponsors_query[ $i ]['paid'] == 1 ) {
					$exclude_unpaid_paypal_count ++;
					$sponsors['names-tooltip'] .= $sponsors_query[ $i ]['display_name'];
					if ( !empty( $sponsors_query[ $i ]['message'] ) ) {
						$sponsors['names-tooltip'] .= '<br /><span class="msg">' . $h3_mgmt_utilities->p1_nl2br( preg_replace( '/\w[-._\w]*\w@\w[-._\w]*\w\.\w{2,3}/', '', preg_replace( "/\r|\n/", '<br />', ( str_replace( '"', '&quot;', str_replace( "'", '&apos;', $sponsors_query[ $i ]['message'] ) ) ) ) ) ) . '</span>';
					}
					if ( $sponsors_query[ $i ]['type'] == 'owner' && !empty( $sponsors_query[ $i ]['owner_link'] ) ) {
						$sponsors['names'] .= '<a class="incognito-link" title="' . _x( 'Visit the TeamOwner\'s website', 'Team Profile', 'h3-mgmt' ) . '" ' . 'href="' . esc_url($h3_mgmt_utilities->fix_urls( $sponsors_query[ $i ]['owner_link'] )) . '">' . $sponsors_query[ $i ]['display_name'] . '</a>';
					} else {
						$sponsors['names'] .= $sponsors_query[ $i ]['display_name'];
					}

					$sponsors['names_arr'][] = $sponsors_query[ $i ]['display_name'];
					if ( $i != ( $scount - 1 ) ) {
						$sponsors['names']         .= $delimiter;
						$sponsors['names-tooltip'] .= $delimiter;
					}
				}
			}

			$sponsors['count'] = $exclude_unpaid_paypal_count;

			if ( $team_id !== 'all' && $type === 'owner' ) {
				$race_id      = $h3_mgmt_teams->get_team_race( $team_id );
				$race_setting = $h3_mgmt_races->get_race_setting( $race_id );

				if ( empty( $sponsors_query ) ) {
					$sponsors['owner_pic']  = H3_MGMT_RELPATH . 'img/owner-pic.png';
					$sponsors['owner_link'] = '<p class="owner-link"><a title="' . _x( 'Become this team&apos;s TeamOwner!', 'Team Profile', 'h3-mgmt' ) . '" ' .
					                          'href="' . get_site_url() . $race_setting['donation_link_link'] . '?id=' . $team_id . '&type=owner">' .
					                          _x( 'No Owner yet.', 'Team Profile', 'h3-mgmt' ) . '<br />' .
					                          _x( 'Become this team&apos;s TeamOwner!', 'Team Profile', 'h3-mgmt' ) .
					                          '</a></p>';
				} elseif ( empty( $sponsors_query[0]['display_name'] ) ) {
					if ( !empty( $sponsors_query[0]['owner_pic'] ) ) {
						$sponsors['owner_pic'] = $sponsors_query[0]['owner_pic'];
					} else {
						$sponsors['owner_pic'] = H3_MGMT_RELPATH . 'img/owner-pic.png';
					}
					$sponsors['owner_link'] = '<p class="owner-link">' . _x( 'There is an Anonymous Owner!', 'Team Profile', 'h3-mgmt' ) . '</p>';
				} else {
					if ( !empty( $sponsors_query[0]['owner_pic'] ) ) {
						$sponsors['owner_pic'] = $sponsors_query[0]['owner_pic'];
					} else {
						$sponsors['owner_pic'] = H3_MGMT_RELPATH . 'img/owner-pic.png';
					}

					$sponsors['owner_link'] .= '<p class="owner-link">';
					if ( !empty( $sponsors_query[0]['owner_link'] ) ) {
						$sponsors['owner_link']     .= '<a title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '" ' .
						                               'href="' . esc_url($h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] )) . '">';
						$sponsors['raw_owner_link'] .= '<a class="cursive-link" title="' . _x( 'Visit the TeamOwner&apos;s website', 'Team Profile', 'h3-mgmt' ) . '" ' .
						                               'href="' . esc_url($h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] )) . '">';
						$sponsors['owner_link_url'] = esc_url($h3_mgmt_utilities->fix_urls( $sponsors_query[0]['owner_link'] ));
					}
					$sponsors['owner_link']     .= $sponsors_query[0]['display_name'];
					$sponsors['raw_owner_link'] .= $sponsors_query[0]['display_name'];
					if ( !empty( $sponsors_query[0]['owner_link'] ) ) {
						$sponsors['owner_link']     .= '</a>';
						$sponsors['raw_owner_link'] .= '</a>';
					}
					if ( !empty( $sponsors_query[0]['message'] ) ) {
						$sponsors['owner_link'] .= '<br /><span class="msg">' . $h3_mgmt_utilities->p1_nl2br( preg_replace( "/\r|\n/", '<br />', ( str_replace( '"', '&quot;', str_replace( "'", '&apos;', $sponsors_query[0]['message'] ) ) ) ) ) . '</span>';
					}
					$sponsors['owner_link'] .= '</p>';
				}
			}

			return $sponsors;
		}

		/**
		 * Returns a team id of donation_client_reference
		 *
		 * @since 1.0
		 * @access public
		 * @see constructor
		 */
		public function get_team_id( $donation_client_reference ) {
            global $wpdb;
            if (!is_string($donation_client_reference)) { return 0; }
            return (int) $wpdb->get_var($wpdb->prepare("SELECT team_id FROM {$wpdb->prefix}h3_mgmt_sponsors WHERE donation_client_reference=%s LIMIT 1", $donation_client_reference));
        }

		/*************** SPONSORING (FRONT END) ***************/

		/**
		 * This function fetches the donations of an event with the betterplace.org API
		 * and returns those that contain the given search string in the public comment field.
		 *
		 * @param string $fundraisingEventId
		 * @param string $searchString
		 *
		 * @return array|null
		 */
        private function fetchAndFilterDonations( $fundraisingEventId, $searchString ) {
            // No unjournaled public-opinion confirmation through this retired helper.
            return null;
        }

		/**
		 * This function extracts the fundraising ID from the given betterplace.org URL.
		 *
		 * @param string $url
		 *
		 * @return string|null
		 */
		private function extractFundraisingId( $url ) {
            $parts = is_string($url) ? wp_parse_url($url) : false;
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), ['betterplace.org','www.betterplace.org'], true)) { return null; }
            return preg_match('~^/(?:de|en)/fundraising-events/([0-9]+)-~', $parts['path'] ?? '', $matches) ? $matches[1] : null;
        }

		/**
		 * This function generates the layout of the donation workflow.
		 *
		 * @param int $raceId
		 * @param int $step
		 * @param string $content
		 *
		 * @return string
		 */
		private function generateLayout( $raceId, $step, $content ) {
			global $h3_mgmt_races;

			$headerText = _x( 'Sponsoring Steps', 'Sponsoring Form', 'h3-mgmt' );

			$output = <<<html
<div class="flex_column av_one_half first avia-builder-el-0  el_before_av_one_half  avia-builder-el-first">
    <h3>$headerText</h3>
html;

			$stepHeaders = [
				_x( 'Choose a team.', 'Sponsoring Form', 'h3-mgmt' ),
				_x( 'Fill out the form for our homepage', 'Sponsoring Form', 'h3-mgmt' ),
				_x( 'Finish', 'Sponsoring Form', 'h3-mgmt' )
			];

			$stepInfoTexts = [
				stripcslashes( $h3_mgmt_races->get_race_information_text( $raceId )[19] ),
				_x( 'Fill out the Form<br />The Data will be shown at the Team-Profile', 'Sponsoring Form', 'h3-mgmt' ),
				_x( 'Donation process finish.', 'Sponsoring Form', 'h3-mgmt' )
			];

			for ( $i = 1; $i <= 3; $i ++ ) {
				$noMarginClass = $i === 3 ? ' no-margin-bottom' : '';
				$imgSrc        = H3_MGMT_RELPATH . 'img/jerrycan-' . $i . '-2014' . ( $step === $i ? '-alternate' : '' ) . '.png';
				$spanClass     = 'to-do-can';
				if ( $i < $step ) {
					$spanClass = 'to-do-confirm-done';
				} elseif ( $i === $step ) {
					$spanClass = 'to-do-positive';
				}

				$output .= <<<html
    <div class="overview-category">
        <div class="description">
            <p>
                <img class="baseline-adjustable$noMarginClass" src="$imgSrc" alt="Jerrycan" />
                <strong><span class="$spanClass">{$stepHeaders[$i - 1]}</span></strong>
                <br />
                <em>{$stepInfoTexts[$i - 1]}</em>
            </p>
        </div>
    </div>
html;
			}

			$output .= <<<html
</div>
<div class="flex_column av_one_half avia-builder-el-1  el_before_av_one_half">
    $content
</div>
html;

			return H3_MGMT_Security::add_nonces(H3_MGMT_Security::html($output), H3_MGMT_Security::donation_nonce((int) $raceId));
		}

		/**
		 * This function generates the donation form for Step 0,1 and 3
		 *
		 * @param int $raceId
		 * @param string $type
		 * @param int|null $teamId
		 * @param string|null $donationToken
		 * @param int|null $amount
		 * @param string $displayName
		 * @param string $message
		 * @param string $ownerLink
		 * @param string $ownerPic
		 *
		 * @return string
		 */
		private function generateForm(
			$raceId,
			$type,
			$teamId = null,
			$donationToken = null,
			$amount = null,
			$displayName = '',
			$message = '',
			$ownerLink = '',
			$ownerPic = ''
		) {
			global $h3_mgmt_teams;

			// Step 0: Select Sponsor or Owner
			if ( $type !== 'sponsor' && $type !== 'owner' ) {
				return $this->base_selector( $raceId );
			}

			$output = '';

			if ( $teamId !== null && $donationToken !== null ) {
				// Step 3: Donation done
				$teamName = $h3_mgmt_teams->get_team_name( $teamId );

				$paragraphText = str_replace( [ '%amount%', '%team_name%' ], [ $amount ?: '??', $teamName ],
					$type === 'owner' ?
						_x(
							'Great you just donated %amount%€ at Betterplace.' .
							' You are the Team Owner of %team_name% now!' .
							' Please fill out the form below for our Homepage.' .
							' The data will be displayed at the Team Profile.',
							'Sponsoring Form',
							'h3-mgmt'
						) :
						_x(
							'Great you just donated %amount%€ at Betterplace.' .
							' You are a Team Sponsor of %team_name% now!' .
							' Please fill out the form below for our Homepage.' .
							' The data will be displayed at the Team Profile.',
							'Sponsoring Form',
							'h3-mgmt'
						)
				);

				$output .= "<p class=\"message\" style=\"font-weight: bold;\">$paragraphText</p>";
			}

			// General form fields
			$output .= <<<html
<form name="h3_mgmt_donation_form" method="post" enctype="multipart/form-data" action="">
    <input type="hidden" name="type" value="$type"/>
    <div class="form-row trap-row"><label for="address">Please leave this blank...</label>
    <input type="text" name="address" id="address" value=""></div>
html;

			if ( $teamId !== null && $donationToken !== null ) {
				// Step 3: Donation done
				$output .= <<<html
<input type="hidden" name="submitted" value="3"/>
<input type="hidden" name="donation_token" value="$donationToken" />
html;

				if ( $type === 'owner' ) {
					$output .= '<h3>' . _x( 'Owner Priviliges', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
					$fields = $this->add_values( $this->owner_fields( $ownerPic, $ownerLink ) );
					require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
				}

				$output .= '<h3 class="top-space-more">' . _x( 'About you', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

				$fields = $this->add_values( $this->donor_fields( $displayName ) );
				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

				$output .= '<h3 class="top-space-more">' . _x( 'The Message', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
				$fields = $this->add_values( $this->message_field( 'team', $message ) );
			} else {
				// Step 1: Team selection
				$output .= '<input type="hidden" name="submitted" value="1"/>';

				$heading = _x( 'The Team', 'Sponsoring Form', 'h3-mgmt' );
				$output  .= "<h3 class=\"first\">$heading</h3>";

				if ( $type === 'owner' ) {
					$teamOwnerParagraph = _x(
						'All shown Teams haven\'t a TeamOwner yet. Be there TeamOwner NOW!<br /><br />' .
						'<b>Please Remember:</b> At the next Step you have to donate <b>at least 100€</b> to be a <b>TeamOwner</b>' .
						' and to have the privileges to add a picture at their Team-Profile!',
						'Sponsoring Form',
						'h3-mgmt'
					);

					$output .= "<p>$teamOwnerParagraph</p>";
				}

				$fields = $this->add_values(
					$this->team_field(
						[
							'exclude_with_owner' => ( $type === 'owner' ),
							'race'               => $raceId,
						]
					)
				);
			}

			// General end of form
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

			$output .= '<div class="form-row">';

			if ( $this->form_submittable === true ) {
				$inputValue = _x( 'Next', 'Sponsoring Form', 'h3-mgmt' );
				$output     .= "<input type=\"submit\" id=\"donation-submit-betterplace\" name=\"donation-submit-betterplace\" value=\"$inputValue\" />";
			} else {
				$notSubmittableText = _x(
					'This form is currently not submittable. Possibly due to the fact that currently no team can be chosen.',
					'Sponsoring Form',
					'h3-mgmt'
				);
				$output             .= "<p><em>$notSubmittableText</em></p>";
			}

			$output .= '</div></form>';

			return $output;
		}

		/**
		 * @param string $text
		 *
		 * @return string
		 */
		private function generateErrorText( $text ) {
			return <<<html
<p class="message" style="text-align: center; margin-bottom: 200px;">
    $text
</p>
html;
		}

		/**
		 * The handler for the donation workflow with betterplace.org
		 *
		 * @param array $atts
		 *
		 * @return string
		 */
        public function new_sponsoring_handler( $atts = [] ) {
            return \Cor\Mgmt\WordPress\DonationFrontend::render(is_array($atts) ? $atts : []);
        }

		/**
		 * Sponsoring form shortcode handler
		 *
		 * @since 1.0
		 * @access public
		 * @see constructor
		 */
        public function sponsoring_form_handler( $atts = [] ) {
            return \Cor\Mgmt\WordPress\DonationFrontend::render(is_array($atts) ? $atts : []);
        }

		/**
		 * Sponsoring Section Output generator
		 *
		 * @since 1.0
		 * @access public
		 * @see constructor
		 */
		private function sponsoring_section_output( $args = [] ) {

			$default_args = [
				'step'       => 1,
				'messages'   => [],
				'method'     => 'debit',
				'type'       => '',
				'team_id'    => 0,
				'donation'   => 20,
				'sponsor_id' => 0,
				'race'       => 1,
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			global $wpdb, $h3_mgmt_teams, $information_text, $h3_mgmt_races;

			$race_settings = $h3_mgmt_races->get_race_setting( $race );

			$step_strings = [];

			$step_strings[1] = stripcslashes( $information_text[19] );
			//_x( 'Click next and donate at the Betterplace Website.<br />After finishing there you will be redirected to our homepage for the next step.<br />If you will donate more then 100€ you will get Teamowner if still aviable otherwise sponsor. With both you support the WASH-projects of Viva con Agua and the work of PRO ASYL. Teams can have several TeamSponsors but only one TeamOwner, which can place a photo or a logo and a link in the team profile.', 'Sponsoring Form', 'h3-mgmt' );

			$step_strings[2] = _x( 'Fill out the Form<br />The Data will be shown at the Team-Profile', 'Sponsoring Form', 'h3-mgmt' );

			$step_strings[3] = _x( 'Donation process finish.', 'Sponsoring Form', 'h3-mgmt' );

			$output = '<div class="flex_column av_one_half first avia-builder-el-0  el_before_av_one_half  avia-builder-el-first">' .
			          '<h3>' . _x( 'Sponsoring Steps', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

			for ( $i = 1; $i <= 3; $i ++ ) {
				$output .= '<div class="overview-category"><div class="description';

				if ( $i != $step ) {
					$output .= '';
				}

				$output .= '"><p>' .
				           '<img class="baseline-adjustable';
				if ( 3 === $i ) {
					$output .= ' no-margin-bottom';
				}
				$output .= '" src="' . H3_MGMT_RELPATH . 'img/jerrycan-' . $i . '-2014';

				if ( $i == $step ) {
					$output .= '-alternate';
				}

				$output .= '.png" alt="Jerrycan" />' .
				           '<strong>';

				if ( false && $step < 4 && $i != $step && $i < $step ) {
					$output .= '<a title="' . _x( 'Navigate to step', 'Progressive Form Steps', 'h3-mgmt' ) . '" ' .
					           'href="' . get_option( 'siteurl' ) . preg_replace( '/\?.*/', '', $_SERVER['REQUEST_URI'] );

					if ( $i == 2 ) {
						$output .= '?type=' . $_GET['type'];
					}

					$output .= '">';
				}

				$output .= '<span ';
				if ( false && $i != $step ) {
					$output .= 'onmouseover="tooltip(\'' . $step_strings[ $i ] . '\');" onmouseout="exit();" class="tip ';
				} else {
					$output .= 'class="';
				}
				if ( $i != $step && $i < $step ) {
					$output .= 'to-do-confirm-done';
				} elseif ( $i == $step ) {
					$output .= 'to-do-positive';
				} else {
					$output .= 'to-do-can';
				}
				$output .= '">';

				if ( $i === 1 ) {
					$output .= _x( 'Choose a team.', 'Sponsoring Form', 'h3-mgmt' );
				} elseif ( $i === 2 ) {
					$output .= _x( 'Fill out the form for our homepage', 'Sponsoring Form', 'h3-mgmt' );
				} elseif ( $i === 3 ) {
					$output .= _x( 'Finish', 'Sponsoring Form', 'h3-mgmt' );
				}

				if ( $step < 3 && $i != $step && $i < $step ) {
					$output .= '</a>';
				}

				$output .= '</span>';

				$output .= '</strong>';

				if ( $i == $step ) {
					$output .= '<br /><em>' . $step_strings[ $i ] . '</em>';
				}

				$output .= '</p></div></div>';
			}

			$output .= '</div><div class="flex_column av_one_half avia-builder-el-1  el_before_av_one_half">';

			if ( $step === 1 ) {

				if ( $race_settings['kind_of_donation_tool'] == 1 ) {
					$type = 'sponsor';
				} else {
					$type = $_GET['type'];
				}

				// check if the choose owner or sponsor if not let them choose
				if ( $type == 'owner' || $type == 'sponsor' ) {
					if ( $type == 'owner' ) {
						// choose team but just where no owner is
						$output .= $this->make_form(
							[
								'type'     => 'team_owner',
								'messages' => $messages,
								'race'     => $race,
							]
						);
					} else {
						// choose between all teams
						$output .= $this->make_form(
							[
								'type'     => 'team',
								'messages' => $messages,
								'race'     => $race,
							]
						);
					}
				} else {
					$output .= $this->base_selector( $race );

					$output .= '</div>';
				}
			} elseif ( $step === 2 ) {

				if ( $type == 'owner' ) {
					$output .= $this->make_form(
						[
							'type'     => 'owner',
							'method'   => 'Betterplace',
							'messages' => $messages,
						]
					);
				} else {
					$output .= $this->make_form(
						[
							'type'     => 'sponsor',
							'method'   => 'Betterplace',
							'messages' => $messages,
						]
					);
				}
			} elseif ( $step === 3 ) {
				$team_id = $this->get_team_id( $_POST['donation_client_reference'] );
				$output  .= $this->finish_form( $team_id );

				$output .= '</div>';

			}

			return $output;
		}

		/**
		 * Sponsoring Section Output generator
		 *
		 * @since 1.0
		 * @access private
		 */
		private function technical_issue_section_output( $race_id ) {

			global $h3_mgmt_races;

			$race_settings = $h3_mgmt_races->get_race_setting( $race_id[0]['race_id'] );

			$redirect_url = ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $race_settings['donation_link_link'] . '?section=2&donation_client_reference=' . $donation_client_reference . '&amount=' . $amount . '&donation_token=' . $donation_token;

			$errors = [
				'type'    => 'error',
				'message' => _x( 'Sorry there is a technical issue! Please contact web@tramprennnen.org', 'Form Validation Error', 'h3-mgmt' ),
			];

			$output .= '<div style="text-align: center;">
                                    <p class="' . $errors['type'] . '">' . $errors['message'] . '<br><br>
                                    <strong><a title="BACK" href="' . esc_url($redirect_url) . '">BACK</a></strong></p>
                                    </div>

            ';

			return $output;
		}

		/**
		 * Returns success section
		 *
		 * @since 1.0
		 * @access private
		 */
		private function betterplace( $args = [] ) {
			global $current_user, $h3_mgmt_teams;

			$default_args = [
				'method'     => 'debit',
				'type'       => 'sponsor',
				'team_id'    => 0,
				'donation'   => 20,
				'sponsor_id' => 0,
				'race'       => 1,
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			//$output = '<h3>' . _x( 'Just one more step!', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

			$thumbs    = intval( $donation ) / 10;
			$team_name = $h3_mgmt_teams->get_team_name( $team_id );

			$titles = [
				'sponsor'   => _x( 'TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
				'owner'     => _x( 'TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
				'patron'    => _x( 'RacePatron', 'Sponsoring Form', 'h3-mgmt' ),
				'structure' => _x( 'Structural Sponsor', 'Sponsoring Form', 'h3-mgmt' ),
			];

			$paypal_sum = number_format( $donation, 2, '.', '' );
			if ( in_array( $current_user->ID, [ 1, 4, 2259 ] ) ) {
				$paypal_sum = $paypal_sum / 100;
			}

			$type_strings = [
				'sponsor' => [
					'title'          => $titles['sponsor'],
					'debit-message'  => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['sponsor'], _x( 'You have successfully become %1$sponsoring_type% of "%team_name%" and chosen to donate %thumbs% thumbs (%2$donation% Euros)', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					                    '<br />' .
					                    _x( 'The donation will be deducted from your bank account within a month.', 'Sponsoring Form', 'h3-mgmt' ) .
					                    '<br />' .
					                    _x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ),
					'paypal-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['sponsor'], _x( 'In order to become %1$sponsoring_type% of "%team_name%" by donating %thumbs% thumbs (%2$donation% Euros), be so kind as to transfer the chosen amount via PayPal by clicking the below button.', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					                    '</p>' . $vca_paypal_form . '<br /><p>' .
					                    _x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ) . '</p>',
				],
				'owner'   => [
					'title'          => $titles['owner'],
					'debit-message'  => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['owner'], _x( 'You have successfully become %1$sponsoring_type% of "%team_name%" and chosen to donate %thumbs% thumbs (%2$donation% Euros)', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					                    '<br />' .
					                    _x( 'The donation will be deducted from your bank account within a month.', 'Sponsoring Form', 'h3-mgmt' ) .
					                    '<br />' .
					                    _x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ),
					'paypal-message' => str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%thumbs%', $thumbs, str_replace( '%sponsoring_type%', $titles['owner'], _x( 'In order to become %1$sponsoring_type% of "%team_name%" by donating %thumbs% thumbs (%2$donation% Euros), be so kind as to transfer the chosen amount via PayPal by clicking the below button.', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) ) .
					                    '</p>' . $vca_paypal_form . '<br /><p>' .
					                    _x( 'Thanks in the name of the team, on behalf of Tramprennen and on behalf of Viva con Agua and the people in India and Nepal!', 'Sponsoring Form', 'h3-mgmt' ) . '</p>',
				],
			];

			$output = '<iframe height="1350px" width="100%" frameborder="0" src=" https://www.betterplace.org/de/fundraising-events/tramprennen15/iframe_donations/new#eft" id="iFrameResizer0" scrolling="no" style="max-width: 600px; max-height: none; width: 100%; overflow: hidden; background-color: transparent;"></iframe>';

			// $output .= '<p class="message">';
			// $output .= sprintf( _x( 'You now are almost a %1$s!', 'Sponsoring Form', 'h3-mgmt' ), $type_strings[$type]['title'] );
			// $output .= '</p><p>';
			// $output .= '<p>';
			// $output .= str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%sponsoring_type%', $titles[$type], _x( 'You have chosen to become %sponsoring_type% of "%team_name%" by donating %donation% Euros.', 'Sponsoring Form', 'h3-mgmt' ) ) ) );
			// $output .= '</p><h5 >';
			// $output .= sprintf( _x( 'To complete the donation process:', 'Sponsoring Form', 'h3-mgmt' ));
			// $output .= '</h5><p class="message">';
			// $output .= str_replace( '%betterplace%', '<a style="font-size: xx-large;" target="_blank" href="https://www.betterplace.org/de/fundraising-events/tramprennen15/donations/new#eft" title="' . __( 'Complete the donation', 'h3-mgmt' ) . '">betterplace.org</a>', str_replace( '%code%', $team_id.'-'.$sponsor_id, _x( 'Follow the link to %betterplace%<br> and enter <font style="font-size: xx-large;">%code%</font> in the comments section!!!', 'Sponsoring Form', 'h3-mgmt' ) ) );
			// $output .= '</p><p>';
			// $output .= sprintf( _x( 'Important: You don\'t have to donate extra money for Betterplace!', 'Sponsoring Form', 'h3-mgmt' ));
			// $output .= '</p>';
			$output .= '<h3 style="text-align: center; margin-top: 0px;">';
			$output .= sprintf( _x( 'Thanks!', 'Sponsoring Form', 'h3-mgmt' ) );
			$output .= '</h3>';
			// $output .= str_replace( '%betterplace%', '<a target="_blank" href="https://www.betterplace.org/de/fundraising-events/tramprennen15" title="' . __( 'Complete the donation', 'h3-mgmt' ) . '">betterplace.org</a>', str_replace( '%team_name%', $team_name, str_replace( '%donation%', $donation, str_replace( '%sponsoring_type%', $titles[$type], _x( 'You have chosen to become %sponsoring_type% of "%team_name%" by donating %donation% Euros. Be so kind as to complete the transaction via %betterplace%.', 'Sponsoring Form', 'h3-mgmt' ) ) ) ) );
			// $output .= '</p><p>';
			// $output .= str_replace( '%code%', $team_id.'-'.$sponsor_id, _x( 'If you want to appear in the team&apos;s profile, please do not forget enter the following code "%code%" in betterplace&apos;s comments section!', 'Sponsoring Form', 'h3-mgmt' ) );

			return $output;
		}

		/**
		 * Returns the form field for the method selector
		 *
		 * @since 1.0
		 * @access private
		 */
		private function method_field() {

			$method_field = [
				[
					'label'   => _x( 'Donation Method', 'Sponsoring Form', 'h3-mgmt' ),
					'tooltip' => _x( 'Donations can be submitted either via PayPal or via direct debit from a German bank account.', 'Sponsoring Form', 'h3-mgmt' ),
					'id'      => 'method',
					'type'    => 'radio',
					'options' => [
						[
							'label' => _x( 'Debit (Germany only)', 'Sponsoring Form', 'h3-mgmt' ),
							'value' => 'debit',
						],
						[
							'label' => _x( 'PayPal', 'Sponsoring Form', 'h3-mgmt' ),
							'value' => 'paypal',
						],
					],
				],
			];

			return $method_field;
		}

		/**
		 * Returns the form field for the team selection
		 *
		 * @since 1.0
		 * @access private
		 */
		private function team_field( $args = [] ) {
			global $h3_mgmt_teams;

			$default_args = [
				'orderby'            => 'team_name',
				'order'              => 'ASC',
				'please_select'      => true,
				'exclude_with_owner' => false,
				'exclude_incomplete' => true,
				'show_mates'         => true,
				'select_text'        => '',
				'race'               => 1,
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			$options = $h3_mgmt_teams->select_options(
				[
					'orderby'            => $orderby,
					'order'              => $order,
					'please_select'      => $please_select,
					'exclude_with_owner' => $exclude_with_owner,
					'exclude_incomplete' => $exclude_incomplete,
					'show_mates'         => $show_mates,
					'select_text'        => $select_text,
					'race'               => $race,
				]
			);

			if ( empty( $options ) || ( true === $please_select && 1 >= count( $options ) ) ) {
				$team_field             = [
					[
						'label' => _x( 'Choose Team', 'Sponsoring Form', 'h3-mgmt' ),
						'id'    => 'team_id',
						'type'  => 'note',
						'note'  => $exclude_with_owner ? _x( 'Either no teams have completed the registration yet or all that have have a TeamOwner already...', 'Sponsoring Form', 'h3-mgmt' ) : _x( 'So far no teams have completed the registration yet...', 'Sponsoring Form', 'h3-mgmt' ),
					],
				];
				$this->form_submittable = false;
			} else {
				$team_field = [
					[
						'label'   => _x( 'Choose Team', 'Sponsoring Form', 'h3-mgmt' ),
						'id'      => 'team_id',
						'type'    => 'select',
						'options' => $options,
					],
				];
			}

			return $team_field;
		}

		/**
		 * Returns the form fields for the donor section
		 *
		 * @since 1.0
		 * @access private
		 */
		public function donor_fields( $displayNameValue = '' ) {

			return [
				// array (
				// 'label'	=> _x( 'First Name', 'Sponsoring Form', 'h3-mgmt' ),
				// 'id'	=> 'first_name',
				// 'type'	=> 'text'
				// ),
				// array (
				// 'label'	=> _x( 'Last Name', 'Sponsoring Form', 'h3-mgmt' ),
				// 'id'	=> 'last_name',
				// 'type'	=> 'text'
				// ),
				// array (
				// 'label'	=> _x( 'E-Mail', 'Sponsoring Form', 'h3-mgmt' ),
				// 'id'	=> 'email',
				// 'type'	=> 'text'
				// ),
				[
					'label' => _x( 'Display Name', 'Sponsoring Form', 'h3-mgmt' ),
					'desc'  => _x( 'Choose how your Name should be displayed on the website (and in the Team Profile, if applicable). If you do not want your name to show at all, simply leave this field blank.', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'display_name',
					'type'  => 'text',
					'value' => $displayNameValue,
				],
			];
		}

		/**
		 * Returns the form fields for the debit section
		 *
		 * @since 1.0
		 * @access private
		 */
		private function debit_fields() {

			$debit_fields = [
				[
					'label' => _x( 'Account ID (KTN)', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'account_id',
					'type'  => 'text',
				],
				[
					'label' => _x( 'Bank ID (BLZ)', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'bank_id',
					'type'  => 'text',
				],
				[
					'label' => _x( 'Name of Bank', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'bank_name',
					'type'  => 'text',
				],
				[
					'label' => _x( 'Debit confirmation', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'debit_confirmation',
					'type'  => 'checkbox',
					'desc'  => _x( 'By checking this box you confirm that Viva con Agua e.V. may debit the above sum from your above bank account. Thank you!', 'Sponsoring Form', 'h3-mgmt' ),
				],
			];

			return $debit_fields;
		}

		/**
		 * Returns the form fields for the debit section
		 *
		 * @since 1.0
		 * @access private
		 */
		private function address_fields() {

			$address_fields = [
				[
					'label'   => _x( 'Receipt', 'Sponsoring Form', 'h3-mgmt' ),
					'id'      => 'receipt',
					'type'    => 'select',
					'options' => [
						[
							'label' => __( 'Yes', 'h3-mgmt' ),
							'value' => 1,
						],
						[
							'label' => __( 'No', 'h3-mgmt' ),
							'value' => 0,
						],
					],
				],
				[
					'label'     => _x( 'Street Address', 'Sponsoring Form', 'h3-mgmt' ),
					'id'        => 'street',
					'type'      => 'text',
					'row-class' => 'address-row',
				],
				[
					'label'     => _x( 'Zip Code', 'Sponsoring Form', 'h3-mgmt' ),
					'id'        => 'zip_code',
					'type'      => 'text',
					'row-class' => 'address-row',
				],
				[
					'label'     => _x( 'City', 'Sponsoring Form', 'h3-mgmt' ),
					'id'        => 'city',
					'type'      => 'text',
					'row-class' => 'address-row',
				],
				[
					'label'     => _x( 'Country', 'Sponsoring Form', 'h3-mgmt' ),
					'id'        => 'country',
					'type'      => 'text',
					'row-class' => 'address-row',
				],
				[
					'label'     => _x( 'Additional Adress Field', 'Sponsoring Form', 'h3-mgmt' ),
					'id'        => 'address_additional',
					'type'      => 'text',
					'row-class' => 'address-row',
				],
			];

			return $address_fields;
		}

		/**
		 * Returns the form fields for the owner-specific section
		 *
		 * @since 1.0
		 * @access private
		 */
		private function owner_fields( $ownerPicValue = '', $ownerLinkValue = '' ) {

			return [
				[
					'label' => _x( 'Owner Pic', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'owner_pic',
					'type'  => 'single-pic-upload',
					'desc'  => _x( 'This picture/logo will appear in the sponsored team profile. You may upload .jpg, .gif or .png files.', 'Sponsoring Form', 'h3-mgmt' ),
					'value' => $ownerPicValue
				],
				[
					'label' => _x( 'Link', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'owner_link',
					'type'  => 'text',
					'desc'  => _x( 'If you submit a link, your picture/logo will link to that site.', 'Sponsoring Form', 'h3-mgmt' ),
					'value' => $ownerLinkValue,
				],
			];
		}

		/**
		 * Returns the form fields for the debit section
		 *
		 * @since 1.0
		 * @access private
		 */
		public function message_field( $scope = 'team', $messageValue = '' ) {

			$message_field = [
				[
					'label' => _x( 'Leave your team a note, if you&apos;d like', 'Sponsoring Form', 'h3-mgmt' ),
					'id'    => 'message',
					'type'  => 'textarea',
					'value' => $messageValue,
				],
			];
			if ( $scope === 'global' ) {
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

			$fcount = count( $fields );
			if ( isset( $_POST['submitted'] ) ) {
				for ( $i = 0; $i < $fcount; $i ++ ) {
					if ( isset( $_POST[ $fields[ $i ]['id'] ] ) ) {
						$fields[ $i ]['value'] = $_POST[ $fields[ $i ]['id'] ];
					}
				}
			} elseif ( isset( $_GET['id'] ) && $fields[0]['id'] === 'team_id' ) {
				$fields[0]['value'] = $_GET['id'];
			}

			return $fields;
		}

		/**
		 * Returns the donation type and method selector
		 *
		 * @param int $raceId
		 *
		 * @return string
		 * @since 1.0
		 * @access private
		 */
		private function base_selector( $raceId ) {

			global $h3_mgmt_races;

			$information_text = $h3_mgmt_races->get_race_information_text( $raceId );

			$output = '';

			$strings = [
				[
					'id'          => 'sponsor',
					'title'       => _x( 'TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
					'call'        => _x( 'Become a TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
					'image'       => 'sponsoring-type-sponsor.png',
					'description' => stripcslashes( $information_text[20] ),
					//_x( 'With one donation you can morally support your team and financially support the WASH-projects of Viva con Agua and the work of PRO ASYL. Your name will be listed as a TeamSponsor in the sponsoring list and in the team&apos;s profile (unless you opt to remain anonymous).', 'Sponsoring Form', 'h3-mgmt' )
				],
				[
					'id'          => 'owner',
					'title'       => _x( 'TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
					'call'        => _x( 'Become a TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
					'image'       => 'sponsoring-type-owner.png',
					'description' => stripcslashes( $information_text[21] ),
					//_x( 'For those who are tired of Chealsea or Hoffenheim, but still wanna play a little Abramovitch: As a TeamOwner you give a minimum of 100 Euros for Viva con Agua and PRO ASYL. As a Thank You, you may place a photo, picture or logo in the team profile which can optionally be linked to a URL of your choice.', 'Sponsoring Form', 'h3-mgmt' )
				],
			];

			// $output .= '<p><em> If you donate 100€ or more we will choose automaticly TeamOwner if still aviable.</em></p>';

			for ( $i = 0; $i <= 1; $i ++ ) {
				$output .= '<div class="overview-category toggle-wrapper">';
				// '<a href="' . get_option( 'siteurl' ) . preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI'] ) . '?type=' . $strings[$i]['id'] . '">' .
				// '<img src="' . H3_MGMT_RELPATH . 'img/' . $strings[$i]['image'] . '" ' .
				// 'alt="' . $strings[$i]['title'] . '"' .
				// 'title="' . '" class="no-bsl-adjust no-margin-bottom" />' .
				if ( $i == 1 ) {
					$output .= '<hr>';
				}
				$output .= '<div class="description">' .
				           '<h3 class="first">' . $strings[ $i ]['title'] . '</h3>' .
				           // '<label>' .  $strings[$i]['title'] . '</label>' .
				           '<p><em>' . $strings[ $i ]['description'] . '</em></p>' .
				           '<p class="no-margin-bottom"><a title="' .
				           $strings[ $i ]['call'] .
				           '" href="http://' .
				           $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '?type=' . $strings[ $i ]['id'] .
				           '">' .
				           $strings[ $i ]['call'] .
				           '</a></p>' .
				           '</div>' .
				           // '</a>' .
				           '</div>';
				if ( $i !== 1 ) {
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

			$strings = [
				[
					'id'          => 'debit',
					'title'       => _x( 'Debit (Germany only)', 'Sponsoring Form', 'h3-mgmt' ),
					'call'        => _x( 'Donate via direct debit', 'Sponsoring Form', 'h3-mgmt' ),
					'image'       => 'sponsoring-method-debit.png',
					'description' => _x( 'If you have a German bank account, you can submit your donation via direct debit. The donation will be charged by Viva con Agua e.V. directly and that within a month from now.', 'Sponsoring Form', 'h3-mgmt' ),
				],
				[
					'id'          => 'paypal',
					'title'       => _x( 'PayPal', 'Sponsoring Form', 'h3-mgmt' ),
					'call'        => _x( 'Donate via PayPal', 'Sponsoring Form', 'h3-mgmt' ),
					'image'       => 'sponsoring-method-paypal.png',
					'description' => _x( 'If you are not from Germany or would rather use PayPal, choose this method.', 'Sponsoring Form', 'h3-mgmt' ),
				],
			];

			for ( $i = 0; $i <= 1; $i ++ ) {
				$output .= '<div class="overview-category toggle-wrapper">' .
				           '<a href="' . get_option( 'siteurl' ) . $_SERVER['REQUEST_URI'] . '&method=' . $strings[ $i ]['id'] . '">' .
				           '<img src="' . H3_MGMT_RELPATH . 'img/' . $strings[ $i ]['image'] . '" ' .
				           'alt="' . $strings[ $i ]['title'] . '"' .
				           'title="' . '" class="no-bsl-adjust no-margin-bottom" />' .
				           '<div class="description">' .
				           '<h2 class="first">' . $strings[ $i ]['title'] . '</h2>' .
				           '<p><em>' . $strings[ $i ]['description'] . '</em></p>' .
				           '<p class="no-margin-bottom"><a title="' .
				           $strings[ $i ]['call'] .
				           '" href="' .
				           get_option( 'siteurl' ) . $_SERVER['REQUEST_URI'] . '&method=' . $strings[ $i ]['id'] .
				           '">' .
				           $strings[ $i ]['call'] .
				           '</a></p>' .
				           '</div>' .
				           '</a>' .
				           '</div>';
				if ( $i !== 1 ) {
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

			if ( $type === 'text' ) {
				$fields = [
					[
						'type'  => 'text',
						'id'    => 'donation',
						'label' => _x( 'Donation (in Euro)', 'Sponsoring Form', 'h3-mgmt' ),
						'value' => $_POST['donation'],
					],
				];
				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
			} else {
				if ( isset( $_POST['donation'] ) ) {
					$start_val = $_POST['thumbs'];
				} elseif ( $min <= 1 ) {
					$start_val = 1;
				} else {
					$start_val = $min;
				}
				$don_val = intval( $start_val * 10 );

				$output = '<input type="hidden" name="thumbs" id="thumbs" value="' . $start_val . '"/>' .
				          '<input type="hidden" name="donation" id="donation" value="' . $don_val . '"/>' .
				          '<input type="hidden" name="min" id="min" value="' . $min . '"/>';

				$output .= '<div class="form-row donation-selector-row">' .
				           '<div class="arrow-wrap"><span class="less-arrow horiz-arrow arrow">&lt;&lt;</span></div>' .
				           '<div class="donation-info-wrap">' .
				           '<div class="thumbs-wrap" style="display:none;">' .
				           '<span class="thumbs-text">' .
				           '<span class="thumbs">' . $start_val . '</span>' . ' ' .
				           _x( 'Thumbs', 'Sponsoring Form', 'h3-mgmt' ) .
				           '</span>' .
				           '</div>' .
				           '<span class="donation-text">' .
				           '<span class="donation">' . $don_val . '</span>' . ' Euros' .
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
		private function make_form( $args = [] ) {

			$default_args = [
				'type'     => 'sponsor',
				'method'   => 'debit',
				'messages' => [],
				'race'     => 1,
			];
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );
            if (isset($order)) { $order = $order === 'DESC' ? 'DESC' : 'ASC'; }

			global $h3_mgmt_teams;

			$output = '';

			if ( !empty( $messages ) ) {
				foreach ( $messages as $message ) {
					$output .= '<p class="' . $message['type'] . '">' . $message['message'] . '</p>';
				}
			}

			if ( $type == 'team' ) {
				$output .= '<form name="h3_mgmt_donation_form" method="post" enctype="multipart/form-data" action="">' .
				           '<input type="hidden" name="submitted" value="1"/>' .
				           '<input type="hidden" name="type" value="sponsor"/>' .
				           '<div class="form-row trap-row"><label for="address">Please leave this blank...</label>' .
				           '<input type="text" name="address" id="address" value=""></div>';

				$output .= '<h3 class="first">' . _x( 'The Team', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

				$fields = $this->add_values(
					$this->team_field(
						[
							'exclude_with_owner' => false,
							'race'               => $race,
						]
					)
				);

				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

				if ( $this->form_submittable ) {
					$output .= '<div class="form-row">' .
					           '<input type="submit" id="donation-submit-' . $method . '" name="donation-submit-' . $method . '" value="' .
					           _x( 'Next', 'Sponsoring Form', 'h3-mgmt' ) .
					           '" /></div>';
				} else {
					$output .= '<div class="form-row">' .
					           '<p><em>' .
					           _x( 'This form is currently not submittable. Possbily due to the fact that currently no team can be chosen.', 'Sponsoring Form', 'h3-mgmt' ) .
					           '</em></p></div>';
				}

				$output .= '</form>';

			} elseif ( $type == 'team_owner' ) {
				$output .= '<form name="h3_mgmt_donation_form" method="post" enctype="multipart/form-data" action="">' .
				           '<input type="hidden" name="submitted" value="1"/>' .
				           '<input type="hidden" name="type" value="owner"/>' .
				           '<div class="form-row trap-row"><label for="address">Please leave this blank...</label>' .
				           '<input type="text" name="address" id="address" value=""></div>';

				$output .= '<h3 class="first">' . _x( 'The Team', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
				$output .= '<p>' . _x( 'All shown Teams haven\'t a TeamOwner yet. Be there TeamOwner NOW!<br><br><b>Please Remember:</b> At the next Step you have to donate <b>at least 100€</b> to be a <b>TeamOwner</b> and to have the privileges to add a picture at their Team-Profile!', 'Sponsoring Form', 'h3-mgmt' ) . '</p>';

				$fields = $this->add_values(
					$this->team_field(
						[
							'exclude_with_owner' => true,
							'race'               => $race,
						]
					)
				);

				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

				if ( $this->form_submittable ) {
					$output .= '<div class="form-row">' .
					           '<input type="submit" id="donation-submit-' . $method . '" name="donation-submit-' . $method . '" value="' .
					           _x( 'Next', 'Sponsoring Form', 'h3-mgmt' ) .
					           '" /></div>';
				} else {
					$output .= '<div class="form-row">' .
					           '<p><em>' .
					           _x( 'This form is currently not submittable. Possbily due to the fact that currently no team can be chosen.', 'Sponsoring Form', 'h3-mgmt' ) .
					           '</em></p></div>';
				}

				$output .= '</form>';
			} else {
				$team_id   = $this->get_team_id( $_GET['donation_client_reference'] );
				$team_name = $h3_mgmt_teams->get_team_name( $team_id );

				if ( $type == 'owner' ) {
					$output .= '<p class="message" style="font-weight: bold;">' . str_replace( '%amount%', $_GET['amount'], str_replace( '%team_name%', $team_name, _x( 'Great you just donated %amount%€ at Betterplace. You are the Team Owner of %team_name% now! Please fill out the form below for our Homepage. The data will dislayed at the Team Profile.', 'Sponsoring Form', 'h3-mgmt' ) ) );
				} else {
					$output .= '<p class="message" style="font-weight: bold;">' . str_replace( '%amount%', $_GET['amount'], str_replace( '%team_name%', $team_name, _x( 'Great you just donated %amount%€ at Betterplace. You are a Team Sponsor of %team_name% now! Please fill out the form below for our Homepage. The data will dislayed at the Team Profile.', 'Sponsoring Form', 'h3-mgmt' ) ) );
				}
				$output .= '<form name="h3_mgmt_donation_form" method="post" enctype="multipart/form-data" action="">' .
				           '<input type="hidden" name="submitted" value="3"/>' .
				           '<input type="hidden" name="type" value="' . $type . '"/>' .
				           '<input type="hidden" name="donation_client_reference" value="' . $_GET['donation_client_reference'] . '"/>' .
				           '<div class="form-row trap-row"><label for="address">Please leave this blank...</label>' .
				           '<input type="text" name="address" id="address" value=""></div>';

				if ( $type == 'owner' ) {
					$output .= '<h3>' . _x( 'Owner Priviliges', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
					$fields = $this->add_values( $this->owner_fields() );
					require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
				}

				$output .= '<h3 class="top-space-more">' . _x( 'About you', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';

				$fields = $this->add_values( $this->donor_fields() );
				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

				$output .= '<h3 class="top-space-more">' . _x( 'The Message', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>';
				if ( $type == 'sponsor' || $type == 'owner' ) {
					$fields = $this->add_values( $this->message_field( 'team' ) );
				} else {
					$fields = $this->add_values( $this->message_field( 'global' ) );
				}
				require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

				if ( $this->form_submittable ) {
					$output .= '<div class="form-row">' .
					           '<input type="submit" id="donation-submit-' . $method . '" name="donation-submit-' . $method . '" value="' .
					           _x( 'Next', 'Sponsoring Form', 'h3-mgmt' ) .
					           '" /></div>';
				} else {
					$output .= '<div class="form-row">' .
					           '<p><em>' .
					           _x( 'This form is currently not submittable. Possbily due to the fact that currently no team can be chosen.', 'Sponsoring Form', 'h3-mgmt' ) .
					           '</em></p></div>';
				}

				$output .= '</form>';
			}

			return H3_MGMT_Security::add_nonces(H3_MGMT_Security::html($output), H3_MGMT_Security::donation_nonce((int) $race));
		}

		/**
		 * Returns the finish form
		 *
		 * @since 1.0
		 * @access private
		 */
		private function finish_form( $team_id ) {
			return '<h3 style="text-align: center;">' . _x( 'FINISH', 'Sponsoring Form', 'h3-mgmt' ) . '</h3>' .
			       '<p class="message" style="font-weight: bold;">' .
			       str_replace(
				       '%Profile%',
				       '<a title="Team Profile" href="' . get_home_url() . '/follow-us/teams/?id=' . $team_id . '">Team Profile</a>',
				       _x( 'Great you are finished now. Go to the %Profile% and see your entries!', 'Sponsoring Form', 'h3-mgmt' )
			       ) .
			       '</p>';
		}

		/*************** SPONSORING: VALIDATION & SAVING ***************/

		/**
		 * Validates a submitted donation
		 *
		 * @since 1.0
		 * @access private
		 */
		private function validate_submit() {
			$errors = [];

			$valid = true;

			if ( !empty( $_POST['address'] ) ) {
				$errors[] = [
					'type'    => 'error',
					'message' => _x( 'You have been identified as a spam bot. Screw you!', 'Form Validation Error', 'h3-mgmt' ),
				];
				$valid    = false;
			}

			if ( ($_POST['team_id'] ?? '') == 'please_select' ) {
				$errors[] = [
					'type'    => 'error',
					'message' => _x( 'Please select a Team you want to sponsor...', 'Sponsoring Form Error', 'h3-mgmt' ),
				];
				$valid    = false;
			}

            if (isset($_POST['team_id']) && $_POST['team_id'] !== 'please_select' && !H3_MGMT_Security::id($_POST['team_id'])) { $valid = false; $errors[] = ['type'=>'error', 'message'=>__('Invalid team.', 'h3-mgmt')]; }
			// if ( ! is_email( $_POST['email'] ) ) {
			// $errors[] = array(
			// 'type' => 'error',
			// 'message' => _x( "The E-Mail address you entered appears to be invalid.", 'Form Validation Error', 'h3-mgmt' )
			// );
			// $valid = false;
			// }

			// if ( $_GET['method'] != 'paypal' && ( ! isset( $_POST['debit_confirmation'] ) || $_POST['debit_confirmation'] != 1 ) ) {
			//	$errors[] = array(
			//		'type' => 'error',
			//		'message' => _x( "You have chosen to donate via direct debit. Please check the debit confirmation. Thank you!", 'Sponsoring Form Error', 'h3-mgmt' )
			//	);
			//	$valid = false;
			// }

			// if (
			// empty( $_POST['first_name'] ) ||
			// empty( $_POST['last_name'] ) ||
			// empty( $_POST['email'] )
			// ) {
			// $errors[] = array(
			// 'type' => 'error',
			// 'message' => _x( "You have not filled out all required fields.", 'Sponsoring Form Error', 'h3-mgmt' )
			// );
			// $valid = false;
			// }

			return [ $valid, $errors ];
		}

		/**
		 * Saves a donation
		 *
		 * @since 1.0
		 * @access private
		 */
        private function save_donation( $race_id = 1, $status = 0 ) {
            // Retired browser-driven writer. New donations use the verified ledger.
            return H3_MGMT_Security::error();
        }

		/**
		 * Returns client_id from betterplace redirect link saved in Backend
		 *
		 * @param int $race_id
		 *
		 * @return string $client_id
		 *
		 * @since 1.1
		 * @access public
		 */
		public function get_client_id( $race_id ) {
            $race_id = H3_MGMT_Security::id($race_id);
			global $wpdb, $h3_mgmt_races;

			$race_settings = $h3_mgmt_races->get_race_setting( $race_id );

			$client_id = substr( $race_settings['betterplace_redirect_link'], strpos( $race_settings['betterplace_redirect_link'], 'client_id=' ) + strlen( 'client_id=' ) );

			return $client_id;
		}

		/**
		 * Check the validaten of  a donation via the Betterplace API
		 *
		 * @since 1.0
		 * @access private
		 */
        private function donation_check( $donation_token, $donation_client_reference, $client_id ) {
            // The retired browser callback has no verified provider contract.
            return false;
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
			global $wpdb, $h3_mgmt_teams, $h3_mgmt_races;

			extract(
				shortcode_atts(
					[
						'number' => 3,
						'race'   => 'all',
					], $atts
				)
			);

			if ( $race == 'active' ) {
				$race = $h3_mgmt_races->get_active_race();
			}

			$where = '';
			if ( is_numeric( $race ) ) {
				$where = 'WHERE race_id = ' . $race . ' and paid = 1 and var_show = 1 ';
			}

			$sponsors_query = $wpdb->get_results(
				'SELECT * FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				$where .
				'ORDER BY id DESC',
				ARRAY_A
			);

            $sponsors_query = \Cor\Mgmt\WordPress\DonationFrontend::overlay($sponsors_query);
			$race_setting = $h3_mgmt_races->get_race_setting( $race );

			$output = '';
			if ( !empty( $sponsors_query ) ) {
				$output .= '<p>';
				$number = $number <= count( $sponsors_query ) ? $number : count( $sponsors_query );
				for ( $i = 0; $i < $number; $i ++ ) {
					$thumbs = $sponsors_query[ $i ]['donation'];
					if ( empty( $sponsors_query[ $i ]['display_name'] ) ) {
						$sponsors_query[ $i ]['display_name'] = _x( 'Anonymous Sponsor', 'Sponsoring', 'h3-mgmt' );
					}
					if ( $i !== ( $number - 1 ) ) {
						$output .= '<div class="item activity-stream bottom-border"><p>';
					} else {
						$output .= '<div class="item activity-stream"><p>';
					}
					$output .= str_replace( '%donor%',
						'<em>' . esc_html(stripslashes( $sponsors_query[ $i ]['display_name'] )) . '</em>',
						str_replace( '%thumbs%',
							$thumbs,
							str_replace( '%team%',
								'<a class="cursive-link" title="' . _x( 'Check the TeamProfile ...', 'Team', 'h3-mgmt' ) . '" href="' . get_home_url() . $race_setting['team_overview_link'] . '?id=' . $sponsors_query[ $i ]['team_id'] . '">' . $h3_mgmt_teams->get_team_name( $sponsors_query[ $i ]['team_id'] ) . '</a>',
								_x( '%donor% sponsored %team% with %thumbs% Euros', 'Recent Activity Stream', 'h3-mgmt' ) ) ) );
					$output .= '</p></div>';
				}
				$output .= '</p>';
			} else {
				$output .= '<p>' . __( 'No donors yet...', 'h3-mgmt' ) . '</p>';
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
			global $h3_mgmt_races;

			extract(
				shortcode_atts(
					[
						'type'      => 'sponsor',
						'delimiter' => ', ',
						'race'      => 'all',
					], $atts
				)
			);

			if ( $race == 'active' ) {
				$race = $h3_mgmt_races->get_active_race();
			}

			$sponsors = $this->list_sponsors(
				[
					'type'      => $type,
					'team_id'   => 'all',
					'race'      => $race,
					'delimiter' => $delimiter,
				]
			);

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
		public function get_sponsor_language( $sponsor_id = null ) {
			global $wpdb;

			if ( !is_numeric( $sponsor_id ) ) {
				return 'en';
			}

			$language_query = $wpdb->get_results(
				'SELECT language FROM ' .
				$wpdb->prefix . 'h3_mgmt_sponsors ' .
				'WHERE id = ' . $sponsor_id . ' LIMIT 1',
				ARRAY_A
			);

			$language = isset( $language_query[0]['language'] ) ? $language_query[0]['language'] : 'en';

			return $language;
		}

		/**
		 * Handels the redirect from Betterplace, gets the setting in the event
		 * for the right reirect to the sponsor page
		 *
		 * @return redirect to URL in setting
		 *
		 * @since 1.0
		 * @access public
		 */
        public function handle_betterplace_redirect() {
            return '<p>' . esc_html__('A browser redirect does not confirm payment. Return to the donation form and use Done to check the assignment.', 'h3-mgmt') . '</p>';
        }

		/**
		 * PHP5 style constructor
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {
			// generate a unique token and set a cookie (expires after 3 days)
			if ( empty( $_COOKIE['client_token'] ) ) {
				// length: 64 chars
				$token = bin2hex( \Cor\Mgmt\WordPress\Services::get()->random->bytes(32) );
				setcookie('client_token', $token, ['expires'=>time() + 3 * DAY_IN_SECONDS, 'path'=>'/', 'secure'=>is_ssl(), 'httponly'=>true, 'samesite'=>'Lax']);
                $_COOKIE['client_token'] = $token;
				// $_COOKIE['client_token'] = $token;
			}

			add_shortcode( 'h3-sponsoring-form', [ &$this, 'new_sponsoring_handler' ] );
			add_shortcode( 'h3-recent-sponsors', [ &$this, 'recent_sponsors' ] );
			add_shortcode( 'h3-list-sponsors', [ &$this, 'sponsors_overview' ] );
			add_shortcode( 'h3-handle-betterplace-redirect', [ &$this, 'handle_betterplace_redirect' ] );
		}

	} // class

endif; // class exists


