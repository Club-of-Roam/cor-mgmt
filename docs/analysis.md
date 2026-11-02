# Bestandsanalyse

Die nachfolgenden Ausgangsbefunde beschreiben die Bestandsanalyse vor Block 1. Sicherheitskorrekturen vom 2026-11-02 und aktuelle Nachweise stehen in [block-1-security.md](block-1-security.md); historische Befunde sind keine Aussage, dass die jeweilige Lücke weiterhin besteht.


Prüfdatum: 2026-11-01. Untersuchter Quellstand: Git 9c2d1d6e6f03a29f56a3781397d76fa756b276e2. Maßgeblich ist der untersuchte Arbeitsbaum.

## Umfang und Grenzen

28 PHP-Dateien, ca. 19.800 Zeilen; Plugin 1.2.10, Schema 2.8. Statische Prüfung, PHP-8.4-Lint und lesende SELECT/SHOW-Abfragen des untersuchten Datenbestands. Kein Penetrationstest und keine vollständige PHP-8-Laufzeitprüfung. Ein erfolgreicher Lint ist keine Kompatibilitätsfreigabe. Datenzahlen sind Momentaufnahmen.

## Bestätigte Codebefunde

| ID / Priorität | Befund und Quelle | Maßnahme / Block |
|---|---|---|
| SEC-01 hoch | GET/POST-Einladungscode direkt im SQL: [handle_invitation](../includes/class-h3-mgmt-teams.php#L976) | Vorbereitete Abfragen, sichere Tokens; 1/5 |
| SEC-02 hoch | Nonces erzeugt, keine Verifikation im untersuchten Plugin erkennbar: [Form](../templates/class-h3-mgmt-admin-form.php#L93), [Teamverwaltung](../admin/class-h3-mgmt-admin-teams.php#L23) | Nonce plus aktions-/objektbezogene Rechte; 1 |
| SEC-03 hoch | GET löst Löschung/Austritt aus: [Admin](../admin/class-h3-mgmt-admin-teams.php#L35), [Dashboard](../includes/class-h3-mgmt-teams.php#L1649) | POST mit CSRF-Schutz; 1 |
| SEC-04 hoch | Eingang ohne erkennbare Authentifizierung im Handler: [Ticker](../includes/class-h3-mgmt-ticker.php#L1433) | Authentifizierung, Größen-/Typprüfung, Duplikatschutz; 9, vorher isoliert halten |
| SEC-05 hoch | [app.js](../js/app.js#L35) verwendet uploads/ticker_images/process.php; ausgelagerter Endpunkt verschiebt Upload ohne WP-/Bildprüfung | Geschützter Medienhandler; 1/9 |
| SEC-06 hoch | Unpassende Ausgabebehandlung bei Börsennachrichten; Datenänderung nur anhand ID: [XChange](../includes/class-h3-mgmt-xchange.php#L67) | Validierung, Escaping, Eigentumsbedingung; 1/8 |
| PHP-01 mittel | Implizit nullable Parameter: [date_diff2](../includes/class-h3-mgmt-utilities.php#L73); deprecated Interpolation: [XChange](../includes/class-h3-mgmt-xchange.php#L31) | Explizite Typen und moderne Interpolation; 2 |
| PHP-02 hoch | Feldname als String zu Punkten addiert: [save_ranking](../includes/class-h3-mgmt-races.php#L1059) | Am 2026-11-02 isoliert als TypeError reproduziert; Fachkorrektur und Solltests in 6, Belege in ranking-reference.md |
| PHP-03 mittel | strftime/utf8_encode, ungeprüfte Array-/DB-Ergebnisse | Aktuelle APIs, definierte Nullfälle; 2/Module |
| ARCH-01 hoch | Abfrage schreibt Status/Route und sendet ggf. Mail: [is_complete](../includes/class-h3-mgmt-teams.php#L420) | Abfragen/Änderungen trennen; 5 |
| ARCH-02 mittel | Globals, große Klassen, HTML/SQL/Fachlogik vermischt, wiederholte Abfragen in Schleifen | Services, Repositories, Views, gebündelte Abfragen; 2/Module |
| DB-01 hoch | Tabellenanlage nur ohne installierte Version, Versionsoption trotzdem aktualisiert: [Installation](../h3-management.php#L279) | Geprüfte versionierte Migration; 3 |
| DB-02 hoch | Uninstall löscht neun Plugin-Tabellen: h3_mgmt_uninstall in [Bootstrap](../h3-management.php) | Standardmäßig Daten erhalten; 3 |
| MONEY-01 hoch | Betterplace-Centbetrag /100, anschließend Integer-Speicherung: [Handler](../includes/class-h3-mgmt-sponsors.php#L1026) | Centmodell, Herkunft/Einheit prüfen; verlorene Stellen nicht erfinden; 7 |
| HTTP-01 mittel | get_headers/file_get_contents, historische HTTP-URL und sleep im Spendenablauf | WP HTTP API, Zeitlimits, Fehlerzustände, Adapter; 7 |
| UI-01 mittel | Enfold-Markup, feste Domains/Pfade, ActiveX und seitenübergreifende Assets | Eigene Komponenten, WP-URLs, gezieltes Laden; Module |

Zusatzprüfung 2026-11-02: [Ergebnisreferenz](ranking-reference.md) und ausführbare Tests ergänzen folgende Befunde:

| ID / Priorität | Reproduzierter Befund | Maßnahme / Block |
|---|---|---|
| RANK-01 hoch | Variable Zusatzpunkte anderer Etappen: Wert 5 ausgelassen, Wert 1 erzeugt TypeError | Isolierte Berechnung mit validierten Eingaben und Solltests; 6 |
| RANK-02 hoch | Fehler beim zweiten Team nach bereits ausgeführtem update-Aufruf für erstes Team | Kontrollierte Fehler-/Speicherstrategie und DB-Integration; 6 |
| RANK-03 mittel | Top-Begrenzung nutzt Etappenzähler: Top 2 zeigt ein Team, Top 8 bei zehn Teams zeigt zehn | Normalisierte Grenze und Teamzähler; Archivvertrag beachten; 6 |
| RANK-04 mittel | Initialwert previous=999 ergibt Rang 0 für erstes Team mit 999 Punkten | Rangbildung ohne festen Punkteschwellenwert; Archivvertrag beachten; 6 |

Keine echten Datenbankzugriffe; die Schreibreihenfolge ist am Speicheradapter belegt, nicht als DB-Transaktionsprüfung. Keine Aussage, ob echte historische Datensätze betroffen sind.

Dies sind Codebefunde. Externe Erreichbarkeit und vorgeschaltete Serverregeln wurden nicht untersucht. Keine Angriffsanfragen an die Produktion.

## Zusätzliche Referenzbefunde 2026-11-02

- SEC-06 in echtem WordPress/SQL reproduziert: angemeldeter anderer Subscriber kann eine fremde Börsennachricht über deren ID ersetzen. Das zeigt die Handlerlücke; eine browserseitige Exploitkette wurde nicht ausgeführt.
- PROFILE-02: Autorisiertes save_extra_profile_fields liest id aus Abschnittszeilen ohne id; Warnung im WordPress-Test gezielt reproduziert. Fremder Subscriber wird dagegen durch edit_user abgewiesen.
- MAIL-02: Admin-Selbstmail ruft WP_User::user_email() statt Eigenschaft auf, erhält false; der abgefangene wp_mail-Aufruf hat einen ungültigen Empfänger. Test nutzt echte WP_User-Implementierung, kein echter Versand.
- STAT-02: Reale Legacy-Aggregationsmethode zählt ungezahlte debit-Zeilen in Summen, während exclude_unpaid die Liste filtert; counts liefert keinen race-Key, den race_stats('race-sponsors-count') liest. Isoliert gegen synthetische Adapterdaten belegt.
- Freigabe: Echte SQL-Integration bestätigt Rückstufung/Route 0 trotz Altstatus 3. Das widerspricht dem v2-Phasenvertrag; Korrektur erst in der Umsetzung.
- Alle 23 Shortcodes registriert; Administrator erhält die 18 Caps, Subscriber nicht. Das ist kein Nachweis korrekter Objekt-/CSRF-Prüfungen sämtlicher Aktionen.

Referenzabschluss, übrige Modulabnahmen und Testgrenzen stehen in [block-0-acceptance.md](block-0-acceptance.md).

## Integrationen und ausstehende Nachweise

- cor-theme/profile-form.php bindet Plugin-Template direkt ein. Theme enthält außerdem Anmeldung und Datenschutz; dies ist nicht bereits pluginintern implementiert.
- cor-ticker arbeitet auf sms_ticker; Tabellenwechsel braucht abgestimmten Adapter.
- WPML-Sprachkonstante, Members-Rechte, Avatar-Integration, Enfold-HTML und Embed-Privacy-Filter gegen installierte Versionen prüfen.
- uploads/ticker_images/process.php ist angebunden. Weiterer historischer Endpunkt Profile_Layer_Pics/upload.php existiert; heutige Verwendung noch klären.
- Im ursprünglichen Plugin keine Test-/Build-Konfiguration. Leaflet 1.9.4 bereits vorhanden; Bibliotheken nicht pauschal austauschen.
- Statuskombinationen, historische Fälle und Konkurrenzverhalten müssen durch WordPress-Integrationstests geprüft werden.

## Primärquellen

- [PHP 8 inkompatible Änderungen](https://www.php.net/manual/en/migration80.incompatible.php)
- [PHP 8.4 Veraltungen](https://www.php.net/manual/en/migration84.deprecated.php)
- [WordPress Nonces](https://developer.wordpress.org/apis/security/nonces/)
- [WordPress Plugin-Tabellen](https://developer.wordpress.org/plugins/creating-tables-with-plugins/)
- [WordPress REST-Endpunkte](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
