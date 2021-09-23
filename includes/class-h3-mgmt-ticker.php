<?php

/**
 * H3_MGMT_Ticker class.
 *
 * This class contains properties and methods for the SMS/MMS Live-Ticker.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */
if ( !class_exists( 'H3_MGMT_Ticker' ) ) :

	class H3_MGMT_Ticker {
		/*		 * ************* UTILITY METHODS ************** */

		/**
		 * Returns a phone number without whitespaces, zeroes or a plus sign
		 *
		 * @since 1.0
		 * @access public
		 */
		public function normalize_phone_number( $number, $nice = false ) {

			if ( $nice === true ) {

				$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

				if ( mb_substr( $number, 0, 1 ) == '+' ) {
					$number = $number;
				} elseif ( mb_substr( $number, 0, 2 ) == '00' ) {
					$number = '+' . mb_substr( $number, 2 );
				} elseif ( mb_substr( $number, 0, 1 ) == '0' ) {
					$number = '+49' . mb_substr( $number, 1 );
				}

				$number = mb_substr( $number, 0, 3 ) . ' ' . mb_substr( $number, 3, 3 ) . ' ' . mb_substr( $number, 6, 3 ) . ' ' . mb_substr( $number, 9, 3 ) . ' ' . mb_substr( $number, 12 );
			} else {

				$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

				if ( mb_substr( $number, 0, 1 ) == '+' ) {
					$number = mb_substr( $number, 1 );
				} elseif ( mb_substr( $number, 0, 2 ) == '00' ) {
					$number = mb_substr( $number, 2 );
				} elseif ( mb_substr( $number, 0, 1 ) == '0' ) {
					$number = '49' . mb_substr( $number, 1 );
				}
			}

			return $number;
		}

		/**
		 * Returns an array of user_id  for ticker by team_id
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_user_by_team( $team_id ) {
			global $wpdb;

			$ids_querry	 = $wpdb->get_results(
			"SELECT user_id FROM " .
			$wpdb->prefix . "h3_mgmt_teammates " .
			"WHERE team_id = " . $team_id, ARRAY_A
			);
			$ids		 = array();
			foreach ( $ids_querry as $id ) {
				$ids[] = 'U_' . $id;
			}
			$ids[] = 'T_' . $team_id;

			return $ids;
		}

		/**
		 * Returns an array of Ticker Messages by a array of ticker user and 
		 * team ids.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_messages_by_ids( $ids, $race_id, $message_id = 0 ) {
			global $wpdb;

			if ( $ids != NULL ) {
				$where = 'WHERE (';
			}

			foreach ( $ids as $id ) {
				$where .= "send_from = '" . $id . "' OR ";
			}
			$where .= "send_from = 'dummy') AND race_id = '" . $race_id . "' ";

			if ( $message_id != 0 ) {
				$where .= "AND type = 4 ";
			}

			$messages_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix . "sms_ticker " .
			$where .
			" AND type < 100 ORDER BY id DESC", ARRAY_A
			);

			return $messages_query;
		}

		/**
		 * Returns an array of team phone numbers
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_phones( $team_id = 0, $route_id = 'all', $gimme = 'phones', $race_id = 0 ) {
			global $wpdb, $h3_mgmt_teams;

			if ( $route_id === 'all' && $team_id === 0 ) {
				$where = '';
			} elseif ( $team_id != 0 ) {
				$where = " WHERE id = " . $team_id;
			} else {
				$where = " WHERE route_id = " . $route_id;
			}

			$teams_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix . "h3_mgmt_teams" . $where, ARRAY_A
			);

			$teams_query_buf = array();

			if ( $race_id != 0 ) {
				foreach ( $teams_query as $team ) {
					if ( $race_id == $team[ 'race_id' ] ) {
						$teams_query_buf[] = $team;
					}
				}
				$teams_query = $teams_query_buf;
			}

			$phones	 = array();
			$teams	 = array();

			foreach ( $teams_query as $team ) {
				$phonesbuf	 = array();
				$team_phones = $team[ 'team_phone' ];
				$pos		 = stripos( $team_phones, ' ', 0 );
				while ( $pos !== false ) {
					$phonesbuf[] = substr( $team_phones, 0, $pos );
					$team_phones = strpbrk( $team_phones, ' ' );
					$team_phones = ltrim( $team_phones );
					$pos		 = stripos( $team_phones, ' ', 0 );
				}
				$phonesbuf[] = $team_phones;

				foreach ( $phonesbuf as $phone ) {
					$phones[]											 = $this->normalize_phone_number( $phone );
					$teams[ $this->normalize_phone_number( $phone ) ]	 = $team[ 'id' ];
				}
				$user_ids = $h3_mgmt_teams->get_teammates( $team[ 'id' ] );
				foreach ( $user_ids as $user_id ) {
					$phones[]																			 = $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) );
					$teams[ $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) ) ] = $team[ 'id' ];
				}
			}

			if ( $gimme === 'teams' ) {
				return $teams;
			} else {
				return $phones;
			}
		}

		/*		 * ************* TICKER OUTPUT ************** */

		/**
		 * Shortcode Funktion to save Ticker messages via HP
		 *
		 * @since 1.0
		 * @access public
		 */
		public function ticker_Page_controll( $atts = '' ) {
			global $wpdb, $h3_mgmt_teams, $h3_mgmt_races, $information_text;

			extract( shortcode_atts( array(
				'race'				=> 0,
				'comment'			=> false,
				'comment_get'		=> false
			), $atts ) );

			if ( $race == 'active' ) {
				$race = $h3_mgmt_races->get_active_race();
			}

			$ticker_user				 = 'Tramprennen';
			$tramprennen_ticker_phone	 = 999999999;

			$information_text = $h3_mgmt_races->get_race_information_text( $race );
			
			$race_setting = $h3_mgmt_races->get_race_setting( $race );
			//if registration still isn't open return error message
			if ( $race_setting[ 'liveticker' ] == 0 ) {
				$output .= '<p class="message" style="text-align: center;">' .
				stripcslashes( $information_text[ 23 ] ) .
				'</p>';
				$output .= '<br><br><br><br><br><br><br><br><br><br><br><br>';
				return $output;
			}
			
			if ( is_user_logged_in() ) {

				$current_user	 = wp_get_current_user();
				$current_user_id = $current_user->ID;
				$current_user	 = $current_user->user_login;

				$team_id = $h3_mgmt_teams->user_has_team( $race );

				if ( !empty( $team_id ) || $current_user == $ticker_user || $comment == true || $comment_get == true ) {
					if ( $h3_mgmt_teams->is_complete( $team_id ) || $current_user == $ticker_user || $comment == true || $comment_get == true ) {

						$phone_is = "U_" . $current_user_id;

						if ( $current_user == $ticker_user ) {
							$phone_is	 = $tramprennen_ticker_phone;
							$is_phone	 = 1;
						}
						
						//--------------get message------------------------------------------------------------------------------------------
						if ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'send_message' && $comment == false ) {
							$message = htmlspecialchars( $_POST[ 'message' ] );
							
							$wpdb->insert(
							$wpdb->prefix . 'sms_ticker', array(
								'send_from'	 => $phone_is,
								'msg'		 => $message,
								'type'		 => 1,
								'timestamp'	 => time(),
								'race_id'	 => $race
							), array( '%s', '%s', '%d', '%d' )
							);

							$update_message = array(
								'type'		 => 'message',
								'message'	 => _x( 'Message has been sent.', 'Ticker Form', 'h3-mgmt' )
							);

							$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
							$output .= '<p align="center"> <a href="' . get_permalink() . '">Back</a> </p>';
							//--------------get comment message------------------------------------------------------------------------------------------
						} elseif ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'send_message_comment' && $comment == false ) {
							$message = htmlspecialchars( $_POST[ 'message' ] );
							
							$wpdb->insert(
							$wpdb->prefix . 'sms_ticker', array(
								'send_from'		 => $phone_is,
								'msg'			=> $message,
								'type'			=> 100,
								'timestamp'		=> time(),
								'race_id'		=> $race,
								'img'			=> $_POST[ 'ticker_id' ]
							), array( '%s', '%s', '%d', '%d', '%s' )
							);

							$update_message = array(
								'type'		 => 'message',
								'message'	 => _x( 'Comment has been sent.', 'Ticker Form', 'h3-mgmt' )
							);

							$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
							$output .= '<p align="center"> <a href="' . get_permalink() . '">Back</a> </p>';
							//--------------rotate picture------------------------------------------------------------------------------------------
						} elseif ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'rotate'  && $comment == false ) {

							$rest = substr( $_POST[ 'ticker_pic_url' ], -4 );

							if ( isset( $_POST[ 'left' ] ) ) {
								$new_save_pic_url					 = mb_strcut( $_POST[ 'ticker_pic_url' ], 0, (mb_strlen( $_POST[ 'ticker_pic_url' ] ) - 4 ) ) . '-left' . $rest;
								$pic_url							 = get_site_url() . '/' . $_POST[ 'ticker_pic_url' ];
								$imagick							 = new Imagick();
								$file_handle_for_viewing_image_file	 = fopen( $pic_url, 'rb' );
								$imagick->readImageFile( $file_handle_for_viewing_image_file );
								$imagick->rotateImage( new ImagickPixel( 'none' ), 270 );
								fclose( $pic_url, 'rb' );
								file_put_contents( $new_save_pic_url, $imagick );
							}

							if ( isset( $_POST[ 'right' ] ) ) {
								$new_save_pic_url					 = mb_strcut( $_POST[ 'ticker_pic_url' ], 0, (mb_strlen( $_POST[ 'ticker_pic_url' ] ) - 4 ) ) . '-right' . $rest;
								$pic_url							 = get_site_url() . '/' . $_POST[ 'ticker_pic_url' ];
								$imagick							 = new Imagick();
								$file_handle_for_viewing_image_file	 = fopen( $pic_url, 'rb' );
								$imagick->readImageFile( $file_handle_for_viewing_image_file );
								$imagick->rotateImage( new ImagickPixel( 'none' ), 90 );
								fclose( $pic_url, 'rb' );
								file_put_contents( $new_save_pic_url, $imagick );
							}

							if ( isset( $_POST[ '180' ] ) ) {
								$new_save_pic_url					 = mb_strcut( $_POST[ 'ticker_pic_url' ], 0, (mb_strlen( $_POST[ 'ticker_pic_url' ] ) - 4 ) ) . '-180' . $rest;
								$pic_url							 = get_site_url() . '/' . $_POST[ 'ticker_pic_url' ];
								$imagick							 = new Imagick();
								$file_handle_for_viewing_image_file	 = fopen( $pic_url, 'rb' );
								$imagick->readImageFile( $file_handle_for_viewing_image_file );
								$imagick->rotateImage( new ImagickPixel( 'none' ), 180 );
								fclose( $pic_url, 'rb' );
								file_put_contents( $new_save_pic_url, $imagick );
							}

							$update_message = array(
								'type'		 => 'message',
								'message'	 => _x( 'Picture has been rotated .', 'Ticker Form', 'h3-mgmt' )
							);
							$wpdb->update(
							$wpdb->prefix . "sms_ticker", array(
								'img_url' => $new_save_pic_url,
							), array( 'img_url' => $_POST[ 'ticker_pic_url' ] ), array(
								'%s'
							), array( '%s' )
							);

							$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
							$output .= '<img src="' . get_site_url() . '/' . $new_save_pic_url . '" alt="" >';
							$output .= '
										<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="">
										<input type="hidden" name="ticker_pic_url" value="' . $new_save_pic_url . '">
										<p align="center">
										<input type="submit" id="left" name="left" value="rotate 90° left"></br></br>
										<input style="" type="submit" id="180" name="180" value="rotate 180°"></br></br>
										<input type="submit" id="right" name="right" value="rotate 90° right"></br></br>
										<a href="' . get_permalink() . '">Back</a>
										</p>
										</form>
										';

							//--------------get picture------------------------------------------------------------------------------------------	
						} elseif ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'send_picture'  && $comment == false ) {

							$file = $_POST[ 'image_name' ];

							$message = htmlspecialchars( $_POST[ 'message' ] );

							$str_ending = substr( $file, -3 );

							if ( !strcmp( $str_ending, 'jpg' ) || !strcmp( $str_ending, 'gif' ) || !strcmp( $str_ending, 'png' ) ) {

								//unsauber keine harten URL
								//$ticker_pic_url = get_site_url().'/wp-content/uploads/ticker_images/images/'.$file;
								$ticker_pic_url = 'wp-content/uploads/ticker_images/images/' . $file;

								if ( filesize( $ticker_pic_url ) > 0 ) {

									$wpdb->insert(
									$wpdb->prefix . 'sms_ticker', array(
										'send_from'	 => $phone_is,
										'msg'		 => $message,
										'img_url'	 => $ticker_pic_url,
										'type'		 => 3,
										'timestamp'	 => time(),
										'race_id'	 => $race
									), array( '%s', '%s', '%s', '%d', '%d' )
									);

									$update_message = array(
										'type'		 => 'message',
										'message'	 => _x( 'Picture has been sent.', 'Ticker Form', 'h3-mgmt' )
									);
									$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
									$output .= '<img src="' . get_site_url() . '/' . $ticker_pic_url . '" alt="" >';
									$output .= '
												<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=rotate">
												<input type="hidden" name="ticker_pic_url" value="' . $ticker_pic_url . '">
												<p align="center">
												<input type="submit" id="left" name="left" value="rotate 90° left"></br></br>
												<input style="" type="submit" id="180" name="180" value="rotate 180°"></br></br>
												<input type="submit" id="right" name="right" value="rotate 90° right"></br></br>
												<a href="' . get_permalink() . '">Back</a>
												</p>
												</form>
												';
								} else {
									$update_message = array(
										'type'		 => 'error',
										'message'	 => _x( 'There went something wrong, sorry! Please try again...', 'Ticker Form', 'h3-mgmt' )
									);
									$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
									$output .= '
                                                                <form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="' . get_permalink() . '">
                                                                <input type="hidden" name="ticker_pic_msg" value="' . $message . '">
                                                                <p align="center">
                                                                <input style="" type="submit" id="back" name="back" value="Back">
                                                                </p>
                                                                </form>
                                                                ';

									wp_mail( 'jonas@tramprennen.org', 'Image Error', get_site_url() . '/' . $ticker_pic_url . '   -----   ID:' . $phone_is );
								}
							} else {
								$update_message = array(
									'type'		 => 'error',
									'message'	 => _x( 'Not the correct format.', 'Ticker Form', 'h3-mgmt' )
								);
							}
							//--------------get coordinates------------------------------------------------------------------------------------------
						} elseif ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'coordinates'  && $comment == false) {

							$coordinates				 = $_POST[ 'coordinates' ];
							$coordinates_informations	 = htmlspecialchars( $_POST[ 'coordinates_informations' ] );

							if ( !empty( $coordinates ) ) {
								$wpdb->insert(
								$wpdb->prefix . 'sms_ticker', array(
									'send_from'	 => $phone_is,
									'msg'		 => $coordinates_informations,
									'img_url'	 => $coordinates,
									'type'		 => 4,
									'timestamp'	 => time(),
									'race_id'	 => $race
								), array( '%s', '%s', '%s', '%d', '%d' )
								);

								$update_message = array(
									'type'		 => 'message',
									'message'	 => _x( 'Location has been sent.', 'Ticker Form', 'h3-mgmt' )
								);
							} else {
								$update_message = array(
									'type'		 => 'error',
									'message'	 => _x( 'ERROR', 'Ticker Form', 'h3-mgmt' )
								);
							}

							$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
							$output .= '<p align="center"> <a href="' . get_permalink() . '">Back</a> </p>';
							//--------------get video------------------------------------------------------------------------------------------
						} elseif ( isset( $_GET[ 'todo' ] ) && $_GET[ 'todo' ] == 'send_video'  && $comment == false ) {
							$video			 = $_POST[ 'video' ];
							$video_message	 = htmlspecialchars( $_POST[ 'video_message' ] );

							$pos = strrpos( $video, 'v=' );
							if ( !$pos === false ) {
								$video = substr( $video, $pos + 2 );

								$wpdb->insert(
								$wpdb->prefix . 'sms_ticker', array(
									'send_from'	 => $phone_is,
									'msg'		 => $video_message,
									'img_url'	 => $video,
									'type'		 => 5,
									'timestamp'	 => time(),
									'race_id'	 => $race
								), array( '%s', '%s', '%s', '%d', '%d' )
								);

								$update_message = array(
									'type'		 => 'message',
									'message'	 => _x( 'Video has been sent.', 'Ticker Form', 'h3-mgmt' )
								);
							} else {
								$update_message = array(
									'type'		 => 'error',
									'message'	 => _x( 'Not the correct URL / Link.', 'Ticker Form', 'h3-mgmt' )
								);
							}
							$output .= '<p class="' . $update_message[ 'type' ] . '">' . $update_message[ 'message' ] . '</p>';
							$output .= '<p align="center"> <a href="' . get_permalink() . '">Back</a> </p>';
						} elseif( $comment_get == false ) {
							//--------------send message------------------------------------------------------------------------------------------
							
							if( !$comment ) {
								$output .= '<form name="h3_mgmt_ticker_form" accept-charset="UTF-8" method="post" enctype="multipart/form-data" action="?todo=send_message">';
								$output .= '<h5>' . _x( 'Send a message', 'ticker', 'h3-mgmt' ) . '</h5>';
							} else {
								$output .= '<form name="h3_mgmt_ticker_form" accept-charset="UTF-8" method="post" enctype="multipart/form-data" action="?todo=send_message_comment">';
								$output .= '<input type="hidden" id="ticker_id" name="ticker_id" value="">';
							}

							$fields = array(
								array(
									'label'	 => _x( 'Message', 'Ticker Form', 'h3-mgmt' ),
									'desc'	 => _x( 'Send a message like a SMS.', 'Ticker Form', 'h3-mgmt' ),
									'id'	 => 'message',
									'type'	 => 'textarea'
								)
							);

							require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );
							
							$output .= '<div class="form-row">' .
							'<input type="submit" id="submit_form" name="submit_form" value="';
							$output .= _x( 'Send message', 'Ticker Form', 'h3-mgmt' );
							$output .= '" /></div></form>';

							if( !$comment ) {
								//--------------send picture------------------------------------------------------------------------------------------
								$output .= '<hr><h5>' . _x( 'Send a picture + message', 'ticker', 'h3-mgmt' ) . '</h5>';

								$output .= '<form name="h3_mgmt_ticker_form" method="post" enctype="multipart/form-data" action="?todo=send_picture">';

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
									array(
										'label'	 => _x( 'Message for picture', 'Ticker Form', 'h3-mgmt' ),
										'desc'	 => _x( 'Send a message under your picture.', 'Ticker Form', 'h3-mgmt' ),
										'id'	 => 'message',
										'type'	 => 'textarea'
									)
								);

								if ( isset( $_POST[ 'ticker_pic_msg' ] ) ) {
									$fields[ 0 ][ 'value' ] = $_POST[ 'ticker_pic_msg' ];
								}

								require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );


								$output .= '<p id="image_hidden"></p>';
								$output .= '<p id="image_send"></p>';
								$output .= '</form>';

								wp_enqueue_script( 'h3-mgmt-blob' );
								wp_enqueue_script( 'h3-mgmt-resize' );
								wp_enqueue_script( 'h3-mgmt-app' );
								wp_localize_script( 'h3-mgmt-app', 'app_vars', array(
									'url_base'	 => get_site_url(),
									'team_id'	 => $team_id
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
									array(
										'label'	 => _x( 'Informations about position', 'Ticker Form', 'h3-mgmt' ),
										'desc'	 => _x( 'Information about your coordinates like city, lake or area.', 'Ticker Form', 'h3-mgmt' ),
										'id'	 => 'coordinates_informations',
										'type'	 => 'textarea'
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
									array(
										'label'	 => _x( 'Video URL / Link', 'Ticker Form', 'h3-mgmt' ),
										'desc'	 => _x( 'Send a video via the video URL from youtube.', 'Ticker Form', 'h3-mgmt' ),
										'id'	 => 'video',
										'type'	 => 'text'
									),
									array(
										'label'	 => _x( 'Informations about your Video', 'Ticker Form', 'h3-mgmt' ),
										'desc'	 => _x( 'Information about your video.', 'Ticker Form', 'h3-mgmt' ),
										'id'	 => 'video_message',
										'type'	 => 'textarea'
									)
								);

								require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

								$output .= '<div class="form-row">' .
								'<input type="submit" id="submit_form" name="submit_form" value="';
								$output .= _x( 'Send video code', 'Ticker Form', 'h3-mgmt' );
								$output .= '" /></div></form><br>';
							}
						}
					} else {
						$output .= '<p class="error" style="text-align: center;">' . _x( 'Your team has to be complete registered to send a Liveticker message.', 'Ticker Form', 'h3-mgmt' ) . '</p>';
					}
				} else {
					$output .= '<p class="error" style="text-align: center;">' . _x( 'You must have a team for the present race to send a Liveticker message.', 'Ticker Form', 'h3-mgmt' ) . '</p>';
				}
			} else {
				// $output .= '<p class="error">' . _x( 'You must be <a title="Log in" href="' .get_site_url(). '/login">logged in</a> to send a Liveticker message.', 'XChange', 'h3-mgmt' ) . '</p>';
				return do_shortcode( '[theme-my-login show_title=0]' );
			}

			// $output .= '</div>';

			return $output;
		}

		/**
		 * Shortcode Funktion to show google maps with all locations of teams
		 *
		 * @since 1.0
		 * @access public
		 */
		public function ticker_Page_map( $atts = '' ) {
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities, $information_text;

			extract( shortcode_atts( array(
				'route'				 => 'all',
				'race'				 => 0,
				'show_nav'			 => 1,
				'show_warning'		 => 1,
				'coord_center_lat'	 => 54.0237934,
				'coord_center_lng'	 => 9.3754401
			), $atts ) );

			$message_id = 4;

			if ( $race == 'active' ) {
				$race = $h3_mgmt_races->get_active_race();
			}

			$information_text = $h3_mgmt_races->get_race_information_text( $race );

			$race_setting = $h3_mgmt_races->get_race_setting( $race );
			// if registration still isn't open return error message
			if ( $race_setting[ 'liveticker_front' ] == 0 ) {
				if ( $show_warning == 1 ) {
					$output .= '<p class="message" style="text-align: center;">' .
					stripcslashes( $information_text[ 27 ] ) .
					'</p>';
					$output .= '<br><br><br><br><br><br><br><br><br><br><br><br>';
				}
				return $output;
			}

			if ( isset( $_GET[ 'ticker_route' ] ) ) {
				$route = $_GET[ 'ticker_route' ];
			}

			$ids = array();
			if ( $route != 'all' ) {

				$teams = $wpdb->get_results(
				"SELECT id FROM " .
				$wpdb->prefix . "h3_mgmt_teams " .
				"WHERE route_id = '" . $route . "' " .
				" ORDER BY id DESC", ARRAY_A
				);

				$race_id = $wpdb->get_var(
				"SELECT race_id FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				"WHERE id = '" . $route . "' " .
				" ORDER BY id DESC"
				);

				foreach ( $teams as $team ) {
					$ids = array_merge( $ids, $this->get_user_by_team( $team[ id ] ) );
				}

				$messages_query = $this->get_messages_by_ids( $ids, $race, $message_id );
			} elseif ( $race != 0 ) {

				$messages_query = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "sms_ticker " .
				"WHERE type = 4 AND race_id = '" . $race . "' " .
				" ORDER BY id DESC", ARRAY_A
				);
			} else {

				$messages_query = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "sms_ticker " .
				" WHERE type = 4 ORDER BY id DESC", ARRAY_A
				);
			}

			$race_routes = $h3_mgmt_races->get_routes( array(
				'race'		 => $race,
				'orderby'	 => 'name',
				'order'		 => 'ASC'
			) );

			$coordinates = array();

			date_default_timezone_set( 'Europe/Berlin' );

			foreach ( $messages_query as $message ) {
				if ( ( $message[ 'race_id' ] == $race && !empty( $message[ 'img_url' ] ) ) ) {
					if ( substr( $message[ 'send_from' ], 0, 2 ) == 'U_' ) {
						$team_id = $h3_mgmt_teams->get_team_by_user_and_race( substr( $message[ 'send_from' ], 2 ), $message[ 'race_id' ] );
					} else {
						$team_id = substr( $message[ 'send_from' ], 2 );
					}
					$team = $h3_mgmt_teams->get_team_data( $team_id );
					if ( $h3_mgmt_teams->is_complete( $team_id ) && $race_routes[ $team[ 'route_id' ] ][ 'id' ] == $team[ 'route_id' ] ) {
						$display_data[ 'team_id' ]		 = $team_id;
						$display_data[ 'route_image' ]	 = get_site_url() . $race_routes[ $team[ 'route_id' ] ][ 'logo_url' ];
						$display_data[ 'hex_color' ]	 = $h3_mgmt_teams->get_color_by_team( $team_id );
						$display_data[ 'team_name' ]	 = $h3_mgmt_teams->get_team_name( $team_id );
						$display_data[ 'team_name_url' ] = '<a style="border-color:#' . $display_data[ 'hex_color' ] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
						'" href="' . get_site_url() . $race_setting[ 'team_overview_link' ] . __( '?id=', 'h3-mgmt' ) . $team_id . '">' .
						$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
						$display_data[ 'mates' ]		 = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
						$coordinates[]					 = $message[ 'img_url' ];
						$display_data[ 'message' ]		 = stripslashes( $message[ 'msg' ] );
						$display_data[ 'date' ]			 = date( 'l, F jS G:i', intval( $message[ 'timestamp' ] ) );
						$display_data[ 'time' ]			 = $message[ 'timestamp' ];
						$display_data[ 'diff' ]			 = $h3_mgmt_utilities->date_diff( $message[ 'timestamp' ], time() );
						$rgb							 = $h3_mgmt_utilities->hex2rgb( $display_data[ 'hex_color' ] );
						$display_data[ 'color' ]		 = 'rgba(' . $rgb[ 0 ] . ', ' . $rgb[ 1 ] . ', ' . $rgb[ 2 ] . ', .85)';
						$messages[]						 = $display_data;
					}
				}
			}

			if ( $show_nav == 1 ) {
				$post		 = get_post();
				$post_url	 = get_page_link( $post->ID );

				$output .= '<div class="isotope-wrap">' .
				'<ul class="isotope-link-list">' .
				'<li><a href="' .
				_x( $post_url, 'utility translation', 'h3-mgmt' ) .
				'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

				foreach ( $race_routes as $race_route ) {
					$output .= '<li><a href="' .
					_x( $post_url, 'utility translation', 'h3-mgmt' ) .
					'?ticker_race=' . $race . '&ticker_route=' . $race_route[ 'id' ] .
					'" style="color:#' . $race_route[ color_code ] . ';">' . $race_route[ 'name' ] . '</a></li>';
				}

				$output .= '</ul>' .
				'</div>';
			}

			$output .= '<div id="map-canvas" style="height: 500px; margin: 0; padding: 0;"></div>';

			wp_enqueue_script( 'googlemap' );
			wp_enqueue_script( 'google-jsapi' );
			wp_enqueue_script( 'h3-mgmt-map' );


			wp_localize_script( 'h3-mgmt-map', 'app_vars', array(
				'coordinates'		 => $coordinates,
				'messages'			 => $messages,
				'coord_center_lat'	 => $coord_center_lat,
				'coord_center_lng'	 => $coord_center_lng
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
		public function team_ticker_Page_map( $team_id ) {
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities;

			$coord_center_lat	 = 54.0237934;
			$coord_center_lng	 = 9.3754401;
			$message_id			 = 4;

			date_default_timezone_set( 'Europe/Berlin' );

			$race_id		 = $h3_mgmt_teams->get_team_race( $team_id );
			$ids			 = $this->get_user_by_team( $team_id );
			$messages_query	 = $this->get_messages_by_ids( $ids, $race_id, $message_id );

			$race_setting = $h3_mgmt_races->get_race_setting( $race );

			$race_routes = $h3_mgmt_races->get_routes( array(
				'race'		 => $race_id,
				'orderby'	 => 'name',
				'order'		 => 'ASC'
			) );

			$coordinates = array();
			$messages	 = array();

			foreach ( $messages_query as $message ) {
				if ( ( $message[ 'race_id' ] == $race_id && !empty( $message[ 'img_url' ] ) ) ) {
					if ( substr( $message[ 'send_from' ], 0, 2 ) == 'U_' ) {
						$team_id = $h3_mgmt_teams->get_team_by_user_and_race( substr( $message[ 'send_from' ], 2 ), $message[ 'race_id' ] );
					} else {
						$team_id = substr( $message[ 'send_from' ], 2 );
					}
					if ( $h3_mgmt_teams->is_complete( $team_id ) && $race_routes[ $team[ 'route_id' ] ][ 'id' ] == $team[ 'route_id' ] ) {
						$team							 = $h3_mgmt_teams->get_team_data( $team_id );
						$display_data[ 'team_id' ]		 = $team_id;
						$display_data[ 'route_image' ]	 = get_site_url() . $race_routes[ $team[ 'route_id' ] ][ 'logo_url' ];
						$display_data[ 'hex_color' ]	 = $h3_mgmt_teams->get_color_by_team( $team_id );
						$display_data[ 'team_name' ]	 = $h3_mgmt_teams->get_team_name( $team_id );
						$display_data[ 'team_name_url' ] = '<a style="border-color:#' . $display_data[ 'hex_color' ] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
						'" href="' . get_site_url() . $race_setting[ 'team_overview_link' ] . __( '?id=', 'h3-mgmt' ) . $team_id . '">' .
						$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
						$display_data[ 'mates' ]		 = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
						$coordinates []					 = $message[ 'img_url' ];
						$display_data[ 'message' ]		 = stripslashes( $message[ 'msg' ] );
						$display_data[ 'date' ]			 = date( 'l, F jS G:i', intval( $message[ 'timestamp' ] ) );
						$display_data[ 'time' ]			 = $message[ 'timestamp' ];
						$display_data[ 'diff' ]			 = $h3_mgmt_utilities->date_diff( $message[ 'timestamp' ], time() );
						$rgb							 = $h3_mgmt_utilities->hex2rgb( $display_data[ 'hex_color' ] );
						$display_data[ 'color' ]		 = 'rgba(' . $rgb[ 0 ] . ', ' . $rgb[ 1 ] . ', ' . $rgb[ 2 ] . ', .85)';
						$messages[]						 = $display_data;
					}
				}
			}

			$output .= '<div class="ticker-page-map" style="margin-left: auto;  margin-right: auto;	max-width: 500px;">'; // .                  //<div class="flex_column av_one_half   avia-builder-el-2  avia-builder-el-last">

			$output .= '</div>';

			$output .= '<div id="map-canvas" style="height: 300px; margin: 0; padding: 0;"></div>';

			$output .= '<p>   </p>';

			wp_enqueue_script( 'googlemap' );
			wp_enqueue_script( 'google-jsapi' );
			wp_enqueue_script( 'h3-mgmt-map' );


			wp_localize_script( 'h3-mgmt-map', 'app_vars', array(
				'coordinates'		 => $coordinates,
				'messages'			 => $messages,
				'coord_center_lat'	 => $coord_center_lat,
				'coord_center_lng'	 => $coord_center_lng
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
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams, $h3_mgmt_utilities, $information_text;

			extract( shortcode_atts( array(
				'max'			 => 10,
				'route'			 => 'all',
				'race'			 => 0,
				'team'			 => 0,
				'show_warning'	 => 1,
				'show_nav'		 => 0
			), $atts ) );

			if ( $race === 'active' ) {
				$race = $h3_mgmt_races->get_active_race();
			}

			if ( $race != 0 ) {
				$information_text	 = $h3_mgmt_races->get_race_information_text( $race );
				$race_setting		 = $h3_mgmt_races->get_race_setting( $race );

				//if registration still isn't open return error message
				if ( $race_setting[ 'liveticker_front' ] == 0 ) {
					if ( $show_warning == 1 ) {
						$output .= '<p class="message" style="text-align: center;">' .
						stripcslashes( $information_text[ 27 ] ) .
						'</p>';
						$output .= '<br><br><br><br><br><br><br><br><br><br><br><br>';
					}
					return $output;
				}
			}

			if( isset( $_GET[ 'todo' ] ) ) {
				$output .= do_shortcode( '[h3-ticker-message race=' . $race . ' comment_get=false]' );
				return $output;
			}
			do_shortcode( '[h3-ticker-message race=' . $race . ']' );
			
			$tramprennen_ticker_phone = 999999999;
			date_default_timezone_set( 'Europe/Berlin' );

			$min = 0;

			if ( isset( $_GET[ 'min' ] ) ) {
				$min = $_GET[ 'min' ];
			}

			if ( isset( $_GET[ 'ticker_route' ] ) ) {
				$route = $_GET[ 'ticker_route' ];
			}

			$ids = array();
			if ( $team != 0 ) {

				$race_id = $h3_mgmt_teams->get_team_race( $team );

				$ids = $this->get_user_by_team( $team );

				$messages_query = $this->get_messages_by_ids( $ids, $race_id );
			} elseif ( $route != 'all' ) {

				$teams = $wpdb->get_results(
				"SELECT id FROM " .
				$wpdb->prefix . "h3_mgmt_teams " .
				"WHERE route_id = '" . $route . "' " .
				" ORDER BY id DESC", ARRAY_A
				);

				$race_id = $wpdb->get_var(
				"SELECT race_id FROM " .
				$wpdb->prefix . "h3_mgmt_routes " .
				"WHERE id = '" . $route . "' " .
				" ORDER BY id DESC"
				);

				foreach ( $teams as $team ) {
					$ids = array_merge( $ids, $this->get_user_by_team( $team[ id ] ) );
				}

				$messages_query = $this->get_messages_by_ids( $ids, $race_id );
			} elseif ( $race != 0 ) {

				$messages_query = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "sms_ticker " .
				"WHERE race_id = '" . $race . "' " .
				"AND type < 100 ORDER BY id DESC", ARRAY_A
				);
			} else {

				$messages_query = $wpdb->get_results(
				"SELECT * FROM " .
				$wpdb->prefix . "sms_ticker " .
				"AND type < 100 ORDER BY id DESC", ARRAY_A
				);
			}

			$race_routes = $h3_mgmt_races->get_routes( array(
				'race'		 => $race,
				'orderby'	 => 'name',
				'order'		 => 'ASC'
			) );

			$reach_max	 = 0;
			$count_all	 = 0;
			$count_shown = 0;
			foreach ( $messages_query as $message ) {
				if ( !empty( $message[ 'msg' ] ) || $message[ 'type' ] > 2 ) {
					if ( $message[ 'send_from' ] == $tramprennen_ticker_phone ) {
						$count_all++;
						if ( $count_all > $min ) {
							$count_shown++;
							$display_data = array();
							if ( strlen( $message[ 'timestamp' ] ) == 9 ) {
								$message[ 'timestamp' ] .= 0;
							}
							$display_data[ 'route_image' ]	 = '/wp-content/plugins/cor-mgmt/img/tramprennen-ticker.jpg';
							$display_data[ 'hex_color' ]	 = "ff0000";
							$display_data[ 'team_name' ]	 = '<a style="color:#' . $display_data[ 'hex_color' ] . '; border-color:#' . $display_data[ 'hex_color' ] . '" class="incognito-link ticker-message-team-name" title="' . __( "To Home", 'h3-mgmt' ) .
							'" href="' . get_site_url() . '">Tramprennen</a>';
							$display_data[ 'mates' ]		 = 'Tramprennen Orga-Team';
							if ( $message[ 'type' ] == 4 || $message[ 'type' ] == 5 ) {
								$display_data[ 'img_path' ] = $message[ 'img_url' ];
							} elseif ( $message[ 'type' ] == 3 ) {
								$display_data[ 'img_path' ] = get_site_url() . $message[ 'img_url' ];
							} else {
								$display_data[ 'img_url' ]	 = get_site_url() . '/wp-content/uploads/mms/' . $message[ 'img_url' ];
								$display_data[ 'img_path' ]	 = ABSPATH . 'wp-content/uploads/mms/' . $message[ 'img_url' ];
							}
							$display_data[ 'message' ]	 = stripslashes( $message[ 'msg' ] );
							$display_data[ 'type' ]		 = $message[ 'type' ];
							$display_data[ 'date' ]		 = date( 'l, F jS G:i', intval( $message[ 'timestamp' ] ) );
							$display_data[ 'time' ]		 = $message[ 'timestamp' ];
							$display_data[ 'diff' ]		 = $h3_mgmt_utilities->date_diff( $message[ 'timestamp' ], time() );
							$rgb						 = $h3_mgmt_utilities->hex2rgb( $display_data[ 'hex_color' ] );
							$display_data[ 'color' ]	 = 'rgba(' . $rgb[ 0 ] . ', ' . $rgb[ 1 ] . ', ' . $rgb[ 2 ] . ', .85)';
								
							$comment_query = array();
							$comment_query = $wpdb->get_results(
							"SELECT * FROM " .
							$wpdb->prefix . "sms_ticker " .
							"WHERE type > 99 AND img = '" . $message[ 'id' ] . "' ORDER BY id DESC", ARRAY_A
							);

							foreach ( $comment_query as $key => $comment ) {
								$user_id = substr( $comment[ 'send_from' ], 2 );
								$user = get_user_by( 'id', $user_id );

								if ( !empty( $comment[ 'msg' ] ) && $user != false ) {
									if ( strlen( $comment[ 'timestamp' ] ) == 9 ) {
										$comment[ 'timestamp' ] .= 0;
									}

									$display_data[ 'comments' ][$key][ 'name' ]			= $user->first_name . ' ' . $user->last_name;
									$display_data[ 'comments' ][$key][ 'message' ]		= stripslashes( $comment['msg'] );
									$display_data[ 'comments' ][$key][ 'time' ]			= $comment[ 'timestamp' ];
									$display_data[ 'comments' ][$key][ 'type' ]			= $comment[ 'type' ];
									$display_data[ 'comments' ][$key][ 'date' ]			= date( 'l, F jS G:i', intval( $comment[ 'timestamp' ] ) );
								}	
							}
							
							$messages[]					 = $display_data;
						}
					} else {
						if ( substr( $message[ 'send_from' ], 0, 2 ) == 'U_' ) {
							$team_id = $h3_mgmt_teams->get_team_by_user_and_race( substr( $message[ 'send_from' ], 2 ), $message[ 'race_id' ] );
						} else {
							$team_id = substr( $message[ 'send_from' ], 2 );
						}
						$team = $h3_mgmt_teams->get_team_data( $team_id );
						if ( $h3_mgmt_teams->is_complete( $team_id ) && $race_routes[ $team[ 'route_id' ] ][ 'id' ] == $team[ 'route_id' ] ) {
							$count_all++;
							if ( $count_all > $min ) {
								$count_shown++;
								$display_data = array();
								if ( strlen( $message[ 'timestamp' ] ) == 9 ) {
									$message[ 'timestamp' ] .= 0;
								}
								$display_data[ 'route_image' ]	 = $race_routes[ $team[ 'route_id' ] ][ 'logo_url' ];
								$display_data[ 'hex_color' ]	 = $h3_mgmt_teams->get_color_by_team( $team_id );
								$display_data[ 'team_name' ]	 = '<a style="border-color:#' . $display_data[ 'hex_color' ] . '" class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
								'" href="' . get_site_url() . $race_setting[ 'team_overview_link' ] . __( '?id=', 'h3-mgmt' ) . $team_id . '">' .
								$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
								$display_data[ 'mates' ]		 = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
								if ( $message[ 'type' ] == 4 || $message[ 'type' ] == 5 ) {
									$display_data[ 'img_path' ] = $message[ 'img_url' ];
								} elseif ( $message[ 'type' ] == 3 ) {
									$display_data[ 'img_path' ] = get_site_url() . $message[ 'img_url' ];
								} else {
									$display_data[ 'img_url' ]	 = get_site_url() . '/wp-content/uploads/mms/' . $message[ 'img_url' ];
									$display_data[ 'img_path' ]	 = ABSPATH . 'wp-content/uploads/mms/' . $message[ 'img_url' ];
								}
								$display_data[ 'message' ]	 = stripslashes( $message[ 'msg' ] );
								$display_data[ 'type' ]		 = $message[ 'type' ];
								$display_data[ 'date' ]		 = date( 'l, F jS G:i', intval( $message[ 'timestamp' ] ) );
								$display_data[ 'time' ]		 = $message[ 'timestamp' ];
								$display_data[ 'diff' ]		 = $h3_mgmt_utilities->date_diff( $message[ 'timestamp' ], time() );
								$rgb						 = $h3_mgmt_utilities->hex2rgb( $display_data[ 'hex_color' ] );
								$display_data[ 'color' ]	 = 'rgba(' . $rgb[ 0 ] . ', ' . $rgb[ 1 ] . ', ' . $rgb[ 2 ] . ', .85)';
								$display_data[ 'id' ]		 = $message[ 'id' ];
								
								$comment_query = array();
								$comment_query = $wpdb->get_results(
								"SELECT * FROM " .
								$wpdb->prefix . "sms_ticker " .
								"WHERE type > 99 AND img = '" . $message[ 'id' ] . "' ORDER BY id DESC", ARRAY_A
								);
								
								foreach ( $comment_query as $key => $comment ) {
									$user_id = substr( $comment[ 'send_from' ], 2 );
									$user = get_user_by( 'id', $user_id );
									
									if ( !empty( $comment[ 'msg' ] ) && $user != false ) {
										if ( strlen( $comment[ 'timestamp' ] ) == 9 ) {
											$comment[ 'timestamp' ] .= 0;
										}
										
										$display_data[ 'comments' ][$key][ 'name' ]			= $user->first_name . ' ' . $user->last_name;
										$display_data[ 'comments' ][$key][ 'message' ]		= stripslashes( $comment['msg'] );
										$display_data[ 'comments' ][$key][ 'time' ]			= $comment[ 'timestamp' ];
										$display_data[ 'comments' ][$key][ 'type' ]			= $comment[ 'type' ];
										$display_data[ 'comments' ][$key][ 'date' ]			= date( 'l, F jS G:i', intval( $comment[ 'timestamp' ] ) );
									}	
								}
								
								$messages[]					 = $display_data;
							}
						}
					}
				}
				if ( $max != 0 && $count_shown >= $max ) {
					$reach_max = 1;
					break;
				}
			}
			
			if ( $show_nav == 1 && is_array( $race_routes ) ) {
				$post		 = get_post();
				$post_url	 = get_page_link( $post->ID );

				$output .= '<div class="isotope-wrap">' .
				'<ul class="isotope-link-list">' .
				'<li><a href="' .
				_x( $post_url, 'utility translation', 'h3-mgmt' ) .
				'">' . __( 'All Routes', 'h3-mgmt' ) . '</a></li>';

				foreach ( $race_routes as $race_route ) {
					$output .= '<li><a href="' .
					_x( $post_url, 'utility translation', 'h3-mgmt' ) .
					'?ticker_race=' . $race . '&ticker_route=' . $race_route[ 'id' ] .
					'">' . $race_route[ 'name' ] . '</a></li>';
				}

				$output .= '</ul>' .
				'</div>';
			}

			$post		 = get_post();
			$post_url	 = get_page_link( $post->ID );
			$extra		 = '';

			if ( isset( $_GET[ 'ticker_route' ] ) ) {
				$route = $_GET[ 'ticker_route' ];
				$extra .='&ticker_route=' . $route;
			}
			if ( isset( $_GET[ 'ticker_race' ] ) ) {
				$race = $_GET[ 'ticker_race' ];
				$extra .='&ticker_race=' . $race;
			}

			$max = $min + $max;
			if ( $min == 0 && !empty( $messages ) && $reach_max == 1 ) {
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
			} elseif ( $min == 0 && !empty( $messages ) && $reach_max == 0 ) {
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
			} elseif ( !empty( $messages ) && $reach_max == 0 ) {
				$min_buff	 = $max - $min;
				$min		 = $min - $min_buff;
				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
			} elseif ( empty( $messages ) && $min != 0 ) {
				$output .= '
					<p align="left">
					<a href="?min=0"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
			} elseif ( empty( $messages ) && $min == 0 ) {
				$output .= '<p align="center">' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
			} else {
				$min_buff	 = $max - $min;
				$min		 = $min - $min_buff;
				$output .= '
					<p align="right"> 
					<a style="float:left;" href="' . $post_url . '?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' . $post_url . '?min=' . $max . $extra . '"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="right"> 
					<a style="float:left;" href="' . $post_url . '?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' . $post_url . '?min=' . $max . $extra . '"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
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

			if ( isset( $_GET[ 'min' ] ) ) {
				$min = $_GET[ 'min' ];
			}

			$race_id = $h3_mgmt_teams->get_team_race( $team_id );

			$race_setting = $h3_mgmt_races->get_race_setting( $race_id );

			date_default_timezone_set( 'Europe/Berlin' );

			$ids = $this->get_user_by_team( $team_id );

			$messages_query = $this->get_messages_by_ids( $ids, $race_id );

			$race_routes = $h3_mgmt_races->get_routes( array(
				'race'		 => $race,
				'orderby'	 => 'name',
				'order'		 => 'ASC'
			) );

			$messages = array();
			foreach ( $messages_query as $message ) {
				$team = $h3_mgmt_teams->get_team_data( $team_id );
				if ( (!empty( $message[ 'msg' ] ) || $message[ 'type' ] > 2 ) && $race_routes[ $team[ 'route_id' ] ][ 'id' ] == $team[ 'route_id' ] ) {
					$count_all++;
					if ( $count_all > $min ) {
						$count_shown++;
						$display_data = array();
						if ( strlen( $message[ 'timestamp' ] ) == 9 ) {
							$message[ 'timestamp' ] .= 0;
						}
						$display_data[ 'route_image' ]	 = $race_routes[ $team[ 'route_id' ] ][ 'logo_url' ];
						$display_data[ 'hex_color' ]	 = $h3_mgmt_teams->get_color_by_team( $team_id );
						$display_data[ 'team_name' ]	 = '<a class="incognito-link ticker-message-team-name" title="' . __( "To the team's profile", 'h3-mgmt' ) .
						'" href="' . get_site_url() . $race_setting[ 'team_overview_link' ] . __( '?id=', 'h3-mgmt' ) . $team_id . '">' .
						$h3_mgmt_teams->get_team_name( $team_id ) . '</a>';
						$display_data[ 'mates' ]		 = $h3_mgmt_teams->mate_name_string( $team_id, ', ', false );
						if ( $message[ 'type' ] == 4 || $message[ 'type' ] == 5 ) {
							$display_data[ 'img_path' ] = $message[ 'img_url' ];
						} elseif ( $message[ 'type' ] == 3 ) {
							$display_data[ 'img_path' ] = get_site_url() . $message[ 'img_url' ];
						} else {
							$display_data[ 'img_url' ]	 = get_site_url() . '/wp-content/uploads/mms/' . $message[ 'img_url' ];
							$display_data[ 'img_path' ]	 = ABSPATH . 'wp-content/uploads/mms/' . $message[ 'img_url' ];
						}
						$display_data[ 'message' ]	 = stripslashes( $message[ 'msg' ] );
						$display_data[ 'type' ]		 = $message[ 'type' ];
						$display_data[ 'date' ]		 = date( 'l, F jS G:i', intval( $message[ 'timestamp' ] ) );
						$display_data[ 'time' ]		 = $message[ 'timestamp' ];
						$display_data[ 'diff' ]		 = $h3_mgmt_utilities->date_diff( $message[ 'timestamp' ], time() );
						$rgb						 = $h3_mgmt_utilities->hex2rgb( $h3_mgmt_teams->get_color_by_team( $team_id ) );
						$display_data[ 'color' ]	 = 'rgba(' . $rgb[ 0 ] . ', ' . $rgb[ 1 ] . ', ' . $rgb[ 2 ] . ', .85)';
						$display_data[ 'id' ]		 = $message[ 'id' ];
								
						$comment_query = array();
						$comment_query = $wpdb->get_results(
						"SELECT * FROM " .
						$wpdb->prefix . "sms_ticker " .
						"WHERE type > 99 AND img = '" . $message[ 'id' ] . "' ORDER BY id DESC", ARRAY_A
						);

						foreach ( $comment_query as $key => $comment ) {
							$user_id = substr( $comment[ 'send_from' ], 2 );
							$user = get_user_by( 'id', $user_id );

							if ( !empty( $comment[ 'msg' ] ) && $user != false ) {
								if ( strlen( $comment[ 'timestamp' ] ) == 9 ) {
									$comment[ 'timestamp' ] .= 0;
								}

								$display_data[ 'comments' ][$key][ 'name' ]			= $user->first_name . ' ' . $user->last_name;
								$display_data[ 'comments' ][$key][ 'message' ]		= stripslashes( $comment['msg'] );
								$display_data[ 'comments' ][$key][ 'time' ]			= $comment[ 'timestamp' ];
								$display_data[ 'comments' ][$key][ 'type' ]			= $comment[ 'type' ];
								$display_data[ 'comments' ][$key][ 'id' ]			= $comment[ 'id' ];
								$display_data[ 'comments' ][$key][ 'date' ]			= date( 'l, F jS G:i', intval( $comment[ 'timestamp' ] ) );
							}	
						}
						
						$messages[]					 = $display_data;
					}
				}
				if ( $max != 0 && $count_shown >= $max ) {
					$reach_max = 1;
					break;
				}
			}

			$post		 = get_post();
			$post_url	 = get_page_link( $post->ID );
			$extra		 = '';

			if ( isset( $_GET[ 'id' ] ) ) {
				$id = $_GET[ 'id' ];
				$extra .='&id=' . $id;
			}

			$race = $race_id;
			
			$max = $min + $max;
			if ( $min == 0 && !empty( $messages ) && $reach_max == 1 ) {
				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="right">
					<a href="?min=' . $max . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
			} elseif ( $min == 0 && !empty( $messages ) && $reach_max == 0 ) {
				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );
			} elseif ( !empty( $messages ) && $reach_max == 0 ) {
				$min_buff	 = $max - $min;
				$min		 = $min - $min_buff;
				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="left">
					<a href="?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
			} elseif ( empty( $messages ) && $min != 0 ) {
				$output .= '
					<p align="left">
					<a href="?min=0"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					</p>
					';
			} elseif ( empty( $messages ) && $min == 0 ) {
				$output .= '<p align="center">' . __( 'No messages sent yet...', 'h3-mgmt' ) . '</p>';
			} else {
				$min_buff	 = $max - $min;
				$min		 = $min - $min_buff;
				$output .= '
					<p align="right"> 
					<a style="float:left;" href="' . $post_url . '?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' . $post_url . '?min=' . $max . $extra . '"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';

				require( H3_MGMT_ABSPATH . '/templates/frontend-ticker.php' );

				$output .= '
					<p align="right"> 
					<a style="float:left;" href="' . $post_url . '?min=' . $min . $extra . '"> <img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_links.png" style="max-width:30px;" alt="previous"></a>
					<a href="' . $post_url . '?min=' . $max . $extra . '"><img src="' . get_site_url() . '/wp-content/plugins/cor-mgmt/img/Pfeil_rechts.png" style="max-width:30px;" alt="next"></a>
					</p>
					';
			}

			return $output;
		}

		/**
		 * Shortcode callback to handle incoming messages
		 *
		 * @since 1.1
		 * @access public
		 */
		public function ticker_incoming_interface() {
			global $wpdb, $h3_mgmt_races, $h3_mgmt_teams;

			// Nachricht
			// message=%5B%7B%22img%22%3A%22%22%2C%22message%22%3A%22Hallo+Jonas.+Das+ist+ein+Test%22%2C%22with_img%22%3Afalse%2C%22time%22%3A1471466301735%2C%22number%22%3A%22004915788484508%22%7D%5D
			if ( !empty( $_POST[ 'message' ] ) || !empty( $_GET[ 'msg' ] ) ) {

				$race_id = $h3_mgmt_races->get_active_race();

				if ( !empty( $_POST[ 'message' ] ) ) {
					$message = stripcslashes( utf8_encode( $_POST[ 'message' ] ) );
				} else {
					$message = stripcslashes( utf8_encode( $_GET[ 'msg' ] ) );
				}

				$message = json_decode( $message, true );

				if ( is_array( $message ) && is_array( $message[ 0 ] ) ) {
					$message = $message[ 0 ];
				}

				//Get User Ids from User in actual race
				$user_id_is	 = 0;
				$user_ids	 = $h3_mgmt_teams->get_participant_ids( $race_id );
				$norm_num	 = $this->normalize_phone_number( $message[ 'number' ] );
				foreach ( $user_ids as $user_id ) {
					if ( $norm_num == $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) ) ) {
						$user_id_is = $user_id;
						break;
					}
				}
				$team_id = $h3_mgmt_teams->get_team_by_user_and_race( $user_id, $race_id );

				//If no user found with  the incoming mobile number
				//Infos about cases 
//                    Nummer von Teammitglied im aktuellen Rennen und actives Team -> Gespeichert wird: Renn ID; User ID; wird angezeigt
//                    Nummer von Teammitglied im aktuellen Rennen und nicht aktives Team -> Gespeichert wird: Renn ID = 0; User ID; wird nicht angezeigt
//                    Nummer von Team im aktuellen Rennen -> Gespeichert wird: Renn ID; Team ID; wird angezeigt
//                    Nummer von Mitglied aus keinem Team -> Gespeichert wird: Renn ID = 0; User ID; wird nicht angezeigt
//                    Nummer von Unbekannt -> Gespeichert wird: Renn ID; richtige Nummer; wird nicht angezeigt

				if ( $user_id_is == 0 ) {
					//check if it is a team number from present race
					$phones2team = $this->get_phones( 0, 'all', 'teams', $race_id );
					$team_id	 = $phones2team[ $norm_num ];
					If ( $team_id == 0 ) {
						$user_ids	 = $wpdb->get_col(
						"SELECT user_id FROM " . $wpdb->prefix . "usermeta Where meta_key = 'mobile' AND meta_value != 0"
						);
						$user_id_is	 = 0;
						foreach ( $user_ids as $user_id ) {
							if ( $norm_num == $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) ) ) {
								$user_id_is = $user_id;
								break;
							}
						}
						if ( $user_id_is == 0 ) {
							$from	 = $norm_num;
							$race_id = 0;
						} else {
							$from	 = 'U_' . $user_id_is;
							$race_id = 0;
						}
					} else {
						$from = 'T_' . $team_id;
					}
					//is Team complete save actual race_id
				} elseif ( $h3_mgmt_teams->is_complete( $team_id ) ) {
					$from = 'U_' . $user_id_is;
					//if Team not complete save race_id = 0 for overall ticker
				} else {
					$from	 = 'U_' . $user_id_is;
					$race_id = 0;
				}

				$type		 = $message[ 'with_image' ] ? 2 : 1;
				$msg		 = $message[ 'message' ];
				$img_string	 = !empty( $message[ 'img' ] ) ? $message[ 'img' ] : '';
				$img		 = !empty( $message[ 'img' ] ) ? base64_decode( $message[ 'img' ] ) : '';
				$time		 = !empty( $message[ 'time' ] ) ? rtrim( $message[ 'time' ], '0' ) : time();

				if ( 2 === $type && !empty( $img ) ) {
					$filename = time() . $from;
					file_put_contents( 'wp-content/uploads/ticker/' . $filename . '.jpg', $img );
				}
				$wpdb->insert(
				$wpdb->prefix . 'sms_ticker', array(
					'send_from'	 => $from,
					'msg'		 => $msg,
					'type'		 => $type,
					'img_url'	 => $type === 2 ? $filename . '.jpg' : '',
					'img'		 => $img_string,
					'timestamp'	 => $time,
					'race_id'	 => $race_id
				), array( '%s', '%s', '%d', '%s', '%s', '%s' )
				);
				echo 'OK';
				echo " Type: $type";
				echo " From: $from";
				echo " Message: $msg";
				echo " Image: $img";
			} else {
				echo '400';
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
				'url'	 => get_site_url() . '/send-ticker-message/',
				'race'	 => 1
			), $atts ) );

			$race_id = $race;

			if ( $race == 'active' ) {
				$race_id = $h3_mgmt_races->get_active_race();
			}

			if ( is_user_logged_in() ) {

				$team_id = $h3_mgmt_teams->user_has_team( $race_id );
				$phones	 = $this->get_phones( $team_id );

				if ( !empty( $team_id ) ) {
					if ( $h3_mgmt_teams->is_complete( $team_id ) ) {

						$output .= '<p style="text-align: center; font-size: large; font-weight: bold;">' . _x( 'Send a <a title="Send a ticker message" href="' . $url . '">ticker message</a>!!!', 'ticker', 'h3-mgmt' ) . '</p>';
					}
				}
			}

			return $output;
		}

		/**
		 * Shortcode Funktion to change the from row in SMS Database from mobile
		 * number to team or user ids
		 *
		 * @since 1.0
		 * @access public
		 */
		public function Ticker_Datenbank_Change( $atts = '' ) {
			global $wpdb, $h3_mgmt_teams;

			$teams_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix . "h3_mgmt_teams", ARRAY_A
			);

			$teams_query_buf = array();
			$phones			 = array();
			$teams			 = array();

			foreach ( $teams_query as $team ) {
				$phonesbuf	 = array();
				$team_phones = $team[ 'team_phone' ];
				$pos		 = stripos( $team_phones, ' ', 0 );
				while ( $pos !== false ) {
					$phonesbuf[] = substr( $team_phones, 0, $pos );
					$team_phones = strpbrk( $team_phones, ' ' );
					$team_phones = ltrim( $team_phones );
					$pos		 = stripos( $team_phones, ' ', 0 );
				}
				$phonesbuf[] = $team_phones;

				foreach ( $phonesbuf as $phone ) {
					$phones[]											 = $this->normalize_phone_number( $phone );
					$teams[ $this->normalize_phone_number( $phone ) ]	 = $team[ 'id' ];
				}
			}

			$wpdb->update(
			$wpdb->prefix . "sms_ticker", array(
				'send_from' => 'empty',
			), array( 'send_from' => '' ), array(
				'%s'
			), array( '%s' )
			);

			foreach ( $phones as $phone ) {
				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'T_' . $teams[ $phone ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}

			$phones = array();

			$user_ids = get_users( array( 'fields'	 => 'id',
				'orderby'	 => 'ID' )
			);
			print_r( $user_ids );
			for ( $i = 0; $i <= 500; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo 'DONE 1';

			for ( $i = 501; $i <= 1000; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 2';

			for ( $i = 1001; $i <= 1500; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 3';

			for ( $i = 1501; $i <= 2000; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 4';

			for ( $i = 2001; $i <= 2500; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 5';

			for ( $i = 2501; $i <= 3000; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 6';

			for ( $i = 3001; $i <= 3500; $i++ ) {
				$phone = $this->normalize_phone_number( get_user_meta( $user_ids[ $i ], 'mobile', true ) );

				$wpdb->update(
				$wpdb->prefix . "sms_ticker", array(
					'send_from' => 'U_' . $user_ids[ $i ],
				), array( 'send_from' => $phone ), array(
					'%s'
				), array( '%s' )
				);
			}
			echo '-- DONE 7';
		}

		/**
		 * PHP5 style constructor
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {
			add_shortcode( 'h3-liveticker', array( $this, 'the_ticker' ) );
			add_shortcode( 'h3-ticker-incoming-interface', array( $this, 'ticker_incoming_interface' ) );
			add_shortcode( 'h3-ticker-message', array( $this, 'ticker_Page_controll' ) );
			add_shortcode( 'h3-ticker-map', array( $this, 'ticker_Page_map' ) );
			add_shortcode( 'h3-ticker-send-message-link', array( $this, 'ticker_send_message_link' ) );
			add_shortcode( 'h3-ticker-datenbank-change', array( $this, 'Ticker_Datenbank_Change' ) );
		}

	}

	// class

endif; // class exists
?>
