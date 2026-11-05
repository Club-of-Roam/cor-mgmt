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
| [sponsors_list](../admin/class-h3-mgmt-admin-sponsors.php#L485) | `GET:order`, `GET:orderby` |  |
| [add_values](../admin/class-h3-mgmt-admin-sponsors.php#L895) | `GET:id`, `POST:submitted` |  |
| [sponsors_edit](../admin/class-h3-mgmt-admin-sponsors.php#L915) | `POST:submitted` |  |
| [statistics](../admin/class-h3-mgmt-admin-statistics.php#L17) | `POST:race_id` |  |
| [page](../includes/class-h3-mgmt-maintenance.php#L42) | `POST:apply`, `POST:race` |  |
| [ajax_upload](../includes/class-h3-mgmt-media.php#L61) | `POST:race` |  |
| [picture](../includes/class-h3-mgmt-media.php#L127) | `POST:left`, `POST:right`, `POST:ticker_pic_msg` |  |
| [save_extra_profile_fields](../includes/class-h3-mgmt-profile.php#L144) | `POST:_wpnonce`, `POST:deleteme` |  |
| [ranking_table](../includes/class-h3-mgmt-races.php#L917) | `GET:ranking_route` |  |
| [post](../includes/class-h3-mgmt-security.php#L26) | `POST:cor_mgmt_nonce` |  |
| [donation_nonce](../includes/class-h3-mgmt-security.php#L36) | `COOKIE:client_token` |  |
| [donation_request](../includes/class-h3-mgmt-security.php#L41) | `COOKIE:client_token`, `GET:donation_client_reference` |  |
| [admin_request](../includes/class-h3-mgmt-security.php#L111) | `GET:bulk`, `GET:id`, `GET:todo`, `POST:bulk`, `POST:id`, `POST:race_id`, `POST:route_id`, `POST:submitted`, `POST:team_id`, `POST:todo` |  |
| [ranking_request](../includes/class-h3-mgmt-security.php#L231) | `POST:route`, `POST:stage` |  |
| [sponsoring_section_output](../includes/class-h3-mgmt-sponsors.php#L713) | `GET:type`, `POST:donation_client_reference` |  |
| [add_values](../includes/class-h3-mgmt-sponsors.php#L1267) | `GET:id`, `POST:submitted` |  |
| [donation_selector](../includes/class-h3-mgmt-sponsors.php#L1414) | `POST:donation`, `POST:thumbs` |  |
| [make_form](../includes/class-h3-mgmt-sponsors.php#L1466) | `GET:amount`, `GET:donation_client_reference` |  |
| [validate_submit](../includes/class-h3-mgmt-sponsors.php#L1633) | `GET:method`, `POST:address`, `POST:debit_confirmation`, `POST:email`, `POST:first_name`, `POST:last_name`, `POST:team_id` |  |
| [__construct](../includes/class-h3-mgmt-sponsors.php#L1888) | `COOKIE:client_token` |  |
| [is_invitation](../includes/class-h3-mgmt-teams.php#L871) | `GET:invitation`, `POST:invitation` |  |
| [handle_invitation](../includes/class-h3-mgmt-teams.php#L901) | `GET:invitation`, `POST:invitation` |  |
| [team_section](../includes/class-h3-mgmt-teams.php#L1089) | `POST:submitted` |  |
| [primary_user_section](../includes/class-h3-mgmt-teams.php#L1240) | `POST:submitted` |  |
| [invitations_section](../includes/class-h3-mgmt-teams.php#L1343) | `POST:form_submitted` |  |
| [route_section](../includes/class-h3-mgmt-teams.php#L1455) | `POST:route_id`, `POST:submitted` |  |
| [team_homework](../includes/class-h3-mgmt-teams.php#L1562) | `GET:id`, `GET:todo`, `POST:id`, `POST:todo` |  |
| [display_teams](../includes/class-h3-mgmt-teams.php#L1895) | `GET:id` |  |
| [team_overview](../includes/class-h3-mgmt-teams.php#L2251) | `GET:todo` |  |
| [validate_submit](../includes/class-h3-mgmt-teams.php#L2378) | `POST:address`, `POST:first_name`, `POST:last_name`, `POST:team_name` |  |
| [save_dashboard](../includes/class-h3-mgmt-teams.php#L2409) | `POST:submitted` |  |
| [ticker_Page_controll](../includes/class-h3-mgmt-ticker.php#L172) | `GET:todo`, `POST:coordinates`, `POST:coordinates_informations`, `POST:message`, `POST:submit_form`, `POST:ticker_id`, `POST:ticker_pic_msg`, `POST:video`, `POST:video_message` |  |
| [ticker_Page_map](../includes/class-h3-mgmt-ticker.php#L535) | `GET:ticker_route` |  |
| [the_ticker](../includes/class-h3-mgmt-ticker.php#L787) | `GET:min`, `GET:ticker_race`, `GET:ticker_route`, `GET:todo` |  |
| [team_ticker](../includes/class-h3-mgmt-ticker.php#L1132) | `GET:id`, `GET:min` |  |
| [ticker_incoming_interface](../includes/class-h3-mgmt-ticker.php#L1311) | `POST:message` |  |
| [table_order](../includes/class-h3-mgmt-utilities.php#L189) | `GET:order`, `GET:orderby` |  |
| [insertUpdateDeleteMessage](../includes/class-h3-mgmt-xchange.php#L68) | `POST:address` |  |
| [xchange_control](../includes/class-h3-mgmt-xchange.php#L115) | `POST:message`, `POST:submitted` |  |
| [render](../src/WordPress/DonationFrontend.php#L24) | `COOKIE:client_token`, `FILES:owner_pic`, `GET:id`, `GET:type`, `POST:new`, `POST:submitted` |  |
| [mutate](../src/WordPress/EventAdmin.php#L30) | `GET:bulk` |  |
| [render](../src/WordPress/EventList.php#L30) | `GET:country_filter`, `GET:event_page`, `GET:number_filter`, `GET:order`, `GET:orderby`, `GET:race_filter`, `GET:route_filter` |  |
| [prepare](../src/WordPress/ProfileAvatar.php#L60) | `FILES:cor_profile_avatar` |  |
| [control](../src/WordPress/ResultFrontend.php#L13) | `GET:cor_result_route`, `GET:stage`, `POST:submitted` |  |
| [table](../src/WordPress/ResultFrontend.php#L112) | `GET:ranking_route` |  |
| [request](../src/WordPress/SchemaTools.php#L27) | `POST:backup`, `POST:fingerprint`, `POST:nonce`, `POST:operation`, `POST:quiesced`, `POST:run_id` |  |
| [control](../src/WordPress/TeamAdmin.php#L13) | `GET:bulk`, `GET:id`, `GET:todo` |  |
| [save](../src/WordPress/TeamAdmin.php#L51) | `FILES:team_pic`, `POST:route_id` |  |
| [form](../src/WordPress/TeamAdmin.php#L97) | `GET:race_id`, `POST:race_id` |  |
| [listing](../src/WordPress/TeamAdmin.php#L147) | `GET:complete_filter`, `GET:direction`, `GET:race_filter`, `GET:route_filter`, `GET:sort`, `GET:team_page` |  |
| [profileInput](../src/WordPress/TeamBoundary.php#L55) | `POST:birthday`, `POST:birthday-day`, `POST:birthday-month`, `POST:birthday-year` |  |
| [emails](../src/WordPress/TeamBoundary.php#L109) | `POST:invitations` |  |
| [save](../src/WordPress/TeamBoundary.php#L134) | `FILES:team_pic`, `POST:route_id` |  |
| [dashboard](../src/WordPress/TeamFrontend.php#L40) | `GET:invitation`, `POST:submitted`, `POST:todo` |  |
| [render](../src/WordPress/TeamFrontend.php#L80) | `GET:invitation`, `POST:invitation` |  |
| [output](../templates/class-h3-mgmt-admin-form.php#L69) | `GET:page` |  |
| [__construct](../templates/class-h3-mgmt-admin-table.php#L73) | `GET:order`, `GET:orderby` |  |
| [output](../templates/class-h3-mgmt-admin-table.php#L122) | `GET:filter_name0`, `GET:order`, `GET:orderby`, `GET:page` |  |
| [get_filtered_rows](../templates/class-h3-mgmt-admin-table.php#L910) | `GET:filter_name0` |  |

Nicht ausführen: Ein Aufruf kann schreiben, Mails auslösen oder Medien ändern. Referenztests müssen synthetische Daten verwenden und echte Seiteneffekte verhindern.
