# Block 10: Gesamtabnahme und Releasevorbereitung

Stand 2026-11-11. Technische Teilnachweise vorhanden; gemeinsame fachliche und visuelle Abnahme gemäß D-51 begonnen. Noch keine abschließende Benutzerfreigabe, kein Releasekandidat und kein Produktionswechsel.

## Bereits ausgeführte technische Arbeit

- Fehler im GitHub-Lauf [42684774834](https://github.com/Club-of-Roam/cor-mgmt/actions/runs/42684774834) auf allen vier PHP-Versionen identifiziert: Der CLI-Portalprozess hatte keinen `SERVER_NAME`; WordPress erzeugte in `wp_head()` eine Warnung. Die synthetische Umgebung setzt den Servernamen jetzt aus der geprüften Testadresse. Der Portalworker entfernt zuvor einen geerbten Wert, damit der Fehler auch auf anderen Betriebssystemen reproduzierbar bleibt. Keine Warnung unterdrückt und kein WordPress-Core geändert. Entfernter Wiederholungslauf erst nach Übernahme der Änderungen.
- Eigenständiger ZIP-Test mit produktivem Autoloader, frischem WordPress, unverändertem Twenty Twenty-Five und ohne weitere Fachplugins. Ein ausschließlich synthetisches Must-use-Plugin blockiert Mail und externe HTTP-Verbindungen. Getrennte neue WordPress-Anfrage nach Aktivierung; kein manuelles Nachladen der Plugin-Laufzeit als Ersatz für einen echten Aktivierungsweg.
- Aktivierung, fünf eventbezogene Seitenrollen, wiederholte Einrichtung, veröffentlichte Shortcodes, vollständige Standardtheme-Ausgabe, Unterverzeichnis, Deaktivierung, Reaktivierung und datenerhaltende Deinstallation/Wiederinstallation geprüft. Der Zielserver benötigt keine Entwicklungsabhängigkeiten. Transport bleibt gesperrt.
- Neuinstallationsfehler behoben: Fehlende/ungültige Veranstaltungstexte und fehlende Rennen verursachen keine Null-JSON-Veraltungswarnung mehr. Alle 27 historischen Textslots besitzen eine leere Standardausgabe; vorhandene Texte einschließlich Lücken bleiben erhalten. Fehlende Texte werden dadurch nicht redaktionell ersetzt.
- Seiteneinrichtung unter **Werkzeuge → Event pages** ergänzt. Nur explizite POST-Aktionen mit Nonce, Administrator- und Seitenrechten. Neue Seiten sind Entwürfe. Bestehende Seiten werden nur zugeordnet, wenn sie den exakt angegebenen Eventshortcode enthalten; Inhalte und fremde Eigentumsmetadaten bleiben unverändert. Bestehende Zuordnungen und eigene Entwürfe werden wiederverwendet. Bearbeitete, entfernte oder falsch zugeordnete Seiten verlangen Prüfung statt automatischen Überschreibens. Erst veröffentlichte, passwortfreie Teamseiten ersetzen den eigenständigen Portal-Link.

## Übernahme- und Wiederherstellungsprobe

Verwendet wurde die vorhandene Sicherung vom **2026-11-01**, keine neu angefertigte Produktionskopie. Die Probe lief als gesonderter manueller Migrationslauf, außerhalb der automatisierten synthetischen Tests und ohne Anschluss produktiver Dienste. Weder bestehende Anwendungsdatenbanken noch das ursprüngliche Sicherungsmaterial wurden verändert. Private Daten und vollständige Prüfartefakte gehören nicht ins Repository.

| Schritt | Nachgewiesenes Ergebnis |
|---|---|
| Eigene neun Legacy-Tabellen | 7.772 Datensätze in 84 begrenzten Schritten übernommen und verifiziert |
| Historische Phasen | 15 Archivkandidaten vorgemerkt; noch keine aktive Phasenumschaltung |
| Unveränderte Quelle | Identischer Vorprüfungsfingerprint vor und nach beiden Übernahmen |
| Separater Tickerwechsel | 16.115 Einträge mit ursprünglichen IDs übernommen und zweitgeprüft; eigener nativer Speicher aktiv ausschließlich in der Prüfkopie |
| Sicherung und Wiederimport der Prüfkopie | 95 Tabellen / 387.187 Zeilen verglichen; alle verglichenen Zeilen stimmen überein |

Die Vorprüfung meldet weiterhin: zwei nicht transaktionale eigene Quelltabellen, 5.148 ungeklärte historische Spendenbeträge, 569 ungeklärte Spendenziele, 1.802 nicht eindeutig zuordenbare Ergebnis-Slots und verwaiste Beziehungen. Der Tickerbericht enthält 8.456 Hinweiszählungen; das sind keine 8.456 verlorenen Beiträge und keine behauptete Zahl eindeutiger Fehler. Originaldaten und ungeklärte Zuordnungen bleiben erhalten. Geldbeträge, Etappenzuordnungen und Identitäten werden nicht geraten.

Dieser Nachweis gilt für die additive Zwischenkopie und den separaten Tickervertrag. Er belegt weder die aktive Fachmigration aller Module noch ein fertig publiziertes Archiv oder die Übertragbarkeit neuer Schreibvorgänge auf eine ältere Plugin-Version.

## Technische Releaseblocker

| ID | Erforderliche Arbeit | Abschlussnachweis |
|---|---|---|
| R-01 | Fachliche Reader-/Writer-Umschaltung zusammenführen; `legacy-shadow-v1` ist weiterhin eine Zwischenkopie | Aktive Zuordnung je Modul, verweigerte falsche Generation, kontrollierter Wechsel und geprüfter Rückweg |
| R-02 | Vollständigen Archivsnapshot mit historischen Namen/Profilansichten, Ergebnissen/Rangfolge, Spenden, Ticker und Medienbezügen implementieren und anbinden | Alte Phase-3-Rennen werden beim endgültigen Wechsel ohne Neuberechnung Phase 4; neue Rennabschlüsse einfrieren; spätere Profil-/Datenänderungen verändern das Archiv nicht |
| R-03 | Historische Medien und unklare Zuordnungen am Bestand prüfen | Erhalt oder explizit dokumentierter Befund, keine geratene Zuordnung oder automatische Löschung |
| R-04 | Vollständige Website-Integration einschließlich bisheriger Theme-Profilseite prüfen | Teilnehmerabläufe mit Standardtheme und optionalem Bestandstheme; direkte Template-Verwendung getrennt dokumentieren |
| R-05 | Reale Betterplace-Konfiguration und unveränderten Bestätigungsablauf fachlich abnehmen | Kein angenommener Zahlungseingang allein durch Rückleitung; Automatisierungsprüfung separat festhalten |
| R-06 | Tatsächliche Versandfreigabe, Aufbewahrung und unklare Zustellausgänge entscheiden | Geprüfte Vorlagen/Empfänger und ausdrücklich genehmigter Anbietertest; keine erfundenen Löschfristen |
| R-07 | Frische Produktionskopie, finale Matrix, Übersetzungen, Leistungs- und Paketabnahme | Reproduzierbares ZIP, protokollierte Gesamtprüfung und endgültig deklarierte Plattformunterstützung |
| R-08 | Wechselplan und Rückweg nach neuen Schreibvorgängen abnehmen | Zuständigkeiten, Wartungsfenster, Sicherung und Wiederherstellung einschließlich neuer Token-/Journal-/Zahlungsdaten |

R-01/R-02 werden durch [Archiv und aktive Umschaltung](block-10-archive.md) technisch umgesetzt und geprüft: parallele modernisierte Fachtabellen, atomarer Namenswechsel, Snapshotveröffentlichung und geschützter Rückweg. Neue Rennabschlüsse laufen ausdrücklich über das Archivwerkzeug; gewöhnliche Phasenformulare dürfen den Snapshot nicht umgehen (`archive_not_ready`). Das ist keine Bestands-/Medien-/Releasefreigabe. R-03 bis R-08 sowie die gemeinsame Anzeige-/Integrationsabnahme bleiben offen. Der Versionsheader bleibt bis erfüllter Releasekriterien unverändert.

Bestandsprobe vom 2026-11-10: 15 historische Rennen mit 29.454 Snapshotdatensätzen veröffentlicht, Wiederaufnahme nach tatsächlichem Namenswechsel und unveränderte Rückschaltung der 7.772 ursprünglichen Fachdatensätze nachgewiesen. 8.376 Medienverweise besitzen gesicherte Bildkopien, 78 Verweise sind nicht verfügbar und bleiben R-03. Es wurde eine vorhandene ältere Sicherung verwendet; die frische Produktionskopie für R-07 steht weiterhin aus. Vollständige Belege: [verification.md](verification.md).

## Gemeinsame fachliche und visuelle Abnahmeliste

Die folgende Liste wird gemeinsam in ihrer Reihenfolge bearbeitet. Noch kein Bereich ist durch den Benutzer abgenommen. Automatisierte Teilnachweise ersetzen diese gemeinsame Prüfung nicht. Rückmeldungen werden mit Fall-ID, Soll/Ist, Befund und Nachprüfung protokolliert; eine Korrektur allein gilt nicht als bestandene Wiederholungsprüfung.

| Bereich | Gemeinsam prüfen |
|---|---|
| Installation und Einstieg | Einrichtung verständlich, Entwürfe zuordnen/veröffentlichen, Login/Profil ohne Spezialtheme, brauchbare Hinweise bei fehlender Konfiguration |
| Rennen und Phasen | Vor Anmeldung, offene/geschlossene Anmeldung, Rennstart und eingefrorenes Archiv; Zurücksetzen reaktiviert genau die Regeln der Zielphase |
| Einstellungen | Grundstruktur, Teilnahmevoraussetzungen und Darstellung getrennt; Warnungen bei Kapazität/Startpunkten; keine unzulässigen Änderungen nach Rennstart |
| Teams und Profile | Teamgründung, Änderungen, Verlassen, persönliche Angaben und Bilder; Adminausnahmen nur innerhalb des bestätigten Vertrags |
| Einladungen und Kapazität | Unbegrenztes Versenden ohne Reservierung; Annahme nur bei freiem Mitgliedsplatz bis Anmeldeschluss; unvollständige Teams belegen keinen Routenplatz |
| Freigabe | Voraussetzungen einstellen und persönliche Bestätigungen getrennt bedienen; automatische Rückstufung/Route vor Start, keine Rücknahme nach Start |
| Ergebnisse | Berechtigte Routen, Etappen, Ranglisten und Extras; Zusatzpunkte auch bei 0/99 und variable Zusatzpunkte nur Etappe 6; historische Werte unverändert |
| Betterplace | Bestehender bestätigter Ablauf, Spendenprofil, Sichtbarkeit und Zuordnung; Frontend weiterhin ganze Euro, historische Daten unverändert |
| Kommunikation | Unabhängige Autoresponses für zwei parallele Events, Sprache, Vorschau/Empfänger, Freigaben, Stop/Retry und unklare Ergebnisse |
| Börse | Öffentlicher Jahres-/Teilnahmefilter, eigene CRUD-Aktionen und administrative Moderation |
| Ticker und Medien | Texte, Kommentare, Bilder/Drehkopie, Video-/Kartenfreigabe, Standorte, Routenebenen, Moderation und historische Inhalte |
| Darstellung | Admin-Tabellen, Formulare, Mobilansicht, Tastatur, Screenreader und Übersetzungen über alle Module |
| Betrieb | Keine unerwarteten Weiterleitungen, Mails oder produktiven Schnittstellen; keine Warnungen/Fatal Errors; getrennte Änderungsliste für Nachbarplugins/Endpunkte |

## Gemeinsame Prüfung 1: Installation und Einstieg

Begonnen 2026-11-10. Zunächst nur Anmeldung und Status-/Einrichtungsansichten prüfen. Die folgenden Einrichtungsschritte werden danach einzeln angeleitet. Bestehende Veranstaltungen und Seiten sind keine Wegwerf-Testdaten. Sicherungsbestätigungen, Tabellenumschaltung, Archivveröffentlichung, echter Versand und Zahlungsaufrufe gehören nicht zu dieser ersten Sichtprüfung.

| Fall | Handlung | Erwartetes Ergebnis | Gemeinsamer Status |
|---|---|---|---|
| AB-I01 | Als Administrator anmelden, **Werkzeuge → cor-mgmt system status** öffnen | Anmeldung und Statusseite ohne kritischen Fehler; erkannter Bestand, Voraussetzungen und gegebenenfalls fehlende Einrichtung verständlich angezeigt | Sichtnachweis erhalten; Hinweistext korrigiert, Nachsicht offen |
| AB-I02 | **Werkzeuge → Event pages** öffnen | Ansicht erreichbar; Auswahl über Event-ID erklärt. Bei fehlender Grundinstallation verständlicher Verweis auf den Systemstatus. Öffnen allein legt keine Seiten an | Sichtnachweis erhalten: Event-ID und Auswahl sichtbar; Zuordnung noch ungeprüft |
| AB-I03 | Anschließend eine eigene Abnahmeveranstaltung auswählen bzw. im gemeinsamen nächsten Schritt anlegen | Eindeutige ID und Zuordnung; historische Veranstaltungen unverändert. Bei fertiger Grundinstallation fünf Seitenrollen mit passenden Eventshortcodes sichtbar | Offen |
| AB-I04 | Für die Abnahmeveranstaltung einen Seitenentwurf anlegen, in WordPress prüfen und dieselbe Einrichtungsaktion wiederholen | Entwurf enthält passenden Shortcode; keine automatische Veröffentlichung, keine Duplikate und kein Überschreiben bearbeiteter Seiten | Offen |
| AB-I05 | Eine eigene vorhandene Testseite zuordnen; zusätzlich falsche Eventzuordnung prüfen | Passende Seite wird zugeordnet, Inhalt bleibt unverändert. Falscher bzw. fehlender Eventshortcode wird verständlich zurückgewiesen | Offen |
| AB-I06 | Geprüfte Testseite ausdrücklich veröffentlichen und angemeldet sowie als Gast öffnen | Erwartete Ausgabe mit klarer Anmeldung bzw. Eingabe; richtige Veranstaltungszuordnung, keine ungewollten Weiterleitungen oder kritischen Fehler. Darstellung auch mit dem verwendeten Theme gemeinsam bewerten | Offen |

Aktueller gemeinsamer Schritt: **AB-I01 Einrichtung fortsetzen**, anschließend AB-I03. Team-/Einladungs- und Ergebnisspeicher sowie die neue Warteanzeige wurden vom Benutzer erfolgreich geprüft. Nun Spenden-, Event-Autoresponse-, Mailqueue-, Rundmail- und Börsen-Auditspeicher nacheinander einrichten. Das ergänzt eigene Modultabellen; es schaltet weder die Fachtabellen um noch veröffentlicht es Archive. Ticker-/Archiveinrichtung und Seitenzuordnung werden anschließend gesondert angeleitet. Keine Zugangsdaten oder personenbezogenen Bildschirmdaten in das Abnahmeprotokoll übernehmen.

| Fall/Befund | Beobachtung | Nachbesserung / Nachprüfung | Ergebnis / Freigabe |
|---|---|---|---|
| AB-I01 / AB-I02, Rückmeldung 2026-11-11 | Beide geschützten Ansichten in Screenshots sichtbar. Bestandsschema erkannt, keine Migration ausgeführt; sieben zusätzliche Moduleinrichtungen angeboten. Event-ID-Feld und Auswahl sichtbar. Ticker meldet erforderliche Einrichtung/Bestandsübernahme | Modulaufbau einzeln prüfen, danach eigene Abnahmeveranstaltung auswählen | Sichtprüfung teilweise nachgewiesen; Einrichtung und Seitenzuordnung offen |
| AB-I01-B01, 2026-11-11 | Veralteter Text behauptet, aktive Umschaltung und eingefrorene Archive seien noch nicht verfügbar | Systemstatus erklärt jetzt die getrennten Werkzeuge und verlinkt deren Verwaltungsseite. Nachsicht nach Neuladen ausstehend | Korrigiert; Benutzer-Nachprüfung offen |
| AB-I01-B02, 2026-11-11 | Warnhinweis: bestehendes WPML 3.1.5 wegen PHP-8-Inkompatibilität vom Laden ausgeschlossen; Übersetzungsdaten bleiben erhalten | Optionale Sprachintegration und passende WPML-Version separat im Rahmen der Website-/Sprachabnahme klären. Keine automatische Reaktivierung der inkompatiblen Version | Offener Integrationsbefund; keine Freigabe mehrsprachiger Abläufe |
| AB-I01: Team-/Einladungsspeicher, 2026-11-11 | Benutzer bestätigt automatisches Neuladen und verschwundenen Einrichtungsbutton | Sichtbarer Einrichtungsablauf erfolgreich; keine daraus abgeleitete Freigabe sämtlicher Teilnahmefunktionen | Einrichtungsschritt im gemeinsamen Ablauf bestanden |
| AB-I01-B03, 2026-11-11 | Während der Einrichtung werden Schaltflächen grau; verständliche Warte-/Fortschrittsmeldung fehlt | Spinner und Live-Status oberhalb der Aktionen, Wartebeschriftung am aktiven Button, Abschluss-/Fehlerhinweise ergänzt. Sperre bis Neuladen beibehalten; bei Fehler vorherige Schaltflächenzustände wiederherstellen. Assetversion erhöht. Benutzer bestätigt sichtbare Warteanzeige bei erfolgreicher Ergebniseinrichtung | Korrigiert und im gemeinsamen Ablauf nachgeprüft; Befund geschlossen |
| AB-I01: Ergebnisspeicher, 2026-11-11 | Benutzer bestätigt sichtbare Warteanzeige und erfolgreiche Einrichtung | Sichtbarer Einrichtungsablauf erfolgreich; Wertungseinrichtung und Ergebnisfunktionen bleiben eigene Abnahmefälle | Einrichtungsschritt im gemeinsamen Ablauf bestanden |
| AB-I01: technische Vorprüfung, 2026-11-10 | Startseite und vorhandene Anmeldeseite antworten mit HTTP 200; Anmeldeformular vorhanden, kein erkennbarer kritischer Fehler im Antworttext. Gastaufruf der Administration leitet auf die Anmeldeseite um. Bestehende Theme-My-Login-Integration erkannt | Tatsächliche Anmeldung und geschützte Status-/Einrichtungsansichten gemeinsam prüfen; daraus noch keine Freigabe des Anmeldeablaufs ableiten | Teilnachweis; gemeinsame Abnahme offen |

## Nachbarintegration und Ausrollen

Die bisherige Theme-Profilseite bindet `templates/frontend-form.php` weiterhin direkt ein; das bleibt eine gesondert zu prüfende optionale Kompatibilitätskante. Das Standardtheme-Paket braucht diese Datei nicht aus dem Theme aufzurufen. WordPress-Profilverwaltung und native Teilnehmerformulare bleiben getrennte Einstiege.

Die beiden alten Upload-Eingänge sind im untersuchten Bestand bereits 410-Platzhalter. Die drei in Block 9 gesondert bearbeiteten cor-ticker-Dateien sind ebenfalls weiterhin getrennte Ausrollbestandteile. Das cor-mgmt-ZIP verändert oder sperrt keine fremden PHP-Dateien; Deaktivierung eines Plugins sperrt direkte URLs nicht. Keine weiteren Theme-, Upload- oder Nachbarplugindateien wurden in diesem Block geändert.

## Prüfkommandos

`composer check`, `composer test`, `composer test:integration`, `npm test`, `npm run lint` und `npm run test:browser` entsprechend der [Teststrategie](testing.md).

Zusätzlicher Paketvertrag: `php tools/create-wordpress-fixture.php CORE_SOURCE NEW_EMPTY_TARGET --standard-theme` erzeugt einen ausdrücklich synthetischen Kernbestand mit dem mitgelieferten Twenty Twenty-Five. `php tools/check-standalone.php PLUGIN_ZIP` verlangt `COR_TEST_STANDALONE_CORE` und die streng beschränkten Testdatenbankvariablen der Integrationssuite. Das Werkzeug besitzt keinen Pfad für Anwendungsdatenbanken. Die temporäre Installation verwendet einen eigenen zufälligen Präfix und wird nach der Prüfung entfernt. In CI nach dem Paketbau auf PHP 8.2/8.5 vorgesehen. Tatsächlich ausgeführte Läufe und Grenzen stehen in [verification.md](verification.md).
