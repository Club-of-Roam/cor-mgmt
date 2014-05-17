<?php

global $user_id;

echo '<table class="form-table"><tbody>';

/* loop through fields */
if ( isset ( $fields ) && ! empty( $fields ) ) {
	foreach ( $fields as $field ) {

		$field['value'] = ( ! isset( $field['type'] ) || 'section' !== $field['type'] ) ? esc_attr( get_user_meta( $user_id, $field['id'], true ) ) : NULL;
		$field['id'] = isset( $field['id'] ) ? $field['id'] : '';

		echo '<tr';
		if( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
			echo ' class="' . $field['row-class'] . '"';
		}
		echo '><th>';

		if( isset( $field['label'] ) &&
		   ! empty( $field['label'] ) &&
		   ( ! isset( $field['admin_hide'] ) || $field['admin_hide'] !== true ) ) {
			echo '<label for="' . $field['id'] . '">';
			if( $field['type'] == 'section' ) {
				echo '<h3>';
			}
			echo $field['label'];
			if( $field['type'] == 'section' ) {
				echo '</h3>';
			}
			echo '</label>';
		}

		echo '</th><td>';

		switch( $field['type'] ) {
			case 'section':
				echo '';
			break;

			case 'hidden':
				$output .= '<input type="hidden" ' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input"' .
					'" value="' . $field['value'] . '" />';
			break;

			case 'tel':
				if( ! isset( $field['admin_hide'] ) || $field['admin_hide'] !== true ) {
					echo '<input type="tel" class="input input-tel"' .
						'name="' . $field['id'] .
						'" id="' . $field['id'] .
						'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
				}
			break;

			case 'email':
				if( ! isset( $field['admin_hide'] ) || $field['admin_hide'] !== true ) {
					echo '<input type="email" class="input input-email"' .
						'name="' . $field['id'] .
						'" id="' . $field['id'] .
						'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
				}
			break;

			case 'textarea':
				echo '<textarea name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" cols="60" rows="4"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>' .$field['value'] . '</textarea>';
			break;

			case 'select':
				echo '<select name="' . $field['id'] .
				'" id="' . $field['id'] . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';

				foreach ($field['options'] as $option) {
					echo '<option';
					if( $field['value'] == $option['value'] ) {
						echo ' selected="selected"';
					}
					if( isset( $option['class'] ) && ! empty( $option['class'] ) ) {
						echo ' class="' . $option['class'] . '"';
					}
					echo ' value="' . $option['value'] . '">' . $option['label'] . '&nbsp;</option>';
				}
				echo '</select>';
			break;

			case 'date':
				echo '<select name="' . $field['id'] . '-day' .
				'" id="' . $field['id'] . '-day' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';
				for( $i = 1; $i <= 31; $i++ ) {
					echo '<option';
					if( date( 'd', intval( $field['value'] ) ) == $i ) {
						echo ' selected="selected"';
					}
					echo ' value="' . $i . '">';
					if( $i < 10 ) {
						echo '0' . $i;
					} else {
						echo $i;
					}
					echo '</option>';
				}
				echo '</select>';

				echo '<select name="' . $field['id'] . '-month' .
				'" id="' . $field['id'] . '-month' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';
				for( $i = 1; $i <= 12; $i++ ) {
					echo '<option';
					if( date( 'n', intval( $field['value'] ) ) == $i ) {
						echo ' selected="selected"';
					}
					echo ' value="' . $i . '">';
					if( $i < 10 ) {
						echo '0' . $i;
					} else {
						echo $i;
					}
					echo '</option>';
				}
				echo '</select>';

				echo '<select name="' . $field['id'] . '-year' .
				'" id="' . $field['id'] . '-year' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';
				for( $i = 2012; $i >= 1910; $i-- ) {
					echo '<option';
					if( date( 'Y', intval( $field['value'] ) ) == $i ) {
						echo ' selected="selected"';
					}
					echo ' value="' . $i . '">' . $i . '</option>';
				}
				echo '</select>';
			break;

			case 'membership':
			case 'checkbox':
				echo '<input type="checkbox"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] . '" ';
				if( isset ( $field['value'] ) && ! empty( $field['value'] ) ) {
					echo ' checked="checked"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '/><label for="' . $field['id'] . '">' . ( isset( $field['option'] ) ? $field['option'] : '' ) . '</label>';
			break;

			case 'radio':
				foreach ( $field['options'] as $option ) {
					echo '<input type="radio"' .
						'name="' . $field['id'] .
						'" id="' . $field['id'] . '_' . $option['value'] .
						'" value="' . $option['value'] . '" ';
					if( $field['value'] == $option['value'] ) {
						echo ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' /><label for="' . $field['id'] . '_' . $option['value'] . '">' . $option['label'] . '</label><br />';
				}
			break;

			case 'checkbox_group':
				foreach( $field['options'] as $option ) {
					echo '<input type="checkbox"' .
						'value="' . $option['value'] .
						'" name="' . $field['id'] .
						'" id="' . $option['value'] . '"';
					if(
						isset( $field['value'] )
						&& is_array( $field['value'] )
						&& in_array( $option['value'], $field['value'] )
					) {
							echo ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' /><label for="' .$option['value'] . '">' .
						$option['label'] .
						'</label><br />';
				}
			break;

			case 'text':
			default:
				if( ! isset( $field['admin_hide'] ) || $field['admin_hide'] !== true ) {
					echo '<input type="text" class="input regular-text"' .
						'name="' . $field['id'] .
						'" id="' . $field['id'] .
						'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
				}
			break;
		} // type switch
		if( isset( $field['desc'] ) && ! empty( $field['desc'] ) ) {
			echo '<br /><span class="description">' . $field['desc'] . '</span>';
		}
		echo '</td></tr>';
	} // foreach field
	echo '</tbody></table>';
} // if ! empty

?>