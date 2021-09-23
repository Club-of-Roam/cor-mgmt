<?php

/**
 * H3_MGMT_Admin_Page class.
 *
 * This class contains properties and methods
 * to display the very basic elements of every backend page
 *
 * @package Hitchhiking Hub Management
 * @since 1.1
 */

if ( ! class_exists( 'H3_MGMT_Admin_Page' ) ) :

class H3_MGMT_Admin_Page {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	private $default_args = array(
		'echo' => false,
		'icon' => 'icon-races',
		'title' => 'Admin Page',
		'active_tab' => '',
		'url' => '?page=admin.php',
		'extra_head_html' => '',
		'tabs' => array(),
		'messages' => array()
	);
	private $args = array();

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function __construct( $args = array() ) {
		$this->args = wp_parse_args( $args, $this->default_args );
	}

	/**
	 * Constructs HTML,
	 * echoes or returns it
	 *
	 * @since 1.1
	 * @access private
	 */
	private function output( $type ) {
		global $h3_mgmt_admin;

		extract( $this->args );

		$output = '';

		switch ( $type ) {
			case 'top':
				$output .= '<div class="wrap">' .
					'<div id="' . $icon . '" class="icon32-h3"></div>' .
					'<h2>' . $title . '</h2><br />';

				if( ! empty( $messages ) ) {
					$output .= $h3_mgmt_admin->convert_messages( $messages );
				}

				if( ! empty( $extra_head_html ) ) {
					$output .= $extra_head_html;
				}

				if( ! empty( $tabs ) && is_array( $tabs ) ) {
					$output .= '<h2 class="nav-tab-wrapper">';
					$i = 0;
					foreach ( $tabs as $tab ) {
						$output .= '<a href="' . $url . '&tab=' . $tab['value'] . '" class="nav-tab ' . ( $tab['value'] === $active_tab || ( $tab['value'] === '' && 0 === $i ) ? 'nav-tab-active' : '' ) . '">' .
								'<div class="nav-tab-icon nt-' . $tab['icon'] . '"></div>' .
								$tab['title'].
							'</a>';
						$i++;
					}
					$output .= '</h2>';
				}
			break;

			case 'bottom':
				$output .= '</div>';
			default:

			break;
		}

		if ( $echo ) {
			echo $output;
		}
		return $output;
	}

	/**
	 * Wrapper for top HTML
	 *
	 * @since 1.1
	 * @access public
	 */
	public function top() {
		return $this->output( 'top' );
	}

	/**
	 * Wrapper for bottom HTML
	 *
	 * @since 1.1
	 * @access public
	 */
	public function bottom() {
		return $this->output( 'bottom' );
	}

} // class

endif; // class exists

?>