<?php
require_once __DIR__ . "/class-h3-mgmt-security.php";

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
		echo H3_MGMT_Security::nonce('cor-mgmt-delete-account-' . (int) $user->ID);
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
        $user_id = H3_MGMT_Security::id($user_id);
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!$user_id || !current_user_can('edit_user', $user_id) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'update-user_' . $user_id)) { return false; }
        if (isset($_POST['deleteme'])) {
            // Account deletion is a distinct destructive operation, never an incidental profile update.
            if ($_POST['deleteme'] !== 'forever' || get_current_user_id() !== $user_id
                || !H3_MGMT_Security::post('cor-mgmt-delete-account-' . $user_id)) { return false; }
            global $wpdb;
            if ($wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE user_id=%d LIMIT 1', $wpdb->prefix . 'h3_mgmt_teammates', $user_id))) { return false; }
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            return true;
        }
        try {
            \Cor\Mgmt\WordPress\ProfileAvatar::save($user_id);
        } catch (\Throwable $error) {
            return false;
        }

        return true;
    }

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_action('profile_personal_options', static function ($user): void { echo H3_MGMT_Security::nonce('cor-mgmt-delete-account-' . (int) $user->ID); });
        add_action( 'show_user_profile', array( &$this, 'user_extra_profile_fields' ) );
		add_action( 'edit_user_profile', array( &$this, 'admin_extra_profile_fields' ) );
		add_action( 'personal_options_update', array( &$this, 'save_extra_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( &$this, 'save_extra_profile_fields' ) );
	}
}

endif; // class exists
