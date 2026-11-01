# Request- und Aktionsinventar

Generiert mit `python tools/build-inventory.py`. Literale GET/POST/FILES/COOKIE/REQUEST-Felder je Methode und case-Labels aus deren Quelltext. Dynamische Schlüssel, indirekte Aufrufe und JavaScript sind nicht vollständig erfasst. Labels können auch interne Schalter sein. Kein Berechtigungs- oder Erreichbarkeitsnachweis.

| Methode / Quelle | Request-Felder | case-Labels |
|---|---|---|
| [mail_form](../admin/class-h3-mgmt-admin-emails.php#L26) | `GET:email`, `GET:todo`, `POST:subject` |  |
| [mail_send](../admin/class-h3-mgmt-admin-emails.php#L177) | `POST:message`, `POST:receipient`, `POST:sender`, `POST:subject` |  |
| [autoresponses_edit](../admin/class-h3-mgmt-admin-emails.php#L241) | `GET:tab`, `GET:todo`, `POST:submitted` |  |
| [races_control](../admin/class-h3-mgmt-admin-races.php#L24) | `GET:id`, `GET:todo`, `POST:active`, `POST:amount_extra_point`, `POST:betterplace_redirect_link`, `POST:dis_fee`, `POST:dis_mobile_inf`, `POST:dis_shirt_size`, `POST:dis_waiver`, `POST:donation`, `POST:donation_link_link`, `POST:end`, `POST:extra_point_amount`, `POST:kind_of_donation_tool`, `POST:liveticker`, `POST:liveticker_front`, `POST:logo_url`, `POST:name`, `POST:num_teammember`, `POST:num_teammember_min`, `POST:one_extra_point`, `POST:question_1_invisible`, `POST:question_2_invisible`, `POST:question_3_invisible`, `POST:question_4_invisible`, `POST:question_5_invisible`, `POST:race_id_inf`, `POST:show_donation_amount`, `POST:start`, `POST:start_route_id`, `POST:startingpoint`, `POST:status`, `POST:team_overview_link`, `POST:vary_extra_point_field` | `active`, `delete`, `donation-set`, `donation-unset`, `edit`, `liveticker-front-set`, `liveticker-front-unset`, `liveticker-set`, `liveticker-unset`, `new`, `save` |
| [routes_control](../admin/class-h3-mgmt-admin-races.php#L407) | `GET:bulk`, `GET:id`, `GET:todo`, `POST:color_code`, `POST:logo_url`, `POST:max_teams`, `POST:name`, `POST:race_id`, `POST:route_account_email`, `POST:route_account_login_name`, `POST:route_account_pw1`, `POST:route_account_pw2` | `bulk-delete`, `delete`, `edit`, `save` |
| [stages_control](../admin/class-h3-mgmt-admin-races.php#L615) | `GET:bulk`, `GET:id`, `GET:todo`, `POST:country`, `POST:country_3166_alpha_2`, `POST:destination`, `POST:meeting_point`, `POST:number`, `POST:route_id` | `bulk-delete`, `delete`, `edit`, `new`, `save` |
| [fields](../admin/class-h3-mgmt-admin-races.php#L1138) | `POST:submitted` |  |
| [sponsors_control](../admin/class-h3-mgmt-admin-sponsors.php#L22) | `GET:bulk`, `GET:id`, `GET:method`, `GET:todo`, `POST:accound_id`, `POST:address_additional`, `POST:bank_id`, `POST:bank_name`, `POST:city`, `POST:country`, `POST:debit_confirmation`, `POST:display_name`, `POST:donation`, `POST:email`, `POST:first_name`, `POST:language`, `POST:last_name`, `POST:message`, `POST:method`, `POST:owner_link`, `POST:owner_pic`, `POST:paid`, `POST:race_id`, `POST:receipt`, `POST:street`, `POST:team_id`, `POST:type`, `POST:var_show`, `POST:zip_code` | `bulk-delete`, `bulk-hide`, `bulk-paid`, `bulk-show`, `bulk-unpaid`, `delete`, `hide`, `paid`, `save`, `show`, `unpaid` |
| [sponsors_list](../admin/class-h3-mgmt-admin-sponsors.php#L476) | `GET:order`, `GET:orderby` |  |
| [add_values](../admin/class-h3-mgmt-admin-sponsors.php#L886) | `GET:id`, `POST:submitted` |  |
| [sponsors_edit](../admin/class-h3-mgmt-admin-sponsors.php#L906) | `POST:submitted` |  |
| [statistics](../admin/class-h3-mgmt-admin-statistics.php#L16) | `POST:race_id` |  |
| [teams_control](../admin/class-h3-mgmt-admin-teams.php#L23) | `FILES:team_pic`, `GET:id`, `GET:todo`, `POST:description`, `POST:member1`, `POST:member2`, `POST:member3`, `POST:race_id`, `POST:route_id`, `POST:team_name`, `POST:team_phone`, `POST:team_pic-tmp` | `delete`, `edit`, `save` |
| [participants_control](../admin/class-h3-mgmt-admin-teams.php#L299) | `GET:bulk`, `GET:id`, `GET:todo` | `bulk-paid`, `bulk-unpaid`, `bulk-waiver-set`, `bulk-waiver-unset`, `paid`, `unpaid`, `waiver-set`, `waiver-unset` |
| [teams_list](../admin/class-h3-mgmt-admin-teams.php#L515) | `GET:order`, `GET:orderby` |  |
| [teams_edit](../admin/class-h3-mgmt-admin-teams.php#L636) | `POST:submitted` |  |
| [participants_list](../admin/class-h3-mgmt-admin-teams.php#L885) | `GET:order`, `GET:orderby` |  |
| [save_extra_profile_fields](../includes/class-h3-mgmt-profile.php#L142) | `POST:deleteme` | `date` |
| [ranking_submission_control](../includes/class-h3-mgmt-races.php#L858) | `GET:stage`, `POST:submitted` |  |
| [save_ranking](../includes/class-h3-mgmt-races.php#L1059) | `POST:route`, `POST:stage` |  |
| [ranking_table](../includes/class-h3-mgmt-races.php#L1174) | `GET:ranking_route` |  |
| [new_sponsoring_handler](../includes/class-h3-mgmt-sponsors.php#L758) | `COOKIE:client_token`, `FILES:owner_pic`, `GET:type`, `POST:display_name`, `POST:donation_token`, `POST:message`, `POST:new`, `POST:owner_link`, `POST:owner_pic-tmp`, `POST:submitted`, `POST:team_id`, `POST:type` |  |
| [sponsoring_form_handler](../includes/class-h3-mgmt-sponsors.php#L1143) | `GET:donation_client_reference`, `GET:donation_token`, `GET:section`, `POST:submitted` |  |
| [sponsoring_section_output](../includes/class-h3-mgmt-sponsors.php#L1242) | `GET:type`, `POST:donation_client_reference` |  |
| [add_values](../includes/class-h3-mgmt-sponsors.php#L1793) | `GET:id`, `POST:submitted` |  |
| [donation_selector](../includes/class-h3-mgmt-sponsors.php#L1940) | `POST:donation`, `POST:thumbs` |  |
| [make_form](../includes/class-h3-mgmt-sponsors.php#L1992) | `GET:amount` |  |
| [validate_submit](../includes/class-h3-mgmt-sponsors.php#L2158) | `GET:method`, `POST:address`, `POST:debit_confirmation`, `POST:email`, `POST:first_name`, `POST:last_name`, `POST:team_id` |  |
| [save_donation](../includes/class-h3-mgmt-sponsors.php#L2216) | `FILES:owner_pic`, `GET:amount`, `GET:donation_client_reference`, `GET:donation_token`, `POST:display_name`, `POST:donation_client_reference`, `POST:email`, `POST:first_name`, `POST:last_name`, `POST:message`, `POST:owner_link`, `POST:owner_pic-tmp`, `POST:team_id`, `POST:type` |  |
| [handle_betterplace_redirect](../includes/class-h3-mgmt-sponsors.php#L2717) | `GET:amount`, `GET:donation_client_reference`, `GET:donation_token`, `GET:status` |  |
| [__construct](../includes/class-h3-mgmt-sponsors.php#L2812) | `COOKIE:client_token` |  |
| [is_invitation](../includes/class-h3-mgmt-teams.php#L923) | `GET:invitation`, `POST:invitation` |  |
| [handle_invitation](../includes/class-h3-mgmt-teams.php#L976) | `GET:invitation`, `POST:invitation` |  |
| [team_section](../includes/class-h3-mgmt-teams.php#L1184) | `POST:submitted` |  |
| [primary_user_section](../includes/class-h3-mgmt-teams.php#L1333) | `POST:submitted` |  |
| [invitations_section](../includes/class-h3-mgmt-teams.php#L1436) | `POST:form_submitted` |  |
| [route_section](../includes/class-h3-mgmt-teams.php#L1547) | `POST:route_id`, `POST:submitted` |  |
| [team_dashboard_control](../includes/class-h3-mgmt-teams.php#L1630) | `GET:id`, `GET:todo`, `POST:submitted` |  |
| [team_dashboard](../includes/class-h3-mgmt-teams.php#L1718) | `GET:invitation`, `POST:invitation` |  |
| [team_homework](../includes/class-h3-mgmt-teams.php#L1924) | `GET:id`, `GET:todo` |  |
| [display_teams](../includes/class-h3-mgmt-teams.php#L2252) | `GET:id` |  |
| [team_overview](../includes/class-h3-mgmt-teams.php#L2618) | `GET:todo` |  |
| [validate_submit](../includes/class-h3-mgmt-teams.php#L2744) | `POST:address`, `POST:first_name`, `POST:last_name`, `POST:team_name` |  |
| [save_dashboard](../includes/class-h3-mgmt-teams.php#L2775) | `FILES:team_pic`, `GET:invitation`, `POST:description`, `POST:donation_goal`, `POST:first_name`, `POST:invitation`, `POST:invitations`, `POST:meta_1`, `POST:meta_2`, `POST:meta_3`, `POST:meta_4`, `POST:meta_5`, `POST:route_id`, `POST:team_name` | `date` |
| [ticker_Page_controll](../includes/class-h3-mgmt-ticker.php#L182) | `GET:todo`, `POST:180`, `POST:coordinates`, `POST:coordinates_informations`, `POST:image_name`, `POST:left`, `POST:message`, `POST:right`, `POST:ticker_id`, `POST:ticker_pic_msg`, `POST:ticker_pic_url`, `POST:video`, `POST:video_message` |  |
| [ticker_Page_map](../includes/class-h3-mgmt-ticker.php#L659) | `GET:ticker_route` |  |
| [the_ticker](../includes/class-h3-mgmt-ticker.php#L909) | `GET:min`, `GET:ticker_race`, `GET:ticker_route`, `GET:todo` |  |
| [team_ticker](../includes/class-h3-mgmt-ticker.php#L1254) | `GET:id`, `GET:min` |  |
| [ticker_incoming_interface](../includes/class-h3-mgmt-ticker.php#L1433) | `GET:msg`, `POST:message` |  |
| [table_order](../includes/class-h3-mgmt-utilities.php#L187) | `GET:order`, `GET:orderby` |  |
| [xchange_control](../includes/class-h3-mgmt-xchange.php#L110) | `POST:message`, `POST:submitted` |  |
| [__construct](../templates/class-h3-mgmt-admin-table.php#L73) | `GET:order`, `GET:orderby` |  |
| [output](../templates/class-h3-mgmt-admin-table.php#L122) | `GET:filter_name0`, `GET:order`, `GET:orderby`, `GET:page` |  |
| [get_filtered_rows](../templates/class-h3-mgmt-admin-table.php#L907) | `GET:filter_name0` |  |

Nicht ausführen: Ein Aufruf kann schreiben, Mails auslösen oder Medien ändern. Referenztests müssen synthetische Daten verwenden und echte Seiteneffekte verhindern.
