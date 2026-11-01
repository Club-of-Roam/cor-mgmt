# Datenmodell und Migration

Stand: 2026-11-01, lesende SELECT/SHOW-Prüfung. Noch keine Migration implementiert oder ausgeführt.

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
- Vorprüfung/Probelauf ohne Änderungen, Batches mit Lock und Journal, idempotente Wiederaufnahme. Keine Massendatenmigration bei Seitenaufrufen.
- Neue Tabellen zunächst parallel; DDL nicht als vollständig transaktional rückrollbar behandeln. Bei Fehler keine automatische DROP-/Neuanlage.
- Umschaltung pro Modul, keine konkurrierenden Writer. Rückweg mit neuen Schreibvorgängen vor Freigabe festlegen.
- Vergleiche: Zeilenzahlen, IDs, Beziehungen, Status, Beträge, Zeichen, Vorlagen, Medien und Ranglisten. Berichte ohne private Inhalte in CI.

Exakte DDL, Feldmapping und Klassifikation sind Liefergegenstände von Block 3 vor Bestandsänderungen. Standard-Uninstall erhält Daten; explizite Bereinigung ist ein eigener geschützter Vorgang.
