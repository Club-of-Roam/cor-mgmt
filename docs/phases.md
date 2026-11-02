# Verbindliche Phasenmatrix für Version 2

Fachliche Zielvorgabe des Benutzers vom 2026-11-02. Diese Matrix mit fünf Phasen ersetzt die früheren Vorschläge; die Fachentscheidungen zu Rückwechseln, Verwaltung und Archivierung sind ergänzt. Noch keine Implementierung oder Abnahme des Anwendungscodes. Ist-Befunde stehen getrennt in [business-rules.md](business-rules.md).

Ja erlaubt die Aktion nur bei vorhandener Berechtigung und erfüllten Objekt-, Daten- und Kapazitätsprüfungen. Verwaltungseinstellungen betreffen berechtigte Organisatoren, Teilnehmeraktionen das eigene Team/Profil. Kapazitätskonflikte müssen vor dem Speichern sichtbar gewarnt werden; erlaubte Änderungen bleiben möglich und dürfen keine Teams still löschen oder umverteilen. »Keine Teilnahme« betrifft das jeweilige Rennen, nicht historische Mitgliedschaften anderer Rennen.

| Bereich / Aktion | 0 · Vor Anmeldung | 1 · Anmeldung offen | 2 · Anmeldung geschlossen | 3 · Rennen gestartet | 4 · Rennen beendet / Archiv |
| --- | --- | --- | --- | --- | --- |
| **Teilnahme und Teams** |  |  |  |  |  |
| Neues Team erstellen | Nein | Ja | Nein | Nein | Nein |
| Einladung versenden | Nein | Ja, unabhängig von freien Mitgliedsplätzen | Nein | Nein | Nein |
| Einladung annehmen | Nein | Ja, wenn Mitgliedsplatz frei | Nein | Nein | Nein |
| Team verlassen | Nein | Ja | Ja | Nein | Nein |
| Teamname ändern | Nein | Ja | Nein | Nein | Nein |
| Beschreibung, Bild, Spendenziel ändern | Nein | Ja | Ja | Ja | Nein |
| Zusatzantworten ändern | Nein | Ja | Ja, vorläufig alle | Ja, vorläufig alle | Nein |
| Persönliche Kontaktangaben ändern | Ja | Ja | Ja | Ja | Nein |
| **Routenwahl durch Teilnehmer** |  |  |  |  |  |
| Route erstmals wählen | Nein | Team vollständig und Platz frei | Team vollständig und Platz frei | Nein | Nein |
| Route wechseln | Nein | Team vollständig und Zielroute frei | Nein | Nein | Nein |
| Unvollständiges Team belegt Routenplatz | Nein | Nein | Nein | Nein | Nein |
| **Grundstruktur und Teilnahmevoraussetzungen** |  |  |  |  |  |
| Minimale/maximale Teamgröße ändern | Ja | Nein | Nein | Nein | Nein |
| Maximale Teams je Route ändern | Ja | Ja | Nein | Nein | Nein |
| Ein oder mehrere Startpunkte festlegen | Ja | Ja | Nein | Nein | Nein |
| Pflicht zu Gebühr, Haftungsverzicht, Shirtgröße und weiteren Angaben ändern | Ja | Nein | Nein | Nein | Nein |
| **Darstellung und Inhalte durch Organisatoren** |  |  |  |  |  |
| Logo/Icon und Routenfarbe ändern | Ja | Ja | Ja | Nein | Nein |
| Beschreibungen und Hinweistexte ändern | Ja | Ja | Ja | Nein | Nein |
| **Organisation und Ablauf** |  |  |  |  |  |
| Rennphase setzen | Ja | Ja | Ja | Ja, Zielphase gilt wieder vollständig | Nein |
| Aktives Rennen auswählen | Ja | Ja | Ja | Ja | Nein |
| Beginn/Ende ändern | Ja | Ja | Ja | Ja, im Adminbereich | Nein |
| Routen und Etappen strukturell ändern | Ja | Nein | Nein | Nein | Nein |
| Treffpunkte und organisatorische Hinweise aktualisieren | Ja | Ja | Ja | Ja | Nein |
| **Funktionsschalter** |  |  |  |  |  |
| Ticker-Eingang aktivieren/deaktivieren | Ja | Ja | Ja | Ja | Nein |
| Ticker-Anzeige aktivieren/deaktivieren | Ja | Ja | Ja | Ja | Nein |
| Spendenfunktion aktivieren/deaktivieren | Ja | Ja | Ja | Ja | Nein |
| **Wertung** |  |  |  |  |  |
| Zusatzpunkt-Regeln und feste Punkthöhe ändern | Ja | Ja | Ja | Nein | Nein |
| Variable Zusatzpunkte aktivieren/deaktivieren | Ja | Ja | Ja | Nein | Nein |
| Ergebnisse durch berechtigte Routenverantwortliche eingeben | Nein | Nein | Nein | Ja | Nein |
| Rangliste anzeigen | Nein | Nein | Nein | Ja | Ja, eingefrorener Stand |
| **Teilnehmerbestätigungen und Teamstatus** |  |  |  |  |  |
| Zahlung/Haftungsverzicht bestätigen oder zurücknehmen | Ja, sofern Teilnehmer vorhanden | Ja | Ja | Nein | Nein |
| Team automatisch zurückstufen | Keine Teilnahme | Bei fehlenden Voraussetzungen | Bei fehlenden Voraussetzungen | Nein | Nein |
| Route bei automatischer Rückstufung entfernen | Keine Teilnahme | Ja | Ja | Nein | Nein |

## Beschlossene Präzisierungen

- Teamgröße und Teilnahmevoraussetzungen sind während der gesamten Phase 0 änderbar, nicht nur bei Erstellung. Routenkapazität und Startpunktmodus sind zusätzlich in Phase 1 änderbar.
- **Phasenrückwechsel zwischen 0 bis 3:** Nach einem Rückwechsel gelten wieder alle Erlaubnisse der Zielphase. Keine dauerhafte Startsperre entgegen der Zielphase einführen. Bei Wiederöffnung ist Einladungsannahme wieder gemäß Phase 1 möglich, sofern der Code weiterhin existiert, nicht verbraucht/aufgehoben ist und ein Mitgliedsplatz frei ist. Verbrauchte Einladungen werden nicht wiederhergestellt.
- **Admin-Ausnahmen für Teilnehmer:** Berechtigte Organisatoren sollen Teilnehmerangelegenheiten einschließlich Teamzusammensetzung und Routenzuweisungen auch außerhalb der normalen Teilnehmerabläufe bearbeiten können. Das ist keine pauschale Freigabe gesperrter Event-/Renneinstellungen und kein Recht auf Archivänderungen. Welche Rollen welche Einzelaktionen ausführen dürfen, bei der Rechteumsetzung konkret zuordnen.
- **Kapazitäts- und Startpunktkonflikte:** Warnung im Adminbereich anzeigen, beispielsweise beim Senken unter die vorhandene Belegung oder beim Wechsel des Startpunktmodus trotz bestehender Zuordnungen. Änderung bleibt möglich. Bestehende Teams und Zuordnungen nicht automatisch löschen/umverteilen. Normale Teilnehmerzugriffe dürfen eine Überbelegung nicht weiter erhöhen. Diese Konfliktbehandlung hebt die Phasensperren für Eventeinstellungen nicht pauschal auf; Archive bleiben gesperrt.
- **Zusatzantworten:** Vorläufig alle Antworten auch nach Anmeldeschluss und nach Start bearbeitbar. Die genauere Unterscheidung ausdrücklich für spätere fachliche Prüfung vormerken; bis dahin keine erfundene Kategorie »unkritisch« erzwingen.
- **Beginn/Ende:** Im Adminbereich auch nach Start änderbar, bis zur Archivierung. Folgewirkungen später fachlich prüfen; die Vermutung »nur Anzeige« ist kein geprüfter Befund. Bis zur Klärung keine neuen automatischen Phasenwechsel oder Neuberechnungen allein aus der Datumskorrektur ableiten.
- Darstellung/redaktionelle Texte sind ab Start gesperrt, organisatorische Hinweise bleiben aktualisierbar. Dies sind andere Felder als die weiter erlaubte Teilnehmerbeschreibung, Bilder und Spendenziele.
- Einladungen reservieren keine Plätze. Versand/Annahme nur in Phase 1, freie Mitgliedskapazität bei Annahme atomar prüfen. Unvollständige Teams belegen keinen Routenplatz.
- Ab Start keine automatische Teamrückstufung oder dadurch ausgelöste Routenentfernung; Teilnehmerbestätigungen in Phase 3 gesperrt. Ein expliziter Rückwechsel richtet sich wieder nach der Zielphase.
- Spendenfunktion freischalten entscheidet nicht über künftige Zahlungswege; deren Fachprüfung bleibt separat.

## Phase 4: Rennen beendet / eingefrorenes Archiv

Neue Phase mit vorgeschlagenem technischem Statuswert 4; endgültige Schema-/Enum-Abbildung in Block 3 festlegen. Beim Abschluss werden Rennstand, Einstellungen, Teams/Mitgliedschaften, Zuordnungen, Ergebnisse/Summen/Rangfolge und zugehörige historische Inhalte erhalten. Kein fachlicher Schreibzugriff, keine Neuberechnung, keine automatische Rückstufung, kein Versand oder neue Ticker-/Spenden-Schreibverarbeitung für dieses Rennen. Auch Admin-Ausnahmen enden an dieser Archivgrenze. Öffentliche Historie und Rangliste bleiben lesbar, anhand des eingefrorenen Standes.

Gemeinsam verwendete Benutzerkonten bleiben nutzbar. Änderungen am allgemeinen Benutzerprofil dürfen den historischen Rennstand nicht nachträglich verändern; benötigte historische Werte sind beim Abschluss zu sichern. Den genauen Snapshotumfang einschließlich Medien, Ticker und Spenden-/Providerverknüpfungen in Block 3/6/7/9 ausarbeiten, keine historischen Datensätze verwerfen.

Der Benutzer fordert ein unveränderliches Archiv. Deshalb ist Phase 4 kein gewöhnlicher Rückwechselpfad in Phase 0 bis 3. Falls später eine Wiederöffnung des Archivs benötigt wird, erfordert sie eine eigene fachliche Entscheidung; derzeit kein solches Werkzeug vorsehen.

## Migration bestehender Rennen

Alle Bestandsrennen, die bei der verbindlichen Migrations-Vorprüfung den Status »Rennen gestartet« (3) haben, gehören zur historischen Menge und werden beim Umstieg direkt in Phase »Rennen beendet« überführt. Zielmenge mit IDs und Ausgangsstatus im Migrationsjournal festhalten; bei Wiederaufnahme nicht erneut aus dann möglicherweise veränderten Statuswerten bestimmen.

Keine Berechnung vor dem Einfrieren auslösen: bestehende Ergebnisse/Summen/Rangfolge und Inhalte unverändert übertragen. Andere Ausgangsphasen bleiben erhalten. Die Zuordnung ist Teil des kontrollierten, wiederaufnehmbaren Migrationsverfahrens, nicht gewöhnlicher Seitenaufrufe. Noch keine Migration implementiert oder ausgeführt.

## Noch spätere Detailarbeit

- Zusatzantworten später genauer klassifizieren; bis dahin sind alle gemäß Matrix bearbeitbar.
- Wirkungen von Beginn/Ende und genaue Feldzuordnung redaktioneller versus organisatorischer Texte prüfen.
- Rollen und Einzelrechte für Teilnehmer-Adminaktionen konkretisieren; keine pauschalen Event-Overrides.
- Archivsnapshot technisch spezifizieren, einschließlich unabhängiger historischer Darstellung bei späteren Profiländerungen.
- Sonderfall Wechsel eines Teams in ein anderes Rennen noch bei der Fachmodulumsetzung behandeln; Phasenrückwechsel innerhalb desselben Rennens ist entschieden.

## Geplante Abnahme

Positive/negative Tests je Matrixzeile und für manipulierte Requests sowie Phasenwechsel zwischen Anzeige und Speichern. Rückwechsel 3→1 muss erlaubte Aktionen wieder freigeben; neue und noch offene, unbenutzte Einladungen unter Mitgliedskapazitätsprüfung behandeln, verbrauchte Tokens nicht reaktivieren. Admin-Ausnahmen für Teilnehmer von gesperrten Eventeinstellungen trennen. Kapazitätswarnung samt erlaubter Reduzierung unter Bestand ohne automatische Löschung testen. Alle Zusatzantworten in Phase 2/3 und Admin-Datumskorrekturen prüfen.

Archivtests weisen sämtliche Schreib-/Neuberechnungswege auch für Administratoren ab. Änderungen gemeinsamer Nutzerprofile dürfen historische Ausgaben nicht verändern. Migration 3→Archiv prüft IDs, Statusmapping, Ergebniswerte, Rangfolge und wiederholte/unterbrochene Ausführung ohne Datenänderung. Archivierung nach Abschluss neuer Rennen ebenso testen. Noch keine Implementierung oder automatisierte Abnahme dieser Zielregeln; vorhandene Charakterisierungstests beschreiben den Altbestand.
