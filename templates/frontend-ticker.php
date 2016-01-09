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
				default:
					$output .= '<div class="ticker-message ticker-mms" '.$div_backround.'>' .
						'<img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="' .
							get_option( 'siteurl' ) . $message['route_image'] . '" style="width:33px;height:33px;float:left;padding:0;margin-right:10px;"/>' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates" style="font-size:1.2em">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time" style="font-size:1.2em">' . $time . '</p>';
						if( ! empty( $message['img_path'] ) ) {
							$message['img_path'] = stripslashes($message['img_path']);
							$output .= '<iframe max-width="560" max-height="315" src="https://www.youtube.com/embed/' .$message['img_path']. '" frameborder="0" allowfullscreen></iframe>';
						}
						if( ! empty( $message['message'] ) ) {    //------ wird für die marker info genutzt
							$output .= '<p class="the-message">' . $message['message'] . '</p>';
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
							// $output .= //'<table><tr><td style="text-align:center;">' .
								// '<a href="' .
										// $message['img_path'] .
									// '" title="' . __( 'Full-size Ticker Image', 'h3-mgmt' ) . '">' .// class="shutterset"
										// '<img title="Pic sent on ' . $message['date'] . '" src="' . $message['img_path'] . '" />' .
								// '</a>';// .
								// '</td></tr></table>';
						}
						if( ! empty( $message['message'] ) ) {    //------ wird für die marker info genutzt
							$output .= '<p class="the-message">' . $message['message'] . '</p>';
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
						
						if( ! empty( $message['img_path'] ) && file_exists($img_url) ) { // && file_exists ( $message['img_path'] )
										
							$output .= //'<table><tr><td style="text-align:center;">' .
								'<a href="' .
										get_site_url().'/'.$img_url .
									'" title="' . __( 'Full-size Ticker Image', 'h3-mgmt' ) . '">' .// class="shutterset"
										'<img style="display: block; margin-left: auto; margin-right: auto;" title="Pic sent on ' . $message['date'] . '" src="' . get_site_url().'/'.$img_url . '" />' .
								'</a>';// .
								//'</td></tr></table>';
								
						}
						if( ! empty( $message['message'] ) ) {
							$output .= '<p class="the-message">' . $message['message'] . '</p>';
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
							$output .= '<p class="the-message">' . $message['message'] . '</p>';
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
