<?php

/**
 * H3_MGMT_XChange class.
 *
 * This class contains properties and methods for the teammate exchange notice board.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_XChange' ) ) :

	class H3_MGMT_XChange {

		/**
		 * Returns an array of messages with user_id as key
		 *
		 * @since 1.0
		 * @access private
		 */
		private function get_messages() {
			global $wpdb;

			$messages_query = $wpdb->get_results(
				"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_xchange " .
				"ORDER BY time DESC", ARRAY_A
			);

			$messages = [];
			foreach ( $messages_query as $message ) {
				$messages[ strval( $message['user_id'] ) ] = $message;
			}

			return $messages;
		}

		/**
		 * message board controller
		 *
		 * @since 1.0
		 * @access public
		 */
		public function xchange_control() {
			global $current_user, $wpdb;
			get_currentuserinfo();

			$messages = $this->get_messages();

			if ( isset( $_POST['submitted'] ) ) {
				$own_message = $_POST['message'];
			} elseif ( array_key_exists( strval( $current_user->ID ), $messages ) ) {
				$own_message = $messages[ strval( $current_user->ID ) ]['message'];
			} else {
				$own_message = '';
			}

			if ( isset( $_POST['submitted'] ) ) {
				if ( array_key_exists( strval( $current_user->ID ), $messages ) ) {
					$wpdb->update(
						$wpdb->prefix . 'h3_mgmt_xchange',
						[
							'message' => $own_message
						],
						[ 'id' => $messages[ strval( $current_user->ID ) ]['id'] ],
						[ '%s' ],
						[ '%d' ]
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix . 'h3_mgmt_xchange',
						[
							'message' => $own_message,
							'user_id' => $current_user->ID
						],
						[ '%s' ]
					);
				}
			}

			$messages = $this->get_messages();

			$output = '<div class="flex_column av_one_half first  avia-builder-el-0  el_before_av_one_half  avia-builder-el-first">' .
			          '<h3>' . _x( 'HitchMate XChange', 'XChange', 'h3-mgmt' ) . '</h3>';

			foreach ( $messages as $message ) {
				$test = rtrim( $message['message'] );
				if ( ! empty( $test ) ) {
					$user_obj = new WP_User( $message['user_id'] );
					$output   .= '<div class="xchange-message-wrap">' .
					             '<p class="xchange-title">' .
					             _x( 'Message by', 'XChange', 'h3-mgmt' ) . ': ' . $user_obj->first_name . ' (' . $message['time'] . ')' .
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
						'value' => $own_message
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

endif; // class exists

?>