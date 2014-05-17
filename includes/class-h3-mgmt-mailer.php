<?php

/**
 * H3_MGMT_Mailer class.
 *
 * This class contains properties and methods to
 * send emails.
 *
 * It receives instructions from
 * @see class H3_MGMT_Admin_Emails
 * and
 * @see class H3_MGMT_Registrations
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Mailer' ) ) :

class H3_MGMT_Mailer {

	/**
	 * Is called from other objects to send auto responses for user actions.
	 *
	 * Checks databse for custom auto response texts,
	 * otherwise sends generic mail.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function auto_response( $id, $action, $strings = array(), $type = 'id', $language = 'en' ) {
		global $wpdb;

		$default_strings = array(
			'donation' => '10',
			'thumbs' => '1',
			'inviter' => 'Inviter',
			'invitee' => 'Invitee',
			'link' => 'http://tramprennen.org/',
			'name' => 'Participant',
			'names' => 'Participants',
			'team_name' => 'Your Team',
			'code' => '000'
		);

		$strings = wp_parse_args( $strings, $default_strings );

		extract( $strings, EXTR_SKIP );

		if( $type == 'id' ) {
			if( is_array( $id ) ) {
				$to = array();
				foreach( $id as $single_id ) {
					$this_user = new WP_User( $single_id );
					/* get email address */
					$to[] = $this_user->user_email;
				}
			} else {
				$this_user = new WP_User( $id );
				/* get email address */
				$to = $this_user->user_email;
				/* set name variable */
				if( $name === 'Participant' ) {
					$name = $this_user->first_name;
				}
			}
		} else {
			$to = $id; // DIRTY!!! FIX!!!
		}

		/* grab action options from database */
		$options_query = $wpdb->get_results(
			"SELECT * FROM " . $wpdb->prefix . "h3_mgmt_auto_responses " .
			"WHERE action = '" . $action . "' AND language = '" . $language . "' LIMIT 1", ARRAY_A
		);
		$options = $options_query[0];

		/* do nothing if notifications have been disabled for this action */
		if( $options['switch'] == 0 ) {
			return false;
		/* otherwise send message */
		} else {

			$default_responses = array(
				'team-creation'	=>	array(
					'subject'	=>	__( "Your team has been successfully created", 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, str_replace( "%team_name%", $team_name, __( 'Moin %name%! Your team "%team_name%" has been successfully created.', 'h3-mgmt' ) ) )
				),
				'invitation'	=>	array(
					'subject'	=>	__( 'You have been invited to Tramprennen 2013', 'h3-mgmt' ),
					'message'	=>	str_replace( "%code%", $code, str_replace( "%name%", $name, str_replace( "%link%", $link, str_replace( "%team_name%", $team_name, str_replace( "%inviter%", $inviter, __( 'Moin! You have been invited by %inviter% to join his/her team "%team_name%" for Tramprennen 2013. To accept the invitation, either click here: %link%, or enter the code %code% manually on tramprennen.org.', 'h3-mgmt' ) ) ) ) ) )
				),
				'invitation-accepted-inviter'	=>	array(
					'subject'	=>	__( 'Your invitation was accepted!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, str_replace( "%link%", $link, str_replace( "%team_name%", $team_name, str_replace( "%invitee%", $invitee, __( 'Moin %name%! %invitee% has accepted your invitation to join "%team_name%" for Tramprennen 2013. Check the status of your time via: %link%', 'h3-mgmt' ) ) ) ) )
				),
				'invitation-accepted-invitee'	=>	array(
					'subject'	=>	__( 'You have successfully joined a team!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, str_replace( "%link%", $link, str_replace( "%team_name%", $team_name, str_replace( "%inviter%", $inviter, __( 'Moin %name%! You have successfully accepted the invitation by %inviter% to join "%team_name%" for Tramprennen 2013. Check the status of your team via: %link%', 'h3-mgmt' ) ) ) ) )
				),
				'package-paid'	=>	array(
					'subject'	=>	__( 'HitchPackage payment received', 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, str_replace( "%link%", $link, __( 'Moin %name%! The payment for your HitchPackage has been received. Thank you. Check the status of your team via: %link%', 'h3-mgmt' ) ) )
				),
				'waiver-reached'	=>	array(
					'subject'	=>	__( 'Liability waiver received', 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, str_replace( "%link%", $link, __( 'Moin %name%! We have received your signed liability waiver. Thank you. Check the status of your team via: %link%', 'h3-mgmt' ) ) )
				),
				'new-sponsor'	=>	array(
					'subject'	=>	__( 'New TeamSponsor', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, __( 'Hello "%team_name%"! You just got a new sponsor! Awesomeness!', 'h3-mgmt' ) )
				),
				'new-owner'	=>	array(
					'subject'	=>	__( 'New TeamOwner', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, __( 'Hello "%team_name%"! You just got an owner! Awesomeness!', 'h3-mgmt' ) )
				),
				'publishable'	=>	array(
					'subject'	=>	__( 'Team complete!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, str_replace( "%names%", $names, str_replace( "%link%", $link, __( 'Moin %names%! Your team "%team_name%" is now complete. You can now choose your route and publish your team. Welcome to Tramprennen 2013! Check the status of your team via: %link%', 'h3-mgmt' ) ) ) )
				),
				'paypal-please-owner'	=>	array(
					'subject'	=>	__( 'Thank you for your donation!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, str_replace( "%name%", $name, str_replace( "%donation%", $donation, __( 'Moin %name%! You have just become owner of team "%team_name%" and chosen to donate %donation% to Viva con Agua. Be so kind as to complete the PayPal transaction now, if you haven\'t done so already. You will appear in the team\'s profile as soon as the PayPal transaction has been confirmed.', 'h3-mgmt' ) ) ) )
				),
				'paypal-please-sponsor'	=>	array(
					'subject'	=>	__( 'Thank you for your donation!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, str_replace( "%name%", $name, str_replace( "%donation%", $donation, __( 'Moin %name%! You have just become sponsor of team "%team_name%" and chosen to donate %donation% to Viva con Agua. Be so kind as to complete the PayPal transaction now, if you haven\'t done so already. You will appear in the team\'s profile as soon as the PayPal transaction has been confirmed.', 'h3-mgmt' ) ) ) )
				),
				'paypal-thanks'	=>	array(
					'subject'	=>	__( 'PayPal donation confirmed', 'h3-mgmt' ),
					'message'	=>	str_replace( "%name%", $name, __( 'Moin %name%! Your donation to Viva con Agua has been confirmed. Unless you opted to donate anonomously, you are now listed in the sponsor\'s overview and the team profile. Thanks again!', 'h3-mgmt' ) )
				),
				'debit-thanks-owner'	=>	array(
					'subject'	=>	__( 'Thank you for your donation!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, str_replace( "%name%", $name, str_replace( "%donation%", $donation, __( 'Moin %name%! You have just become owner of team "%team_name%" and chosen to donate %donation% to Viva con Agua. You are now listed in the team\'s profile. The donation will be deducted from your bank account soon. Thank you!', 'h3-mgmt' ) ) ) )
				),
				'debit-thanks-sponsor'	=>	array(
					'subject'	=>	__( 'Thank you for your donation!', 'h3-mgmt' ),
					'message'	=>	str_replace( "%team_name%", $team_name, str_replace( "%name%", $name, str_replace( "%donation%", $donation, __( 'Moin %name%! You have just become sponsor of team "%team_name%" and chosen to donate %donation% to Viva con Agua. You are now listed in the team\'s profile. The donation will be deducted from your bank account soon. Thank you!', 'h3-mgmt' ) ) ) )
				)
			);

			if( ! empty( $options['subject'] ) ) {
				$subject = str_replace( "%thumbs%", $thumbs, str_replace( "%code%", $code, str_replace( "%invitee%", $invitee, str_replace( "%inviter%", $inviter, str_replace( "%donation%", $donation, str_replace( "%names%", $names, str_replace( "%link%", $link, str_replace( "%name%", $name, str_replace( "%team_name%", $team_name, $options['subject'] ) ) ) ) ) ) ) ) );
			} else {
				$subject = 'TR12 | ' . $default_responses[$action]['subject'];
			}

			if( ! empty( $options['message'] ) ) {
				$message = str_replace( "%thumbs%", $thumbs, str_replace( "%code%", $code, str_replace( "%invitee%", $invitee, str_replace( "%inviter%", $inviter, str_replace( "%donation%", $donation, str_replace( "%names%", $names, str_replace( "%link%", $link, str_replace( "%name%", $name, str_replace( "%team_name%", $team_name, $options['message'] ) ) ) ) ) ) ) ) );
			} else {
				$message = $default_responses[$action]['message'];
			}

			$this->send( $to, $subject, $message );

		}
	}

	/**
	 * Sends mails
	 *
	 * @todo add attachments and finalize html messages
	 *
	 * @since 1.0
	 * @access public
	 */
	public function send( $receipient, $subject, $message_pre, $from_name = NULL, $from_email = NULL, $content_type = NULL ) {
		$message = wordwrap($message_pre, 70);

		if( ! is_array( $receipient ) ) {
			$receipient = array( $receipient );
		}

		$headers = "From: ";
		if( $from_name == NULL ) {
			$headers .= "Tramprennen ";
		} else {
			$headers .= $from_name . " ";
		}
		if( $from_email == NULL ) {
			$headers .= "<no-reply@tramprennen.org>" . "\r\n";
			$headers .= "X-Sender: <no-reply@tramprennen.org>" . "\r\n";
		} else {
			$headers .= $from_email . "\r\n";
			$headers .= "X-Sender: <" . $from_email . ">" . "\r\n";
		}
		$headers .= "X-Mailer: PHP" . "\r\n";
		$headers .= "X-Priority: 1" . "\r\n";
		$headers .= "Mime-Version: 1.0" . "\r\n";
		if( $content_type == 'html' ) {
			$headers .= "Content-Type: text/plain; charset=UTF-8" . "\r\n"; // change to html
		} else {
			$headers .= "Content-Type: text/plain; charset=UTF-8" . "\r\n";
		}

		foreach( $receipient as $to ) {
			wp_mail( $to, $subject, $message, $headers );
		}
	}

} // class

endif; // class exists

?>