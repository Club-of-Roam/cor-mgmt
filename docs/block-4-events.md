# Block 4: Rennen, Routen und Etappen

Stand 2026-11-04. Verwaltungsumbau im Kompatibilitätsbetrieb implementiert. Kein Versionssprung und keine aktive Datenmigration. Die bestehenden Tabellen und IDs bleiben für die laufenden Module maßgeblich; eine geprüfte Zwischenkopie wird nicht ausgewählt.

## Umsetzung und Quellen

| Schicht | Quelle und Verantwortung |
|---|---|
| Fachregeln | [RacePhase](../src/Domain/RacePhase.php) und [EventPolicy](../src/Domain/EventPolicy.php): fünf Phasen, explizite Feldgruppen, unbekannte Felder gesperrt |
| Anwendungsfälle | [EventEditor](../src/Application/Events/EventEditor.php): Erstellen, Speichern, Löschen, Phasenprüfung, Warnungen, Elternbeziehungen, optimistische Revisionen |
| Datenzugriff | [EventStore](../src/Application/Events/EventStore.php) und [LegacyEventStore](../src/Infrastructure/WordPress/LegacyEventStore.php): vorbereitete Identifikatoren/Werte, Vergleich vor Schreibzugriff, gemeinsame Sperre, Transaktionen |
| WordPress | [EventAdmin](../src/WordPress/EventAdmin.php): echte Nonces, Objektberechtigungen, Eingabebehandlung, Datum, Textübernahme und Routenkonten |
| Darstellung | [EventList](../src/WordPress/EventList.php), [EventForm](../src/WordPress/EventForm.php): WordPress-Tabellen, native Formulare und kontextgerechte Ausgabe |
| Kompatibilität | [Admin-Einstiegspunkte](../admin/class-h3-mgmt-admin-races.php): vorhandene Menüs delegieren; Formularmetadaten bleiben erhalten |

Die bisherigen großen Schreibcontroller und Tabellenkonstruktionen wurden ersetzt. Die Formulare behalten die vorhandenen Einstellungsfelder, Texte in beiden bisherigen Sprachen sowie die Übernahme von Informationstexten aus einem anderen Rennen. Unbekannte JSON-Einstellungen und Informationstext-Schlüssel bleiben bei Teiländerungen erhalten. Unveränderte Informationstexte werden nicht neu serialisiert. Passwörter bzw. WordPress-Passworthashes werden niemals vorausgefüllt.

## Verbindliche Feldzuordnung

| Gruppe | Felder / Verhalten |
|---|---|
| Teilnahmevoraussetzungen, Phase 0 | Mindest-/Maximalgröße, Gebühr, Haftungsverzicht, Shirt-/Mobilangaben, Sichtbarkeit der Zusatzfragen |
| Kapazität/Startpunkte, Phase 0/1 | Maximale Teams, Startpunktmodus, feste Startroute; Startroute muss zum Rennen gehören |
| Darstellung, Phase 0/1/2 | Renn-/Routenname, Logo, Farbe, Informationstexte, bisherige Darstellungs-/Spendenlinkfelder |
| Wertungsvorgaben, Phase 0/1/2 | Feste/variable Zusatzpunktschalter und Höhe; keine Ergebnisberechnung |
| Organisation, Phase 0 bis 3 | Datum, aktives Rennen, Ticker-/Spendenfunktionsschalter, Routenverantwortliche, Etappen-Treffpunkt |
| Struktur, Phase 0 | Rennen/Routen/Etappen erstellen, Elternzuordnung, Etappennummer/-ziel/-land, zulässige Löschung |
| Archiv, Phase 4 | Keine Verwaltungsänderung; Abschlussfunktion bleibt bis zum vollständigen Archivsnapshot gesperrt |

Routenname ist Darstellung; Etappenziel und Land sind Streckenstruktur. Treffpunkt ist organisatorischer Hinweis. Informationstexte des Bestands sind gemeinsam gespeicherte redaktionelle Inhalte und deshalb ab Start gesperrt. Eine zukünftige Trennung organisatorischer Renntexte erfordert eigene Felder; bestehende gemischte Texte werden nicht pauschal freigegeben.

Zahlungsweg-Einstellungen werden lediglich erhalten. Ein gesetzter Spendenfunktionsschalter ist keine fachliche Freigabe eines Zahlungsanbieters; die bestehenden Sicherheitsgrenzen bleiben bestehen. Die eigenständige Fachprüfung gehört zu Block 7.

## Phasen, Validierung und Historie

- Berechtigung und aktuelle Phase werden beim Speichern erneut geprüft. UI-Sperren ersetzen diese Prüfungen nicht. Ein Formular enthält eine Revision des Datensatzes und seines Rennens; veraltete Stände werden abgewiesen.
- Rückwechsel zwischen 0 und 3 stellen die Rechte der Zielphase wieder her. Phase und neu freizugebende/zu sperrende Einstellungen getrennt speichern, damit ein kombinierter Request Sperren nicht umgeht.
- Teamgrößen: 1 bis 100, Minimum nicht größer als Maximum. Kapazität: 0 bis 32767 entsprechend dem Kompatibilitätsfeld. Etappennummern: 0 bis 6, je Route eindeutig bei neuen Strukturänderungen. Allgemeine Etappenanzahl und Ergebnisprojektion mit Block 6 erweitern; bestehende abweichende Werte nicht massenhaft korrigieren.
- Daten werden in WordPress-Zeitzone geprüft. Unveränderte Kalendertage behalten ihren exakten bisherigen Zeitstempel. Keine automatische Phase, Punkteberechnung oder Mail durch Datumskorrektur.
- Nur vollständige Teams belegen Kapazität. [get_free_slots](../includes/class-h3-mgmt-races.php) verwendet den gespeicherten Status, ruft keine freigabeverändernde Abfrage auf und liefert mindestens null.
- Kapazität unter Belegung bzw. geänderte Startpunkte mit vorhandenen Teams: erst Warnung ohne Schreibzugriff, dann ausdrücklich bestätigen. Bestehende Teams/Zuordnungen bleiben unverändert. Dynamisch entstandene Konflikte werden beim Speichern erneut ermittelt.
- Elternwechsel prüfen Quell-/Zielrennen und Rechte. Eine belegte Route wird nicht in ein anderes Rennen verschoben. Löschen referenzierter Struktur ist gesperrt; keine Kaskadenlöschung. Ein aktives Rennen kann nicht direkt gelöscht werden.
- Phase 4 ist unveränderlich. Die normale Verwaltung kann noch kein Rennen abschließen: Teilnehmer-/Medien-/Ticker-/Spenden-Snapshots und die vollständige Absicherung aller Modulwriter fehlen noch. Ein bloßes Statusfeld wäre kein vollständiges Archiv.

## Nebenläufigkeit und Speichergrenzen

Alle neuen Verwaltungswriter nutzen dieselbe datenbank-/präfixbezogene Sperre wie die Migrationswerkzeuge. Ein zweiter Verwaltungsaufruf wird bei belegter Sperre abgewiesen. Vergleichende Updates prüfen alle ursprünglichen Felder bytegenau und verhindern das Überschreiben zwischenzeitlicher Datensatzänderungen.

Mit InnoDB werden aktuelle Datensätze unter Transaktion und Zeilensperren gelesen. Für eine bereits geöffnete Transaktion werden Savepoints verwendet. Der Wechsel des aktiven Rennens setzt bisherige aktive Rennen und das Ziel gemeinsam; bei Fehler erfolgt ein Rollback. Eine ausgewählte Archivzeile wird dabei nicht verändert. Sammellöschungen validieren alle Datensätze vor dem ersten Löschen und rollen Fehler zurück.

Mehrfachänderungen, Kapazitäts-/Startpunktprüfungen und Kontoerstellung setzen transaktionale Rennen-, Routen-, Etappen- und Teamtabellen voraus. MyISAM wird nicht automatisch umgestellt. Andernfalls bleibt die Aktion mit Hinweis auf kontrollierte Datenbankprüfung gesperrt; einfache vergleichende Einzelupdates bleiben möglich. Dies ist ein bewusster Übergangsschutz und keine abgeschlossene Engine-Migration.

Direkte Fremdwriter und die noch nicht umgestellten Teilnehmerabläufe sind nicht vollständig durch diese Verwaltungssperre koordiniert. Normale Routenzuweisung/Freigabe und ihr gemeinsamer Kapazitätsvertrag werden in Block 5 umgestellt und unter parallelen Teilnehmerzugriffen geprüft. Die bestehenden Sicherheitsprüfungen bleiben aktiv; keine vollständige Nebenläufigkeitsfreigabe aller Module ableiten.

## Routenkonten und Oberfläche

Vorhandene WordPress-Benutzer können als Routenverantwortliche zugeordnet werden. Die Zuweisung ist berechtigten Rennverwaltern vorbehalten; sie erteilt bestehenden Benutzern keine zusätzlichen Rechte. Optionale neue Konten erfordern außerdem create_users, passende Passwörter und freie Login-/Mailadresse. Erstellung erfolgt erst nach Fachvalidierung und Warnbestätigung. Neue Konten erhalten begrenzte eigene Verwaltungsrechte; ein fehlgeschlagener Schreibvorgang räumt nur das gerade erzeugte Konto auf. Bestehende Kontoauswahlen berücksichtigen sowohl historische Rollenkonten als auch neu zugewiesene Konten. Keine neuen Versandaktionen.

Tabellen sind serverseitig auf 50 Zeilen begrenzt, sortierbar und nach Rennen filterbar. Etappen behalten zusätzlich Filter nach Route, Nummer und Land. Routen zeigen belegte Plätze/Kapazität. Einzel- und Sammelaktionen verwenden POST/Nonces. Formulare verwenden native Datumseingaben, zugeordnete Labels/Hilfetexte, aufklappbare Abschnitte und sichtbare Phasensperren. Keine neue JavaScript-/Framework-Laufzeitabhängigkeit.

## Prüfung und verbleibende Arbeit

[EventPolicyTest](../tests/Foundation/EventPolicyTest.php) und [EventManagementTest](../tests/Integration/EventManagementTest.php) prüfen die Fachgruppen über alle fünf Phasen, echte WordPress-Rechte/Nonces, Warnbestätigung, Datenerhalt, veraltete Formulare, Schema-/Kontogrenzen, Sperrkonflikt, Rollback und schreibfreies Rendering. Die ehemalige Charakterisierung unvollständiger Routenplätze wurde durch den Regressionstest für die bestätigte Zielregel ersetzt.

Aktuelle Ergebnisse und nicht ausgeführte Abnahmen: [verification.md](verification.md). Vollständige Browser-/Tastaturabnahme, aktive Fachmigration, vollständiger Archivsnapshot sowie integrierte Teilnehmer-/Ergebnis-/Provider-/Tickerabläufe bleiben bei den zugeordneten Folgeblöcken. Theme, cor-ticker und externe Upload-Einstiege wurden in diesem Block nicht geändert.
