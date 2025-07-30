<?php

/**
 * H3_MGMT_XChange class.
 *
 * This class contains properties and methods for the teammate exchange notice board.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */
class H3_MGMT_XChange {

	/**
	 * Returns an array of messages where the user doesn't have a team in the active race.
	 * To avoid old messages, only messages from the current year are returned.
	 *
	 * @since 1.0
	 * @access private
	 */
	private function get_messages(): array {
		global $wpdb, $h3_mgmt_races;

		$activeRace   = $h3_mgmt_races->get_active_race();
		$current_year = date( 'Y-01-01' );

		return $wpdb->get_results(
			"SELECT xc.*
					FROM {$wpdb->prefix}h3_mgmt_xchange xc
					    LEFT JOIN (SELECT tm.user_id
					               FROM {$wpdb->prefix}h3_mgmt_teammates tm
					                   JOIN {$wpdb->prefix}h3_mgmt_teams t ON tm.team_id = t.id
					               WHERE t.race_id = ${activeRace}) j ON xc.user_id = j.user_id
					WHERE time > '${current_year}' AND j.user_id IS NULL
					ORDER BY time DESC", ARRAY_A
		);
	}

	/**
	 * Retrieves the last message of own user.
	 *
	 * @return array|null
	 */
	private function getOwnMessage(): ?array {
		global $wpdb, $current_user;

		if ( !is_user_logged_in() ) {
			return null;
		}

		return $wpdb->get_row( "SELECT *
											FROM {$wpdb->prefix}h3_mgmt_xchange
											WHERE user_id = {$current_user->ID}
											ORDER BY time DESC", ARRAY_A );
	}

	/**
	 * Inserts, updates or deletes a message.
	 * If id is given and $new_message isn't empty, updates the message with id.
	 * If id is given and $new_message is empty, deletes the message.
	 * If id is empty and $new_message isn't empty, inserts a new message.
	 *
	 * @param string $newMessage
	 * @param int|null $id
	 *
	 * @return false|int
	 */
	public function insertUpdateDeleteMessage( string $newMessage, ?int $id ) {
		global $wpdb, $current_user;

		if ( !is_user_logged_in() ) {
			return null;
		}

		$table = "{$wpdb->prefix}h3_mgmt_xchange";

		if ( !empty( $newMessage ) && empty( $id ) ) {
			return $wpdb->insert(
				$wpdb->prefix . 'h3_mgmt_xchange',
				[
					'message' => $newMessage,
					'user_id' => $current_user->ID
				],
				[ '%s', '%d' ],
			);
		}

		if ( !empty( $newMessage ) && !empty( $id ) ) {
			return $wpdb->update(
				$table,
				[ 'message' => $newMessage ],
				[ 'id' => $id ],
				[ '%s' ],
				[ '%d' ],
			);
		}

		return $wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ],
		);
	}

	/**
	 * message board controller
	 *
	 * @since 1.0
	 * @access public
	 */
	public function xchange_control(): string {
		$ownMessage = $this->getOwnMessage();

		$ownMessageText = trim( $_POST['message'] ?? '' );

		if ( isset( $_POST['submitted'] ) ) {
			$this->insertUpdateDeleteMessage( $ownMessageText, $ownMessage['id'] ?? null );
		} elseif ( isset( $ownMessage['id'] ) ) {
			$ownMessageText = $ownMessage['message'];
		}

		$output = '<div class="flex_column av_one_half first  avia-builder-el-0  el_before_av_one_half  avia-builder-el-first">' .
		          '<h3>' . _x( 'HitchMate XChange', 'XChange', 'h3-mgmt' ) . '</h3>';

		foreach ( $this->get_messages() as $message ) {
			$user = get_userdata( $message['user_id'] );
			if ( !empty( trim( $message['message'] ) ) && $user !== false ) {
				$output .= '<div class="xchange-message-wrap">' .
				           '<p class="xchange-title">' .
				           _x( 'Message by', 'XChange', 'h3-mgmt' ) . ': ' . $user->first_name . ' (' . $message['time'] . ')' .
				           '</p>' .
				           '<p class="xchange-message no-margin-bottom">' .
				           preg_replace( '#(<br */?>\s*){2,}#i', '<br /><br />', preg_replace( '/[\r|\n]/', '<br>', stripslashes( $message['message'] ) ) ) .
				           '</p>' .
				           '</div>';
			}
		}

		$output .= '</div><div class="flex_column av_one_half   avia-builder-el-2  avia-builder-el-last">' .
		           '<h3>' . _x( 'Your own message', 'XChange', 'h3-mgmt' ) . '</h3>';

		if ( is_user_logged_in() ) {
			$output .= '<p>' . _x( 'If you want to post a HitchMate search message yourself, you can do so here. To delete an existing message, simply empty the text field and submit an empty message - your existing one will be deleted.', 'XChange', 'h3-mgmt' ) . '</p>' .
			           '<p>' . _x( 'Please do not forget to include some sort of contact information in your message.', 'XChange', 'h3-mgmt' ) . '</p>';

			$output .= '<form name="h3_mgmt_xchange_message" method="post" action="">' .
			           '<input type="hidden" name="submitted" value="y"/>' .
			           '<div class="form-row trap-row"><label for="address">Please leave this blank...</label>' .
			           '<input type="text" name="address" id="address" value=""></div>';

			$fields = [
				[
					'type'  => 'textarea',
					'id'    => 'message',
					'label' => __( 'The message', 'h3-mgmt' ),
					'value' => $ownMessageText
				]
			];
			require( H3_MGMT_ABSPATH . '/templates/frontend-form.php' );

			$output .= '<div class="form-row">' .
			           '<input type="submit" id="submit_form" name="submit_form" value="' .
			           _x( 'Submit / Update own message', 'XChange', 'h3-mgmt' ) .
			           '" /></div></form>';
		} else {
			$output .= '<p>' . _x( 'You must be <a title="Log in" href="' . site_url( 'login' ) . '">logged in</a> to post a HitchMate search message.', 'XChange', 'h3-mgmt' ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}

	/*************** CONSTRUCTORS ***************/

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_shortcode( 'h3-mate-xchange', [ $this, 'xchange_control' ] );
	}
}
