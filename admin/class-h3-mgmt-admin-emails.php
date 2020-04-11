<?php

/**
 * H3_MGMT_Admin_Emails class.
 *
 * This class contains properties and methods for
 * the email/newsletter interface in the administration backend.
 *
 * Attention: It does not actually handle the sending of emails.
 * @see class H3_MGMT_Mailer for that,
 * contained in /includes/h3-mgmt-mailer.php
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Emails' ) ) :

class H3_MGMT_Admin_Emails {

	/**
	 * Outputs form to send emails
	 *
	 * @since 1.0
	 * @access public
	 */
	public function mail_form() {

            global $wpdb, $h3_mgmt_races;
            
            $url = 'admin.php?page=h3-mgmt-emails';
            $form_action = $url . '&amp;todo=send';
            $sent = false;

            if( isset( $_GET['todo'] ) && $_GET['todo'] == 'send' ) {
                    $sent = $this->mail_send();
            }

            $messages = array();
                    
            if ( $sent ) {
                    $messages[] = array(
                            'type' => 'message',
                            'message' => sprintf(
                                            _x( 'The Email titled &quot;%s&quot; has been successfully sent.', 'Admin Email Interface', 'h3-mgmt' ),
                                            $_POST['subject']
                                    )
                            );
            }

            if( isset( $_GET['email'] ) ) {
                    $receipient_field = array(
                            'type' => 'text',
                            'label' => _x( 'Receipient', 'Admin Email Interface', 'h3-mgmt' ),
                            'id' => 'receipient',
                            'disabled' => true,
                            'value' => $_GET['email'],
                            'desc' => _x( 'You are writing to a single user.', 'Admin Email Interface', 'h3-mgmt' )
                    );
            } else {

                $receipients = array(
                    array(
                            'label' => _x( 'Testmail to yourself', 'Admin Email Interface', 'h3-mgmt' ),
                            'value' => 'self'
                    ),
                    array(
                            'label' => _x( 'All HHH Users who participated at Tramprennen', 'Admin Email Interface', 'h3-mgmt' ),
                            'value' => 'all'
                    )
                );
                
                $races = $h3_mgmt_races->get_races( array( 'orderby' => 'id', 'race' => 'all' ) );                
                
                foreach( $races as $race){
                    $receipients[] = array(
                                        'label' => _x( 'All participants of "'.  $race['name'] .'"', 'Admin Email Interface', 'h3-mgmt' ),
                                        'value' => ('race-'. $race['id'])
                                    );
                }
                
                $receipient_field = array(
                        'type' => 'select',
                        'label' => _x( 'Receipient', 'Admin Email Interface', 'h3-mgmt' ),
                        'id' => 'receipient',
                        'value' => 'race-'.$h3_mgmt_races->get_active_race(),
                        'options' => $receipients,
                        'desc' => _x( 'Select who receives the email. Choose the &quot;Testmail to yourself&quot; option, to see how it will look in your own inbox.', 'Admin Email Interface', 'h3-mgmt' )
                );
            }

            $metaboxes = array(
                    array(
                            'title' => _x( 'E-Mail Metadata', 'Admin Email Interface', 'h3-mgmt' ),
                            'fields' => array(
                                    $receipient_field,
                                    array(
                                            'type' => 'select',
                                            'label' => _x( 'Sender', 'Admin Email Interface', 'h3-mgmt' ),
                                            'id' => 'sender',
                                            'value' => 'nr',
                                            'options' => array(
                                                    array(
                                                            'label' => _x( 'My own email address', 'Admin Email Interface', 'h3-mgmt' ),
                                                            'value' => 'own'
                                                    ),
                                                    array(
                                                            'label' => 'no-reply@tramprennen.org',
                                                            'value' => 'nr'
                                                    )
                                            ),
                                            'desc' => _x( 'Send the email either from your personal email address or select the generic &quot;no-reply&quot;.', 'Admin Email Interface', 'h3-mgmt' )
                                    )
                            )
                    ),
                    array(
                            'title' => _x( 'E-Mail Metadata', 'Admin Email Interface', 'h3-mgmt' ),
                            'fields' => array(
                                    array(
                                            'type' => 'text',
                                            'label' =>  _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
                                            'id' => 'subject',
                                            'desc' => _x( 'The email&apos;s subject line', 'Admin Email Interface', 'h3-mgmt' )
                                    ),
                                    array(
                                            'type' => 'textarea',
                                            'label' =>  _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
                                            'id' => 'message',
                                            'desc' => _x( 'Message Body', 'Admin Email Interface', 'h3-mgmt' )
                                    )
                            )
                    )
            );

            $page_args = array(
                    'echo' => true,
                    'icon' => 'icon-mails',
                    'title' => _x( 'Send an email', 'Admin Email Interface', 'h3-mgmt' ),
                    'url' => $url,
                    'messages' => $messages
            );
            $the_page = new H3_MGMT_Admin_Page( $page_args );

            $form_args = array(
                    'echo' => true,
                    'form' => true,
                    'method' => 'post',
                    'metaboxes' => true,
                    'js' => false,
                    'url' => $url,
                    'action' => $form_action,
                    'id' => $id,
                    'button' => __( 'Send E-Mail!', 'h3-mgmt' ),
                    'top_button' => true,
                    'back' => true,
                    'back_url' => $url,
                    'fields' => $metaboxes
            );
            $the_form = new H3_MGMT_Admin_Form( $form_args );

            $the_page->top();
            $the_form->output();
            $the_page->bottom();
	}

	/**
	 * Prepares groupmail for sending
	 *
	 * @since 1.0
	 * @access private
	 */
	private function mail_send() {
		global $wpdb, $h3_mgmt_mailer, $h3_mgmt_teams, $h3_mgmt_races;

		if( isset( $_POST['receipient'] ) ) {
			if( $_POST['receipient'] == 'self' ) {
				global $current_user;
				get_currentuserinfo();
				$to = $current_user->user_email();
			} elseif( $_POST['receipient'] == 'all' ) {
				$to = array();
                                $race_ids = array();
                                $participants = array();
                                $participants_all = array();
                                
                                $race_ids = $h3_mgmt_races->get_races( array( 'orderby' => 'id', 'race' => 'all' ) ); 
                                foreach( $race_ids as $race_id){
                                    $participants_all = array_merge ( $participants_all, $h3_mgmt_teams->get_participant_ids( $race_id['id'] ) );
                                }
                                foreach( $participants_all as $participant_all){
                                    if( !in_array($participant_all, $participants) ){
                                        $participants[] = $participant_all;
                                    }
                                }
                                
				foreach( $participants as $participant ) {
					$part_obj = new WP_User( $participant );
					$to[] = $part_obj->user_email;
				}                                
			} elseif( substr( $_POST['receipient'], 0, 4 ) == 'race' ) {
				$recipient_arr = explode( '-', $_POST['receipient'] );
				$race_id = intval( $recipient_arr[1] );
				$participants = $h3_mgmt_teams->get_participant_ids( $race_id );
				$to = array();
				foreach( $participants as $participant ) {
					$part_obj = new WP_User( $participant );
					$to[] = $part_obj->user_email;
				}
			}
		}

		if( isset( $_POST['sender'] ) && $_POST['sender'] === 'own' ) {
			$from_name = $current_user->first_name;
			$from_email = $current_user->user_email;
		} else {
			$from_name = NULL;
			$from_email = NULL;
		}
                
		$h3_mgmt_mailer->send( $to, $_POST['subject'], $_POST['message'], $from_name, $from_email );

		return true;
	}

	/**
	 * Outputs form to edit autoresponse texts and saves them to the database
	 *
	 * @since 1.0
	 * @access public
	 */
	public function autoresponses_edit() {
		global $wpdb;

		if ( isset( $_GET[ 'tab' ] ) && in_array( $_GET[ 'tab' ], array( 'en', 'de' ) ) ) {
			$active_tab = $_GET[ 'tab' ];
		} else {
			$active_tab = 'en';
		}

		$url = 'admin.php?page=h3-mgmt-emails-autoresponses';
		$form_action = $url . '&tab=' . $active_tab . '&amp;todo=save';

		$messages = array();

		$metaboxes = array(
			array(
				'title' => _x( 'Notification of successful team creation', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'team-creation-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'team-creation-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'team-creation-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Invitation (sent to invitee)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Notification of accepted invitation (sent to inviter)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-inviter-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-inviter-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-inviter-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Notification of successful invitation acceptance (sent to invitee)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-invitee-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-invitee-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'invitation-accepted-invitee-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Notification of HitchPackage payment having been received', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'package-paid-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'package-paid-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'package-paid-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Notification of waiver form payment having been received', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'waiver-reached-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'waiver-reached-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'waiver-reached-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'Notification when team is complete (homework done, route can be chosen)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'publishable-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'publishable-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'publishable-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Sponsor, Team Notification', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-sponsor-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-sponsor-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-sponsor-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Owner, Team Notification', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-owner-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-owner-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'new-owner-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Sponsor, donor confirmation (debit)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-sponsor-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-sponsor-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-sponsor-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Owner, donor confirmation (debit)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-owner-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-owner-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'debit-thanks-owner-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Sponsor, donor notification (PayPal)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-sponsor-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-sponsor-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-sponsor-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'New Owner, donor notification (PayPal)', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-owner-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-owner-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-please-owner-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			),
			array(
				'title' => _x( 'PayPal payment received, confirmation to sponsor/owner', 'Admin Email Interface', 'h3-mgmt' ),
				'fields' => array(
					array(
						'type' => 'checkbox',
						'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-thanks-switch',
						'desc' => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'text',
						'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-thanks-subject',
						'desc' => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' )
					),
					array(
						'type' => 'textarea',
						'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
						'id' => 'paypal-thanks-message',
						'desc' => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' )
					)
				)
			)
		);

		/* populate fields */
		$mcount = count($metaboxes);
		for ( $i = 0; $i < $mcount; $i++ ) {
			$fcount = count( $metaboxes[$i]['fields'] );
			for ( $j = 0; $j < $fcount; $j++ ) {
				$id = explode( '-', $metaboxes[$i]['fields'][$j]['id'] );
				$column = strval( array_pop($id) );
				$action = strval( implode( '-', $id ) );
				if( ! isset( $_POST['submitted'] ) ) {
					$data = $wpdb->get_results(
						"SELECT " . $column . " FROM " .
						$wpdb->prefix . "h3_mgmt_auto_responses " .
						"WHERE action = '" . $action . "' AND language = '" . $active_tab . "' " .
						"LIMIT 1",
						ARRAY_A
					);
					$metaboxes[$i]['fields'][$j]['value'] = stripslashes( $data[0][$column] );
				} elseif( $metaboxes[$i]['fields'][$j]['type'] == 'checkbox' ) {
					if( isset( $_POST[$metaboxes[$i]['fields'][$j]['id']] ) ) {
						$metaboxes[$i]['fields'][$j]['value'] = 1;
					} else {
						$metaboxes[$i]['fields'][$j]['value'] = 0;
					}
				} else {
					$metaboxes[$i]['fields'][$j]['value'] = stripslashes( $_POST[$metaboxes[$i]['fields'][$j]['id']] );
				}
				/* save */
				if( isset( $_GET['todo'] ) && $_GET['todo'] == 'save' ) {
					if( $metaboxes[$i]['fields'][$j]['type'] != 'checkbox' ) {
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_auto_responses',
							array( $column => $_POST[$metaboxes[$i]['fields'][$j]['id']] ),
							array( 'action' => $action, 'language' => $active_tab ),
							array( '%s' ),
							array( '%s', '%s' )
						);
					} elseif( isset( $_POST[$metaboxes[$i]['fields'][$j]['id']] ) ) {
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_auto_responses',
							array(
								$column => 1
							),
							array( 'action' => $action, 'language' => $active_tab ),
							array( '%d' ),
							array( '%s', '%s' )
						);
					} else {
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_auto_responses',
							array(
								$column => 0
							),
							array( 'action' => $action, 'language' => $active_tab ),
							array( '%d' ),
							array( '%s', '%s' )
						);
					}
				}
			}
		}

		if( isset( $_GET['todo'] ) && $_GET['todo'] == 'save' ) {
			$messages[] = array(
				'type' => 'message',
				'message' => __( 'Options successfully updated!', 'h3-mgmt' )
			);
		}

		$page_args = array(
			'echo' => true,
			'icon' => 'icon-mails',
			'title' => _x( 'Edit Automatic Responses', 'Admin Email Interface', 'h3-mgmt' ),
			'url' => $url,
			'extra_head_html' => '<p>' .
					_x( 'You can use the following placeholders (where they make sense):', 'Admin Email Interface', 'h3-mgmt' ) .
					'<br />%code%, %donation%, %invitee%, %inviter%, %link%, %name%, %names%, %team_name%, %thumbs%</p>',
			'messages' => $messages,
			'tabs' => array(
				array(
					'title' => __( 'English', 'h3-mgmt' ),
					'value' => 'en',
					'icon' => 'icon-flag-en'
				),
				array(
					'title' => __( 'German', 'h3-mgmt' ),
					'value' => 'de',
					'icon' => 'icon-flag-de'
				)
			),
			'active_tab' => $active_tab
		);
		$the_page = new H3_MGMT_Admin_Page( $page_args );

		$form_args = array(
			'echo' => true,
			'form' => true,
			'headspace' => true,
			'method' => 'post',
			'metaboxes' => true,
			'js' => false,
			'url' => $url,
			'action' => $form_action,
			'id' => $id,
			'button' => __( 'Save Automatic Response Texts', 'h3-mgmt' ),
			'top_button' => true,
			'back' => true,
			'back_url' => $url,
			'fields' => $metaboxes
		);
		$the_form = new H3_MGMT_Admin_Form( $form_args );

		$the_page->top();
		$the_form->output();
		$the_page->bottom();
	}

} // class

endif; // class exists

?>
