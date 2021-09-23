<?php

/**
 * H3_MGMT_Admin_Sponsors class.
 *
 * This class contains properties and methods for
 * the creation of new races.
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Sponsors' ) ) :

	class H3_MGMT_Admin_Sponsors {

		/**
		 * Sponsors administration menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function sponsors_control( $method = '' ) {
			global $wpdb, $h3_mgmt_teams;

			$messages = array();

			$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : '';

			if ( $todo == 'edit' ) {
				$this->sponsors_edit( $_GET['id'] );
			} elseif ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {
				switch ( $todo ) {

					case 'delete':
						if ( $_GET['id'] ) {
							$wpdb->query(
								'DELETE FROM ' .
								$wpdb->prefix . 'h3_mgmt_sponsors ' .
								"WHERE id = '" . $_GET['id'] . "' LIMIT 1"
							);
							$messages[] = array(
								'type'    => 'message',
								'message' => __( 'The selected donor has been successfully deleted.', 'h3-mgmt' ),
							);
						}
						unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					case 'save':
						if ( isset( $_GET['id'] ) && $_GET['id'] != null ) {
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_sponsors',
								array(
									'type'               => $_POST['type'],
									'method'             => $_POST['method'],
									'donation'           => $_POST['donation'],
									'language'           => $_POST['language'],
									'display_name'       => $_POST['display_name'],

									// 'first_name' => $_POST['first_name'],
									// 'last_name' => $_POST['last_name'],
									// 'accound_id' => $_POST['accound_id'],
									// 'bank_id' => $_POST['bank_id'],
									// 'bank_name' => $_POST['bank_name'],

									'paid'               => $_POST['paid'],
									'message'            => $_POST['message'],
									'team_id'            => $_POST['team_id'],
									'var_show'           => $_POST['var_show'],
									'race_id'            => $_POST['race_id'],

									'email'              => $_POST['email'],
									'owner_pic'          => $_POST['owner_pic'],
									'owner_link'         => $_POST['owner_link'],
									'street'             => $_POST['street'],
									'zip_code'           => $_POST['zip_code'],

									'city'               => $_POST['city'],
									'country'            => $_POST['country'],
									'address_additional' => $_POST['address_additional'],
									'receipt'            => $_POST['receipt'],
									'debit_confirmation' => isset( $_POST['debit_confirmation'] ) ? 1 : 0,
								),
								array( 'id' => $_GET['id'] ),
								array(
									'%s',
									'%s',
									'%d',
									'%s',
									'%s',
									// '%s', '%s', '%s', '%s', '%s',
									'%d',
									'%s',
									'%d',
									'%d',
									'%d',
									'%s',
									'%s',
									'%s',
									'%s',
									'%s',
									'%s',
									'%s',
									'%s',
									'%d',
									'%d',
								),
								array( '%d' )
							);
							$messages[] = array(
								'type'    => 'message',
								'message' => __( 'Donor successfully updated!', 'h3-mgmt' ),
							);
						}

						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					case 'paid':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_sponsors',
							array( 'paid' => 1 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected donor&apos;s payment status has been set to &quot;paid&quot;.', 'h3-mgmt' ),
						);

						unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					case 'unpaid':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_sponsors',
							array( 'paid' => 0 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected donor&apos;s payment status has been set to &quot;not paid&quot;.', 'h3-mgmt' ),
						);
						unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					case 'show':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_sponsors',
							array( 'var_show' => 1 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected sponsor does now appear in the frontend.', 'h3-mgmt' ),
						);
						unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					case 'hide':
						$wpdb->update(
							$wpdb->prefix . 'h3_mgmt_sponsors',
							array( 'var_show' => 0 ),
							array( 'id' => $_GET['id'] ),
							array( '%d' ),
							array( '%d' )
						);
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected sponsor does not appear in the frontend anymore!', 'h3-mgmt' ),
						);
						unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
						break;

					default:
						if ( 'debit' == $method ) {
							$this->sponsors_list( 'debit', $messages );
						} elseif ( 'paypal' == $method ) {
							$this->sponsors_list( 'paypal', $messages );
						} elseif ( 'betterplace' == $method ) {
							$this->sponsors_list( 'betterplace', $messages );
						} else {
							$this->sponsors_list( 'all', $messages );
						}
				}
			} elseif ( isset( $_GET['todo'] ) && 'save' == $_GET['todo'] ) {
				$wpdb->insert(
					$wpdb->prefix . 'h3_mgmt_sponsors',
					array(
						'type'               => $_POST['type'],
						'method'             => $_POST['method'],
						'donation'           => $_POST['donation'],
						'language'           => $_POST['language'],
						'display_name'       => $_POST['display_name'],

						// 'first_name' => $_POST['first_name'],
						// 'last_name' => $_POST['last_name'],
						// 'accound_id' => $_POST['accound_id'],
						// 'bank_id' => $_POST['bank_id'],
						// 'bank_name' => $_POST['bank_name'],

						'paid'               => $_POST['paid'],
						'message'            => $_POST['message'],
						'team_id'            => $_POST['team_id'],
						'var_show'           => $_POST['var_show'],
						'race_id'            => $_POST['race_id'],

						'email'              => $_POST['email'],
						'owner_pic'          => $_POST['owner_pic'],
						'owner_link'         => $_POST['owner_link'],
						'street'             => $_POST['street'],
						'zip_code'           => $_POST['zip_code'],

						'city'               => $_POST['city'],
						'country'            => $_POST['country'],
						'address_additional' => $_POST['address_additional'],
						'receipt'            => $_POST['receipt'],
						'debit_confirmation' => isset( $_POST['debit_confirmation'] ) ? 1 : 0,
					),
					array(
						'%s',
						'%s',
						'%d',
						'%s',
						'%s',
						// '%s', '%s', '%s', '%s', '%s',
						'%d',
						'%s',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%d',
					)
				);
				$messages[] = array(
					'type'    => 'message',
					'message' => __( 'Donor successfully added!', 'h3-mgmt' ),
				);

				if ( 'debit' == $method ) {
						$this->sponsors_list( 'debit', $messages );
				} elseif ( 'paypal' == $method ) {
						$this->sponsors_list( 'paypal', $messages );
				} elseif ( 'betterplace' == $method ) {
						$this->sponsors_list( 'betterplace', $messages );
				} else {
						$this->sponsors_list( 'all', $messages );
				}
			} elseif ( isset( $_GET['bulk'] ) && is_array( $_GET['bulk'] ) ) {
				$todo = isset( $_GET['todo'] ) ? $_GET['todo'] : $this->participants_list( $messages );

				foreach ( $_GET['bulk'] as $id ) {
					switch ( $todo ) {
						case 'bulk-delete':
							$wpdb->query(
								'DELETE FROM ' .
								$wpdb->prefix . 'h3_mgmt_sponsors ' .
								"WHERE id = '" . $id . "' LIMIT 1"
							);
							break;

						case 'bulk-paid':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_sponsors',
								array( 'paid' => 1 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							break;

						case 'bulk-unpaid':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_sponsors',
								array( 'paid' => 0 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							break;

						case 'bulk-show':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_sponsors',
								array( 'var_show' => 1 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							break;

						case 'bulk-hide':
							$wpdb->update(
								$wpdb->prefix . 'h3_mgmt_sponsors',
								array( 'var_show' => 0 ),
								array( 'id' => $id ),
								array( '%d' ),
								array( '%d' )
							);
							break;

						default:
							if ( 'debit' == $method ) {
								$this->sponsors_list( 'debit', $messages );
							} elseif ( 'paypal' == $method ) {
								$this->sponsors_list( 'paypal', $messages );
							} elseif ( 'betterplace' == $method ) {
								$this->sponsors_list( 'betterplace', $messages );
							} else {
								$this->sponsors_list( 'all', $messages );
							}
					}
				}
				switch ( $todo ) {
					case 'bulk-delete':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected donor has been successfully deleted.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-paid':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected donor&apos;s payment status has been set to &quot;paid&quot;.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-unpaid':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected donor&apos;s payment status has been set to &quot;not paid&quot;.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-show':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected sponsor does now appear in the frontend.', 'h3-mgmt' ),
						);
						break;

					case 'bulk-hide':
						$messages[] = array(
							'type'    => 'message',
							'message' => __( 'The selected sponsor does not appear in the frontend anymore!', 'h3-mgmt' ),
						);
						break;
				}
				unset( $_GET['todo'], $_GET['id'], $_GET['method'] );
				if ( 'debit' == $method ) {
					$this->sponsors_list( 'debit', $messages );
				} elseif ( 'paypal' == $method ) {
					$this->sponsors_list( 'paypal', $messages );
				} elseif ( 'betterplace' == $method ) {
					$this->sponsors_list( 'betterplace', $messages );
				} else {
					$this->sponsors_list( 'all', $messages );
				}
			} else {
				if ( 'debit' == $method ) {
					$this->sponsors_list( 'debit', $messages );
				} elseif ( 'paypal' == $method ) {
					$this->sponsors_list( 'paypal', $messages );
				} elseif ( 'betterplace' == $method ) {
					$this->sponsors_list( 'betterplace', $messages );
				} else {
					$this->sponsors_list( 'all', $messages );
				}
			}
		}

		/**
		 * Sponsors (Debit only) administration menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function debit_control() {
			$this->sponsors_control( 'debit' );
		}

		/**
		 * Sponsors (PayPal only) administration menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function paypal_control() {
			$this->sponsors_control( 'paypal' );
		}

		/**
		 * Sponsors (Betterplace only) administration menu
		 *
		 * @since 1.0
		 * @access public
		 */
		public function betterplace_control() {
			$this->sponsors_control( 'betterplace' );
		}

		/**
		 * List all sponsors
		 *
		 * @since 1.0
		 * @access private
		 */
		private function sponsors_list( $method = 'all', $messages = array() ) {
			global $wpdb, $h3_mgmt_sponsors, $H3_MGMT_Admin_Table, $h3_mgmt_races;

			$url             = 'admin.php?page=h3-mgmt-sponsors';
			$debit_url       = 'admin.php?page=h3-mgmt-sponsors-debit';
			$paypal_url      = 'admin.php?page=h3-mgmt-sponsors-debit';
			$betterplace_url = 'admin.php?page=h3-mgmt-sponsors-betterplace';

			$columns = array(
				array(
					'id'       => 'first_name',
					'title'    => __( 'First Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
				),
				array(
					'id'       => 'last_name',
					'title'    => __( 'Last Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
					'actions'  => array( 'edit', 'delete' ),
					'cap'      => 'sponsors',
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'team_name',
					'title'    => __( 'Team', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'type',
					'title'      => __( 'Type', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'type',
				),
				array(
					'id'       => 'code',
					'title'    => __( 'Code', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'method',
					'title'      => __( 'Method', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'method',
				),
				array(
					'id'         => 'paid',
					'title'      => __( 'Paid?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'paid',
					'actions'    => array( 'sponsor-payment' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'var_show',
					'title'      => __( 'Show on front?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'boolean',
					'actions'    => array( 'sponsor-show' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'donation',
					'title'      => __( 'Donation', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'donation',
				),
				array(
					'id'       => 'display_name',
					'title'    => __( 'Display Name', 'h3-mgmt' ),
					'sortable' => true,
				),
			);

			$debit_columns = array(
				array(
					'id'       => 'first_name',
					'title'    => __( 'First Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
				),
				array(
					'id'       => 'last_name',
					'title'    => __( 'Last Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
					'actions'  => array( 'edit', 'delete' ),
					'cap'      => 'sponsors',
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'team_name',
					'title'    => __( 'Team', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'bank_info',
					'title'    => __( 'Bank Info', 'h3-mgmt' ),
					'sortable' => false,
				),
				array(
					'id'         => 'type',
					'title'      => __( 'Type', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'type',
				),
				array(
					'id'         => 'paid',
					'title'      => __( 'Paid?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'paid',
					'actions'    => array( 'sponsor-payment' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'var_show',
					'title'      => __( 'Show on front?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'boolean',
					'actions'    => array( 'sponsor-show' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'donation',
					'title'      => __( 'Donation', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'donation',
				),
				array(
					'id'       => 'display_name',
					'title'    => __( 'Display Name', 'h3-mgmt' ),
					'sortable' => true,
				),
			);

			$paypal_columns = array(
				array(
					'id'       => 'first_name',
					'title'    => __( 'First Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
				),
				array(
					'id'       => 'last_name',
					'title'    => __( 'Last Name', 'h3-mgmt' ),
					'sortable' => true,
					'strong'   => true,
					'actions'  => array( 'edit', 'delete' ),
					'cap'      => 'sponsors',
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'team_name',
					'title'    => __( 'Team', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'type',
					'title'      => __( 'Type', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'type',
				),
				array(
					'id'         => 'paid',
					'title'      => __( 'Paid?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'paid',
					'actions'    => array( 'sponsor-payment' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'var_show',
					'title'      => __( 'Show on front?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'boolean',
					'actions'    => array( 'sponsor-show' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'donation',
					'title'      => __( 'Donation', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'donation',
				),
				array(
					'id'       => 'display_name',
					'title'    => __( 'Display Name', 'h3-mgmt' ),
					'sortable' => true,
				),
			);

			$betterlace_columns = array(
				array(
					'id'       => 'timestamp',
					'title'    => __( 'Date', 'h3-mgmt' ),
					'actions'  => array( 'edit', 'delete' ),
					'strong'   => true,
					'sortable' => true,
				),
				array(
					'id'       => 'race',
					'title'    => __( 'Race', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'team_name',
					'title'    => __( 'Team', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'         => 'type',
					'title'      => __( 'Type', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'type',
				),
				array(
					'id'         => 'paid',
					'title'      => __( 'Paid?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'paid',
					'actions'    => array( 'sponsor-payment' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'var_show',
					'title'      => __( 'Show on front?', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'boolean',
					'actions'    => array( 'sponsor-show' ),
					'cap'        => 'sponsors',
				),
				array(
					'id'         => 'donation',
					'title'      => __( 'Donation', 'h3-mgmt' ),
					'sortable'   => true,
					'conversion' => 'donation',
				),
				array(
					'id'       => 'display_name',
					'title'    => __( 'Display Name', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'donation_client_reference',
					'title'    => __( 'Donation Client Reference', 'h3-mgmt' ),
					'sortable' => true,
				),
				array(
					'id'       => 'donation_token',
					'title'    => __( 'Donation Token', 'h3-mgmt' ),
					'sortable' => true,
				),
			);

				$title = _x( 'TeamSponsors &amp; TeamOwners', 'Sponsoring, Backend', 'h3-mgmt' );
			if ( 'paypal' === $method ) {
						$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'team_name';
						$order   = isset( $_GET['order'] ) ? $_GET['order'] : 'ASC';
				$url             = $paypal_url;
				$columns         = $paypal_columns;
				$title           = _x( 'TeamSponsors &amp; TeamOwners, Donation Method: PayPal', 'Sponsoring Backend', 'h3-mgmt' );
			} elseif ( 'debit' === $method ) {
						$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'team_name';
						$order   = isset( $_GET['order'] ) ? $_GET['order'] : 'ASC';
				$url             = $debit_url;
				$columns         = $debit_columns;
				$title           = _x( 'TeamSponsors &amp; TeamOwners, Donation Method: Debit', 'Sponsoring, Backend', 'h3-mgmt' );
			} elseif ( 'betterplace' === $method ) {
						$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'timestamp';
						$order   = isset( $_GET['order'] ) ? $_GET['order'] : 'DESC';
				$url             = $betterplace_url;
				$columns         = $betterlace_columns;
				$title           = _x( 'TeamSponsors &amp; TeamOwners, Donation Method: Betterplace', 'Sponsoring, Backend', 'h3-mgmt' );
			} else {
					$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'timestamp';
					$order   = isset( $_GET['order'] ) ? $_GET['order'] : 'ASC';
			}

			list( $rows, $sponsor_counts, $donations ) = $h3_mgmt_sponsors->get_sponsors_meta(
				array(
					'orderby'        => $orderby,
					'order'          => $order,
					'type'           => 'all',
					'method'         => $method == 'betterplace' ? 'Betterplace' : $method,
					'exclude_unpaid' => false,
					'extra_fields'   => array( 'bank_info', 'race', 'code' ),
				)
			);

			$page_args = array(
				'echo'     => true,
				'icon'     => 'icon-sponsors',
				'title'    => $title,
				'url'      => $url,
				'messages' => $messages,
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			$filter            = array( 'race', 'team_name', 'type', 'paid', 'var_show' );
			$filter_dis_name   = array( 'Race', 'Team', 'Type', 'Paid?', 'Show on Front?' );
			$filter_conversion = array( '', '', '', 'boolean', 'boolean' );

			$active_race = $h3_mgmt_races->get_active_race();

			if ( isset( $active_race ) ) {
				$race_name = $wpdb->get_results(
					'SELECT name FROM ' . $wpdb->prefix . 'h3_mgmt_races ' .
						'WHERE id = ' . $active_race, ARRAY_A
				);
				$race_name = $race_name[0]['name'];
			} else {
				$race_name = null;
			}

			$pre_filtered = array( true, 'race', $race_name );

			$bulk_actions = array(
				array(
					'value' => 'bulk-paid',
					'label' => 'Sponsor has paid!',
				),
				array(
					'value' => 'bulk-unpaid',
					'label' => 'Not yet paid...',
				),
				array(
					'value' => 'bulk-show',
					'label' => 'Show Sponsor!',
				),
				array(
					'value' => 'bulk-hide',
					'label' => 'Hide Sponsor...',
				),
				array(
					'value' => 'bulk-delete',
					'label' => 'Delete',
				),
			);

			$tbl_args = array(
				'orderby'            => $orderby,
				'page_slug'          => 'h3-mgmt-sponsors' . ( 'all' === $method ? '' : '-' . $method ),
				'base_url'           => $url,
				'sort_url'           => $url,
				'pagination_url'     => $url,
				'icon'               => 'icon-teams',
				'headline'           => '',
				'headspace'          => false,
				'show_empty_message' => true,
				'empty_message'      => '',
				'pagination'         => false,
				'total_pages'        => 1,
				'current_page'       => 1,
				'dspl_cnt'           => true,
				'count'              => isset( $sponsor_counts[ $method ] ) ? $sponsor_counts[ $method ] : 0,
				'cnt_txt'            => '%d ' . __( 'Donors', 'h3-mgmt' ),
				'with_bulk'          => true,
				'bulk_btn'           => 'Execute',
				'bulk_confirm'       => '',
				'bulk_name'          => 'bulk',
				'bulk_param'         => 'todo',
				'bulk_desc'          => '',
				'extra_bulk_html'    => '',
				'bulk_actions'       => $bulk_actions,
				'filter'             => $filter,
				'filter_dis_name'    => $filter_dis_name,
				'filter_conversion'  => $filter_conversion,
				'pre_filtered'       => $pre_filtered,
			);

			$the_table = new H3_MGMT_Admin_Table( $tbl_args, $columns, $rows );

			echo    '<br style="clear:left;">
				<div class="message-secondary" style="float: right;"><p>	
				- If Donation Client Reference and Donation Token has a value everything is ok!<br>
				- If just Donation Client Reference has a value the donation broke at Betterplace (there was no redirect to our site)!<br>
				- If both without value the Donor was added via the Backend!
				</p></div>
				<br style="clear:left;">';

			$button = '<form method="post" action="' . $url . '&amp;todo=edit">' .
					'<input type="submit" class="button-secondary" value="+ ' . __( 'add donor', 'h3-mgmt' ) . '" />' .
					'</form>';

			$the_page->top();
			echo $button . '<br />';
			$the_table->output();
			echo $button . '<br />';
			$the_page->bottom();
		}

		/**
		 * Returns fields with values from $_POST
		 *
		 * @since 1.0
		 * @access private
		 */
		private function add_values( $fields ) {

			$fcount = count( $fields );
			if ( isset( $_POST['submitted'] ) ) {
				for ( $i = 0; $i < $fcount; $i++ ) {
					$fields[ $i ]['value'] = $_POST[ $fields[ $i ]['id'] ];
				}
			} elseif ( isset( $_GET['id'] ) && $fields[0]['id'] === 'team_id' ) {
				$fields[0]['value'] = $_GET['id'];
			}

			return $fields;
		}

		/**
		 * Edit a sponsors
		 *
		 * @since 1.0
		 * @access private
		 */
		private function sponsors_edit( $id = null ) {
			global $h3_mgmt_races, $h3_mgmt_sponsors, $h3_mgmt_teams;

			$url         = 'admin.php?page=h3-mgmt-sponsors';
			$form_action = $url . '&amp;todo=save&amp;id=' . $id;

			if ( $id === null ) {
				$title = __( 'Add New Sponsor', 'h3-mgmt' );
			} else {
				$sponsor = $h3_mgmt_sponsors->get_sponsor( $id );
				$title   = sprintf( __( 'Edit "%s"', 'h3-mgmt' ), stripslashes( $sponsor['first_name'] . ' ' . $sponsor['last_name'] ) );
			}

			wp_enqueue_script( 'h3-mgmt-admin-sponsors' );
			$scriptParams                      = array();
			$scriptParams['races']             = $h3_mgmt_races->get_races();
			$scriptParams['teams']             = array();
			$scriptParams['teamsWithoutOwner'] = array();
			if ( ! empty( $scriptParams['races'] ) && is_array( $scriptParams['races'] ) ) {
				foreach ( $scriptParams['races'] as $race_data ) {
					$scriptParams['teams'][ $race_data['id'] ]             = $h3_mgmt_teams->options_array(
						array(
							'orderby'            => 'team_name',
							'order'              => 'ASC',
							'please_select'      => false,
							'exclude_with_owner' => false,
							'owned_team_id'      => ( isset( $sponsor ) && isset( $sponsor['team_id'] ) ) ? $sponsor['team_id'] : 0,
							'exclude_incomplete' => true,
							'show_mates'         => false,
							'select_text'        => '',
							'race'               => $race_data['id'],
						)
					);
					$scriptParams['teamsWithoutOwner'][ $race_data['id'] ] = $h3_mgmt_teams->options_array(
						array(
							'orderby'            => 'team_name',
							'order'              => 'ASC',
							'please_select'      => false,
							'exclude_with_owner' => true,
							'owned_team_id'      => ( isset( $sponsor ) && isset( $sponsor['team_id'] ) ) ? $sponsor['team_id'] : 0,
							'exclude_incomplete' => true,
							'show_mates'         => false,
							'select_text'        => '',
							'race'               => $race_data['id'],
						)
					);
				}
			}
			if ( isset( $sponsor ) && isset( $sponsor['team_id'] ) ) {
				$scriptParams['sponsoredTeamID'] = $sponsor['team_id'];
			}
			wp_localize_script( 'h3-mgmt-admin-sponsors', 'sponsorsParams', $scriptParams );

			$boxes = array(
				array(
					'title'  => _x( 'The Donor', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => $h3_mgmt_sponsors->donor_fields(),
				),
				array(
					'title'  => _x( 'Type', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'id'      => 'type',
							'label'   => _x( 'Type', 'Sponsoring Form', 'h3-mgmt' ),
							'options' => array(
								array(
									'label' => _x( 'TeamSponsor', 'Sponsoring Form', 'h3-mgmt' ),
									'value' => 'sponsor',
								),
								array(
									'label' => _x( 'TeamOwner', 'Sponsoring Form', 'h3-mgmt' ),
									'value' => 'owner',
								),
							),
						),
					),
				),
				array(
					'title'  => _x( 'Sponsored Team', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => array(
						array(
							'id'      => 'race_id',
							'type'    => 'select',
							'label'   => __( 'Race / Event', 'h3-mgmt' ),
							'options' => $h3_mgmt_races->options_array( array( 'data' => 'race' ) ),
						),
						array(
							'id'      => 'team_id',
							'type'    => 'select',
							'label'   => __( 'Team', 'h3-mgmt' ),
							'options' => $h3_mgmt_teams->options_array(
								array(
									'orderby'            => 'team_name',
									'order'              => 'ASC',
									'please_select'      => true,
									'exclude_with_owner' => ( ! empty( $sponsor['type'] ) && 'owner' === $sponsor['type'] ) ? true : false,
									'owned_team_id'      => isset( $sponsor['team_id'] ) ? $sponsor['team_id'] : 0,
									'exclude_incomplete' => true,
									'race'               => ! empty( $sponsor['race_id'] ) ? $sponsor['race_id'] : 1,
								)
							),
						),
					),
				),
				array(
					'title'  => _x( 'Donation', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => 'donation',
							'label' => _x( 'Donation (in Euro)', 'Sponsoring Form', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Donation Method', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'id'      => 'method',
							'label'   => _x( 'Method', 'Sponsoring Form', 'h3-mgmt' ),
							'options' => array(
								array(
									'label' => _x( 'Debit', 'Sponsoring Form', 'h3-mgmt' ),
									'value' => 'debit',
								),
								array(
									'label' => _x( 'PayPal', 'Sponsoring Form', 'h3-mgmt' ),
									'value' => 'paypal',
								),
								array(
									'label' => _x( 'Betterplace', 'Sponsoring Form', 'h3-mgmt' ),
									'value' => 'Betterplace',
								),
							),
						),
						// array (
							// 'label'	=> _x( 'Account ID (KTN)', 'Sponsoring Form', 'h3-mgmt' ),
							// 'id'	=> 'account_id',
							// 'type'	=> 'text'
						// ),
						// array (
							// 'label'	=> _x( 'Bank ID (BLZ)', 'Sponsoring Form', 'h3-mgmt' ),
							// 'id'	=> 'bank_id',
							// 'type'	=> 'text'
						// ),
						// array (
							// 'label'	=> _x( 'Name of Bank', 'Sponsoring Form', 'h3-mgmt' ),
							// 'id'	=> 'bank_name',
							// 'type'	=> 'text'
						// ),
						// array (
							// 'label'	=> _x( 'Debit confirmation', 'Sponsoring Form', 'h3-mgmt' ),
							// 'id'	=> 'debit_confirmation',
							// 'type'	=> 'checkbox',
							// 'desc' => _x( 'By checking this box the sponsor confirms that Viva con Agua e.V. may debit the above sum from his/her above bank account.', 'Sponsoring Form','h3-mgmt' )
						// )
					),
				),
				array(
					'title'  => _x( 'Donation Receipt', 'Sponsoring Form', 'h3-mgmt' ),
					'class'  => 'receipt',
					'fields' => array(
						array(
							'label'   => _x( 'Donation Receipt', 'Sponsoring Form', 'h3-mgmt' ),
							'id'      => 'receipt',
							'type'    => 'radio',
							'options' => array(
								array(
									'value' => 0,
									'label' => __( 'No', 'h3-mgmt' ),
								),
								array(
									'value' => 1,
									'label' => __( 'Yes', 'h3-mgmt' ),
								),
							),
							'desc'    => _x( 'If the donated amount surpasses 200 Euros, the donor may ask to receive a donation receipt.', 'Sponsoring Form', 'h3-mgmt' ),
						),
						array(
							'label' => _x( 'Street Address', 'Sponsoring Form', 'h3-mgmt' ),
							'id'    => 'street',
							'type'  => 'text',
						),
						array(
							'label' => _x( 'Zip Code', 'Sponsoring Form', 'h3-mgmt' ),
							'id'    => 'zip_code',
							'type'  => 'text',
						),
						array(
							'label' => _x( 'City', 'Sponsoring Form', 'h3-mgmt' ),
							'id'    => 'city',
							'type'  => 'text',
						),
						array(
							'label' => _x( 'Country', 'Sponsoring Form', 'h3-mgmt' ),
							'id'    => 'country',
							'type'  => 'text',
						),
						array(
							'label' => _x( 'Additional Adress Field', 'Sponsoring Form', 'h3-mgmt' ),
							'id'    => 'address_additional',
							'type'  => 'text',
						),
					),
				),
				array(
					'title'  => _x( 'Message', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => $h3_mgmt_sponsors->message_field(),
				),
				array(
					'title'  => _x( 'Special TeamOwner Parameters', 'Sponsoring Form', 'h3-mgmt' ),
					'class'  => 'owner-special',
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => 'owner_link',
							'label' => _x( 'Link', 'Sponsoring Form', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'owner_pic',
							'label' => _x( 'Picture / Photo / Logo', 'Sponsoring Form', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => _x( 'Administrative Parameters', 'Sponsoring Form', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'radio',
							'id'      => 'paid',
							'label'   => _x( 'Has paid', 'Sponsoring Form', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => __( 'No', 'h3-mgmt' ),
								),
								array(
									'value' => 1,
									'label' => __( 'Yes', 'h3-mgmt' ),
								),
							),
							'desc'    => _x( 'Set whether the donor&apos;s donation has been received.', 'Sponsoring Form', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'id'      => 'var_show',
							'label'   => _x( 'Show in frontend', 'Sponsoring Form', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => __( 'No', 'h3-mgmt' ),
								),
								array(
									'value' => 1,
									'label' => __( 'Yes', 'h3-mgmt' ),
								),
							),
							'desc'    => _x( 'Set whether the donor should be shown to the public in the &quot;Donor&apos;s Overview&quot; page, as well as the relevant team&apos;s profile.', 'Sponsoring Form', 'h3-mgmt' ),
						),
						array(
							'type'    => 'select',
							'id'      => 'language',
							'label'   => _x( 'Preferred Language', 'Sponsoring Form', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 'en',
									'label' => __( 'English', 'h3-mgmt' ),
								),
								array(
									'value' => 'de',
									'label' => __( 'German', 'h3-mgmt' ),
								),
							),
							'desc'    => _x( 'The language the donor should receive E-Mails in. Automatically set when submitted from the frontend, depending on the language the website is viewed in.', 'Sponsoring Form', 'h3-mgmt' ),
						),
					),
				),
			);

			$mcount = count( $boxes );
			for ( $i = 0; $i < $mcount; $i++ ) {
				$fcount = count( $boxes[ $i ]['fields'] );
				for ( $j = 0; $j < $fcount; $j++ ) {
					if ( isset( $_POST['submitted'] ) ) {
						$boxes[ $i ]['fields'][ $j ]['value'] = stripslashes( $_POST[ $boxes[ $i ]['fields'][ $j ]['id'] ] );
					} elseif ( is_numeric( $id ) ) {
						$boxes[ $i ]['fields'][ $j ]['value'] = ! empty( $sponsor[ $boxes[ $i ]['fields'][ $j ]['id'] ] ) ? stripslashes( $sponsor[ $boxes[ $i ]['fields'][ $j ]['id'] ] ) : '';
					}
				}
			}

			$page_args = array(
				'echo'     => true,
				'icon'     => 'icon-donations',
				'title'    => $title,
				'url'      => $url,
				'messages' => array(),
			);
			$the_page  = new H3_MGMT_Admin_Page( $page_args );

			$form_args = array(
				'echo'       => true,
				'form'       => true,
				'method'     => 'post',
				'metaboxes'  => true,
				'js'         => false,
				'url'        => $url,
				'action'     => $form_action,
				'id'         => $id,
				'button'     => __( 'Save Sponsor', 'h3-mgmt' ),
				'top_button' => true,
				'back'       => true,
				'back_url'   => $url,
				'fields'     => $boxes,
			);
			$the_form  = new H3_MGMT_Admin_Form( $form_args );

			$the_page->top();
			$the_form->output();
			$the_page->bottom();
		}

	} // class

endif; // class exists
