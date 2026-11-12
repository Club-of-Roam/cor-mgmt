<?php
require_once dirname(__DIR__) . "/includes/class-h3-mgmt-security.php";

/**
 * H3_MGMT_Admin_Races class.
 *
 * This class contains properties and methods for
 * the creation of new races.
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Admin_Races' ) ) :

	class H3_MGMT_Admin_Races {

		/* CONTROLLERS */

		/**
		 * Races administration menu
		 *
		 * @since 1.1
		 * @access public
		 */
		public function races_control() { $this->control('race'); }
		public function routes_control() { $this->control('route'); }
		public function stages_control() { $this->control('stage'); }

        /** Stable legacy menu callbacks, one application writer per resource. */
        private function control(string $type): void {
            if (!H3_MGMT_Security::admin_request($type . 's')) { echo H3_MGMT_Security::error(); return; }
            $todo = (string) ($_GET['todo'] ?? '');
            $id = H3_MGMT_Security::id($_GET['id'] ?? 0);
            if (in_array($todo, ['new', 'edit'], true)) {
                try { $this->edit($type, $id ?: null); }
                catch (\Throwable $error) { echo '<p class="notice notice-error">' . esc_html(\Cor\Mgmt\WordPress\EventAdmin::message('missing_record')) . '</p>'; }
                return;
            }
            $messages = [];
            if ($todo !== '') {
                try {
                    $result = \Cor\Mgmt\WordPress\EventAdmin::mutate($type, $id, $todo);
                    foreach ($result['warnings'] ?? [] as $warning) {
                        echo '<div class="notice notice-warning"><p>' . esc_html(\Cor\Mgmt\WordPress\EventAdmin::message($warning)) . '</p></div>';
                    }
                    if (!empty($result['confirmation_required'])) {
                        $this->confirm_event = true;
                        $this->edit($type, $id ?: null);
                        return;
                    }
                    $messages[] = ['type' => 'message', 'message' => __('Changes saved.', 'h3-mgmt')];
                    if ($type === 'race' && !$id && current_user_can('manage_options')) {
                        echo '<div class="notice notice-info"><p><a href="' . esc_url(\Cor\Mgmt\WordPress\SitePages::settingsUrl((int) $result['id'])) . '">' . esc_html__('Next: create or select the event pages in the event settings.', 'h3-mgmt') . '</a></p></div>';
                    }
                    unset($_POST['submitted']);
                } catch (\Throwable $error) {
                    $reason = \Cor\Mgmt\WordPress\EventAdmin::errorCode($error);
                    echo '<div class="notice notice-error"><p>' . esc_html(\Cor\Mgmt\WordPress\EventAdmin::message($reason)) . ' <code>' . esc_html($reason) . '</code></p></div>';
                    if ($todo === 'save' && $reason !== 'stale_record') {
                        try { $this->edit($type, $id ?: null); } catch (\Throwable $ignored) { /* The record may have disappeared. */ }
                        return;
                    }
                }
            }
            \Cor\Mgmt\WordPress\EventList::render($type, $messages);
        }
        private bool $confirm_event = false;

		/**
		 * List all races
		 *
		 * @since 1.1
		 * @access private
		 */
		/* INTERFACES TO EDIT A SINGLE DATA SET */

		/**
		 * Edit a race/route/stage
		 *
		 * @since 1.1
		 * @access public
		 */
		public function edit( $type = 'race', $id = null ) {
			global $h3_mgmt_races, $h3_mgmt_utilities;

			$url         = 'admin.php?page=h3-mgmt-' . $type . 's';
			$form_action = $url . '&amp;todo=save&amp;id=' . $id;

			if ( ! is_numeric( $id ) ) {
				$fields = $this->fields( $type );
				$title  = sprintf( __( 'Add New %s', 'h3-mgmt' ), $h3_mgmt_utilities->convert_strings( $type ) );
			} else {
				$fields = $this->fields( $type, $id );
				$name   = $h3_mgmt_races->get_name( $id, $type );
				$title  = sprintf( __( 'Edit &quot;%s&quot;', 'h3-mgmt' ), $name );
			}

            $revision = isset($_POST['submitted']) ? (string) ($_POST['cor_event_revision'] ?? '') : \Cor\Mgmt\WordPress\EventAdmin::editor()->revision($type, (int) $id);
            \Cor\Mgmt\WordPress\EventForm::render($type, (int) $id, $fields, $title, $revision, $this->confirm_event);

		}

		/**
		 * Returns an array of fields for the editing form
		 *
		 * @since 1.0
		 * @access private
		 */
		private function fields( $type = 'race', $id = null ) {
			global $wpdb, $h3_mgmt_races, $h3_mgmt_utilities, $h3_mgmt_teams;

			$options_race_first_empty = array(
				array(
					'value' => 0,
					'label' => '---',
				),
			);
			$options_race_first_empty = array_merge( $options_race_first_empty, $h3_mgmt_races->options_array( array( 'data' => 'race' ) ) );

			$disabled = $id !== null && H3_MGMT_Security::phase((int) $id) !== 0;

					$startiing_points_array = [['options' => array_merge([['value' => 0, 'label' => '---']], $h3_mgmt_races->options_array(['data' => 'route', 'race' => $type === 'race' ? $id : 0]))]];

			$race_fields = array(
				array(
					'title'  => __( 'The Event', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text',
							'label' => __( 'Name of event', 'h3-mgmt' ),
							'id'    => 'name',
							'desc'  => __( 'The name or title of the event', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Logo URL', 'h3-mgmt' ),
							'id'    => 'logo_url',
							'desc'  => __( 'If you want the event to have a special logo and you uploaded one to the site, set the URL here. (Optional. So far not implemented in the frontend.)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Enter the URL to the team overview page on your site.', 'h3-mgmt' ),
							'id'    => 'team_overview_link',
							'desc'  => __( 'Enter the URL (without http:// or https:// and your Domain. For example just "/team_overview/".) to the team overview page where the user get the overviwe about all teams and could see the team prfiles. Please make sure that the page with the ID of the event has the same ID as this event! The shortcode is "h3-teams race=ID_of_the_event"', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => __( 'Settings Race functions', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'radio',
							'label'   => __( 'Is the Race / Event the active one?', 'h3-mgmt' ),
							'id'      => 'active',
							'desc'    => __( 'Is this Race / Event the present one?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'select',
							'label'   => __( 'Status', 'h3-mgmt' ),
							'id'      => 'status',
							'desc'    => __( 'The Status of the event (bevor registration, registration closed...)', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'bevor registration',
								),
								array(
									'value' => 1,
									'label' => 'registration open',
								),
								array(
									'value' => 2,
									'label' => 'registration closed and bevor race start',
								),
								array(
									'value' => 3,
									'label' => 'race started',
								),
							),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'More then 1 starting point?', 'h3-mgmt' ),
							'id'      => 'startingpoint',
							'desc'    => __( 'Could the Teams choose the starting point?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'select',
							'label'   => __( 'Starting point if chosen just one starting point', 'h3-mgmt' ),
							'id'      => 'start_route_id',
							'desc'    => __( 'If chosen just one starting point the teams will be automatically added to this starting point.', 'h3-mgmt' ),
							'options' => $startiing_points_array[0]['options'],
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Liveticker Send-Function enabled?', 'h3-mgmt' ),
							'id'      => 'liveticker',
							'desc'    => __( 'Do you want to allow to send Liveticker messages?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Show Liveticker Content', 'h3-mgmt' ),
							'id'      => 'liveticker_front',
							'desc'    => __( 'Do you want to show the Liveticker content at the Frontend?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Donation Tool enabled?', 'h3-mgmt' ),
							'id'      => 'donation',
							'desc'    => __( 'Do you want to allow to donate for teams?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Enable 1 extra Point for each Stage', 'h3-mgmt' ),
							'id'      => 'one_extra_point',
							'desc'    => __( 'Do you want to enable the function of 1 extra point for each stage and Team in the ranking?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Enable a chosen amount of extra Point for each stage', 'h3-mgmt' ),
							'id'      => 'amount_extra_point',
							'desc'    => __( 'Do you want to enable the function of a chosen amount of extra points for each stage and Team in the ranking?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Fixed extra points per stage', 'h3-mgmt' ),
							'id'    => 'extra_point_amount',
							'desc'  => __( 'Enter the fixed number of extra points that can be awarded for a stage.', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Enable a extra field to give extra points only for the last stage', 'h3-mgmt' ),
							'id'      => 'vary_extra_point_field',
							'desc'    => __( 'Do you want to enable the function that they get a extra field where they could chose the amount of points they would give for the last stage in the ranking?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
					),
				),
				array(
					'title'  => __( 'Participation requirements (editable before registration)', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'     => 'text',
							'label'    => __( 'How many participants per team minimum?', 'h3-mgmt' ),
							'id'       => 'num_teammember_min',
							'disabled' => $disabled,
							'desc'     => __( 'How many team member a team must have?', 'h3-mgmt' ),
							'value'    => 2,
						),
						array(
							'type'     => 'text',
							'label'    => __( 'How many participants per team maximum?', 'h3-mgmt' ),
							'id'       => 'num_teammember',
							'disabled' => $disabled,
							'desc'     => __( 'How many team member a team could have?', 'h3-mgmt' ),
							'value'    => 3,
						),
						array(
							'type'     => 'radio',
							'label'    => __( 'Disable liability waiver?', 'h3-mgmt' ),
							'id'       => 'dis_waiver',
							'disabled' => $disabled,
							'desc'     => __( 'Do you want to disable the liability waiver? It\'s not visible in the team dashboard then and not required to get complete.', 'h3-mgmt' ),
							'options'  => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'     => 'radio',
							'label'    => __( 'Disable to pay a fee for a Hitchpackage or something else?', 'h3-mgmt' ),
							'id'       => 'dis_fee',
							'disabled' => $disabled,
							'desc'     => __( 'Do you want to disable that the participants have to pay a fee? It\'s not visible in the team dashboard then and not required to get complete.', 'h3-mgmt' ),
							'options'  => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'     => 'radio',
							'label'    => __( 'Disable choose shirt size?', 'h3-mgmt' ),
							'id'       => 'dis_shirt_size',
							'disabled' => $disabled,
							'desc'     => __( 'Do you want to disable the shirt function? It\'s not visible in the team dashboard then.', 'h3-mgmt' ),
							'options'  => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'     => 'radio',
							'label'    => __( 'Disable choose mobile information and extra field', 'h3-mgmt' ),
							'id'       => 'dis_mobile_inf',
							'disabled' => $disabled,
							'desc'     => __( 'Do you want to disable the mobile information and extra field? It\'s not visible in the team dashboard then.', 'h3-mgmt' ),
							'options'  => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
					),
				),
				array(
					'title'  => __( 'Settings Donation Tool', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'label'   => __( 'Kind of donation tool', 'h3-mgmt' ),
							'id'      => 'kind_of_donation_tool',
							'desc'    => __( 'How should the donation tool work? Select the functions of the donation tool', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'Standard with TeamOwner and TeamSponsor',
								),
								array(
									'value' => 1,
									'label' => 'Only TeamSponsor',
								),
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Enter the redirect link to your Betterplace project on the Betterlace site.', 'h3-mgmt' ),
							'id'    => 'betterplace_redirect_link',
							'desc'  => __(
								' You will get the Third Party Link from Betterplace. If you need more information contact us. <br> Example: https://www.testtest.com/de/**your project or event**/**your id**/client_donations/new?client_id=**your client id** <br>'
									. '     How you get the Third Party Link you will find <a title="Info third party link URL" href="https://github.com/betterplace/betterplace_apidocs/blob/master/donation_form/third_party_app_donation_form.md#how-to-get-it">here</a> <br><br>'
									. '     Please let them know under the point "Description" that you use the tramprennen API <br>'
									. '     For "Callback url production" you have to  set up ONE page in Wordpress for all other Events. In this page you just enter this shortcode "[h3-handle-betterplace-redirect]". You could get the  right URL if you call the page and add "?status=Test" at the end of the URL <br>'
								. '     If you have any problems send a mail to web@tramprennen.org', 'h3-mgmt'
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Enter the redirect link to the donation tool page on your site.', 'h3-mgmt' ),
							'id'    => 'donation_link_link',
							'desc'  => __( 'Enter the Link (without http:// or https:// and your Domain. For example just "/donate/".) to your page where the user also will donate. Thats the page where you add the shortcode to run the donation code. Please make sure that the page with the ID of the event has the same ID as this event! The shortcode is "h3-sponsoring-form race=ID_of_the_event"', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Show donation amount in team homework?', 'h3-mgmt' ),
							'id'      => 'show_donation_amount',
							'desc'    => __( 'Do you want to show the amount of collected donations in the teams homework list?', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
					),
				),
				array(
					'title'  => __( 'Timeframe', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'date',
							'label' => __( 'Start', 'h3-mgmt' ),
							'id'    => 'start',
							'desc'  => __( 'The start date of the event', 'h3-mgmt' ),
						),
						array(
							'type'  => 'date',
							'label' => __( 'End', 'h3-mgmt' ),
							'id'    => 'end',
							'desc'  => __( 'The end date of the event (usually 2 days after arrival @ the destination)', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => __( 'Load information text from older race', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'label'   => __( 'Event / Race', 'h3-mgmt' ),
							'id'      => 'race_id_inf',
							'desc'    => __( 'From wich Race / Event you wanna copy? If you press save and select a race all data in the "Information Texts" section will be overwritten!!!', 'h3-mgmt' ),
							'options' => $options_race_first_empty,
						),
					),
				),
				array(  //Highest ID 31
					'title'  => __( 'Questions in Team profiles', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 1', 'h3-mgmt' ),
							'id'    => '1en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '1de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Description of Team Profile Question 1', 'h3-mgmt' ),
							'id'    => '6en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '6de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
							'id'      => 'question_1_invisible',
							'desc'    => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 2', 'h3-mgmt' ),
							'id'    => '2en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '2de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Description of Team Profile Question 2', 'h3-mgmt' ),
							'id'    => '7en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '7de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
							'id'      => 'question_2_invisible',
							'desc'    => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 3', 'h3-mgmt' ),
							'id'    => '3en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '3de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Description of Team Profile Question 3', 'h3-mgmt' ),
							'id'    => '8en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '8de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
							'id'      => 'question_3_invisible',
							'desc'    => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 4', 'h3-mgmt' ),
							'id'    => '4en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '4de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 4 Answer 1', 'h3-mgmt' ),
							'id'    => '12en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '12de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 4 Answer 2', 'h3-mgmt' ),
							'id'    => '13en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '13de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 4 Answer 3', 'h3-mgmt' ),
							'id'    => '14en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '14de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 4 Answer 4', 'h3-mgmt' ),
							'id'    => '15en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '15de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Description of Team Profile Question 4', 'h3-mgmt' ),
							'id'    => '9en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '9de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
							'id'      => 'question_4_invisible',
							'desc'    => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Team Profile Question 5', 'h3-mgmt' ),
							'id'    => '5en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '5de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Description of Team Profile Question 5', 'h3-mgmt' ),
							'id'    => '10en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '10de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'    => 'radio',
							'label'   => __( 'Do you want to make this question invisible?', 'h3-mgmt' ),
							'id'      => 'question_5_invisible',
							'desc'    => __( 'If yes the question is invisible in team dashboard and profile.', 'h3-mgmt' ),
							'options' => array(
								array(
									'value' => 0,
									'label' => 'No',
								),
								array(
									'value' => 1,
									'label' => 'Yes',
								),
							),
						),
					),
				),
				array(
					'title'  => __( 'Information Texts', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under "Select starting point" in Team Dashboard', 'h3-mgmt' ),
							'id'    => '11en',
							'desc'  => __( 'In english (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '11de',
							'desc'  => __( 'In german (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'First Text under "INVITE TEAMMATE(S)" in Team Dashboard', 'h3-mgmt' ),
							'id'    => '30en',
							'desc'  => __( 'In english (Will displayed if the participant hasn\'t invited anyone yet.)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '30de',
							'desc'  => __( 'In german (Will displayed if the participant hasn\'t invited anyone yet.)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Second Text under "INVITE TEAMMATE(S)" in Team Dashboard', 'h3-mgmt' ),
							'id'    => '31en',
							'desc'  => __( 'In english (Will displayed if the participant has invited someone yet.)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '31de',
							'desc'  => __( 'In german (Will displayed if the participant has invited someone yet.)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under HOMEWORK ASSIGNMENTS', 'h3-mgmt' ),
							'id'    => '32en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '32de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: Invite Teampartner.', 'h3-mgmt' ),
							'id'    => '33en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '33de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: liability waiver from participant.', 'h3-mgmt' ),
							'id'    => '34en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '34de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: liability waiver from team mate', 'h3-mgmt' ),
							'id'    => '35en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '35de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: Fee from participant for Example: Hitchpackage.', 'h3-mgmt' ),
							'id'    => '28en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '28de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: Fee from team mate for Example: Hitchpackage.', 'h3-mgmt' ),
							'id'    => '29en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '29de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: T-Shirt from participant.', 'h3-mgmt' ),
							'id'    => '36en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '36de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: T-Shirt from team mate', 'h3-mgmt' ),
							'id'    => '37en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '37de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: Extra from participant.', 'h3-mgmt' ),
							'id'    => '38en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '38de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Points for BEFORE PUBLISHING: Extra from team mate', 'h3-mgmt' ),
							'id'    => '39en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '39de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text for Liability Waiver Form information', 'h3-mgmt' ),
							'id'    => '16en',
							'desc'  => __( 'In english (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '16de',
							'desc'  => __( 'In german (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text for money transfer information', 'h3-mgmt' ),
							'id'    => '17en',
							'desc'  => __( 'In english (with HTML code BUT no < / p > at the end!!!)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '17de',
							'desc'  => __( 'In german (with HTML code BUT no < / p > at the end!!!)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under the points of "After Team is complete"', 'h3-mgmt' ),
							'id'    => '18en',
							'desc'  => __( 'In english (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '18de',
							'desc'  => __( 'In german (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under step one in "Sponsoring Steps"', 'h3-mgmt' ),
							'id'    => '19en',
							'desc'  => __( 'In english (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '19de',
							'desc'  => __( 'In german (with HTML code)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under Teamsponsor at the right', 'h3-mgmt' ),
							'id'    => '20en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '20de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( 'Text under Teamowner at the right', 'h3-mgmt' ),
							'id'    => '21en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'textarea_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '21de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for registration isn\'t open yet', 'h3-mgmt' ),
							'id'    => '22en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '22de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for liveticker isn\'t enabled yet', 'h3-mgmt' ),
							'id'    => '23en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '23de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for donation tool isn\'t activated yet', 'h3-mgmt' ),
							'id'    => '24en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '24de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for team overview when registration isn\'t open yet', 'h3-mgmt' ),
							'id'    => '25en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '25de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for the Ranking table when race isn\'t started yet', 'h3-mgmt' ),
							'id'    => '26en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '26de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( 'Information text for Liveticker when show liveticker content is disabled', 'h3-mgmt' ),
							'id'    => '27en',
							'desc'  => __( 'In english', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text_long',
							'label' => __( '', 'h3-mgmt' ),
							'id'    => '27de',
							'desc'  => __( 'In german', 'h3-mgmt' ),
						),
					),
				),
			);

					$route_account = $type === 'route' && $id ? $h3_mgmt_races->get_route_account($id) : 0;

			if ( $route_account == null || $route_account == 0 ) {
				$disabled2 = false;
			} else {
				$disabled2 = true;
			}

			$route_fields = array(
				array(
					'title'  => __( 'The Route', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text',
							'label' => __( 'Name of route', 'h3-mgmt' ),
							'id'    => 'name',
							'desc'  => __( 'The name or title of the route', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Maximum Teams', 'h3-mgmt' ),
							'id'    => 'max_teams',
							'desc'  => __( 'The maximum number (integer!) of teams that can register for this route', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => __( 'Context', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'label'   => __( 'Event / Race', 'h3-mgmt' ),
							'id'      => 'race_id',
							'desc'    => __( 'The event this route belongs to', 'h3-mgmt' ),
							'options' => $h3_mgmt_races->options_array( array( 'data' => 'race' ) ),
						),
					),
				),
				array(
					'title'  => __( 'Design', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text',
							'label' => __( 'Color', 'h3-mgmt' ),
							'id'    => 'color_code',
							'desc'  => __( 'The 6-digit hex color code (no # !) of the route', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Logo URL', 'h3-mgmt' ),
							'id'    => 'logo_url',
							'desc'  => __( 'The relative path (without the domain!) of the route\'s logo', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => __( 'Route Account (After creating a account you can\'t change it anymore)', 'h3-mgmt' ),
					'fields' => array(
                        array('type' => 'text', 'label' => __('Existing route account user ID (0 for none)', 'h3-mgmt'), 'id' => 'user_id', 'value' => '0', 'desc' => __('Assign an existing WordPress user. Creating an account below takes precedence.', 'h3-mgmt')),
						array(
							'type'     => 'text',
							'label'    => __( 'Login Name', 'h3-mgmt' ),
							'id'       => 'route_account_login_name',
							'disabled' => $disabled2,
							'desc'     => __( 'The login name of the route account', 'h3-mgmt' ),
						),
						array(
							'type'     => 'text',
							'label'    => __( 'E-Mail', 'h3-mgmt' ),
							'id'       => 'route_account_email',
							'disabled' => $disabled2,
							'desc'     => __( 'The E-Mail Address of the route account', 'h3-mgmt' ),
						),
						array(
							'type'     => 'password',
							'label'    => __( 'Password', 'h3-mgmt' ),
							'id'       => 'route_account_pw1',
							'disabled' => $disabled2,
							'desc'     => __( 'The login password of the route account', 'h3-mgmt' ),
						),
						array(
							'type'     => 'password',
							'label'    => __( 'Repeat password', 'h3-mgmt' ),
							'id'       => 'route_account_pw2',
							'disabled' => $disabled2,
							'desc'     => __( 'Repeat the login password of the route account', 'h3-mgmt' ),
						),
					),
				),
			);

			$stage_fields = array(
				array(
					'title'  => __( 'The Stage', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'  => 'text',
							'label' => __( 'Destination', 'h3-mgmt' ),
							'id'    => 'destination',
							'desc'  => __( 'The destination of the stage', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Number', 'h3-mgmt' ),
							'id'    => 'number',
							'desc'  => __( 'The running number of the stage', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Country', 'h3-mgmt' ),
							'id'    => 'country',
							'desc'  => __( 'The destination city&apos;s country', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Country Code', 'h3-mgmt' ),
							'id'    => 'country_3166_alpha_2',
							'desc'  => __( 'The destination city&apos;s countries 2-letter country code (as defined by <a target="_blank" title="Read the standard" href="http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2">ISO 3166-1-alpha-2</a>)', 'h3-mgmt' ),
						),
						array(
							'type'  => 'text',
							'label' => __( 'Meeting Point', 'h3-mgmt' ),
							'id'    => 'meeting_point',
							'desc'  => __( 'Where the teams will meet in the destination city', 'h3-mgmt' ),
						),
					),
				),
				array(
					'title'  => __( 'Context', 'h3-mgmt' ),
					'fields' => array(
						array(
							'type'    => 'select',
							'label'   => __( 'Route', 'h3-mgmt' ),
							'id'      => 'route_id',
							'desc'    => __( 'The route this stage belongs to', 'h3-mgmt' ),
							'options' => $h3_mgmt_races->options_array( array( 'data' => 'route' ) ),
						),
					),
				),
			);

			if ( 'stage' === $type ) {
				$fields = $stage_fields;
			} elseif ( 'route' === $type ) {
				$fields = $route_fields;
			} else {
				$fields = $race_fields;
			}


            $data = [];
            if (is_numeric($id)) {
                $repository = new \Cor\Mgmt\Infrastructure\WordPress\LegacyEventStore(new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($wpdb));
                $row = $repository->find($type, (int) $id);
                if (!$row) { throw new \Cor\Mgmt\Application\Events\EventException('missing_record'); }
                $data = \Cor\Mgmt\Application\Events\EventEditor::flatten($row);
                $information = json_decode((string) ($row['information_text'] ?? '{}'), true);
                if (is_array($information)) { $data += $information; }
                $data['country_3166_alpha_2'] = $data['country_3166_alpha-2'] ?? $data['country_3166_alpha_2'] ?? '';
            }
            $race = $type === 'race' ? (int) ($id ?? 0) : (int) ($data['race_id'] ?? 0);
            $account = $type === 'route' && !empty($data['user_id']) ? get_userdata((int) $data['user_id']) : false;
            foreach ($fields as &$section) {
                foreach ($section['fields'] as &$field) {
                    $key = $field['id'];
                    $allowed = \Cor\Mgmt\WordPress\EventAdmin::fieldAllowed($type, $key, $race);
                    $field['disabled'] = !$allowed || !H3_MGMT_Security::can_object($type . 's', 'edit', (int) $id) || (str_starts_with($key, 'route_account_') && (bool) $account);
                    if (($key === 'user_id' || str_starts_with($key, 'route_account_')) && !current_user_can('h3_mgmt_edit_races')) { $field['disabled'] = true; }
                    if (str_starts_with($key, 'route_account_')) {
                        $field['value'] = match ($key) {
                            'route_account_login_name' => $account ? $account->user_login : '',
                            'route_account_email' => $account ? $account->user_email : '',
                            default => '',
                        };
                    } elseif (isset($_POST['submitted']) && array_key_exists($key, $_POST) && $allowed && is_scalar($_POST[$key])) {
                        $field['value'] = wp_unslash((string) $_POST[$key]);
                    } elseif (array_key_exists($key, $data)) {
                        $field['value'] = $field['type'] === 'date' ? ((int) $data[$key] ? wp_date('Y-m-d', (int) $data[$key]) : '') : (string) $data[$key];
                    }
                    if (!$allowed) { $field['desc'] = ($field['desc'] ?? '') . ' ' . __('Locked in the current race phase.', 'h3-mgmt'); }
                }
                unset($field);
            }
            unset($section);
            return $fields;
        }
    }
endif;
