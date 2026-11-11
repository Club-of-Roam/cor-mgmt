# Block 9 – Webticker, Medien und kontrollierter Übergang

Umsetzung: 2026-11-09. Fachliche Grundlage: D-50/51 und [Plan](block-9-ticker-plan.md). Fachliche und visuelle Gesamtabnahme bleibt in Block 10; ausgeführte technische Prüfungen ausschließlich nach [Prüfbelegen](verification.md).

## Laufzeit und Eigenständigkeit

Der Webticker gehört zu cor-mgmt. Die vorhandenen sechs Ticker-Shortcodes und öffentlichen Teamticker-Methoden bleiben registriert. Native Formulare, Anzeige und Verwaltung benötigen weder Spezialtheme noch cor-ticker. Schreiben erfolgt über den geschützten WordPress-Einstieg `admin-post.php`, nicht beim Rendern einer Liste. Nachrichten, Kommentare, Bilder, YouTube-Videos und Standorte verwenden den gemeinsamen Writer mit Eventbindung, Berechtigungen, Nonce und Anfrageschlüssel. SQL bleibt vorbereitet; Inhalt wird bei Ausgabe behandelt.

Das Eingabe-Flag `liveticker` und Anzeige-Flag `liveticker_front` bleiben getrennt. Vollständige Teams und objektberechtigte Organisatoren dürfen neue Beiträge schreiben; angemeldete Benutzer dürfen sichtbare Beiträge desselben Events kommentieren. Archivierte Events bleiben unveränderlich, auch für Organisatoren. Der Absendername „Tramprennen“ und eine besondere Telefonnummer verleihen keine Rechte mehr.

Neue Beiträge erhalten eine feste Akteurs-/Team-/Eventzuordnung. Historische Telefonnummern bleiben historische Angaben und werden nicht gegen heutige Profilnummern aufgelöst. U_/T_-Bezüge werden übernommen; unbekannte und verwaiste Angaben werden gemeldet. Historische Autoren werden öffentlich ohne Telefonnummer dargestellt. Team-/Routenfilter für frühere U_-Beiträge verwenden die vorhandenen expliziten Mitgliedschaften; dies erzeugt keine neuen historischen Zuordnungen.

Listen sind paginiert und pro Schritt auf höchstens 100 Hauptbeiträge begrenzt; `max=0` bedeutet keine unbegrenzte Serverantwort mehr. Bestehende Shortcode-Defaults für Event, Route, Team, Navigation und Hinweise bleiben erhalten. Kommentarformulare sind eigene native Formulare; alte themegebundene POST-Aufbauten müssen auf den Plugin-Renderer umgestellt werden. Neue Anfragen brauchen einen stabilen `request_key` aus 32 Hexzeichen. Reine GET-Aufrufe führen keine Befehle aus.

Alte Leseparameter `ticker_race`, `ticker_route` und `min` bleiben nutzbar; ein explizites Eventattribut wird nicht durch die Query überschrieben. Karten zeigen höchstens 100 Positionen je Seite, Routenfarben und ein-/ausblendbare Routen- bzw. Teamebenen. Beim Öffnen eines Markers erscheint der Teamverlauf der geladenen Positionen. Marker und Ebenen sind per Tastatur erreichbar; Namen und Popuptexte werden als Text behandelt.

## Daten und Installation

[TickerSchema](../src/Infrastructure/WordPress/Schema/TickerSchema.php) 1.0.0 ergänzt eigene InnoDB-/utf8mb4-Tabellen für Beiträge und Änderungsjournal. Das unabhängige Installationsmanifest wird bei Aktivierung oder ausdrücklich unter **Werkzeuge → Web ticker** eingerichtet. Referenz: [SQL](schema-ticker-1.0.0.sql). Bestehende acht Schema-/Migrationspläne bleiben unverändert.

Eine Erstinstallation ohne alte Tickertabelle ist sofort für die native Datenhaltung eingerichtet. Bei Bestand gibt es keine automatische Datenumschaltung: bis zur geprüften Übernahme bleibt eine begrenzte, rein lesende Altanzeige verfügbar; neue Webeinsendungen sind pausiert. Deaktivierung/Reaktivierung löscht keine Beiträge oder Medien. Deinstallation bleibt datenerhaltend.

[TickerImport](../src/Infrastructure/WordPress/TickerImport.php) arbeitet ausdrücklich in Schritten von höchstens 100 Datensätzen:

1. Sicherung erstellen und sämtliche alten Writer stoppen. Nebenplugin und direkte SMS-/MMS-URLs gehören ausdrücklich dazu. Fremde SQL-Writer werden durch eine Anwendungssperre nicht automatisch gestoppt.
2. Modul einrichten und den Stillstand bestätigen. **Start controlled transfer** deaktiviert gezielt `cor-ticker/sms_ticker.php`; gewöhnliche Seitenaufrufe ändern keinen Pluginstatus.
3. Original-IDs, Typen, Inhalte und Bezüge kopieren. Originalspalten einschließlich unbekannter/binärer Felder und Nullwerten werden als sortierter Base64-Snapshot samt SHA-256 erhalten. HEX-Abfragen vermeiden Zeichensatzverluste beim Lesen. Die alte Variante `from` und die Variante `send_from` werden berücksichtigt; fehlende Event-/Elternangaben werden nicht erfunden. UTF-32/UTF-16/UCS-2/latin1 werden nur für die Anzeigeprojektion dekodiert. Nicht geprüfte oder ungültige Textkodierungen stoppen den Schritt mit einem Prüfbefund, statt eine Kodierung zu erraten. Binäre Zusatzfelder und alte Bildblobs werden unverändert gesichert. Unbekannte Zeitformate werden nicht geraten.
4. Zweiter begrenzter Prüfdurchlauf vergleicht Quelle, Snapshot, Anzeigefelder, Datensatzanzahl und IDs. Geänderte Quelle, Zielkonflikte oder fehlende Zeilen brechen den Schritt ab. Journal und Datenpaket werden gemeinsam gespeichert; Fortsetzung übernimmt keine Duplikate.
5. Erst nach erfolgreicher Prüfung native Schreibwege freischalten. Die vorherige Quelle bleibt unverändert bestehen.

Für eine bereits gestartete Übernahme bietet **Resume automatic processing** die automatische Fortsetzung. Nach ausdrücklichem Klick wird jeweils genau ein vorhandener, geschützter POST-Schritt ausgeführt; kein neuer Start und keine Rücksetzung. Nach der Kopie folgt automatisch der Prüfdurchlauf. **Stop after current step** wartet auf den laufenden Schritt und hält danach an; erneutes Fortsetzen verwendet den gespeicherten Stand. Die Seite muss währenddessen geöffnet bleiben. Bei Netzwerkfehlern, Weiterleitungen, fehlender Erfolgsbestätigung, ungültigem Zustand, fehlendem Fortschritt oder geänderter Quellidentität stoppt die Automatik ohne Wiederholung. Vor erneutem Start nach einem Fehler die Seite neu öffnen und den tatsächlich gespeicherten Stand prüfen. Ohne JavaScript bleiben die einzelnen POST-Aktionen verfügbar.

Während der Kopie zeigt die Oberfläche die letzte Quell-ID und einen unbestimmten Fortschrittsbalken; ID-Lücken werden nicht als kopierte Datensätze gezählt. Im Prüfdurchlauf zeigt sie die tatsächlich geprüften Datensätze gegenüber der Gesamtzahl. Hinweise bleiben sichtbar; ein abgeschlossener Lauf ersetzt ihre fachliche Prüfung nicht. Die Automatik verwendet die bestehende Nonce- und Administratorprüfung der Verwaltungsseite, erlaubt ausschließlich dieselbe Herkunft und folgt keinen Weiterleitungen. Antwortseiten werden nur als Daten gelesen; ihre Skripte und Inhalte werden nicht in die Verwaltungsseite eingesetzt.

Das WordPress-Optionsverzeichnis muss für das atomare Fortschrittsjournal ebenfalls InnoDB verwenden. Ein nicht transaktionaler Checkpoint wird abgewiesen; keine automatische Engine-Konvertierung. Ein Quellstillstand über den gesamten Übergang ist Voraussetzung; dies ist keine Online-Replikation für parallel weiter beschriebene MyISAM-Tabellen. Unklare Zeit-/Absender-/Medienangaben und verwaiste Event-/Eltern-/Teilnehmer-/Teambezüge stehen im Bericht. Unbekannte Nachrichtentypen und Felder bleiben erhalten. `sms_ticker_status` bleibt als stillgelegter historischer Betriebsbestand unverändert in seiner ursprünglichen Tabelle; seine Gateway-Zeitwerte steuern keine neuen Funktionen. Alte Tabellen und Medien werden niemals automatisch entfernt.

Vor Wiederherstellung zunächst neue Writer stoppen und alle nach der Umschaltung entstandenen Beiträge und Moderationsentscheidungen sichern. Ein Rückwechsel zum alten Writer ohne Übernahme neuer Daten wäre kein vollständiger Rückweg. Das alte Gateway darf auch beim Rückweg nicht wieder geöffnet werden.

## Medien und externe Inhalte

Uploads erfolgen über WordPress, mit tatsächlichem Uploadnachweis, Größen-/MIME-/Pixelgrenzen, zufälligem Dateinamen und Event-/Eigentumsprüfung. Drehen erzeugt eine neue Datei und ein neues Attachment. Originale, gemeinsam verwendete Dateien und Archive werden nicht überschrieben. Eine veraltete Drehanfrage wird abgewiesen; eine dabei neu erzeugte, noch nicht übernommene Datei wird aufgeräumt. Das ehemalige direkte `picture()`-SQL-Verfahren ist stillgelegt.

Alte MMS-Bilder und vorhandene Upload-URLs werden nur auf Dateien innerhalb des WordPress-Uploadverzeichnisses abgebildet. Nicht auflösbare oder externe Referenzen erzeugen keine automatischen Fremdabrufe und benötigen Bestandsprüfung. Es werden keine alten Medien heruntergeladen oder gelöscht.

Auch vorhandene Dateien benötigen einen Rasterbildnachweis und eine erlaubte Bildendung. PHP-/SVG-Dateien, ausführbare Zwischenendungen wie `.php.png` und falsche Bildinhalte erzeugen keinen Bildaufruf. Dieser Schutz gilt auch bei gefälschten Attachment-Metadaten. Die Originaldatei bleibt zur Prüfung erhalten.

Karten verwenden mitgeliefertes **Leaflet 1.9.4**, die derzeit stabile 1.x-Version; Leaflet 2 ist laut [Projektinstallation](https://leafletjs.com/install.html) noch Beta. Lizenz liegt im Paket. Geprüfte SHA-256-Werte: JS `db49d009c841f5ca34a888c96511ae936fd9f5533e90d8b2c4d57596f4e5641a`, CSS `a7837102824184820dfa198d1ebcd109ff6d0ff9a2672a074b9a1b4d147d04c6`.

Kartenkacheln und Videos werden erst nach ausdrücklichem Klick geladen, ohne Vorschaubild-/CDN-Abruf davor. Videos erlauben ausschließlich geprüfte YouTube-IDs und `youtube-nocookie.com`. Standortbestimmung erfolgt nur nach Klick mit Browserberechtigung; manuelle Koordinaten bleiben möglich. OSM-Nennung, Referer und Browsercache bleiben erhalten; keine Vorab-/Offline-Massenabfragen. Betreiber müssen die [OSM-Kachelrichtlinie](https://operations.osmfoundation.org/policies/tiles/) und das erwartete Verkehrsvolumen berücksichtigen. Keine Verfügbarkeitszusage für externe Dienste.

## Verwaltung und abgelöste Schnittstellen

**Werkzeuge → Web ticker** bündelt Einrichtung, Fortschritt und eventbezogene Moderation. Ausblenden/Wiederherstellen verwendet Objektberechtigung, Revision, Anfrageschlüssel und Journal. Datensätze werden nicht hart gelöscht. Archive bleiben nur lesbar. Alte Telefonnummern-Reparatur bietet nur noch einen geschützten Bericht; Umschreiben historischer Identitäten ist stillgelegt.

In der Moderationsansicht werden Bildvorschauen proportional auf höchstens 360 × 280 Pixel und die verfügbare Breite begrenzt; kleine Bilder werden nicht vergrößert. Ein per Tastatur erreichbarer Bild-/Textlink öffnet die volle Bildgröße in einem neuen Tab. Originaldateien werden dadurch nicht verändert.

`h3-ticker-incoming-interface` liefert für jeden Aufruf HTTP 410, auch mit früher korrekt signierter Nutzlast. `COR_MGMT_TICKER_SECRET` aktiviert keinen Eingang mehr. Es werden keine Replay-Optionen, Attachments oder Beiträge aus Gatewayanfragen erzeugt.

**Gesonderte Änderungen außerhalb dieses Repositorys:**

| Datei im bisherigen cor-ticker | Änderung |
|---|---|
| `sms_ticker.php` | Inaktiver Kompatibilitätsplatzhalter; kein Menü, GET-Löschen oder eigener Installer |
| `interfaces/sms.php` | HTTP 410 ohne WordPress-Bootstrap, Dateiannahme oder Datenbankschreiben |
| `interfaces/sms-status.php` | HTTP 410; keine Betriebszeitantwort für den eingestellten Transport |

Diese drei Änderungen müssen beim Ausrollen ausdrücklich mitgeliefert bzw. die alten URLs auf Serverebene gesperrt werden; das cor-mgmt-ZIP schreibt keine fremden Plugindateien um. Die Deaktivierung des Nebenplugins allein sperrt direkte PHP-URLs nicht. Keine Theme- oder ausgelagerten Upload-Endpunkte in diesem Paket geändert. Deren bereits vorhandene Sperren bleiben bestehen.

## Noch zur Gesamtabnahme

Native Skripte und Styles verwenden eigene Dateien und Handles (`cor-mgmt-ticker`); die bisherigen `h3-mgmt-ticker`-Assets bleiben unverändert. Ein Regressionstest prüft beide Registrierungsreihenfolgen und verhindert doppelte native Skriptanfragen trotz der bisherigen globalen Legacy-Einbindung.

Kontrollierte Übernahme einer frischen Bestandskopie, Prüfung aller historischen Medien/Zeichensätze/Sonderfälle, tatsächliche Theme-/Nachbarintegration, Übersetzungen sowie fachliche, visuelle und manuelle Tastaturabnahme gehören in Block 10. Automatisierte synthetische Prüfungen ersetzen diese Bestandsfreigabe nicht. Kein echter Gateway-/Mail-/Zahlungsaufruf und keine Anwendungsmigration durch die Entwicklungstests.

## Bezug zur Funktionsreferenz

| Referenz | Neuer Vertrag und Nachweis |
|---|---|
| F-TICK-01 | Native Text-/Kommentar-/Bild-/Video-/Positionsbefehle; Eventrechte, Nonce, Replay und Archivschutz; echte multipart-Bildübertragung und getrennte Drehkopie |
| F-TICK-02 | Getrennte Eingabe-/Anzeigeschalter, Event-/Team-/Routenfilter, begrenzte Pagination, Einblendefreigabe, Routenebenen und Teamverlauf; keine Schreibwirkung beim Rendern |
| F-TICK-03 | Durch D-50 fachlich stillgelegt: auch ehemals gültig signierte Gatewayanfragen erhalten 410, ohne Datenannahme |
| F-TICK-04 | Historischer Telefonnummern-Helfer bleibt kompatibel; er erteilt keine Rechte und ordnet keine neuen Webbeiträge zu |

Installations- und Übergangsfälle prüfen zusätzlich Neuinstallation, Wiederholung, Fortsetzung nach Unterbrechung, Quell-/Zieländerung, ursprüngliche UTF-32-Struktur, binäre Zusatzspalten, unbekannte Kodierung, nicht transaktionalen Checkpoint und zwei echte parallele Writerprozesse. Die ursprünglichen Quellbefunde des Referenzkatalogs sind historische Ausgangsbefunde; die heutige Umsetzung ersetzt deren alte Schreibverträge.
