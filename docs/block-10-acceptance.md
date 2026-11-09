# Block 10: Gesamtabnahme und Releasevorbereitung

Stand 2026-11-09. Technische Abnahme begonnen; kein Releasekandidat und kein Produktionswechsel. Die gemeinsame fachliche und visuelle Abnahme erfolgt anschließend gemäß D-51.

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

R-01/R-02 sind aus früheren Blöcken übernommene Implementierungsarbeit, keine bloß noch ausstehende Bestätigung durch den Benutzer. Rennabschluss bleibt bis R-02 ausdrücklich gesperrt (`archive_not_ready`). Der Versionsheader bleibt bis erfüllter Releasekriterien unverändert.

## Gemeinsame fachliche und visuelle Abnahmeliste

Die folgende Liste ist vorbereitet, noch nicht durch den Benutzer abgenommen. Automatisierte Teilnachweise ersetzen diese gemeinsame Prüfung nicht.

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

## Nachbarintegration und Ausrollen

Die bisherige Theme-Profilseite bindet `templates/frontend-form.php` weiterhin direkt ein; das bleibt eine gesondert zu prüfende optionale Kompatibilitätskante. Das Standardtheme-Paket braucht diese Datei nicht aus dem Theme aufzurufen. WordPress-Profilverwaltung und native Teilnehmerformulare bleiben getrennte Einstiege.

Die beiden alten Upload-Eingänge sind im untersuchten Bestand bereits 410-Platzhalter. Die drei in Block 9 gesondert bearbeiteten cor-ticker-Dateien sind ebenfalls weiterhin getrennte Ausrollbestandteile. Das cor-mgmt-ZIP verändert oder sperrt keine fremden PHP-Dateien; Deaktivierung eines Plugins sperrt direkte URLs nicht. Keine weiteren Theme-, Upload- oder Nachbarplugindateien wurden in diesem Block geändert.

## Prüfkommandos

`composer check`, `composer test`, `composer test:integration`, `npm test`, `npm run lint` und `npm run test:browser` entsprechend der [Teststrategie](testing.md).

Zusätzlicher Paketvertrag: `php tools/create-wordpress-fixture.php CORE_SOURCE NEW_EMPTY_TARGET --standard-theme` erzeugt einen ausdrücklich synthetischen Kernbestand mit dem mitgelieferten Twenty Twenty-Five. `php tools/check-standalone.php PLUGIN_ZIP` verlangt `COR_TEST_STANDALONE_CORE` und die streng beschränkten Testdatenbankvariablen der Integrationssuite. Das Werkzeug besitzt keinen Pfad für Anwendungsdatenbanken. Die temporäre Installation verwendet einen eigenen zufälligen Präfix und wird nach der Prüfung entfernt. In CI nach dem Paketbau auf PHP 8.2/8.5 vorgesehen. Tatsächlich ausgeführte Läufe und Grenzen stehen in [verification.md](verification.md).
