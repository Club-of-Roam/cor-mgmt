# Request- und Aktionsinventar

Generiert mit `python tools/build-inventory.py`. Literale GET/POST/FILES/COOKIE/REQUEST-Felder je Methode und case-Labels aus deren Quelltext. Dynamische Schlüssel, indirekte Aufrufe und JavaScript sind nicht vollständig erfasst. Labels können auch interne Schalter sein. Kein Berechtigungs- oder Erreichbarkeitsnachweis.

| Methode / Quelle | Request-Felder | case-Labels |
|---|---|---|
| [mail_form](../admin/class-h3-mgmt-admin-emails.php#L27) | `GET:email`, `GET:todo`, `POST:subject` |  |
| [mail_send](../admin/class-h3-mgmt-admin-emails.php#L179) | `POST:message`, `POST:receipient`, `POST:sender`, `POST:subject` |  |
| [autoresponses_edit](../admin/class-h3-mgmt-admin-emails.php#L244) | `GET:tab`, `GET:todo`, `POST:submitted` |  |
| [control](../admin/class-h3-mgmt-admin-races.php#L30) | `GET:id`, `GET:todo`, `POST:submitted` |  |
| [edit](../admin/class-h3-mgmt-admin-races.php#L80) | `POST:cor_event_revision`, `POST:submitted` |  |
| [fields](../admin/class-h3-mgmt-admin-races.php#L106) | `POST:submitted` |  |
| [sponsors_control](../admin/class-h3-mgmt-admin-sponsors.php#L23) | `GET:bulk`, `GET:id`, `GET:method`, `GET:todo`, `POST:accound_id`, `POST:address_additional`, `POST:bank_id`, `POST:bank_name`, `POST:city`, `POST:country`, `POST:debit_confirmation`, `POST:display_name`, `POST:donation`, `POST:email`, `POST:first_name`, `POST:language`, `POST:last_name`, `POST:message`, `POST:method`, `POST:owner_link`, `POST:owner_pic`, `POST:paid`, `POST:race_id`, `POST:receipt`, `POST:street`, `POST:team_id`, `POST:type`, `POST:var_show`, `POST:zip_code` | `bulk-delete`, `bulk-hide`, `bulk-paid`, `bulk-show`, `bulk-unpaid`, `delete`, `hide`, `paid`, `save`, `show`, `unpaid` |
| [sponsors_list](../admin/class-h3-mgmt-admin-sponsors.php#L478) | `GET:order`, `GET:orderby` |  |
| [add_values](../admin/class-h3-mgmt-admin-sponsors.php#L888) | `GET:id`, `POST:submitted` |  |
| [sponsors_edit](../admin/class-h3-mgmt-admin-sponsors.php#L908) | `POST:submitted` |  |
| [statistics](../admin/class-h3-mgmt-admin-statistics.php#L17) | `POST:race_id` |  |
| [teams_control](../admin/class-h3-mgmt-admin-teams.php#L24) | `FILES:team_pic`, `GET:id`, `GET:todo`, `POST:description`, `POST:member1`, `POST:member2`, `POST:member3`, `POST:race_id`, `POST:route_id`, `POST:team_name`, `POST:team_phone`, `POST:team_pic-tmp` | `delete`, `edit`, `save` |
| [participants_control](../admin/class-h3-mgmt-admin-teams.php#L295) | `GET:bulk`, `GET:id`, `GET:todo` | `bulk-paid`, `bulk-unpaid`, `bulk-waiver-set`, `bulk-waiver-unset`, `paid`, `unpaid`, `waiver-set`, `waiver-unset` |
| [teams_list](../admin/class-h3-mgmt-admin-teams.php#L512) | `GET:order`, `GET:orderby` |  |
| [teams_edit](../admin/class-h3-mgmt-admin-teams.php#L633) | `POST:submitted` |  |
| [participants_list](../admin/class-h3-mgmt-admin-teams.php#L882) | `GET:order`, `GET:orderby` |  |
| [page](../includes/class-h3-mgmt-maintenance.php#L42) | `POST:apply`, `POST:race` |  |
| [ajax_upload](../includes/class-h3-mgmt-media.php#L61) | `POST:race` |  |
| [picture](../includes/class-h3-mgmt-media.php#L127) | `POST:left`, `POST:right`, `POST:ticker_pic_msg` |  |
| [save_extra_profile_fields](../includes/class-h3-mgmt-profile.php#L144) | `POST:_wpnonce`, `POST:deleteme` |  |
| [ranking_submission_control](../includes/class-h3-mgmt-races.php#L886) | `GET:stage`, `POST:submitted` |  |
| [persist_ranking](../includes/class-h3-mgmt-races.php#L1103) | `POST:route`, `POST:stage` |  |
| [ranking_table](../includes/class-h3-mgmt-races.php#L1219) | `GET:ranking_route` |  |
| [post](../includes/class-h3-mgmt-security.php#L26) | `POST:cor_mgmt_nonce` |  |
| [donation_nonce](../includes/class-h3-mgmt-security.php#L36) | `COOKIE:client_token` |  |
| [donation_request](../includes/class-h3-mgmt-security.php#L41) | `COOKIE:client_token`, `GET:donation_client_reference` |  |
| [admin_request](../includes/class-h3-mgmt-security.php#L111) | `GET:bulk`, `GET:id`, `GET:todo`, `POST:bulk`, `POST:id`, `POST:race_id`, `POST:route_id`, `POST:submitted`, `POST:team_id`, `POST:todo` |  |
| [ranking_request](../includes/class-h3-mgmt-security.php#L229) | `POST:route`, `POST:stage` |  |
| [new_sponsoring_handler](../includes/class-h3-mgmt-sponsors.php#L728) | `COOKIE:client_token`, `FILES:owner_pic`, `GET:type`, `POST:display_name`, `POST:donation_token`, `POST:message`, `POST:new`, `POST:owner_link`, `POST:owner_pic-tmp`, `POST:submitted`, `POST:team_id`, `POST:type` |  |
| [sponsoring_form_handler](../includes/class-h3-mgmt-sponsors.php#L1115) | `GET:section`, `POST:submitted` |  |
| [sponsoring_section_output](../includes/class-h3-mgmt-sponsors.php#L1191) | `GET:type`, `POST:donation_client_reference` |  |
| [add_values](../includes/class-h3-mgmt-sponsors.php#L1745) | `GET:id`, `POST:submitted` |  |
| [donation_selector](../includes/class-h3-mgmt-sponsors.php#L1892) | `POST:donation`, `POST:thumbs` |  |
| [make_form](../includes/class-h3-mgmt-sponsors.php#L1944) | `GET:amount`, `GET:donation_client_reference` |  |
| [validate_submit](../includes/class-h3-mgmt-sponsors.php#L2111) | `GET:method`, `POST:address`, `POST:debit_confirmation`, `POST:email`, `POST:first_name`, `POST:last_name`, `POST:team_id` |  |
| [save_donation](../includes/class-h3-mgmt-sponsors.php#L2170) | `COOKIE:client_token`, `FILES:owner_pic`, `GET:amount`, `GET:donation_client_reference`, `GET:donation_token`, `POST:display_name`, `POST:donation_client_reference`, `POST:email`, `POST:first_name`, `POST:last_name`, `POST:message`, `POST:owner_link`, `POST:owner_pic-tmp`, `POST:team_id`, `POST:type` |  |
| [handle_betterplace_redirect](../includes/class-h3-mgmt-sponsors.php#L2641) | `GET:amount`, `GET:donation_client_reference`, `GET:donation_token`, `GET:status` |  |
| [__construct](../includes/class-h3-mgmt-sponsors.php#L2740) | `COOKIE:client_token` |  |
| [is_invitation](../includes/class-h3-mgmt-teams.php#L940) | `GET:invitation`, `POST:invitation` |  |
| [handle_invitation](../includes/class-h3-mgmt-teams.php#L993) | `GET:invitation`, `POST:invitation` |  |
| [team_section](../includes/class-h3-mgmt-teams.php#L1184) | `POST:submitted` |  |
| [primary_user_section](../includes/class-h3-mgmt-teams.php#L1335) | `POST:submitted` |  |
| [invitations_section](../includes/class-h3-mgmt-teams.php#L1438) | `POST:form_submitted` |  |
| [route_section](../includes/class-h3-mgmt-teams.php#L1550) | `POST:route_id`, `POST:submitted` |  |
| [team_dashboard_control](../includes/class-h3-mgmt-teams.php#L1634) | `GET:todo`, `POST:submitted`, `POST:todo` |  |
| [team_dashboard](../includes/class-h3-mgmt-teams.php#L1723) | `GET:invitation`, `POST:invitation` |  |
| [team_homework](../includes/class-h3-mgmt-teams.php#L1927) | `GET:id`, `GET:todo`, `POST:id`, `POST:todo` |  |
| [display_teams](../includes/class-h3-mgmt-teams.php#L2260) | `GET:id` |  |
| [team_overview](../includes/class-h3-mgmt-teams.php#L2629) | `GET:todo` |  |
| [validate_submit](../includes/class-h3-mgmt-teams.php#L2756) | `POST:address`, `POST:first_name`, `POST:last_name`, `POST:team_name` |  |
| [save_dashboard](../includes/class-h3-mgmt-teams.php#L2787) | `POST:birthday-day`, `POST:birthday-month`, `POST:birthday-year`, `POST:description`, `POST:donation_goal`, `POST:invitation`, `POST:invitations`, `POST:meta_4`, `POST:route_id`, `POST:team_name` |  |
| [persist_dashboard](../includes/class-h3-mgmt-teams.php#L2841) | `FILES:team_pic`, `GET:invitation`, `POST:description`, `POST:donation_goal`, `POST:first_name`, `POST:invitation`, `POST:invitations`, `POST:meta_1`, `POST:meta_2`, `POST:meta_3`, `POST:meta_4`, `POST:meta_5`, `POST:route_id`, `POST:team_name` | `date` |
| [ticker_Page_controll](../includes/class-h3-mgmt-ticker.php#L172) | `GET:todo`, `POST:coordinates`, `POST:coordinates_informations`, `POST:message`, `POST:submit_form`, `POST:ticker_id`, `POST:ticker_pic_msg`, `POST:video`, `POST:video_message` |  |
| [ticker_Page_map](../includes/class-h3-mgmt-ticker.php#L535) | `GET:ticker_route` |  |
| [the_ticker](../includes/class-h3-mgmt-ticker.php#L787) | `GET:min`, `GET:ticker_race`, `GET:ticker_route`, `GET:todo` |  |
| [team_ticker](../includes/class-h3-mgmt-ticker.php#L1132) | `GET:id`, `GET:min` |  |
| [ticker_incoming_interface](../includes/class-h3-mgmt-ticker.php#L1311) | `POST:message` |  |
| [table_order](../includes/class-h3-mgmt-utilities.php#L187) | `GET:order`, `GET:orderby` |  |
| [insertUpdateDeleteMessage](../includes/class-h3-mgmt-xchange.php#L68) | `POST:address` |  |
| [xchange_control](../includes/class-h3-mgmt-xchange.php#L115) | `POST:message`, `POST:submitted` |  |
| [mutate](../src/WordPress/EventAdmin.php#L23) | `GET:bulk` |  |
| [render](../src/WordPress/EventList.php#L30) | `GET:country_filter`, `GET:event_page`, `GET:number_filter`, `GET:order`, `GET:orderby`, `GET:race_filter`, `GET:route_filter` |  |
| [request](../src/WordPress/SchemaTools.php#L27) | `POST:backup`, `POST:fingerprint`, `POST:nonce`, `POST:operation`, `POST:quiesced`, `POST:run_id` |  |
| [output](../templates/class-h3-mgmt-admin-form.php#L69) | `GET:page` |  |
| [__construct](../templates/class-h3-mgmt-admin-table.php#L73) | `GET:order`, `GET:orderby` |  |
| [output](../templates/class-h3-mgmt-admin-table.php#L122) | `GET:filter_name0`, `GET:order`, `GET:orderby`, `GET:page` |  |
| [get_filtered_rows](../templates/class-h3-mgmt-admin-table.php#L910) | `GET:filter_name0` |  |

Nicht ausführen: Ein Aufruf kann schreiben, Mails auslösen oder Medien ändern. Referenztests müssen synthetische Daten verwenden und echte Seiteneffekte verhindern.
