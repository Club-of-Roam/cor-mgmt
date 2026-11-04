# Datenmodell und Migration

Ausgangsbefund: 2026-11-01, lesende SELECT/SHOW-Prüfung. Umsetzung Stand 2026-11-03: [Erstinstallation und kontrollierte Zwischenmigration](block-3-migration.md), [ausführbare Schema-Referenz](schema-3.0.0.sql). Keine vorhandenen Anwendungsdaten migriert; die fachliche Umschaltung und vollständige Archivabnahme bleiben offen.

## Ist-Modell

Tabellen: WordPress-Präfix plus h3_mgmt_*. Im untersuchten Bestand ist h3_h3_mgmt_* korrekt, weil das WP-Präfix h3_ lautet. Präfix niemals fest einbauen.

| Suffix | Datensätze | Engine | Inhalt |
|---|---:|---|---|
| auto_responses | 28 | InnoDB | Aktion, Sprache, Schalter, Betreff, Text |
| invitations | 215 | MyISAM | Team, E-Mail, Code |
| races | 17 | InnoDB | Veranstaltung, Zeiten, JSON-Einstellungen/Texte |
| routes | 60 | InnoDB | Rennen, Kapazität, Zuständigkeit, Gestaltung |
| stages | 329 | InnoDB | Rennen, Route, Nummer, Ziel, Treffpunkt, Land |
| sponsors | 5148 | InnoDB | Spende/Zahlung, Kontakt, Zuordnung, öffentliche Angaben |
| teams | 569 | InnoDB | Stammdaten, Freigabe, sechs Ergebnis-/Zusatzpunktgruppen, meta_1..5 |
| teammates | 1237 | InnoDB | Team/Benutzer, Zahlung, Haftungsverzicht, Sprache |
| xchange | 169 | MyISAM | Benutzer, Nachricht, Änderungszeit |

Gemeinsam mit cor-ticker: sms_ticker mit 16115 Einträgen und sms_ticker_status. Zeichensätze latin1/utf8mb3/utf32, teils pro Spalte abweichend. überwiegend nur UNIQUE(id), fehlende Beziehungsindizes. Ist-Schema weicht z.B. bei country_3166_alpha-2 und teammates.language vom Installationscode ab.

## Beziehungsbefunde

4 Mitgliedschaften ohne Team, 15 ohne Benutzer, 27 Einladungen ohne Team, 479 Sponsoringeinträge mit fehlender Team-ID ungleich null. Gruppen können sich überschneiden. Keine doppelten (team_id,user_id) oder Mehrfachteams je Benutzer/Rennen gefunden. Daraus keine automatische Löschung ableiten.

Historische Spendenmethoden: Betterplace 4197, debit 604, paypal 341, leer 6. Dies belegt keine Freigabe künftiger Zahlungswege.

## Installation und Upgrade

Frische Installation und erkanntes Bestandsupgrade sind getrennte Pfade. Aktivierung, Einrichtung, Wiederholung, Deaktivierung und datenerhaltende Deinstallation werden gemäß [Installationsvertrag](installation.md) geprüft. Tabellen, Standardwerte und Rechte ohne vorhandene Website-Konfiguration einrichten. Bestehende Daten niemals als Neuinstallation überschreiben.

## Migrationsvertrag

- Plugin-, Schema- und Schrittversionen unabhängig. Erst nach erfolgreicher Prüfung als migriert markieren.
- InnoDB/utf8mb4, Primärschlüssel und querybezogene Indizes. Zeichenkonvertierung mit Stichproben prüfen; kein blindes Umdekodieren.
- Etappenergebnisse als Datensätze je Team/Etappe statt sechs Spaltengruppen. Historische Slots anhand Rennen/Route/Nummer zuordnen; nicht zuordenbare Werte erhalten und melden.
- Neue Beträge in Cent plus Währung. Alte Einheit nach Schreibpfad klassifizieren, Originalwert/Herkunft erhalten. Verlorene Nachkommastellen nicht rekonstruieren. Mehrdeutige Fälle nicht automatisch fachlich konvertieren.
- Konten, Passwörter, IDs und Medien erhalten. Verwaiste Beziehungen behalten Original-ID; keine kaskadierende Bereinigung.
- Neue sichere Einladungstokens mit Hash, Ablauf und Verbrauch. Offene numerische Codes über geschützten dokumentierten Übergangspfad erhalten.
- Fachentscheidung 2026-11-02: Historische Ergebnisse nicht nachträglich korrigieren. Gespeicherte Summen und historische Rangfolge bei Migration/Anzeige erhalten, auch wenn die neue Berechnung abweicht; Abweichungen nur getrennt berichten. Historiengrenze beschlossen: Bestandsrennen mit Ausgangsstatus 3 bei der Migrations-Vorprüfung, siehe Archivvertrag unten. Kein stiller Neuberechnungslauf als Teil der Normalisierung.
- Vorprüfung/Probelauf ohne Änderungen, Batches mit Lock und Journal, idempotente Wiederaufnahme. Keine Massendatenmigration bei Seitenaufrufen.
- Neue Tabellen zunächst parallel; DDL nicht als vollständig transaktional rückrollbar behandeln. Bei Fehler keine automatische DROP-/Neuanlage.
- Umschaltung pro Modul, keine konkurrierenden Writer. Rückweg mit neuen Schreibvorgängen vor Freigabe festlegen.
- Vergleiche: Zeilenzahlen, IDs, Beziehungen, Status, Beträge, Zeichen, Vorlagen, Medien und Ranglisten. Berichte ohne private Inhalte in CI.

Exakte DDL, Feldmapping und Klassifikation sind Liefergegenstände von Block 3 vor Bestandsänderungen. Standard-Uninstall erhält Daten; explizite Bereinigung ist ein eigener geschützter Vorgang.


## Archivphase und Statusmigration (beschlossen 2026-11-02)

- Neue Phase »Rennen beendet«, vorgeschlagener technischer Wert 4. Altstatus 0/1/2 bleibt erhalten; alle Bestandsrennen mit Ausgangsstatus 3 werden direkt archiviert. Die ID-/Statusmenge im verbindlichen Preflight festhalten, in Journal und Wiederaufnahme verwenden.
- Daten vor Statusumstellung unverändert sichern/übertragen. Kein Aufruf der alten Freigabe- oder Punkteberechnung, kein Mailversand und keine stillen Korrekturen historischer Summen/Rangfolge.
- Archivierung benötigt einen stabilen historischen Stand: Einstellungen, Teams/Mitgliedschaften, Routen-/Etappenzuordnungen, Ergebnisse und benötigte Darstellungswerte erhalten. Allgemeine Nutzerprofile bleiben eigenständig; spätere Änderungen dürfen den archivierten Rennstand nicht verändern. Snapshotumfang der verbundenen Medien/Ticker/Spenden mit den jeweiligen Modulen spezifizieren.
- Alle fachlichen Writer einschließlich Admin, Jobs und externen Eingängen müssen die Archivphase beachten. Lesen und Anzeige historischer Ranglisten erfolgt aus dem eingefrorenen Stand; keine neue Punkteberechnung beim Anzeigen.
- Schema-/Darstellungsmigration darf historische Werte verlustfrei in neue Strukturen übertragen; das ist keine Erlaubnis zu fachlicher Neuberechnung. Wiederholung/Unterbrechung erzeugt weder weitere Snapshots noch abweichende Werte.
- Vor und nach Migration IDs, Zeilenzahlen, Ergebniswerte, historische Rangfolge und Statusmapping vergleichen. Datensätze mit anderem/unklarem Ausgangsstatus melden, nicht geraten zuordnen.
- Neue Rennen werden nach ihrem Abschluss nach demselben Archivvertrag eingefroren. Ein gewöhnliches Wiederöffnen des Archivs ist nicht beschlossen; Rückwechselrechte der Phasen 0..3 sind davon getrennt.

## Historische Installerreferenz

Die [neun DDL-Anweisungen des Altinstallers](legacy-schema-reference.md) sind als Quellreferenz erhalten. Sie entsprechen nicht automatisch dem tatsächlichen Bestand und ersetzen weder Feldvergleich noch neue versionierte Migration. Der Laufzeitbootstrap verwendet sie nicht.

## Additives Teilnahme-Modul (Block 5)

[Modulreferenz 1.0.0](schema-participation-1.0.0.sql) und eigenes Manifest ergänzen Hash-Codes sowie Rohdatenhistorie, ohne den eingefrorenen Storage-/Shadow-Plan zu verändern. Bestandscode-Verbrauch bleibt ohne ALTER/DELETE in der Quelltabelle nachvollziehbar. Team-/Benutzer-IDs bleiben maßgeblich; keine aktive Generation oder Archivsnapshots durch dieses Paket. Installation, Engine-Schutz und Übergangsgrenzen in [Block 5](block-5-teams.md).
