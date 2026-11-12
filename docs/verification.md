# Prüfbelege

## Gemeinsame Abnahme: getrennte Archivspeichereinrichtung – 2026-11-12

Screenshot bestätigt AB-I01-B08: Reine Archivspeichereinrichtung war im selben Formular wie Archivierung/Umschaltung an Wartungsbestätigungen gebunden. Neue `archive-setup`-Aktion am vorhandenen Schema-Endpunkt verwendet Administratorrecht, POST, Schema-Nonce und bestehende Voraussetzungskontrolle. Sie ruft ausschließlich den wiederholbaren Archiv-Installer auf und bestätigt Neuladen. Systemstatus bietet den fehlenden Speicher mit bestehender Warteanzeige an; Archivseite verlinkt diesen getrennten Weg. Der globale Hinweis benennt fehlenden Archivspeicher konkret. Bestehende Archiv-/Umschaltbefehle einschließlich des bisherigen administrativen Setup-POST behalten unverändert Sicherungs-/Stillstandsbestätigungen.

`composer check` und `composer test` bestehen auf PHP 8.2/8.3/8.4/8.5 mit jeweils 350 Tests / 678 Assertions. Inventar regeneriert. Neue Integrationstests prüfen GET/Gast/falsche Nonce, wiederholte Einrichtung ohne Wartungsbestätigungen, unveränderte Zeilenzahlen der Fach-/Archivtabellen, unveränderte Laufzeitgeneration sowie gezielten Hinweis und getrennte Oberfläche. Ein erster Integrationsversuch wurde durch einen Absturz des separaten MariaDB-Testprozesses bei einer Schemaabfrage unterbrochen; Wiederholung auf einer frisch initialisierten synthetischen Testdatenbank. Dort bestehen auf allen vier Versionen die 21 Archiv-/Umschaltfälle; der neue Einrichtungsfall scheiterte zunächst an seinem unvollständigen Testaufbau: Nach absichtlichem Entfernen des Besitzermanifests für die Anzeigeprüfung darf der Installer vorhandene Tabellen nicht übernehmen. Testaufbau korrigiert, Kollision ausdrücklich als erwartete Abweisung ergänzt; Laufzeitcode und Kollisionsschutz unverändert. Kein erfolgreicher gemeinsamer 59-Fälle-Lauf mit dem korrigierten Testaufbau behauptet. Keine Verbindung dieser Testläufe zur Anwendungsdatenbank.

Abschließend bestehen alle 38 Schema-Verwaltungsfälle / 144 Assertions auf PHP 8.2/8.3/8.4/8.5 mit korrigiertem Testaufbau. Dokumentationsprüfung bestanden. Gemeinsame Einrichtung und Nachprüfung des verschwundenen Hinweises offen. Keine neue automatische Browser- oder ZIP-Prüfung; unveränderte JavaScript-Logiktests nicht wiederholt. Keine Einrichtung oder Datenänderung in der Anwendungsdatenbank durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: verbleibender Einrichtungshinweis – 2026-11-11

Benutzer meldet weiterhin den allgemeinen Einrichtungshinweis. Quellprüfung von Lifecycle::notice bestätigt: Zusätzlich zu den sieben Zusatzspeichern wird ArchiveSchema::ready verlangt. Lesende Bestandsprüfung in schreibgeschützter Transaktion bestätigt fehlendes Archiv-Installationsmanifest und null von drei erwarteten Archivtabellen. Damit ist mindestens eine konkrete Auslösebedingung nachgewiesen; die erfolgreiche Tickerübernahme ersetzt die Archivspeichereinrichtung nicht. Keine Tabellen oder Optionen geändert.

AB-I01-B08 erfasst den ungenauen Hinweis und den noch offenen Einrichtungsschritt. Archivwerkzeug zunächst gemeinsam sichten: Sein bisheriges Formular verlangt Sicherung und Writerstillstand auch bei der reinen Speichereinrichtung. Diese Bestätigungen dürfen nicht nur zum Entfernen einer Warnung gesetzt werden. Keine Freigabe eines Archivabschlusses oder einer aktiven Tabellenumschaltung. Nächster Schritt vor AB-I03: Archivwerkzeug und Voraussetzungen prüfen.

Nur Dokumentation geändert; Dokumentationsprüfung bestanden. Keine neuen Laufzeit-/Integrations-/Browser-/Pakettests, keine Datenänderungen und keine Git-Aktionen.

## Gemeinsame Abnahme: Tickerhinweise eingeordnet – 2026-11-11

Manuelle lesende Bestandsprüfung in einer ausdrücklich schreibgeschützten Datenbanktransaktion, ohne WordPress-Start oder automatisierte Tests gegen Anwendungsdaten. Nur Hinweis-Codes und aggregierte Zählungen ausgegeben; keine personenbezogenen Inhalte übernommen. 16.115 Tickerbeiträge, davon 531 mit mindestens einem Hinweis; insgesamt 559 Hinweise. Die Summe stimmt mit dem vom Benutzer gemeldeten Abschluss überein.

| Hinweis | Anzahl | Bedeutung |
|---|---:|---|
| `unknown_timestamp` | 222 | Historischer Zeitwert nicht als unterstützter Unix-Zeitstempel erkennbar |
| `historical_author` | 132 | Absenderkennung entspricht nicht dem unterstützten Benutzer-/Team-ID-Format |
| `former_participant` | 58 | Referenziertes Benutzerkonto nicht vorhanden |
| `unresolved_image` | 42 | Bildverweis kann nicht als zulässige vorhandene Bilddatei aufgelöst werden |
| `unknown_parent` | 40 | Kommentar ohne erkennbare übergeordnete Beitrags-ID |
| `orphan_team` | 31 | Referenziertes Team fehlt oder passt nicht zum Event |
| `orphan_event` | 24 | Eventzuordnung fehlt oder verweist auf keinen vorhandenen Eventdatensatz |
| `unknown_media_reference` | 10 | Video-/Standortverweis entspricht keinem unterstützten Format |

Ein Beitrag kann mehrere Hinweise tragen. Insbesondere 42 nicht aufgelöste Bilder sind kein Nachweis, dass alle 42 Originaldateien verloren sind; Pfade, Dateibestand und Sicherheitsprüfung müssen getrennt untersucht werden. Keine geratene Reparatur, keine Löschung und keine Gleichsetzung mit den Medienzählungen der früheren Archivprobe. Kategorien gemäß [Content](../src/Domain/Ticker/Content.php) und [TickerImport](../src/Infrastructure/WordPress/TickerImport.php). Inhaltliche Einzelfallprüfung bleibt offen (AB-I01-B07 / R-03).

Nur Dokumentation geändert; Dokumentationsprüfung bestanden. Keine neuen Laufzeit-, Integrations-, Browser- oder Pakettests ausgeführt. Datenbankverbindung nach Rollback beendet; keine Daten geändert und keine Git-Aktionen.

## Gemeinsame Abnahme: kompakte Tickerbilder – 2026-11-11

Benutzer bestätigt Stop und Fortsetzen; AB-I01-B05 geschlossen. Sichtprobe zeigt übernommene Text-/Bildbeiträge. Befund AB-I01-B06: Bilder in der Verwaltung beanspruchen zu viel Platz. Moderationsbilder erhalten eine proportionale Vorschau mit maximal 360 × 280 Pixeln und begrenzter Containerbreite. Kleine Bilder werden nicht vergrößert; Bild und Textlink öffnen volle Größe im neuen Tab mit `noopener noreferrer`. CSS-Assetversion erhöht. Originalmedien und Übernahme unverändert; gemeinsame Nachprüfung der Vorschau offen.

Neuer Playwright-Browserfall mit synthetischen Bildern bestanden: Querformat, Hochformat, kleines Bild, Proportionen, Größenbegrenzung und fehlender horizontaler Überlauf bei 1.000/320 Pixeln Ansichtsbreite sowie Tastaturzugriff auf den Vollgrößenlink. ESLint bestanden, nachdem Browser-Globals im Test ausdrücklich deklariert wurden. `composer check` und `composer test` bestehen auf PHP 8.2/8.3/8.4/8.5 mit jeweils 350 Tests / 678 Assertions. Inventar regeneriert; abschließende Dokumentationsprüfung bestanden. Keine neue WordPress-Integrationssuite oder Paketprüfung, keine Wiederholung der unveränderten JavaScript-Logiktests; keine Datenbankaktionen durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: Tickerübernahme abgeschlossen – 2026-11-11

Benutzer bestätigt Abschluss über die automatische Fortsetzung: `state=native`, `count=16115`, `verified=16115`, `cursor=max=17851`, `issues=559` und sichtbare Abschlussmeldung. Damit ist der Kopier-/Prüfdurchlauf abgeschlossen und nativer Speicher aktiv. Das ist kein Nachweis fehlerfreier historischer Medien oder vollständiger fachlicher Tickerabnahme. 559 Hinweiszählungen sind keine Anzahl verlorener Beiträge; konkrete Kategorien und Inhalte bleiben zu prüfen. Gemeinsame Nachprüfung von Stop/Wiederaufnahme noch nicht ausdrücklich bestätigt. Nächster Schritt: Eventfilter und übernommene Darstellung gemeinsam prüfen.

Nur Dokumentation aktualisiert; Dokumentationsprüfung bestanden. Keine neuen Laufzeit-, Integrations-, Browser- oder Pakettests ausgeführt. Keine Datenbankaktionen durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: automatische Tickerfortsetzung – 2026-11-11

AB-I01-B05 ergänzt die ausdrücklich gestartete automatische Fortsetzung eines bereits laufenden Tickerimports. Sie verwendet dieselbe geschützte POST-Aktion wie der Einzel-Schritt und ändert weder Kopierlogik noch Datenbankschema. Eine Anfrage nach der anderen; Kopie wechselt in Verifikation. Stop wartet auf den aktuellen Schritt. Fehler, unerwartete Seiten, Weiterleitungen, geänderte Quellidentität oder fehlender Fortschritt stoppen ohne automatische Wiederholung. Bei unklarem Ausgang muss der gespeicherte Stand nach erneutem Öffnen geprüft werden. Kopieranzeige nennt Quell-IDs; Verifikationsanzeige zählt tatsächlich geprüfte Datensätze. Einzelaktionen bleiben ohne JavaScript verfügbar.

28 JavaScript-Tests bestanden. Vier zusätzliche Regressionstests decken expliziten Start, Nonce/POST/gleiche Herkunft, Übergang Kopie/Verifikation, Stop ohne Abbruch der laufenden Anfrage, Mehrfachklickschutz, Fehler ohne Wiederholung und ungültige/geänderte Zustände ab. Zwei neue Playwright-Browserfälle mit ausschließlich synthetischen Antwortseiten bestanden: tatsächliche Formularübermittlung/HTML-Auswertung bis zum Abschluss sowie Stop/Wiederaufnahme. Diese Browserfälle verbinden sich nicht mit einer WordPress-Datenbank und sind kein neuer WordPress-Integrationsnachweis. Inventar regeneriert. Gemeinsame Bedienprüfung am laufenden Übernahmestand offen; bisheriger Benutzerstand `copy`, Cursor 500, 16.115 Quelldatensätze, 15 Hinweise.

`composer check` und `composer test` auf PHP 8.2/8.3/8.4/8.5 bestanden, jeweils 350 Tests / 678 Assertions. ESLint einschließlich der neuen Browserfälle bestanden; abschließende Dokumentationsprüfung bestanden. Keine Anwendungsdatenbankaktionen durch den Agenten, keine neue Übernahmeprobe, kein neues ZIP und keine Git-Aktionen.

## Gemeinsame Abnahme: Tickereinrichtung und Rückmeldung – 2026-11-11

Benutzerscreenshot bestätigt erfolgreiche Einrichtung der Tickertabellen; Übernahmezustand zuletzt `pending`. Keine historische Übernahme durch den Agenten gestartet. AB-I01-B04 erfasst die fehlende Warteanzeige und unauffällige Erfolgsmeldung. Korrektur: Spinner, Live-Status und Mehrfachklickschutz bei nativer Formularübermittlung; der Aktionswert wird trotz gesperrter Buttons erhalten. Erfolgreiche Tabelleneinrichtung wird als hervorgehobene Meldung mit ausdrücklicher Abgrenzung zur Übernahme bestätigt. Benutzer bestätigt erneute Durchführung und gute Rückmeldung; AB-I01-B04 geschlossen. Erneute reine Quellprüfung der bekannten Nachbar-/Upload-Eingänge bestätigt weiterhin stillgelegte Platzhalter; keine Änderung dieser Dateien. Nächster gemeinsamer Schritt: kontrollierten Tickerübernahmestart anleiten und Zustand prüfen.

24 JavaScript-Tests und ESLint bestanden; zwei neue Regressionstests prüfen Warteanzeige, korrekte POST-Aktion, Mehrfachklickschutz und Verwendung außerhalb der Tickerseite. Inventar regeneriert. Ein erster PHP-8.2-Qualitätslauf wurde durch einen Schreibfehler im gemeinsamen PHPStan-Cache gestoppt; abschließende Wiederholung mit getrennten temporären Verzeichnissen auf allen vier Versionen erfolgreich. `composer check` und `composer test` bestehen auf PHP 8.2/8.3/8.4/8.5, jeweils 350 Tests / 678 Assertions. Keine neue WordPress-Integrationssuite, automatisierte Browser- oder ZIP-Prüfung für diese Oberflächenkorrektur. Keine Datenbankaktionen durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: übrige Zusatzspeicher – 2026-11-11

Benutzer bestätigt die erfolgreiche Einrichtung der fünf übrigen Zusatzspeicher für Spenden, Event-Autoresponses, Mailqueue, Rundmails und Börsen-Audit. Alle Einrichtungsbuttons verschwunden, Warteanzeige funktioniert. Damit sind die sieben angebotenen Zusatzspeicher im gemeinsamen sichtbaren Ablauf eingerichtet. Fachliche Modulabnahme, Tickerübernahme und Archivumschaltung werden dadurch nicht als bestanden ausgewiesen. Nächster Schritt ist die Sichtprüfung der Tickerwerkzeuge; siehe [Abnahmeprotokoll](block-10-acceptance.md).

Nur Dokumentation geändert; Dokumentationsprüfung bestanden. Keine erneuten Laufzeit-, Integrations-, Browser- oder Pakettests erforderlich bzw. ausgeführt. Keine Datenbankaktionen durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: Warteanzeige und Moduleinrichtung – 2026-11-11

AB-I01-B03 ist geschlossen: Der Benutzer bestätigt die sichtbare Warteanzeige und erfolgreiche Einrichtung des Ergebnisspeichers. Zuvor wurde die Einrichtung des Team-/Einladungsspeichers bestätigt. Die übrigen fünf Speicher-Einrichtungen und die fachlichen Modulabläufe bleiben offen; siehe [Abnahmeprotokoll](block-10-acceptance.md).

Die Statusseite zeigt während der Verarbeitung einen Spinner, einen Live-Status und eine Wartebeschriftung. Aktionen bleiben bis zum Neuladen gesperrt; Fehler stellen die vorherigen Schaltflächenzustände wieder her. Die Assetversion wurde erhöht. Datenbankwriter und Migrationslogik bleiben unverändert.

`composer check` und `composer test` bestehen auf PHP 8.2/8.3/8.4/8.5 mit jeweils 350 isolierten Tests / 678 Assertions. Alle 22 JavaScript-Tests und ESLint bestehen; zwei neue Regressionstests prüfen die unmittelbare Warteanzeige, Mehrfachklickschutz und Wiederherstellung nach Netzwerkfehlern. Nach der abschließenden Textanpassung bestehen zusätzlich alle fünf gezielten Schema-JavaScript-Tests. Inventar regeneriert. Keine neue WordPress-Integrationssuite, automatisierte Browserprüfung oder ZIP-Prüfung für diese Oberflächenkorrektur; der sichtbare Erfolgsablauf wurde gemeinsam im Browser geprüft. Keine Datenbankeinrichtung durch den Agenten und keine Git-Aktionen.

## Gemeinsame Abnahme: Einstieg und Hinweistext – 2026-11-11

Benutzerrückmeldung zu AB-I01/AB-I02 anhand von Screenshots erfasst: Systemstatus und Event-ID-Auswahl erreichbar; erkannter Altbestand und noch ausstehende Moduleinrichtungen sichtbar. WPML-Kompatibilität als gesonderten Integrationsbefund festgehalten. Der veraltete Hinweis zur noch nicht verfügbaren aktiven Umschaltung ist korrigiert; der Systemstatus verlinkt nun die getrennten Archiv-/Umschaltwerkzeuge. Benutzer-Nachsicht und tatsächliche Moduleinrichtung bleiben offen; siehe [Abnahmeprotokoll](block-10-acceptance.md).

Für diese Änderung bestehen `composer check` und `composer test` auf PHP 8.2/8.3/8.4/8.5, jeweils 350 isolierte Tests / 678 Assertions. Inventar regeneriert, Dokumentationsprüfung bestanden. Datenbankwriter und Migrationslogik unverändert; keine neue Integrations-/Browser- oder ZIP-Prüfung in diesem Paket. Keine Datenbankeinrichtung oder Migration durch den Agenten und keine Git-Aktionen.

## Block 10: Archiv und aktive Speicherumschaltung – 2026-11-10

Umsetzung und Bedienvertrag: [Archiv und Umschaltung](block-10-archive.md). Pluginheader 1.2.10 und die bisherigen neun SQL-Installationspläne sind gegenüber dem vorherigen Paket bytegleich. Unabhängiges Archivschema 1.0.0 und Umschaltplan `live-copy-v1` ergänzt. Keine Git-Aktionen oder Freigabe einer Anwendungs-/Produktionsdatenbank.

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 350 Tests / 678 Assertions | 350 / 678 | 350 / 678 | 350 / 678 |
| WordPress-Gesamtsuite vor abschließender Schlüsseloptimierung | 457 Tests / 3.339 Assertions | 457 / 3.339 | 457 / 3.339 | 457 / 3.339 |
| Abschließender Composer-Qualitätslauf | bestanden | bestanden | bestanden | bestanden |
| HTTP-/Browser-/Tastatur-/axe-Szenarien | 8 bestanden | nicht ausgeführt | nicht ausgeführt | 8 bestanden |
| Abschließendes ZIP mit Standardtheme | bestanden | nicht ausgeführt | nicht ausgeführt | bestanden |

Die Gesamtsuite wurde nach Ergänzung des Optionsschemas im isolierten MyISAM-Verwaltungstest wiederholt; der ursprüngliche Fehler war ein unvollständiger Testaufbau, kein aufgehobener Wartungsschutz. Die zuletzt ergänzten Archivanzeigen wurden zusätzlich mit 19 Archiv-/Umschaltfällen und 161 Assertions auf allen vier PHP-Versionen geprüft. Nach der letzten Optimierung der Rückwegprüfung bestehen auf allen vier Versionen zusätzlich 13 gezielte Umschalttests / 86 Assertions einschließlich des neuen Batchgrenzenfalls. Der Gesamtlauf oben wird nicht rückwirkend als neuer Lauf mit diesem Stand ausgewiesen.

20 JavaScript-Fälle und ESLint bestanden. Darin sechs neue Fälle für Bestätigungen, Nonce/POST/Identität, gleiche Herkunft, begrenzte Verarbeitung, Stop, fehlerhafte Antworten und fehlende automatische Veröffentlichung. Das zusätzliche Browserszenario prüft Zugriffsschutz, Tastaturbedienung, Bestätigungen und Barrierefreiheit der neuen Archivwerkzeuge.

Abschließende Qualitätsläufe: Syntaxprüfung über 204 PHP-Dateien, PHPStan, PSR-/WordPress-Regeln, PHPCompatibility, zehn Schema-Referenzen und Dokumentationsprüfung über 39 Markdown-Dateien / 23 Shortcodes bestanden. Inventar mit 140 Quellen und 83 Request-Methoden regeneriert. Keine neuen Werkzeugabhängigkeiten erforderlich.

Das abschließende ZIP ist mit zwei getrennten Builds bytegleich: 318 Dateien, keine Entwicklungsabhängigkeiten oder privaten Artefakte; SHA-256 `da0e1c5b25cb3bc91e69236c5f56d71b63e83aab053b89a19025c3fb24f637b8`. Produktionsloader und enthaltene Klassen geprüft; Laufzeitdateien mit den aktuellen Quellen verglichen. Dieses Paket besteht auf PHP 8.2/8.5 Aktivierung, Standardtheme, fünf Seitenrollen, wiederholte Einrichtung, vollständige öffentliche Ausgabe im Unterordner, Deaktivierung, Reaktivierung und datenerhaltende Deinstallation/Neuinstallation.

### Bestandsprobe und Rückweg

Manuelle Probe mit einer vorhandenen Sicherung vom 2026-11-01 auf einer getrennten Übernahmekopie, keine frische Produktionskopie und keine automatisierte Übernahme privater Testfixtures. Kein Mailversand, keine Zahlungen und keine produktiven Writer.

- Neun Fachquellen mit 7.772 Datensätzen; 15 Phase-3-Rennen. Native Tickerübernahme bereits zuvor verifiziert.
- 15 Snapshots mit 29.454 Datensätzen unabhängig verglichen; Namen/Profile, Ergebnisse, Spenden und Tickerdaten erfasst. Keine Punkte-Neuberechnung.
- Für 8.376 Medienverweise eine geprüfte lokale Bildkopie gesichert; 78 Verweise `unavailable`. Verweise sind keine Anzahl unterschiedlicher Bilddateien. Fehlende Medien bleiben ausdrücklich R-03 und werden nicht durch stillen Rückgriff auf veränderliche Originale verdeckt.
- Parallele Fachtabellen vollständig kopiert/geprüft, gemeinsame Umbenennung ausgeführt. Tatsächliche Unterbrechung nach dem Namenswechsel über `recover` fortgesetzt; alle 15 Archivzeiger und Phase 4 sowie gültige Laufzeitgeneration nachgewiesen.
- Rückschaltung vor neuen Datenänderungen ausgeführt. Quellenfingerabdruck nach Rückschaltung exakt gleich dem Ausgangsfingerabdruck; Originaltabellen, alte Punkte und IDs erhalten. Snapshots, kopierte Bilder und vorbereitete Tabellen behalten.

Die große Probe zeigte zwei Laufzeitprobleme: korrelierte Kontenzuordnung per `IN(UNION...)` und wiederholte Sortierung/Offset-Scans bei der Rückwegprüfung. Die Teilnehmermenge wird nun einmal materialisiert; die Fingerabdruckprüfung verwendet qualifizierte Primärschlüssel und exakte Schlüsselfortsetzung in Batches. Zahlen werden ohne Fließkomma-Vergleich fortgesetzt; zusammengesetzte Textschlüssel behalten ihren Zeichensatz und ihre Kollation. Die erfolgreiche Wiederaufnahme/Rückschaltung oben wurde mit dieser Optimierung ausgeführt.

### Offene Abnahme

Gemeinsame fachliche/visuelle Abnahme, Theme-Direktabfragen, fehlende Medien, Anbieter-/Versandfreigaben, frische Produktionskopie und endgültiger Wechselplan bleiben offen. MySQL wird vom aktiven Namenswechsel bis zu einer eigenen DDL-/Wiederherstellungsprüfung abgewiesen. Kein grüner entfernter GitHub-Wiederholungslauf behauptet. Die folgenden datierten Abschnitte dokumentieren frühere Zwischenstände.

## Block 10: Installations- und Übernahmeprüfung – 2026-11-09

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 350 Tests / 678 Assertions | 350 / 678 | 350 / 678 | 350 / 678 |
| WordPress-Gesamtsuite nach Laufzeitänderungen | 437 Tests / 3.174 Assertions | 437 / 3.174 | 437 / 3.174 | 437 / 3.174 |
| Vollständiger Composer-Qualitätslauf | bestanden | bestanden | bestanden | bestanden |
| HTTP-/Browser-/Tastatur-/axe-Szenarien | 7 bestanden | nicht ausgeführt | nicht ausgeführt | 7 bestanden |
| ZIP-Lebenszyklus mit Standardtheme | bestanden | nicht ausgeführt | zusätzlicher Umsetzungslauf bestanden | bestanden |

Sechs neue Integrationsfälle prüfen Entwürfe, wiederholte Anlage, Veröffentlichung und Portal-Fallback, unveränderte Bestandsseiten, Fremd-/Gast-/GET-/Nonce-Verweigerung, falsche Events/Shortcodes, lesende Einrichtung und sichere Textausgabe bei fehlender Eventkonfiguration. Der bestehende Portaltest entfernt im separaten Worker nun absichtlich den geerbten Servernamen; Bootstrap ergänzt ihn aus der geprüften Testadresse. Die vollständigen Suiten bestehen einschließlich dieses Nachweises auf allen vier Versionen.

GitHub: Alle vier Logs des [Laufs 42684774834](https://github.com/Club-of-Roam/cor-mgmt/actions/runs/42684774834) gelesen. Jeweils genau ein fehlgeschlagener Test `CommunicationDeliveryTest::testIndependentPortalRendersParticipantFormAndGuestLogin`: fehlendes `SERVER_NAME` in der CLI-Umgebung, WordPress `general-template.php`. Metadaten-/Qualitätslauf, JavaScript und isolierte Tests hatten bereits bestanden. Fixturekorrektur und Regression sind implementiert; ein grüner entfernter Wiederholungslauf wird ausdrücklich nicht behauptet.

Gesonderte manuelle Bestandsprobe der Sicherung vom 2026-11-01: neun eigene Tabellen / 7.772 Datensätze in 84 Schritten verifiziert, 15 Archivkandidaten vorgemerkt, 16.115 Tickerzeilen kopiert und zweitgeprüft. Ursprüngliche Quelle vor/nach Übernahme unverändert. Dump und Wiederimport der Prüfkopie über 95 Tabellen / 387.187 Zeilen verglichen; sämtliche verglichenen Werte gleich. Keine Anwendungsmigration, keine Aktivierung eines Wechsels in der bestehenden Website, keine Neuberechnung oder geratene Geldkonvertierung. Originale und private Artefakte außerhalb des Repositorys; keine Bestandsdaten als automatisierte Testfixtures übernommen. Dies war keine frische Produktionskopie. Details und aggregierte Befunde in [Block 10](block-10-acceptance.md).

Qualitätslauf: 195 PHP-Dateien syntaktisch geprüft, 38 Markdown-Dateien / 23 Shortcodes / 133 inventarisierte Quellen, neun SQL-Referenzen, PHPStan Level 8, Standards und PHPCompatibility. Acht bisherige Schema-Referenzen, bisherige Tickerassets und Versionsheader zusätzlich bytegleich gegen die Ausgangsreferenz geprüft. 14 Node-Regressionen und ESLint bestanden. Keine Laufzeitänderungen nach den abschließenden Gesamtsuiten; anschließende Änderungen betreffen Dokumentation und den zusätzlichen Prüfwerkzeug-Abschlussnachweis.

Paket: 308 Dateien, produktiver Autoloader ohne Entwicklungsabhängigkeiten. ZIP-Lebenszyklus auf PHP 8.2/8.5 mit frischem WordPress und Twenty Twenty-Five bestanden, einschließlich kompletter Themeausgabe, neuer Anfrage nach Aktivierung, fünf eventbezogener Seitenrollen, Wiederholung, Unterverzeichnis und datenerhaltendem Lebenszyklus. Auf PHP 8.4 zusätzlicher Umsetzungslauf, kein weiterer behaupteter Pflichtmatrixlauf. Sieben vorhandene Browserfälle mit synthetischen Daten auf PHP 8.2/8.5 erneut bestanden; keine vollständige manuelle Bedienungs-/Screenreaderfreigabe. Prüfsoftware wie im Block-9-Nachweis. Der fertige Runtime-Inhalt wird beim abschließenden Dokumentations-/Wiederholungsbuild mit dem geprüften ZIP verglichen.

Keine Git-Aktionen oder produktiven Dienste. Die aktive Fachumschaltung und das vollständige Archiv bleiben ausdrücklich offene Implementierungsarbeit (R-01/R-02); die gemeinsame fachliche/visuelle Abnahme und der tatsächliche Wechsel sind nicht erfolgt.

## Block 9: Integrierter Ticker und Medien – 2026-11-09

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 350 Tests / 678 Assertions | 350 / 678 | 350 / 678 | 350 / 678 |
| Abschließende WordPress-Gesamtsuite | 431 Tests / 3.129 Assertions | 431 / 3.129 | 431 / 3.129 | 431 / 3.129 |
| Vollständiger Composer-Qualitätslauf | bestanden | bestanden | bestanden | bestanden |
| HTTP-/Browser-/Tastatur-/axe-Szenarien | 7 bestanden | nicht ausgeführt | nicht ausgeführt | 7 bestanden |
| Multipart-Bildfluss nach abschließender Pfadprüfung | erneut bestanden | nicht ausgeführt | nicht ausgeführt | erneut bestanden |

Prüfsoftware: WordPress 7.1.4, MariaDB 11.4.13, PHPUnit 11.5.57, Node.js 24.22.2, Playwright Test 1.65.0, @axe-core/playwright 4.14.0 und Microsoft Edge 157.0.4341.42. Alle 14 Node-Regressionen und ESLint bestanden. Composer prüft 192 PHP-Dateien, Dokumentation/Inventar mit 37 Markdown-Dateien und 23 Shortcodes, neun SQL-Referenzen, PHPStan Level 8, Standards und PHPCompatibility. Die acht bisherigen SQL-Referenzen sind zusätzlich gegen die private Ausgangsreferenz bytegleich geprüft.

Die vollständigen Fach-, WordPress- und Composer-Läufe wurden nach den letzten Laufzeitänderungen auf allen vier PHP-Versionen ausgeführt. Die WordPress-Suite enthält 19 Tickerfälle einschließlich getrennt registrierter Legacy-/Modulassets, Kodierungsprüfung und abgewiesener externer Bildpfade. Wiederholungsläufe werden nicht zu einer zusätzlichen Gesamtsuitenzahl addiert. Kein entfernter GitHub-Lauf ausgeführt.

Migration: eigenständige additive Einrichtung ohne cor-ticker, Wiederholung, Original-IDs, Kommentare und bytegetreue Rohdaten einschließlich Null-/Binärspalten. Explizit geprüft: alte UTF-32-Variante mit `from`, Umlaute und unbekannte Zusatzfelder; nicht geprüfte Kodierung stoppt ohne Quellenänderung. Eine nicht transaktionale Fortschrittstabelle wird abgewiesen. Übernahme von 101 Zeilen in begrenzten Paketen mit zweiter Prüfung, Wiederaufnahme, geänderte Quelle trotz unveränderter Anzahl und beschädigte Zielprojektion trotz intaktem Originalpayload. Neue IDs liegen oberhalb der übernommenen IDs. Historische Tabellen und Dateien bleiben unverändert bestehen.

Writer und Rechte: neue Texte, Kommentare, Videos, Standorte und Bilder mit konkretem Event, Objektberechtigung und Anfragebindung. Fremde Events/Eltern/Attachments, gefälschte Nonces, veraltete Moderation und mehrdeutige Mitgliedschaft werden abgewiesen. Derselbe Befehl erhält kein zweites Ergebnis; veränderte Wiederholung wird abgelehnt. Zwei echte gleichzeitige Workerprozesse erzeugen zusammen genau einen Beitrag und einen Journaleintrag. Eingabe und Anzeige sind unabhängig; Archive blockieren Änderungen bei weiterhin erlaubter administrativer Einsicht. Rendern verarbeitet keine übermittelten Befehle. Fehlende Eventtexte und unbekannte Events erzeugen keine Veraltungswarnungen.

Medien: echte multipart-Übertragung über HTTP, Eigentum/Eventbindung und neue Drehdatei statt Überschreiben. Originalpfad/-hash bleiben erhalten. Historische ausführbare Dateien, `.php.png`, SVG und als PNG getarnter Code werden ohne Bildanforderung zurückgewiesen; auch gefälschte Attachment-Metadaten öffnen diesen Pfad nicht. Originale bleiben im synthetischen Prüffall bis zur ausdrücklich begrenzten Fixturebereinigung bestehen. Ehemals gültig signierte Gatewayanfragen erhalten jetzt ausschließlich 410 ohne Schreibwirkung; alte Positivtests wurden bewusst durch den Stilllegungsvertrag ersetzt. Die drei gesonderten cor-ticker-Dateien sind syntaktisch und über ihren 410-Ausgang geprüft. Quellsuche in Theme-/Upload-PHP fand keine weiteren literalen Verweise auf die untersuchten Ticker-Schreibwege.

Browser: Schreiben und Kommentieren, Tastaturbedienung, Nonceverweigerung, vollständiger Bildfluss mit Erhalt der Original-URL und moderiertes Ausblenden. Tickerverwaltung verweigert Teilnehmern HTTP 403. Standortabfrage nur nach Klick, Video-/Kartenfreigabe ohne vorherige Fremdanfragen. Zwei Teams auf getrennten Routen, Markeröffnung per Tastatur, Teamverlauf und Umschalten der Routenebenen sind geprüft. Kartenkacheln wurden synthetisch beantwortet, andere externe Browserverbindungen blockiert. axe prüfte WCAG-2.1-A/AA-Tags; das ist keine vollständige manuelle Barrierefreiheitsfreigabe und kein Nachweis echter YouTube-/OSM-Verfügbarkeit. Kein echter Mail-, Zahlungs- oder Gatewayversand.

Paketgrenzen und mitgelieferter Produktionsautoloader mit 306 Dateien unter PHP 8.2 und 8.5 geprüft; keine Entwicklungsabhängigkeiten im ZIP. Abschließende Wiederholungsbuilds werden auf Bytegleichheit geprüft. Die sieben Browserfälle wurden nach sämtlichen Laufzeitänderungen erneut auf beiden Versionen bestanden, ebenso 14 Node-Regressionen und ESLint. Aktuelle Umsetzung und separate Nachbaränderungen: [Block 9](block-9-ticker.md). Keine Anwendungsmigration, keine Aktivierung eines Bestandswechsels und keine Git-Aktionen. Fachliche, visuelle, Bestands-, Übersetzungs-, Leistungs- und Releaseabnahme gemeinsam in Block 10.

## Block 8: Rundmails, Börse und Browsernachweis – 2026-11-08

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 327 Tests / 637 Assertions | 327 / 637 | 327 / 637 | 327 / 637 |
| WordPress-Gesamtsuite dieses Pakets | nicht vollständig wiederholt | 407 Tests / 2.734 Assertions | nicht vollständig wiederholt | 407 / 2.734 |
| Betroffene Kommunikations-/Queue-/Vorlagen-/Legacy-/Sicherheits-/Einrichtungsfälle | 228 Tests / 1.618 Assertions | in der Gesamtsuite | 228 / 1.618 | in der Gesamtsuite |
| Abschließende Rundmail-/Börsen-/Stapelsuite | 31 Tests / 277 Assertions | 31 / 277 | 31 / 277 | 31 / 277 |
| Vollständiger Composer-Qualitätslauf | bestanden | bestanden | bestanden | bestanden |
| Abschließende HTTP-/Browser-/Tastatur-/axe-Szenarien | 4 bestanden | nicht ausgeführt | 4 für diese PHP-Version nicht ausgeführt | 4 bestanden |

Prüfsoftware: WordPress 7.1.4, MariaDB 11.4.13, PHPUnit 11.5.57, Node.js 24.22.2, Playwright Test 1.65.0 und @axe-core/playwright 4.14.0; Browser Microsoft Edge 157.0.4341.42. JavaScript: sämtliche 14 Node-Regressionen und ESLint für Produktivskripte sowie die eingeführten Browser-/Stapeltreibern bestanden.

Zeitliche Grenzen: Die vollständigen WordPress-Läufe und die 228er-Auswahl lagen vor den letzten Vorprüfungs-/Formularregressionen und dem stoppbaren Stapellauf. Anschließend sämtliche 31 betroffenen Integrationsfälle mit je 277 Assertions auf allen vier PHP-Versionen wiederholt. Die vollständigen Composer-Läufe umfassen 184 Syntaxprüfungen, Dokumentation/Inventar, acht SQL-Referenzen, PHPStan Level 8, Standards und PHPCompatibility; der letzte Stapel-/Zuordnungsnachlauf wurde zusätzlich mit PHPStan (PHP-8.2-Ziel), betroffenen Standards und PHPCompatibility geprüft. Browserfälle wurden danach über echte HTTP-Requests erneut unter PHP 8.2/8.5 ausgeführt. Wiederholungssuiten nicht addieren und daraus keine neue Gesamtsuitenzahl berechnen.

Integrationsnachweise: getrennte konkrete Event-/Teamgruppen, eigene und explizite Einzeladresse sowie deduplizierte bisherige Teilnehmer. Sprachfilter und eindeutige Mitgliedersprache, keine erfundene Personalisierung, fehlende/leere gerenderte Inhalte, widersprüchliche gemeinsame Adressen und Empfängeränderung nach Vorschau. Unveränderliche Empfänger-/Text-/Absenderhüllen, wiederholungsfester Auftrag und Konflikt bei abweichendem Fingerabdruck. Queue-Schritte maximal 100 und Versand-Schritte maximal 5; fehlerhafte zweite Hülle rollt den gesamten Schritt zurück. Server-/Kampagnenblockade und verlorene Rechte verhindern Transport; fremde oder beschädigte Queue-/Hüllenverknüpfungen werden abgewiesen. Fehlgeschlagene und unklare Ausgänge verlangen ausdrückliche Nachbereitung. Stapelfortschritt, frische Revisionen, noncegeschützter privater AJAX-Hook und fehlende Versandbestätigung sind geprüft. Transportannahme ist synthetisch, außerhalb der Transaktion und keine echte Zustellung.

Börse: eigene CRUD-Aktionen mit realem SQL, fremde IDs, ausdrückliche Moderation, Revisionskonflikte und Wiederholung alter Leerformulare nach Anlegen/Löschen. Öffentlicher Jahres-/Teilnahmefilter, globale Bestandshaltung, Escaping, maximale Textlänge und native Formulare. InnoDB-Voraussetzung verweigert Writer ohne automatische Engineänderung. Der im Browser gefundene Unterschied zwischen Revision der eigenen Formularansicht und konkreter Eintrags-ID ist korrigiert und durch einen eigenen Integrationsfall gesichert. Alte direkte Rundmailtests verlangen jetzt ausdrücklich die Verweigerung des unmittelbaren Altwegs; neue positive Kampagnenszenarien ersetzen den bisherigen Umgehungspfad.

Browser: Vorschau, Auftrag, echter AJAX-Queue-Stapelschritt mit Fortschritt, Freigabe/Pause ohne SMTP, Rechteverweigerung, Börseneintrag per Tastatur, Bearbeiten/Löschen und verweigertes altes Formular. Administrative Bearbeitung/Löschung mit sichtbarem Journal sowie lesender Speicherbericht. axe-Prüfungen für WCAG 2.1 A/AA in den geprüften Ansichten ohne gemeldete Regelverletzungen. Zusätzliche Node-Fälle prüfen Stop nach dem laufenden Request ohne Abbruch, frische Revision/Nonce, bestätigten Start, Fehler ohne Retry sowie gleiche Herkunft und verweigerte Weiterleitungen.

Browserfixture verwendet synthetische Testidentitäten und native Seitenrenderer; sie ist kein Nachweis der echten WordPress-Anmeldung, des vollständigen Adminrahmens oder der endgültigen Theme-Darstellung. Keine gesonderten Firefox-/WebKit-/Screenreader-/MySQL-Läufe. Manuelle Tastatur-/Inhalts-/Bestandsabnahme, echter SMTP-/Anbietertest und Aufbewahrungsentscheidung bleiben offen. CI um Chromium-Browserläufe unter PHP 8.2/8.5 ergänzt; kein entfernter CI-Lauf ausgeführt.

Inventar: 127 Quellen, 23 Shortcodes, 78 Methoden mit literalen Request-Feldern; 35 Markdown-Dateien. Pluginheader, composer.lock und die sechs bisherigen SQL-Pläne bleiben bytegleich. Neue Browserwerkzeuge ausschließlich als festgeschriebene Entwicklungsabhängigkeiten; package-lock.json aktualisiert. Kein Anwendungsschema umgeschaltet, keine echten Mails oder externen Schreibzugriffe, keine Git-Aktionen. [Umsetzung und notwendige Mitwirkung](block-8-completion.md).

Paketabschluss: zwei bytegleiche ZIP-Builds mit 295 Dateien; Paketgrenzen und sämtliche gebündelten Produktionstypen unter PHP 8.2/8.5 geprüft. Keine Testdaten, Testserver, Browserwerkzeuge oder Entwicklungsabhängigkeiten im ZIP.

## Block 8: Teamauslöser und Queueverwaltung – 2026-11-08

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 319 Tests / 628 Assertions | 319 / 628 | 319 / 628 | 319 / 628 |
| WordPress-Gesamtsuite | 381 Tests / 2.511 Assertions | 381 / 2.512 | 381 / 2.511 | 381 / 2.512 |
| Abschließender Auslöser-/Verwaltungsnachlauf | 21 Tests / 235 Assertions | 21 / 235 | 21 / 235 | 21 / 235 |
| Composer check | bestanden | bestanden | bestanden | bestanden |

Zeitliche Grenze: Die vollständigen 8.2-/8.4-Läufe lagen vor der letzten Kompatibilitätsassertion zum bisherigen Einladungsplatzhalter name. 8.3/8.5 enthielten diese Assertion bereits. Nach ausdrücklicher UTF-8-Konvertierung beim Lesen der JSON-Ereignisreferenz bestanden alle 21 Auslöser-/Verwaltungsfälle mit je 235 Assertions erneut auf allen vier Versionen; betroffene Standards ebenfalls. PHPStan Level 8 abschließend erneut bestanden. Wiederholungssuiten nicht zur Gesamtzahl addieren.

Echte WordPress-/wpdb-Nachweise: Gründung, Einladung, Annahme und Freigabe erzeugen passende Eventvorlagen, konkrete Empfänger, Mitgliedersprachen, Namen und Eventlinks in derselben Fachtransaktion. Dasselbe Konto in zwei Events bleibt getrennt. Wiederholung ohne Statuswechsel, alte notify-Aufrufe und Replay desselben Bestätigungsformulars erzeugen keine Duplikate. Fehlende Vorlagen erzeugen blockierte Nachrichten ohne Globalfallback. Ein Einrichtungsfehler rollt Team und Profil zurück; ein Fehler in der zweiten Nachricht rollt auch die erste Nachricht, Einladung und das Ereignisjournal zurück. Historischer kontextfreier auto_response-Einstieg sendet nicht mehr; der entsprechende frühere Ist-Test wurde ausdrücklich auf den neuen Vertrag umgestellt. Neue positive Event-/Sprach-/Platzhalterfälle ersetzen dessen frühere Globalfallback-Erwartung.

Verwaltung: Objekt-/Event-/Nachrichtengrenzen, POST, Nonce, ausdrückliche Versandbestätigung, fehlende Rechte, geänderte Revision, Aus-/Einschaltzyklus und Replay geprüft. Unabhängige Teamereignisse blockieren keine Vorlagenbearbeitung. Vorschau escaped, Server- und Eventblockade ohne Transportaufruf, nur gezielt geprüfte Nachricht statt nächstem beliebigen Queueeintrag. Blockierte Nachrichten nur ausdrücklich nachbereitet, feste Snapshots unverändert; Fehler-Retry auf drei Versuche begrenzt. Unklare Ausgänge ohne Retry und mit protokollierter externer Klärung; Abschluss ohne weiteren Versand und Archivschutz geprüft. Verbrauchte Einladungen und geschlossene Anmeldung verhindern Versand; gültige Einladung bleibt nach Versand annehmbar.

Zwei gleichzeitig gestartete echte Workerprozesse erzeugten für denselben geprüften Vorgang genau eine synthetische Transportannahme und einen Versuch. Vorhandene parallele Platz-/Beitrittstests prüfen zusätzlich die atomare Nachrichtenerzeugung nur für den Gewinner. Getrennte WordPress-Prozesse rufen die registrierten Teilnehmer-/Gasthooks auf und prüfen Teamformular bzw. Anmelderückleitung. Der Transport ist injiziert und schreibt ausschließlich eine synthetische Quittung; keine echte Nachricht. Providerannahme ist keine Zusage über Zustellung ins Postfach.

Qualitätsumfang: 172 PHP-Dateien ohne Syntaxfehler, 34 Markdown-Dateien, 118 Inventarquellen / 23 Shortcodes / 75 Methoden mit literalen Request-Feldern, sechs unveränderte SQL-Referenzen. Strikte Composer-Validierung, PHPStan Level 8, eingeführte Standards und PHPCompatibility sowie 7 Node-Tests und ESLint bestanden. Anwendungsheader, Lockdateien, Abhängigkeiten und sämtliche bestehenden Schema-/Installationspläne sind unverändert. Keine Anwendungsmigration, kein Remote-CI-Lauf und keine Git-Aktionen.

Paketabschluss: zwei bytegleiche Builds mit 282 Dateien. Paketgrenzen und gebündelter Produktionsautoloader ohne Entwicklungsabhängigkeiten unter PHP 8.2 und 8.5 geprüft.

Grenzen: MariaDB-Integration, kein gesonderter MySQL-Lauf. Kein SMTP-/Anbieterversand oder Produktionsbestandsabgleich. Rendering in WordPress-Subprozessen ersetzt keine HTTP-/Browser-/Tastatur-/Accessibility-Abnahme. Kein Hintergrundversand; Serverfreigabe wird nicht gesetzt. Manueller Rundmailversand, Börse, Aufbewahrungsverfahren und Release-/Gesamtabnahme bleiben Folgearbeit. Der aktuelle Vertrag steht in [Mailauslöser und Queueverwaltung](block-8-delivery.md).


## Block 8: Queue-Grundlage – 2026-11-08

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Gesamtsuite | 319 Tests / 628 Assertions | 319 / 628 | 319 / 628 | 319 / 628 |
| WordPress-Gesamtsuite | 359 Tests / 2.273 Assertions | 360 / 2.278 | 359 / 2.273 | 360 / 2.278 |
| Abschließender Queue-/Einrichtungsnachlauf | 53 Tests / 281 Assertions | in Gesamtsuite enthalten | 53 / 281 | in Gesamtsuite enthalten |

Zeitliche Grenze: Nach den Gesamtläufen für PHP 8.2/8.4 kam der Regressionstest für einen zweiten Adapter derselben gesperrten Verbindung hinzu. Die betroffenen Queue-/Einrichtungsfälle bestanden danach auf beiden Versionen vollständig erneut. PHP 8.3/8.5 liefen bereits mit diesem abschließenden Stand. Wiederholungssuiten nicht zur Gesamtzahl addieren.

Neue Nachweise mit echtem WordPress/wpdb und synthetischen Datensätzen: additive wiederholbare Installation, Erhalt vorhandener Nachrichten, Namenskollision, beschädigtes Manifest bzw. fehlender eindeutiger Nachrichtenschlüssel, POST-/Nonce-/Adminschutz der Einrichtung und kein Schedulerstart. Vorgangsidentität je Event/Aktion/Empfänger/Sprache, kanonische Eingaben, unveränderliche Nachrichtensnapshots trotz Vorlagen-/Aktivwechsel, abweichende Wiederholung als Konflikt, fehlende/deaktivierte Vorlagen ohne Fremdfallback und ohne spätere automatische Reaktivierung. Fachänderung und Queueeintrag rollen gemeinsam zurück; unvollständiges Rendering hinterlässt keinen Eintrag.

Anspruch und Besitz wurden mit zwei echten Datenbankverbindungen geprüft: besetzte Sperre und bereits beanspruchte Nachricht verhindern eine zweite Übernahme. Falsches Token verhindert Abschluss. Der injizierte Transport läuft nach Commit und ohne gehaltene Sperre; äußere Transaktionen und eine durch einen zweiten Adapter verdeckte Verbindungssperre verhindern Versand. Ablehnung verlangt ausdrücklichen Retry mit maximal drei Versuchen. Ausnahme und abgelaufener Anspruch werden unklar, niemals automatisch erneut gesendet. Archive verhindern neue Nachrichten/Ansprüche; bereits laufende Ergebnisse bleiben protokollierbar. Kein Anbietertext oder Nachrichteninhalt im Statusjournal.

Abschließende Composer-Prüfungen unter PHP 8.2/8.3/8.4/8.5 bestanden: strikte Composer-Validierung, Syntax für 166 PHP-Dateien, Dokumentations-/Inventarprüfung, sechs SQL-Referenzen, PHPStan Level 8, eingeführte Standards und PHPCompatibility. Inventar: 114 Quellen / 23 Shortcodes / 73 Methoden mit literalen Request-Feldern; 33 Markdown-Dateien. 7 Node-Tests und ESLint bestanden. Zwei bytegleiche Paketbuilds und Paketgrenzen/Produktionsloader unter PHP 8.2 und 8.5 geprüft. Kein Remote-CI-Lauf oder Git-Vorgang.

Grenzen: Die Queue wird noch von keinem bisherigen Mailauslöser befüllt. Keine End-to-End-Abnahme der Teilnahmebenachrichtigungen, keine registrierte produktive Verarbeitung, keine neue Queue-Verwaltungsoberfläche, keine parallelen Workerprozesse oder echten Mailzustellungen geprüft. Zwei Verbindungen sind kein Nachweis des vollständigen Produzenten-/Workerpfads. Manueller Versand, Absendervertrag, Börse und Browser-/Tastatur-/Accessibility-Abnahme bleiben offen. Keine Anwendungsmigration oder produktiven Zugriffe. Pluginheader, Lockdateien, bisherige fünf SQL-Pläne und Mailauslöser bleiben unverändert.


## Block 8: Vorlagenbasis – 2026-11-08

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Suite, abschließend | 309 Tests / 615 Assertions | 309 / 615 | 309 / 615 | 309 / 615 |
| WordPress-Gesamtsuite | 337 Tests / 2.099 Assertions | 337 / 2.099 | 337 / 2.099 | 337 / 2.099 |
| Abschließende Konfigurationssuite | 18 Tests / 138 Assertions | 18 / 138 | 18 / 138 | 18 / 138 |
| composer check | bestanden | bestanden | bestanden | bestanden |

Nach Ergänzung mehrteiliger Sprachschlüssel wie de_DE_formal wurde die isolierte Gesamtsuite auf allen vier Versionen erneut ausgeführt; die zusätzliche Assertion ist in der Tabelle enthalten. Nach dem zusätzlichen Setup-Hinweis für nicht eingerichtete Installationen bestanden sämtliche 18 Konfigurationsfälle auf allen vier Versionen erneut; PHPStan und betroffene Standards wurden ebenfalls erneut geprüft. Keine Wiederholungssuiten zur Gesamtzahl addieren. Vorlagenkonfiguration und Renderer sind getestet, keine neue Versandverarbeitung.

Neue Nachweise: getrennte Eventvorlagen und Aktivierung trotz Wechsel des aktiven Rennens, fehlende Vorlage ohne Fremd-/Global-/Sprachfallback, Sprachalias-Normalisierung, alle 14 vorhandenen Aktionsnamen und Platzhalter, nicht rekursive Ersetzung, fehlende Werte und Header-Injection. Kontext-/Revisionsschutz, Autorisierung pro Event, Archivschutz, Vorschau und unveränderte globale Originale, unabhängige Kopien, Erhalt individuell bearbeiteter Ziele, Wiederholung ohne Duplikate, doppelte/ungültige/unvollständige Quellen, Änderungen nach Vorschau und Journal-Rollback sind mit echtem WordPress/wpdb geprüft. Leseoperationen schreiben nicht, fehlendes Modul wird nicht bei Anzeige angelegt. Native Formulare prüfen POST/Nonce/Eventrechte und escaped Textausgabe.

Additive Installation ist wiederholbar, übernimmt keine fremden Tabellen und erkennt beschädigte fertige Manifeste bzw. den veränderten Event-/Aktions-/Sprachschlüssel. Systemstatus-Aktion lehnt GET, fehlende Rechte und ungültige Nonces ab. Syntaxprüfung: 159 PHP-Dateien. Inventar: 110 Quellen / 23 Shortcodes / 73 Methoden mit literalen Request-Feldern. Dokumentation: 32 Markdown-Dateien. PHPStan Level 8, eingeführte Standards-/Kompatibilitätsprüfungen, fünf SQL-Referenzen, 7 Node-Tests und ESLint bestanden; Altbestandsgrenzen der Werkzeuge gelten weiter.

Zwei Paketbuilds ergaben ein bytegleiches ZIP mit 271 Dateien; Paketgrenzen und Produktionsloader ohne Entwicklungsabhängigkeiten bestanden unter PHP 8.2 und 8.5.

Pluginheader, Lockdateien und alle vier vorherigen SQL-Pläne bleiben unverändert. Alter Mailer, Teilnahme-Benachrichtigungsbrücke und Börse sind unverändert; neue Eventvorlagen haben ausdrücklich noch keine Versandwirkung. Kein neuer Zahlungsweg, keine Anwendungsmigration und keine echten Mails/API-Aufrufe. Kein Browser-/Accessibility-/Worker-/Versandnachweis. Nächster Schritt: dauerhafte Fachereignisse und atomare Outbox, anschließend Auslöser-/Versandumschaltung. [Umsetzung und Grenzen](block-8-templates.md). Keine Git-Aktionen.


## Block 7: Verwaltung, Ganz-Euro-Anzeige und Altprüfung – 2026-11-05

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Suite | 296 Tests / 585 Assertions | 296 / 585 | 296 / 585 | 296 / 585 |
| WordPress-Gesamtsuite | 309 Tests / 1.932 Assertions | 309 / 1.932 | 311 / 1.940 | 311 / 1.940 |
| Abschließende Spendenfälle | 30 Tests / 185 Assertions | 30 / 185 | 30 / 185 | 30 / 185 |
| composer check | bestanden | bestanden | bestanden | bestanden |

Die Gesamtsuiten unter 8.2/8.3 liefen vor den zwei ergänzten Fällen für administrative Ausblendung und Owner-URL-Schutz. Danach bestanden auf allen Versionen sämtliche 30 Spendenfälle erneut, einschließlich der zuletzt ergänzten Prüfung der eigenen Routenliste. Wiederholungsläufe überschneiden sich und werden nicht addiert. Die aktuelle Ganz-Euro-Vorgabe ersetzt die frühere öffentliche Centanzeige; interne Werte und Verwaltungsanzeige bleiben exakt.

Neue Regressionen: berechtigte/unberechtigte und eigene/fremde Routenbearbeitung, Nonce-Schutz, erlaubte Metadaten und unveränderliche Finanzfelder, veraltete Revisionen, vollständiger Sammelrollback bei unbestätigter Veröffentlichung oder fremder Zuständigkeit, Journalfehler-Rollback, identische Wiederholung, Archivsperre, administrativ versteckte Profile bleiben auch nach Spenderpflege versteckt, keine fremde Bild-URL/ausführbare Website. Historische Vorprüfung ist ausdrücklich, wiederholbar und unverändert hinsichtlich Ursprungszeile und Sitzungsrechten; Orphans, bestätigte Zeilen und Konfigurationsdrift werden abgewiesen. Der Prüfplan enthält keine alten Codes/Cookies und löst kein Anbieter-HTTP aus.

Exakte interne Summen und Offsets, öffentliche Ganz-Euro-Beträge, unveränderte Lastschrift-Zählkonvention, bereitgestellter Rennzähler und keine Wiederaufnahme ausgefilterter Zeilen durch Zusatzspalten sind geprüft. Der öffentliche Spendenzähler schreibt keine Optionswerte mehr und zeigt auch ohne JavaScript einen Betrag. PHPStan Level 8, eingeführte PHPCS-/Kompatibilitätsprüfungen, vier unveränderte SQL-Referenzen und Syntaxprüfung (151 Dateien) bestanden. Inventar: 105 Quellen / 23 Shortcodes / 69 Methoden mit literalen Request-Feldern. Dokumentation: 30 Markdown-Dateien. 7 Node-Tests und ESLint bestanden. Altbestandsgrenzen der Werkzeuge gelten weiter.

Zwei aufeinanderfolgende Builds erzeugten ein bytegleiches ZIP mit 263 Dateien. Paketgrenzen und Produktionsloader ohne Entwicklungsabhängigkeiten bestanden unter PHP 8.2 und 8.5.

Keine neuen Schema-/Versionsmarker oder Laufzeitabhängigkeiten. Keine Anwendungsdaten migriert, keine echten Zahlungs-/Mail-/API-Aufrufe und keine Git-Aktionen. Kein Browser-/Tastatur-/Accessibility- oder Anbieter-/Bestandsnachweis daraus abzuleiten. Tatsächliche Übernahme alter Zahlungen bleibt bis zum Nachweis des ursprünglichen Vertrags gesperrt. [Umfang und Grenzen](block-7-administration.md).


## Block 7: Betterplace-Grundlage – 2026-11-05

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Suite | 295 Tests / 578 Assertions | 295 / 578 | 295 / 578 | 295 / 578 |
| WordPress-Gesamtsuite | 297 Tests / 1.843 Assertions | 297 / 1.843 | 297 / 1.843 | 297 / 1.843 |
| Abschließender Spenden-/Hinweistextlauf | 17 Tests / 92 Assertions | 17 / 92 | 17 / 92 | 17 / 92 |
| composer check | bestanden | bestanden | bestanden | bestanden |

Die vollständigen Suiten bestanden auf allen vier Versionen. Danach wurde der Schutz verwalteter Spenden bei gleichzeitig übergebener Einzel-ID und Bulk-Liste verschärft; die betroffenen 17 Fälle wurden auf allen vier Versionen erneut erfolgreich ausgeführt. Wiederholungsläufe werden nicht zur Gesamtzahl addiert. PHPStan Level 8, eingeführte PHPCS-/Kompatibilitätsregeln, vier SQL-Referenzen, Syntaxprüfung (148 Dateien) und Dokumentationsinventar (102 Quellen, 23 Shortcodes, 66 Methoden mit literalen Request-Feldern) bestanden. 7 Node-Tests und ESLint bestanden. Beschränkungen der bestehenden statischen Altbestandsprüfung gelten weiter.

Neue Nachweise: exakte Dezimal-/Centbeträge und Überlaufgrenzen; zulässige HTTPS-Veranstaltungen; begrenzte paginierte Anbieterantworten; exakte Codes, verborgene Beträge, ungültige Antwortdaten, Sortierreihenfolge, mehrdeutige Treffer, Zeitgrenzen und Transportfehler. WordPress-Prüfungen belegen Sitzungs-/Rennbindung, idempotente Bestätigung, Profile erst nach Zuordnung, Owner-Verfügbarkeit und Sponsor-Rückfall, Schreibsperre im Archiv, geänderte Veranstaltung während HTTP, Journal-Rollback, erhaltene historische Zeilen und geschützte Admin-Aktionen. Routenabfrage und jüngste Spenden zeigen neue Centanteile; deklarierter Sortierschlüssel vermeidet eine PHP-8.2-Veraltungswarnung. Deaktivierte Spenden behalten ihren konfigurierten Hinweistext.

DonationConcurrencyTest führt zwei echte PHP-Prozesse bis zur Anbieterbarriere: ein öffentlicher Nachweis mit beiden Codes führt zu genau einer Zuordnung und einem Bestätigungsjournal. Installationstests prüfen wiederholte additive Einrichtung, Datenerhalt, fremde Tabellenkollision, Wiederaufnahme und beschädigte fertige Manifeste einschließlich Anbieter-Unique-Index. Geschützte Systemstatus-Aktionen prüfen POST, Nonce, Rechte und nur lesenden aggregierten Prüfbericht.

Zwei aufeinanderfolgende Builds erzeugten bytegleiches ZIP mit 259 Dateien. Paketgrenzen und vollständiger Produktionsloader ohne Entwicklungsabhängigkeiten wurden unter PHP 8.2 und 8.5 erfolgreich geprüft. Dies ersetzt keine vollständige Browser- oder Anbieterabnahme.

Alle Antworten und Daten sind synthetisch; keine echten Mails, Zahlungen oder Anbieterabfragen. Die guarded WordPress-Testfixture verwendet für ihre synthetischen Passwörter eine niedrige bcrypt-Kostenstufe; keine Änderung an Anwendungspasswörtern. Pluginheader, Legacy-/Storage-Pläne und Lockdateien bleiben unverändert. Keine Anwendungsmigration oder Git-Aktionen. Browser-, Anbieter-, Bestands-, kontrollierte Admin-Korrektur- und Archivabnahme bleiben offen; [Block 7](block-7-donations.md) beschreibt die Grenzen. Die weitergehende Automatisierung ist zur Prüfung vorgemerkt und nicht umgesetzt.


## Block 6: Ergebnisse und Wertung – 2026-11-05

| Prüfung | PHP 8.2.35 | PHP 8.3.36 | PHP 8.4.27 | PHP 8.5.12 |
|---|---|---|---|---|
| Isolierte Suite | 259 Tests / 529 Assertions | 259 / 529 | 259 / 529 | 259 / 529 |
| WordPress-Gesamtsuite | 266 Tests / 1.692 Assertions | 266 / 1.692 | 266 / 1.692 | 266 / 1.692 |
| Ergänzter Etappen-Grenztest | 1 Test / 4 Assertions | 1 / 4 | 1 / 4 | 1 / 4 |
| Rennen/Teilnahme/Ergebnisse und parallele Writer erneut | 110 Tests / 767 Assertions | 110 / 767 | 110 / 767 | 110 / 767 |
| Abschließende Ergebnis-/Teilnahmesuiten | 65 Tests / 543 Assertions | 65 / 543 | 65 / 543 | 65 / 543 |
| composer check | bestanden | bestanden | bestanden | bestanden |

Die Gesamtsuite wurde vor der abschließenden Anpassung der Etappenobergrenze ausgeführt. Nach der Korrektur entsprechend dem bestehenden tinyint-Feld wurde der zusätzliche Test für 255/256 auf allen vier Versionen separat erfolgreich ausgeführt. Danach wurden die betroffenen Rennen-/Teilnahme-/Ergebnis-Suiten einschließlich beider Prozess-Konkurrenztests nach der Freigaberückstufungs-Ergänzung erneut erfolgreich auf allen vier Versionen ausgeführt (110 Tests / 767 Assertions). Nach Ergänzung der Löschsperre für normalisierte Null-Punkte-Ergebnisse bestanden die Ergebnis-/Teilnahmesuiten nochmals auf allen vier Versionen (65 Tests / 543 Assertions). Die Wiederholungssuiten überschneiden sich und werden nicht zu einer Gesamtzahl addiert.

Neue Nachweise: unveränderte Platzpunkte; Zusatzpunkte ohne Platzierung/bei Disqualifikation entsprechend der inzwischen fachlich bestätigten Regel D-38; unabhängiger fester Bonus; variable Werte 1 und 7 bleiben bei weiteren Etappeneingaben erhalten; siebte Etappe ohne erfundene Legacy-Spalten; Gleichstände, hohe Summen und exakte Top-Grenzen; Rechte, Nonce, Phase, Team-/Etappenzuordnung; veraltete Revisionen einschließlich Änderung/Rückkehr; identische Wiederholung ohne neues Journal. Erzwungene Fehler beim zweiten Ergebnis oder nach sämtlichen Ergebniswrites vor dem Journal rollen Ergebnisse und Legacy-Projektion gemeinsam zurück. Bestehende Ergebniswerte verhindern eine stille Aktivierung, Umhängung oder Löschung der gewerteten Struktur. Erlaubte Phasenrückwechsel dürfen Teams zurückstufen und Routenplätze freigeben; Ergebniszeilen und Summen bleiben erhalten, die bisherige Route kann später wieder zugewiesen werden.

ResultConcurrencyTest startet zwei echte PHP-Prozesse mit derselben Revision. Genau ein Stand gewinnt; zwei Ergebniszeilen und genau ein Journal bleiben zusammen erhalten. ResultsInstallationTest prüft wiederholbare additive Einrichtung, fremde Tabellen, laufendes/abgeschlossenes Manifest, fehlende Tabelle und MyISAM-/Planabweichung. HistoricalComparison ist als schreibfreie arithmetische Diagnose geprüft; unklare Zuordnungen liefern keinen geratenen Kandidaten. Charakterisierung des alten TypeError-Writers liegt jetzt nur in der nicht ausgelieferten Testreferenz.

7 Node-Tests und ESLint bestanden. Syntaxprüfung umfasst 134 PHP-Dateien; Inventar 93 PHP-Quellen / 23 Shortcodes / 69 Methoden mit literalen Request-Feldern. Dokumentationsprüfung: 28 Markdown-Dateien, 0 Fehler. Pluginheader, Lockdateien und beide bisherigen SQL-Referenzen unverändert. Keine Anwendungsmigration, keine Bestandsneuberechnung, keine produktiven Schnittstellen und keine Git-Aktionen. Aktueller Umsetzungsumfang und verbleibende Bestands-/Archiv-/Browserabnahme: [Block 6](block-6-results.md).

Paketgrenzen und Produktionsloader mit 248 Dateien auf PHP 8.2/8.5 geprüft. Entwicklungswerkzeuge, Testreferenzen und private SQL-Dateien werden nicht ausgeliefert. Abschließender Paketbau zweimal mit identischem SHA256; Produktionsloader und Paketgrenzen erneut unter PHP 8.2/8.5 geprüft.


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
