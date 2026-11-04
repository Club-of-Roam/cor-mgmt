# Fachregeln und Zustandswechsel – Block 0

Quellprüfung: 2026-11-01; Fachentscheidungen ergänzt 2026-11-02. Grundlage der Ist-Befunde ist der unveränderte Quellcode, nicht eine vollständig ausgeführte Website-Abnahme. **Ist** beschreibt beobachteten Code; **Ziel** bezeichnet Anforderungen für die Modernisierung. Ein Charakterisierungstest macht einen Altfehler nicht zur gewünschten Funktion.

## Anmeldung und Rennphasen

Phasen aus [Verwaltung](../admin/class-h3-mgmt-admin-races.php), Anzeigeverhalten aus [Dashboard](../includes/class-h3-mgmt-teams.php#L1630) und [Formularaufbau](../includes/class-h3-mgmt-teams.php#L1718).

| Phase | Neues Team ohne Einladung: Ist | Bestehendes Team: Ist | Routenauswahl bei mehreren Startpunkten: Ist |
|---|---|---|---|
| 0 vor Anmeldung | Kombinierter Dashboard-/Hausaufgaben-Shortcode blendet aus; direkter Dashboardpfad sperrt nicht ausdrücklich | Bearbeitung im direkten Dashboardpfad möglich | Für vollständiges Team nur bei route_id=0 angeboten |
| 1 offen | Formular angeboten | Bearbeitung möglich | Vollständiges Team darf wählen/wechseln |
| 2 geschlossen, vor Start | Formular verweigert | Bearbeitung mit Hinweis; Name im HTML schreibgeschützt | Nur erste Wahl bei route_id=0 und vollständigem Team |
| 3 gestartet | Formular verweigert | Bearbeitung mit Hinweis; Name im HTML schreibgeschützt | Keine Auswahl |

Eine gültige Einladung öffnet einen gesonderten Formularpfad auch bei geschlossener Anmeldung. Der Speicherpfad prüft die Phase nicht erneut. Schreibgeschützte HTML-Felder sind keine Berechtigungsprüfung. Quellen: [kombinierter Shortcode](../includes/class-h3-mgmt-teams.php#L1586), [Speichern](../includes/class-h3-mgmt-teams.php#L2775).

### FACH-01: Beschlossene Zielmatrix

Die vom Benutzer korrigierte [Phasenmatrix](phases.md) vom 2026-11-02 mit fünf Zielphasen einschließlich Archiv ist die verbindliche Zielvorgabe und ersetzt die früheren Vorschläge. Die vier Ist-Phasen oben bleiben davon getrennt. Die Tabelle oben beschreibt weiterhin ausschließlich den Ist-Zustand. Rechte- und Kapazitätsprüfungen sind auch bei erlaubten Aktionen erforderlich.

Die Zielmatrix trennt Grundstruktur/Kapazitäten, Teilnahmevoraussetzungen, Darstellung, Organisation/Ablauf, Funktionsschalter, Wertung, Teilnehmerbestätigungen und berechneten Teamstatus. Teamgröße und Teilnahmevoraussetzungen sind während der gesamten Phase 0 änderbar, nicht nur bei Erstellung. Routenkapazität und Anzahl der Startpunkte dürfen auch in Phase 1 geändert werden. Darstellung und redaktionelle Texte sind ab Start gesperrt; Treffpunkte und organisatorische Hinweise bleiben aktualisierbar.

Nachträge beschlossen: Rückwechsel zwischen Phase 0 bis 3 stellen alle Rechte der Zielphase wieder her; Teilnehmerangelegenheiten im Adminbereich mit Ausnahmen bearbeiten, keine pauschalen Event-Overrides; Kapazitäts-/Startpunktkonflikte warnen, Änderung ermöglichen; Zusatzantworten vorläufig alle bearbeitbar; Beginn/Ende im Adminbereich auch nach Start ändern, Wirkungen später prüfen. Neue Phase Rennen beendet friert alles rennbezogen ein. Noch keine automatisierte Abnahme der Zielmatrix.

**Ist-Abgrenzung:** Im [Rennformular](../admin/class-h3-mgmt-admin-races.php) ist disabled an eine vorhandene ID gebunden; [Teamgröße und Teilnahmevoraussetzungen](../admin/class-h3-mgmt-admin-races.php) sind dort nach Erstellung deaktiviert. Das belegt zunächst die Formularbehandlung, keine vollständige serverseitige Unveränderlichkeit. [Routenkapazität](../admin/class-h3-mgmt-admin-races.php) besitzt diese Deaktivierung im Formular nicht. [Routenfarbe und Logo](../admin/class-h3-mgmt-admin-races.php) gehören ausdrücklich zur Darstellung. Mit Icon ist hier die fachliche Bezeichnung gemeint; das vorhandene Formular führt Logo-URLs.

## Teamfreigabe

Quelle: [is_complete](../includes/class-h3-mgmt-teams.php#L420). Die Methode ist im Bestand keine reine Abfrage.

### Getrennte Begriffe (bestätigt 2026-11-02)

| Begriff für Tabellen und Bedienung | Bedeutung | Beschlossener Zustand ab Rennstart |
|---|---|---|
| Grundstruktur / Teilnahmevoraussetzungen ändern | Freigaberelevante Vorgaben wie Mindestmitgliederzahl und Pflicht zu Gebühr/Haftungsverzicht; nicht Darstellung oder sämtliche Renneinstellungen | Gesperrt; genaue Änderungsphasen je Feld in phases.md |
| Zahlung/Haftungsverzicht bestätigen oder zurücknehmen | Erfüllung pro Teilnehmer im Backend markieren bzw. eine Bestätigung zurücknehmen | Gesperrt |
| Team automatisch zurückstufen | Aus fehlenden Voraussetzungen den Teamstatus von vollständig/freigegeben auf unvollständig ändern | Ausgeschlossen; keine dadurch verursachte Routenentfernung |

Diese Trennung präzisiert die bereits beabsichtigte Fachlogik und ersetzt die mehrdeutige Sammelbezeichnung »Freigabevoraussetzungen im Backend ändern«. Sie ist keine neue Funktion. Die berechnete Teamfreigabe bleibt von Rennregeln und Teilnehmerbestätigungen getrennt. Allgemeine Profilangaben sind keine pauschal gesperrten Bestätigungen.

| Voraussetzung | Ist-Regel | Testbeleg |
|---|---|---|
| Mitgliederzahl | Mindestens num_teammember_min, bei fehlender Einstellung 2 | TeamRulesTest: Minimum |
| Gebühr und Haftungsverzicht | Jedes Mitglied paid=1 und waiver=1, soweit jeweiliger dis_*-Schalter nicht deaktiviert | TeamRulesTest: vier Pflichtanforderungen jeweils aktiv/deaktiviert |
| Shirt und Mobilfunkangabe | Je Mitglied nicht leer, soweit Pflicht aktiviert | Dieselben Tests |
| Mobilfunkangabe »no« | Gilt als ausgefüllt; Zustimmung »yes« ist keine Freigabevoraussetzung | Erfolgreicher Freigabetest mit yes/no |
| Weitere Daten | Maximalgröße, Phase, Route, Telefon, Geburtstag und Zusatzantworten werden hier nicht geprüft | Statischer Befund, keine Vollständigkeitsgarantie |

| Alter Status | Berechneter Status | Ist-Nebenwirkungen |
|---|---|---|
| 0 | 0 | Keine Änderung, keine Freigabemail |
| 0 | 1 | complete=1; bei einem Startpunkt start_route_id setzen; publishable an Teammitglieder in Nutzersprache |
| 1 | 1 | Keine erneute Änderung oder Freigabemail |
| 1 | 0 | complete=0, Route am Ende 0, keine Freigabemail; bei einem Startpunkt vorher redundante Startzuweisung |

Diese Übergänge sind isoliert automatisiert. »Nur einmal« gilt für wiederholte Aufrufe nach gespeichertem Status, nicht für parallele Aufrufe oder einen erneuten Übergang 0→1. Konkurrenzverhalten ist ungeprüft.

**Ziel:** Freigabe als expliziten Zustandswechsel behandeln; Rendern und Abfragen dürfen weder speichern noch senden. **FACH-02, ab Rennstart beschlossen (2026-11-02):** Freigaberelevante Vorgaben ändern und Zahlung/Haftungsverzicht bestätigen oder zurücknehmen sind getrennte Backend-Aktionen; beide sind ab Rennstart gesperrt. Dies ist keine Sperre sämtlicher Renneinstellungen; die Änderungsphasen der einzelnen Gruppen stehen in phases.md. Ein freigegebenes Team darf danach nicht mehr automatisch zurückgestuft werden; keine dadurch ausgelöste automatische Routenentfernung. Schreibsperren serverseitig durchsetzen, nicht nur Bedienelemente ausblenden. Änderungen an allgemeinen Profilangaben sind damit nicht pauschal verboten. Mitgliedschaftsänderungen und weitere Einflusswege auf die Freigabe müssen mit FACH-01 abgestimmt werden. **Vor Rennstart beschlossen:** In Phase 1/2 bei fehlenden Voraussetzungen zurückstufen und Route entfernen. Ein expliziter Rückwechsel aus Phase 3 richtet sich wieder vollständig nach der Zielphase; Phase 4 bleibt eingefroren. **Noch offen:** Schalter außerhalb 0/1 und widersprüchliche Min-/Maxwerte. Keine historische Route automatisch bereinigen.

## Einladungen und Formularvalidierung

Quellen: [Anzahl](../includes/class-h3-mgmt-teams.php#L941), [Codeannahme](../includes/class-h3-mgmt-teams.php#L976), [Validierung](../includes/class-h3-mgmt-teams.php#L2744), [Speichern](../includes/class-h3-mgmt-teams.php#L2775).

Bei Maximum 3 liefert allow_invitations für 0/1/2 Mitglieder 2/2/1 Einladungsplätze, ab 3 false. Beim noch leeren Team wird ein Platz für den Ersteller reserviert. Für Maximum 1 und leeres Team kommt 0 zurück. Diese Grenzen sind getestet. Ausstehende Einladungen werden in dieser Berechnung nicht gezählt; die Berechnung garantiert keine sichere Obergrenze beim Beitritt. Fehlende num_teammember-Einstellung wird vor dem vorgesehenen Standardwert bereits gelesen und kann warnen.

Die Codeauflösung prüft weder Ablauf noch Empfängeradresse oder Rennzugehörigkeit. GET hat beim Auflösen Vorrang, POST beim späteren Löschen des Codes. Einladungscodes werden mit mt_rand erzeugt. Das sind Sicherheitsbefunde für Block 1/3/5, keine erhaltenswerten Verträge.

validate_submit prüft nur das Honeypotfeld address und nichtleere team_name/first_name/last_name. Automatisiert: gültiger Antrag, jedes fehlende Pflichtfeld, Honeypot, beide Fehler gleichzeitig. Leerzeichen werden akzeptiert, Zeichenfolge »0« als leer abgewiesen; auch diese Altbesonderheiten sind erfasst, keine Zielvorgabe. Objektberechtigung, Nonce, Phase, Kapazität und Datentypen sind damit nicht abgesichert.

**Ziel:** Sichere, zeitlich begrenzte, einmalig verbrauchbare Tokens und erneute Berechtigungs-/Kapazitätsprüfung beim Schreiben; bestehende offene Einladungen erhalten einen Übergangspfad. **FACH-03, teilweise beschlossen (2026-11-02):** Einladungen reservieren keinen Platz. Die Anzahl der Einladungen ist fachlich nicht durch die freien Plätze begrenzt; es dürfen beliebig viele Einladungen versendet werden. Erst bei erfolgreicher Annahme wird ein Platz belegt. Sind alle Plätze belegt, weitere Annahmen ohne Mitgliedschaftsänderung zurückweisen. Gleichzeitige Annahmen um den letzten Platz müssen atomar behandelt werden, sodass genau eine erfolgreich ist. Technische Missbrauchsbegrenzung beim Versand bleibt davon unberührt; noch keine konkrete Versandgrenze festgelegt.

**Bestätigt 2026-11-02:** Mit »Platz« ist ein Mitgliedsplatz innerhalb eines Teams gemeint, begrenzt durch dessen maximale Mitgliederzahl. Die Team-/Routenkapazitäten sind verschiedene Grenzen. Einladungen sind bis zum Ende der offenen Anmeldung gültig; Annahmen ab Anmeldeschluss oder nach Rennstart serverseitig ablehnen, auch über alte Links oder bereits geöffnete Formulare. Das Ende der Gültigkeit ist an die Rennanmeldung gebunden, keine Frist von beispielsweise 14 Tagen ab Versand. **Wiederöffnung beschlossen:** Zurück auf Phase 1 gelten wieder deren Rechte; vorhandene unbenutzte Einladungen können unter Kapazitätsprüfung angenommen werden, verbrauchte Tokens nicht reaktivieren. Vor Anmeldestart sind Versand/Annahme gesperrt. Wechsel in ein anderes Rennen bleibt ein gesonderter Fall. Annahme/Verbrauch, Ablaufgrenze und parallele Beitritte benötigen noch Integrationstests. Die vorhandenen Anzahltests charakterisieren nur den Altbestand und sind kein v2-Solltest.

## Routen und Kapazitäten

[Freie Plätze](../includes/class-h3-mgmt-races.php#L377) = max_teams minus Anzahl zugewiesener Teams. Der SQL-Pfad filtert nicht nach complete; negative Werte werden nicht auf 0 begrenzt. Das ist statisch geprüft, nicht gegen eine echte Datenbank ausgeführt.

[Routenfeld](../includes/class-h3-mgmt-teams.php#L1507) zeigt nur Routen mit freien Plätzen; die aktuelle Route bleibt auch bei 0 oder negativem Rest auswählbar. Platzhalter ist route_id=0. Automatisiert sind offene, volle, überbuchte und unbekannte Auswahlwerte, einschließlich numerischer Zeichenfolge.

Der Speicherpfad übernimmt eine übermittelte Route ohne entsprechende Kapazitäts- und Rennzugehörigkeitsprüfung. **Ziel:** Auswahl und tatsächliche Zuweisung getrennt prüfen; konkurrierende Anmeldungen dürfen kein unbemerktes Überbuchen verursachen. **FACH-04 beschlossen (2026-11-02):** Unvollständige Teams belegen keinen Routenplatz. Bei Freigabe und tatsächlicher Routenzuweisung die Kapazität serverseitig prüfen; gleichzeitige Freigaben/Zuweisungen dürfen die Route nicht überbuchen. Einladungen reservieren ebenfalls keinen Routenplatz. Bestehender SQL-Zählpfad weicht von dieser Zielregel ab und muss später angepasst werden. Keine automatische rückwirkende Änderung historischer Zuordnungen.

## Ergebnisse und Punkte

[Platzpunkte](../includes/class-h3-mgmt-races.php#L1390): 1..12 → 20,17,14,12,10,8,6,5,4,3,2,1; 0 und 99 → 0. 99 kann »disq.« anzeigen. Tests bestehen für Ganzzahlen und numerische Zeichenfolgen. Ungültige Platzwerte sind noch nicht abgesichert.

[Ergebnisabgabe](../includes/class-h3-mgmt-races.php#L858) prüft zunächst Routenbenutzer des aktiven Rennens. Die anschließende Routensuche ist nicht rennbezogen; Speichern vertraut übermittelter Route/Etappe. Diese Grenzen benötigen negative Berechtigungstests und Korrekturen in Block 1/6.

[save_ranking](../includes/class-h3-mgmt-races.php#L1059) summiert sechs feste Etappen: Platzpunkte plus Ein-Punkt-Flag und konfigurierten Betrag bei Betrags-Flag; variable Zusatzpunkte werden für die aktuelle Eingabe ab Etappe 6 übernommen. Beim Summieren anderer Etappen wird ein variabler Wert nur bei ==1 berücksichtigt und dabei ein Feldnamensstring addiert. Das kann unter PHP 8 einen TypeError verursachen; andere variable Werte werden ignoriert. **Am 2026-11-02 isoliert reproduziert:** Wert 1 erzeugt TypeError, Wert 5 wird ausgelassen. Frühere Teams können bereits update-Aufrufe ausgelöst haben. Details und weitere Ranglistenbefunde in [ranking-reference.md](ranking-reference.md). Schalter im Eingabeformular stellen keine serverseitige Begrenzung dar.

[Rangliste](../includes/class-h3-mgmt-races.php#L1174) ist vor Phase 3 ausgeblendet. Ranggleichheit und fehlerhafte Top-/999-Punkte-Grenzen sind nun isoliert charakterisiert; Routenfilter, Navigation und historische Datensätze bleiben zu prüfen. **FACH-05, Historie beschlossen (2026-11-02):** Erkannte Berechnungsfehler dürfen historische Ergebnisse nicht nachträglich verändern. Migration und neue Anzeige dürfen weder gespeicherte historische Summen überschreiben noch unbemerkt eine neu berechnete Rangfolge zeigen. Auffälligkeiten dürfen getrennt berichtet werden; Erhalt der historischen Ausgabe benötigt Referenztests. Korrekturen für neue Berechnungen isoliert testen. **Historiengrenze beschlossen:** Alle Bestandsrennen mit Ausgangsstatus 3 bei der Migrations-Vorprüfung werden ohne Neuberechnung direkt in die neue Archivphase überführt; diese Zielmenge für Wiederaufnahme protokollieren. Neue Rennen werden beim Abschluss ebenfalls eingefroren. Zulässige variable Punkte noch fachlich konkretisieren. Ob tatsächlich gespeicherte Ergebnisse betroffen sind, ist bisher nicht nachgewiesen; der Codebefund allein beweist keinen historischen Datenfehler.

## Noch offene Abnahme

Die isolierten Tests, unter anderem [TeamRulesTest](../tests/Characterization/TeamRulesTest.php), ersetzen äußere Abhängigkeiten durch Speicherobjekte. Ergänzend führt [LegacyWordPressTest](../tests/Integration/LegacyWordPressTest.php) reale Hooks, Benutzerrechte, Shortcodes, Mailabfang und SQL aus. Nebenläufigkeit und vollständige Modul-/Browserabläufe sind noch nicht abgenommen. Alle Funktionsbereiche besitzen nun Referenzszenarien im [Referenzkatalog](reference-catalog.md); Block 0 als Referenzphase ist abgeschlossen. Belege und den Folgeblöcken zugeordnete Restarbeiten stehen in [block-0-acceptance.md](block-0-acceptance.md).

## Ablösung der Verwaltungsbefunde ab Block 4

Die obigen Ist-Befunde beschreiben den analysierten Ausgangscode. Die großen Renn-/Routen-/Etappen-Schreibcontroller sind durch [Block 4](block-4-events.md) ersetzt. Teilnahmevoraussetzungen sind in Phase 0 erneut bearbeitbar, weitere Felder folgen der verbindlichen Matrix. Die Platzabfrage zählt inzwischen ausschließlich gespeicherte vollständige Teams. Historische Quellcodeverweise auf den entfernten Controller wurden auf dessen erhaltene Adapterdatei verkürzt; aktuelle Regeln und Quellen sind in Block 4 verlinkt. Andere Teilnehmer-/Ergebnisbefunde bleiben bis zur jeweiligen Modulkorrektur bestehen.
