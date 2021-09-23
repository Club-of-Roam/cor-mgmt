<?php

/**
 * H3_MGMT_Admin_Teams class.
 *
 * This class contains properties and methods for
 * the team related backend functionality
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Teams' ) ) :

	class H3_MGMT_Admin_Teams {

		/**
		 * Teams administration menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function teams_control() {
			global $wpdb, $h3_mgmt_teams, $h3_mgmt_races;
			/** @var H3_MGMT_Teams $h3_mgmt_teams */

			$messages = array();

			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : '';

			switch ( $todo ) {

				case 'delete':
					if ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {
						$wpdb->query(
							'DELETE FROM ' .
							$wpdb->prefix . 'h3_mgmt_teams ' .
							"WHERE id = '" . $_GET['id'] . "' LIMIT 1"
						);
						$wpdb->query(
							'DELETE FROM ' .
							$wpdb->prefix . 'h3_mgmt_teammates ' .
							"WHERE team_id = '" . $_GET['id'] . "'"
						);
						$wpdb->query(
							'DELETE FROM ' .
							$wpdb->prefix . 'h3_mgmt_invitations ' .
							"WHERE team_id = '" . $_GET['id'] . "'"
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected team has been successfully deleted.', 'h3-mgmt' ),
						);
					}
					unset( $_GET['todo'], $_GET['id'] );
					$this->teams_list( $messages );
					break;

				case 'save':
					if ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) && isset( $_POST['team_name'] ) ) {
									//update Teaminformations
									/* Handle image upload */
						if ( ! empty( $_FILES['team_pic']['name'] ) ) {
								$team_pic_data           = wp_upload_bits(
									$_FILES['team_pic']['name'],
									null,
									file_get_contents( $_FILES['team_pic']['tmp_name'] )
								);
								$team_insert['team_pic'] = $team_pic_data['url'];
								$team_data_types[]       = '%s';
						} else {
							$team_insert['team_pic'] = $_POST['team_pic-tmp'];
						}

						$test = $wpdb->update(
							$wpdb->prefix . 'h3_mgmt_teams',
							array(
								'team_name'   => $_POST['team_name'],
								'description' => $_POST['description'],
								'team_pic'    => $team_insert['team_pic'],
								'team_phone'  => $_POST['team_phone'],
								'route_id'    => $_POST['route_id'],
								'race_id'     => $_POST['race_id'],
							),
							array( 'id' => intval( $_GET['id'] ) ),
							array( '%s', '%s', '%s', '%s', '%d', '%d' ),
							array( '%d' )
						);

						$race_setting   = $h3_mgmt_races->get_race_setting( $h3_mgmt_teams->get_team_race( $_GET['id'] ) );
						$num_teammember = intval( $race_setting['num_teammember'] );
						//update or insert team member
						//get old and new Teammate IDs

									$new_members = array();
						for ( $i = 1; $i <= $num_teammember; $i++ ) {
							$new_members[] = $_POST[ 'member' . $i ];
						}

									$old_members = $h3_mgmt_teams->get_teammates( $_GET['id'], $exclude_current = false );

									//save or update in teammate section compared to different changes
									$x = 0;
						foreach ( $new_members as $new_member ) {
							if ( $new_member != $old_members[ $x ] ) {
								if ( empty( $new_member ) && ! empty( $old_members[ $x ] ) ) {
									$wpdb->query(
										'DELETE FROM ' .
										$wpdb->prefix . 'h3_mgmt_teammates ' .
										'WHERE team_id = ' . $_GET['id'] . ' AND user_id = ' . $old_members[ $x ]
									);
								} elseif ( ! empty( $new_member ) && ! empty( $old_members[ $x ] ) ) {
									$id = $wpdb->get_results(
										'SELECT id FROM ' .
											$wpdb->prefix . 'h3_mgmt_teammates ' .
											'WHERE team_id = ' . $_GET['id'] . ' and user_id = ' . $old_members[ $x ],
										ARRAY_A
									);
									$id = $id[0]['id'];
									$wpdb->update(
										$wpdb->prefix . 'h3_mgmt_teammates',
										array(
											'user_id' => $new_member,
											'paid'    => 0,
											'waiver'  => 0,
										),
										array( 'id' => $id ),
										array( '%d', '%d', '%d', '%s' ),
										array( '%d' )
									);
								} elseif ( ! empty( $new_member ) && empty( $old_members[ $x ] ) ) {
										$wpdb->insert(
											$wpdb->prefix . 'h3_mgmt_teammates',
											array(
												'user_id' => $new_member,
												'team_id' => $_GET['id'],
												'paid'    => 0,
												'waiver'  => 0,
											),
											array( '%d', '%d', '%d', '%d', '%s' )
										);
								}
							}
								$x = $x + 1;

								//update language in teammate section
							if ( ! empty( $new_member ) ) {
											$language_value_name = 'member' . $x . '_language';
											$id                  = $wpdb->get_results(
												'SELECT id FROM ' .
													$wpdb->prefix . 'h3_mgmt_teammates ' .
													'WHERE team_id = ' . $_GET['id'] . ' and user_id = ' . $new_member,
												ARRAY_A
											);
											$id                  = $id[0]['id'];
											$wpdb->update(
												$wpdb->prefix . 'h3_mgmt_teammates',
												array(
													'language' => $_POST[ $language_value_name ],
												),
												array( 'id' => $id ),
												array( '%s' ),
												array( '%d' )
											);
							}
						}
									$h3_mgmt_teams->is_complete( $_GET['id'] );
									$messages[] = array(
										'type'    => 'message',
										'message' => __( 'Team successfully updated!', 'h3-mgmt' ),
									);
					} else {
						/* Handle image upload */
						if ( ! empty( $_FILES['team_pic']['name'] ) ) {
								$team_pic_data           = wp_upload_bits(
									$_FILES['team_pic']['name'],
									null,
									file_get_contents( $_FILES['team_pic']['tmp_name'] )
								);
								$team_insert['team_pic'] = $team_pic_data['url'];
								$team_data_types[]       = '%s';
						} else {
							$team_insert['team_pic'] = $_POST['team_pic-tmp'];
						}

						if ( empty( $team_insert['team_pic'] ) )
						{
							$team_insert['team_pic'] = '';
						}
						
						$wpdb->insert(
							$wpdb->prefix . 'h3_mgmt_teams',
							array(
								'race_id'     => $_POST['race_id'],
								'team_name'   => $_POST['team_name'],
								'description' => $_POST['description'],
								'team_pic'    => $team_insert['team_pic'],
								'team_phone'  => $_POST['team_phone'],
								'route_id'    => $_POST['route_id'],
							),
							array( '%d', '%s', '%s', '%s', '%s', '%d' )
						);
										$team_id = $wpdb->insert_id;
						//update or insert team member
						//get old and new Teammate IDs
						$new_members = array( $_POST['member1'], $_POST['member2'], $_POST['member3'] );
						$old_members = $h3_mgmt_teams->get_teammates( $team_id, $exclude_current = false );

						//save or update in teammate section compared to different changes
						$x = 0;
						foreach ( $new_members as $new_member ) {
							if ( $new_member != $old_members[ $x ] ) {
								if ( empty( $new_member ) && ! empty( $old_members[ $x ] ) ) {
									$wpdb->query(
										'DELETE FROM ' .
																				$wpdb->prefix . 'h3_mgmt_teammates ' .
																				'WHERE team_id = ' . $team_id . ' AND user_id = ' . $old_members[ $x ]
									);
								} elseif ( ! empty( $new_member ) && ! empty( $old_members[ $x ] ) ) {
									$id = $wpdb->get_results(
										'SELECT id FROM ' .
																			$wpdb->prefix . 'h3_mgmt_teammates ' .
																			'WHERE team_id = ' . $team_id . ' and user_id = ' . $old_members[ $x ],
										ARRAY_A
									);
									$id = $id[0]['id'];
									$wpdb->update(
										$wpdb->prefix . 'h3_mgmt_teammates',
										array(
											'user_id' => $new_member,
											'paid'    => 0,
											'waiver'  => 0,
										),
										array( 'id' => $id ),
										array( '%d', '%d', '%d', '%s' ),
										array( '%d' )
									);
								} elseif ( ! empty( $new_member ) && empty( $old_members[ $x ] ) ) {
									$wpdb->insert(
										$wpdb->prefix . 'h3_mgmt_teammates',
										array(
											'user_id' => $new_member,
											'team_id' => $team_id,
											'paid'    => 0,
											'waiver'  => 0,
										),
										array( '%d', '%d', '%d', '%d', '%s' )
									);
								}
							}
							$x = $x + 1;

							//update language in teammate section
							if ( ! empty( $new_member ) ) {
								$language_value_name = 'member' . $x . '_language';
								$id                  = $wpdb->get_results(
									'SELECT id FROM ' .
																$wpdb->prefix . 'h3_mgmt_teammates ' .
																'WHERE team_id = ' . $team_id . ' and user_id = ' . $new_member,
									ARRAY_A
								);
								$id                  = $id[0]['id'];
								$wpdb->update(
									$wpdb->prefix . 'h3_mgmt_teammates',
									array(
										'language' => $_POST[ $language_value_name ],
									),
									array( 'id' => $id ),
									array( '%s' ),
									array( '%d' )
								);
							}
						}
										$h3_mgmt_teams->is_complete( $team_id );
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'Team successfully added!', 'h3-mgmt' ),
						);
					}
					$this->teams_list( $messages );
					break;

				case 'edit':
					$this->teams_edit( $_GET['id'], $_POST['race_id'] );
					break;

				default:
					$this->teams_list( $messages );
			}
		}

		/**
		 * Participants administration menu
		 * (set hitchpackage payments & waiver form receipts)
		 *
		 * @since 1.0
		 * @access public
		 */
		public function participants_control() {
			global $wpdb, $h3_mgmt_mailer, $h3_mgmt_teams;

			$messages = array();

			if ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {

				$mate_query = $wpdb->get_results(
					'SELECT * FROM ' .
					$wpdb->prefix . 'h3_mgmt_teammates ' .
					'WHERE id = ' . $_GET['id'] . ' LIMIT 1',
					ARRAY_A
				);

				$user_id = isset( $mate_query[0]['user_id'] ) ? $mate_query[0]['user_id'] : null;
				$team_id = isset( $mate_query[0]['team_id'] ) ? $mate_query[0]['team_id'] : null;

				$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : '';

				switch ( $todo ) {

					case 'paid':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_teammates',
							array( 'paid' => 1 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[]    = array(
							'type'    => 'message',
							'message' => __( 'The selected participant&apos;s HitchPackage has been paid.', 'h3-mgmt' ),
						);
						$response_args = array(
							'team_name' => $h3_mgmt_teams->get_team_name( $team_id ),
						);
						$h3_mgmt_mailer->auto_response( $user_id, 'package-paid', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
						$h3_mgmt_teams->is_complete( $team_id );
						unset( $_GET['todo'], $_GET['id'] );
						$this->participants_list( $messages );
						break;

					case 'waiver-set':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_teammates',
							array( 'waiver' => 1 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[]    = array(
							'type'    => 'message',
							'message' => __( 'The selected participant&apos;s waiver has been set to received.', 'h3-mgmt' ),
						);
						$response_args = array(
							'team_name' => $h3_mgmt_teams->get_team_name( $team_id ),
						);
						$h3_mgmt_mailer->auto_response( $user_id, 'waiver-reached', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
						$h3_mgmt_teams->is_complete( $team_id );
						unset( $_GET['todo'], $_GET['id'] );
						$this->participants_list( $messages );
						break;

					case 'unpaid':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_teammates',
							array( 'paid' => 0 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participant&apos;s HitchPackage was not yet paid, after all.', 'h3-mgmt' ),
						);
						$h3_mgmt_teams->is_complete( $team_id );
						unset( $_GET['todo'], $_GET['id'] );
						$this->participants_list( $messages );
						break;

					case 'waiver-unset':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_teammates',
							array( 'waiver' => 0 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participant&apos;s waiver has not yet reached, after all.', 'h3-mgmt' ),
						);
						$h3_mgmt_teams->is_complete( $team_id );
						unset( $_GET['todo'], $_GET['id'] );
						$this->participants_list( $messages );
						break;

					default:
						$this->participants_list( $messages );
				}
			} elseif ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : $this->participants_list( $messages );

				foreach ( $_GET['bulk'] as $id ) {
					$mate_query = $wpdb->get_results(
						'SELECT * FROM ' .
						$wpdb->prefix . 'h3_mgmt_teammates ' .
						'WHERE id = ' . $id . ' LIMIT 1',
						ARRAY_A
					);

					$user_id = isset( $mate_query[0]['user_id'] ) ? $mate_query[0]['user_id'] : null;
					$team_id = isset( $mate_query[0]['team_id'] ) ? $mate_query[0]['team_id'] : null;

					switch ( $todo ) {
						case 'bulk-paid':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_teammates',
								array( 'paid' => 1 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							$response_args = array(
								'team_name' => $h3_mgmt_teams->get_team_name( $team_id ),
							);
							$h3_mgmt_mailer->auto_response( $user_id, 'package-paid', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
							$h3_mgmt_teams->is_complete( $team_id );
							break;

						case 'bulk-waiver-set':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_teammates',
								array( 'waiver' => 1 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							$response_args = array(
								'team_name' => $h3_mgmt_teams->get_team_name( $team_id ),
							);
							$h3_mgmt_mailer->auto_response( $user_id, 'waiver-reached', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
							$h3_mgmt_teams->is_complete( $team_id );
							break;

						case 'bulk-unpaid':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_teammates',
								array( 'paid' => 0 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							$h3_mgmt_teams->is_complete( $team_id );
							break;

						case 'bulk-waiver-unset':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_teammates',
								array( 'waiver' => 0 ),
								array( 'id' => $id ), //$_GET['id']
								array( '%d' ),
								array( '%d' )
							);
								$h3_mgmt_teams->is_complete( $team_id );
							break;

						default:
							$this->participants_list( $messages );
					}
				}
				switch ( $todo ) {
					case 'bulk-paid':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participants HitchPackages have been paid.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-waiver-set':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participants waivers have been set to received.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-unpaid':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participants HitchPackagea were not yet paid, after all.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-waiver-unset':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected participants waivers have not yet reached, after all.', 'h3-mgmt' ),
						);
						break;

					default:
						$this->participants_list( $messages );
				}
				unset( $_GET['todo'], $_GET['bulk'] );
				$this->participants_list( $messages );
			} else {
				$this->participants_list( $messages );
			}
		}

		/**
		 * List all teams
		 *
		 * @since 1.0
		 * @access private
		 */
		private function teams_list( $messages = array() ) {
			global $wpdb, $h3_mgmt_teams, $h3_mgmt_races;

			$url = 'admin.php?page=h3-mgmt-teams';

			$columns = array(
				array(
					'id'       => 'team_name',
					'title'    => __( 'Team Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
					'actions'  => array( 'edit', 'delete' ),
					'cap'      => 'team',
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Event / Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'route',
					'title'    => __( 'Route', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'mates',
					'title'      => __( 'HitchMates', 'h3-mgmt' ),
					'sortable'   => false,
					'conversion' => 'mates',
				),
				array(
					'id'         => 'complete',
					'title'      => __( 'Complete?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'boolean',
				),
			);

			$team_args = array(
				'orderby'            => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'team_name',
				'order'              => isset( $_GET['order'] ) ? $_GET['order'] : 'ASC',
				'exclude_incomplete' => false,
				'extra_fields'       => array( 'mates', 'route', 'race' ),
			);
			list( $team_count, $complete_count, $incomplete_count, $rows ) = $h3_mgmt_teams->get_teams_meta( $team_args );

			$page_args = array(
				'echo'     => true,
				'icon'     => 'icon-teams',
				'title'    => __( 'Teams', 'h3-mgmt' ),
				'url'      => $url,
				'messages' => $messages,
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			$filter            = array( 'race', 'route', 'complete' );
			$filter_dis_name   = array( 'Event/Race', 'Route', 'Complete?' );
			$filter_conversion = array( '', '', 'boolean' );

			$active_race = $h3_mgmt_races->get_active_race();

			if ( isset( $active_race ) ) {
				$race_name = $wpdb->get_results(
					'SELECT name FROM ' . $wpdb->prefix . 'h3_mgmt_races ' .
					'WHERE id = ' . $active_race, ARRAY_A
				);
			}

			$race_name     = isset( $race_name[0]['name'] ) ? $race_name[0]['name'] : null;

			$pre_filtered = array( true, 'race', $race_name );

			$tbl_args  = array(
				'orderby'            => 'team_name',
				'page_slug'          => 'h3-mgmt-teams',
				'base_url'           => $url,
				'sort_url'           => $url,
				'pagination_url'     => $url,
				'icon'               => 'icon-teams',
				'headline'           => '',
				'headspace'          => false,
				'show_empty_message' => true,
				'empty_message'      => '',
				'pagination'         => false,
				'total_pages'        => 1,
				'current_page'       => 1,
				'dspl_cnt'           => true,
				'count'              => count( $rows ),
				'cnt_txt'            => '%d ' . __( 'Teams', 'h3-mgmt' ),
				'with_bulk'          => false,
				'bulk_btn'           => 'Execute',
				'bulk_confirm'       => '',
				'bulk_name'          => 'bulk',
				'bulk_param'         => 'todo',
				'bulk_desc'          => '',
				'extra_bulk_html'    => '',
				'bulk_actions'       => array(),
				'filter'             => $filter,
				'filter_dis_name'    => $filter_dis_name,
				'filter_conversion'  => $filter_conversion,
				'pre_filtered'       => $pre_filtered,
			);
			$the_table = new H3_MGMT_Admin_Table( $tbl_args, $columns, $rows );

			$button = '<form method="post" action="' . $url . '&amp;todo=edit">' .
					'<input type="submit" class="button-secondary" value="+ ' . __( 'add team', 'h3-mgmt' ) . '" />' .
					'</form>';

			$the_page->top();
			echo $button . '<br />';
			$the_table->output();
			echo $button . '<br />';
			$the_page->bottom();
		}

		/**
		 * Edit a team
		 *
		 * @since 1.0
		 * @access private
		 */
		private function teams_edit( $team_id = null, $race_id = null ) {
			global $h3_mgmt_races, $h3_mgmt_teams, $information_text;

			$route_id = isset( $team['route_id'] ) ? $team['route_id'] : 0;
			if ( $team_id == null && $race_id == null ) {
					$race_id          = $h3_mgmt_races->get_active_race();
					$information_text = $h3_mgmt_races->get_race_information_text( $race_id );
					$race_setting     = $h3_mgmt_races->get_race_setting( $race_id );

					$status = 'just_race';

			} elseif ( $race_id != null && $team_id == null ) {
				$information_text = $h3_mgmt_races->get_race_information_text( $race_id );
				$race_setting     = $h3_mgmt_races->get_race_setting( $race_id );

			} elseif ( $h3_mgmt_teams->team_exists( $team_id ) ) {
					$team             = $h3_mgmt_teams->get_team_data( $team_id, array( 'mates', 'route' ) );
					$information_text = $h3_mgmt_races->get_race_information_text( $h3_mgmt_teams->get_team_race( $team_id ) );
					$race_id          = $h3_mgmt_teams->get_team_race( $team_id );
					$race_setting     = $h3_mgmt_races->get_race_setting( $race_id );
			}

			if ( $status == 'just_race' ) {

				$race_mb = array(
					'title' => __( 'Race', 'h3-mgmt' ),
				);

				$race_mb['fields'][] = array(
					'id'      => 'race_id',
					'type'    => 'select',
					'label'   => __( 'Event / Race', 'h3-mgmt' ),
					'options' => $h3_mgmt_races->options_array(
						array(
							'data'    => 'race',
							'orderby' => 'start',
							'order'   => 'DESC',
							'value'   => 'id',
							'label'   => 'name',
						)
					),
				);

				$fields   = array();
				$fields[] = $race_mb;

			} else {

				$race_mb = array(
					'title'  => __( 'Race', 'h3-mgmt' ),
					'fields' => $h3_mgmt_teams->route_field( $h3_mgmt_teams->get_team_race( $team_id ), intval( $route_id ) ),
				);

				$race_mb['fields'][] = array(
					'id'    => 'race_id',
					'type'  => 'hidden',
					'value' => $race_id,
				);

				$race_mb['fields'][] = array(
					'id'    => 'team_phone',
					'type'  => 'text',
					'label' => __( 'Phones (for Ticker)', 'h3-mgmt' ),
					'desc'  => _x( 'More than 1 Phone Number separated by space bar!!!', 'h3-mgmt' ),
				);

				$fields     = array();
				$member_ids = array();
				$user_ids   = array();
				$fields[]   = $race_mb;

				$member_ids     = $h3_mgmt_teams->get_teammates( $team_id, $exclude_current = false );
				$user_ids       = $h3_mgmt_teams->get_user_ids_without_team( $race_id );
				$num_teammember = intval( $race_setting['num_teammember'] );

				if ( $member_ids != null || $user_ids != null ) {
					$user_member_ids            = array_merge( $member_ids, $user_ids );
					$user_member_nicnames_class = get_users(
						array(
							'fields'  => array( 'user_nicename', 'ID' ),
							'include' => $user_member_ids,
							'orderby' => 'user_nicename',
							'order'   => 'ASC',
						)
					);

					$user_member_nicnames[0]['value'] = 0;
					$user_member_nicnames[0]['label'] = '-----';

					$x = 1;
					foreach ( $user_member_nicnames_class as $user_member_nicname ) {
							$user_member_nicnames[ $x ]['value'] = $user_member_nicname->ID;
							$user_member_nicnames[ $x ]['label'] = $user_member_nicname->user_nicename;
							$x                                   = $x + 1;
					}
				} else {
					$user_member_nicnames[0]['value'] = 0;
					$user_member_nicnames[0]['label'] = '-----';
				}

				$team_member_fields = array(
					'title' => __( 'Team Member', 'h3-mgmt' ),
				);
				if ( $num_teammember == 0 || ! isset( $num_teammember ) ) {
					$num_teammember = 3;
				}
				for ( $i = 1; $i <= $num_teammember; $i++ ) {
					$team_member_fields['fields'][] = array(
						'id'      => 'member' . $i,
						'type'    => 'select',
						'label'   => __( 'Member ', 'h3-mgmt' ) . $i,
						'desc'    => _x( 'Please watch out that you don\'t choose a participant twice!!!', 'h3-mgmt' ),
						'options' => $user_member_nicnames,
					);
					$team_member_fields['fields'][] = array(
						'id'      => 'member' . $i . '_language',
						'type'    => 'radio',
						'label'   => __( 'Language Member ', 'h3-mgmt' ) . $i,
						'desc'    => _x( 'Choose the language for the E-Mails of the participant.', 'h3-mgmt' ),
						'options' => array(
							array(
								'value' => 'en',
								'label' => 'english',
							),
							array(
								'value' => 'de',
								'label' => 'german',
							),
						),
					);
				}

				$fields[] = $team_member_fields;

				$fields_buff = $h3_mgmt_teams->team_fields( true, $race_id );
				$fields[]    = $fields_buff[0];
			}

			$mcount = count( $fields );
			for ( $i = 0; $i < $mcount; $i++ ) {
				$fcount = count( $fields[ $i ]['fields'] );
				for ( $j = 0; $j < $fcount; $j++ ) {
					if ( isset( $_POST['submitted'] ) ) {
						if ( $fields[ $i ]['fields'][ $j ]['id'] == 'team_pic' ) {
							$fields[ $i ]['fields'][ $j ]['value'] = stripslashes( $team[ $fields[ $i ]['fields'][ $j ]['id'] ] );
						} else {
							$fields[ $i ]['fields'][ $j ]['value'] = stripslashes( $_POST[ $fields[ $i ]['fields'][ $j ]['id'] ] );
						}
					} elseif ( $h3_mgmt_teams->team_exists( $team_id ) ) {

						$fields[ $i ]['fields'][ $j ]['value'] = stripslashes( $team[ $fields[ $i ]['fields'][ $j ]['id'] ] );

						for ( $x = 1; $x <= $num_teammember; $x++ ) {
							if ( $fields[ $i ]['fields'][ $j ]['id'] == 'member' . $x ) {
								$fields[ $i ]['fields'][ $j ]['value'] = $member_ids[ ( $x - 1 ) ];
							} elseif ( $fields[ $i ]['fields'][ $j ]['id'] == 'member' . $x . '_language' ) {
								$fields[ $i ]['fields'][ $j ]['value'] = empty( $member_ids[ ( $x - 1 ) ] ) ? 'en' : $h3_mgmt_teams->get_participant_language( $member_ids[0] );
							}
						}
					} else {
						if ( $fields[ $i ]['fields'][ $j ]['id'] == 'race_id' ) {
							$fields[ $i ]['fields'][ $j ]['value'] = $race_id;
						}

						for ( $x = 1; $x <= $num_teammember; $x++ ) {
							if ( $fields[ $i ]['fields'][ $j ]['id'] == 'member' . $x . '_language' ) {
								$fields[ $i ]['fields'][ $j ]['value'] = 'en';
							}
						}
					}
				}
			}

			$url = 'admin.php?page=h3-mgmt-teams';
			if ( $status == 'just_race' ) {
				$form_action = $url . '&amp;todo=edit';
			} else {
				$form_action = $url . '&amp;todo=save&amp;id=' . $team_id;
			}

			if ( $status == 'just_race' ) {
					$title = __( 'Add New Team | Choose Race: ', 'h3-mgmt' );
			} elseif ( $team_id === null ) {
					$title = __( 'Add New Team for Race: ', 'h3-mgmt' ) . $h3_mgmt_races->get_name( $race_id, $type = 'race' );
			} else {
					$title = sprintf( __( 'Edit "%s" in Race: ', 'h3-mgmt' ), stripslashes( $team['team_name'] ) ) . $h3_mgmt_races->get_name( $race_id, $type = 'race' );
			}

			$page_args = array(
				'echo'     => true,
				'icon'     => 'icon-teams',
				'title'    => $title,
				'url'      => $url,
				'messages' => array(),
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			if ( $status == 'just_race' ) {
				$form_args = array(
					'echo'       => true,
					'form'       => true,
					'method'     => 'post',
					'metaboxes'  => true,
					'js'         => false,
					'url'        => $url,
					'action'     => $form_action,
					'id'         => $team_id,
					'button'     => __( 'Next', 'h3-mgmt' ),
					'top_button' => true,
					'back'       => true,
					'back_url'   => $url,
					'fields'     => $fields,
				);
			} else {
				$form_args = array(
					'echo'       => true,
					'form'       => true,
					'method'     => 'post',
					'metaboxes'  => true,
					'js'         => false,
					'url'        => $url,
					'action'     => $form_action,
					'id'         => $team_id,
					'button'     => __( 'Save Team', 'h3-mgmt' ),
					'top_button' => true,
					'back'       => true,
					'back_url'   => $url,
					'fields'     => $fields,
				);
			}

			$the_form = new H3_MGMT_Admin_Form( $form_args );

			$the_page->top();
			$the_form->output();
			$the_page->bottom();
		}

		/**
		 * List all participants
		 *
		 * @since 1.0
		 * @access private
		 */
		private function participants_list( $messages = array() ) {
			global $h3_mgmt_teams, $wpdb, $h3_mgmt_races;

			$url = 'admin.php?page=h3-mgmt-participants';

			$columns = array(
				array(
					'id'       => 'first_name',
					'title'    => __( 'First Name', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'last_name',
					'title'    => __( 'Last Name', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'email',
					'title'    => __( 'Email', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'city',
					'title'    => __( 'City', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'mobile',
					'title'    => __( 'Phone', 'h3-mgmt' ),
					'sortable' => false,
				),
				array(
					'id'       => 'team',
					'title'    => __( 'Team', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'shirt',
					'title'    => __( 'Shirt', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'InfMobile',
					'title'    => __( 'Inf. for Mobile', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Event / Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'waiver',
					'title'      => __( 'Waiver received?', 'h3-mgmt' ),
					'sortable'   => true,
					'strong'     => true,
					'conversion' => 'boolean',
					'actions'    => array( 'waiver' ),
					'cap'        => 'team',
				),
				array(
					'id'         => 'paid',
					'title'      => __( 'Package paid?', 'h3-mgmt' ),
					'sortable'   => true,
					'strong'     => true,
					'conversion' => 'boolean',
					'actions'    => array( 'package' ),
					'cap'        => 'team',
				),
			);

			$parts_args = array(
				'orderby'            => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'first_name',
				'order'              => isset( $_GET['order'] ) ? $_GET['order'] : 'ASC',
				'exclude_incomplete' => false,
				'extra_fields'       => array( 'first_name', 'last_name', 'email', 'city', 'mobile', 'team', 'race', 'shirt', 'InfMobile' ),
			);
			list( $participants_count, $participants_complete_count, $participants_incomplete_count, $rows ) = $h3_mgmt_teams->get_participants_meta( $parts_args );

			$page_args = array(
				'echo'     => true,
				'icon'     => 'icon-participants',
				'title'    => __( 'Participants', 'h3-mgmt' ),
				'url'      => $url,
				'messages' => $messages,
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			$filter            = array( 'team', 'shirt', 'InfMobile', 'race', 'waiver', 'paid' );
			$filter_dis_name   = array( 'Team', 'Shirt', 'Inf. for Mobile', 'Event/Race', 'Waiver received?', 'Package paid?' );
			$filter_conversion = array( '', '', '', '', 'boolean', 'boolean' );

			$active_race = $h3_mgmt_races->get_active_race();

			if ( isset( $active_race ) ) {
				$race_name = $wpdb->get_results(
					'SELECT name FROM ' . $wpdb->prefix . 'h3_mgmt_races ' .
					'WHERE id = ' . $active_race, ARRAY_A
				);
			}

			$race_name = isset( $race_name[0]['name'] ) ? $race_name[0]['name'] : null;

			$pre_filtered = array( true, 'race', $race_name );

			$bulk_actions = array(
				array(
					'value' => 'bulk-waiver-set',
					'label' => 'Waiver received!',
				),
				array(
					'value' => 'bulk-waiver-unset',
					'label' => 'Waiver not received!',
				),
				array(
					'value' => 'bulk-paid',
					'label' => 'HitchPackage paid!',
				),
				array(
					'value' => 'bulk-unpaid',
					'label' => 'Not yet paid...',
				),
			);

			$tbl_args  = array(
				'orderby'            => 'team',
				'page_slug'          => 'h3-mgmt-participants',
				'base_url'           => $url,
				'sort_url'           => $url,
				'pagination_url'     => $url,
				'icon'               => 'icon-participants',
				'headline'           => '',
				'headspace'          => false,
				'show_empty_message' => true,
				'empty_message'      => '',
				'pagination'         => false,
				'total_pages'        => 10,
				'current_page'       => 1,
				'dspl_cnt'           => true,
				'count'              => count( $rows ),
				'cnt_txt'            => '%d ' . __( 'Participants', 'h3-mgmt' ),
				'with_bulk'          => true,
				'bulk_btn'           => 'Execute',
				'bulk_confirm'       => '',
				'bulk_name'          => 'bulk',
				'bulk_param'         => 'todo',
				'bulk_desc'          => '',
				'extra_bulk_html'    => '',
				'bulk_actions'       => $bulk_actions,
				'filter'             => $filter,
				'filter_dis_name'    => $filter_dis_name,
				'filter_conversion'  => $filter_conversion,
				'pre_filtered'       => $pre_filtered,
			);
			$the_table = new H3_MGMT_Admin_Table( $tbl_args, $columns, $rows );

			$the_page->top();
			$the_table->output();
			$the_page->bottom();
		}
	}

endif; // class exists


