# Block 10: Archiv und aktive Speicherumschaltung

Stand 2026-11-12. Technische Umsetzung; keine Produktions- oder Releasefreigabe. Aktuelle Prüfbelege stehen in [verification.md](verification.md).

## Umfang

`ArchiveSchema` 1.0.0 ergänzt drei eigene Tabellen für Läufe, Datensnapshots und Medienverweise. Die bisherigen neun Installationspläne bleiben unverändert. Plugin 1.2.10, Legacy-Schema 2.8, Storage 3.0.0 und Archivversion sind unabhängig.

`ArchiveSource` erfasst ein Rennen mit Routen, Etappen, Teams, Mitgliedschaften, Einladungen und Spenden einschließlich unbekannter Felder. Hinzu kommen vorhandene neue Wertungs-/Spendenjournale, native Tickerbeiträge und deren Historie. Originalbytes werden als HEX erhalten; die Darstellung erhält eine gesonderte, anhand des deklarierten Zeichensatzes geprüfte Projektion. Mehrdeutige historische Geldwerte werden nicht umgerechnet. Konten bleiben im WordPress-Bestand; der Archivsnapshot enthält keine Benutzerpasswörter oder Konto-E-Mail-Adressen. Öffentliche Namen, Geburtsdatum, Ort und lokale Avatarverweise werden für eine zeitlich stabile Teilnehmeranzeige eingefroren. Die privaten Fachsnapshots werden nicht durch einen öffentlichen Datenendpunkt ausgegeben.

`ArchiveMedia` kopiert vorhandene, geprüfte lokale Bilder in einen inhaltsadressierten Archivbereich. Originale bleiben unverändert. Keine Netzwerkdownloads, kein Ausführen kopierter Dateien und kein Rückgriff auf später veränderte Originale. Fehlende, externe oder unzulässige Bilder erhalten den Status `unavailable`; ihre Anzahl wird angezeigt. Das ist ein Abnahmebefund, kein erfolgreich kopiertes Bild. Vorhandene Videos bleiben als eingefrorene Anbieter-/Medienreferenz erhalten; externe Anbieterinhalte können nicht durch das Plugin unveränderlich gemacht werden.

## Bedienung und Voraussetzungen

Die reine Archivspeichereinrichtung erfolgt wie die übrigen Zusatzspeicher unter **Werkzeuge → cor-mgmt system status** über **Set up archive storage**. Dafür gelten Administratorrecht, POST und Nonce; die Einrichtung ist wiederholbar und startet weder Snapshot noch Kopie oder Tabellenumschaltung. Sie benötigt keine Bestätigung eines vollständigen Writerstillstands. Die Archivseite verlinkt diesen getrennten Einrichtungsweg, und ein fehlender Archivspeicher wird im allgemeinen Einrichtungshinweis konkret benannt.

Unter **Werkzeuge → Archives and storage transition** bleiben Snapshotverarbeitung und Speicherumschaltung erreichbar. Diese Befehle benötigen zusätzlich die ausdrücklichen Bestätigungen für vorhandene Sicherung und gestoppte Quellwriter. Auch der bisherige administrative POST-Befehl `archive-setup` behält für Aufrufkompatibilität seine bisherigen Bestätigungen; der getrennte Einrichtungsweg verwendet den geschützten Schema-Endpunkt. Alte Plugins, Direktendpunkte, Hintergrundjobs, Konto-/Profiländerungen und andere Datenbankschreiber müssen für die Übernahme pausieren. Der gemeinsame Pluginlock ersetzt keine Sperre fremder Programme.

Eine Seitenansicht startet keine Kopie. Jeder Verarbeitungsschritt ist begrenzt; die Browserverarbeitung muss ausdrücklich gestartet werden und endet vor Veröffentlichung bzw. Umschaltung. Stop wartet den laufenden Schritt ab. Fehler werden nicht automatisch wiederholt. Journal und Oberfläche erlauben die ausdrückliche Wiederaufnahme. Ein abgebrochener, unveröffentlichter Lauf kann ohne Löschen seiner Daten aufgegeben werden.

Für ein neues Rennende: Rennen in Phase 3 auswählen, Snapshot im Modus `completion` anlegen, kopieren und unabhängig prüfen, anschließend ausdrücklich veröffentlichen. Erst dann werden Phase 4 und Archivzeiger gemeinsam gesetzt. Gewöhnliche Phasenformulare dürfen diesen Weg nicht umgehen. Veröffentlichte Snapshots sind unveränderlich; Teilnehmer- und Verwaltungswriter sind bereits während der Vorbereitung gesperrt.

## Aktive Migration

`Cutover` verwendet den unabhängigen Plan `live-copy-v1`:

1. Eine verifizierte `legacy-shadow-v1`-Übernahme muss exakt zur aktuellen Vorprüfung passen. Für jedes dort erfasste Phase-3-Rennen wird zusätzlich ein verifizierter Snapshot im Modus `migration` benötigt. Die native Tickerübernahme muss zuvor abgeschlossen sein.
2. Neun parallele Fachtabellen erhalten alle bisherigen Spalten und IDs, InnoDB, utf8mb4 sowie Beziehungsindizes. Unbekannte Binärwerte bleiben unverändert. Textkonvertierungen folgen dem deklarierten Quellzeichensatz; SQL-Warnungen und Abweichungen stoppen die Verarbeitung. Eigene Trigger und ein- oder ausgehende Fremdschlüssel benötigen eine gesonderte Prüfung; die Umschaltung weist sie ab, statt sie durch `CREATE LIKE` stillschweigend abzukoppeln. Quelltabellen werden während der Vorbereitung nicht verändert.
3. Kopie und unabhängiger Vergleich laufen in wiederaufnehmbaren Batches. Phase 3 wird ausschließlich in der Zielkopie auf Phase 4 gesetzt; gespeicherte Punkte werden niemals neu berechnet. Die Laufzeit bleibt während des Übergangs für gewöhnliche Pluginwriter gesperrt.
4. Unmittelbar vor der Umschaltung werden Vorprüfungsfingerabdruck, Schema, Mengen und vollständige Zielwerte erneut geprüft. Ein gemeinsames `RENAME TABLE` tauscht die Tabellennamen. Originale bleiben unter journalisierten Sicherungsnamen erhalten. Alle bisherigen Einstiegspunkte lesen anschließend die modernisierten Tabellen unter ihren bisherigen Namen.
5. Archivveröffentlichung, aktive Laufzeitgeneration und Journalabschluss werden transaktional verbunden. Ein unterbrochener Namenswechsel wird ausdrücklich anhand der tatsächlich vorhandenen Tabellen fortgesetzt; widersprüchliche Zustände benötigen Prüfung und werden nicht geraten.

Die aktive Umschaltung ist derzeit nur für MariaDB freigegeben. MySQL wird bis zu einer gesonderten Prüfung dieses DDL-/Wiederherstellungswegs abgewiesen; der allgemeine Installationsvertrag ist davon getrennt. MariaDB dokumentiert die gemeinsame, ab 10.6 für die verwendeten Engines crash-atomare [Tabellenumbenennung](https://mariadb.com/docs/server/reference/sql-statements/data-definition/rename-table). Die Mindestversion des Plugins liegt darüber. Eine Wiederherstellungsprobe und Sicherung bleiben erforderlich.

## Archivansichten und Grenzen

Öffentliche Team-, Ergebnis-, Etappen-, Statistik-, Spenden- und Tickeransichten verwenden versiegelte Snapshots. Teilnehmernamen, Orte, Alter zum Erfassungszeitpunkt, Bilder, Teamtexte und gespeicherte Summen ändern sich dadurch nicht mit späteren Profiländerungen. Statistikauswahl, Routenauswahl, Ranglistenoptionen und ganze Euro bleiben erhalten. Eingabeformulare zeigen den schreibgeschützten Archivstatus. Die Ausgabe wird weiterhin nach HTML-/URL-Kontext behandelt; historische Rohfelder werden nicht ungefiltert ausgegeben.

Eigene Theme-Direktabfragen und externe Leser müssen bei der Gesamtabnahme gesondert geprüft werden: Sie werden nicht durch den Shortcodeadapter automatisch auf Snapshots umgeleitet. Historische Anzeige-/Punktefehler werden nicht durch eine neue Wertungsberechnung korrigiert. Visuelle Gleichheit, vorhandene Seitenattribute, Sprachvarianten, Anbieterinhalte und fehlende Bilder bleiben Bestandteile der gemeinsamen Abnahme.

## Rückweg und Datenerhalt

Vor dem Namenswechsel kann die Vorbereitung aufgegeben werden; bereits erstellte Kopien bleiben erhalten. Nach Aktivierung erlaubt ein ausdrücklicher Rückweg die umgekehrte gemeinsame Umbenennung ausschließlich ohne neue Datenänderungen. Ein Fingerabdruck über sämtliche bekannten Fachtabellen, Snapshots sowie WordPress-Benutzer und -Metadaten blockiert den Rückweg bei neuen Änderungen. Das kann auch eine inzwischen gespeicherte Sitzung oder Profiländerung betreffen. Es gibt keinen automatischen Rückweg, der neue Daten verwirft.

Der Rückweg entfernt lediglich die aktiven Archivzeiger; versiegelte Snapshots, kopierte Bilder und vorbereitete Tabellen werden nicht gelöscht. Ein unterbrochener Rückweg ist auf beiden Seiten der Umbenennung wiederaufnehmbar. Nach neuen Schreibvorgängen ist eine gesonderte, datenbewahrende Wiederherstellung bzw. Vorwärtskorrektur erforderlich. Fremde Tabellen, WordPress-Inhalte und Dateien werden nicht durch den Pluginfingerabdruck zu einer umfassenden Sicherung.

## Prüfung

Synthetische WordPress-Integration prüft Archivschutz, Profil-/Bild-/Tickerstabilität, beschädigte Snapshots, falschen Modus, Rechte/Nonce/Bestätigungen, Statistik-/Ranglistenoptionen, Zeichensätze und unbekannte Spalten sowie aktive Umschaltung, Abbruch und Wiederaufnahme einschließlich Rückweg. JavaScript-Prüfungen prüfen ausdrücklichen Start, gleiche Herkunft, Anfragedaten, Stop, fehlerhafte Antworten und ausbleibende automatische Veröffentlichung. Befehle: `composer check`, `composer test`, `composer test:integration`, `composer test:js`, `npm run lint`, `npm run test:browser`, `composer build` und `php tools/check-package.php`.

Eine ältere Bestandskopie ersetzt keine frische Produktionskopie. Die endgültige vollständige Bestands-/Medien-/Bedienungsabnahme und das Wartungsfenster bleiben [Releasekriterien](block-10-acceptance.md).
