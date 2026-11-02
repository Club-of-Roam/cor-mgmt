# Funktionsreferenz – Block 0

Stand 2026-11-02, aus Code und Referenztests abgeleitet. Keine vollständige Website-Abnahme. Details: [Shortcodes](shortcodes.md), [Request-Aktionen](actions.md).

Zielergänzung 2026-11-02: [Phasenmatrix](phases.md) mit neuer fünfter Phase »Rennen beendet« als unveränderliches Archiv. Ist-Aussagen unten beziehen sich weiter auf den alten Code. Geplante Abnahme zusätzlich für Abschluss/Archivschreibsperre und Migration aller alten Rennen mit Ausgangsstatus 3 ohne Neuberechnung; noch nicht ausgeführt.

## Akteure

Besucher: öffentliche Teams, Spenden, Ranglisten, Ticker/Karten und Spendenablauf. Teilnehmer: eigene Daten, Team und Börsennachricht. Routenbenutzer: Ergebnisabgabe für die zugeordnete Route. Administrator/Custom-Rolle: freigegebene Verwaltungsbereiche.

Capabilities aus H3_MGMT::extra_caps: h3_mgmt_send_emails, h3_mgmt_edit_autoresponses; h3_mgmt_view_races/edit_races/edit_own_races/delete_races/delete_own_races; h3_mgmt_view_teams/view_own_teams/edit_teams/edit_own_teams/delete_teams/delete_own_teams; h3_mgmt_view_sponsors/view_own_sponsors/edit_sponsors/edit_own_sponsors; h3-mgmt-view-statistics. Kurzschreibweisen nach Schrägstrich besitzen denselben h3_mgmt_-Präfix. Vorhandene Menürechte sind kein Nachweis geschützter Schreibaktionen. Members-Integration und Zuweisung beim init vorhanden.

## Referenzabschluss

Block 0 als Referenzphase abgeschlossen; sämtliche Bereiche sind im [Referenzkatalog](reference-catalog.md) mit Ausgangslage, Aktion, Erwartung, Quelle und späterem Abnahmeblock beschrieben. Rollen/Hooks/externe Kopplungen in [interfaces.md](interfaces.md), Abschlussbelege und Grenzen in [block-0-acceptance.md](block-0-acceptance.md). Die Statusspalte unten bezeichnet Testtiefe, nicht den Abschlussstatus der späteren Fachmodule.

## Referenzszenarien

R = Referenz recherchiert, vollständige Modulausführung noch ausstehend. A = isoliert automatisiert. T = Teilregeln automatisiert, vollständige Modulabnahme offen. W = echte WordPress-/wpdb-Referenzfälle ausgeführt, keine vollständige Modulabnahme. Sicherheits-Sollfälle zusätzlich in Block 1 absichern. Vertiefung: [Fachregeln und Zustandswechsel](business-rules.md).

| ID | Ablauf / erwartetes Ergebnis | Quelle | Status |
|---|---|---|---|
| F-RACE-01 | Rennen anlegen/ändern, aktiv wählen; Historie bleibt abrufbar | admin/class-h3-mgmt-admin-races.php | R/W: Einstellungen im realen Shortcodepfad |
| F-RACE-02 | Status 2 schließt neue Anmeldung, 3 behandelt gestartetes Rennen; Einladungssonderweg gesondert prüfen | includes/class-h3-mgmt-teams.php:1630 | R |
| F-ROUTE-01 | Kapazitäten, Zuständigkeit und Etappen verwalten; volle Route korrekt behandeln | includes/class-h3-mgmt-races.php:377 | T/W: Auswahlfilter und echte SQL-Zählabweichung |
| F-TEAM-01 | Eigenes Team erstellen, Text/Bild/Spendenziel/Zusatzantworten ändern | includes/class-h3-mgmt-teams.php:2775 | R |
| F-TEAM-02 | Einladung versenden/annehmen; richtiges Team/Rennen, ungültiger Code ändert nichts | includes/class-h3-mgmt-teams.php:976 | T/W: Anzahl, Codeauflösung und Präzedenz; Annahme/Konkurrenz in 5 |
| F-TEAM-03 | Eigenes Team verlassen; im Bestand entfernt letztes Mitglied das Team, Spendenhistorie erhalten | includes/class-h3-mgmt-teams.php:1649 | R |
| F-TEAM-04 | Freigabe aus Mindestmitgliedern, Gebühr/Haftungsverzicht und aktivierten Pflichtangaben; Übergang einmal behandeln | includes/class-h3-mgmt-teams.php:420 | T/W: echte Status-/Routenwrites, einmalige Benachrichtigung |
| F-PROFILE-01 | Stadt, Telefon, Geburtstag, Shirt, Einwilligung und Avatar; keine unberechtigten Fremdänderungen | includes/class-h3-mgmt-profile.php | W: Hooks, Fremdschutz, Abschnittsfehler charakterisiert |
| F-RANK-01 | Plätze 1..12 ergeben 20,17,14,12,10,8,6,5,4,3,2,1; 0/99 ergeben 0, 99 kann disq. anzeigen | includes/class-h3-mgmt-races.php:1390 | A |
| F-RANK-02 | Zuständiger Benutzer gibt Etappe und Zusatzpunkte ab; andere Route darf nicht verändert werden | includes/class-h3-mgmt-races.php:858 | T: Summierung, Boni und Fehlerpfade; Controllerrechte offen |
| F-RANK-03 | Tabellen beachten Rennen/Route/Top/Navigation/Etappen; historische Ausgaben vergleichen | includes/class-h3-mgmt-races.php:1174 | T: Phasenanzeige, Gleichstände, Top und Ranggrenze; Filter/Navigation/Archiv offen |
| F-DON-01 | Betterplace: Team/Typ, Anbieter, Bestätigung, öffentliche Angaben; Abbruch ist nicht bezahlt | includes/class-h3-mgmt-sponsors.php:758 | R/W: URL-Parser, deaktivierter Einstieg, Minimalvalidierung; Provider in 7 |
| F-DON-02 | Sponsor/Owner, Schwellwerte, Sichtbarkeit und Zähler; Centverlust als Altfehler behandeln | includes/class-h3-mgmt-sponsors.php:1026 | T/W: Aggregation und bezahlte/versteckte SQL-Summen; Centmodell in 7 |
| F-DON-03 | Historische Verfahren und Admin bezahlt/unbezahlt/zeigen/verbergen erhalten; künftige Wege separat entscheiden | admin/class-h3-mgmt-admin-sponsors.php | R/T: Methodenaggregation; vollständiges Admin-CRUD in 7 |
| F-MAIL-01 | Selbsttest, Einzel-/Teilnehmergruppen; automatische Vorlage nach Aktion/Sprache | admin/class-h3-mgmt-admin-emails.php | W: Vorlagen/Sprache/Empfänger, Race-Gruppe und Selfdefekt |
| F-XCH-01 | Eigene Nachricht erstellen/ersetzen, leer löscht; laufendes Jahr ohne Team im aktiven Rennen anzeigen | includes/class-h3-mgmt-xchange.php | W: CRUD, Gast, Fremd-ID-Defekt, Jahres-/Teamfilter |
| F-TICK-01 | Text, Bild, Kommentar, Video, Position; nur berechtigte Zuordnung/Änderung | includes/class-h3-mgmt-ticker.php:182 | W: Text/Kommentar/Position/Video; Bilder/Negativrechte in 9 |
| F-TICK-02 | Listen/Karte nach Rennen/Route/Team; Navigation und historische Medien | includes/class-h3-mgmt-ticker.php:659 | R/W: Sender-/Renn-/Typfilter; Karte/Browser in 9 |
| F-TICK-03 | Gateway ordnet synthetische Nummer zu; unbekannt/unvollständiges Team gesondert | includes/class-h3-mgmt-ticker.php:1433 | R/W: leerer Eingang; externer Vertrag in 9 |
| F-TICK-04 | Lokale deutsche, 0049-/+49-Nummer und Trennzeichen normalisieren identisch | includes/class-h3-mgmt-ticker.php:22 | A |
| F-ADMIN-01 | CRUD und Sortierung; Sichtrecht erlaubt nicht automatisch Änderung | admin/class-h3-mgmt-admin.php | R/W: Menü-/Capregistrierung; sichere CRUD-Handler in 1/Modulen |
| F-STAT-01 | Teilnehmer-/Team-/Shirt-/Kontaktstatistiken nach Rennen, Rechte/Summen prüfen | admin/class-h3-mgmt-admin-statistics.php | R/T: zwölf Selektoren und Methodenaggregation; Admin-PHP8-Befunde |
| F-I18N-01 | Deutsche/englische Inhalte, Links, Mail; fehlendes Sprachplugin behandeln | includes/class-h3-mgmt-utilities.php:370 | R/W: en-Fallback und de-Mailvorlage; WPML/Theme in 10 |

## Übergabe an die Umsetzung

Renneinstellungen: status, startingpoint/start_route_id, num_teammember/min, dis_fee/dis_waiver/dis_shirt_size/dis_mobile_inf, donation/liveticker/liveticker_front, one_extra_point/amount_extra_point/extra_point_amount/vary_extra_point_field, question_1..5_invisible und Anbieter-/Übersichtslinks.

Phasen, zentrale Freigabeschalter und Einladungsgrenzen sind in business-rules.md tabellarisch charakterisiert; Phasensteuerung ist noch nicht automatisiert. Weitere Grenzfälle: fehlende Nutzer, doppelte Bestätigungen, Zusatzpunkte, Zeichensätze, Zeitformat/Zeitzonen und konkurrierende Anmeldungen. Inventar zeigt Eingabeflächen, keine vollständige Validierung.

Regeln und Referenzfälle sind mit passenden isolierten und WordPress-Integrationstests belegt; nicht automatisierte Vollabläufe sind den Folgeblöcken im Referenzkatalog zugeordnet. Sicherheitskorrekturen, Konkurrenz-, Browser-, Migrations- und Releaseabnahme bleiben eigenständige Liefergegenstände der Umsetzung.
