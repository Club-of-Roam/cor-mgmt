# Funktionsreferenz – Block 0

Stand 2026-11-01, aus Code abgeleitet. Keine vollständige Website-Abnahme. Details: [Shortcodes](shortcodes.md), [Request-Aktionen](actions.md).

## Akteure

Besucher: öffentliche Teams, Spenden, Ranglisten, Ticker/Karten und Spendenablauf. Teilnehmer: eigene Daten, Team und Börsennachricht. Routenbenutzer: Ergebnisabgabe für die zugeordnete Route. Administrator/Custom-Rolle: freigegebene Verwaltungsbereiche.

Capabilities aus H3_MGMT::extra_caps: h3_mgmt_send_emails, h3_mgmt_edit_autoresponses; h3_mgmt_view_races/edit_races/edit_own_races/delete_races/delete_own_races; h3_mgmt_view_teams/view_own_teams/edit_teams/edit_own_teams/delete_teams/delete_own_teams; h3_mgmt_view_sponsors/view_own_sponsors/edit_sponsors/edit_own_sponsors; h3-mgmt-view-statistics. Kurzschreibweisen nach Schrägstrich besitzen denselben h3_mgmt_-Präfix. Vorhandene Menürechte sind kein Nachweis geschützter Schreibaktionen. Members-Integration und Zuweisung beim init vorhanden.

## Referenzszenarien

R = recherchiert, noch nicht vollständig ausgeführt. A = isoliert automatisiert. Sicherheits-Sollfälle zusätzlich in Block 1 absichern.

| ID | Ablauf / erwartetes Ergebnis | Quelle | Status |
|---|---|---|---|
| F-RACE-01 | Rennen anlegen/ändern, aktiv wählen; Historie bleibt abrufbar | admin/class-h3-mgmt-admin-races.php | R |
| F-RACE-02 | Status 2 schließt neue Anmeldung, 3 behandelt gestartetes Rennen; Einladungssonderweg gesondert prüfen | includes/class-h3-mgmt-teams.php:1630 | R |
| F-ROUTE-01 | Kapazitäten, Zuständigkeit und Etappen verwalten; volle Route korrekt behandeln | includes/class-h3-mgmt-races.php:377 | R |
| F-TEAM-01 | Eigenes Team erstellen, Text/Bild/Spendenziel/Zusatzantworten ändern | includes/class-h3-mgmt-teams.php:2775 | R |
| F-TEAM-02 | Einladung versenden/annehmen; richtiges Team/Rennen, ungültiger Code ändert nichts | includes/class-h3-mgmt-teams.php:976 | R |
| F-TEAM-03 | Eigenes Team verlassen; im Bestand entfernt letztes Mitglied das Team, Spendenhistorie erhalten | includes/class-h3-mgmt-teams.php:1649 | R |
| F-TEAM-04 | Freigabe aus Mindestmitgliedern, Gebühr/Haftungsverzicht und aktivierten Pflichtangaben; Übergang einmal behandeln | includes/class-h3-mgmt-teams.php:420 | R |
| F-PROFILE-01 | Stadt, Telefon, Geburtstag, Shirt, Einwilligung und Avatar; keine unberechtigten Fremdänderungen | includes/class-h3-mgmt-profile.php | R |
| F-RANK-01 | Plätze 1..12 ergeben 20,17,14,12,10,8,6,5,4,3,2,1; 0/99 ergeben 0, 99 kann disq. anzeigen | includes/class-h3-mgmt-races.php:1390 | A |
| F-RANK-02 | Zuständiger Benutzer gibt Etappe und Zusatzpunkte ab; andere Route darf nicht verändert werden | includes/class-h3-mgmt-races.php:858 | R |
| F-RANK-03 | Tabellen beachten Rennen/Route/Top/Navigation/Etappen; historische Ausgaben vergleichen | includes/class-h3-mgmt-races.php:1174 | R |
| F-DON-01 | Betterplace: Team/Typ, Anbieter, Bestätigung, öffentliche Angaben; Abbruch ist nicht bezahlt | includes/class-h3-mgmt-sponsors.php:758 | R |
| F-DON-02 | Sponsor/Owner, Schwellwerte, Sichtbarkeit und Zähler; Centverlust als Altfehler behandeln | includes/class-h3-mgmt-sponsors.php:1026 | R |
| F-DON-03 | Historische Verfahren und Admin bezahlt/unbezahlt/zeigen/verbergen erhalten; künftige Wege separat entscheiden | admin/class-h3-mgmt-admin-sponsors.php | R |
| F-MAIL-01 | Selbsttest, Einzel-/Teilnehmergruppen; automatische Vorlage nach Aktion/Sprache | admin/class-h3-mgmt-admin-emails.php | R |
| F-XCH-01 | Eigene Nachricht erstellen/ersetzen, leer löscht; laufendes Jahr ohne Team im aktiven Rennen anzeigen | includes/class-h3-mgmt-xchange.php | R |
| F-TICK-01 | Text, Bild, Kommentar, Video, Position; nur berechtigte Zuordnung/Änderung | includes/class-h3-mgmt-ticker.php:182 | R |
| F-TICK-02 | Listen/Karte nach Rennen/Route/Team; Navigation und historische Medien | includes/class-h3-mgmt-ticker.php:659 | R |
| F-TICK-03 | Gateway ordnet synthetische Nummer zu; unbekannt/unvollständiges Team gesondert | includes/class-h3-mgmt-ticker.php:1433 | R |
| F-TICK-04 | Lokale deutsche, 0049-/+49-Nummer und Trennzeichen normalisieren identisch | includes/class-h3-mgmt-ticker.php:22 | A |
| F-ADMIN-01 | CRUD und Sortierung; Sichtrecht erlaubt nicht automatisch Änderung | admin/class-h3-mgmt-admin.php | R |
| F-STAT-01 | Teilnehmer-/Team-/Shirt-/Kontaktstatistiken nach Rennen, Rechte/Summen prüfen | admin/class-h3-mgmt-admin-statistics.php | R |
| F-I18N-01 | Deutsche/englische Inhalte, Links, Mail; fehlendes Sprachplugin behandeln | includes/class-h3-mgmt-utilities.php:370 | R |

## Nächste Vertiefung

Renneinstellungen: status, startingpoint/start_route_id, num_teammember/min, dis_fee/dis_waiver/dis_shirt_size/dis_mobile_inf, donation/liveticker/liveticker_front, one_extra_point/amount_extra_point/extra_point_amount/vary_extra_point_field, question_1..5_invisible und Anbieter-/Übersichtslinks.

Alle Status-/Schalterkombinationen noch tabellarisch charakterisieren. Grenzfälle: route_id=0, aktive Rennen, fehlende Nutzer, doppelte Bestätigungen, Zusatzpunkte, Zeichensätze, Zeitformat/Zeitzonen und konkurrierende Anmeldungen. Inventar zeigt Eingabeflächen, keine vollständige Validierung.

Block 0 erst abschließen, wenn Regeln und Referenzdaten durch passende Fach- und Integrationstests belegt sind.
