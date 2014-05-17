<?php

/**
 * H3_MGMT_Admin_Races class.
 *
 * This class contains properties and methods for
 * the creation of new races.
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Races' ) ) :

class H3_MGMT_Admin_Races {

	/* CONTROLLERS */

	/**
	 * Races administration menu
	 *
	 * @since 1.1
	 * @access public
	 */
	public function races_control() {
		global $wpdb;

		$messages = array();

		$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : 'list';

		switch ( $todo ) {

			case "delete":
				if ( $_GET['id'] ) {
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_races " .
						"WHERE id='" . $_GET['id'] . "' LIMIT 1"
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected event or race has been successfully deleted.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;

			case "save":
				if( isset( $_GET['id'] ) && $_GET['id'] != NULL ) {
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'name' => $_POST['name'],
							'start' => strtotime( $_POST['start'] ),
							'end' => strtotime( $_POST['end'] ),
							'logo_url' => $_POST['logo_url']
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s', '%d', '%d', '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Event successfully updated!', 'h3-mgmt' )
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'name' => $_POST['name'],
							'start' => strtotime( $_POST['start'] ),
							'end' => strtotime( $_POST['end'] ),
							'logo_url' => $_POST['logo_url']
						),
						array( '%s', '%d', '%d', '%s' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Event successfully added!', 'h3-mgmt' )
					);
				}
				$this->races_list( $messages );
			break;

			case "edit":
				$this->edit( 'race', $_GET['id'] );
			break;

			case "new":
				$this->edit( 'race' );
			break;

			default:
				$this->races_list();
		}
	}

	/**
	 * Routes administration menu
	 *
	 * @since 1.0
	 * @access public
	 */
	public function routes_control() {
		global $wpdb;

		$messages = array();

		$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : 'list';

		switch ( $todo ) {

			case "delete":
				if ($_GET['id']) {
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_routes " .
						"WHERE id='" . $_GET['id'] . "' LIMIT 1"
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected route has been successfully deleted.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->routes_list( $messages );
			break;

			case "save":
				if( isset( $_GET['id'] ) && $_GET['id'] != NULL ) {
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_routes',
						array(
							'race_id' => $_POST['race_id'],
							'name' => $_POST['name'],
							'color_code' => $_POST['color_code'],
							'logo_url' => $_POST['logo_url'],
							'max_teams' => $_POST['max_teams']
						),
						array( 'id'=> $_GET['id'] ),
						array( '%d', '%s', '%s', '%s', '%d' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Route successfully updated!', 'h3-mgmt' )
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix.'h3_mgmt_routes',
						array(
							'race_id' => $_POST['race_id'],
							'name' => $_POST['name'],
							'color_code' => $_POST['color_code'],
							'logo_url' => $_POST['logo_url'],
							'max_teams' => $_POST['max_teams']
						),
						array( '%d', '%s', '%s', '%s', '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Route successfully added!', 'h3-mgmt' )
					);
				}
				$this->routes_list( $messages );
			break;

			case "edit":
				$this->edit( 'route', $_GET['id'] );
			break;

			case "new":
				$this->edit( 'route' );
			break;

			default:
				$this->routes_list( $messages );
		}
	}

	/**
	 * Stages administration menu
	 *
	 * @since 1.0
	 * @access public
	 */
	public function stages_control() {
		global $wpdb, $h3_mgmt_races;

		$messages = array();

		$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : 'list';

		switch ( $todo ) {

			case "delete":
				if ($_GET['id']) {
					$wpdb->query(
						"DELETE FROM " .
						$wpdb->prefix . "h3_mgmt_stages " .
						"WHERE id='" . $_GET['id'] . "' LIMIT 1"
					);
					echo '<div class="updated"><p><strong>' .
					__( 'The selected stage has been successfully deleted.', 'h3-mgmt' ) .
					'</strong></p></div>';
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->stages_list( $messages );
			break;

			case "save":
				if( isset( $_GET['id'] ) && $_GET['id'] != NULL ) {
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_stages',
						array(
							'race_id' => $h3_mgmt_races->get_route_parent( $_POST['route_id'] ),
							'route_id' => $_POST['route_id'],
							'number' => $_POST['number'],
							'destination' => $_POST['destination'],
							'country' => $_POST['country'],
							'country_3166_alpha-2' => $_POST['country_3166_alpha-2'],
							'meeting_point' => $_POST['meeting_point']
						),
						array( 'id'=> $_GET['id'] ),
						array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Stage successfully updated!', 'h3-mgmt' )
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix.'h3_mgmt_stages',
						array(
							'race_id' => $h3_mgmt_races->get_route_parent( $_POST['route_id'] ),
							'route_id' => $_POST['route_id'],
							'number' => $_POST['number'],
							'destination' => $_POST['destination'],
							'country' => $_POST['country'],
							'country_3166_alpha-2' => $_POST['country_3166_alpha-2'],
							'meeting_point' => $_POST['meeting_point']
						),
						array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'Stage successfully updated!', 'h3-mgmt' )
					);
				}
				$this->stages_list( $messages );
			break;

			case "edit":
				$this->edit( 'stage', $_GET['id'] );
			break;

			case "new":
				$this->edit( 'stage' );
			break;

			default:
				$this->stages_list( $messages );
		}
	}

	/* DATA LISTS */

	/**
	 * List all races
	 *
	 * @since 1.1
	 * @access private
	 */
	private function races_list( $messages = array() ) {
		global $current_user, $h3_mgmt_races, $h3_mgmt_utilities;

		$url = 'admin.php?page=h3-mgmt-races';

		$adminpage = new H3_MGMT_Admin_Page( array(
			'icon' => 'icon-races',
			'title' => __( 'Events / Races', 'h3-mgmt' ),
			'messages' => $messages,
			'url' => $url
		));

		$button = '';
		if (
			$current_user->has_cap( 'h3_mgmt_edit_own_races' ) ||
			$current_user->has_cap( 'h3_mgmt_edit_races' )
		) {
			$button = '<form method="post" action="admin.php' . $url . '&amp;todo=new">' .
				'<input type="submit" class="button-secondary" value="+ ' . __( 'add event / race', 'h3-mgmt' ) . '" />' .
			'</form>';
		}

		extract( $h3_mgmt_utilities->table_order() );
		$rows = $h3_mgmt_races->get_races( array( 'orderby' => $orderby, 'order' => $order ) );

		$columns = array(
			array(
				'id' => 'name',
				'title' => __( 'Event', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'actions' => array( 'edit', 'delete' ),
				'cap' => 'race'
			),
			array(
				'id' => 'id',
				'title' => __( 'Event ID', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'start',
				'title' => __( 'Start', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'date'
			),
			array(
				'id' => 'end',
				'title' => __( 'End', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'date'
			)
		);

		$args = array(
			'base_url' => $url,
			'sort_url' => $url,
			'echo' => false
		);
		$the_table = new H3_MGMT_Admin_Table( $args, $columns, $rows );

		$output = $adminpage->top() .
			'<br />' . $button . '<br />' .
			$the_table->output() .
			'<br />' . $button .
			$adminpage->bottom();

		echo $output;
	}

	/**
	 * List all routes
	 *
	 * @since 1.0
	 * @access private
	 */
	private function routes_list( $messages = array() ) {
		global $current_user, $h3_mgmt_races, $h3_mgmt_utilities;

		$url = 'admin.php?page=h3-mgmt-routes';

		$adminpage = new H3_MGMT_Admin_Page( array(
			'icon' => 'icon-routes',
			'title' => __( 'Routes', 'h3-mgmt' ),
			'messages' => $messages,
			'url' => $url
		));

		$button = '';
		if (
			$current_user->has_cap( 'h3_mgmt_edit_own_races' ) ||
			$current_user->has_cap( 'h3_mgmt_edit_races' )
		) {
			$button = '<form method="post" action="admin.php' . $url . '&amp;todo=new">' .
				'<input type="submit" class="button-secondary" value="+ ' . __( 'add route', 'h3-mgmt' ) . '" />' .
			'</form>';
		}

		extract( $h3_mgmt_utilities->table_order() );
		$rows = $h3_mgmt_races->get_routes( array( 'race' => 'all', 'order' => $orderby, 'order' => $order ) );

		$columns = array(
			array(
				'id' => 'name',
				'title' => __( 'Route', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'actions' => array( 'edit', 'delete' ),
				'cap' => 'race'
			),
			array(
				'id' => 'id',
				'title' => __( 'Route ID', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'race_id',
				'title' => __( 'Event / Race', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'race-name'
			),
			array(
				'id' => 'max_teams',
				'title' => __( 'Max. Teams', 'h3-mgmt' ),
				'sortable' => true
			)
		);

		$args = array(
			'base_url' => $url,
			'sort_url' => $url,
			'echo' => false
		);
		$the_table = new H3_MGMT_Admin_Table( $args, $columns, $rows );

		$output = $adminpage->top() .
			'<br />' . $button . '<br />' .
			$the_table->output() .
			'<br />' . $button .
			$adminpage->bottom();

		echo $output;
	}

	/**
	 * List all stages
	 *
	 * @since 1.0
	 * @access private
	 */
	private function stages_list( $messages = array() ) {
		global $current_user, $h3_mgmt_races, $h3_mgmt_utilities;

		$url = 'admin.php?page=h3-mgmt-stages';

		$adminpage = new H3_MGMT_Admin_Page( array(
			'icon' => 'icon-stages',
			'title' => __( 'Stages', 'h3-mgmt' ),
			'messages' => $messages,
			'url' => $url
		));

		$button = '';
		if (
			$current_user->has_cap( 'h3_mgmt_edit_own_races' ) ||
			$current_user->has_cap( 'h3_mgmt_edit_races' )
		) {
			$button = '<form method="post" action="admin.php' . $url . '&amp;todo=new">' .
				'<input type="submit" class="button-secondary" value="+ ' . __( 'add stage', 'h3-mgmt' ) . '" />' .
			'</form>';
		}

		extract( $h3_mgmt_utilities->table_order( 'destination' ) );
		$rows = $h3_mgmt_races->get_stages( array( 'parent_type' => 'all', 'orderby' => $orderby, 'order' => $order ) );

		$columns = array(
			array(
				'id' => 'destination',
				'title' => __( 'Stage Destination', 'h3-mgmt' ),
				'sortable' => true,
				'strong' => true,
				'actions' => array( 'edit', 'delete' ),
				'cap' => 'race'
			),
			array(
				'id' => 'route_id',
				'title' => __( 'Route', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'route-name'
			),
			array(
				'id' => 'race_id',
				'title' => __( 'Event / Race', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'race-name'
			),
			array(
				'id' => 'number',
				'title' => __( 'Running Number', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'country',
				'title' => __( 'Country', 'h3-mgmt' ),
				'sortable' => true
			),
			array(
				'id' => 'meeting_point',
				'title' => __( 'Meeting Point', 'h3-mgmt' ),
				'sortable' => true
			)
		);

		$args = array(
			'base_url' => $url,
			'sort_url' => $url,
			'echo' => false
		);
		$the_table = new H3_MGMT_Admin_Table( $args, $columns, $rows );

		$output = $adminpage->top() .
			'<br />' . $button . '<br />' .
			$the_table->output() .
			'<br />' . $button .
			$adminpage->bottom();

		echo $output;
	}

	/* INTERFACES TO EDIT A SINGLE DATA SET */

	/**
	 * Edit a race/route/stage
	 *
	 * @since 1.1
	 * @access public
	 */
	public function edit( $type = 'race', $id = NULL ) {
		global $h3_mgmt_races, $h3_mgmt_utilities;

		$url = 'admin.php?page=h3-mgmt-' . $type .'s';
		$form_action = $url . "&amp;todo=save&amp;id=" . $id;

		if( ! is_numeric( $id ) ) {
			$fields = $this->fields( $type );
			$title = sprintf( __( 'Add New %s', 'h3-mgmt' ), $h3_mgmt_utilities->convert_strings( $type ) );
		} else {
			$fields = $this->fields( $type, $id );
			$name = $h3_mgmt_races->get_name( $id, $type );
			$title = sprintf( __( 'Edit &quot;%s&quot;', 'h3-mgmt' ), $name );
		}

		$adminpage = new H3_MGMT_Admin_Page( array(
			'icon' => 'icon-races',
			'title' => $title,
			'url' => $url
		));

		$args = array(
			'echo' => false,
			'form' => true,
			'metaboxes' => true,
			'action' => $form_action,
			'id' => $id,
			'back' => true,
			'back_url' => $url,
			'fields' => $fields
		);
		$form = new H3_MGMT_Admin_Form( $args );

		$output = $adminpage->top() .
			$form->output() .
			$adminpage->bottom();

		echo $output;
	}

	/**
	 * Returns an array of fields for the editing form
	 *
	 * @since 1.0
	 * @access private
	 */
	private function fields( $type = 'race', $id = NULL ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_utilities;

		$race_fields = array(
			array(
				'title' => __( 'The Event', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'text',
						'label' => __( 'Name of event', 'h3-mgmt' ),
						'id' => 'name',
						'desc' => __( 'The name or title of the event', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Logo URL', 'h3-mgmt' ),
						'id' => 'logo_url',
						'desc' => __( 'If you want the event to have a special logo and you uploaded one to the site, set the URL here. (Optional. So far not implemented in the frontend.)', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => __( 'Timeframe', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'date',
						'label' => __( 'Start', 'h3-mgmt' ),
						'id' => 'start',
						'desc' => __( 'The start date of the event', 'h3-mgmt' )
					),
					array(
						'type' => 'date',
						'label' => __( 'End', 'h3-mgmt' ),
						'id' => 'end',
						'desc' => __( 'The end date of the event (usually 2 days after arrival @ the destination)', 'h3-mgmt' )
					)
				)
			)
		);

		$route_fields = array(
			array(
				'title' => __( 'The Route', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'text',
						'label' => __( 'Name of route', 'h3-mgmt' ),
						'id' => 'name',
						'desc' => __( 'The name or title of the route', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Maximum Teams', 'h3-mgmt' ),
						'id' => 'max_teams',
						'desc' => __( 'The maximum number (integer!) of teams that can register for this route', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => __( 'Context', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'select',
						'label' => __( 'Event / Race', 'h3-mgmt' ),
						'id' => 'race_id',
						'desc' => __( 'The event this route belongs to', 'h3-mgmt' ),
						'options' => $h3_mgmt_races->options_array( array( 'data' => 'race' ) )
					)
				)
			),
			array(
				'title' => __( 'Design', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'text',
						'label' => __( 'Color', 'h3-mgmt' ),
						'id' => 'color_code',
						'desc' => __( 'The 6-digit hex color code (no # !) of the route', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Logo URL', 'h3-mgmt' ),
						'id' => 'logo_url',
						'desc' => __( 'The relative path (without the domain!) of the route\'s logo', 'h3-mgmt' )
					)
				)
			)
		);

		$stage_fields = array(
			array(
				'title' => __( 'The Stage', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'text',
						'label' => __( 'Destination', 'h3-mgmt' ),
						'id' => 'destination',
						'desc' => __( 'The destination of the stage', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Number', 'h3-mgmt' ),
						'id' => 'number',
						'desc' => __( 'The running number of the stage', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Country', 'h3-mgmt' ),
						'id' => 'country',
						'desc' => __( 'The destination city&apos;s country', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Country Code', 'h3-mgmt' ),
						'id' => 'country_3166_alpha-2',
						'desc' => __( 'The destination city&apos;s countries 2-letter country code (as defined by <a target="_blank" title="Read the standard" href="http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2">ISO 3166-1-alpha-2</a>)', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => __( 'Meeting Point', 'h3-mgmt' ),
						'id' => 'meeting_point',
						'desc' => __( 'Where the teams will meet in the destination city', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => __( 'Context', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'select',
						'label' => __( 'Route', 'h3-mgmt' ),
						'id' => 'route_id',
						'desc' => __( 'The route this stage belongs to', 'h3-mgmt' ),
						'options' => $h3_mgmt_races->options_array( array( 'data' => 'route' ) )
					)
				)
			)
		);

		if ( 'stage' === $type ) {
			$fields = $stage_fields;
		} elseif ( 'route' === $type ) {
			$fields = $route_fields;
		} else {
			$fields = $race_fields;
		}

		if ( ! is_numeric( $id ) ) {

			return $fields;

		} else {

			$data = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "h3_mgmt_" . $type . "s " .
				"WHERE id = " . $id . " LIMIT 1", ARRAY_A
			);
			$data = $data[0];

			$mcount = count($fields);
			for ( $i = 0; $i < $mcount; $i++ ) {
				$fcount = count($fields[$i]['fields']);
				for ( $j = 0; $j < $fcount; $j++ ) {
					if ( empty( $_POST['submitted'] ) ) {
						if ( $fields[$i]['fields'][$j]['type'] !== 'date' ) {
							$fields[$i]['fields'][$j]['value'] = stripslashes( $data[$fields[$i]['fields'][$j]['id']] );
						} else {
							$fields[$i]['fields'][$j]['value'] = $h3_mgmt_utilities->h3_strftime( stripslashes( $data[$fields[$i]['fields'][$j]['id']] ) );
						}
					} else {
						$fields[$i]['fields'][$j]['value'] = stripslashes( $_POST[$fields[$i]['fields'][$j]['id']] );
					}
				}
			}

		}

		return $fields;
	}

} // class

endif; // class exists