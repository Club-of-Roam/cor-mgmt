# Block 3: Installation und kontrollierte Datenübernahme

Stand 2026-11-03. Implementiert und mit synthetischen Daten geprüft: Erstinstallation, lesende Vorprüfung, explizite Migration in getrennte Tabellen, Journal, Wiederaufnahme und Datenvergleich. Keine vorhandenen Anwendungsdaten migriert. Noch keine Umschaltung des laufenden Plugins und keine vollständige Archivfreigabe.

## Versionen und ausführbare Definition

- Plugin-Header weiterhin 1.2.10; Releaseziel 2.0.0.
- Legacy-Schemamarker `h3_mgmt_db_version` weiterhin 2.8. Er wird bei einer Erstinstallation erst nach erfolgreicher Tabellenprüfung gesetzt; bei einer Bestandskopie nicht angehoben.
- Neue Speicherstruktur `cor_mgmt_storage_version`: 3.0.0. Dies kennzeichnet die zusätzliche Infrastruktur, keine aktivierte Fachmigration.
- Konkreter Migrationsschritt: `legacy-shadow-v1`; Manifest-/Payloadformat: 1. Journal und Schrittfassung werden separat geprüft. Unbekannte Versionen werden abgewiesen.
- [Catalogue.php](../src/Infrastructure/WordPress/Schema/Catalogue.php) ist die ausführbare Definition. Die daraus erzeugte [SQL-Referenz](schema-3.0.0.sql) wird durch `composer check` auf Aktualität geprüft. Sie dient dem Review; direkte SQL-Ausführung umgeht die Eigentums- und Vorprüfungen.

## Erstinstallation

Die ausdrückliche WordPress-Aktivierung prüft Voraussetzungen und Administratorrolle. Nur bei einem leeren, eindeutig neuen Bestand werden die neun für den bestehenden Code benötigten Tabellen und sechs zusätzliche Migrationstabellen angelegt. Kein DDL bei gewöhnlichen Seitenaufrufen. Die Administrationsseite bietet einen geschützten Wiederholungsweg für eine unterbrochene Erstinstallation.

Alle neu angelegten Tabellen besitzen InnoDB, utf8mb4, Primärschlüssel und passende Beziehungsindizes. Legacy-kompatible Defaultwerte funktionieren im strengen SQL-Modus. `teammates.language` nimmt Sprachcodes auf; das Etappenfeld mit Bindestrich entspricht dem bestehenden Schreibadapter statt dem abweichenden historischen Installer. Bestehende Tabellen werden dafür nicht umbenannt oder verändert. Die technischen Mindestwerte sind MariaDB 10.11 oder MySQL 8.0.13; letzteres wegen Ausdrucksdefaults für Textfelder. Geprüfte Kombinationen stehen in verification.md, nicht jede zugelassene Mindestversion ist damit abgenommen.

Vor dem ersten CREATE wird ein Eigentumsmanifest mit Planhash geschrieben. Nach Unterbrechung werden ausschließlich eigene Tabellen mit passender Struktur, Eigentumsmarkierung und leerem Inhalt wiederverwendet. Vollständige Installationen können mit vorhandenen Daten wiederholt werden. Fremde Namenskollisionen, geänderte Pläne oder Strukturen werden nicht automatisch repariert, geleert oder übernommen. Typen, Nullbarkeit, Defaults, Autoincrement, Collation und Indexdefinitionen werden verglichen. DDL ist nicht vollständig transaktional; ein Fehler lässt prüfbare Teilschritte stehen. Die Laufzeit bleibt bei ausstehender Erstinstallation gesperrt.

Spezialtheme, Seitenzuordnung, Ticker-Eigenbetrieb und alle Fachabläufe sind dadurch noch nicht als komplette Neuinstallation abgenommen. Gemeinsame Ticker-Tabellen werden hier nicht ungefragt als Eigentum übernommen; Block 9 liefert deren Vertrag.

## Vorprüfung und Bericht

Unter Werkzeuge > cor-mgmt system status stehen explizite Datenbankwerkzeuge bereit. Die Vorprüfung liest direkt aus dem Schema und den neun Quelltabellen, ohne alte Fachmethoden, Mail, HTTP, Punkte- oder Freigabeberechnung aufzurufen. Keine Tabellen, Optionen, Transients oder Migrationsläufe werden durch die Vorprüfung angelegt.

Erfasst werden Schema-Metadaten einschließlich Zeichensatz/Collation, Engine, eindeutige IDs, Zeilenzahlen, SHA256-Vergleiche, fehlende/zusätzliche Felder, verwaiste Beziehungen, nicht zuordenbare Ergebnisplätze, unbekannte Rennphasen und ungeklärte Beträge. Benutzerreferenzen werden gegen die WordPress-Benutzertabelle geprüft, soweit verfügbar. Fehlende Quelltabellen, nicht eindeutige/ungültige IDs oder ein anderer Quellversionsmarker verhindern den Start. Zugelassen ist derzeit der belegte Bestand 2.8, kein geratener Universal-Upgrader.

Der angezeigte Bericht enthält nur Zähler, Hashes und aggregierte Befundcodes. Private Zeilen und vollständige Schema-Metadaten bleiben innerhalb der Datenbank. Sein Fingerprint bindet einen Start an den gerade geprüften Stand. Ein veralteter Fingerprint wird abgewiesen.

## Migrationsablauf

1. Wiederherstellbare Sicherung und gestoppte Quellschreibvorgänge bestätigen. Das betrifft auch Jobs, gemeinsame Tabellen und externe Writer. Die Oberfläche verlangt dies für Start und Fortsetzung; sie stoppt fremde Prozesse nicht selbst.
2. Zusätzliche Tabellen unter separaten Namen anlegen/prüfen, bestehenden Bestand unverändert lassen. Ein neuer Lauf erhält eine zufällige ID und ein persistiertes Manifest.
3. Pro Anfrage höchstens 100 Quellzeilen verarbeiten; die interne API erlaubt 1 bis 500. Der Browser setzt fort, solange der Lauf läuft. Schließen der Seite beendet nur die Anfragen, nicht das Journal. Fortsetzen ist nach erneuter Anmeldung/Nonce möglich.
4. Kopierte Zeilen, Projektionen und Cursor werden gemeinsam in einer InnoDB-Transaktion gespeichert. Ein fehlgeschlagener Batch hinterlässt weder halbe Ergebnisse noch vorgerückten Cursor. Frühere abgeschlossene Batches bleiben erhalten.
5. Ein datenbank-/präfixbezogener Advisory-Lock verhindert parallele Werkzeugläufe. Verlorene Verbindung/Locks werden vor dem Commit erkannt. Dieser Lock sperrt keine fremden Writer; er ersetzt kein Wartungsfenster.
6. Nach allen Batches Quelle erneut prüfen. Zeilenzahlen, IDs, bytegenaue Inhalte und jede erzeugte Projektion einschließlich zusätzlicher Zielzeilen vergleichen. Eine geänderte Quelle, manipulierte Kopie oder andere Schrittfassung verhindert die Verifikation.
7. Ergebniszustand `verified` bedeutet geprüfte Kopie zu diesem Zeitpunkt. Er setzt weder eine aktive Generation noch Live-Rennphasen oder einen neuen Legacy-Schemamarker. Nach erneuten Quelländerungen ist diese Kopie kein automatisch aktueller Umstiegsstand.

Fehlercodes enthalten keine SQL-Anweisungen, Inhalte oder ursprünglichen Ausnahmemeldungen. Fehler-/Abbruchläufe behalten Daten und Journal. Ein ausdrückliches Schließen markiert einen Lauf als verlassen, löscht aber nichts; erst danach kann ein neuer abweichender Stand vorbereitet werden. Eine als aktiv ausgewählte Generation darf so nicht geschlossen werden. Keine automatische Rückmigration oder Bereinigung.

## Feldmapping und Zwischenstrukturen

| Quelle | Neue Ablage | Behandlung |
|---|---|---|
| Alle Spalten aller neun eigenen Tabellen | `cor_mgmt_migration_rows` | Lauf-ID, Quelltabelle, originale ID, Payload und Zeilenhash. Unbekannte Felder/Meta bleiben erhalten |
| Schema-/Zeichensatzdefinitionen, Zähler und Befunde | `cor_mgmt_migration_runs.manifest` | Private Wiederaufnahme-/Vergleichsbasis ohne kopierte Zeilen |
| `races.setting.status` | `cor_mgmt_race_states` | 0/1/2 unverändert; belegter Ausgangsstatus 3 wird als Ziel 4 vorgemerkt; unbekannte Werte bleiben unzugeordnet und im Original erhalten |
| `stages.id/race_id/route_id/number` | `cor_mgmt_stage_keys` | Originale IDs; keine erzwungene Eindeutigkeit historisch doppelter Etappennummern |
| `teams.rank_stage_n`, `extra_stage_n`, `amount_extra_stage_n`, `vary_extra_stage_n` | `cor_mgmt_stage_results` | Ein Datensatz je historischer Team-/Slotzuordnung; keine neuen sechs Spaltengruppen. Etappen-ID nur bei eindeutiger Zuordnung, sonst NULL und Befund. Keine Berechnung |
| `teams.total_points` und weitere historische Werte | Unveränderter Zeilen-Payload | Kein Aufruf der neuen oder alten Punkteformel, kein stilles Korrigieren |
| `sponsors.donation`, `teams.donation_goal` | `cor_mgmt_legacy_amounts` | Ursprüngliche Bytes erhalten; Centbetrag/Währung NULL, Einheit ausdrücklich unresolved bis Block 7 |
| Einladungen, numerische Codes, Benutzer-/Teamzuordnungen | Unveränderter Zeilen-Payload | Keine Tokenrotation und keine geratenen Ablaufdaten; sicherer neuer Tokenpfad folgt mit Block 5 |

Die Payloads enthalten pro Feld HEX-Werte der ursprünglichen Speicherbytes oder NULL. Damit werden latin1/utf32, Binärdaten, leere Werte und unbekannte Metadaten ohne blindes Umdekodieren erhalten. Die neuen strukturierten Tabellen verwenden utf8mb4; dies ist keine pauschale Konvertierung des alten Live-Schemas. Die Rennphasen-JSON wird ausschließlich anhand der erklärten Spalten-Collation mit nachgewiesenem Byte-Roundtrip gelesen; auch utf32-Konfigurationen werden dadurch erkannt. Ungültige oder nicht unterstützte Zeichensätze werden nicht geraten. Die endgültigen Fachrepositories benötigen weiterhin ihre Darstellungsprüfungen. Quell-/Ziel-IDs werden bis zum vollen Bereich von BIGINT UNSIGNED ohne Float-Konvertierung verarbeitet.

Keine kaskadierenden Fremdschlüssel und keine neuen Eindeutigkeitsannahmen löschen oder verändern historische Orphans. Alle neuen Tabellen bleiben bei Deinstallation erhalten.

## Bewusst noch offen

- Aktive Reader-/Writer-Umschaltung pro Fachmodul, exakte Zielentitäten und Rückweg bei neuen Schreibvorgängen: mit Blöcken 4 bis 9, vor Release abnehmen. Die Tabellen dieses Pakets sind kontrollierte Zwischenstrukturen, kein vollständig umgestelltes v2-Fachmodell.
- Vollständiger Archivsnapshot einschließlich historischer Benutzerdarstellung, Medien, Ticker und Spenden sowie gesperrter Writer und eingefrorener Rangfolge: mit den betroffenen Modulen. Das geplante Phasenmapping ist implementiert, das publizierte Archiv noch nicht.
- Bestätigte historische Betragseinheiten/Währungen und aktive Zahlungswege: Block 7; Unklarheiten werden nicht durch Multiplikation oder angenommene EUR-Werte verdeckt.
- Neue Einladungstokens, Phasen-/Teilnahmevoraussetzungen und Nebenläufigkeit der fachlichen Schreibabläufe: Block 5.
- MySQL-/weitere Versionsmatrix, Browser-/Accessibility-Gesamtabnahme, vollständiges Standardtheme-Setup und Wiederherstellung des fertigen Releases: vor Release. Keine dieser Abnahmen folgt allein aus einem grünen Migrationsfixture.

## Primärquellen

- [WordPress: vorbereitete Werte und Identifier mit wpdb::prepare](https://developer.wordpress.org/reference/classes/wpdb/prepare/)
- [MySQL: Ausdrucksdefaults und Textfelder](https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html)
- [MariaDB: Advisory-Locks und Verbindungslebensdauer](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock)

- [MySQL: latin1 entspricht Windows-1252](https://dev.mysql.com/doc/refman/8.4/en/charset-we-sets.html)
