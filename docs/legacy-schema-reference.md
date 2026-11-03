# Historische Installer-DDL als Referenz

Erfasst 2026-11-03 aus der Installationsroutine von Plugin 1.2.10 / Schema 2.8. Diese Definitionen sind ein Quellcodebefund, kein Nachweis des tatsächlich vorhandenen Schemas und kein freigegebener neuer Installer. Sie werden nirgendwo geladen oder ausgeführt. Bekannte Schemaabweichungen, fehlende Ticker-Tabellen, Zeichensätze und Migration sind in Block 3 gesondert zu behandeln. Das Präfix bleibt wie im Original dynamisch.

Die problematische damalige Steuerlogik (Anlage nur ohne Versionsmarker, anschließend unbedingtes Anheben des Markers) und der löschende Uninstaller wurden durch den datenerhaltenden Lebenszyklus ersetzt. Nur die neun ursprünglichen Tabellenanweisungen bleiben hier zur Nachvollziehbarkeit erhalten.

```php
$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_auto_responses (
				id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               action tinytext NOT NULL ,
               language tinytext NOT NULL ,
               switch tinyint UNSIGNED ,
               subject text NOT NULL ,
               message longtext NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_invitations (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               team_id int UNSIGNED NOT NULL ,
               email varchar(255) NOT NULL ,
               code bigint NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_races (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               name varchar(255) NOT NULL ,
               start int UNSIGNED NOT NULL,
               end int UNSIGNED NOT NULL,
               logo_url varchar(255) NOT NULL,
               setting longtext DEFAULT NULL, 
               information_text longtext DEFAULT NULL,
               active int DEFAULT NULL,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_routes (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               race_id int UNSIGNED NOT NULL ,
               name varchar(255) NOT NULL ,
               color_code varchar(255) NOT NULL ,
               logo_url varchar(255) NOT NULL ,
               max_teams smallint NOT NULL ,
               user_id bigint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_stages (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               race_id int UNSIGNED NOT NULL ,
               route_id int UNSIGNED NOT NULL ,
               number tinyint UNSIGNED NOT NULL ,
               destination varchar(255) NOT NULL ,
               country varchar(255) NOT NULL ,
               meeting_point text NOT NULL ,
               country_3166_alpha_2 varchar(255) NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_sponsors (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               type tinytext NOT NULL ,
               method tinytext NOT NULL ,
               donation int UNSIGNED NOT NULL ,
               language tinytext NOT NULL, 
               display_name text NOT NULL ,
               first_name text NOT NULL ,
               last_name text NOT NULL ,
               account_id varchar(255) NOT NULL ,
               bank_id varchar(255) NOT NULL ,
               bank_name text NOT NULL ,
               paid tinyint UNSIGNED NOT NULL ,
               message text NOT NULL ,
               team_id int UNSIGNED NOT NULL ,
               var_show tinyint UNSIGNED NOT NULL ,
               race_id int UNSIGNED NOT NULL ,
               email varchar(255) NOT NULL ,
               owner_pic varchar(255) NOT NULL ,
               owner_link varchar(255) NOT NULL ,
               street text NOT NULL ,
               zip_code text NOT NULL ,
               city text NOT NULL ,
               country text NOT NULL ,
               address_additional text NOT NULL ,
               receipt tinyint UNSIGNED NOT NULL ,
               debit_confirmation tinyint UNSIGNED NOT NULL ,
               donation_client_reference varchar(255) NOT NULL ,
               donation_token varchar(255) NOT NULL ,
               timestamp datetime NOT NULL DEFAULT current_timestamp(), 
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_teams (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               description text NOT NULL ,
               race_id int UNSIGNED NOT NULL ,
               team_name text NOT NULL ,
               team_pic varchar(255) NOT NULL ,
               complete tinyint UNSIGNED NOT NULL ,
               route_id int UNSIGNED NOT NULL ,
               team_phone varchar(255) NOT NULL ,
               rank_stage_1 smallint UNSIGNED NOT NULL ,
               rank_stage_2 smallint UNSIGNED NOT NULL ,
               rank_stage_3 smallint UNSIGNED NOT NULL ,
               rank_stage_4 smallint UNSIGNED NOT NULL ,
               rank_stage_5 smallint UNSIGNED NOT NULL ,
               rank_stage_6 smallint UNSIGNED NOT NULL ,
               extra_stage_1 smallint UNSIGNED NOT NULL ,
               extra_stage_2 smallint UNSIGNED NOT NULL ,
               extra_stage_3 smallint UNSIGNED NOT NULL ,
               extra_stage_4 smallint UNSIGNED NOT NULL ,
               extra_stage_5 smallint UNSIGNED NOT NULL ,
               extra_stage_6 smallint UNSIGNED NOT NULL ,
               total_points int UNSIGNED NOT NULL ,
               meta_1 text NOT NULL,
               meta_2 text NOT NULL,
               meta_3 text NOT NULL,
               meta_4 text NOT NULL,
               meta_5 text NOT NULL,
               donation_goal int UNSIGNED NOT NULL,
               amount_extra_stage_1 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_2 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_3 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_4 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_5 tinyint UNSIGNED NOT NULL ,
               amount_extra_stage_6 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_1 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_2 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_3 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_4 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_5 tinyint UNSIGNED NOT NULL ,
               vary_extra_stage_6 tinyint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_teammates (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               team_id int UNSIGNED NOT NULL ,
               user_id int UNSIGNED NOT NULL ,
               paid tinyint UNSIGNED NOT NULL ,
               waiver tinyint UNSIGNED NOT NULL ,
               language tinyint UNSIGNED NOT NULL ,
               UNIQUE KEY id (id)
       );';

$sql[] = 'CREATE TABLE ' . $wpdb->prefix . 'h3_mgmt_xchange (
               id int UNSIGNED NOT NULL AUTO_INCREMENT ,
               user_id int UNSIGNED NOT NULL ,
               message text NOT NULL ,
               time timestamp on update CURRENT_TIMESTAMP ,
               UNIQUE KEY id (id)
		);';
```
