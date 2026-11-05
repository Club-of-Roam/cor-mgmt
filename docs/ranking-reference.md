# Ergebnisse und Ranglisten: ausführbare Bestandsreferenz

Prüfdatum 2026-11-02. Echte Legacy-Methoden mit ausschließlich synthetischen Daten und Speicher-/Darstellungs-Doubles. Kein WordPress-Bootstrap, keine Datenbank, kein Versand. Anwendungscode unverändert. Die Charakterisierung bewahrt auch Fehler als reproduzierbaren Ist-Befund, nicht als v2-Sollverhalten.

## Ergebnisabgabe

Quelle: [save_ranking](../includes/class-h3-mgmt-races.php). Tests: [RankingSubmissionTest](../tests/Characterization/RankingSubmissionTest.php).

| Fall | Beobachtetes Ergebnis |
|---|---|
| Platz 1, keine Boni | 20 Punkte; vorherige gespeicherte Summe wird ersetzt, nicht erhöht |
| Andere Etappe Platz 1, aktuelle Etappe Platz 2 | 20 + 17 = 37; numerischer Platz als Zeichenfolge ebenfalls verarbeitet |
| Andere Etappe Platz 2 plus Ein-Punkt-Flag und fester Bonus 5, aktuelle Etappe Platz 1 | 17 + 1 + 5 + 20 = 43 |
| Aktuelle Etappe Platz 1, beide festen Bonusfelder übermittelt | 26, obwohl die UI-Funktionsschalter deaktiviert sind |
| Bonusfelder enthalten Zeichenfolge »0« | Bloßes Vorhandensein aktiviert die beiden Flags weiterhin |
| Variable 7 auf Etappe 5 übermittelt | Ignoriert, gespeichert als 0 |
| Etappe 6 Platz 1, Boni 1 + 5 + 7, andere Etappe Platz 2 | 50, variable Punkte der aktuellen sechsten Etappe werden übernommen |
| Andere Etappe enthält variable Zusatzpunkte 5 und Platz 2, aktuelle Etappe Platz 1 | Summe 37: die 5 Zusatzpunkte fehlen |
| Andere Etappe enthält variable Zusatzpunkte 1 | TypeError auf PHP 8; Feldnamensstring wird addiert |
| Fehler tritt erst beim zweiten Team auf | Erstes Team wurde bereits an den Datenbankadapter zum Schreiben übergeben; keine Rücknahme dieses Aufrufs |
| Disqualifiziert (99) mit Boni 1 + 5 + 7 | Platzpunkte 0, Boni bleiben: Summe 13 |
| Unplatziert (0) mit Ein-Punkt-Flag | Summe 1 |
| Etappe 7 übermittelt | Akzeptiert, schreibt rank_stage_7 und Bonusfelder; Summe ignoriert diese Etappe, da Summenschleife nur 1..6 umfasst |

Die komplette Datenübergabe an update einschließlich Team-ID, Feldern und Formatliste wird geprüft. Der Testadapter schreibt nur in Speicher. Er belegt keine erfolgreiche echte SQL-Ausführung, kein Transaktionsverhalten der verwendeten Tabellen-Engine und keine Berechtigungsprüfung des WordPress-Controllers.

**Bestätigter Fehler RANK-01:** Variable Zusatzpunkte anderer Etappen werden entweder ausgelassen oder lösen TypeError aus. **RANK-02:** Eine später scheiternde Berechnung kann eine zuvor begonnene Mehrteam-Verarbeitung teilweise ausgeführt hinterlassen. Die Tests belegen die Aufrufreihenfolge und fehlende Rücknahme im Handler; eine echte DB-Integration steht aus.

**Validierungslücken:** Funktionsschalter, Bonuswerte, Etappengrenzen und Routenberechtigung am Schreibzugriff sichern. Die Fachentscheidung D-38 vom 2026-11-05 bestätigt für neue Wertungen Zusatzpunkte bei Platz 0/99 und variable Zusatzpunkte ausschließlich auf Etappe 6. Historische Ergebnisse bleiben unverändert; siehe [Entscheidungen](decisions.md).

## Ranglistenanzeige

Quelle: [ranking_table](../includes/class-h3-mgmt-races.php). Tests: [RankingDisplayTest](../tests/Characterization/RankingDisplayTest.php).

| Fall | Beobachtetes Ergebnis |
|---|---|
| Phase 0, 1 oder 2 | Hinweis statt Tabelle; Teamrepository wird nicht abgefragt |
| Summen 100, 100, 90 | Gesamtränge 1, 1, 3; bei getrennten Routen eigene Routenränge |
| top als Ganzzahl 0, drei Teams | Nur ein Team erscheint: Standardwert setzt max auf Teamzahl, spätere Abbruchprüfung nutzt jedoch Etappenvariable i=7 |
| top als Zeichenfolge »0« | Alle Teams; strikter Vergleich mit Ganzzahl 0 führt zu anderem Zweig und deaktivierter Grenze |
| top als Zeichenfolge »2« | Nur ein Team statt zwei |
| top als Zeichenfolge »8«, zehn Teams | Alle zehn Teams statt acht |
| Erstes Team hat Summe 999 | Gesamt- und Routenrang 0 statt 1, wegen Initialwert previous=999 |

**RANK-03:** Top-Grenze nutzt Etappenzähler statt Teamzähler und unterscheidet Ganzzahl-/Zeichenfolge-Null. **RANK-04:** Initialer Punkteschwellenwert 999 verhindert korrekten ersten Rang bei mindestens 999 Punkten. Test belegt den Grenzwert 999; höhere Werte sind aus dem Vergleich abgeleitet, nicht separat getestet.

Die Anzeige sortiert nicht selbst, sondern fordert absteigende Summen beim Repository an. Die Tests liefern bereits sortierte synthetische Datensätze; echte SQL-Sortierung und Reihenfolge bei Gleichstand sind ungeprüft. Routenfilter/Navigation und Sprach-/Themeintegration sind ebenfalls noch nicht umfassend automatisiert.

## Konsequenzen für Version 2 und Archiv

- Neue Berechnung isolieren und Grenz-/Fehlerfälle validieren; Fehlerkorrekturen erhalten separate Solltests. Die Ist-Tests ersetzen keine korrigierte Implementierung.
- Keine Neuberechnung historischer Summen. Alle Bestandsrennen mit Ausgangsstatus 3 werden gemäß [Migration](migration.md) unverändert archiviert.
- Rangpositionen werden im Altcode zur Anzeige neu abgeleitet. Deshalb reicht das Bewahren von total_points allein nicht: Archivmigration muss historische Rangfolge/Positionen und bekannte Darstellungsabweichungen erfassen, bevor ein korrigierter Renderer die historischen Ausgaben verändert. Entscheidung über mögliche Abweichungen ausweisen, nicht still korrigieren.
- Ob echte historische Datensätze von den synthetisch reproduzierten Fehlern betroffen sind, ist nicht nachgewiesen. Keine Anwendungssummen oder Datensätze wurden für diese Tests ausgelesen/verändert.
- Vollständige WordPress-, Datenbank-, Migrations- und Browserabnahme bleibt offen. Referenzfälle F-RANK-02/03 sind damit teilweise charakterisiert, nicht vollständig abgenommen.


## Umstellung Block 6 – 2026-11-05

Die obigen Tabellen beschreiben den eingefrorenen Altbestand. Der alte Writer wird jetzt ausschließlich durch [LegacyRankingReference](../tests/Support/LegacyRankingReference.php) charakterisiert und nicht ausgeliefert. Historische Ranglisten lesen weiterhin die bisherigen Summen mit dem bisherigen Renderer. Die neue [Scoring](../src/Domain/Scoring.php)-Berechnung gilt nur für ausdrücklich eingerichtete Rennen. Fehlerkorrekturen und Prüfgrenzen: [Block 6](block-6-results.md).
