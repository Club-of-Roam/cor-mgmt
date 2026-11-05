# Block 6 – Ergebnisse, Punkte und Ranglisten

Stand 2026-11-05. Neue Wertung im Kompatibilitätsbetrieb implementiert; vollständige Archiv-, Bestands- und Browserabnahme bleibt offen. Plugin- und bisherige Schema-/Migrationsversionen bleiben unverändert.

## Fachlogik und Speicher

[Scoring](../src/Domain/Scoring.php) berechnet die bisherigen Platzpunkte 20/17/14/12/10/8/6/5/4/3/2/1 für Platz 1..12; 0 und 99 ergeben keine Platzpunkte. Neue Summen addieren gespeicherte Etappenergebnisse, einschließlich variabler Zusatzpunkte von anderen Etappen. Ein fixer Bonus besitzt seinen eigenen Schalter. Ranggleichheit verwendet Wettbewerbsränge 1/1/3; gleiche Punktzahlen werden nach Team-ID stabil sortiert. Weder die Zahl 999 noch die gewünschte Zeilenzahl beeinflusst die Rangberechnung.

Fachlich bestätigt am 2026-11-05 (D-38): Zusatzpunkte bleiben bei fehlender Platzierung (0) und Disqualifikation (99) möglich. Variable Zusatzpunkte bleiben bei aktiviertem Funktionsschalter ausschließlich auf Etappe 6 verfügbar. Beide Entscheidungen entsprechen der implementierten Logik; der bestehende technische Eingabebereich 0..255 bleibt unverändert. Historische Ergebnisse werden nicht neu berechnet.

[ResultManager](../src/Application/Results/ResultManager.php) hängt nur von Store, Berechtigungsfunktion und Uhrzeit ab. [ResultRepository](../src/Infrastructure/WordPress/ResultRepository.php) übernimmt WordPress/SQL. Eine Etappe wird durch ihre ID bestimmt; eine mehrdeutige Nummer innerhalb einer Route blockiert die Eingabe. Etappe 0 ist ein Startpunkt und erhält kein Ergebnis. Die Verwaltung akzeptiert weitere Etappennummern bis 255 entsprechend dem bestehenden Etappenfeld; neue Wertungen sind für Platz-/feste Zusatzpunkte nicht auf sechs Slots beschränkt.

| Tabelle des Ergebnis-Moduls 1.0.0 | Inhalt |
|---|---|
| cor_mgmt_scoring_profiles | Explizite Wertungsversion je Rennen |
| cor_mgmt_results | Ein Datensatz je Team-/Etappen-ID; Rennen, Route, Platz, Boni, Punkte, verwendete Regelwerte und Rechenversion |
| cor_mgmt_result_history | Handelnder Benutzer, Zeitpunkt, vorherige Ergebniszeilen, neue Eingaben, Summen und Regelwerte |

Alle drei Tabellen verwenden InnoDB und utf8mb4. [SQL-Referenz](schema-results-1.0.0.sql). Manifest cor_mgmt_results_install ist unabhängig vom Teilnahme-Modul, Plugin-Release, Storage 3.0.0 und Legacy-Marker 2.8. Die Definitionen von legacy-shadow-v1 bleiben unverändert. Dessen gleichnamig klingende Stage-Projektion ist weiterhin nur eine Zwischenkopie und kein Live-Writer.

Die ersten sechs Legacy-Slots und total_points werden ausschließlich für neue Wertungen als Kompatibilitätsprojektion mitgeschrieben. Anzeige und neue Summierung lesen die normalisierten Ergebnisse. Historische Rohfelder und Tabellen werden nicht gelöscht. Summen oberhalb der noch verwendeten unsigned-int-Projektion werden vor Schreibbeginn abgewiesen.

## Einrichtung und Eingabe

1. Ergebnis-Modul bei Plugin-Aktivierung oder ausdrücklich im Systemstatus einrichten. Keine Schemaänderung während normaler Seitenaufrufe; keine automatische Engine-Konvertierung. Unbekannte Tabellen/Manifestversionen und unvollständige abgeschlossene Installationen verlangen Prüfung.
2. Im Rennformular »Enable scoring 2.0 for this race« wählen. Nur berechtigte Organisatoren, POST und passende Nonce; nur Phase 0, ohne vorhandene historische Punkte oder Ergebnisflags, einschließlich noch keiner Route zugewiesener Teams. Ein Schema-Setup allein aktiviert kein Rennen.
3. Bestehenden Shortcode h3-submit-rankings verwenden. Er bietet ausschließlich erlaubte Routen des aktiven Rennens an. Auch Organisatoren können eine berechtigte Route auswählen; ein Routenbenutzer wird nicht auf seine Route eines fremden Rennens verwiesen.
4. Ergebnisse nur in Phase 3 eingeben. Jede aktuelle vollständige Mannschaft der gewählten Route muss genau einmal vorhanden sein. Fremde/fehlende Teams, unbekannte Plätze, deaktivierte Boni, ungültige Zahlen, Startpunkte und unbekannte/mehrdeutige Etappen werden zurückgewiesen.

Formulare verwenden native beschriftete Bedienelemente, eine Revision und Nonces. Änderungen von Phase, Regeln, Teambelegung, Etappen und Ergebnissen machen alte Formulare ungültig. Die Journalgeneration verhindert auch ein Überschreiben nach zwischenzeitlicher Änderung und Rückkehr zu denselben Werten. Identisches erneutes Speichern verändert weder Ergebnisse noch Journal. Fehlermeldungen fordern zum erneuten Laden auf.

Alle Zeilen werden zuerst geprüft. Ergebnisse, Kompatibilitätswerte und Journal stehen unter derselben Datenbanksperre und Transaktion wie Renn-/Teilnehmerwriter. Fehler bei späteren Teams oder beim Journal rollen die ganze Aktion zurück. Benötigte Bestandstabellen müssen ebenfalls InnoDB verwenden. Vorhandene fremde Ergebnisidentitäten werden nicht durch Upserts übernommen.

Ein erlaubter Phasenrückwechsel darf Teams zurückstufen und ihre Routenplätze freigeben. Bereits gespeicherte Ergebniszeilen und Summen bleiben dabei erhalten; eine spätere Zuweisung zu derselben Ergebnisroute ist möglich. Der Wechsel zu einer anderen Route mit vorhandenen Ergebnissen verlangt weiterhin einen kontrollierten Übertrag. Auch ein gespeichertes Null-Punkte-Ergebnis verhindert das Löschen seines Teams. Gewertete Etappen lassen sich auch nach Phasenrückwechsel nicht umnummerieren, umhängen oder löschen. Solche Änderungen benötigen einen gesonderten kontrollierten Ergebnisübertrag; dafür wird hier keine stille Umrechnung angeboten. Phase 4 bleibt unveränderlich.

## Anzeige und Historie

Die bestehenden Shortcodes und Attribute bleiben erhalten. Die neue Rangliste zeigt Gesamt-/Routenrang, Punkte, dynamische Etappenspalten, Namen und Teamverweise. top begrenzt die tatsächlich ausgegebenen Teams; show_title, show_stages und show_nav steuern die jeweiligen Bestandteile. Ein Routenfilter verändert den Gesamtrang nicht. Die Ausgabe behandelt Namen und Inhalte als untrusted Daten.

Rennen ohne neues Wertungsprofil behalten den bisherigen historischen Renderer und die gespeicherten Summen. Auch dessen bekannten alten Top-/999-Eigenheiten werden hier nicht still für historische Rennen umgedeutet. Der fehlerhafte alte Schreibpfad ist aus dem ausgelieferten Anwendungscode entfernt und lebt ausschließlich in [LegacyRankingReference](../tests/Support/LegacyRankingReference.php) für die Charakterisierung weiter. Ergebniswriter ohne neues Profil sind gesperrt.

[HistoricalComparison](../src/Application/Results/HistoricalComparison.php) liefert einen nur lesenden Vergleich. Ein Administrator kann ihn im Rennformular mit POST/Nonce als JSON herunterladen. Der Bericht enthält Team-IDs, gespeicherte Summe, hypothetische Summe, Differenz, Unklarheiten und einen Hash der ursprünglichen Ergebnisfelder. Er enthält keine Namen, Kontaktdaten oder Zugangsdaten. Nicht eindeutige Etappenzuordnungen, unbekannte Werte oder unklare Regeln erhalten keine geratene Vergleichssumme.

Der Kandidat ist eine arithmetische Diagnose mit dem vorhandenen festen Bonuswert und den Rohflags, keine nachgewiesene historische Sollwertung. Frühere manuelle Korrekturen oder andere damalige Regeln können Abweichungen erklären. Weder Download noch Anzeige ändern Daten, Freigaben, Routen, E-Mails oder Zahlungen. Archivrennen werden nicht neu berechnet.

## Prüfungen und noch offene Abnahme

[ScoringTest](../tests/Foundation/ScoringTest.php), [ResultManagementTest](../tests/Integration/ResultManagementTest.php), [ResultsInstallationTest](../tests/Integration/ResultsInstallationTest.php) und [ResultConcurrencyTest](../tests/Integration/ResultConcurrencyTest.php) prüfen Rechenfälle, Schutzgrenzen, Rollback, Wiederholung, Installation und tatsächliche parallele PHP-Prozesse mit synthetischen Daten. PHP-Matrix und ausgeführte Gesamtprüfungen: [Prüfbelege](verification.md).

Noch offen und ausdrücklich keine Freigabe:

- Historischen Vergleich auf fachlich geprüften Bestandsrennen beurteilen; Rohwerte und Anzeige bleiben bis dahin maßgeblich. Keine Anwendungsmigration oder Aktivierung wurde ausgeführt.
- Vollständigen Archivsnapshot einschließlich Teilnehmeridentitäten, Medien, Zahlungen und Ticker sowie neue Modulstände in den abschließenden Migrations-/Archivplan aufnehmen. Die bisherige Zwischenkopie allein deckt das Ergebnis-Modul nicht ab. Ein Phasen-3-Rennen mit neuem Profil muss dabei von ursprünglich historischen Bestandsrennen unterschieden werden.
- Browser-, Tastatur- und Barrierefreiheitsprüfung der Eingabe/Ranglisten, Theme-Anbindung und visuelle Freigabe durchführen. Native HTML-Tests ersetzen diese Abnahme nicht.
- Größere Bestände auf Laufzeit und Darstellbarkeit prüfen; Ergebnisformulare bearbeiten gegenwärtig die vollständige Route, keine zeitversetzten Teilbatches.

Block 7 beginnt mit der fachlichen Prüfung der Zahlungswege. Die offenen Archiv- und Abnahmearbeiten bleiben ihren Folgeblöcken zugeordnet und werden nicht durch diesen Einstieg in die neue Wertung erledigt.
