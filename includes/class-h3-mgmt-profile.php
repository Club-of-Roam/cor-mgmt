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
	private function create_extra_profile_fields() {

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
	 * PHP4 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function H3_MGMT_Profile() {
		$this->__construct();
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
