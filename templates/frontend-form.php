<?php

/**
 * Template for forms used in the frontend
 *
 **/

if( ! isset( $output ) ) {
	$output = '';
}

/* loop through fields */
if( isset ( $fields ) &&  ! empty( $fields ) ) {
	foreach ( $fields as $field ) {

		if ( $field['type'] == 'section' ) {
			$output .= '<h3 class="top-space-more">' . $field['label'] . '</h3>';
			continue;
		}

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = '';
		}
		
		if ( $field['type'] == 'radio' ) {
			$output .= '<div style="margin-bottom: 15px;';
		}else {	
			$output .= '<div class="form-row';
		}
		if ( isset( $field['type'] ) && 'hidden' === $field['type'] ) {
			$output .= ' hidden-field-row no-margin-bottom';
		}
		if ( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
			$output .= ' ' . $field['row-class'];
		}
		if ( isset( $field['label'] ) ) { // && ! empty( $field['label'] )
			$output .= '">' .
				'<label for="' . $field['id'] . '">' .
					$field['label'];
				if( isset ( $field['tooltip'] ) && ! empty( $field['tooltip'] ) ) {
					$output .= '<span class="tip" onmouseover="tooltip(\'' .
						str_replace( '"', '&quot;', str_replace( "'", '&apos;', $field['tooltip'] ) ) .
						'\');" onmouseout="exit();"> (?)</span>';
				}
			$output .= '</label>' ;
		}

		switch( $field['type'] ) {
			case 'note':
				$output .= '<p id="' . $field['id'] . '"><em>' . $field['note'] . '</em></p>';
			break;

			case 'textarea':
				$output .= '<textarea name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" cols="60" rows="6"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>' . $field['value'] . '</textarea>';
			break;

			case 'hidden':
				$output .= '<input type="hidden" ' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input"' .
					'" value="' . $field['value'] . '" />';
			break;

			case 'tel':
				$output .= '<input type="tel" class="input input-tel"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;

			case 'email':
				$output .= '<input type="email" class="input input-email"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;

			case 'select':
				$output .= '<select name="' . $field['id'] .
				'" id="' . $field['id'] . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';

				foreach ($field['options'] as $option) {
					$output .= '<option';
					if( $field['value'] == $option['value'] ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $option['value'] . '">' . $option['label'] . '&nbsp;</option>';
				}
				$output .= '</select>';
			break;

			case 'checkbox':
				$output .= '<span class="box-test"></span><input type="checkbox"' .
					'name="' . $field['id'] . '" ' .
					'id="' . $field['id'] . '" ' .
					'value="1" ';
				if( isset ( $field['value'] ) && $field['value'] == 1 ) {
					$output .= ' checked="checked"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '/><label for="' . $field['id'] . '"><span class="box"></span>' . ( ! empty( $field['option'] ) ? $field['option'] : '' ) . '</label>';
			break;

			case 'radio':
				foreach ( $field['options'] as $option ) {
					$output .= '<input type="radio"' .
						'name="' . $field['id'] .
						'" id="' . $option['value'] .
						'" value="' . $option['value'] . '" ';

					if( $field['value'] == $option['value'] ) {
						$output .= ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						$output .= ' disabled="disabled"';
					}

					$output .= ' /><label style="display:inline;" for="' . $field['id'] . '_' . $option['value'] . '">' . $option['label'] . '</label><br />';
				}
			break;

			case 'checkbox_group':
				foreach( $field['options'] as $option ) {
					$output .= '<input type="checkbox"' .
						'value="' . $option['value'] .
						'" name="' . $field['id'] . '[]' .
						'" class="' . $field['id'] .
						'" id="' . $field['id'] . '_' . $option['value'] . '"';

					if( isset( $field['value'] ) && is_array( $field['value'] ) && in_array( $option['value'], $field['value'] ) ) {
							$output .= ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						$output .= ' disabled="disabled"';
					}

					$output .= ' /><label for="' . $field['id'] . '_' . $option['value'] . '">' .
						$option['label'] .
						'</label><br />';
				}
			break;

			case 'date':

				$output .= '<select name="' . $field['id'] . '-year' .
				'" id="' . $field['id'] . '-year' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for( $i = intval(date('Y')); $i >= (intval(date('Y')) - 100); $i-- ) {
					$output .= '<option';
					if( date( 'Y', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && (intval(date('Y')) - 100) == $i ) ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $i . '">' . $i . '</option>';
				}
				$output .= '</select>';

				$output .= '<select name="' . $field['id'] . '-month' .
				'" id="' . $field['id'] . '-month' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for( $i = 1; $i <= 12; $i++ ) {
					$output .= '<option';
					if( date( 'n', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && intval(date('n')) == $i ) ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $i . '">';
					if( $i < 10 ) {
						$output .= '0' . $i;
					} else {
						$output .= $i;
					}
					$output .= '</option>';
				}
				$output .= '</select>';

				$output .= '<select name="' . $field['id'] . '-day' .
				'" id="' . $field['id'] . '-day' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for( $i = 1; $i <= 31; $i++ ) {
					$output .= '<option';
					if( date( 'j', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && intval(date('j')) == $i ) ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $i . '">';
					if( $i < 10 ) {
						$output .= '0' . $i;
					} else {
						$output .= $i;
					}
					$output .= '</option>';
				}
				$output .= '</select>';
			break;

			case 'single-pic-upload':
				$output .= '<input type="file" name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" accept="image/jpeg,image/gif,image/png"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
				if( isset( $field['value'] ) && ! empty( $field['value'] ) ) {
						$output .= '<input type="hidden" ' .
							'name="' . $field['id'] . '-tmp" ' .
							'id="' . $field['id'] . '-tmp" ' .
							'value="' . $field['value'] . '" />' .
							'<img class="form-img no-bsl-adjust" alt="Your Pic" src="' .
								$field['value'] .
							'" style="height:150px;"/>';
				}
			break;

			case 'text-repeatable-no-js':
				for( $i = 0; $i < $field['num']; $i++ ) {
					$output .= '<input type="text" name="' .$field['id'] . '['.$i.']" ' .
						'id="' . $field['id'] . '" value="';
					if( is_array( $field['value'] ) && ! empty( $field['value'][$i] ) ) {
						$output .= $field['value'][$i];
					}
					$output .= '" size="30" />';

					if( ( $i + 1 ) <  $field['num'] ) {
						$output .= '</div><div class="form-row';
						if( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
							$output .= ' ' . $field['row-class'];
						}
						$output .= '">';
						if( isset( $field['label'] ) && ! empty( $field['label'] ) ) {
							$output .= '<label for="' . $field['id'] . '">' .
								$field['label'] . ' ' . ( $i + 2 );
							if( isset ( $field['tooltip'] ) && ! empty( $field['tooltip'] ) ) {
								$output .= '<span class="tip" onmouseover="tooltip(\'' .
									str_replace( '"', '&quot;', str_replace( "'", '&apos;', $field['tooltip'] ) ) .
									'\');" onmouseout="exit();"> (?)</span>';
							}
							$output .= '</label>' ;
						}
					}
				}
			break;

			case 'text':
			default:
				$output .= '<input type="text" class="regular-text"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input"' .
					'" value="' . $field['value'] . '" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				if( isset( $field['readonly'] ) && $field['readonly'] === true ) {
					$output .= ' readonly';
				}
				$output .= ' />';
			break;
		} // type switch
		if( isset( $field['desc'] ) && ! empty( $field['desc'] ) ) {
			if ( 'single-pic-upload' === $field['type'] ) {
				$output .= '<br />';
			}
			$output .= '<span class="description">' . $field['desc'] . '</span>';
		}
		$output .= '</div>';
	} // foreach field
} // if ! empty

?>
