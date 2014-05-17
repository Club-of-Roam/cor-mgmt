<?php

/**
 * H3_MGMT_Admin_Metaboxes class.
 *
 * This class contains properties and methods
 * to display sections in the admin backend
 *
 * @package Hitchhiking Hub Management
 * @since 1.1
 */

if ( ! class_exists( 'H3_MGMT_Admin_Metaboxes' ) ) :

class H3_MGMT_Admin_Metaboxes {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	private $default_args = array(
		'echo' => false,
		'columns' => 1,
		'running' => 1,
		'id' => '',
		'title' => 'Metabox',
		'js' => false
	);
	private $args = array();

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function H3_MGMT_Admin_Form( $args = array() ) {
		$this->__construct( $args = array() );
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function __construct( $args = array() ) {
		$this->args = wp_parse_args( $args, $this->default_args );
		if ( true === $this->args['js'] ) {
			wp_enqueue_script( 'postbox' );
			add_action( 'admin_footer', array( $this, 'print_script' ) );
		}
	}
	public function print_script() {
		echo '<script>jQuery(document).ready(function(){ postboxes.add_postbox_toggles(pagenow); });</script>';
	}

	/**
	 * Constructs the form HTML,
	 * echoes or returns it
	 *
	 * @since 1.1
	 * @access private
	 */
	private function output( $type, $add_args = array() ) {
		extract( $this->args );
		extract( $add_args, EXTR_OVERWRITE );

		$output = '';

		switch ( $type ) {
			case 'top':
				$output .= '<div id="poststuff" class="noflow">' .
						'<div id="post-body" class="metabox-holder columns-' . $columns . '">' .
							'<div id="postbox-container-' . $running . '" class="postbox-container">';
				if ( $js ) {
					$output .= '<div id="normal-sortables" class="meta-box-sortables ui-sortable">';
				}
			break;

			case 'mb_top':
				$output .= '<div' . ( ! empty( $id ) ? ' id="' . $id . '"' : '' ) . ' class="postbox ">';
				if ( $js ) {
					$output .= '<div class="handlediv" title="' . esc_attr__('Click to toggle') . '"><br></div>' .
						'<h3 class="hndle"';
				} else {
					$output .= '<h3 class="no-hover"';
				}
				$output .= '><span>' . $title . '</span></h3>' .
					'<div class="inside">';
			break;

			case 'mb_bottom':
				$output .= '</div></div>';
			break;

			case 'bottom':
			default:
				if ( $js ) {
					$output .= '</div>';
				}
				$output .= '</div></div></div>';
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
	 * Wrapper for top HTML of a single metabox
	 *
	 * @since 1.1
	 * @access public
	 */
	public function mb_top( $add_args = array() ) {
		return $this->output( 'mb_top', $add_args );
	}

	/**
	 * Wrapper for bottom HTML of a single metabox
	 *
	 * @since 1.1
	 * @access public
	 */
	public function mb_bottom() {
		return $this->output( 'mb_bottom' );
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