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
			$diff_string = ' <span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['minute'], _x( '%time% minutes ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 86400 ) {
			$diff_string = ' <span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['hour'], _x( '%time% hours ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 604800 ) {
			$diff_string = ' <span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['day'], _x( '%time% days ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} elseif( $stamp_diff < 2419200 ) {
			$diff_string = ' <span class="ticker-message-ago">(' . str_replace( '%time%', $message['diff']['week'], _x( '%time% weeks ago', 'Ticker', 'h3-mgmt' ) ) . ')</span>';
		} else {
			$diff_string = '';
		}

		if( ! empty( $message['message'] ) || ( file_exists( $message['img_path'] ) && filesize( $message['img_path'] ) > 1 ) ) {
			$time = $message['date'] . $diff_string;

			switch( $message['type'] ) {
				case 2: //mms
					$output .= '<div class="ticker-message ticker-mms" style="box-shadow: -2px 2px 5px 0 ' . $message['color'] . ';">' .
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
					$output .= '<div class="ticker-message ticker-sms" style="box-shadow: -2px 2px 5px 0 ' . $message['color'] . ';">' .
						'<p class="ticker-message-title">' . $message['team_name'] . '</p>' .
						'<p class="ticker-message-mates">' . $message['mates'] . '</p>' .
						'<p class="ticker-message-time">' . $time . '</p>' .
						'<p class="the-message">' . $message['message'] . '</p>';
				break;

			} // type switch

			$output .= '</div>';
		}

	} // foreach message
} // if ! empty

?>
