<?php

/**
 * H3_MGMT_Admin_Statistics class.
 *
 * This class contains properties and methods for
 * statistics in the administration backend.
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Statistics' ) ) :

class H3_MGMT_Admin_Statistics {

    public function statistics() {
		global $h3_mgmt_teams, $h3_mgmt_races, $wpdb;

                if( isset($_POST['race_id']) && $_POST['race_id'] != NULL ){
                    $race_id = $_POST['race_id'];
                }else{
                    $race_id = $h3_mgmt_races->get_active_race();
                }
                
                $url = 'admin.php?page=h3-mgmt-statistics';
		$form_action = $url;
                
                $adminpage = new H3_MGMT_Admin_Page( array(
			'icon' => 'icon-teams',
			'title' => 'Statistics for "'. $h3_mgmt_races->get_name($race_id). '"',
			'url' => $url
		));
                
		$args = array(
			'echo' => false,
			'form' => true,
			'metaboxes' => true,
			'action' => $form_action,
			'id' => $race_id,
			'back' => false,
			'back_url' => $url,
                        'top_button' => false,
                        'button' => 'Load',
			'fields' => $this->fields()
		);
                
		$form = new H3_MGMT_Admin_Form( $args );
		$output = $adminpage->top() . $form->output();
                
		$simcount_all_yes = 0;
		$simcount_all_no = 0;
		$simcount_all_null = 0;
		$simcount_nocomplete = 0;
		$participants_count = 0;
		
		$participants = $h3_mgmt_teams->get_participant_ids($race_id);
                
		$head1 .= 'Im Moment<br />(inkl. Teilnehmer mit unvollständigen Teams, exclusive Teilnehmer, die die Shirtgröße noch nicht gewählt haben)';
		$sizes = array( 'all' => 0 );
		
		foreach($participants as $participant) {
			$size = get_user_meta( $participant, 'shirt_size', true);
			$sim = get_user_meta( $participant, 'public_mobile_inf', true);

			$participants_count = $participants_count +1;
			if($sim == yes) {
				$simcount_all_yes = $simcount_all_yes + 1;
			}
			if($sim == no) {
				$simcount_all_no = $simcount_all_no + 1;
			}
			if( in_array( $size, array('gl','gm','gs','ms','mm','ml','mx') ) ) {
				if( ! array_key_exists( $size, $sizes ) ) {
					$sizes[$size] = 1;
				} else {
					$sizes[$size] = $sizes[$size] + 1;
				}
				$sizes['all'] = $sizes['all'] + 1;
			}
		}
		
		$simcount_all_null = $participants_count - ($simcount_all_yes + $simcount_all_no);
	
		//print_r($size);
		$output1 .= '<P>';
		$output1 .= 'Alle Teilnehmer:&nbsp;&nbsp;' . $participants_count . '<br><br> Davon haben T-Shirts ausgewählt<br>';
		foreach( $sizes as $key => $count ) {
			$output1 .= $key . ':&nbsp;&nbsp;' . $count . '<br>';
		}
		$output1 .= '<br>Sim-Karten - JA:&nbsp;&nbsp;' . $simcount_all_yes . '<br>';
		$output1 .= 'Sim-Karten - Nein:&nbsp;&nbsp;' . $simcount_all_no . '<br>';
		$output1 .= 'Sim-Karten - nicht gewählt:&nbsp;&nbsp;' . $simcount_all_null . '</P>';
		
		$head2 .= 'Im Moment<br />(Nur Teinehmer die vollständig angemeldet sind)';
		$sizes = array( 'all' => 0 );
		
		
			
		$team_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teams " .
			"WHERE race_id = " . $race_id,
			ARRAY_A
		);
		
		$sizes = array( 'all' => 0 );
		$simcount_all_yes = 0;
		$simcount_all_no = 0;
		$simcount_all_null = 0;
		$simcount_nocomplete = 0;
		$participants_count = 0;
		$mobile_adresses = [];
		$mobile_adresses_nocomplete = [];
		// print_r($team_query);
		
		foreach($team_query as $team) {
			
			if($team[complete] == 1) { 		//print_r($teams);
							
				$participants = $wpdb->get_results(
					"SELECT * FROM " .
					$wpdb->prefix."h3_mgmt_teammates " .
					"WHERE team_id = " . $team[id],
					ARRAY_A
				);
				
				foreach($participants as $participant) {
					$size = get_user_meta( $participant[user_id], 'shirt_size', true);
					$sim = get_user_meta( $participant[user_id], 'public_mobile_inf', true);
					$participants_count = $participants_count +1;
					
					if($sim == no) {
						$simcount_all_no = $simcount_all_no + 1;
					}
					if($sim == yes) {
						$simcount_all_yes = $simcount_all_yes + 1;
						
						$mobile_adresses[$simcount_all_yes]['team'] = $team['team_name'];
						$mobile_adresses[$simcount_all_yes]['first_name'] = get_user_meta( $participant[user_id], 'first_name', true);
						$mobile_adresses[$simcount_all_yes]['last_name'] = get_user_meta( $participant[user_id], 'last_name', true);
						$mobile_adresses[$simcount_all_yes]['nickname'] = get_user_meta( $participant[user_id], 'nickname', true);
						$mobile_adresses[$simcount_all_yes]['addressMobile'] = get_user_meta( $participant[user_id], 'addressMobile', true);
					}
			
					if( in_array( $size, array('gl','gm','gs','ms','mm','ml','mx') ) ) {
						if( ! array_key_exists( $size, $sizes ) ) {
							$sizes[$size] = 1;
						} else {
							$sizes[$size] = $sizes[$size] + 1;
						}
						$sizes['all'] = $sizes['all'] + 1;
					}
				}
			}
			
			if($team[complete] == 0) { 		//print_r($teams);
			
				$participants = $wpdb->get_results(
					"SELECT * FROM " .
					$wpdb->prefix."h3_mgmt_teammates " .
					"WHERE team_id = " . $team[id],
					ARRAY_A
				);
				
				//print_r($participants); 
				foreach($participants as $participant) {
					$sim = get_user_meta( $participant[user_id], 'public_mobile_inf', true);
					
					if($sim == yes) {
						$simcount_nocomplete = $simcount_nocomplete + 1;
						
						$mobile_adresses_nocomplete[$simcount_nocomplete]['team'] = $team['team_name'];
						$mobile_adresses_nocomplete[$simcount_nocomplete]['first_name'] = get_user_meta( $participant[user_id], 'first_name', true);
						$mobile_adresses_nocomplete[$simcount_nocomplete]['last_name'] = get_user_meta( $participant[user_id], 'last_name', true);
						$mobile_adresses_nocomplete[$simcount_nocomplete]['nickname'] = get_user_meta( $participant[user_id], 'nickname', true);
						$mobile_adresses_nocomplete[$simcount_nocomplete]['addressMobile'] = get_user_meta( $participant[user_id], 'addressMobile', true);
					}
				}
			}
		}
		
		$simcount_all_null = $participants_count - ($simcount_all_yes + $simcount_all_no);
	
		$output2 .= '<P>';
		foreach( $sizes as $key => $count2 ) {
			$output2 .= $key . ':&nbsp;&nbsp;' . $count2 . '<br>';
		}
		$output2 .= '<br>Sim-Karten - JA:&nbsp;&nbsp;' . $simcount_all_yes . '<br>';
		$output2 .= 'Sim-Karten - Nein:&nbsp;&nbsp;' . $simcount_all_no . '<br>';
		$output2 .= 'Sim-Karten - nicht gewählt:&nbsp;&nbsp;' . $simcount_all_null . '</P>';
		
		$head3 .= 'Adressen für Simkarten<br />(Nur Teinehmer die vollständig angemeldet sind)';
		$output3 .= '<b>Anzahl: ' .count($mobile_adresses). '</b><hr><br><br>';
		$output3 .= '<p>';
		foreach( $mobile_adresses as $mobile_adresse) {
			$output3 .= 'Team:&nbsp;&nbsp;' . $mobile_adresse['team'] . '<br>';
			$output3 .= 'Name:&nbsp;&nbsp;' . $mobile_adresse['first_name'] . '<br>';
			$output3 .= 'Nachnahme:&nbsp;&nbsp;' . $mobile_adresse['last_name'] . '<br>';
			$output3 .= 'Nickname:&nbsp;&nbsp;' . $mobile_adresse['nickname'] . '<br>';
			$output3 .= 'Adresse:&nbsp;&nbsp;' . $mobile_adresse['addressMobile'] . '<br>';
			$output3 .= '<hr><br><br>';
			//print_r($mobile_adresse); 
		}
		
		$output3 .= '</p>';
                
		$head4 .= 'Adressen für Simkarten<br />(Nur Teinehmer die unvollständig angemeldet sind)';
		$output4 .= '<b>Anzahl: ' .count($mobile_adresses_nocomplete). '</b><hr><br><br>';
		$output4 .= '<p>';
		foreach( $mobile_adresses_nocomplete as $mobile_adresse_nocomplete) {
			$output4 .= 'Team:&nbsp;&nbsp;' . $mobile_adresse_nocomplete['team'] . '<br>';
			$output4 .= 'Name:&nbsp;&nbsp;' . $mobile_adresse_nocomplete['first_name'] . '<br>';
			$output4 .= 'Nachnahme:&nbsp;&nbsp;' . $mobile_adresse_nocomplete['last_name'] . '<br>';
			$output4 .= 'Nickname:&nbsp;&nbsp;' . $mobile_adresse_nocomplete['nickname'] . '<br>';
			$output4 .= 'Adresse:&nbsp;&nbsp;' . $mobile_adresse_nocomplete['addressMobile'] . '<br>';
			$output4 .= '<hr><br><br>';
			//print_r($mobile_adresse); 
		}
		$output4 .= '</p>';
                
                $boxes = array(
                    array(
                        'title' => $head1,
                        'fields' =>  array(
                            array(
                                'type' => 'content',
                                'value' => $output1
                                )
                            )
                            ),
                    array(
                        'title' => $head2,
                        'fields' =>  array(
                            array(
                                'type' => 'content',
                                'value' => $output2
                                )
                            )
                        ),
                    array(
                        'title' => $head3,
                        'fields' =>  array(
                            array(
                                'type' => 'content',
                                'value' => $output3
                                )
                            )
                        ),
                    array(
                        'title' => $head4,
                        'fields' =>  array(
                            array(
                                'type' => 'content',
                                'value' => $output4
                                )
                            )
                        )
                    );
                        
                        
                
                $args = array(
			'echo' => false,
			'form' => false,
			'metaboxes' => true,
			'action' => $form_action,
			'id' => $race_id,
			'back' => false,
			'back_url' => $url,
                        'top_button' => false,
                        'button' => 'Load',
			'fields' => $boxes
		);
                
		$form = new H3_MGMT_Admin_Form( $args );
		$output .= $form->output();
                
                echo $output;
	}

    /**
    * Returns an array of fields
    *
    * @since 1.0
    * @access private
    */
   private function fields( $type = 'race', $id = NULL ) {
        global $h3_mgmt_races;

        $route_fields = array(
            array(
                'title' => __( 'Which Race?', 'h3-mgmt' ),
                'fields' => array(
                    array(
                            'type' => 'select',
                            'label' => __( 'Event / Race', 'h3-mgmt' ),
                            'id' => 'race_id',
                            'desc' => __( 'The event this route belongs to', 'h3-mgmt' ),
                            'options' => $h3_mgmt_races->options_array( array( 'data' => 'race' ) )
                    )
                )
            )
        );
        
        return $route_fields;
   }
} // class

endif; // class exists
