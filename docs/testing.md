# Teststrategie

## Ausführbare Referenzprüfungen

PHPUnit-Referenztests prüfen die bestehende Platz-/Punktetabelle, Telefonnummern-Normalisierung sowie Teamfreigabe, Pflichtfelder, Einladungsgrenzen und Routenauswahl. Klassen werden ohne Konstruktor verwendet; kein WordPress-Bootstrap, keine Datenbank, keine Netzwerk-/Mailzugriffe. Nur synthetische Eingaben. Das belegt keine Sicherheit oder Vollkompatibilität des übrigen Plugins.

TeamRulesTest führt die echten Legacy-Methoden mit partiellen Doubles für deren Abhängigkeiten aus. Die Datenbankattrappe akzeptiert ausschließlich die erwarteten Abfragen/Schreibziele und führt Änderungen im Speicher nach. Mailaufrufe werden lediglich aufgezeichnet. Globale Werte und POST werden nach jedem Test wiederhergestellt. tests/Support/LegacyEnvironment.php ist ausschließlich Testcode und verweigert einen laufenden WordPress-Kontext. Diese Tests sind keine WordPress-Integrationstests. Zuordnung und Grenzen: [Fachregeln](business-rules.md).

- composer install: gesperrte Entwicklungsabhängigkeiten installieren (CLI PHP >= 8.2).
- composer test: isolierte Referenztests.
- composer test:integration: separate echte WordPress-/wpdb-Referenzsuite; ausdrücklich bereitgestellte synthetische Testressourcen erforderlich, keine vorhandene Website verwenden.
- composer check: Composer-Metadaten, PHP-Syntax und Dokumentations-/Inventarkonsistenz.
- python tools/build-inventory.py: Inventar regenerieren, danach Diff prüfen.

GitHub Actions enthält PHP 8.2/8.3/8.4/8.5, jeweils isolierte Tests und WordPress-Integration mit WordPress 7.1.4 sowie MariaDB 11.4.13. Das Corearchiv ist per Version und SHA-256 festgelegt. Alle vier PHP-Versionen wurden mit beiden Suiten ausgeführt; das ist kein bereits ausgeführter Remote-GitHub-Lauf. Details in verification.md und [Referenzabschluss](block-0-acceptance.md).

Lint erfasst den Altbestand, blendet Veraltungsdiagnosen bewusst aus: Nur Syntaxfehler führen zum Abbruch. Veraltungen sind in analysis.md dokumentiert. PHPUnit läuft für die abgedeckten Pfade mit strenger Warnungsbehandlung.

## Ergebnis- und Darstellungsreferenz

RankingSubmissionTest und RankingDisplayTest ergänzen Summierung, feste/variable Boni, fehlende Etappengrenze, Fehler nach früheren Schreibaufrufen, Phasenanzeige, Ranggleichheit und Top-Grenzen. Known-defect-Fälle erwarten bewusst das fehlerhafte Ist-Verhalten bzw. TypeError; sie sind keine Sollfreigabe für Version 2. Renderer-Doubles simulieren nur benötigte WordPress-Funktionen ohne echten Shortcodeaufruf. POST/GET und verwendete globale Werte werden wiederhergestellt. Tests verwenden ausschließlich synthetische Daten und Speicherobjekte, keine SQL-Ausführung, WordPress-Hooks oder Netz-/Mailzugriffe. Befunde und Einzelbeispiele: [Ergebnisreferenz](ranking-reference.md). Archiv-/Migrationstests stehen weiterhin aus.

## WordPress-Integration

phpunit.integration.xml.dist lädt echte Core-APIs in einem getrennten Prozess ohne die Funktions-Doubles der isolierten Suite. Verwendete Software: WordPress 7.1.4, MariaDB 11.4.13, PHPUnit gemäß composer.lock und PHP aus der Matrix. Der Workflow enthält die ausführbare Bereitstellung und Prüfkommandos; keine Testdaten aus bestehenden Websites.

Der Bootstrap verlangt COR_TEST_WP_ROOT mit einer ausdrücklichen Fixturemarkierung, fehlende wp-config.php, leere Plugin-/Themeordner und einen eigenen eingeschränkten Datenbankbenutzer. Datenbanknamen müssen cor_mgmt_test_* entsprechen, der Host muss ein Loopback-Endpunkt mit eigenem Port sein; fehlende/unsichere Vorgaben brechen ab. Das Werkzeug tools/create-wordpress-fixture.php kopiert ausschließlich Coredateien in ein neues Ziel und übernimmt weder Sitekonfiguration noch Inhalte. Zugangswerte nur über die Prozessumgebung, keine Zugangsdaten im Repository.

Die Suite installiert frisches WordPress und lädt den echten Plugin-Bootstrap; danach ruft sie dessen init einmal auf. Minimale synthetische Tabellen sind Referenzfixtures, keine Ziel-DDL oder bestandene Legacy-Neuinstallation. Pro Test Transaktion/Rollback und Cachebereinigung. Rollen, Benutzer, Metadaten, tatsächliche SQL-Abfragen/Schreibzugriffe, Shortcodes und wp_mail-Hook sind real. Mail wird vor Transport abgefangen; WP-HTTP blockiert. Direkte Provider-Dateizugriffe werden nicht ausgeführt.

Bekannte Ladeveraltungen werden eng nach Dateiname und Meldungsmuster aus utilities (implizit nullable) und XChange (Interpolation) protokolliert. Andere Warnungen bleiben Fehler. Nach WordPress-Bootstrap wird E_ALL wiederhergestellt. Profilabschnittsfehler werden im betreffenden Known-defect-Test gezielt als ErrorException erwartet. Solche Referenzen sind keine Bestätigung korrekter oder sicherer Zielimplementierung.

Primärquellen: [WordPress wp_install](https://developer.wordpress.org/reference/functions/wp_install/), [Core-Teststrategie](https://make.wordpress.org/core/handbook/testing/automated-testing/), [WordPress 7.1.4](https://wordpress.org/documentation/wordpress-version/version-7-1-4/). Die schlanke Integration verwendet PHPUnit direkt; sie behauptet nicht, auf WP_UnitTestCase zu basieren.

## Weitere Testebenen

| Ebene | Werkzeug | Inhalt |
|---|---|---|
| Fachlogik | PHPUnit | Punkte, Zusatzpunkte, Status, Einladungen, Geld |
| WordPress | WordPress-PHPUnit-Umgebung | Hooks, Shortcodes, Objektberechtigungen, REST, Datenzugriff |
| Migration | PHPUnit + MariaDB | Neu/Alt, Zeichensätze, Orphans, Abbruch, Wiederaufnahme, Vergleich |
| Dienste | Injizierte HTTP-/Mail-/Zeit-/Zufallsadapter | Fehler, Timeout, Wiederholungen, ungültige Bestätigungen |
| Browser | Playwright | Teilnahme, Admin, Ergebnisse, simulierte Spenden, Medien/Ticker |
| Statische Analyse | PHPStan | Neue Fachmodule streng, Altbestand schrittweise mit belegter Baseline |
| Standards | PHPCS + WPCS + PHPCompatibility | Neue/geänderte Module zuerst, Altlasten sichtbar halten |
| Frontend | ESLint und Barrierefreiheitsprüfung | JS, Labels, Fokus, mobile Formulare; manuelle Tastaturtests ergänzen |

WordPress-Referenzintegration ist eingerichtet; umfassende WP-Modul-/REST-/Aktivierungs-/Migrationstests bleiben offen. PHPStan, PHPCS, PHPCompatibility, Playwright und ESLint folgen im technischen Fundament. Keine leeren Tests als Abdeckung ausweisen. Werkzeugversionen bei Einführung auf Kompatibilität prüfen und mit Lockdateien festschreiben.

## Allgemeine Testgrundsätze

Die Zielmatrix in [phases.md](phases.md) umfasst jetzt fünf Phasen. Zusätzlich prüfen: Rückwechsel 0..3 stellt Rechte der Zielphase wieder her; Teilnehmer-Adminausnahmen überschreiben keine gesperrten Eventeinstellungen; Kapazitätskonflikte warnen, erlaubte Änderung bleibt möglich ohne automatische Löschung. Zusatzantworten vorläufig alle bearbeitbar. Archivphase weist sämtliche rennbezogenen Schreib-/Neuberechnungswege auch im Adminbereich ab. Migration aller Bestandsrennen mit Ausgangsstatus 3 in das Archiv ohne Neuberechnung, einschließlich Wiederholung/Wiederaufnahme und unveränderter historischer Darstellung bei späteren Profiländerungen, benötigt eigene Tests. Diese Erweiterungen sind geplant, noch nicht implementiert oder ausgeführt.

Synthetische Testdaten verwenden und bestehende Anwendungsdaten unangetastet lassen. Externe Dienste einschließlich Mail- und Zahlungsversand werden simuliert. Echte Provider-Vertragstests nur separat aktiviert, nicht im Standard-PR-Lauf.

## Eigenständigkeit und Installationspaket

Die Fälle I-01 bis I-12 aus dem [Installationsvertrag](installation.md) sind verpflichtende Integrations-, Paket- und Browserprüfungen. Das tatsächliche Release-ZIP auf frischem WordPress mit Standardtheme und ohne weitere Fachplugins prüfen, nicht nur den Entwicklungscheckout. Neuinstallation und Bestandsupgrade getrennt abdecken; Wiederholbarkeit, fehlende Voraussetzungen, optionale Integrationen, Deaktivierung/Reaktivierung und datenerhaltende Deinstallation prüfen. Noch nicht implementiert oder ausgeführt.

## Qualitätsregeln

Tests sichern Verhalten und müssen bei plausiblen Fehländerungen scheitern. Bugfixes erhalten Regressionstests. Kritische Regeln benötigen Grenz-, Fehler- und Berechtigungsfälle. Keine bloßen Methodennamen-Tests oder pauschale Snapshot-Flut. Coverage ist Orientierung, kein Ersatz für Fachfälle.

CI-Artefakte nur mit synthetischen Daten, Screenshots/Traces bei Browserfehlern und mit begrenzter Aufbewahrung. Manuelle Fälle bleiben offen, bis ausgeführt.

## Primärquellen

- [PHPUnit 11: PHP >= 8.2](https://docs.phpunit.de/en/11.5/installation.html)
- [PHPStan](https://phpstan.org/user-guide/getting-started)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP)
