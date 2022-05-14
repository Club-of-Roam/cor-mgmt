<?php

/**
 * Template for the ticker messages
 *
 **/

if( ! isset( $output ) ) {
	$output = '';
}

/* loop through fields */

if ( isset ( $messages ) && ! empty( $messages ) ) {
	foreach ( $messages as $message ) {

		$stamp_diff = time() - intval( $message['time'] );
		if( $stamp_diff < 3600 ) {
			$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['minute'], _x( '%time% minutes ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 86400 ) {
			$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['hour'], _x( '%time% hours ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 604800 ) {
			$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['day'], _x( '%time% days ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 2419200 ) {
			$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['week'], _x( 'weeks ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} else {
			$diff_string = '';
		}

		//if( ! empty( $message['message'] ) || ( file_exists( $message['img_path'] ) && filesize( $message['img_path'] ) > 1 ) ) {
			$time = $message['date'] . $diff_string;
			if ( $message['mates'] == 'Tramprennen Orga-Team' ){
				$div_backround = 'style="background: #A9C5DE;"';
			} else {
				$div_backround = '';
			}
			// $current_user = wp_get_current_user();
			// $current_user = $current_user->user_login;
			// if ( $current_user=='Tramprennen' ) {
				// echo $div_backround;
			// }
			
			switch( $message['type'] ) {
				case 5: //video
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" style="width:33px;height:33px;float:left;padding:0;margin-right:10px;"/>' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates" style="font-size:1.2em">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>';
						if( ! empty( $message['img_path'] ) ) {
							$message['img_path'] = stripslashes($message['img_path']);
							$output .= '<div class="ticker-youtube"> <iframe width="560" height="315" src="https://www.youtube.com/embed/' .$message['img_path']. '" frameborder="0" allowfullscreen></iframe></div>';
						}
						if( ! empty( $message['message'] ) ) {    //------ wird für die marker info genutzt
							$output .= '<p class="the-message" style="font-weight:700;font-style:italic;">' . $message['message'] . '</p>';
						}
				break;
				
				case 4: //coordinates webpage
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" style="width:33px;height:33px;float:left;padding:0;margin-right:10px;"/>' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates" style="font-size:1.2em">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>';

						if( ! empty( $message['img_path'] ) ) {
							$output .= '
							<iframe style="height:300px;width:100%;border:0;" frameborder="0" 
							src="https://www.google.com/maps/embed/v1/place?q=' .$message['img_path']. 
							'&zoom=10&key=AIzaSyDtdxfnAWhpou6zyzlRcMkZfxwbgrdvhnE"></iframe>
							';
						}
						if( ! empty( $message['message'] ) ) {    //------ wird für die marker info genutzt
							$output .= '<p class="the-message" style="font-weight:700;font-style:italic;">' . $message['message'] . '</p>';
						}
				break;
				
				case 3: //image webpage
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" style="width:33px;height:33px;float:left;padding:0;margin-right:10px;"/>' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates" style="font-size:1.2em">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>';
						
						$img_url = strstr($message['img_path'], 'wp-content');
                                                
						if( ! empty( $message['img_path'] ) && file_exists($img_url) && filesize($img_url) > 0 ) {
										
							$output .= 
								'<a class="ticker-message-pic" href="' .
										get_site_url().'/'.$img_url .
									'" title="' . __( 'Full-size Ticker Image', 'h3-mgmt' ) . '">' .// class="shutterset" 
										'<img class="ticker-message-pic" title="Pic sent on ' . $message['date'] . '" src="' . get_site_url().'/'.$img_url . '" />' .
								'</a>';
								
						}
						if( ! empty( $message['message'] ) ) {
							$output .= '<p class="the-message" style="font-weight:700;font-style:italic;">' . $message['message'] . '</p>';
						}
				break;
				
				case 2: //mms
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" />' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time">' . $time . '</p>';

						if( file_exists( $message['img_path'] ) && filesize( $message['img_path'] ) > 1 ) {
							$output .= '<table><tr><td style="text-align:center;">' .
								'<a href="' .
										$message['img_url'] .
									'" title="' . __( 'Full-size Ticker Image', 'h3-mgmt' ) . '" class="shutterset">' .
										'<img title="Pic sent on ' . $message['date'] . '" src="' . $message['img_url'] . '" />' .
								'</a>' .
								'</td></tr></table>';
						}
						if( ! empty( $message['message'] ) ) {
							$output .= '<p class="the-message" style="font-weight:700;font-style:italic;">' . $message['message'] . '</p>';
						}
				break;

				case 1: //sms
				default:
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" style="width:33px;height:33px;float:left;padding:0;margin-right:10px;"/>' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates" style="font-size:1.2em">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>' .
						'<p class="the-message" style="font-weight:700;font-style:italic;">' . $message['message'] . '</p>';
				break;

			} // type switch

			if( $message['comments'] != null && count( $message['comments'] ) > 0 ) {
					$output .= '<p class="ticker-show-comments-button"><a data-ticker_msg_id="' . $message['id'] . '" href="javascript:void(0);" class="ticker-show-comments"> + show ' . count( $message['comments'] ) . ' comment(s) </a></p> ';
					$output .= '<div class="ticker-show-comments show-comments_close" id="ticker-show-comments_' . $message['id'] . '" style="display: none;" > ';
					
				foreach ( $message['comments'] as $comment ) {

					$stamp_diff = time() - intval( $comment['time'] );
					if( $stamp_diff < 3600 ) {
						$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $comment['diff']['minute'], _x( '%time% minutes ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
					} elseif( $stamp_diff < 86400 ) {
						$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $comment['diff']['hour'], _x( '%time% hours ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
					} elseif( $stamp_diff < 604800 ) {
						$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $comment['diff']['day'], _x( '%time% days ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
					} elseif( $stamp_diff < 2419200 ) {
						$diff_string = '<br /><span class="ticker-message-ago">(' . str_replace( '%time%', $comment['diff']['week'], _x( 'weeks ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
					} else {
						$diff_string = '';
					}

					$time = $comment['date'] . $diff_string;
					$output .= '<div class="ticker-comment" id="ticker-comment_' . $comment['id'] . '"> ';
					$output .= '<p class="ticker-message-title">' . $comment['name'] . '</p>' .
							'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>' .
							'<p class="the-message" style="font-weight:700;font-style:italic;">' . $comment['message'] . '</p>';
					$output .= '</div>';
				}
				$output .= '</div>';
			}
				
			if( $race_setting['liveticker'] == 1 && is_user_logged_in() ){
				$output .= '<p><a data-ticker_msg_id="' . $message['id'] . '" href="javascript:void(0);" class="ticker-comment-button"> + send a comment </a></p> ';
				$output .= '<div class="ticker-send-comment comment_close" id="ticker-send-comment_' . $message['id'] . '" style="display: none;" > ';
				$output .= do_shortcode( '[h3-ticker-message race=' . $race . ' comment=true]' );
				$output .= '</div>';
			}
			
			$output .= '</div>';
		//}

	} // foreach message
} // if ! empty

function autoRotateImage($image) { 
    $orientation = $image->getImageOrientation(); 

    switch($orientation) { 
        case imagick::ORIENTATION_BOTTOMRIGHT: 
            $image->rotateimage("#000", 180); // rotate 180 degrees 
        break; 

        case imagick::ORIENTATION_RIGHTTOP: 
            $image->rotateimage("#000", 90); // rotate 90 degrees CW 
        break; 

        case imagick::ORIENTATION_LEFTBOTTOM: 
            $image->rotateimage("#000", -90); // rotate 90 degrees CCW 
        break; 
    } 

    // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image! 
    $image->setImageOrientation(imagick::ORIENTATION_TOPLEFT); 
}

?>
