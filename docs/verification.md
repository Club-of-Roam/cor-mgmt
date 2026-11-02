# Prüfbelege

## Block 1 abgeschlossen – 2026-11-02

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Composer validate --strict | bestanden | bestanden | bestanden | bestanden |
| PHP-Syntax | 46 Dateien, 0 Fehler | 46 Dateien, 0 Fehler | 46 Dateien, 0 Fehler | 46 Dateien, 0 Fehler |
| Dokumentation / Inventar | 22 Markdown-Dateien, 23 Shortcodes, 0 Fehler | bestanden | bestanden | bestanden |
| Isolierte Referenz | 84 Tests / 307 Assertions | 84 / 307 | 84 / 307 | 84 / 307 |
| WordPress-Referenz und Sicherheit | 96 Tests / 658 Assertions | 96 / 658 | 96 / 658 | 96 / 658 |

Pro PHP-Version **180 Tests / 965 Assertions**, keine übersprungenen Tests oder PHPUnit-Warnungen. Gegenüber Block 0 erwarten die korrigierten Sicherheitsfälle jetzt geschütztes Verhalten; die veränderten Assertions sind deshalb kein unverändert gebliebener Altbefund. Private Berechnungsreferenzen bleiben für Block 6 erhalten.

Testsoftware weiterhin PHPUnit 11.5.57, Composer 2.11.1, WordPress 7.1.4 und MariaDB 11.4.13. Zusätzlich Node.js 24.22.2: **4 JavaScript-Tests bestanden**; Syntaxprüfung aller fünf geänderten JavaScript-Dateien erfolgreich. Keine npm-Abhängigkeiten. Die integrierte Bildprüfung einschließlich GD-Komposition wurde mit synthetischem PNG unter allen vier PHP-Versionen ausgeführt.

Ausgeführt: composer check, vollständige isolierte Suite und WordPress-Integration auf jeder PHP-Version; composer test:js / node --test tests/security-js.test.cjs und node --check für app, admin, sponsoring, location und map. Inventar nach letztem Anwendungscode regeneriert: 31 PHP-Quelldateien, 23 bestehende Shortcodes, 65 Methoden mit direkten Request-Referenzen. GitHub Actions enthält PHP-Matrix, GD, Node 24 und die Sicherheitssuiten; **kein Remote-GitHub-Lauf**.

Zusätzlich: Hashgleichheit zwischen beiden versionierten Upload-Ersatzdateien und ihren eingesetzten Gegenstücken bestätigt. Direkte HTTP-Aufrufe der alten URLs werden bereits vom Webserver mit 403 abgewehrt; die Ersatzdateien liefern bei PHP-Ausführung wie vorgesehen 410. Keine erfolgreichen Uploads über die alten Eingänge.

Prüfgrenzen: keine vollständige Browser-/multipart-HTTP-/UI-Abnahme, kein echter Gatewaylauf, keine produktive Zahlungsbestätigung, keine Migration oder Neuinstallations-/Releaseabnahme. JavaScript arbeitet mit DOM-Doubles, WordPress mit echten Core-/SQL-/Nonce-/Rechteprüfungen und synthetischen Daten. Vorhandene Anwendungsdaten wurden weder migriert noch neu berechnet. Kein echter Mailtransport oder Provider-HTTP; WordPress-HTTP und Mail sind in Tests abgefangen. Keine Git-Aktionen.

Umsetzung, Integrationsverträge und zugeordnete Folgearbeiten: [block-1-security.md](block-1-security.md).


## Block 0 abgeschlossen – 2026-11-02

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Composer validate --strict | bestanden | bestanden | bestanden | bestanden |
| PHP-Syntax | 40 Dateien, 0 Fehler | 40 Dateien, 0 Fehler | 40 Dateien, 0 Fehler | 40 Dateien, 0 Fehler |
| Dokumentation / Inventar | 21 Markdown-Dateien, 23 Shortcodes, 0 Fehler | bestanden | bestanden | bestanden |
| Isoliertes PHPUnit | 84 Tests / 309 Assertions | 84 / 309 | 84 / 309 | 84 / 309 |
| WordPress-Integration | 33 Tests / 298 Assertions | 33 / 298 | 33 / 298 | 33 / 298 |

Pro PHP-Version insgesamt **117 Tests und 607 Assertions**, beide Suiten erfolgreich. Testsoftware: PHPUnit 11.5.57 (composer.lock), Composer 2.11.1, offizielles WordPress 7.1.4 und MariaDB 11.4.13. WordPress-Archiv SHA-256: 881e53a4681ff2cd911651b11cccdede5ae477ddf0765af5d6a1a8b219a1d835. Workflow mit denselben Versionen/Prüfkommandos und Matrix 8.2..8.5 erweitert; kein Remote-GitHub-Lauf.

Ausgeführt: composer check, vollständige isolierte PHPUnit-Suite und separate Integration aus phpunit.integration.xml.dist mit allen vier ausdrücklich ausgewählten PHP-Binaries. WordPress nutzt nur synthetische Fixtures; echte SQL-Aufrufe, Benutzerrechte, Shortcodes und Mailabfang sind geprüft. Bootstrap ohne explizite Testvorgaben bricht wie vorgesehen ab. Core-Fixturewerkzeug wurde gegen das offizielle Archiv ausgeführt und übernimmt keine Sitekonfiguration/-inhalte.

Bekannte Ladeveraltungen (utilities: implizit nullable; XChange: Interpolation) werden eng protokolliert; Laufzeitfehler/Warnungen sind nicht pauschal unterdrückt, Integration setzt E_ALL wieder her. PHP-Lint blendet Veraltungen aus und ist reine Syntaxprüfung. Erwartete Altfehler sind bewusst Charakterisierung, keine Zielkorrekturen.

Unveränderte normalisierte Hashes aller 28 Legacy-PHP-Dateien bestätigt. Anwendungscode, Plugin-/Schemaversion und bestehende Anwendungsdaten unverändert. Keine echten Mails/Provideraufrufe, keine Git-Aktionen. Testdatenbank und Upload-/Core-Fixture getrennt von bestehenden Anwendungsdaten.

Abschlussumfang und Grenzen: [block-0-acceptance.md](block-0-acceptance.md), vollständige Referenzfälle: [reference-catalog.md](reference-catalog.md), Rollen/Schnittstellen: [interfaces.md](interfaces.md). Keine umfassende Sicherheits-, WordPress-Modul-, Browser-, Medienupload-, Migration- oder Releaseabnahme. Diese Aufgaben sind den Folgeblöcken zugeordnet.

## Frühere Zwischenstände

### Paket Ergebnisse und Ranglisten – früherer Zwischenstand 2026-11-02

| Prüfung | PHP 8.2.35 | PHP 8.4.27 |
|---|---|---|
| Composer validate --strict | bestanden | bestanden |
| Syntaxprüfung | 36 PHP-Dateien, 0 Fehler | 36 PHP-Dateien, 0 Fehler |
| Dokumentations-/Inventarprüfung | 18 Markdown-Dateien, 23 Shortcodes, 0 Fehler | 18 Markdown-Dateien, 23 Shortcodes, 0 Fehler |
| PHPUnit 11.5.57 | 71 Tests, 244 Assertions, bestanden | 71 Tests, 244 Assertions, bestanden |

22 neue Fälle führen die unveränderten Legacy-Methoden für Ergebnisabgabe und Ranglistenanzeige aus. Daten-/WordPress-/Renderinggrenzen sind Speicher-Doubles, Fehlerfälle werden als Ist-Verhalten erwartet. Details: [ranking-reference.md](ranking-reference.md). Composer check und vollständige PHPUnit-Suite wurden mit beiden ausgewählten PHP-Versionen ausgeführt. PHP 8.3/8.5 nicht ausgeführt; vorhandene CI-Konfiguration weiterhin nur 8.2/8.4, kein Remote-Lauf.

Die Inventarprüfung bestätigt unveränderte normalisierte Quellhashes aller 28 erfassten Legacy-PHP-Dateien. Keine Änderung von Anwendungscode, Header oder Anwendungsdaten, keine Git-Aktionen. Diese Tests sind keine WordPress-/DB-/Migration-/Browserabnahme und keine Freigabe der Zielmatrix oder des Archivs.

### Weitere frühere Prüfstände

Nachtrag 2026-11-02, Archiv-/Phasenentscheidungen: Dokumentations-/Inventarprüfung unter PHP 8.4.27 bestanden (17 Markdown-Dateien, 23 Shortcodes, 0 Fehler). Rückwechsel, Admin-Ausnahmen und neue Archivphase nur als Zielregeln dokumentiert; keine Migration oder neue Anwendungstests ausgeführt. Keine bestehende DB verändert.

Nachtrag 2026-11-02, Phasenmatrix: Dokumentations-/Inventarprüfung unter PHP 8.4.27 bestanden (17 Markdown-Dateien, 23 Shortcodes, 0 Fehler). Nur Dokumentation geändert; keine neuen Anwendungstests ausgeführt. Die untenstehenden Laufzeitnachweise bleiben die bisherigen Charakterisierungsergebnisse und belegen keine Implementierung der Zielmatrix.

Prüfdatum: 2026-11-01 (Europe/Berlin). Anwendungscode weiterhin 1.2.10 / Schema 2.8.

| Prüfung | PHP 8.2.35 | PHP 8.4.27 |
|---|---|---|
| Composer validate --strict | bestanden | bestanden |
| Syntaxprüfung | 34 PHP-Dateien, 0 Fehler | 34 PHP-Dateien, 0 Fehler |
| Dokumentations-/Inventarprüfung | 16 Markdown-Dateien, 23 Shortcodes, 0 Fehler | 16 Markdown-Dateien, 23 Shortcodes, 0 Fehler |
| PHPUnit 11.5.57 | 49 Tests, 119 Assertions, bestanden | 49 Tests, 119 Assertions, bestanden |

Befehle: composer check und composer test, jeweils mit dem ausdrücklich ausgewählten PHP-Binary. Die Syntaxprüfung blendet Veraltungsdiagnosen aus; sie ist keine vollständige PHP-8-Freigabe. Die Unit-Tests selbst laufen mit strenger Warnungs-/Veraltungsbehandlung, ohne WordPress, Datenbank oder externe Dienste.

Verwendete Testsoftware: Composer 2.11.1 und PHPUnit 11.5.57. composer.lock hält die aufgelösten 27 Entwicklungsabhängigkeiten fest; vendor ist ignoriert.

Inventar: 23 Shortcodes, 54 Methoden mit literalen Request-Feldern und 28 Legacy-PHP-Dateien. Hashes normalisieren BOM/CRLF, damit Windows und Linux dieselben Quellen erkennen.

## Grenzen

- GitHub Actions konfiguriert, kein Remote-Lauf ausgeführt; kein Push.
- Keine vollständigen WordPress-, Browser-, Datenbankmigrations- oder Produktionsprüfungen.
- PHPUnit deckt F-RANK-01 und F-TICK-04 sowie Teilregeln zu F-TEAM-02/04 und F-ROUTE-01 ab. Zusätzlich werden Pflichtfeld-/Honeypotfälle charakterisiert. Details und offene Gesamtpfade in business-rules.md; keine umfassende Funktionsabdeckung.
- Keine Änderung an Anwendungscode, Versionsheader, Datenbank.
- Zum damaligen Zwischenstand blieb Block 0 begonnen; durch den Referenzabschluss oben ersetzt.

## Paket Fachregeln

27 zusätzliche Tests mit echten Legacy-Methoden und Speicher-Doubles: aktivierte/deaktivierte Pflichtanforderungen, Mindestmitglieder, Freigabe-/Rücknahme-Nebenwirkungen, wiederholter Aufruf ohne zweite Benachrichtigung, mehrere Startpunkte, Einladungsanzahl, Formularvalidierung und Routenfilter. Keine echte SQL-Ausführung oder Mailzustellung. Die Inventarprüfung bestätigt unveränderte normalisierte Quellhashes aller 28 erfassten Legacy-PHP-Dateien. Die Tests belegen keine Sicherheit der unbehandelten Speicherpfade. Zum damaligen Prüfstand waren Phasenmatrix und Zusatzpunktefehler nur statisch untersucht. Zusatzpunkte sind nun im Paket 2026-11-02 isoliert reproduziert; Controller-/Phasenabnahme bleibt ausstehend.
