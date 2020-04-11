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
		global $wpdb, $h3_mgmt_races;

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
			
			case "liveticker-set":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['liveticker'] = 1;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Liveticker function for the selected event or race has been successfully enabled.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "liveticker-unset":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['liveticker'] = 0;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Liveticker function for the selected event or race has been successfully disabled.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "liveticker-front-set":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['liveticker_front'] = 1;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Liveticker content for the selected event or race will be successfully visible.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "liveticker-front-unset":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['liveticker_front'] = 0;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Liveticker content for the selected event or race will be successfully unvisible.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "active":
				if ( $_GET['id'] ) {
					$active_id = $wpdb->get_results(
                                                "SELECT id FROM " . $wpdb->prefix . "h3_mgmt_races " .
                                                "WHERE active = 1" , ARRAY_A
                                        );
					
					$active_id = $active_id[0]['id'];
					
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'active' => 0
						),
						array( 'id'=> $active_id ),
						array( '%d' ),
						array( '%d' )
					);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'active' => 1
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected event or race will be active.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "donation-set":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['donation'] = 1;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Donation Tool function for the selected event or race has been successfully enabled.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;
			
			case "donation-unset":
				if ( $_GET['id'] ) {
					$setting = $h3_mgmt_races->get_race_setting( $_GET['id'] );
					$setting['donation'] = 0;
					$setting = json_encode($setting);
				
					$wpdb->update(
						$wpdb->prefix.'h3_mgmt_races',
						array(
							'setting' => $setting
						),
						array( 'id'=> $_GET['id'] ),
						array( '%s' ),
						array( '%d' )
					);
					$messages[] = array(
						'type' => 'message',
						'message' => __( 'The Donation Tool function for the selected event or race has been successfully disabled.', 'h3-mgmt' )
					);
				}
				unset( $_GET['todo'], $_GET['id'] );
				$this->races_list( $messages );
			break;

			case "save":
                            
                                if( $_GET['id'] != NULL ){
                                    $setting_datbase = $h3_mgmt_races->get_race_setting( $_GET['id'] );
                                    $setting = array(   'status'                        => $_POST['status'],
                                                        'startingpoint'                 => $_POST['startingpoint'],
                                                        'liveticker'                    => $_POST['liveticker'],
                                                        'donation'                      => $_POST['donation'],
                                                        'liveticker_front'              => $_POST['liveticker_front'],
                                                        'kind_of_donation_tool'         => $_POST['kind_of_donation_tool'],
                                                        'dis_shirt_size'                => $setting_datbase['dis_shirt_size'],
                                                        'dis_mobile_inf'                => $setting_datbase['dis_mobile_inf'],
                                                        'dis_waiver'                    => $setting_datbase['dis_waiver'],
                                                        'dis_fee'                       => $setting_datbase['dis_fee'],
                                                        'start_route_id'                => $_POST['start_route_id'],
                                                        'num_teammember'                => $setting_datbase['num_teammember'],
                                                        'num_teammember_min'            => $setting_datbase['num_teammember_min'],
                                                        'betterplace_redirect_link'     => $_POST['betterplace_redirect_link'],
                                                        'show_donation_amount'          => $_POST['show_donation_amount'],
                                                        'one_extra_point'               => $_POST['one_extra_point'],
                                                        'amount_extra_point'            => $_POST['amount_extra_point'],
                                                        'extra_point_amount'            => $_POST['extra_point_amount'],
                                                        'vary_extra_point_field'        => $_POST['vary_extra_point_field'],
                                                        'donation_link_link'            => $_POST['donation_link_link'],
                                                        'question_1_invisible'          => $_POST['question_1_invisible'],
                                                        'question_2_invisible'          => $_POST['question_2_invisible'],
                                                        'question_3_invisible'          => $_POST['question_3_invisible'],
                                                        'question_4_invisible'          => $_POST['question_4_invisible'],
                                                        'question_5_invisible'          => $_POST['question_5_invisible'],
                                                        'team_overview_link'            => $_POST['team_overview_link']
                                                    );
                                }else{
                                    if( intval( $_POST['num_teammember'] ) < 1 ){
                                       $num_teammember = 1; 
                                    }elseif( intval( $_POST['num_teammember'] ) > 100 ){
                                        $num_teammember = 100;
                                    }else{
                                        $num_teammember = $_POST['num_teammember'];
                                    }
                                    
                                    if( intval( $_POST['num_teammember_min'] ) < 1 ){
                                       $num_teammember_min = 1; 
                                    }elseif( intval( $_POST['num_teammember_min'] ) > 100 ){
                                        $num_teammember_min = 100;
                                    }else{
                                        $num_teammember_min = $_POST['num_teammember_min'];
                                    }
                                     
                                    $setting = array(   'status'                        => $_POST['status'],
                                                        'startingpoint'                 => $_POST['startingpoint'],
                                                        'liveticker'                    => $_POST['liveticker'],
                                                        'donation'                      => $_POST['donation'],
                                                        'liveticker_front'              => $_POST['liveticker_front'],
                                                        'kind_of_donation_tool'         => $_POST['kind_of_donation_tool'],
                                                        'dis_shirt_size'                => $_POST['dis_shirt_size'],
                                                        'dis_mobile_inf'                => $_POST['dis_mobile_inf'],
                                                        'dis_waiver'                    => $_POST['dis_waiver'],
                                                        'dis_fee'                       => $_POST['dis_fee'],
                                                        'start_route_id'                => $_POST['start_route_id'],
                                                        'num_teammember'                => $num_teammember,
                                                        'num_teammember_min'            => $num_teammember_min,
                                                        'betterplace_redirect_link'     => $_POST['betterplace_redirect_link'],
                                                        'show_donation_amount'          => $_POST['show_donation_amount'],
                                                        'one_extra_point'               => $_POST['one_extra_point'],
                                                        'amount_extra_point'            => $_POST['amount_extra_point'],
                                                        'extra_point_amount'            => $_POST['extra_point_amount'],
                                                        'vary_extra_point_field'        => $_POST['vary_extra_point_field'],
                                                        'donation_link_link'            => $_POST['donation_link_link'],
                                                        'question_1_invisible'          => $_POST['question_1_invisible'],
                                                        'question_2_invisible'          => $_POST['question_2_invisible'],
                                                        'question_3_invisible'          => $_POST['question_3_invisible'],
                                                        'question_4_invisible'          => $_POST['question_4_invisible'],
                                                        'question_5_invisible'          => $_POST['question_5_invisible'],
                                                        'team_overview_link'            => $_POST['team_overview_link']
                                                    );
                                }
				$setting = json_encode($setting);
				
				if( $_POST['race_id_inf'] != 0 ){
                                    $information_text = $wpdb->get_results(
                                        "SELECT information_text FROM " . $wpdb->prefix . "h3_mgmt_races " .
                                        "WHERE id = " . $_POST['race_id_inf'], ARRAY_A
                                    );
                                    $information_text = $information_text[0]['information_text'];
		
				}else{
                                    $i = 1;
                                    while( isset( $_POST[$i.'en'] ) ){
                                        $information_text[$i.'en'] = $_POST[$i.'en'];
                                        $information_text[$i.'de'] = $_POST[$i.'de'];
                                        $i++;
                                    }
                                    $information_text = json_encode($information_text);
				}
			
				if( $_POST['active'] == 1 ){
                                    $active_id = $wpdb->get_results(
                                        "SELECT id FROM " . $wpdb->prefix . "h3_mgmt_races " .
                                        "WHERE active = 1" , ARRAY_A
                                    );

                                    $active_id = $active_id[0]['id'];

                                    $wpdb->update(
                                        $wpdb->prefix.'h3_mgmt_races',
                                        array(
                                                'active' => 0
                                        ),
                                        array( 'id'=> $active_id ),
                                        array( '%d' ),
                                        array( '%d' )
                                    );
		
				}
				
				if( isset( $_GET['id'] ) && $_GET['id'] != NULL ) {
                                    $wpdb->update(
                                            $wpdb->prefix.'h3_mgmt_races',
                                            array(
                                                    'name' => $_POST['name'],
                                                    'start' => strtotime( $_POST['start'] ),
                                                    'end' => strtotime( $_POST['end'] ),
                                                    'logo_url' => $_POST['logo_url'],
                                                    'setting' => $setting,
                                                    'information_text' => $information_text,
                                                    'active' => $_POST['active']
                                            ),
                                            array( 'id'=> $_GET['id'] ),
                                            array( '%s', '%d', '%d', '%s', '%s', '%s', '%d' ),
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
                                                    'logo_url' => $_POST['logo_url'],
                                                    'setting' => $setting,
                                                    'information_text' => $information_text,
                                                    'active' => $_POST['active']
                                            ),
                                            array( '%s', '%d', '%d', '%s', '%s', '%d' )
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
                
		if($todo == "new"){
			$this->edit( 'route' );
		}elseif ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) { 
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
                            if( !$_POST['route_account_login_name'] == NULL || !$_POST['route_account_login_name'] == ''){
                                if( $_POST['route_account_pw1'] == $_POST['route_account_pw2'] && ( !$_POST['route_account_pw1'] == NULL || !$_POST['route_account_pw1'] == '' )){
                                    $user_id = username_exists( $_POST['route_account_login_name'] );
                                    if ( !$user_id and email_exists($_POST['route_account_email']) == false ) {
                                        
                                        $user_id = wp_create_user( $_POST['route_account_login_name'], $_POST['route_account_pw1'], $_POST['route_account_email'] );
                                        wp_insert_user( array(
                                                            'ID'  => $user_id,
                                                            'user_login'  => $_POST['route_account_login_name'],
                                                            'role'    =>  'route',
                                                            'user_email' => $_POST['route_account_email']
                                                        ) ); 

                                        $wpdb->update(
                                            $wpdb->prefix.'h3_mgmt_routes',
                                            array(
                                                    'race_id' => $_POST['race_id'],
                                                    'name' => $_POST['name'],
                                                    'color_code' => $_POST['color_code'],
                                                    'logo_url' => $_POST['logo_url'],
                                                    'max_teams' => $_POST['max_teams'],
                                                    'user_id' => $user_id
                                            ),
                                            array( 'id'=> $_GET['id'] ),
                                            array( '%d', '%s', '%s', '%s', '%d', '%d' ),
                                            array( '%d' )
                                        ); 
                                       
                                        $messages[] = array(
                                            'type' => 'message',
                                            'message' => __( 'Route successfully created!', 'h3-mgmt' )
                                        );
                                    } else { 
                                        $messages[] = array(
                                            'type' => 'error',
                                            'message' => __( 'Route account name or E-Mail already exist!', 'h3-mgmt' )
                                        );
                                    }
                                }else{
                                    $messages[] = array(
                                        'type' => 'error',
                                        'message' => __( 'The entered passwords are different or empty', 'h3-mgmt' )
                                    );
                                }
                            }else{
                                
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
                                    'message' => __( 'Route successfully created!', 'h3-mgmt' )
                                );
                            }

                            $this->routes_list( $messages );
                        break;

                        case "edit":
                                $this->edit( 'route', $_GET['id'] );
                        break;

                        default:
                            $this->routes_list( $messages );
                    }
		}elseif( $todo == "save" ) {                         
                    if( !$_POST['route_account_login_name'] == NULL || !$_POST['route_account_login_name'] == ''){
                        if( $_POST['route_account_pw1'] == $_POST['route_account_pw2'] && ( !$_POST['route_account_pw1'] == NULL || !$_POST['route_account_pw1'] == '' )){
                            $user_id = username_exists( $_POST['route_account_login_name'] );
                            if ( !$user_id and email_exists($_POST['route_account_email']) == false ) {
                               
                                $user_id = wp_create_user( $_POST['route_account_login_name'], $_POST['route_account_pw1'], $_POST['route_account_email'] );
                                wp_insert_user( array(
                                                    'ID'  => $user_id,
                                                    'user_login'  => $_POST['route_account_login_name'],
                                                    'role'    =>  'route',
                                                    'user_email' => $_POST['route_account_email']
                                                ) ); 

                                $wpdb->insert(
                                        $wpdb->prefix.'h3_mgmt_routes',
                                        array(
                                                'race_id' => $_POST['race_id'],
                                                'name' => $_POST['name'],
                                                'color_code' => $_POST['color_code'],
                                                'logo_url' => $_POST['logo_url'],
                                                'max_teams' => $_POST['max_teams'],
                                                'user_id' => $user_id
                                        ),
                                        array( '%d', '%s', '%s', '%s', '%d', '%d' )


                                );   

                                $messages[] = array(
                                    'type' => 'message',
                                    'message' => __( 'Route successfully created!', 'h3-mgmt' )
                                );
                            } else { 
                                $messages[] = array(
                                    'type' => 'error',
                                    'message' => __( 'Route account name or E-Mail already exist!', 'h3-mgmt' )
                                );
                            }
                        }else{
                            $messages[] = array(
                                'type' => 'error',
                                'message' => __( 'The entered passwords are different or empty', 'h3-mgmt' )
                            );
                        }
                    }else{
                        
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
                                    'message' => __( 'Route successfully created!', 'h3-mgmt' )
                                );
                    }
                    
                    $this->routes_list( $messages );
                    
                }elseif(isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : $this->participants_list( $messages );
			
			foreach($_GET['bulk'] as $id){
				switch ( $todo ) {

				case "bulk-delete":
						$wpdb->query(
							"DELETE FROM " .
							$wpdb->prefix . "h3_mgmt_routes " .
							"WHERE id='" . $id . "' LIMIT 1"
						);
				break;
				
				default:
					$this->routes_list( $messages );
				}
			}
			$messages[] = array(
                                'type' => 'message',
                                'message' => __( 'The selected routes have been successfully deleted.', 'h3-mgmt' )
                        );
			unset( $_GET['todo'], $_GET['bulk'] );
			$this->routes_list( $messages );
		}else{
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
		if ( isset( $_GET['id'] ) || $_GET['todo'] == 'new' ) {
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
		}elseif(isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : $this->participants_list( $messages );
			
			foreach($_GET['bulk'] as $id){
				switch ( $todo ) {
					case "bulk-delete":
						$wpdb->query(
							"DELETE FROM " .
							$wpdb->prefix . "h3_mgmt_stages " .
							"WHERE id='" . $id . "' LIMIT 1"
						);
					break;
					
					default:
						$this->stages_list( $messages );
				}
			}
			unset( $_GET['todo'], $_GET['bulk'] );
			$messages[] = array(
						'type' => 'message',
						'message' => __( 'The selected stages have been successfully deleted.', 'h3-mgmt' )
					);
			$this->stages_list( $messages );
		}else{
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
			$button = '<form method="post" action="' . $url . '&todo=new">' .
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
				'id' => 'active',
				'title' => __( 'Active?', 'h3-mgmt' ),
				'sortable' => true,
				'actions' => array( 'active' ),
				'conversion' => 'boolean'
			),
			array(
				'id' => 'status',
				'title' => __( 'Status', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'status'
			),
			array(
				'id' => 'startingpoint',
				'title' => __( 'More then 1 Start point?', 'h3-mgmt' ),
				'sortable' => true,
				'conversion' => 'boolean'
			),
			array(
				'id' => 'liveticker',
				'title' => __( 'Liveticker Send-Function enabled', 'h3-mgmt' ),
				'sortable' => true,
				'actions' => array( 'liveticker' ),
				'conversion' => 'boolean'
			),
			array(
				'id' => 'liveticker_front',
				'title' => __( 'Show Liveticker Content', 'h3-mgmt' ),
				'sortable' => true,
				'actions' => array( 'liveticker_front' ),
				'conversion' => 'boolean'
			),
			array(
				'id' => 'donation',
				'title' => __( 'Donation Tool enabled', 'h3-mgmt' ),
				'sortable' => true,
				'actions' => array( 'donation' ),
				'conversion' => 'boolean'
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
		global $wpdb, $current_user, $h3_mgmt_races, $h3_mgmt_utilities;

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
			$button = '<form method="post" action="' . $url . '&amp;todo=new">' .
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

		$filter = array( 'race_id' );
		$filter_dis_name = array( 'Event/Race' );
		$filter_conversion = array( 'race-name' );
                
                $active_race = $h3_mgmt_races->get_active_race();
					
		$pre_filtered = array( true, 'race_id', $active_race);
		
		$bulk_actions = array(
                                    array( 	'value' => 'bulk-delete',
                                                'label' => 'Delete')
                                    );
		
		$args = array(
			'page_slug' => 'h3-mgmt-routes',
			'base_url' => $url,
			'sort_url' => $url,
			'echo' => false,
			'dspl_cnt' => true,
			'count' => count($rows),
			'cnt_txt' => '%d ' . __( 'Routes', 'h3-mgmt' ),
			'with_bulk' => true,
			'bulk_btn' => 'Delete',
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
		global $wpdb, $current_user, $h3_mgmt_races, $h3_mgmt_utilities;

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
			$button = '<form method="post" action="' . $url . '&amp;todo=new">' .
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

		$filter = array( 'route_id', 'race_id', 'number', 'country' );
		$filter_dis_name = array( 'Route', 'Event/Race', 'Running Number', 'Country' );
		$filter_conversion = array( 'route-name', 'race-name', '', '' );
		
		
                $active_race = $h3_mgmt_races->get_active_race();
			
		$pre_filtered = array( true, 'race_id', $active_race);
                
		$bulk_actions = array(
							array( 	'value' => 'bulk-delete',
									'label' => 'Delete')
							);
		
		$args = array(
			'page_slug' => 'h3-mgmt-stages',
			'base_url' => $url,
			'sort_url' => $url,
			'echo' => false,
			'dspl_cnt' => true,
			'count' => count($rows),
			'cnt_txt' => '%d ' . __( 'Stages', 'h3-mgmt' ),
			'with_bulk' => true,
			'bulk_btn' => 'Delete',
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

		$output = 	$adminpage->top() .
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
		global $wpdb, $h3_mgmt_races, $h3_mgmt_utilities, $h3_mgmt_teams;
		
		$options_race_first_empty = array( 
                                                array(
                                                    'value' => 0,
                                                    'label' => '---'
                                                )
                                            );
		$options_race_first_empty = array_merge( $options_race_first_empty, $h3_mgmt_races->options_array( array( 'data' => 'race' ) ));			
		
                if( $id === NULL ){
                    $disabled = false;
                }else{
                    $disabled = true;
                }
                
                $startiing_points_array = $h3_mgmt_teams->route_field( $id );
                
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
					),
                                        array(
                                            'type' => 'text_long',
                                            'label' => __( 'Enter the URL to the team overview page on your site.', 'h3-mgmt' ),
                                            'id' => 'team_overview_link',
                                            'desc' => __( 'Enter the URL (without http:// or https:// and your Domain. For example just "/team_overview/".) to the team overview page where the user get the overviwe about all teams and could see the team prfiles. Please make sure that the page with the ID of the event has the same ID as this event! The shortcode is "h3-teams race=ID_of_the_event"', 'h3-mgmt' )
                                        )
				)
			),
			array(
				'title' => __( 'Settings Race functions', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'radio',
						'label' => __( 'Is the Race / Event the active one?', 'h3-mgmt' ),
						'id' => 'active',
						'desc' => __( 'Is this Race / Event the present one?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'select',
						'label' => __( 'Status', 'h3-mgmt' ),
						'id' => 'status',
						'desc' => __( 'The Status of the event (bevor registration, registration closed...)', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'bevor registration'
							),
							array(
								'value' => 1,
								'label' => 'registration open'
							),
							array(
								'value' => 2,
								'label' => 'registration closed and bevor race start'
							),
							array(
								'value' => 3,
								'label' => 'race started'
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => __( 'More then 1 starting point?', 'h3-mgmt' ),
						'id' => 'startingpoint',
						'desc' => __( 'Could the Teams choose the starting point?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
                                        array(
						'type' => 'select',
						'label' => __( 'Starting point if chosen just one starting point', 'h3-mgmt' ),
						'id' => 'start_route_id',
						'desc' => __( 'If chosen just one starting point the teams will be automatically added to this starting point.', 'h3-mgmt' ),
                                                'options' => $startiing_points_array[0]['options']
                                        ),
					array(
						'type' => 'radio',
						'label' => __( 'Liveticker Send-Function enabled?', 'h3-mgmt' ),
						'id' => 'liveticker',
						'desc' => __( 'Do you want to allow to send Liveticker messages?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => __( 'Show Liveticker Content', 'h3-mgmt' ),
						'id' => 'liveticker_front',
						'desc' => __( 'Do you want to show the Liveticker content at the Frontend?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => __( 'Donation Tool enabled?', 'h3-mgmt' ),
						'id' => 'donation',
						'desc' => __( 'Do you want to allow to donate for teams?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => __( 'Enable 1 extra Point for each Stage', 'h3-mgmt' ),
						'id' => 'one_extra_point',
						'desc' => __( 'Do you want to enable the function of 1 extra point for each stage and Team in the ranking?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => __( 'Enable a chosen amount of extra Point for each stage', 'h3-mgmt' ),
						'id' => 'amount_extra_point',
						'desc' => __( 'Do you want to enable the function of a chosen amount of extra points for each stage and Team in the ranking?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'text',
						'label' => __( 'The amount of the field', 'h3-mgmt' ),
						'id' => 'extra_point_amount',
						'desc' => __( 'Enter the amount of extra points they could give the teams each route in the ranking.', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Enable a extra field to give extra points only for the last stage', 'h3-mgmt' ),
						'id' => 'vary_extra_point_field',
						'desc' => __( 'Do you want to enable the function that they get a extra field where they could chose the amount of points they would give for the last stage in the ranking?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					)
				)
                        ),
                        array(
                                    'title' => __( 'Settings Race functions (After creating you can\'t change  it anymore)', 'h3-mgmt' ),
                                    'fields' => array(
                                        array(
						'type' => 'text',
						'label' => __( 'How many participants per team minimum?', 'h3-mgmt' ),
						'id' => 'num_teammember_min',
                                                'disabled' => $disabled,
						'desc' => __( 'How many team member a team must have?', 'h3-mgmt' ),
                                                'value' => 2
					),
                                        array(
						'type' => 'text',
						'label' => __( 'How many participants per team maximum?', 'h3-mgmt' ),
						'id' => 'num_teammember',
                                                'disabled' => $disabled,
						'desc' => __( 'How many team member a team could have?', 'h3-mgmt' ),
                                                'value' => 3
					),
                                        array(
						'type' => 'radio',
						'label' => __( 'Disable liability waiver?', 'h3-mgmt' ),
						'id' => 'dis_waiver',
                                                'disabled' => $disabled,
						'desc' => __( 'Do you want to disable the liability waiver? It\'s not visible in the team dashboard then and not required to get complete.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
                                        array(
						'type' => 'radio',
						'label' => __( 'Disable to pay a fee for a Hitchpackage or something else?', 'h3-mgmt' ),
						'id' => 'dis_fee',
                                                'disabled' => $disabled,
						'desc' => __( 'Do you want to disable that the participants have to pay a fee? It\'s not visible in the team dashboard then and not required to get complete.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
                                        array(
						'type' => 'radio',
						'label' => __( 'Disable choose shirt size?', 'h3-mgmt' ),
						'id' => 'dis_shirt_size',
                                                'disabled' => $disabled,
						'desc' => __( 'Do you want to disable the shirt function? It\'s not visible in the team dashboard then.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
                                        array(
						'type' => 'radio',
						'label' => __( 'Disable choose mobile information and extra field', 'h3-mgmt' ),
						'id' => 'dis_mobile_inf',
                                                'disabled' => $disabled,
						'desc' => __( 'Do you want to disable the mobile information and extra field? It\'s not visible in the team dashboard then.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					)
                                )
                            ),
			array(
				'title' => __( 'Settings Donation Tool', 'h3-mgmt' ),
				'fields' => array(
					array(
                                            'type' => 'select',
                                            'label' => __( 'Kind of donation tool', 'h3-mgmt' ),
                                            'id' => 'kind_of_donation_tool',
                                            'desc' => __( 'How should the donation tool work? Select the functions of the donation tool', 'h3-mgmt' ),
                                            'options' => array( 
                                                array(
                                                        'value' => 0,
                                                        'label' => 'Standard with TeamOwner and TeamSponsor'
                                                ),
                                                array(
                                                        'value' => 1,
                                                        'label' => 'Only TeamSponsor'
                                                )
                                            )
					),
                                        array(
                                            'type' => 'text_long',
                                            'label' => __( 'Enter the redirect link to your Betterplace project on the Betterlace site.', 'h3-mgmt' ),
                                            'id' => 'betterplace_redirect_link',
                                            'desc' => __( ' You will get the Third Party Link from Betterplace. If you need more information contact us. <br> Example: https://www.testtest.com/de/**your project or event**/**your id**/client_donations/new?client_id=**your client id** <br>'
                                                    . '     How you get the Third Party Link you will find <a title="Info third party link URL" href="https://github.com/betterplace/betterplace_apidocs/blob/master/donation_form/third_party_app_donation_form.md#how-to-get-it">here</a> <br><br>'
                                                    . '     Please let them know under the point "Description" that you use the tramprennen API <br>'
                                                    . '     For "Callback url production" you have to  set up ONE page in Wordpress for all other Events. In this page you just enter this shortcode "[h3-handle-betterplace-redirect]". You could get the  right URL if you call the page and add "?status=Test" at the end of the URL <br>'
                                                    . '     If you have any problems send a mail to web@tramprennen.org', 'h3-mgmt' ) 
                                        ),
                                        array(
                                            'type' => 'text_long',
                                            'label' => __( 'Enter the redirect link to the donation tool page on your site.', 'h3-mgmt' ),
                                            'id' => 'donation_link_link',
                                            'desc' => __( 'Enter the Link (without http:// or https:// and your Domain. For example just "/donate/".) to your page where the user also will donate. Thats the page where you add the shortcode to run the donation code. Please make sure that the page with the ID of the event has the same ID as this event! The shortcode is "h3-sponsoring-form race=ID_of_the_event"', 'h3-mgmt' )
                                        ),
					array(
						'type' => 'radio',
						'label' => __( 'Show donation amount in team homework?', 'h3-mgmt' ),
						'id' => 'show_donation_amount',
						'desc' => __( 'Do you want to show the amount of collected donations in the teams homework list?', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
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
			),
                        array(
				'title' => __( 'Load information text from older race', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'select',
						'label' => __( 'Event / Race', 'h3-mgmt' ),
						'id' => 'race_id_inf',
						'desc' => __( 'From wich Race / Event you wanna copy? If you press save and select a race all data in the "Information Texts" section will be overwritten!!!', 'h3-mgmt' ),
						'options' => $options_race_first_empty
					)
				)
			),
			array(  //Highest ID 31
				'title' => __( 'Questions in Team profiles', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 1', 'h3-mgmt' ),
						'id' => '1en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '1de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Description of Team Profile Question 1', 'h3-mgmt' ),
						'id' => '6en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '6de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
						'id' => 'question_1_invisible',
						'desc' => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 2', 'h3-mgmt' ),
						'id' => '2en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '2de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Description of Team Profile Question 2', 'h3-mgmt' ),
						'id' => '7en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '7de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
						'id' => 'question_2_invisible',
						'desc' => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 3', 'h3-mgmt' ),
						'id' => '3en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '3de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Description of Team Profile Question 3', 'h3-mgmt' ),
						'id' => '8en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '8de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
						'id' => 'question_3_invisible',
						'desc' => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 4', 'h3-mgmt' ),
						'id' => '4en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '4de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 4 Answer 1', 'h3-mgmt' ),
						'id' => '12en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '12de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 4 Answer 2', 'h3-mgmt' ),
						'id' => '13en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '13de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 4 Answer 3', 'h3-mgmt' ),
						'id' => '14en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '14de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 4 Answer 4', 'h3-mgmt' ),
						'id' => '15en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '15de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Description of Team Profile Question 4', 'h3-mgmt' ),
						'id' => '9en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '9de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
						'id' => 'question_4_invisible',
						'desc' => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Team Profile Question 5', 'h3-mgmt' ),
						'id' => '5en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '5de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Description of Team Profile Question 5', 'h3-mgmt' ),
						'id' => '10en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '10de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'radio',
						'label' => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
						'id' => 'question_5_invisible',
						'desc' => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
						'options' => array( 
							array(
								'value' => 0,
								'label' => 'No'
							),
							array(
								'value' => 1,
								'label' => 'Yes'
							)
						)
					)
                                    )
                            ),
                            array(  
				'title' => __( 'Information Texts', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under "Select starting point" in Team Dashboard', 'h3-mgmt' ),
						'id' => '11en',
						'desc' => __( 'In english (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '11de',
						'desc' => __( 'In german (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'First Text under "INVITE TEAMMATE(S)" in Team Dashboard', 'h3-mgmt' ),
						'id' => '30en',
						'desc' => __( 'In english (Will displayed if the participant hasn\'t invited anyone yet.)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '30de',
						'desc' => __( 'In german (Will displayed if the participant hasn\'t invited anyone yet.)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Second Text under "INVITE TEAMMATE(S)" in Team Dashboard', 'h3-mgmt' ),
						'id' => '31en',
						'desc' => __( 'In english (Will displayed if the participant has invited someone yet.)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '31de',
						'desc' => __( 'In german (Will displayed if the participant has invited someone yet.)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under HOMEWORK ASSIGNMENTS', 'h3-mgmt' ),
						'id' => '32en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '32de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: Invite Teampartner.', 'h3-mgmt' ),
						'id' => '33en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '33de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: liability waiver from participant.', 'h3-mgmt' ),
						'id' => '34en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '34de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: liability waiver from team mate', 'h3-mgmt' ),
						'id' => '35en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '35de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: Fee from participant for Example: Hitchpackage.', 'h3-mgmt' ),
						'id' => '28en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '28de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: Fee from team mate for Example: Hitchpackage.', 'h3-mgmt' ),
						'id' => '29en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '29de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: T-Shirt from participant.', 'h3-mgmt' ),
						'id' => '36en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '36de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: T-Shirt from team mate', 'h3-mgmt' ),
						'id' => '37en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '37de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: Extra from participant.', 'h3-mgmt' ),
						'id' => '38en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '38de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Points for BEFORE PUBLISHING: Extra from team mate', 'h3-mgmt' ),
						'id' => '39en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '39de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text for Liability Waiver Form information', 'h3-mgmt' ),
						'id' => '16en',
						'desc' => __( 'In english (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '16de',
						'desc' => __( 'In german (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text for money transfer information', 'h3-mgmt' ),
						'id' => '17en',
						'desc' => __( 'In english (with HTML code BUT no < / p > at the end!!!)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '17de',
						'desc' => __( 'In german (with HTML code BUT no < / p > at the end!!!)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under the points of "After Team is complete"', 'h3-mgmt' ),
						'id' => '18en',
						'desc' => __( 'In english (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '18de',
						'desc' => __( 'In german (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under step one in "Sponsoring Steps"', 'h3-mgmt' ),
						'id' => '19en',
						'desc' => __( 'In english (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '19de',
						'desc' => __( 'In german (with HTML code)', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under Teamsponsor at the right', 'h3-mgmt' ),
						'id' => '20en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '20de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( 'Text under Teamowner at the right', 'h3-mgmt' ),
						'id' => '21en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '21de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for registration isn\'t open yet', 'h3-mgmt' ),
						'id' => '22en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '22de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for liveticker isn\'t enabled yet', 'h3-mgmt' ),
						'id' => '23en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '23de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for donation tool isn\'t activated yet', 'h3-mgmt' ),
						'id' => '24en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '24de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for team overview when registration isn\'t open yet', 'h3-mgmt' ),
						'id' => '25en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '25de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for the Ranking table when race isn\'t started yet', 'h3-mgmt' ),
						'id' => '26en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '26de',
						'desc' => __( 'In german', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( 'Information text for Liveticker when show liveticker content is disabled', 'h3-mgmt' ),
						'id' => '27en',
						'desc' => __( 'In english', 'h3-mgmt' )
					),
					array(
						'type' => 'text_long',
						'label' => __( '', 'h3-mgmt' ),
						'id' => '27de',
						'desc' => __( 'In german', 'h3-mgmt' )
					)
				)
			)
		);
                
                $route_account = $h3_mgmt_races->get_route_account( $id );
                
                if( $route_account  == NULL || $route_account == 0){
                    $disabled2 = false;
                }else{
                    $disabled2 = true;
                }
                
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
			),
			array(
                            'title' => __( 'Route Account (After creating a account you can\'t change it anymore)', 'h3-mgmt' ),
                            'fields' => array(
                                array(
                                        'type' => 'text',
                                        'label' => __( 'Login Name', 'h3-mgmt' ),
                                        'id' => 'route_account_login_name',
                                        'disabled' => $disabled2,
                                        'desc' => __( 'The login name of the route account', 'h3-mgmt' )
                                ),
                                array(
                                        'type' => 'text',
                                        'label' => __( 'E-Mail', 'h3-mgmt' ),
                                        'id' => 'route_account_email',
                                        'disabled' => $disabled2,
                                        'desc' => __( 'The E-Mail Address of the route account', 'h3-mgmt' )
                                ),
                                array(
                                        'type' => 'password',
                                        'label' => __( 'Password', 'h3-mgmt' ),
                                        'id' => 'route_account_pw1',
                                        'disabled' => $disabled2,
                                        'desc' => __( 'The login password of the route account', 'h3-mgmt' )
                                ),
                                array(
                                        'type' => 'password',
                                        'label' => __( 'Repeat password', 'h3-mgmt' ),
                                        'id' => 'route_account_pw2',
                                        'disabled' => $disabled2,
                                        'desc' => __( 'Repeat the login password of the route account', 'h3-mgmt' )
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
			
			$json_array =  json_decode( $data['setting'], true );
			if( !empty($json_array) ){
				$json_array_keys = array_keys($json_array);
				$json_id = 0;
				foreach( $json_array as $json ) {
					$data[$json_array_keys[$json_id]] = $json;
					$json_id = $json_id + 1;
				}
			}

			$json_array =  json_decode( $data['information_text'], true );
			if( !empty($json_array) ){
				$json_array_keys = array_keys($json_array);
				$json_id = 0;
				foreach( $json_array as $json ) {
					$data[$json_array_keys[$json_id]] = $json;
					$json_id = $json_id + 1;
				}
			}
                   
			$user_info = get_userdata( $h3_mgmt_races->get_route_account( $id ) );
                        
			$mcount = count($fields);
			for ( $i = 0; $i < $mcount; $i++ ) {
				$fcount = count($fields[$i]['fields']);
				for ( $j = 0; $j < $fcount; $j++ ) {
					if ( empty( $_POST['submitted'] ) ) {
                                                if( $fields[$i]['fields'][$j]['id'] == 'route_account_login_name' ){
                                                    $fields[$i]['fields'][$j]['value'] = $user_info->user_login;
                                                }
                                                elseif( $fields[$i]['fields'][$j]['id'] == 'route_account_id' ){
                                                    $fields[$i]['fields'][$j]['value'] = $user_info->ID;
                                                }
                                                elseif( $fields[$i]['fields'][$j]['id'] == 'route_account_email' ){
                                                    $fields[$i]['fields'][$j]['value'] = $user_info->user_email;
                                                }
                                                elseif( $fields[$i]['fields'][$j]['id'] == 'route_account_pw1' ){
                                                    $fields[$i]['fields'][$j]['value'] = $user_info->user_pass;
                                                }
                                                elseif( $fields[$i]['fields'][$j]['id'] == 'route_account_pw2' ){
                                                    $fields[$i]['fields'][$j]['value'] = $user_info->user_pass;
                                                }
						elseif ( $fields[$i]['fields'][$j]['type'] !== 'date' ) {
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