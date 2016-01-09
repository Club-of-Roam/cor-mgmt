<?php

/**
 * Template for the frontend user profile
 *
 **/

/* loop through fields */
if ( isset ( $fields ) && ! empty( $fields ) ) {
	foreach ( $fields as $field ) {

		if ( $field['type'] == 'section' ) {
			echo '<h3 class="top-space-more">' . $field['label'] . '</h3>';
			continue;
		}

		$field['value'] = esc_attr( get_user_meta( $user->ID, $field['id'], true ) );

		echo '<div class="form-row';
		if( isset( $field['row-class'] ) && ! empty( $field['row-class'] ) ) {
			echo ' ' . $field['row-class'];
		}
		echo '">';

		if( isset( $field['desc'] ) && ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . $field['desc'] . '</p>';
		}

		if( isset( $field['label'] ) && ! empty( $field['label'] ) ) {
			echo '<label for="' . $field['id'] . '">' .
					$field['label'];
				if( isset ( $field['tooltip'] ) && ! empty( $field['tooltip'] ) ) {
					echo '<span class="tip" onmouseover="tooltip(\'' .
						$field['tooltip'] .
						'\');" onmouseout="exit();">?</span>';
				}
			echo '</label>';
		}

		switch( $field['type'] ) {
			case 'textarea':
				echo '<textarea name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" cols="60" rows="4"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>' . $field['value'] . '</textarea>';
			break;

			case 'hidden':
				$output .= '<input type="hidden" ' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input"' .
					'" value="' . $field['value'] . '" />';
			break;

			case 'tel':
				echo '<input type="tel" class="input input-tel"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
			break;

			case 'email':
				echo '<input type="email" class="input input-email"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
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
					if( $field['value'] === $option['value'] ) {
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
				echo '<select name="' . $field['id'] . '-year' .
				'" id="' . $field['id'] . '-year' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';
				for( $i = intval(date('Y')); $i >= (intval(date('Y')) - 100); $i-- ) {
					echo '<option';
					if( date( 'Y', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && (intval(date('Y')) - 100) == $i ) ) {
						echo ' selected="selected"';
					}
					echo ' value="' . $i . '">' . $i . '</option>';
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
					if( date( 'n', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && intval(date('n')) == $i ) ) {
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

				echo '<select name="' . $field['id'] . '-day' .
				'" id="' . $field['id'] . '-day' . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '>';
				for( $i = 1; $i <= 31; $i++ ) {
					echo '<option';
					if( date( 'j', intval( $field['value'] ) ) == $i || ( $field['value'] === '' && intval(date('j')) == $i ) ) {
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
			break;

			case 'checkbox':
				echo '<span class="box-test"></span><input type="checkbox"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] . '" ';
				if( isset ( $field['value'] ) && ! empty( $field['value'] ) ) {
					echo ' checked="checked"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					echo ' disabled="disabled"';
				}
				echo '/><label for="' . $field['id'] . '"><span class="box"></span>' . $field['option'] . '</label>';
			break;

			case 'radio':
				echo '<br />';
				foreach ( $field['options'] as $option ) {
					echo '<span class="box-test"></span><input type="radio"' .
						'name="' . $field['id'] .
						'" id="' . $option['value'] .
						'" value="' . $option['value'] . '" ';
					if( $field['value'] == $option['value'] ) {
						echo ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' /><label for="' . $option['value'] . '"><span class="box"></span>' . $option['label'] . '</label><br />';
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

			case 'avatar':
				echo '<table style="font-size:1em !important;margin-bottom:0"><td style="min-width:100px;padding-right:21px">' . get_avatar( $user->ID );
				wp_nonce_field( 'simple_local_avatar_nonce', '_simple_local_avatar_nonce', false );
				echo '</td>';
				do_action( 'simple_local_avatar_notices' );
				echo '<td style="vertical-align:top"><input type="file" style="margin-bottom:7px" name="simple-local-avatar" id="simple-local-avatar" />';
				if ( empty( $user->simple_local_avatar ) ) {
					echo '<p class="description">' . __( 'No local avatar is set. Falling back to a gravatar, if exists.', 'h3-mgmt' ) . '</p>';
				} else {
					echo '<span class="box-test"></span><input type="checkbox" name="simple-local-avatar-erase" id="simple-local-avatar-erase" value="1" /><label for="simple-local-avatar-erase"><span class="box"></span>' . __( 'Delete avatar', 'h3-mgmt' ) . '</label><p class="description">' . __( 'Replace the local avatar by uploading a new avatar, or erase the local avatar (falling back to a gravatar) by checking the delete option.', 'h3-mgmt' ) . '</p>';
				}
				echo '</td></table><script type="text/javascript">' .
						'var form = document.getElementById("your-profile");' .
						'form.encoding = "multipart/form-data";' .
						'form.setAttribute("enctype", "multipart/form-data");' .
					'</script>';
			break;

			case 'text':
			default:
				echo '<input type="text" class="regular-text"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input"' .
					'" value="' . $field['value'] . '" size="30"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						echo ' disabled="disabled"';
					}
					echo ' />';
			break;
		} // type switch
		echo '</div>';
	} // foreach field
} // if ! empty

?>