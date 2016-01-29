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
	public function get_phones( $team_id = 0, $route_id = 'all', $gimme = 'phones', $race_id = 0 ) {
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
		
		$teams_query_buf = array();
		
		if( $race_id != 0 ) {
			foreach( $teams_query as $team ) {
				if ( $race_id == $team['race_id'] ) {
					$teams_query_buf[] = $team;
				}
			}
			$teams_query = $teams_query_buf;
		}

		$phones = array();
		$teams = array();

		foreach( $teams_query as $team ) {
			$phonesbuf = array();
			$team_phones = $team['team_phone'];			
			$pos = stripos($team_phones, ' ', 0);
			while ($pos !== false) {
				$phonesbuf[] =substr($team_phones, 0, $pos);		
				$team_phones = strpbrk($team_phones, ' ');
				$team_phones = ltrim($team_phones);
				$pos = stripos($team_phones, ' ', 0);
			}														
			$phonesbuf[] =$team_phones;
			
			foreach( $phonesbuf as $phone ) {
				$phones[] = $this->normalize_phone_number( $phone );
				$teams[$this->normalize_phone_number( $phone )] = $team['id'];
			}
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
	 * Shortcode Funktion to save Ticker messages via HP
	 *
	 * @since 1.0
	 * @access public
	 */
	 
	public function ticker_Page_controll( $atts = '' ) {
		global $wpdb, $h3_mgmt_teams;
		
		extract( shortcode_atts( array(
			'race' => 0
		), $atts ) );
		$race_id = $race;
		$ticker_user = 'Tramprennen';
		$tramprennen_ticker_phone = 999999999;
		
		$output .= '<div class="send-ticker-message" style="margin-left: auto;  margin-right: auto;	max-width: 500px;">' .                  //<div class="flex_column av_one_half   avia-builder-el-2  avia-builder-el-last">
			'<h3>' . _x( 'Send a Liveticker message', 'ticker', 'h3-mgmt' ) . '</h3><hr>';

		if( is_user_logged_in() ) { 
			
			$current_user = wp_get_current_user();
			$current_user = $current_user->user_login;
			//echo $current_user;
			
			$team_id = $h3_mgmt_teams->user_has_team($race_id);
			$phones = $this->get_phones($team_id);
			
			if ( ! empty($team_id) || $current_user==$ticker_user ) {
				if ( $h3_mgmt_teams->is_complete( $team_id ) || $current_user==$ticker_user ) {
					foreach ( $phones as $phone ) {
						if ( strlen($phone) > 5 ) {
							$is_phone = 1;
							$phone_is = $phone;
						}
					}	
					
					if ( $current_user==$ticker_user ) {
						$phone_is = $tramprennen_ticker_phone;
						$is_phone = 1;
					}
					
					if ( $is_phone ) {
						//--------------get message------------------------------------------------------------------------------------------
						if ( isset( $_GET['todo'] ) && $_GET['todo'] == 'send_message' ) 
						{
							$message = htmlspecialchars($_POST['message']);
							
							$wpdb->insert(
								$wpdb->prefix.'sms_ticker',
								array(
									'from' => $phone_is,
									'msg' => $message,
									'type' => 1,
									'timestamp' => time(),
									'race_id' => $race_id
								),
								array('%s', '%s', '%d', '%d')
							);
							
							$update_message = array(
								'type' => 'message',
								'message' => _x( 'Message has been sent.', 'Ticker Form', 'h3-mgmt' )
							);
							
							$output .= '<p class="' . $update_message['type'] . '">' . $update_message['message'] . '</p>';
							$output .= '<p align="center"> <a href="'. get_permalink (). '">Back</a> </p>';
						//--------------rotate picture------------------------------------------------------------------------------------------
						}elseif ( isset( $_GET['todo'] ) && $_GET['todo'] == 'rotate' ) {
							
							$save_pic_url = $_POST['ticker_pic_url'];
							$pic_url = get_site_url().'/'.$save_pic_url;
										
							if( isset( $_POST['left'] ) ) {
								$imagick = new Imagick(); 
								$file_handle_for_viewing_image_file = fopen($pic_url, 'rb');						
								$imagick->readImageFile($file_handle_for_viewing_image_file);
								$imagick->rotateImage(new ImagickPixel('none'), 270); 
								fclose($pic_url, 'rb');
								file_put_contents($save_pic_url, $imagick);
							}
							
							if( isset( $_POST['right'] ) ) {
								$imagick = new Imagick(); 
								$file_handle_for_viewing_image_file = fopen($pic_url, 'rb');						
								$imagick->readImageFile($file_handle_for_viewing_image_file);
								$imagick->rotateImage(new ImagickPixel('none'), 90); 
								fclose($pic_url, 'rb');
								file_put_contents($save_pic_url, $imagick);
							}
							
							if( isset( $_POST['180'] ) ) {
								$imagick = new Imagick(); 
								$file_handle_for_viewing_image_file = fopen($pic_url, 'rb');						
								$imagick->readImageFile($file_handle_for_viewing_image_file);
								$imagick->rotateImage(new ImagickPixel('none'), 180); 
								fclose($pic_url, 'rb');
								file_put_contents($save_pic_url, $imagick);
							}
							
							$update_message = array(
								'type' => 'message',
								'message' => _x( 'Picture has been rotated .', 'Ticker Form', 'h3-mgmt' )
							);
							
							$output .= '<p class="' . $update_message['type'] . '">' . $update_message['message'] . '</p>';
							$output .= '<p align="center"> <a href="'. get_permalink (). '">Back</a> </p>';
							
						//--------------get picture------------------------------------------------------------------------------------------	
						}elseif ( isset( $_GET['todo'] ) && $_GET['todo'] == 'send_picture' )  {
							
							$file = $_POST['image_name'];
							
							$message = htmlspecialchars($_POST['message']);
							
							$str_ending = substr($file, -3);
									
							if ( ! strcmp($str_ending, 'jpg') || ! strcmp($str_ending, 'gif') || ! strcmp($str_ending, 'png') ) {
								
								//unsauber keine harten URL
								//$ticker_pic_url = get_site_url().'/wp-content/uploads/ticker_images/images/'.$file;
								$ticker_pic_url = 'wp-content/uploads/ticker_images/images/'.$file;
								$wpdb->insert(
									$wpdb->prefix.'sms_ticker',
									array(
										'from' => $phone_is,
										'msg' => $message,
										'img_url' => $ticker_pic_url,
										'type' => 3,
										'timestamp' => time(),
										'race_id' => $race_id
									),
									array('%s', '%s', '%s', '%d', '%d') 
								);
								
								$update_message = array(
									'type' => 'message',
									'message' => _x( 'Picture has been sent.', 'Ticker Form', 'h3-mgmt' )
								);
							} else {
								$update_message = array(
									'type' => 'error',
									'message' => _x( 'Not the correct format.', 'Ticker Form', 'h3-mgmt' )
								);
							}
							$output .= '<p class="' . $update_message['type'] . '">' . $update_message['message'] . '</p>';
							$output .= '<img src="' .get_site_url().'/'.$ticker_pic_url. '" alt="" >';
							$output .= '
								<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=rotate">
								<input type="hidden" name="ticker_pic_url" value="'. $ticker_pic_url .'">
								<p align="center">
								<input style="float: left;" type="submit" id="left" name="left" value="rotate 90° left">
								<input style="" type="submit" id="180" name="180" value="rotate 180°">
								<input style="float: right;" type="submit" id="right" name="right" value="rotate 90° right">
								</p>
								<p align="center">
								<a href="'. get_permalink (). '">Back</a>
								</p>
								';
						//--------------get coordinates------------------------------------------------------------------------------------------
						}elseif ( isset( $_GET['todo'] ) && $_GET['todo'] == 'coordinates' )  {
							
							$coordinates = $_POST['coordinates'];
							$coordinates_informations = htmlspecialchars($_POST['coordinates_informations']);
							
							if( ! empty( $coordinates ) ) {
								$wpdb->insert(
									$wpdb->prefix.'sms_ticker',
									array(
										'from' => $phone_is,
										'msg' => $coordinates_informations,
										'img_url' => $coordinates,
										'type' => 4,
										'timestamp' => time(),
										'race_id' => $race_id
									),
									array('%s', '%s', '%s', '%d', '%d') 
								);
								
								$update_message = array(
									'type' => 'message',
									'message' => _x( 'Location has been sent.', 'Ticker Form', 'h3-mgmt' )
								);
							} else {
								$update_message = array(
									'type' => 'error',
									'message' => _x( 'ERROR', 'Ticker Form', 'h3-mgmt' )
								);
							}
							
							$output .= '<p class="' . $update_message['type'] . '">' . $update_message['message'] . '</p>';
							$output .= '<p align="center"> <a href="'. get_permalink (). '">Back</a> </p>';
						//--------------get video------------------------------------------------------------------------------------------
						}elseif ( isset( $_GET['todo'] ) && $_GET['todo'] == 'send_video' ) {
							$video = $_POST['video'];
							$video_message = htmlspecialchars($_POST['video_message']);
							
							$pos = strrpos ( $video , 'v=' );
							if ( !$pos === false ) {
								$video = substr($video, $pos + 2);
								
								$wpdb->insert(
									$wpdb->prefix.'sms_ticker',
									array(
										'from' => $phone_is,
										'msg' => $video_message,
										'img_url' => $video,
										'type' => 5,
										'timestamp' => time(),
										'race_id' => $race_id
									),
									array('%s', '%s', '%s', '%d', '%d')
								);
								
								$update_message = array(
									'type' => 'message',
									'message' => _x( 'Video has been sent.', 'Ticker Form', 'h3-mgmt' )
								);								
							} else {
								$update_message = array(
									'type' => 'error',
									'message' => _x( 'Not the correct URL / Link.', 'Ticker Form', 'h3-mgmt' )
								);
							}
							$output .= '<p class="' . $update_message['type'] . '">' . $update_message['message'] . '</p>';
							$output .= '<p align="center"> <a href="'. get_permalink (). '">Back</a> </p>';
						}else{
							//--------------send message------------------------------------------------------------------------------------------
							$output .= '<form name="h3_mgmt_ticker_form" accept-charset="UTF-8" method="post" enctype="multipart/form-data" action="?todo=send_message">';
							$output .= '<h5>' . _x( 'Send a message', 'ticker', 'h3-mgmt' ) . '</h5>';
							
							$fields = array(
								array (
									'label'	=> _x( 'Message', 'Ticker Form', 'h3-mgmt' ),
									'desc'	=> _x( 'Send a message like a SMS.', 'Ticker Form', 'h3-mgmt' ),
									'id'	=> 'message',
									'type'	=> 'textarea'
								)
							);
							
							require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							
							$output .= '<div class="form-row">' .
							'<input type="submit" id="submit_form" name="submit_form" value="';
							$output .= _x( 'Send message', 'Ticker Form', 'h3-mgmt' );
							$output .= '" /></div></form><br><br><hr>';
							
							//--------------send picture------------------------------------------------------------------------------------------
							$output .= '<h5>' . _x( 'Send a picture + message', 'ticker', 'h3-mgmt' ) . '</h5>';
							
							$output .= '<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=send_picture">';
							
							// $fields = array(
								// array(
									// 'label'	=> _x( 'Picture', 'Ticker Form', 'h3-mgmt' ),
									// 'id'	=> 'photo',
									// 'type'	=> 'single-pic-upload',
									// 'desc'	=> _x( "This picture will appear in the ticker. You may upload .jpeg, .gif or .png files.", 'Ticker Form', 'h3-mgmt' )
								// )
							// );
							
							// require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							//<input type="file" accept="image/jpeg, image/png, image/gif"  />
							$output .= '
								<div class="form-row">
								<label for="photo">Picture</label>
								<input type="file" accept="image/jpeg, image/png, image/gif"  />
								<span class="description">This picture will appear in the ticker. You may upload .jpeg, .gif or .png files.</span>
								</div>
								<p id="image" style="color:grey;">No picture chosen or wrong format.</p>
								<p id="image_loading" style="color:red;"></p>
								<p id="image_ready" style="color:green;"></p>
							';
							
							$fields = array(
								array (
									'label'	=> _x( 'Message for picture', 'Ticker Form', 'h3-mgmt' ),
									'desc'	=> _x( 'Send a message under your picture.', 'Ticker Form', 'h3-mgmt' ),
									'id'	=> 'message',
									'type'	=> 'textarea'
								)
							);
							
							require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							
							
							$output .= '<p id="image_hidden"></p>';	
							// $output .= '<p id="image_hidden2"></p>';
							$output .= '<p id="image_send"></p>';
							$output .= '</form>';
							
							wp_enqueue_script( 'h3-mgmt-blob' );
							wp_enqueue_script( 'h3-mgmt-resize' );
							wp_enqueue_script( 'h3-mgmt-app' );
							wp_localize_script('h3-mgmt-app', 'app_vars', array(
								'url_base' => get_site_url(),
								'team_id' => $team_id
								)
							);
							
							//--------------send location------------------------------------------------------------------------------------------
							$output .= '<hr><h5>' . _x( 'Send your location', 'ticker', 'h3-mgmt' ) . '</h5>';
							$output .= '
								<button onclick="getLocation()">Get coordinates</button>
								<p>Click the button to get your coordinates.</p>
								<p id="coordinates_loading" style="color:red;"></p>
								<p id="coordinates_position" style="color:grey;"></p>
								<p id="coordinates" style="color:grey;"></p>
							';
							
							$output .= '<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=coordinates">';
							
							$fields = array(
								array (
									'label'	=> _x( 'Informations about position', 'Ticker Form', 'h3-mgmt' ),
									'desc'	=> _x( 'Information about your coordinates like city, lake or area.', 'Ticker Form', 'h3-mgmt' ),
									'id'	=> 'coordinates_informations',
									'type'	=> 'textarea'
								)
							);
							
							require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							
							$output .= '<p id="coordinates_hidden"></p>';
							$output .= '<p id="coordinates_send"></p>';
							$output .= '</form>';
							
							wp_enqueue_script( 'h3-mgmt-location' );
							
							//--------------send video via youtube------------------------------------------------------------------------------------------
							$output .= '<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=send_video">';
							$output .= '<hr><h5>' . _x( 'Send a youtube video', 'ticker', 'h3-mgmt' ) . '</h5>';
							
							$fields = array(
								array (
									'label'	=> _x( 'Video URL / Link', 'Ticker Form', 'h3-mgmt' ),
									'desc'	=> _x( 'Send a video via the video URL from youtube.', 'Ticker Form', 'h3-mgmt' ),
									'id'	=> 'video',
									'type'	=> 'text'
								),
								array (
									'label'	=> _x( 'Informations about your Video', 'Ticker Form', 'h3-mgmt' ),
									'desc'	=> _x( 'Information about your video.', 'Ticker Form', 'h3-mgmt' ),
									'id'	=> 'video_message',
									'type'	=> 'textarea'
								)
							);
							
							require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							
							$output .= '<div class="form-row">' .
							'<input type="submit" id="submit_form" name="submit_form" value="';
							$output .= _x( 'Send video code', 'Ticker Form', 'h3-mgmt' );
							$output .= '" /></div></form><br>';
							
						}
					} else {
						$output .= '<p class="error">' . _x( 'You must have entered a mobile number to send a Liveticker message.', 'XChange', 'h3-mgmt' ) . '</p>';
					}
				} else {
					$output .= '<p class="error">' . _x( 'Your team has to be complete registered to send a Liveticker message.', 'XChange', 'h3-mgmt' ) . '</p>';
				}
			} else {
				$output .= '<p class="error">' . _x( 'You must have a team for the present race to send a Liveticker message.', 'XChange', 'h3-mgmt' ) . '</p>';
			}
			
		} else {
			// $output .= '<p class="error">' . _x( 'You must be <a title="Log in" href="' .get_site_url(). '/login">logged in</a> to send a Liveticker message.', 'XChange', 'h3-mgmt' ) . '</p>';
			return do_shortcode( '[theme-my-login show_title=0]' );
		}

		$output .= '</div>';

		return $output;
	}
	
	/**
	 * Shortcode Funktion to show google maps with all locations of teams
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ticker_Page_map( $atts = '' ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

		extract( shortcode_atts( array(
			'route' => 'all',
			'race' => 0,
			'team' => 0,
			'show_nav' => 1,
			'coord_center_lat' => 54.0237934,
			'coord_center_lng' => 9.3754401
		), $atts ) );
		$race_id = $race;
		
		if( isset( $_GET['ticker_route'] ) ) {
			$route = $_GET['ticker_route'];
		}

		if( $team != 0 ) {
			$phones = $this->get_phones( $team );
		} elseif( $route != 'all' ) {
			$phones = $this->get_phones( 0, $route );
		} elseif( $race != 0 ) {
		$phones = $this->get_phones( 0, $route, 'phones', $race );
		} else {
			$phones = $this->get_phones();
		}

		$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."sms_ticker " .
			"WHERE type = 4" ,
			ARRAY_A
		);
		
		if( $race != 0 ) {
			$phones2team = $this->get_phones( 0, 'all', 'teams', $race );
		} else {
			$phones2team = $this->get_phones( 0, 'all', 'teams' );
		}

		$race_routes = $h3_mgmt_races->get_routes( array(
			'race' => $race,
			'orderby' => 'name',
			'order' => 'ASC'
		));
		
		$coordinates = array();
		// foreach( $messages_query as $coordination ) {
		// $coordinates []= $coordination['img_url'];
		// }
		date_default_timezone_set('Europe/Berlin');
		
		$i = 0;
		foreach( $messages_query as $message ) {
			$norm_num = $this->normalize_phone_number( $message['from'] );
			if ( ( $message['race_id'] == $race && ! empty( $message['img_url'] ) ) ) {	
				if( ! empty( $norm_num ) && in_array( $norm_num, $phones ) ) {
					$team_id = $phones2team[$norm_num];
					if( $h3_mgmt_teams->is_complete( $team_id ) ) {
						$team = $h3_mgmt_teams->get_team_data( $team_id );
						$display_data['team_id'] = $team_id;
						$display_data['route_image'] = get_site_url() . $race_routes[$team['route_id']]['logo_url'];
						$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
						$display_data['team_name'] = $h3_mgmt_teams->get_team_name( $team_id );
						$display_data['team_name_url'] = '<a style="border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
							'" href="' . get_site_url() . __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
							$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
						$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
						// if ( $message['type'] > 2 ) {
							// $display_data['img_path'] = $message['img_url'];
						// } else {
							// $display_data['img_url'] = 'http://tramprennen.org/wp-content/uploads/mms/' . $message['img_url'];
							// $display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
						// }
						$coordinates []= $message['img_url'];
						$display_data['message'] = stripslashes( $message['msg'] );
						// $display_data['type'] = $message['type'];
						$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
						$display_data['time'] = $message['timestamp'];
						$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
						$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
						$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
						$messages[] = $display_data;
						$i++;
					}
				}
			}
			if( $max != 0 && $i >= $max ) {
				break;
			}
		}
		// print_r($coordinates);		//test	
		// print_r($messages);		
		
		$output .= '<div class="ticker-page-map" style="margin-left: auto;  margin-right: auto;	max-width: 500px;">' .                  //<div class="flex_column av_one_half   avia-builder-el-2  avia-builder-el-last">
			'<h3>' . _x( 'See all locations of the teams', 'ticker', 'h3-mgmt' ) . '</h3><hr>';
		$output .= '</div>';
		
		if( $show_nav == 1 ) {
			$post = get_post();
			$post_url = get_page_link($post->ID);
			
			$output .= '<div class="isotope-wrap">' .
					'<ul class="isotope-link-list">' .
						'<li><a href="' .
									_x( $post_url, 'utility translation', 'h3-mgmt' ) .
							'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

						foreach ( $race_routes as $race_route ) {
							$output .= '<li><a href="' . 
									_x( $post_url, 'utility translation', 'h3-mgmt' ) .
									'?ticker_race=' . $race . '&ticker_route=' . $race_route['id'] .
								'" style="color:#' . $race_route[color_code]. ';">' . $race_route['name'] . '</a></li>';
						}

			$output .= '</ul>' .
				'</div>';
		}
		
		$output .= '<div id="map-canvas" style="height: 500px; margin: 0; padding: 0;"></div>';
		
		wp_enqueue_script( 'googlemap' );
		wp_enqueue_script( 'google-jsapi' );
		wp_enqueue_script( 'h3-mgmt-map' );
		
		
		wp_localize_script('h3-mgmt-map', 'app_vars', array(
			'coordinates' => $coordinates,
			'messages' => $messages,
			'coord_center_lat' => $coord_center_lat,
			'coord_center_lng' => $coord_center_lng
			)
		);

		
		

		return $output;
	}
	
	/**
	 * Shortcode Funktion to show google maps with all locations of teams
	 *
	 * @since 1.0
	 * @access public
	 */
	public function team_ticker_Page_map(  $team_id ) {
		global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

		$coord_center_lat = 54.0237934;
		$coord_center_lng = 9.3754401;
		
		
		$phones = $this->get_phones( $team_id );
		date_default_timezone_set('Europe/Berlin');
		
		$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."sms_ticker " .
			"WHERE type = 4" ,
			ARRAY_A
		);
		
		$team_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teams " .
			"WHERE id = " . $team_id . " LIMIT 1",
			ARRAY_A
		);

		$race_routes = $h3_mgmt_races->get_routes( array(
			'race' => $race,
			'orderby' => 'name',
			'order' => 'ASC'
		));
		
		$coordinates = array();
		$messages = array();
		//print_r( $messages_query );
		foreach( $messages_query as $message ) {
			$norm_num = $this->normalize_phone_number( $message['from'] );
			if ( ( $message['race_id'] ==  $team_query[0]['race_id'] && ! empty( $message['img_url'] ) ) ) {
				if( ! empty( $norm_num ) && in_array( $norm_num, $phones ) ) {
					// $team_id = $phones2team[$norm_num];
					$team = $h3_mgmt_teams->get_team_data( $team_id );
					$display_data['team_id'] = $team_id;
					$display_data['route_image'] = get_site_url() . $race_routes[$team['route_id']]['logo_url'];
					$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
					$display_data['team_name'] = $h3_mgmt_teams->get_team_name( $team_id );
					$display_data['team_name_url'] = '<a style="border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
						'" href="' . get_site_url(). __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
						$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
					$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
					// if ( $message['type'] > 2 ) {
						// $display_data['img_path'] = $message['img_url'];
					// } else {
						// $display_data['img_url'] = 'http://tramprennen.org/wp-content/uploads/mms/' . $message['img_url'];
						// $display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
					// }
					$coordinates []= $message['img_url'];
					$display_data['message'] = stripslashes( $message['msg'] );
					// $display_data['type'] = $message['type'];
					$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
					$display_data['time'] = $message['timestamp'];
					$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
					$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
					$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
					$messages[] = $display_data;
					$i++;
				}
			}
		}
		// print_r($coordinates);		//test	
		// print_r($messages);		
		
		$output .= '<div class="ticker-page-map" style="margin-left: auto;  margin-right: auto;	max-width: 500px;">';// .                  //<div class="flex_column av_one_half   avia-builder-el-2  avia-builder-el-last">
			//'<h3>' . _x( 'See all locations of the teams', 'ticker', 'h3-mgmt' ) . '</h3><hr>';
		$output .= '</div>';
		
		$output .= '<div id="map-canvas" style="height: 300px; margin: 0; padding: 0;"></div>';
		
		$output .= '<p>   </p>';
		
		wp_enqueue_script( 'googlemap' );
		wp_enqueue_script( 'google-jsapi' );
		wp_enqueue_script( 'h3-mgmt-map' );
		
		
		wp_localize_script('h3-mgmt-map', 'app_vars', array(
			'coordinates' => $coordinates,
			'messages' => $messages,
			'coord_center_lat' => $coord_center_lat,
			'coord_center_lng' => $coord_center_lng
			)
		);

		return $output;
	}
	
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
			'max' => 10,
			'route' => 'all',
			'race' => 0,
			'team' => 0,
			'show_nav' => 1
		), $atts ) );
		
		$tramprennen_ticker_phone = 999999999;
		date_default_timezone_set('Europe/Berlin');
		// $current_user = wp_get_current_user();
		// $current_user = $current_user->user_login;
		// if ( $current_user=='Tramprennen' ) {
			// echo date('l jS \of F Y h:i:s A');
		// }
		
		$min = 0;
		
		if( isset( $_GET['min'] ) ) {
			$min = $_GET['min'];
		}
		
		if( isset( $_GET['ticker_route'] ) ) {
			$route = $_GET['ticker_route'];
		}
		
		$max = $min + $max;

		if( $team != 0 ) {
			$phones = $this->get_phones( $team );
		} elseif( $route != 'all' ) {
			$phones = $this->get_phones( 0, $route );
		} elseif( $race != 0 ) {
		$phones = $this->get_phones( 0, $route, 'phones', $race );
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

		if( $race != 0 ) {
			$phones2team = $this->get_phones( 0, 'all', 'teams', $race );
		} else {
			$phones2team = $this->get_phones( 0, 'all', 'teams' );
		}

		$race_routes = $h3_mgmt_races->get_routes( array(
			'race' => $race,
			'orderby' => 'name',
			'order' => 'ASC'
		));
		
		$reach_max = 0;
		if ( $race < 4 ) {
			$i = 0;
			foreach( $messages_query as $message ) {
				$norm_num = $this->normalize_phone_number( $message['from'] );
				if ( ( $message['race_id'] < 4 && ! empty( $message['msg'] ) ) || ( $message['type'] > 2 && $message['race_id'] == $race ) ) {
					if( ! empty( $norm_num ) && in_array( $norm_num, $phones ) || $norm_num==$tramprennen_ticker_phone) {
						if( $i >= $min ) {
							if( $norm_num==$tramprennen_ticker_phone ) {
								if ( strlen($message['timestamp']) == 9 ) {
									$message['timestamp'] .= 0;
								}
								// $team_id = 999;
								$display_data['route_image'] = '/wp-content/plugins/cor-mgmt/img/tramprennen-ticker.jpg';
								$display_data['hex_color'] = "ff0000";
								$display_data['team_name'] = '<a style="color:#' . $display_data['hex_color'] . '; border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To Home", 'h3-mgmt' ) .
									'" href="' . get_site_url() . '">Tramprennen</a>';
								$display_data['mates'] = 'Tramprennen Orga-Team';
								if ( $message['type'] == 4 || $message['type'] == 5 ) {
									$display_data['img_path'] = $message['img_url'];
								} elseif ( $message['type'] == 3 ) {
									$display_data['img_path'] = get_site_url().$message['img_url'];
								} else {
									$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
									$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
								}
								$display_data['message'] = stripslashes( $message['msg'] );
								$display_data['type'] = $message['type'];
								$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
								$display_data['time'] = $message['timestamp'];
								$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
								$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
								$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
								$messages[] = $display_data;
								$i++;
							} else {
								$team_id = $phones2team[$norm_num];
								if( $h3_mgmt_teams->is_complete( $team_id ) ) {
									if ( strlen($message['timestamp']) == 9 ) {
										$message['timestamp'] .= 0;
									}
									$team = $h3_mgmt_teams->get_team_data( $team_id );
									$display_data['route_image'] = $race_routes[$team['route_id']]['logo_url'];
									$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
									$display_data['team_name'] = '<a style="border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
										'" href="' . get_site_url(). __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
										$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
									$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
									if ( $message['type'] == 4 || $message['type'] == 5 ) {
										$display_data['img_path'] = $message['img_url'];
									} elseif ( $message['type'] == 3 ) {
										$display_data['img_path'] = get_site_url().$message['img_url'];
									} else {
										$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
										$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
									}
									$display_data['message'] = stripslashes( $message['msg'] );
									$display_data['type'] = $message['type'];
									$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
									$display_data['time'] = $message['timestamp'];
									$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
									$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
									$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
									$messages[] = $display_data;
									$i++;
								}
							}
						}
					}
				}
				if( $max != 0 && $i >= $max ) {
					$reach_max = 1;
					break;
				}
			}
		} else {
			$i = 0;
			foreach( $messages_query as $message ) {
				$norm_num = $this->normalize_phone_number( $message['from'] );
				if ( ( $message['race_id'] == $race && ! empty( $message['msg'] ) ) || ( $message['type'] > 2 && $message['race_id'] == $race ) ) {			// || ( $message['type'] > 2 )
					if( ! empty( $norm_num ) && in_array( $norm_num, $phones ) || $norm_num==$tramprennen_ticker_phone ) {
						if( $i >= $min ) {
							if( $norm_num==$tramprennen_ticker_phone ) {
								if ( strlen($message['timestamp']) == 9 ) {
									$message['timestamp'] .= 0;
								}
								// $team_id = 999;
								$display_data['route_image'] = '/wp-content/plugins/cor-mgmt/img/tramprennen-ticker.jpg';
								$display_data['hex_color'] = "ff0000";
								$display_data['team_name'] = '<a style="color:#' . $display_data['hex_color'] . '; border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To Home", 'h3-mgmt' ) .
									'" href="' . get_site_url() . '">Tramprennen</a>';
								$display_data['mates'] = 'Tramprennen Orga-Team';
								if ( $message['type'] == 4 || $message['type'] == 5 ) {
									$display_data['img_path'] = $message['img_url'];
								} elseif ( $message['type'] == 3 ) {
									$display_data['img_path'] = get_site_url().$message['img_url'];
								} else {
									$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
									$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
								}
								$display_data['message'] = stripslashes( $message['msg'] );
								$display_data['type'] = $message['type'];
								$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
								$display_data['time'] = $message['timestamp'];
								$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
								$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
								$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
								$messages[] = $display_data;
							} else {
								$team_id = $phones2team[$norm_num];
								if( $h3_mgmt_teams->is_complete( $team_id ) ) {
									if ( strlen($message['timestamp']) == 9 ) {
										$message['timestamp'] .= 0;
									}
									$team = $h3_mgmt_teams->get_team_data( $team_id );
									$display_data['route_image'] = $race_routes[$team['route_id']]['logo_url'];
									$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
									$display_data['team_name'] = '<a style="border-color:#' . $display_data['hex_color'] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
										'" href="' . get_site_url(). __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
										$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
									$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
									if ( $message['type'] == 4 || $message['type'] == 5 ) {
										$display_data['img_path'] = $message['img_url'];
									} elseif ( $message['type'] == 3 ) {
										$display_data['img_path'] = get_site_url().$message['img_url'];
									} else {
										$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
										$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
									}
									$display_data['message'] = stripslashes( $message['msg'] );
									$display_data['type'] = $message['type'];
									$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
									$display_data['time'] = $message['timestamp'];
									$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
									$rgb = $h3_mgmt_utilities->hex2rgb( $display_data['hex_color'] );
									$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
									$messages[] = $display_data;
								}
							}
						}
						$i++;
					}
				}
				if( $max != 0 && $i >= $max ) {
					$reach_max = 1;
					break;
				}
			}
		}
		// echo $min .' - '. $max;
		
		
		if( $show_nav == 1 ) {
			$post = get_post();
			$post_url = get_page_link($post->ID);
			
			$output .= '<div class="isotope-wrap">' .
					'<ul class="isotope-link-list">' .
						'<li><a href="' .
									_x( $post_url, 'utility translation', 'h3-mgmt' ) .
							'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

						foreach ( $race_routes as $race_route ) {
							$output .= '<li><a href="' . 
									_x( $post_url, 'utility translation', 'h3-mgmt' ) .
									'?ticker_race=' . $race . '&ticker_route=' . $race_route['id'] .
								'">' . $race_route['name'] . '</a></li>';
						}

			$output .= '</ul>' .
				'</div>';
		}
		
		// $complete_url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
		// echo $min . ' + ' . $max;
		// echo $complete_url;
		
		$post = get_post();
		$post_url = get_page_link($post->ID);
		$extra = '';
		
		if( isset( $_GET['ticker_route'] ) ) {
			$route = $_GET['ticker_route'];
			$extra .='&ticker_route='. $route;
		}
		if( isset( $_GET['ticker_race'] ) ) {
			$race = $_GET['ticker_race'];
			$extra .='&ticker_race='. $race;
		}
		
		// $max_first = $max - 1;
		if( $min == 0 && !empty( $messages ) && $reach_max==1 ) {
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
					
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
					
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';	
		} elseif( $min == 0 && !empty( $messages ) && $reach_max==0 ) {
			require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
		} elseif( !empty( $messages ) && $reach_max==0 ) {
			$min_buff = $max-$min;
			$min = $min - $min_buff;
			$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
					
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
					
				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';	
		} elseif( empty( $messages ) && $min!=0 ) {
			$output .= '
					<p align="left">
					<a href="?min=0"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
		} elseif( empty( $messages ) && $min==0 ) {
			$output .= '<p align="center">' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
		} else {
			$min_buff = $max-$min;
			$min = $min - $min_buff;
			$output .= '
					<p align="right"> 
					<a style="float:left;" href="' .$post_url . '?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' .$post_url . '?min=' . $max . $extra .'"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
					
			require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
			
			$output .= '
					<p align="right"> 
					<a style="float:left;" href="' .$post_url . '?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' .$post_url . '?min=' . $max . $extra .'"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
		}
					
		return $output;
	}

	/**
	 * Team specific Ticker
	 *
	 * @since 1.0
	 * @access public
	 * @see constructor
	 */
	public function team_ticker( $team_id, $max = 10 ) {
		global $wpdb, $h3_mgmt_teams, $h3_mgmt_utilities, $h3_mgmt_races;
		
		$min = 0;
		
		if( isset( $_GET['min'] ) ) {
			$min = $_GET['min'];
		}
		
		$max = $min + $max;

		$phones = $this->get_phones( $team_id );
		
		date_default_timezone_set('Europe/Berlin');
		
		$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."sms_ticker " .
			"ORDER BY id DESC",
			ARRAY_A
		);
		
		$team_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix."h3_mgmt_teams " .
			"WHERE id = " . $team_id . " LIMIT 1",
			ARRAY_A
		);

		$race_routes = $h3_mgmt_races->get_routes( array(
			'race' => $race,
			'orderby' => 'name',
			'order' => 'ASC'
		));

		$messages = array();
		if ( $team_query[0]['race_id'] < 4 ) {
			$i = 0;
			foreach( $messages_query as $message ) {
				if ( ! empty( $message['msg'] ) ) {
					$norm_num = $this->normalize_phone_number( $message['from'] );
					if( in_array( $norm_num, $phones ) ) {
						if ( $message['race_id'] < 4 && ! empty( $message['msg'] )) {
							if( $i >= $min ) {
								if ( strlen($message['timestamp']) == 9 ) {
									$message['timestamp'] .= 0;
								}
								$team = $h3_mgmt_teams->get_team_data( $team_id );
								$display_data['route_image'] = $race_routes[$team['route_id']]['logo_url'];
								$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
								$display_data['team_name'] = '<a class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
									'" href="' . get_site_url(). __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
									$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
								$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
								if ( $message['type'] == 4 || $message['type'] == 5 ) {
									$display_data['img_path'] = $message['img_url'];
								} elseif ( $message['type'] == 3 ) {
									$display_data['img_path'] = get_site_url().$message['img_url'];
								} else {
									$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
									$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
								}
								$display_data['message'] = stripslashes( $message['msg'] );
								$display_data['type'] = $message['type'];
								$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
								$display_data['time'] = $message['timestamp'];
								$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
								$rgb = $h3_mgmt_utilities->hex2rgb( $h3_mgmt_teams->get_color_by_team( $team_id ) );
								$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
								$messages[] = $display_data;
							}
							$i++;
						}
					}
					if( $max != 0 && $i >= $max ) {
						$reach_max = 1;
						break;
					}
				}
			}
		} else {
			$i = 0;
			foreach( $messages_query as $message ) {
				if ( ! empty( $message['msg'] ) || ( $message['type'] > 2 ) ) {
					if ( $team_query[0]['race_id'] == $message['race_id'] ) {
						$norm_num = $this->normalize_phone_number( $message['from'] );
						if( in_array( $norm_num, $phones ) ) {
							if( $i >= $min ) {
								if ( strlen($message['timestamp']) == 9 ) {
									$message['timestamp'] .= 0;
								}
								$team = $h3_mgmt_teams->get_team_data( $team_id );
								$display_data['route_image'] = $race_routes[$team['route_id']]['logo_url'];
								$display_data['hex_color'] = $h3_mgmt_teams->get_color_by_team( $team_id );
								$display_data['team_name'] = '<a class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
									'" href="' . get_site_url(). __( '/follow-us/teams/?id=', 'h3-mgmt' ) . $team_id . '">' .
									$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
								$display_data['mates'] = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
								if ( $message['type'] == 4 || $message['type'] == 5 ) {
									$display_data['img_path'] = $message['img_url'];
								} elseif ( $message['type'] == 3 ) {
									$display_data['img_path'] = get_site_url().$message['img_url'];
								} else {
									$display_data['img_url'] = get_site_url().'/wp-content/uploads/mms/' . $message['img_url'];
									$display_data['img_path'] = ABSPATH . 'wp-content/uploads/mms/' . $message['img_url'];
								}
								$display_data['message'] = stripslashes( $message['msg'] );
								$display_data['type'] = $message['type'];
								$display_data['date'] = date( 'l, F jS G:i' , intval( $message['timestamp'] ) );
								$display_data['time'] = $message['timestamp'];
								$display_data['diff'] = $h3_mgmt_utilities->date_diff( $message['timestamp'],time() );
								$rgb = $h3_mgmt_utilities->hex2rgb( $h3_mgmt_teams->get_color_by_team( $team_id ) );
								$display_data['color'] = 'rgba(' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] . ', .85)';
								$messages[] = $display_data;
							}
							$i++;
						}
						if( $max != 0 && $i >= $max ) {
							$reach_max = 1;
							break;
						}
					}
				}
			}
		}
		
		$post = get_post();
		$post_url = get_page_link($post->ID);
		$extra = '';
		
		if( isset( $_GET['id'] ) ) {
			$id = $_GET['id'];
			$extra .='&id='. $id;
		}
		
		if( $min == 0 && !empty( $messages ) && $reach_max==1 ) {
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
					
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
					
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';	
		} elseif( $min == 0 && !empty( $messages ) && $reach_max==0 ) {
			require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
		} elseif( !empty( $messages ) && $reach_max==0 ) {
			$min_buff = $max-$min;
			$min = $min - $min_buff;
			$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
					
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
					
				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';	
		} elseif( empty( $messages ) && $min!=0 ) {
			$output .= '
					<p align="left">
					<a href="?min=0"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
		} elseif( empty( $messages ) && $min==0 ) {
			$output .= '<p align="center">' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
		} else {
			$min_buff = $max-$min;
			$min = $min - $min_buff;
			$output .= '
					<p align="right"> 
					<a style="float:left;" href="' .$post_url . '?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' .$post_url . '?min=' . $max . $extra .'"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
					
			require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
			
			$output .= '
					<p align="right"> 
					<a style="float:left;" href="' .$post_url . '?min=' . $min . $extra .'"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' .$post_url . '?min=' . $max . $extra .'"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
		}
  
		// if( ! empty( $messages ) ) {
			// require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
		// } else {
			// $output .= '<p>' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
		// }

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
	 * Shortcode Funktion to show google maps with all locations of teams
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ticker_send_message_link( $atts = '' ) {
		global $wpdb, $h3_mgmt_teams;
		
		extract( shortcode_atts( array(
			'url' => get_site_url(). '/send-ticker-message/',
			'race_id' =>1
		), $atts ) );
		
		if( is_user_logged_in() ) { 
				
			$team_id = $h3_mgmt_teams->user_has_team($race_id);
			$phones = $this->get_phones($team_id);
			
			if ( ! empty($team_id) ) {
				if ( $h3_mgmt_teams->is_complete( $team_id ) ) {
					
					$output .= '<p style="text-align: center; font-size: large; font-weight: bold;">' . _x( 'Send a <a title="Send a ticker message" href="' .$url. '">ticker message</a>!!!', 'ticker', 'h3-mgmt' ) . '</p>';
					
				}
			}
		}
		
		return $output;
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
		add_shortcode( 'h3-ticker-message', array( $this, 'ticker_Page_controll' ) );	
		add_shortcode( 'h3-ticker-map', array( $this, 'ticker_Page_map' ) );	
		add_shortcode( 'h3-ticker-send-message-link', array( $this, 'ticker_send_message_link' ) );
	}

} // class

endif; // class exists

?>
