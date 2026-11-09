# Shortcode-Inventar

Generiert mit `python tools/build-inventory.py`. Statische Registrierung und Standardattribute aus dem Altbestand. Kein Laufzeittest. `keine direkten Defaults` bedeutet: kein direkter shortcode_atts-Aufruf im Handler; nicht zwingend keine mittelbaren Parameter.

| Shortcode | Handler / Quelle | Standardattribute (PHP-Ausdruck) |
|---|---|---|
| `h3-donation-counter` | [donation_counter](../includes/class-h3-mgmt-races.php#L678) | 'race' => 1, 'animation' => 0, 'offset' => 0, |
| `h3-event-statistics` | [race_stats](../includes/class-h3-mgmt-races.php#L583) | 'stat' => 'all', 'race' => 0, 'event' => 1, 'offset' => 0, |
| `h3-handle-betterplace-redirect` | [handle_betterplace_redirect](../includes/class-h3-mgmt-sponsors.php#L1879) | keine direkten Defaults |
| `h3-list-sponsors` | [sponsors_overview](../includes/class-h3-mgmt-sponsors.php#L1810) | 'type' => 'sponsor', 'delimiter' => ', ', 'race' => 'all', |
| `h3-liveticker` | [the_ticker](../includes/class-h3-mgmt-ticker.php#L201) | keine direkten Defaults |
| `h3-mate-xchange` | [xchange_control](../includes/class-h3-mgmt-xchange.php#L30) | keine direkten Defaults |
| `h3-rand-team` | [random_team_info](../includes/class-h3-mgmt-teams.php#L2156) | 'event' => 0, 'race' => 0 |
| `h3-ranking-table` | [ranking_table](../includes/class-h3-mgmt-races.php#L910) | 'top' => 0, 'route' => 'all', 'race' => 1, 'show_title' => 0, 'show_stages' => 1, 'show_nav' => 1, |
| `h3-recent-sponsors` | [recent_sponsors](../includes/class-h3-mgmt-sponsors.php#L1740) | 'number' => 3, 'race' => 'all', |
| `h3-sponsoring-form` | [new_sponsoring_handler](../includes/class-h3-mgmt-sponsors.php#L692) | keine direkten Defaults |
| `h3-stages` | [display_stages](../includes/class-h3-mgmt-races.php#L749) | 'race' => 1, 'class' => '', |
| `h3-stages-isotope` | [isotope_links](../includes/class-h3-mgmt-races.php#L799) | 'race' => 1, 'class' => '', |
| `h3-submit-rankings` | [ranking_submission_control](../includes/class-h3-mgmt-races.php#L880) | keine direkten Defaults |
| `h3-team-dashboard` | [team_dashboard_control](../includes/class-h3-mgmt-teams.php#L1540) | 'event' => 1, 'race' => 0 |
| `h3-team-dashboard-homework` | [team_dashboard_homework_control](../includes/class-h3-mgmt-teams.php#L1496) | 'event' => 1, 'race' => 0 |
| `h3-team-homework` | [team_homework](../includes/class-h3-mgmt-teams.php#L1563) | 'event' => 0, 'race' => 0 |
| `h3-teams` | [display_teams](../includes/class-h3-mgmt-teams.php#L1894) | 'event' => 0, 'race' => 0, 'max' => 10 |
| `h3-ticker-datenbank-change` | [Ticker_Datenbank_Change](../includes/class-h3-mgmt-ticker.php#L221) | keine direkten Defaults |
| `h3-ticker-incoming-interface` | [ticker_incoming_interface](../includes/class-h3-mgmt-ticker.php#L211) | keine direkten Defaults |
| `h3-ticker-map` | [ticker_Page_map](../includes/class-h3-mgmt-ticker.php#L194) | keine direkten Defaults |
| `h3-ticker-message` | [ticker_Page_controll](../includes/class-h3-mgmt-ticker.php#L190) | keine direkten Defaults |
| `h3-ticker-send-message-link` | [ticker_send_message_link](../includes/class-h3-mgmt-ticker.php#L215) | keine direkten Defaults |
| `h3-user-panel-link` | [user_panel_link](../includes/class-h3-mgmt-teams.php#L1857) | 'event' => 0, 'race' => 0 |

h3-ticker-datenbank-change ist ein historisches Wartungswerkzeug mit Schreibwirkung. In v2 geschütztes Werkzeug statt öffentlicher Ausführung; nicht zur Vorschau aufrufen.

Die alten Defaults werden dokumentiert, nicht als fachlich korrekt bestätigt. Insbesondere race/event-Fallbacks und active sind pro Handler zu prüfen.
