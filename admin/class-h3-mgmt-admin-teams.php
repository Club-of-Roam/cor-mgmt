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
		global $wpdb;

		$messages = array();

		$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : '';

		switch ( $todo ) {

			case "delete":
				if ( isset( $_GET['id'] ) &&  is_numeric( $_GET['id'] ) ) {
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_teams " .
						"WHERE id = '" . $_GET['id'] . "' LIMIT 1"
					);
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_teammates " .
						"WHERE team_id = '" . $_GET['id'] . "'"
					);
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_invitations " .
						"WHERE team_id = '" . $_GET['id'] . "'"
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected team has been successfully deleted.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->teams_list( $messages );
			break;

			case "save":
				if( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {
					$test = $wpdb->update(
						$wpdb->prefix.'h3_mgmt_teams',
						array(
							'team_name' => $_POST['team_name'],
							'description' => $_POST['description'],
							'team_pic' => $_POST['team_pic'],
							'team_phone' => $_POST['team_phone'],
							'route_id' => $_POST['route_id'],
							'race_id' => $_POST['race_id']
						),
						array( 'id'=> intval( $_GET['id'] ) ),
						array( '%s', '%s', '%s', '%s', '%d', '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Team successfully updated!', 'h3-mgmt' )
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix.'h3_mgmt_teams',
						array(
							'race_id' => $_POST['race_id'],
							'team_name' => $_POST['team_name'],
							'description' => $_POST['description'],
							'team_pic' => $_POST['team_pic'],
							'team_phone' => $_POST['team_phone'],
							'route_id' => $_POST['route_id']
						),
						array( '%d', '%s', '%s', '%s', '%s', '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Team successfully added!', 'h3-mgmt' )
					);
				}
				$this->teams_list( $messages );
			break;

			case "edit":
				$this->teams_edit( $_GET['id'] );
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
				"SELECT * FROM " .
				$wpdb->prefix."h3_mgmt_teammates " .
				"WHERE id = " . $_GET['id'] . " LIMIT 1",
				ARRAY_A
			);

			$user_id = isset( $mate_query[0]['user_id'] ) ? $mate_query[0]['user_id'] : NULL;
			$team_id = isset( $mate_query[0]['team_id'] ) ? $mate_query[0]['team_id'] : NULL;

			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : '';

			switch ( $todo ) {

				case "paid":
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_teammates',
						array( 'paid' => 1 ),
						array( 'id'=> $_GET['id'] ),
						array( '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participant&apos;s HitchPackage has been paid.', 'h3-mgmt' )
					);
					$response_args = array(
						'team_name' => $h3_mgmt_teams->get_team_name( $team_id )
					);
					$h3_mgmt_mailer->auto_response( $user_id, 'package-paid', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
					$h3_mgmt_teams->is_complete( $team_id );
					unset( $_GET['todo'], $_GET['id'] );
					$this->participants_list( $messages );
				break;

				case "waiver-set":
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_teammates',
						array( 'waiver' => 1 ),
						array( 'id'=> $_GET['id'] ),
						array( '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participant&apos;s waiver has been set to received.', 'h3-mgmt' )
					);
					$response_args = array(
						'team_name' => $h3_mgmt_teams->get_team_name( $team_id )
					);
					$h3_mgmt_mailer->auto_response( $user_id, 'waiver-reached', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
					$h3_mgmt_teams->is_complete( $team_id );
					unset( $_GET['todo'], $_GET['id'] );
					$this->participants_list( $messages );
				break;

				case "unpaid":
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_teammates',
						array( 'paid' => 0 ),
						array( 'id'=> $_GET['id'] ),
						array( '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participant&apos;s HitchPackage was not yet paid, after all.', 'h3-mgmt' )
					);
					$h3_mgmt_teams->is_complete( $team_id );
					unset( $_GET['todo'], $_GET['id'] );
					$this->participants_list( $messages );
				break;

				case "waiver-unset":
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_teammates',
						array( 'waiver' => 0 ),
						array( 'id'=> $_GET['id'] ),
						array( '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participant&apos;s waiver has not yet reached, after all.', 'h3-mgmt' )
					);
					$h3_mgmt_teams->is_complete( $team_id );
					unset( $_GET['todo'], $_GET['id'] );
					$this->participants_list( $messages );
				break;

				default:
					$this->participants_list( $messages );
			}
		}elseif(isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : $this->participants_list( $messages );
			
			foreach($_GET['bulk'] as $id){
				$mate_query = $wpdb->get_results(
					"SELECT * FROM " .
					$wpdb->prefix."h3_mgmt_teammates " .
					"WHERE id = " . $id . " LIMIT 1",
					ARRAY_A
				);

				$user_id = isset( $mate_query[0]['user_id'] ) ? $mate_query[0]['user_id'] : NULL;
				$team_id = isset( $mate_query[0]['team_id'] ) ? $mate_query[0]['team_id'] : NULL;

				switch ( $todo ) {
					case "bulk-paid":
						$wpdb->update(
							$wpdb->prefix.'h3_mgmt_teammates',
							array( 'paid' => 1 ),
							array( 'id'=> $id ),
							array( '%d' ),
							array( '%d' )
						);
						$response_args = array(
							'team_name' => $h3_mgmt_teams->get_team_name( $team_id )
						);
						$h3_mgmt_mailer->auto_response( $user_id, 'package-paid', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
						$h3_mgmt_teams->is_complete( $team_id );
					break;

					case "bulk-waiver-set":
						$wpdb->update(
							$wpdb->prefix.'h3_mgmt_teammates',
							array( 'waiver' => 1 ),
							array( 'id'=> $id ),
							array( '%d' ),
							array( '%d' )
						);
						$response_args = array(
							'team_name' => $h3_mgmt_teams->get_team_name( $team_id )
						);
						$h3_mgmt_mailer->auto_response( $user_id, 'waiver-reached', $response_args, 'id', $h3_mgmt_teams->get_participant_language( $user_id ) );
						$h3_mgmt_teams->is_complete( $team_id );
					break;

					case "bulk-unpaid":
						$wpdb->update(
							$wpdb->prefix.'h3_mgmt_teammates',
							array( 'paid' => 0 ),
							array( 'id'=> $id ),
							array( '%d' ),
							array( '%d' )
						);
						$h3_mgmt_teams->is_complete( $team_id );
					break;

					case "bulk-waiver-unset":
							$wpdb->update(
								$wpdb->prefix.'h3_mgmt_teammates',
								array( 'waiver' => 0 ),
								array( 'id'=> $id ), //$_GET['id']
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
				case "bulk-paid":
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participants HitchPackages have been paid.', 'h3-mgmt' )
					);
				break;

				case "bulk-waiver-set":
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participants waivers have been set to received.', 'h3-mgmt' )
					);
				break;

				case "bulk-unpaid":
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected participants HitchPackagea were not yet paid, after all.', 'h3-mgmt' )
					);
				break;

				case "bulk-waiver-unset":						
						$messages[] = array(
								'type' => 'message',
								'message' => __( 'The selected participants waivers have not yet reached, after all.', 'h3-mgmt' )
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
		global $wpdb, $h3_mgmt_teams;

		$url = 'admin.php?page=h3-mgmt-teams';

		$columns = array(
			array(
				'id' => 'team_name',
				'title' => __( 'Team Name', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'actions' => array( 'edit', 'delete' ),
				'cap' => 'team'
			),
			array(
				'id' => 'race',
				'title' => __( 'Event / Race', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'route',
				'title' => __( 'Route', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'mates',
				'title' => __( 'HitchMates', 'h3-mgmt' ),
				'sortable' => false,
				'conversion' => 'mates'
			),
			array(
				'id' => 'complete',
				'title' => __( 'Complete?', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'boolean'
			)
		);

		$team_args = array(
			'orderby' => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'team_name',
			'order' => isset( $_GET['order'] ) ? $_GET['order'] : 'ASC',
			'exclude_incomplete' => false,
			'extra_fields' => array( 'mates', 'route', 'race' )
		);
		list( $team_count, $complete_count, $incomplete_count, $rows ) = $h3_mgmt_teams->get_teams_meta( $team_args );

		$page_args = array(
			'echo' => true,
			'icon' => 'icon-teams',
			'title' => __( 'Teams', 'h3-mgmt' ),
			'url' => $url,
			'messages' => $messages
		);
		$the_page = new H3_MGMT_Admin_Page( $page_args );

		$filter = array( 'race', 'route', 'complete' );
		$filter_dis_name = array( 'Event/Race', 'Route', 'Complete?' );
		$filter_conversion = array( '', '', 'boolean' );
		
		$race_name = $wpdb->get_results(
						"SELECT name FROM " . $wpdb->prefix . "h3_mgmt_races " .
						"ORDER BY id DESC LIMIT 1", ARRAY_A
					);
		$race_name = $race_name[0]['name'];
					
		$pre_filtered = array( true, 'race', $race_name);

		$tbl_args = array(
			'orderby' => 'team_name',
			'page_slug' => 'h3-mgmt-teams',
			'base_url' => $url,
			'sort_url' => $url,
			'pagination_url' => $url,
			'icon' => 'icon-teams',
			'headline' => '',
			'headspace' => false,
			'show_empty_message' => true,
			'empty_message' => '',
			'pagination' => false,
			'total_pages' => 1,
			'current_page' => 1,
			'dspl_cnt' => true,
			'count' => count($rows),
			'cnt_txt' => '%d ' . __( 'Teams', 'h3-mgmt' ),
			'with_bulk' => false,
			'bulk_btn' => 'Execute',
			'bulk_confirm' => '',
			'bulk_name' => 'bulk',
			'bulk_param' => 'todo',
			'bulk_desc' => '',
			'extra_bulk_html' => '',
			'bulk_actions' => array(),
			'filter' => $filter,
			'filter_dis_name' => $filter_dis_name,
			'filter_conversion' => $filter_conversion,
			'pre_filtered' => $pre_filtered
		);
		$the_table = new H3_MGMT_Admin_Table( $tbl_args, $columns, $rows );

		$the_page->top();
		$the_table->output();
		$the_page->bottom();
	}

	/**
	 * Edit a team
	 *
	 * @since 1.0
	 * @access private
	 */
	private function teams_edit( $team_id = NULL ) {
		global $h3_mgmt_races, $h3_mgmt_teams;

		if( $h3_mgmt_teams->team_exists( $team_id ) ) {
			$team = $h3_mgmt_teams->get_team_data( $team_id, array( 'mates', 'route' ) );
		}
		$route_id = isset( $team['route_id'] ) ? $team['route_id'] : 0;

		$fields = $h3_mgmt_teams->team_fields( true );
		$race_mb = array(
			'title' => __( 'Race', 'h3-mgmt' ),
			'fields' => $h3_mgmt_teams->route_field( $h3_mgmt_teams->get_team_race( $team_id ), intval( $route_id ) )
		);
		$race_mb['fields'][] = array(
			'id' => 'race_id',
			'type' => 'select',
			'label'	=> __( 'Event / Race', 'h3-mgmt' ),
			'options' => $h3_mgmt_races->options_array( array(
					'data' => 'race',
					'orderby' => 'start',
					'order' => 'DESC',
					'value' => 'id',
					'label' => 'name'
				))
		);
		
		$race_mb['fields'][] = array(
			'id' => 'team_phone',
			'type' => 'text',
			'label'	=> __( 'Phones (for Ticker)', 'h3-mgmt' ),
			'desc'	=> _x( 'More than 1 Phone Number separated by space bar!!!', 'h3-mgmt' ),
			'options' => $h3_mgmt_races->options_array( array(
					'data' => 'race',
					'orderby' => 'start',
					'order' => 'DESC',
					'value' => 'id',
					'label' => 'name'
				))
		);
		
		$fields[] = $race_mb;   			
		
		$mcount = count($fields);
		for ( $i = 0; $i < $mcount; $i++ ) {
			$fcount = count($fields[$i]['fields']);
			for ( $j = 0; $j < $fcount; $j++ ) {
				if( isset( $_POST['submitted'] ) ) {
					if( $fields[$i]['fields'][$j]['id'] == 'team_pic' ) {
						$fields[$i]['fields'][$j]['value'] = stripslashes( $team[$fields[$i]['fields'][$j]['id']] );
					} else {
						$fields[$i]['fields'][$j]['value'] = stripslashes( $_POST[$fields[$i]['fields'][$j]['id']] );
					}
				} elseif( $h3_mgmt_teams->team_exists( $team_id ) ) {
					$fields[$i]['fields'][$j]['value'] = stripslashes( $team[$fields[$i]['fields'][$j]['id']] );
				}
			}
		}

		$url = "admin.php?page=h3-mgmt-teams";
		$form_action = $url . "&amp;todo=save&amp;id=" . $team_id;

		if( $team_id === NULL ) {
			$title = __( 'Add New Team', 'h3-mgmt' );
		} else {
			$title = sprintf( __( 'Edit "%s"', 'h3-mgmt' ), stripslashes( $team['team_name'] ) );
		}

		$page_args = array(
			'echo' => true,
			'icon' => 'icon-teams',
			'title' => $title,
			'url' => $url,
			'messages' => array()
		);
		$the_page = new H3_MGMT_Admin_Page( $page_args );

		$form_args = array(
			'echo' => true,
			'form' => true,
			'method' => 'post',
			'metaboxes' => true,
			'js' => false,
			'url' => $url,
			'action' => $form_action,
			'id' => $team_id,
			'button' => __( 'Save Team', 'h3-mgmt' ),
			'top_button' => true,
			'back' => true,
			'back_url' => $url,
			'fields' => $fields
		);
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
		global $h3_mgmt_teams, $wpdb;

		$url = 'admin.php?page=h3-mgmt-participants';

		$columns = array(
			array(
				'id' => 'first_name',
				'title' => __( 'First Name', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'last_name',
				'title' => __( 'Last Name', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'email',
				'title' => __( 'Email', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'city',
				'title' => __( 'City', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'mobile',
				'title' => __( 'Phone', 'h3-mgmt' ),
				'sortable' => false
			),
			array(
				'id' => 'team',
				'title' => __( 'Team', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'shirt',
				'title' => __( 'Shirt', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'InfMobile',
				'title' => __( 'Inf. for Mobile', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'race',
				'title' => __( 'Event / Race', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'waiver',
				'title' => __( 'Waiver received?', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'conversion' => 'boolean',
				'actions' => array( 'waiver' ),
				'cap' => 'team'
			),
			array(
				'id' => 'paid',
				'title' => __( 'Package paid?', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'conversion' => 'boolean',
				'actions' => array( 'package' ),
				'cap' => 'team'
			)
		);

		// $team_args = array(
			// 'orderby' => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'first_name',
			// 'order' => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'ASC',
			// 'exclude_incomplete' => false,
			// 'extra_fields' => array( 'mates', 'route', 'race' )
		// );
		// list( $team_count, $complete_count, $incomplete_count, $rows ) = $h3_mgmt_teams->get_teams_meta( $team_args );
		
		$parts_args = array(
			'orderby' => isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'first_name',
			'order' => isset( $_GET['order'] ) ? $_GET['order'] : 'ASC',
			'exclude_incomplete' => false,
			'extra_fields' => array( 'first_name', 'last_name', 'email', 'city', 'mobile', 'team', 'race', 'shirt', 'InfMobile' )
		);
		list( $participants_count, $participants_complete_count, $participants_incomplete_count, $rows ) = $h3_mgmt_teams->get_participants_meta( $parts_args );

		$page_args = array(
			'echo' => true,
			'icon' => 'icon-participants',
			'title' => __( 'Participants', 'h3-mgmt' ),
			'url' => $url,
			'messages' => $messages
		);
		$the_page = new H3_MGMT_Admin_Page( $page_args );

		$filter = array( 'shirt', 'InfMobile', 'race', 'waiver', 'paid' );
		$filter_dis_name = array( 'Shirt', 'Inf. for Mobile', 'Event/Race', 'Waiver received?', 'Package paid?' );
		$filter_conversion = array( '', '', '', 'boolean', 'boolean' );
		
		$race_name = $wpdb->get_results(
						"SELECT name FROM " . $wpdb->prefix . "h3_mgmt_races " .
						"ORDER BY id DESC LIMIT 1", ARRAY_A
					);
		$race_name = $race_name[0]['name'];
					
		$pre_filtered = array( true, 'race', $race_name);
		
		$bulk_actions = array(
							array( 	'value' => 'bulk-waiver-set',
									'label' => 'Waiver received!'),
							array( 	'value' => 'bulk-waiver-unset',
									'label' => 'Waiver not received!'),
							array( 	'value' => 'bulk-paid',
									'label' => 'HitchPackage paid!'),
							array( 	'value' => 'bulk-unpaid',
									'label' => 'Not yet paid...')
							);

		$tbl_args = array(
			'orderby' => 'team',
			'page_slug' => 'h3-mgmt-participants',
			'base_url' => $url,
			'sort_url' => $url,
			'pagination_url' => $url,
			'icon' => 'icon-participants',
			'headline' => '',
			'headspace' => false,
			'show_empty_message' => true,
			'empty_message' => '',
			'pagination' => false,
			'total_pages' => 10,
			'current_page' => 1,
			'dspl_cnt' => true,
			'count' => count($rows),
			'cnt_txt' => '%d ' . __( 'Participants', 'h3-mgmt' ),
			'with_bulk' => true,
			'bulk_btn' => 'Execute',
			'bulk_confirm' => '',
			'bulk_name' => 'bulk',
			'bulk_param' => 'todo',
			'bulk_desc' => '',
			'extra_bulk_html' => '',
			'bulk_actions' => $bulk_actions,
			'filter' => $filter,
			'filter_dis_name' => $filter_dis_name,
			'filter_conversion' => $filter_conversion,
			'pre_filtered' => $pre_filtered
		);
		$the_table = new H3_MGMT_Admin_Table( $tbl_args, $columns, $rows );

		$the_page->top();
		$the_table->output();
		$the_page->bottom();
	}
}

endif; // class exists

?>