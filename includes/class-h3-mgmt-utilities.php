<?php

/**
 * H3_MGMT_Utilities class.
 *
 * This class contains utility methods used here and there.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Utilities' ) ) :

	class H3_MGMT_Utilities {

		/**
		 * Calculates age,
		 * i.e. the difference between two Unix Timestamps
		 *
		 * @since 1.0
		 * @access public
		 */
		public function date_diff( $d1, $d2 ) {
			if ( $d1 < $d2 ) {
				$temp = $d2;
				$d2   = $d1;
				$d1   = $temp;
			} else {
				$temp = $d1;
			}
			$d1 = date_parse( date( 'Y-m-d H:i:s', $d1 ) );
			$d2 = date_parse( date( 'Y-m-d H:i:s', $d2 ) );
			//seconds
			if ( $d1['second'] >= $d2['second'] ) {
				$diff['second'] = $d1['second'] - $d2['second'];
			} else {
				$d1['minute']--;
				$diff['second'] = 60 - $d2['second'] + $d1['second'];
			}
			//minutes
			if ( $d1['minute'] >= $d2['minute'] ) {
				$diff['minute'] = $d1['minute'] - $d2['minute'];
			} else {
				$d1['hour']--;
				$diff['minute'] = 60 - $d2['minute'] + $d1['minute'];
			}
			//hours
			if ( $d1['hour'] >= $d2['hour'] ) {
				$diff['hour'] = $d1['hour'] - $d2['hour'];
			} else {
				$d1['day']--;
				$diff['hour'] = 24 - $d2['hour'] + $d1['hour'];
			}
			//days
			if ( $d1['day'] >= $d2['day'] ) {
				$diff['day'] = $d1['day'] - $d2['day'];
			} else {
				$d1['month']--;
				$diff['day'] = date( 't', $temp ) - $d2['day'] + $d1['day'];
			}
			//months
			if ( $d1['month'] >= $d2['month'] ) {
				$diff['month'] = $d1['month'] - $d2['month'];
			} else {
				$d1['year']--;
				$diff['month'] = 12 - $d2['month'] + $d1['month'];
			}
			//years
			$diff['year'] = $d1['year'] - $d2['year'];
			return $diff;
		}

		/**
		 * Converts DB strings into translatable strings
		 *
		 * @since 1.1
		 * @access public
		 */
		public function convert_strings( $string ) {
			if ( $string === 'race' ) {
				$string = __( 'Race', 'h3-mgmt' );
			} elseif ( $string === 'route' ) {
				$string = __( 'Route', 'h3-mgmt' );
			} elseif ( $string === 'stage' ) {
				$string = __( 'Stage', 'h3-mgmt' );
			}

			return $string;
		}

		/**
		 * Spits out date formatted according to locale
		 *
		 * @since 1.1
		 * @access public
		 */
		public function h3_strftime( $time ) {
			if ( isset( $_SERVER['REQUEST_URI'] ) && substr( $_SERVER['REQUEST_URI'], 0, 4 ) === '/de/' ) {
				$string = strftime( '%d. %m. %Y', $time );
			} else {
				$string = strftime( '%Y-%m-%d', $time );
			}

			return $string;
		}

		/**
		 * Replaces in-text URLs with working Links
		 *
		 * @since 1.0
		 * @access public
		 */
		public function urls_to_links( $string ) {
			/* make sure there is an http:// on all URLs */
			$string = preg_replace( '/([^\w\/])(www\.[a-z0-9\-]+\.[a-z0-9\-]+)/i', '$1http://$2', $string );
			/* create links */
			$string = preg_replace( '/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i', '<a target="_blank" title="' . __( 'Visit Site', 'h3-mgmt' ) . '" href="$1">$1</A>', $string );

			return $string;
		}

		/**
		 * Appends "http://" to incomplete URLs
		 *
		 * @since 1.0
		 * @access public
		 */
		public function fix_urls( $string ) {
			if ( ! preg_match( '~^(?:f|ht)tps?://~i', $string ) ) {
				$string = 'http://' . $string;
			}
			return $string;
		}

		/**
		 * Returns a phone number without whitespaces, zeroes or a plus sign
		 *
		 * @since 1.2
		 * @access public
		 */
		public function normalize_phone_number( $number, $args = array() ) {
			$default_args = array(
				'nice' => false,
				'ext'  => '49',
			);
			extract( wp_parse_args( $args, $default_args ), EXTR_SKIP );

			$number = preg_replace( '/[^0-9+]/', '', $number );

			if ( ! empty( $number ) ) {

				if ( mb_substr( $number, 0, 2 ) == '00' ) {
					$number = mb_substr( $number, 2 );
				} elseif ( mb_substr( $number, 0, 1 ) == '+' ) {
					$number = mb_substr( $number, 1 );
				} elseif ( mb_substr( $number, 0, 1 ) == '0' ) {
					$number = $ext . mb_substr( $number, 1 );
				}

				if ( $nice === true ) {
					$number = '+' . mb_substr( $number, 0, 2 ) . ' ' . mb_substr( $number, 2, 3 ) . ' ' . mb_substr( $number, 5, 3 ) . ' ' . mb_substr( $number, 8, 3 ) . ' ' . mb_substr( $number, 11, 3 ) . ' ' . mb_substr( $number, 14 );
				}
			} else {
				$number = __( 'not set', 'h3-mgmt' );
			}
			return $number;
		}

		/**
		 * Handles determination of how to order tabular data
		 * (Often recurring code block in Administrative Backend)
		 *
		 * @since 1.1
		 * @access public
		 */
		public function table_order( $default_orderby = 'name' ) {

			if ( isset( $_GET['orderby'] ) ) {
				$orderby = $_GET['orderby'];
			} else {
				$orderby = $default_orderby;
			}
			if ( isset( $_GET['order'] ) ) {
				$order = $_GET['order'];
				if ( 'ASC' == $order ) {
					$toggle_order = 'DESC';
				} else {
					$toggle_order = 'ASC';
				}
			} else {
				$order        = 'ASC';
				$toggle_order = 'DESC';
			}

			return array(
				'order'        => $order,
				'orderby'      => $orderby,
				'toggle_order' => $toggle_order,
			);
		}

		/**
		 * Sorting Methods
		 *
		 * @since 1.1
		 * @access public
		 */
		public function sort_by_key( $arr, $key, $order = 'ASC' ) {
			$this->sort_key = $key;
			if ( $order == 'DESC' ) {
				usort( $arr, array( &$this, 'sbk_cmp_desc' ) );
			} else {
				usort( $arr, array( &$this, 'sbk_cmp_asc' ) );
			}
			return ( $arr );
		}
		private function sbk_cmp_asc( $a, $b ) {
			$encoding = mb_internal_encoding();
			return strcmp( mb_strtolower( $a[ $this->sort_key ], $encoding ), mb_strtolower( $b[ $this->sort_key ], $encoding ) );
		}
		private function sbk_cmp_desc( $b, $a ) {
			$encoding = mb_internal_encoding();
			return strcmp( mb_strtolower( $a[ $this->sort_key ], $encoding ), mb_strtolower( $b[ $this->sort_key ], $encoding ) );
		}

		/**
		 * Custom do_settings_sections (originally WP-core function)
		 *
		 * @since 1.1
		 * @access public
		 */
		public function do_settings_sections( $page ) {
			global $wp_settings_sections, $wp_settings_fields;

			if ( ! isset( $wp_settings_sections ) || ! isset( $wp_settings_sections[ $page ] ) ) {
				return;
			}

			foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
				if ( $section['title'] ) {
					echo '<div class="postbox"><h3 class="no-hover"><span>' . $section['title'] . '</span></h3><div class="inside">';
				}
				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}
				if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					continue;
				}
				echo '<table class="form-table">';
				do_settings_fields( $page, $section['id'] );
				echo '</table></div></div>';
			}
		}

		/**
		 * Converts EOLs to <br /> Tags
		 *
		 * @since 1.1
		 * @access public
		 */
		public function p1_nl2br( $input ) {
			return preg_replace(
				'#(<br */?>\s*){2,}#i', '<br><br>', preg_replace(
					'/[\r|\n]/', '<br>',
					$input
				)
			);
		}
		/**
		 * Resizes Images,
		 * if necessary
		 *
		 * @since 1.0
		 * @access public
		 */
		public function pic_resize( $image, $size ) {
			$parts     = explode( '.', $image );
			$extension = array_pop( $parts );
			$path      = implode( '.', $parts );
			$new_image = $path . '-' . $size . '.' . $extension;
			if ( ! file_exists( str_replace( site_url() . '/', ABSPATH, $new_image ) ) ) {
				image_resize( str_replace( site_url() . '/', ABSPATH, $image ), $size, $size, false, $size, null, 100 );
			}

			if ( file_exists( str_replace( site_url() . '/', ABSPATH, $new_image ) ) ) {
				return $new_image;
			}

			return $image;
		}

		/**
		 * Returns rgb values if fed a hex color
		 *
		 * @since 1.0
		 * @access public
		 */
		public function hex2rgb( $color ) {

			if ( $color[0] == '#' ) {
				$color = substr( $color, 1 );
			}

			if ( strlen( $color ) == 6 ) {
				list($r, $g, $b) = array(
					$color[0] . $color[1],
					$color[2] . $color[3],
					$color[4] . $color[5],
				);
			} elseif ( strlen( $color ) == 3 ) {
				list($r, $g, $b) = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
			} else {
				return false;
			}

			$r = hexdec( $r );
			$g = hexdec( $g );
			$b = hexdec( $b );

			return array( $r, $g, $b );
		}

		/**
		 * Returns the language a user is surfing the site in
		 *
		 * @since 1.1
		 * @access public
		 */
		public function user_language() {
			if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				return ICL_LANGUAGE_CODE;
			}
			return 'en';
		}

	} // class

endif; // class exists


