<?php

/**
 * H3_MGMT_Admin_Form class.
 *
 * This class contains properties and methods
 * to display user input forms in the administrative backend
 *
 * @package Hitchhiking Hub Management
 * @since 1.1
 */

if ( ! class_exists( 'H3_MGMT_Admin_Form' ) ) :

class H3_MGMT_Admin_Form {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	private $default_args = array(
		'echo' => true,
		'form' => false,
		'headspace' => false,
		'method' => 'post',
		'metaboxes' => false,
		'js' => false,
		'url' => '#',
		'action' => '',
		'nonce' => 'h3-mgmt',
		'id' => 0,
		'button' => 'Save',
		'top_button' => true,
		'back' => false,
		'back_url' => '#',
		'has_cap' => true,
		'fields' => array()
	);
	private $args = array();

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function H3_MGMT_Admin_Form( $args ) {
		$this->__construct( $args );
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function __construct( $args ) {
		$this->default_args['button'] = __( 'Save', 'h3-mgmt' );

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
	 * @access public
	 */
	public function output() {
		extract( $this->args );

		$output = '';

		$the_button = '<input type="submit" name="submit" id="submit" class="button-primary" value="' . $button . '">';

		if ( $form ) {
			$output .= '<form name="h3-mgmt-form" method="' . $method . '" action="' . $action . '"';
			if ( $headspace ) {
				$output .= ' class="headspace"';
			}
			$output .= '>';
			if ( $back ) {
				$output .= '<a href="' . $back_url . '" class="button-secondary margin" title="' . __( 'Back to where you came from...', 'h3-mgmt' ) . '">' .
						'&larr; ' . __( 'back', 'h3-mgmt' ) .
					'</a>';
			}
			if ( $top_button && $has_cap ) {
				$output .= $the_button;
			}
			$output .= '<input type="hidden" name="submitted" value="y"/>' .
					'<input type="hidden" name="edit_val" value="' . $id . '"/>' ;
			if ( 'post' === $method ) {
				$output .= wp_nonce_field( $nonce, $nonce . '-nonce', false, false ) .
					wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false, false ) .
			        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false, false );
			}
		}

		if ( $metaboxes ) {
			$output .= '<div id="poststuff" class="noflow"><div id="post-body" class="metabox-holder columns-1"><div id="postbox-container-1" class="postbox-container">';
			if ( $js ) {
				$output .= '<div id="normal-sortables" class="meta-box-sortables ui-sortable">';
			}
			foreach ( $fields as $box ) {
				$output .= '<div class="postbox';
				if ( isset( $box['class'] ) ) {
					$output .= ' ' . $box['class'];
				}
				$output .= '">';
				if ( $js ) {
					$output .= '<div class="handlediv" title="' . esc_attr__('Click to toggle') . '"><br></div>' .
						'<h3 class="hndle"';
				} else {
					$output .= '<h3 class="no-hover"';
				}
				$output .= '><span>' . $box['title'] . '</span></h3>' .
					'<div class="inside">' .
						'<table class="form-table pool-form"><tbody>';

				foreach ( $box['fields'] as $field ) {
					$output .= $this->field( $field );
				}
				$output .= '</tbody></table></div></div>';
			}
		} else {
			$output .= '<table class="form-table pool-form"><tbody>';
			foreach ( $fields as $field ) {
				$output .= $this->field( $field );
			}
			$output .= '</tbody></table>';
		}

		if ( $metaboxes ) {
			if ( $js ) {
				$output .= '</div>';
			}
			$output .= '</div></div></div>';
		}

		if ( $form ) {
			if ( $has_cap ) {
				$output .= $the_button;
			}
			$output .= '</form>';
		}

		if ( $echo ) {
			echo $output;
		}
		return $output;
	}

	/**
	 * Returns the HTML
	 * for a single form table row
	 *
	 * @since 1.1
	 * @access private
	 */
	private function field( $field ) {

		$output = '';

		$field['name'] = ( ! isset( $field['name'] ) || empty( $field['name'] ) ) ? $field['id'] : $field['name'];

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = '';
		}

		if ( 'hidden' !== $field['type'] ) {
			$output .= '<tr valign="top" id="row-' . $field['id'] . '"';
			if ( ( isset( $field['row-class'] ) && isset( $field['js-only'] ) && true === $field['js-only'] ) || ( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) ) {
				$output .= 'class="';
				if ( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
					$output .= $field['row-class'];
				}
				if ( isset( $field['js-only'] ) && true === $field['js-only'] ) {
					if ( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
						$output .= ' ';
					}
					$output .= 'no-js-hide';
				}
				$output .= '"';
			}
			$output .= '><th scope="row">';
			if( $field['type'] != 'section' && isset( $field['label'] ) && ! empty( $field['label'] ) ) {
				$output .= '<label for="' .
					$field['id'] .
					'">' .
					$field['label'] .
					'</label>';
			}
			$output .= '</th><td>';
		}

		switch( $field['type'] ) {
			case 'section':
				$output .= '<h3>' . $field['label'] . '</h3>';
			break;

			case 'hidden':
				$output .= '<input type="hidden" ' .
					'name="' . $field['name'] .
					'" id="' . $field['id'] .
					'" class="input' .
					'" value="' . $field['value'] . '" />';
			break;

			case 'tel':
				$output .= '<input type="tel" class="input input-tel"' .
					'name="' . $field['name'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="40"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;

			case 'email':
				$output .= '<input type="email" class="input input-email"' .
					'name="' . $field['name'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="40"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;

			case 'textarea':
				$output .= '<textarea name="' . $field['name'] .
					'" id="' . $field['id'] .
					'" cols="100" rows="10"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>' . $field['value'] . '</textarea>';
			break;

			case 'select':
				$output .= '<select name="' . $field['name'] .
				'" id="' . $field['id'] . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';

				foreach ($field['options'] as $option) {
					$output .= '<option';
					if( ( $field['value'] == $option['value'] && $option['value'] != 0 ) || $field['value'] === $option['value'] ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $option['value'] . '">' . $option['label'] . '&nbsp;</option>';
				}
				$output .= '</select>';
			break;

			case 'checkbox':
				$output .= '<input type="checkbox"' .
					'name="' . $field['name'] .
					'" id="' . $field['id'] . '" ';
				if( isset( $field['value'] ) && ! empty( $field['value'] ) ) {
					$output .= ' checked="checked"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '/><label for="' . $field['id'] . '">' . $field['label'] . '</label>';
			break;

			case 'radio':
				$end = count( $field['options'] );
				$i = 1;
				foreach ( $field['options'] as $option ) {
					$output .= '<input type="radio"' .
						'name="' . $field['name'] .
						'" id="' . $field['id'] . '_' . $option['value'] .
						'" value="' . $option['value'] . '" ';

					if( $field['value'] == $option['value'] ) {
						$output .= ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						$output .= ' disabled="disabled"';
					}
					$output .= ' /><label for="' . $field['id'] . '_' . $option['value'] . '">' . $option['label'] . '</label>';
					if( $i < $end ) {
						$output .= '<br />';
					}
					$i++;
				}
			break;

			case 'checkbox_group':
			case 'checkbox-group':

				if( isset( $field['cols'] ) ) {
					$cols = $field['cols'];
				} else {
					$cols = 3;
				}

				if ( ! empty( $field['options'] ) ) {

					if( $cols !== 1 ) {
						$output .= '<table class="table-inside-table table-mobile-collapse subtable subtable-' . $field['id'] . '"><tr><td>';
						$i = 1;
						$end = count( $field['options'] );
					}
					$optcount = count( $field['options'] );
					$j = 1;
					foreach( $field['options'] as $option ) {

						$output .= '<input type="checkbox"' .
							'value="' . $option['value'] . '" ' .
							'name="' . $field['name'] . '[]" ' .
							'class="' . $field['id'] . '" ' .
							'id="' . $field['id'] . '_' . $option['value'] . '"';

						if( ( isset( $field['value'] ) && is_array( $field['value'] ) && in_array( $option['value'], $field['value'] ) )
							|| ( isset( $option['checked'] ) && true === $option['checked'] ) ) {
							$output .= ' checked="checked"';
						}
						if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
							$output .= ' disabled="disabled"';
						}

						$output .= ' /><label for="' . $field['id'] . '_' . $option['value'] . '">' .
							$option['label'] .
							'</label>';

						if( $cols !== 1 ) {
							if( ( $i % $cols ) === 0 ) {
								if( $i === $end ) {
									$output .= '</td></tr></table>';
								} else {
									$output .= '</td></tr><tr><td>';
								}
							} elseif( $i === $end ) {
								$empty_cell = '</td><td>';
								for( $i = 0; $i < ( $i % $cols ); $i++ ) {
									$$output .= $empty_cell;
								}
								$output .= '</td></tr></table>';
							} else {
								$output .= '</td><td>';
							}
							$i++;
						} else {
							$output .= '<br />';
						}
						//$j++;
					}
				} else {
					$output .= '<p>' . __( 'There is no data to select...', 'h3-mgmt' ) . '</p>';
				}

				if ( isset( $field['extra'] ) && 'bulk_deselect' === $field['extra'] ) {
					$output .= '<input type="submit" name="" class="button-secondary bulk-deselect" value="' .
								__( 'Deselect all', 'h3-mgmt' ) . '" /><br />';
				}
			break;

			case 'date':
				if( ! empty( $field['value'] ) ) {
					$stamp = strtotime( $field['value'] );
					$value = $field['value'];
					$day_val = date( 'd', $stamp );
					$month_val = date( 'm', $stamp );
					$year_val = date( 'Y', $stamp );
				} else {
					$value = '';
					$day_val = date( 'd' );
					$month_val = date( 'm' );
					$year_val = date( 'Y' );
				}
				$output .= '<input type="text" class="no-js-hide datepicker date';
				if ( isset( $field['required'] ) ) {
					$output .= ' required';
				}
				$output .= '" name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $value .
					'" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' /><select class="day js-hide js-hide" id="' . $field['id'] . '_day" name="' . $field['id'] . '_day"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for ( $i = 1; $i < 32; $i++ ) {
					$string = str_pad( $i, 2, '0', STR_PAD_LEFT );
					$output .= '<option value="' . $string . '"';
					if ( $day_val === $string ) {
						$output .= ' selected="selected"';
					}
					$output .= '>' .
							$string . '&nbsp;' .
						'</option>';
				}
				$output .= '</select><select class="months js-hide js-hide" id="' . $field['id'] . '_month" name="' . $field['id'] . '_month"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for ( $i = 1; $i < 13; $i++ ) {
					$string = str_pad( $i, 2, '0', STR_PAD_LEFT );
					$output .= '<option value="' . $string . '"';
					if ( $month_val === $string ) {
						$output .= ' selected="selected"';
					}
					$output .= '>' .
							$string . '&nbsp;' .
						'</option>';
				}
				$output .= '</select><select class="year js-hide js-hide" id="' . $field['id'] . '_year" name="' . $field['id'] . '_year"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for ( $i = 0; $i < 20; $i++ ) {
					$string = strval( 2012 + $i );
					$output .= '<option value="' . $string . '"';
					if ( $year_val === $string ) {
						$output .= ' selected="selected"';
					}
					$output .= '>' .
							$string . '&nbsp;' .
						'</option>';
				}
				$output .= '</select>';
			break;

			case 'text':
			default:
				$output .= '<input type="text"' .
					'name="' . $field['name'] .
					'" id="' . $field['id'] .
					'" class="input regular-text"' .
					'" value="' . $field['value'] . '" size="40"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;
		} // type switch

		if ( 'hidden' !== $field['type'] ) {
			if( isset( $field['desc'] ) && ! empty( $field['desc'] ) ) {
				if( ! in_array( $field['type'], array( 'hidden', 'checkbox_group', 'checkbox-group' ) ) ) {
					$output .= '<br />';
				}
				$output .= '<span class="description">' . $field['desc'] . '</span>';
			}
			$output .= '</td></tr>';
		}

		return $output;
	}

} // class

endif; // class exists

?>