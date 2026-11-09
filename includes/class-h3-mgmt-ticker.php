<?php
require_once __DIR__ . "/class-h3-mgmt-security.php";

/**
 * H3_MGMT_Ticker class.
 *
 * Compatibility facade for the integrated web ticker; SMS reception is retired.
 *
 * @package HitchHikingHub Management
 * @since 1.0
 */
if ( !class_exists( 'H3_MGMT_Ticker' ) ) :

	class H3_MGMT_Ticker {
		/*		 * ************* UTILITY METHODS ************** */

		/**
		 * Returns a phone number without whitespaces, zeroes or a plus sign
		 *
		 * @since 1.0
		 * @access public
		 */
		public function normalize_phone_number( $number, $nice = false ) {

			if ( $nice === true ) {

				$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

				if ( mb_substr( $number, 0, 1 ) == '+' ) {
					$number = $number;
				} elseif ( mb_substr( $number, 0, 2 ) == '00' ) {
					$number = '+' . mb_substr( $number, 2 );
				} elseif ( mb_substr( $number, 0, 1 ) == '0' ) {
					$number = '+49' . mb_substr( $number, 1 );
				}

				$number = mb_substr( $number, 0, 3 ) . ' ' . mb_substr( $number, 3, 3 ) . ' ' . mb_substr( $number, 6, 3 ) . ' ' . mb_substr( $number, 9, 3 ) . ' ' . mb_substr( $number, 12 );
			} else {

				$number = preg_replace( "/\r|\n/", "", str_replace( ' ', '', str_replace( '-', '', str_replace( '/', '', $number ) ) ) );

				if ( mb_substr( $number, 0, 1 ) == '+' ) {
					$number = mb_substr( $number, 1 );
				} elseif ( mb_substr( $number, 0, 2 ) == '00' ) {
					$number = mb_substr( $number, 2 );
				} elseif ( mb_substr( $number, 0, 1 ) == '0' ) {
					$number = '49' . mb_substr( $number, 1 );
				}
			}

			return $number;
		}

		/**
		 * Returns an array of user_id  for ticker by team_id
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_user_by_team( $team_id ) {
            $team_id = H3_MGMT_Security::id($team_id);
			global $wpdb;

			$ids_querry	 = $wpdb->get_results(
			"SELECT user_id FROM " .
			$wpdb->prefix . "h3_mgmt_teammates " .
			"WHERE team_id = " . $team_id, ARRAY_A
			);
			
			$ids		 = array();
			foreach ( $ids_querry as $id ) {
				$ids[] = 'U_' . $id['user_id'];
			}
			$ids[] = 'T_' . $team_id;

			return $ids;
		}

		/**
		 * Returns an array of Ticker Messages by a array of ticker user and 
		 * team ids.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_messages_by_ids( $ids, $race_id, $message_id = 0 ) {
            global $wpdb;
            $ids = is_array($ids) ? array_values(array_filter($ids, static fn($id) => is_scalar($id) && preg_match('/^(?:[UT]_[0-9]+|[+0-9]+)$/D', (string) $id))) : [];
            if (!$ids || count($ids) > 1000) { return []; }
            $db = new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb);
            if (\Cor\Mgmt\WordPress\TickerBoundary::manager()->native()) {
                $sql = $db->prepare('SELECT * FROM %i WHERE race_id=%d', $db->table('cor_mgmt_ticker_posts'), H3_MGMT_Security::id($race_id));
                $placeholders = implode(',', array_fill(0, count($ids), '%s'));
                $sql .= " AND visibility='visible' AND kind<>'comment' AND author_ref IN (" . $wpdb->prepare($placeholders, ...$ids) . ')';
                if ($message_id) { $sql .= " AND kind='location'"; }
                return array_map(static function(array $row): array {
                    $types = ['text'=>1,'image'=>3,'location'=>4,'video'=>5,'unknown'=>0];
                    $old = json_decode($row['source_payload'] ?? '', true);
                    $type = is_array($old) && is_string($old['type'] ?? null) ? base64_decode($old['type'], true) : (string) ($types[$row['kind']] ?? 0);
                    return $row + ['send_from'=>$row['author_ref'],'msg'=>esc_html($row['body']),'type'=>$type,'img_url'=>$row['media_ref'],'img'=>$row['parent_id'],'timestamp'=>$row['created_at']];
                }, $db->rows($sql . ' ORDER BY id DESC'));
            }
            if (!$db->exists($db->table('sms_ticker'))) { return []; }
            $columns = array_column($db->rows($db->prepare('SHOW COLUMNS FROM %i', $db->table('sms_ticker'))), 'Field');
            if (!in_array('race_id', $columns, true)) { return []; }
            $sender = in_array('send_from', $columns, true) ? 'send_from' : 'from';
            $sql = $db->prepare('SELECT * FROM %i WHERE race_id=%d', $db->table('sms_ticker'), H3_MGMT_Security::id($race_id));
            $sql .= $db->prepare(' AND type<100 AND %i IN (', $sender) . $wpdb->prepare(implode(',', array_fill(0,count($ids),'%s')), ...$ids) . ')';
            if ($message_id) { $sql .= ' AND type=4'; }
            return $db->rows($sql . ' ORDER BY id DESC');
        }

		/**
		 * Returns an array of team phone numbers
		 *
		 * @since 1.0
		 * @access public
		 */
		public function get_phones( $team_id = 0, $route_id = 'all', $gimme = 'phones', $race_id = 0 ) {
            $team_id = H3_MGMT_Security::id($team_id);
            $route_id = $route_id === 'all' ? 'all' : H3_MGMT_Security::id($route_id);
            $race_id = H3_MGMT_Security::id($race_id);
			global $wpdb, $h3_mgmt_teams;

			if ( $route_id === 'all' && $team_id === 0 ) {
				$where = '';
			} elseif ( $team_id != 0 ) {
				$where = " WHERE id = " . $team_id;
			} else {
				$where = " WHERE route_id = " . $route_id;
			}

			$teams_query = $wpdb->get_results(
			"SELECT * FROM " .
			$wpdb->prefix . "h3_mgmt_teams" . $where, ARRAY_A
			);

			$teams_query_buf = array();

			if ( $race_id != 0 ) {
				foreach ( $teams_query as $team ) {
					if ( $race_id == $team[ 'race_id' ] ) {
						$teams_query_buf[] = $team;
					}
				}
				$teams_query = $teams_query_buf;
			}

			$phones	 = array();
			$teams	 = array();

			foreach ( $teams_query as $team ) {
				$phonesbuf	 = array();
				$team_phones = $team[ 'team_phone' ];
				$pos		 = stripos( $team_phones, ' ', 0 );
				while ( $pos !== false ) {
					$phonesbuf[] = substr( $team_phones, 0, $pos );
					$team_phones = strpbrk( $team_phones, ' ' );
					$team_phones = ltrim( $team_phones );
					$pos		 = stripos( $team_phones, ' ', 0 );
				}
				$phonesbuf[] = $team_phones;

				foreach ( $phonesbuf as $phone ) {
					$phones[]											 = $this->normalize_phone_number( $phone );
					$teams[ $this->normalize_phone_number( $phone ) ]	 = $team[ 'id' ];
				}
				$user_ids = $h3_mgmt_teams->get_teammates( $team[ 'id' ] );
				foreach ( $user_ids as $user_id ) {
					$phones[]																			 = $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) );
					$teams[ $this->normalize_phone_number( get_user_meta( $user_id, 'mobile', true ) ) ] = $team[ 'id' ];
				}
			}

			if ( $gimme === 'teams' ) {
				return $teams;
			} else {
				return $phones;
			}
		}

		/*		 * ************* TICKER OUTPUT ************** */

		/**
		 * Shortcode Funktion to save Ticker messages via HP
		 *
		 * @since 1.0
		 * @access public
		 */
        public function ticker_Page_controll($atts = '') {
            $atts = shortcode_atts(['race'=>0,'comment'=>false,'comment_get'=>false], is_array($atts) ? $atts : []);
            return \Cor\Mgmt\WordPress\TickerBoundary::form($atts);
        }
        public function ticker_Page_map($atts = '') {
            $atts = shortcode_atts(['route'=>'all','race'=>0,'show_nav'=>1,'show_warning'=>1,'coord_center_lat'=>54.0237934,'coord_center_lng'=>9.3754401], is_array($atts) ? $atts : []);
            return \Cor\Mgmt\WordPress\TickerBoundary::feed($atts, true);
        }
        public function team_ticker_Page_map($team_id) {
            return $this->team_ticker($team_id, 100, true);
        }
        public function the_ticker($atts = '') {
            $atts = shortcode_atts(['max'=>10,'route'=>'all','race'=>0,'team'=>0,'show_warning'=>1,'show_nav'=>0], is_array($atts) ? $atts : []);
            return \Cor\Mgmt\WordPress\TickerBoundary::feed($atts);
        }
        public function team_ticker($team_id, $max = 10, $map = false) {
            global $wpdb;
            $team_id = H3_MGMT_Security::id($team_id);
            $race = (int) $wpdb->get_var($wpdb->prepare('SELECT race_id FROM %i WHERE id=%d', $wpdb->prefix . 'h3_mgmt_teams', $team_id));
            return \Cor\Mgmt\WordPress\TickerBoundary::feed(['race'=>$race,'team'=>$team_id,'max'=>$max], (bool) $map);
        }
        public function ticker_incoming_interface() {
            status_header(410);
            echo '410';
        }
        public function ticker_send_message_link($atts = '') {
            $atts = shortcode_atts(['url'=>home_url('/send-ticker-message/'),'race'=>1], is_array($atts) ? $atts : []);
            $race = \Cor\Mgmt\WordPress\TickerBoundary::race($atts);
            if (!\Cor\Mgmt\WordPress\TickerBoundary::allowed(get_current_user_id(), $race, 'write')) { return ''; }
            return '<p><a href="' . esc_url($atts['url']) . '">' . esc_html__('Send a ticker message', 'h3-mgmt') . '</a></p>';
        }
        public function Ticker_Datenbank_Change($atts = '') { return H3_MGMT_Security::error(); }

		public function __construct() {
			add_shortcode( 'h3-liveticker', array( $this, 'the_ticker' ) );
			add_shortcode( 'h3-ticker-incoming-interface', array( $this, 'ticker_incoming_interface' ) );
			add_shortcode( 'h3-ticker-message', array( $this, 'ticker_Page_controll' ) );
			add_shortcode( 'h3-ticker-map', array( $this, 'ticker_Page_map' ) );
			add_shortcode( 'h3-ticker-send-message-link', array( $this, 'ticker_send_message_link' ) );
			add_shortcode( 'h3-ticker-datenbank-change', array( $this, 'Ticker_Datenbank_Change' ) );
		}

	}

	// class

endif; // class exists
