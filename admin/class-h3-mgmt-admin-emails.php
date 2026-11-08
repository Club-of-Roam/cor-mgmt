<?php
require_once dirname(__DIR__) . "/includes/class-h3-mgmt-security.php";

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
            if (!current_user_can('h3_mgmt_send_emails')) { echo H3_MGMT_Security::error(); return; }
            \Cor\Mgmt\WordPress\BroadcastAdmin::page();
        }

        /** Compatibility boundary: immediate group delivery has been retired. */
        private function mail_send() { return false; }

		public function autoresponses_edit() {
            if (!H3_MGMT_Security::admin_request('autoresponses')) { echo H3_MGMT_Security::error(); return; }
            echo '<p>' . esc_html__('These global autoresponses are retained only as a copy source. Automatic team notifications now use event-specific templates and the event mail queue.', 'h3-mgmt') . ' <a href="' . esc_url(add_query_arg('page', 'cor-mgmt-responses', admin_url('admin.php'))) . '">' . esc_html__('Manage event autoresponses', 'h3-mgmt') . '</a></p>';
			global $wpdb;

			if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'en', 'de' ) ) ) {
				$active_tab = $_GET['tab'];
			} else {
				$active_tab = 'en';
			}

			$url         = 'admin.php?page=h3-mgmt-emails-autoresponses';
			$form_action = $url . '&tab=' . $active_tab . '&amp;todo=save';

			$messages = array();

			$metaboxes = array(
				array(
					'title'  => _x( 'Notification of successful team creation', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'team-creation-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'team-creation-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'team-creation-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Invitation (sent to invitee)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Notification of accepted invitation (sent to inviter)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-inviter-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-inviter-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-inviter-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Notification of successful invitation acceptance (sent to invitee)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-invitee-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-invitee-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'invitation-accepted-invitee-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Notification of HitchPackage payment having been received', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'package-paid-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'package-paid-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'package-paid-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Notification of waiver form payment having been received', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'waiver-reached-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'waiver-reached-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'waiver-reached-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Notification when team is complete (homework done, route can be chosen)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'publishable-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'publishable-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'publishable-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Sponsor, Team Notification', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-sponsor-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-sponsor-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-sponsor-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Owner, Team Notification', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-owner-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-owner-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'new-owner-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Sponsor, donor confirmation (debit)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-sponsor-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-sponsor-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-sponsor-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Owner, donor confirmation (debit)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-owner-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-owner-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'debit-thanks-owner-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Sponsor, donor notification (PayPal)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-sponsor-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-sponsor-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-sponsor-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'New Owner, donor notification (PayPal)', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-owner-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-owner-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-please-owner-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'PayPal payment received, confirmation to sponsor/owner', 'Admin Email Interface', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'checkbox',
							'label' => _x( 'Send Mail Switch', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-thanks-switch',
							'desc'  => _x( 'Enable/disable', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => _x( 'Subject', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-thanks-subject',
							'desc'  => _x( 'Subject line', 'Admin Email Interface', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea',
							'label' => _x( 'Message', 'Admin Email Interface', 'h3-mgmt' ),
							'id'    => 'paypal-thanks-message',
							'desc'  => _x( 'Message body', 'Admin Email Interface', 'h3-mgmt' ),
						),
					),
				),
			);

			/* populate fields */
			$mcount = count( $metaboxes );
			for ( $i = 0; $i < $mcount; $i++ ) {
				$fcount = count( $metaboxes[ $i ]['fields'] );
				for ( $j = 0; $j < $fcount; $j++ ) {
					$id     = explode( '-', $metaboxes[ $i ]['fields'][ $j ]['id'] );
					$column = strval( array_pop( $id ) );
					$action = strval( implode( '-', $id ) );
					if ( ! isset( $_POST['submitted'] ) ) {
						$data                                     = $wpdb->get_results(
							'SELECT ' . $column . ' FROM ' .
							$wpdb->prefix . 'h3_mgmt_auto_responses ' .
							"WHERE action = '" . $action . "' AND language = '" . $active_tab . "' " .
							'LIMIT 1',
							ARRAY_A
						);
						$metaboxes[ $i ]['fields'][ $j ]['value'] = isset( $data[0][ $column ] ) ? stripslashes( $data[0][ $column ] ) : null;
					} elseif ( $metaboxes[ $i ]['fields'][ $j ]['type'] == 'checkbox' ) {
						if ( isset( $_POST[ $metaboxes[ $i ]['fields'][ $j ]['id'] ] ) ) {
							$metaboxes[ $i ]['fields'][ $j ]['value'] = 1;
						} else {
							$metaboxes[ $i ]['fields'][ $j ]['value'] = 0;
						}
					} else {
						$metaboxes[ $i ]['fields'][ $j ]['value'] = stripslashes( $_POST[ $metaboxes[ $i ]['fields'][ $j ]['id'] ] );
					}
					/* save */
					if ( isset( $_GET['todo'] ) && $_GET['todo'] == 'save' ) {
						if ( $metaboxes[ $i ]['fields'][ $j ]['type'] != 'checkbox' ) {
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_auto_responses',
								array( $column => $_POST[ $metaboxes[ $i ]['fields'][ $j ]['id'] ] ),
								array(
									'action'   => $action,
									'language' => $active_tab,
								),
								array( '%s' ),
								array( '%s', '%s' )
							);
						} elseif ( isset( $_POST[ $metaboxes[ $i ]['fields'][ $j ]['id'] ] ) ) {
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_auto_responses',
								array(
									$column => 1,
								),
								array(
									'action'   => $action,
									'language' => $active_tab,
								),
								array( '%d' ),
								array( '%s', '%s' )
							);
						} else {
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_auto_responses',
								array(
									$column => 0,
								),
								array(
									'action'   => $action,
									'language' => $active_tab,
								),
								array( '%d' ),
								array( '%s', '%s' )
							);
						}
					}
				}
			}

			if ( isset( $_GET['todo'] ) && $_GET['todo'] == 'save' ) {
				$messages[] = array(
					'type'    => 'message',
					'message' => __( 'Options successfully updated!', 'h3-mgmt' ),
				);
			}

			$page_args = array(
				'echo'            => true,
				'icon'            => 'icon-mails',
				'title'           => _x( 'Edit Automatic Responses', 'Admin Email Interface', 'h3-mgmt' ),
				'url'             => $url,
				'extra_head_html' => '<p>' .
						_x( 'You can use the following placeholders (where they make sense):', 'Admin Email Interface', 'h3-mgmt' ) .
						'<br />%code%, %donation%, %invitee%, %inviter%, %link%, %name%, %names%, %team_name%, %thumbs%</p>',
				'messages'        => $messages,
				'tabs'            => array(
					array(
						'title' => __( 'English', 'h3-mgmt' ),
						'value' => 'en',
						'icon'  => 'icon-flag-en',
					),
					array(
						'title' => __( 'German', 'h3-mgmt' ),
						'value' => 'de',
						'icon'  => 'icon-flag-de',
					),
				),
				'active_tab'      => $active_tab,
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			$form_args = array(
				'echo'       => true,
				'form'       => true,
				'headspace'  => true,
				'method'     => 'post',
				'metaboxes'  => true,
				'js'         => false,
				'url'        => $url,
				'action'     => $form_action,
				'id'         => $id,
				'button'     => __( 'Save Automatic Response Texts', 'h3-mgmt' ),
				'top_button' => true,
				'back'       => true,
				'back_url'   => $url,
				'fields'     => $metaboxes,
			);
			$the_form  = new H3_MGMT_Admin_Form( $form_args );

			$the_page->top();
			$the_form->output();
			$the_page->bottom();
		}

	} // class

endif; // class exists
