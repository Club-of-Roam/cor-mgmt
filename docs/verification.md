# Prüfbelege

## Block 5: Teams und Profile – 2026-11-04

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Suite | 234 Tests / 499 Assertions | 234 / 499 | 234 / 499 | 234 / 499 |
| WordPress-Integration | 232 Tests / 1.464 Assertions | 232 / 1.464 | 232 / 1.464 | 232 / 1.464 |
| composer check | bestanden | bestanden | bestanden | bestanden |

Die Suiten prüfen Anmeldung und Inhalte über alle fünf Phasen, freie Mitglieds-/Routenplätze, Hash-Codes, verbrauchte/widerrufene und alte Codes einschließlich führender Nullen, Wiederöffnung, Profil-/Avataradapter, einzelne und gesammelte Bestätigungen, Objektberechtigungen, Kontaktumfang für Routenverantwortliche, veraltete Formulare, duplicate Legacy-Beziehungen und schreibfreie Reader. Erzwungene Fehler rollen Team, Mitgliedschaft, Token und Profil gemeinsam zurück, auch bei gemeinsamem äußerem Lock/Savepoint und gefangenem Teilfehler.

TeamConcurrencyTest startet echte parallele PHP-Prozesse: genau ein Zugriff erhält den letzten Mitglieds- bzw. Routenplatz; ein späterer Wiederholungsversuch scheitert an ausgeschöpfter Kapazität. Die originale Einladungstabelle bleibt dabei auch als MyISAM-Quelle unverändert. ParticipationInstallationTest prüft echte additive DDL, Wiederholung, Wiederaufnahme, Eigentumskollision, eindeutigen Tokenindex, abweichende Pläne und fehlende abgeschlossene Tabellen. SchemaToolsTest prüft die neue Einrichtung über echte WordPress-POST-/Nonce-/Rechtegrenzen. Sämtliche Daten sind synthetisch, Mail und HTTP sind abgefangen.

Die Vollsuiten bestanden damit je PHP-Version mit 466 Tests / 1.963 Assertions. Nach den Vollsuiten bestand der ergänzte Formularfall für mehrzeilige Zusatzantworten und Einladungsweiterleitung nach Anmeldung auf allen vier Versionen erneut mit 1 Test / 12 Assertions. Unbekannte serialisierte historische Profilwerte sind in der isolierten Suite als unvollständig und unverändert lesbar abgesichert. Keine Warnungen oder pauschalen Unterdrückungen als Erfolg werten.

composer check umfasst Metadaten, **118 PHP-Dateien**, **27 Markdown-Dateien**, **83 Inventarquellen / 23 Shortcodes / 69 Methoden mit direkter Request-Referenz**, beide Schemareferenzen, PHPStan Level 8, PSR-/WordPress-Regeln und Compatibility-Ergänzung. **7 Node-Tests und ESLint** bestanden. Kein neuer verpflichtender Laufzeitdienst oder Frontend-Build.

Paketgrenzen und vollständiger Produktionsloader mit **236 Dateien** auf PHP 8.2 und 8.5 geprüft. Zwei Builds unveränderter Quellen ergeben dieselbe SHA256-Prüfsumme. Das ist keine Browser- oder Releasefreigabe.

Keine bestehende Anwendungskopie migriert, keine aktive Generation ausgewählt und keine historischen Punkte berechnet. Vollständiger Archivsnapshot, normale Archivabschlüsse, Browser-/Tastatur-/Accessibility-Abnahme, positiver multipart-Bildtransport, echte MySQL-Abnahme und releaseweite Theme-/Integrationsabnahme bleiben offen. Dauerhafte Zustellqueue gehört zu Block 8. Kein entfernter GitHub-Lauf und keine Git-Aktionen. [Umfang und Quellen](block-5-teams.md).



## Block 4: Rennen-/Routenverwaltung – 2026-11-04

Die vollständigen Suiten auf PHP 8.2.35, 8.3.36, 8.4.27 und 8.5.12 bestanden jeweils mit **187 isolierten Tests / 437 Assertions** und **183 WordPress-Integrationstests / 1.055 Assertions**: **370 Tests / 1.492 Assertions je PHP-Version**, ohne Warnungen oder ausgelassene Tests.

Nach Ergänzung der erhaltenen Etappenfilter bestand die betroffene EventManagementTest-Suite nochmals auf allen vier PHP-Versionen mit **42 Tests / 172 Assertions**. Anschließend bestand die zusätzliche Routenkonto-Kompatibilitätsregression ebenfalls auf allen vier Versionen (1 Test / 9 Assertions): neu zugewiesene Konten erscheinen neben historischen Rollenkonten in den bestehenden Auswahladaptern. Unveränderte Fach-/Migrationssuiten wurden für diese begrenzten Darstellungs-/Auswahlanpassungen nicht erneut vollständig ausgeführt.

Neue Prüfungen: Feldgruppen über fünf Phasen, Phase-0-Anpassung der Teilnahmevoraussetzungen, expliziter Phasenrückwechsel, Archiv-/Abschlusssperre, Bearbeitung von Datum und Treffpunkt nach Start, echte WordPress-Nonce-/Rollenprüfung, veraltete Datensatz-/Elternrevision, Kapazitäts- und Startpunktwarnung mit Bestätigung, unveränderte Zuordnungen/JSON-Zusatzdaten/Zeitstempel, abhängige Struktur, gemischte Sammellöschung, Kontoerstellung erst nach Validierung, schreibfreies Rendering, keine Passworthashes in Formularen, zweiter DB-Client am Lock, erzwungener Rollback der aktiven Auswahl und echte MyISAM-Tabellen. Synthetische Daten, kein realer Versand.

Syntaxumfang: 103 PHP-Dateien; Inventar: 73 Quellen, 23 Shortcodes, 67 Methoden mit direkter Request-Referenz; 26 Markdown-Dateien. ESLint und die bestehenden **7 Node-Tests** bestanden. Der erste Standardlauf fand vorhandene CRLF-Zeilenenden in den PSR-Dateien. Diese wurden ohne Inhaltsänderung vereinheitlicht und der LF-Vertrag in .gitattributes festgehalten. **composer check bestand anschließend auf allen vier PHP-Versionen**, einschließlich PHPStan Level 8, PSR-/WordPress-Regeln und Compatibility-Ergänzung. Zwei parallel kollidierende PHPStan-Cache-Läufe wurden mit getrennten temporären Caches erfolgreich wiederholt; kein Regelwerk oder Befund wurde unterdrückt.

Paket: **224 Dateien**, fertiger Produktionsloader ohne Entwicklungsabhängigkeiten; Typen-/Paketprüfung auf PHP 8.2 und 8.5 bestanden. Zwei Builds unveränderter Quellen ergeben die identische SHA256-Prüfsumme. Der Paketprozess ist keine Browser- oder vollständige Releaseabnahme.

Keine Anwendungskopie migriert, keine Generation aktiviert, keine historischen Punkte berechnet. Neue Archivabschlüsse bleiben bis zu vollständigen Snapshots und Writer-Sperren gesperrt. Keine vollständige Browser-/Tastatur-/MySQL-/Theme-/Releaseabnahme und kein entfernter GitHub-Lauf. Teilnehmerseitige Nebenläufigkeit folgt in Block 5. Details und Quellen in [Block 4](block-4-events.md).

## Block 3: Erstinstallation und kontrollierte Zwischenmigration – 2026-11-03

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Composer / Syntax / Schema-Referenz / Inventar | bestanden | bestanden | bestanden | bestanden |
| PHPStan Level 8 / PSR- und WordPress-Regeln / Compatibility-Ergänzung | bestanden | bestanden | bestanden | bestanden |
| Isolierte Referenz und Fundament | 125 Tests / 373 Assertions | 125 / 373 | 125 / 373 | 125 / 373 |
| WordPress, Sicherheit, Installation und Migration | 142 Tests / 888 Assertions | 142 / 888 | 142 / 888 | 142 / 888 |

Pro PHP-Version **267 Tests / 1.261 Assertions**, ohne übersprungene Tests oder PHPUnit-Warnungen. Die vollständigen Suiten wurden nach der abschließenden Sortierkorrektur erneut auf allen vier Versionen ausgeführt. 92 PHP-Dateien in der Syntaxprüfung; Inventar 64 PHP-Quellen, 23 bestehende Shortcodes und 66 Methoden mit direkten Request-Referenzen. 25 Markdown-Dateien und aktuelle generierte SQL-Referenz.

WordPress 7.1.4 und MariaDB 11.4.13. Die WordPress-Suite erzeugt jetzt das reale Erstinstallationsschema mit neun Legacy-kompatiblen und sechs getrennten Migrationstabellen statt der früheren Minimalfixtures. Zusätzliche eigene Präfixe und Verbindungen prüfen Erstaktivierung, fehlende Administratorrolle, DDL-Unterbrechung, Eigentumskollision, Wiederholung mit Daten, reine Vorprüfung, Orphans, unbekannte Spalten, MyISAM-Quelle, latin1/utf32/Binärdaten, sichere Phasen-JSON-Decodierung und neue/inkompatible Speichergenerationen.

Migrationsregressionen: atomarer Batch-Rollback mit unverändertem Cursor, Wiederaufnahme ohne Duplikate, verlorener Komfortzeiger, konkurrierende/verlorene Locks, veränderte Quellen, korrupte Payloads/Projektionen, unerwartete Zielzeilen, unbekannte Schrittfassungen, unveränderte historische Summen und Original-IDs, unbekannte Phasen und nicht geratene Geldbeträge. Unklare Etappenzuordnung bleibt explizit NULL; Originaldaten bleiben erhalten. Kein aktiver Generationseintrag oder Live-Phasenwechsel wird durch den Kopierlauf gesetzt.

Ein zusätzlicher Grenztest entdeckte eine lexikografische Sortierung nach dem HEX-Ausgabealias statt nach der numerischen Quell-ID. Der Vergleich wies die unvollständige Kopie ab; es wurde kein fehlerhafter Lauf verifiziert. Die Sortierung verwendet nun die qualifizierte Quellspalte. Gemischte ID-Längen sowie benachbarte IDs am Maximum von BIGINT UNSIGNED werden in kleinen Batches einschließlich Ergebnisprojektionen geprüft. Numerische Schlüsselvergleiche verwenden ausdrückliche exakte Casts.

SchemaToolsTest prüft echte WordPress-Administrationsrechte, POST, Nonces und JSON-Grenzen. Die Vorprüfung gibt nur aggregierte Daten aus. **7 Node-Tests** und ESLint bestanden; neue Fälle prüfen automatische Batchfortsetzung, Abbruch und Same-Origin-Schutz. `npm ci --ignore-scripts`, npm-Audit und Composer-Audit ohne bekannte Sicherheitswarnungen. Werkzeugversionen und begrenzte PHPCompatibility-9-Abdeckung wie in Block 2.

Paketbau und Produktionsloader wurden geprüft. Das ZIP umfasst 214 Dateien einschließlich der explizit erlaubten generierten SQL-Strukturreferenz; beliebige SQL-Dumps, Logs und private Dateien bleiben ausgeschlossen. Reproduzierbarkeit wird mit zwei Builds desselben endgültigen Stands und Loaderprüfung unter PHP 8.2/8.5 kontrolliert. Dies ist keine vollständige Release-/Standardtheme-/Browserabnahme.

Nicht ausgeführt: Migration vorhandener Anwendungsdaten, aktive Reader-/Writer-Umschaltung, vollständiger Benutzer-/Medien-/Ticker-Archivsnapshot, echte Provider-/Zahlungsaufrufe, tatsächliche MySQL-Matrix und vollständiger Wiederherstellungslauf des Releases. Kein neuer GitHub-Lauf und keine Git-Aktionen. Stand und Restarbeiten: [Block 3](block-3-migration.md).

## Block 2: technische Laufzeitbasis – 2026-11-03

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Composer-Metadaten / Syntax / Inventar | bestanden | bestanden | bestanden | bestanden |
| PHPStan Level 8 für src | bestanden | bestanden | bestanden | bestanden |
| PSR-/WordPress-Regeln und PHPCompatibility-Ergänzung | bestanden | bestanden | bestanden | bestanden |
| Isolierte Referenz und Fundament | 104 Tests / 345 Assertions | 104 / 345 | 104 / 345 | 104 / 345 |
| WordPress-Referenz, Sicherheit und Lebenszyklus | 105 Tests / 726 Assertions | 105 / 726 | 105 / 726 | 105 / 726 |

Pro PHP-Version **209 Tests / 1.071 Assertions**, ohne übersprungene Tests oder PHPUnit-Warnungen. 74 PHP-Dateien in der Syntaxprüfung; Inventar 51 PHP-Quellen, 23 bestehende Shortcodes und 65 Methoden mit direkten Request-Referenzen. Dokumentation: 24 Markdown-Dateien, keine Prüffehler. Die neuen Codegrenzen und Einschränkungen stehen in [Block 2](block-2-foundation.md); keine pauschale statische Freigabe des Altbestands.

WordPress 7.1.4 und MariaDB 11.4.13 mit synthetischen Tabellen einschließlich frischer Datenbank-Erstläufe. Neue Regressionen: absolute Upload-URL beim ersten Core-Start, fehlende Voraussetzungen, neue/unvollständige/ältere/neuere Schemas ohne automatische Reparatur, Aktivierung/Netzwerkablehnung, datenerhaltende Deinstallation über beide Eingänge, ausschließlich eigene Cron-Aufgaben auch mit Argumenten, austauschbare Uhrzeit, Zufallsgrenzen, begrenztes HTTP, Mail-Interception und datensparsame Fehlerprotokolle einschließlich anonymer Ausnahmetypen. Die vier Integrationsvollsuiten bestanden; nach der abschließenden Logger-Härtung wurden die isolierten Suiten auf allen vier PHP-Versionen erneut ausgeführt. Kein Schema- oder Fachverhalten wurde danach geändert.

Weitere Prüfungen bestanden: `npm ci --ignore-scripts`, ESLint für alle eigenen JavaScript-Dateien, **4 Node-Sicherheitstests**, `npm audit` ohne Befund und Composer-Audit ohne bekannte Sicherheitswarnung. Werkzeuge: Composer 2.11.1, PHPUnit 11.5.57, PHPStan 2.3.4, WordPress-Stubs 7.1.2, PHPCS 3.13.6, WPCS 3.4.1, PHPCompatibilityWP 2.1.8/PHPCompatibility 9.3.5, Node.js 24.22.2, ESLint 10.14.0. Alle aufgelösten Versionen sind gesperrt. Begrenzte moderne PHP-Abdeckung des alten Compatibility-Regelwerks ausdrücklich in Block 2 dokumentiert.

Paketbau: 198 Dateien mit fertigem Produktionsloader und Dokumentation. Keine Entwicklungsabhängigkeiten, privaten Konfigurationen, Shell-Historien oder Fehlerprotokolle. Zwei aufeinanderfolgende Builds identischer Quellen ergaben dieselbe SHA256-Prüfsumme. Gesonderter Paketprozess lädt alle neuen Klassen, Interfaces und Enums sowie den Zufallsadapter unter PHP 8.2 und 8.5 ohne Entwicklungs-vendor. Der Build wurde mit PHP 8.2 sowie 8.5 ausgeführt. Paketprüfung ist keine vollständige WordPress-Erstinstallation oder UI-Abnahme.

Der [GitHub-Lauf 41537851183](https://github.com/Club-of-Roam/cor-mgmt/actions/runs/41537851183) wurde lesend analysiert: fehlende absolute WP_CONTENT_URL im frischen Core-Bootstrap verursachte die Medientestfehler aller PHP-Jobs. Bootstrap und Regressionstest sind korrigiert. Kein entfernter Wiederholungslauf für diesen neuen Stand; die aktualisierte CI-Konfiguration ist keine Bestätigung eines erfolgreichen GitHub-Laufs.

Offen bleiben die Neuinstallation und Migration aus Block 3, die späteren Fachmodule sowie Browser-/Accessibility-/Standardtheme-/vollständige Releaseabnahme und weitere WordPress-/DB-Kombinationen. Keine produktiven Aufrufe, keine Migration oder Neuberechnung vorhandener Anwendungsdaten, keine Git-Aktionen. Laufender Versionsheader 1.2.10, Schema-Marker 2.8.

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
