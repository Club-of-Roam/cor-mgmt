<?php

/**
 * H3_MGMT_Profile class.
 * This class contains properties and methods for additional user profile fields.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Profile' ) ) :

class H3_MGMT_Profile {

	/**
	 * Returns an array containing new profile fields
	 *
	 * @since 1.0
	 * @access private
	 */
	function create_extra_profile_fields() {

		$fields = array(
			array(
				'label' => _x( 'About you', 'User Profile', 'h3-mgmt' ),
				'type' => 'section'
			),
			array(
				'label' => _x( 'City', 'User Profile', 'h3-mgmt' ),
				'id' => 'city',
				'type' => 'text'
			),
			array(
				'label' => _x( 'Mobile Phone', 'User Profile', 'h3-mgmt' ),
				'id' => 'mobile',
				'type' => 'text'
			),
			array(
				'label' => _x( 'Date of Birth', 'User Profile', 'h3-mgmt' ),
				'id' => 'birthday',
				'type' => 'date'
			),
			array (
				'label'	=> _x( 'Shirt Size', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'shirt_size',
				'type'	=> 'select',
				'options' => array(
					0 => array(
						'value' => 0,
						'label' => _x( 'Please select your size...', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'ms',
						'label' => _x( "Unisex S", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'mm',
						'label' => _x( "Unisex M", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'ml',
						'label' => _x( "Unisex L", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'mx',
						'label' => _x( "Unisex XL", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gs',
						'label' => _x( 'Slimfit S', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gm',
						'label' => _x( 'Slimfit M', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'gl',
						'label' => _x( 'Slimfit L', 'Team Profile Form', 'h3-mgmt' )
					)
				)
			),
			array (
				'label'	=> _x( 'Could we give our partner Ortel Mobile your personal Information?', 'Team Profile Form', 'h3-mgmt' ),
				'id'	=> 'public_mobile_inf',
				'type'	=> 'select',
				'options' => array(
					0 => array(
						'value' => 0,
						'label' => _x( 'Please select if it is ok or not...', 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'yes',
						'label' => _x( "YES, give them my personal information", 'Team Profile Form', 'h3-mgmt' )
					),
					array(
						'value' => 'no',
						'label' => _x( "Please not (So you do not get your sponsored sim-card", 'Team Profile Form', 'h3-mgmt' )
					)					
				)
                        ),
			array (
				'label'	=> _x( 'Address for Ortel', 'Team Profile Form', 'h3-mgmt' ),
                                'id'	=> 'addressMobile',
				'type'	=> 'textarea'
			),
			array(
				'label' => _x( 'Avatar', 'User Profile', 'h3-mgmt' ),
				'type' => 'section',
				'admin_hide' => true
			),
			array(
				'type' => 'avatar',
				'id' => 'simple-local-avatar',
				'admin_hide' => true
			)
		);
		return $fields;
	}

	/**
	 * Adds to user's profile view
	 *
	 * @since 1.0
	 * @access public
	 */
	public function user_extra_profile_fields( $user ) {
		$fields = $this->create_extra_profile_fields();
		require_once( H3_MGMT_ABSPATH . '/templates/frontend-profile.php' );
	}

	/**
	 * Adds to admin's userprofile view
	 *
	 * @since 1.0
	 * @access public
	 */
	public function admin_extra_profile_fields( $user ) {
		$fields = $this->create_extra_profile_fields();
		require_once( H3_MGMT_ABSPATH . '/templates/admin-profile.php' );
	}

	public function save_extra_profile_fields( $user_id ) {
		global $h3_mgmt_regions, $h3_mgmt_mailer;

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( isset( $_POST['deleteme'] ) && $_POST['deleteme'] == 'forever' ) {
			wp_delete_user( $user_id );
			wp_redirect( get_bloginfo('url'), 200 );
			exit;
			return false;
		}

		$fields = $this->create_extra_profile_fields();
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				switch( $field['type'] ) {
					case 'date':
						update_user_meta(
							$user_id,
							$field['id'],
							mktime( 0, 0, 0,
								$_POST[ $field['id'] . '-month' ],
								$_POST[ $field['id'] . '-day' ],
								$_POST[ $field['id'] . '-year' ]
							)
						);
					break;

					default:
						update_user_meta( $user_id, $field['id'], $_POST[$field['id']] );
					break;
				}
			}
		}
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( &$this, 'user_extra_profile_fields' ) );
		add_action( 'edit_user_profile', array( &$this, 'admin_extra_profile_fields' ) );
		add_action( 'personal_options_update', array( &$this, 'save_extra_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( &$this, 'save_extra_profile_fields' ) );
	}
}

endif; // class exists

?>
