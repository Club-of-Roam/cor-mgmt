<?php

// Frozen pre-v2 writer used only by historical characterization tests. Never packaged.
namespace Cor\Mgmt\Tests\Support;

class LegacyRankingReference extends \H3_MGMT_Races
{
        public function persist_ranking() {
			global $wpdb, $h3_mgmt_teams;

				$race_id       = $this->get_route_parent( $_POST['route'] );
				$race_settings = $this->get_race_setting( $race_id );

			$message = _x( 'Ranking successfully saved!', 'Ranking Submission', 'h3-mgmt' );

			list( $team_count, $complete_count, $incomplete_count, $teams ) = $h3_mgmt_teams->get_teams_meta(
				array(
					'orderby'            => 'team_name',
					'exclude_incomplete' => true,
					'parent'             => $_POST['route'],
				)
			);

			$stage               = $_POST['stage'];
			$stage_rank_string   = 'rank_stage_' . $_POST['stage'];
			$extra_string        = 'extra_stage_' . $_POST['stage'];
			$amount_extra_string = 'amount_extra_stage_' . $_POST['stage'];
			$vary_extra_string   = 'vary_extra_stage_' . $_POST['stage'];

			foreach ( $teams as $team ) {
				$rank_value_string              = 'rank-' . $team['id'];
				$one_extra_value_string         = 'one_extra-' . $team['id'];
				$amount_extra_value_string      = 'amount_extra-' . $team['id'];
				$vary_extra_value_string        = 'vary_extra-' . $team['id'];
						$bit_amount_extra_value = 0;

				if ( $stage < 6 ) {
					if ( isset( $_POST[ $one_extra_value_string ] ) ) {
						$one_extra_value = 1;
					} else {
						$one_extra_value = 0;
					}
					if ( isset( $_POST[ $amount_extra_value_string ] ) ) {
						$amount_extra_value                     = $race_settings['extra_point_amount'];
										$bit_amount_extra_value = 1;
					} else {
						$amount_extra_value = 0;
					}
								$vary_extra_value = 0;
				} else {
					if ( isset( $_POST[ $one_extra_value_string ] ) ) {
						$one_extra_value = 1;
					} else {
						$one_extra_value = 0;
					}
					if ( isset( $_POST[ $amount_extra_value_string ] ) ) {
						$amount_extra_value                     = $race_settings['extra_point_amount'];
										$bit_amount_extra_value = 1;
					} else {
						$amount_extra_value                     = 0;
										$bit_amount_extra_value = 0;
					}
					if ( isset( $_POST[ $vary_extra_value_string ] ) ) {
						$vary_extra_value = $_POST[ $vary_extra_value_string ];
					} else {
						$vary_extra_value = 0;
					}
				}

				$points = 0;

				for ( $i = 1; $i <= 6; $i++ ) {
					$srs    = 'rank_stage_' . $i;
					$ses    = 'extra_stage_' . $i;
					$amount = 'amount_extra_stage_' . $i;
					$vary   = 'vary_extra_stage_' . $i;

					if ( $_POST['stage'] != $i ) {
						$temp_pts = $this->points_conversion( $team[ $srs ] );
						if ( 1 == $team[ $ses ] ) {
							$points = $points + 1;
						}
						if ( 1 == $team[ $amount ] ) {
							$points = $points + $race_settings['extra_point_amount'];
						}
						if ( 1 == $team[ $vary ] ) {
							$points = $points + $vary_extra_value_string;
						}
					} else {
						$temp_pts = $this->points_conversion( $_POST[ $rank_value_string ] );
						$points   = $points + $one_extra_value + $vary_extra_value + $amount_extra_value;
					}
					$points = $points + $temp_pts;
				}

				$written = $wpdb->update(
					$wpdb->prefix . 'h3_mgmt_teams',
					array(
						$stage_rank_string   => $_POST[ $rank_value_string ],
						$extra_string        => $one_extra_value,
						$amount_extra_string => $bit_amount_extra_value,
						$vary_extra_string   => $vary_extra_value,
						'total_points'       => $points,
					),
					array( 'id' => $team['id'] ),
					array( '%d', '%d', '%d', '%d', '%d' ),
					array( '%d' )
				);
                if ($written === false) { throw new \RuntimeException('Ranking write failed'); }

			}

			$output = $this->ranking_submission_form( $_POST['route'], $_POST['stage'], $message );

			return $output;
		}

}
