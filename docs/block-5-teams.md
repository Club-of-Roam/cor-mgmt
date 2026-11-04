# Block 5 – Teams und Profile

Stand 2026-11-04. Fachumbau im Kompatibilitätsbetrieb; kein Versionssprung und keine automatische Aktivierung einer Migrationsgeneration.

## Umfang und Einstiegspunkte

[TeamPolicy](../src/Domain/TeamPolicy.php) enthält die Phasen- und Freigaberegeln. [TeamManager](../src/Application/Teams/TeamManager.php) führt Anmeldung, Annahme, Profiländerung, Routenwahl, Austritt, Mitgliederverwaltung, Bestätigungen und Löschschutz aus. Uhrzeit, Zufall, Berechtigungen und [ParticipationStore](../src/Application/Teams/ParticipationStore.php) werden injiziert. Darstellung und Mailtransport gehören nicht zur Fachlogik.

[ParticipationRepository](../src/Infrastructure/WordPress/ParticipationRepository.php) behält bestehende Team-/Mitglieds-/Benutzer-IDs und Tabellen als maßgebliche Datenhaltung. [TeamBoundary](../src/WordPress/TeamBoundary.php), [TeamFrontend](../src/WordPress/TeamFrontend.php) und [TeamAdmin](../src/WordPress/TeamAdmin.php) verbinden die Anwendungsfälle mit WordPress. Bestehende Shortcodes und Menücallbacks delegieren weiter; kein zusätzliches Theme oder Formularplugin erforderlich.

## Umgesetzte Abläufe

| Bereich | Verhalten |
|---|---|
| Anmeldung | Nur Phase 1; eindeutige Mitgliedschaft pro Rennen, Vor-/Nachname erforderlich; Profileingaben, Teamname und Antworten geprüft |
| Einladungen | Versand/Annahme nur Phase 1, keine Reservierung, fachlich unbegrenzte Anzahl; höchstens 100 Adressen pro Request als technische Batchgrenze |
| Gültigkeit | Schließen der Anmeldung sperrt Annahme; explizites Wiederöffnen lässt unbenutzte, nicht widerrufene Codes wieder zu |
| Annahme | Prüft Rennen, Tokenzustand, bestehende Mitgliedschaft und freie Mitgliedsplätze gemeinsam; Einladungsannahme überschreibt kein Teamprofil |
| Teamname | Teilnehmer nur Phase 1; berechtigte Organisatoren bis Phase 3 |
| Inhalte | Beschreibung, Bild, Telefonnummer, Spendenziel und alle fünf Zusatzantworten in Phase 1..3; Frage 4 behält ihre vier historischen Auswahlwerte und fremde Bestandswerte |
| Routen | Erstwahl Phase 1/2; Wechsel für Teilnehmer nur Phase 1; vollständiges Team und freie Kapazität notwendig; fester Startpunkt wird beachtet |
| Kapazität | Unvollständige Teams zählen nicht; eine vorläufige Adminzuweisung reserviert ebenfalls nichts. Vor Freigabe wird die Belegung erneut geprüft |
| Freigabe | Explizite Schreibaktionen prüfen Mitgliederzahl, Gebühr, Haftungsverzicht, Shirtgröße und Partnerentscheidung entsprechend Eventeinstellungen; »nein« ist eine gültige Partnerentscheidung |
| Phasen/Rennvorgaben | Änderung relevanter Vorgaben und Rückwechsel werden mit den Teamstatus in derselben Transaktion abgeglichen; keine automatische Rückstufung ab Phase 3 |
| Bestätigungen | Zahlung/Haftungsverzicht nur Phase 0..2; einzeln und gesammelt, mit Objektberechtigung pro Mitglied |
| Austritt | Phase 1/2; entfernt nur die eigene Mitgliedschaft, journalisiert den Rohdatensatz und erhält das Team; bei leerem Team werden offene Codes widerrufen |
| Administration | Bestehende Bestätigungen/Mitglieds-IDs bleiben bei unveränderten Personen erhalten; neue Personen beginnen unbestätigt. Teilnehmerausnahmen überschreiben keine Event- oder Archivsperre |
| Unklare Bestände | Doppelte Mitgliedschaften und Wechsel in ein anderes Rennen verlangen kontrollierte Prüfung; keine geratene Zusammenführung |
| Löschen | Archiv, vorhandene Ergebnisse und Spenden schützen das Team; zulässige Löschungen erhalten Rohdatenjournal und Mitgliedschaftshistorie |
| Profil | Kontaktangaben, Geburtstag, Shirt und Partnerentscheidung über geprüfte Eingaben; berechtigte globale Profilpflege bleibt möglich. Bestehende rennbezogene Archivzeilen werden dabei nicht neu berechnet |
| Bilder | Geprüfte WordPress-Bildannahme; fehlgeschlagene Speicherung räumt ausschließlich die gerade hochgeladene Datei auf. Kein freier Bildpfad aus POST |

[ProfileAvatar](../src/WordPress/ProfileAvatar.php) bietet eigenen Bild-Upload und Entfernen über die WordPress-Avatar-Schnittstelle. Historische simple_local_avatar-Werte bleiben optional lesbar; kein Pflichtplugin und keine automatische Löschung vorhandener Bilder. Konto-Selbstlöschung ist bei vorhandenen Mitgliedschaften gesperrt. Vollständige eingefrorene Benutzer-/Medienidentitäten für das Archiv sind weiterhin eine getrennte Migrationsaufgabe.

## Einladungs- und Historieninstallation

[ParticipationSchema](../src/Infrastructure/WordPress/Schema/ParticipationSchema.php) führt eine unabhängige Modulversion 1.0.0. [SQL-Referenz](schema-participation-1.0.0.sql) wird aus denselben Definitionen erzeugt und durch composer check verglichen. Die eingefrorenen Installations-/Migrationspläne aus Block 3 bleiben unverändert.

- cor_mgmt_invitation_tokens: SHA-256-Hash des kryptographischen 256-Bit-Codes, Team, Adresse, Erzeugungszeit, Verbrauchszeit/-benutzer und Widerruf; eindeutiger Hashindex.
- cor_mgmt_membership_history: Rennen, Team, Zeit, Ereignis und HEX-Rohwerte entfernter Mitgliedschaften/Teams. Unbekannte Spalten und NULL bleiben erhalten.
- Neue Tabellen verwenden InnoDB/utf8mb4 mit Primär- und Beziehungsindizes. Installation erfolgt bei Aktivierung oder ausdrücklich über Werkzeuge → cor-mgmt system status → Set up team and invitation storage. POST/Nonce und Administratorrecht sind erforderlich.
- Eigenes Installationsmanifest: laufender Aufbau wiederaufnehmbar; ungehörige Namenskollisionen, veränderte Pläne, Schemaabweichungen und fehlende Tabellen eines bereits abgeschlossenen Aufbaus werden abgewiesen. Daten werden nicht durch »Reparatur« neu angelegt.
- Bestehende numerische Codes bleiben nutzbar. Verbrauch/Widerruf wird im neuen Journal unter kanonischem Legacy-Hash gespeichert; führende Nullen ermöglichen keine Wiederverwendung. Die alte Einladungstabelle bleibt unverändert und darf für diesen Übergang MyISAM verwenden.
- Fehlende Moduleinrichtung führt zu einem Hinweis, nicht zu DDL während einer gewöhnlichen Seitenansicht. Das ist eine additive Einrichtung, keine vollständige Datenmigration.

Gültigkeit wird am aktuellen Rennstatus geprüft, nicht an einem geratenen Enddatum. Ende-Datum und Phase sind bisher unabhängig. Ein neues Kalenderablaufdatum wird daher nicht erfunden.

## Atomarität, Rechte und Nebenwirkungen

Teilnehmerwriter, Profilpflege, Renn-/Routenwriter und Migrationswerkzeuge verwenden dieselbe Datenbank-/Präfixsperre. Konkurrierende Zugriffe scheitern kontrolliert an belegter Sperre oder anschließend an ausgeschöpfter Kapazität. Mitgliedschaft, Status, Routenbelegung, Codeverbrauch und Profilfelder werden gemeinsam zurückgerollt. WordPress-Metacaches werden nach Erfolg und Rollback invalidiert. Vergleichende Updates und Formularrevisionen verhindern veraltetes überschreiben.

Rennen, Routen, Etappen, Teams, Mitgliedschaften sowie WordPress-Benutzer und Benutzermetadaten müssen für diese Mehrfachaktionen InnoDB verwenden. Keine stillen Engine-Änderungen; unbekannte oder unpassende Strukturen sperren den Schreibweg. Direkte SQL-Fremdwriter und parallel laufende alte Pluginversionen sind nicht von der kooperativen Sperre erfasst.

[is_complete](../includes/class-h3-mgmt-teams.php) ist nun eine reine Abfrage derselben Freigaberegeln und schreibt oder sendet auch mit gültigem POST-Kontext nichts. Ab Rennstart liefert sie den gespeicherten Status. Listen, Dashboard und Zufallsteam-Auswahl lösen keine Statusänderungen aus; eine leere Zufallsauswahl terminiert.

POST/Nonces bleiben an allen Formulargrenzen erforderlich. Fachservice prüft zusätzlich Akteur und Objekt. Berechtigte Routenverantwortliche sehen nur ihre Teilnehmerkontakte. Neue Tabellenansichten sind paginiert, nach Event/Route/Teamstatus filterbar und nach erlaubten Spalten sortierbar. Sammelauswahl enthält nur bearbeitbare Teilnehmer. Native Labels, Datumseingaben und gebündelte Verwaltungsformulare ersetzen die alten Formulare schrittweise.

Benachrichtigungen entstehen als Rückgabewerte der erfolgreich abgeschlossenen Transaktion. Erst danach ruft die WordPress-Grenze die bestehenden Sprach-/Vorlagenadapter auf. Erneutes Speichern eines unveränderten Zustands erzeugt keine weitere Freigabebenachrichtigung. Dauerhafte Versandqueue, Zustellwiederholung und umfassende Vorlagenmodernisierung gehören weiterhin zu Block 8; ein fehlgeschlagener Transport rollt keine bereits abgeschlossene Anmeldung zurück.

## Prüfung und Grenzen

[TeamPolicyTest](../tests/Foundation/TeamPolicyTest.php) prüft die fünf Phasen und Freigabebedingungen. [TeamManagementTest](../tests/Integration/TeamManagementTest.php) prüft vollständige Anwendungsfälle, echte WordPress-Rechte/Nonces, Anzeige, Revisionen, Privatsphäre, historische Werte, Fehler-Rollback und schreibfreie Abfragen. [ParticipationInstallationTest](../tests/Integration/ParticipationInstallationTest.php) prüft echte DDL, Wiederholung, Abbruch/Wiederaufnahme, Kollisionen und Schemaabweichungen. [TeamConcurrencyTest](../tests/Integration/TeamConcurrencyTest.php) startet echte parallele PHP-Prozesse für den letzten Mitglieds- und Routenplatz, einschließlich unveränderter MyISAM-Legacyeinladungen.

Ausgeführte Versionen und Ergebnisse: [verification.md](verification.md). Vollständige Browser-/Tastatur-/Accessibility-Abnahme und positiver multipart-Bildtransport bleiben noch auszuführen; serverseitige Bild- und Ablehnungstests ersetzen dies nicht. Folgende fachlichen Arbeiten bleiben ihren Blöcken zugeordnet: Ergebnisnormalisierung und Historienabgleich (6), Zahlungseinheiten/-verfahren (7), Versandverarbeitung (8), Ticker-/Medienarchive (9), vollständiger Archivsnapshot und aktive Fachmigration (3/6/7/9), releaseweite Eigenständigkeitsabnahme (10).

Kein Bestand automatisch migriert, keine aktive Generation ausgewählt, keine historische Punkteberechnung und keine Änderung an Theme, cor-ticker oder ausgelagerten Endpunkten. Spendenziel bleibt in der bisherigen Einheit; keine unbelegte Centumrechnung. Normales Abschließen eines Rennens bleibt bis zum vollständigen Archivsnapshot gesperrt.
