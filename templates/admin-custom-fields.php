<?php

/**
 * Template for custom post types
 * (could be used for regular post's custom fields as well)
 *
 **/

global $post, $wpdb, $current_user;
get_currentuserinfo();

if ( ! isset( $output ) ) {
	$output = '';
}

/* table & loop through fields */
$output .=  '<table class="form-table">';

if ( isset ( $fields ) &&  ! empty( $fields ) ) {
	foreach ( $fields as $field ) {

		/* get value of this field if it exists for this post */
		$meta = get_post_meta( $post->ID, $field['id'], true );
		$meta = ( ! empty( $meta ) || '0' === $meta || 0 === $meta ) ? $meta : ( ! empty( $field['default'] ) ? $field['default'] : $meta );

		switch( $field['type'] ) {
			default:
				$output .= '<tr><th><label for="'.$field['id'].'">'.$field['label'];
				if ( isset( $field['required'] ) ) {
					$output .= ' <span class="required">*</span>';
				}
				$output .= '</label></th><td>';
			break;
		}

		switch( $field['type'] ) {

			case 'hidden':
				$output .= '<input type="hidden" ' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" class="input' .
					'" value="' . $field['value'] . '" />';
			break;

			case 'textarea':
				$output .= '<textarea name="'. $field['id'] .
					'" id="' . $field['id'] . '" ';
				if ( isset( $field['required'] ) ) {
					$output .= ' class="required"';
				}
				$output .= 'cols="60" rows="4"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>' . $meta . '</textarea>';
			break;

			case 'select':
				$output .= '<select name="' . $field['id'] .
				'" id="' . $field['id'] . '" ';
				if ( isset( $field['required'] ) ) {
					$output .= ' class="required"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';

				foreach ($field['options'] as $option) {
					$output .= '<option';
					if( $meta == $option['value'] ) {
						$output .= ' selected="selected"';
					}
					$output .= ' value="' . $option['value'] . '">' . $option['label'] . '&nbsp;</option>';
				}
				$output .= '</select>';
			break;

			case 'checkbox':
				$output .= '<input type="checkbox"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] . '" ' .
					'value="' . $field['value'] . '" ';
				if( isset ( $meta ) && $meta == $field['value'] ) {
					$output .= ' checked="checked"';
				}
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '/><label for="' . $field['id'] . '">' . $field['option'] . '</label>';
			break;

			case 'radio':
				$last_val = end( $field['options'] );
				foreach ( $field['options'] as $option ) {
					$output .= '<input type="radio" ' .
						'name="' . $field['id'] .
						'" id="' . $option['value'] .
						'" value="' . $option['value'] . '" ';
					if( $meta == $option['value'] || empty( $meta ) && isset( $option['default'] ) && true === $option['default'] ) {
						$output .= ' checked="checked"';
					}
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						$output .= ' disabled="disabled"';
					}

					$output .= ' /><label for="' . $option['value'] . '">' . $option['label'] . '</label>';
					if ( $option['value'] !== $last_val['value'] ) {
						$output .= '<br />';
					}
				}
			break;

			case 'checkbox_group':
				foreach( $field['options'] as $option ) {
					$output .= '<input type="checkbox"' .
						'value="' . $option['value'] .
						'" name="' . $field['id'] . '[]' .
						'" class="' . $field['id'] . '"' .
						' id="' . $field['id'] . '_' . $option['value'] . '"';

					if( isset( $meta ) && is_array( $meta ) && in_array( $option['value'], $meta ) ||
					    empty( $meta ) && isset( $option['default'] ) && true === $option['default']
					) {
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

			case 'tax_select':
				$output .= '<select name="' . $field['id'] .
					'" id="' . $field['id'] . '"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '><option value="">' .
					_x( 'Select One', 'taxonomy selection for acitivity', 'h3-mgmt' ) .
					'</option>';

				$terms = get_terms( $field['id'], 'get=all' );

				$selected = wp_get_object_terms( $post->ID, $field['id'] );

				foreach( $terms as $term ) {
					if ( ! empty( $selected ) && ! strcmp( $term->slug, $selected[0]->slug ) ) {
						$output .= '<option value="'.$term->slug.'" selected="selected">'.$term->name.'</option>';
					} else {
						$output .= '<option value="'.$term->slug.'">'.$term->name.'</option>';
					}
				}

				$taxonomy = get_taxonomy($field['id']);

				$output .= '</select>';
			break;

			case 'repeatable':
				$output .= '<ul id="'.$field['id'].'-repeatable" class="repeatable-cf no-margins">';
				$i = 0;
				if ( ! empty( $meta ) ) {
					foreach( $meta as $row ) {
						$output .= '<li><span class="sort handle">|||</span>' .
							'<input type="text" name="'.$field['id'].'['.$i.']" id="'.$field['id'].'" value="'.$row.'" size="30" />' .
							'<a class="repeatable-cf-remove button" href="#">-</a></li>';
						$i++;
					}
				} else {
					$output .= '<li><span class="sort handle">|||</span>' .
						'<input type="text" name="'.$field['id'].'['.$i.']" id="'.$field['id'].'" value="" size="30" />' .
						'<a class="repeatable-cf-remove button" href="#">-</a></li>';
				}
				$output .= '</ul>' .
					 '<a class="repeatable-cf-add button" href="#">+</a>';
			break;

			case 'date':
				if( ! empty( $meta ) ) {
					$meta = intval( $meta );
					$value = date( 'd.m.Y', $meta );
					$day_val = date( 'd', $meta );
					$month_val = date( 'm', $meta );
					$year_val = date( 'Y', $meta );
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

			case 'date_time':
				if( ! empty( $meta ) ) {
					$meta = intval( $meta );
					$date_val = date( 'd.m.Y', $meta );
					$day_val = date( 'd', $meta );
					$month_val = date( 'm', $meta );
					$year_val = date( 'Y', $meta );
					$hour_val = date( 'H', $meta );
					$minutes_val = str_pad( round( intval( date( 'i', $meta ) ) / 15 ) * 15, 2, '0', STR_PAD_LEFT ) ;
				} else {
					$date_val = '';
					$day_val = date( 'd' );
					$month_val = date( 'm' );
					$year_val = date( 'Y' );
					$hour_val = '12';
					$minutes_val = '00';
				}
				$output .= '<select class="day js-hide js-hide" id="' . $field['id'] . '_day" name="' . $field['id'] . '_day"';
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
				$output .= '</select><input type="text" class="no-js-hide datepicker date';
				if ( isset( $field['required'] ) ) {
					$output .= ' required';
				}
				$output .= '" name="' . $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $date_val .
					'" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' /> @ <select class="hour" id="' . $field['id'] . '_hour" name="' . $field['id'] . '_hour"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for ( $i = 0; $i < 24; $i++ ) {
					$string = str_pad( $i, 2, '0', STR_PAD_LEFT );
					$output .= '<option value="' . $string . '"';
					if ( $hour_val === $string ) {
						$output .= ' selected="selected"';
					}
					$output .= '>' .
							$string . '&nbsp;' .
						'</option>';
				}
				$output .= '</select> : ';
				$output .= '<select class="minutes" id="' . $field['id'] . '_minutes" name="' . $field['id'] . '_minutes"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= '>';
				for ( $i = 0; $i < 4; $i++ ) {
					$string = str_pad( $i * 15, 2, '0', STR_PAD_LEFT );
					$output .= '<option value="' . $string . '"';
					if ( $minutes_val === $string ) {
						$output .= ' selected="selected"';
					}
					$output .= '>' .
							$string . '&nbsp;' .
						'</option>';
				}
				$output .= '</select>';
			break;

			case 'slider':
				$meta = ! empty( $meta ) ? $meta : $field['min'];
				$output .= '<div id="' . $field['id'] . '-slider"></div>' .
					'<input type="text" name="'. $field['id'] .
					'" id="' . $field['id'] .
					'" value="' . $meta . '" size="5"';
					if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
						$output .= ' disabled="disabled"';
					}
				$output .= ' />';
			break;

			case 'text':
			default:
				$output .= '<input type="text"' .
					'name="' . $field['id'] .
					'" id="' . $field['id'] . '" ';
				if ( isset( $field['required'] ) ) {
					$output .= ' class="required"';
				}
				$output .= 'value="' . $meta .
					'" size="30"';
				if( isset( $field['disabled'] ) && $field['disabled'] === true ) {
					$output .= ' disabled="disabled"';
				}
				$output .= ' />';
			break;
		} // type switch

		if( isset( $field['desc'] ) && ! empty( $field['desc'] ) ) {
			if( ! in_array( $field['type'], array( 'hidden', 'checkbox_group' ) ) ) {
				$output .= '<br />';
			}
			$output .= '<span class="description">' . $field['desc'] . '</span>';
		}

		switch( $field['type'] ) {
			case 'contact':
				$output .= '';
			break;

			default:
				$output .= '</td></tr>';
			break;
		}
	} // foreach field
}// if ! empty

$output .= '</table>';

?>
?>