# Entscheidungen

Stand 2026-11-02.

| ID | Status | Entscheidung |
|---|---|---|
| D-01 | beschlossen | 2.0.0 als Ziel; Plugin-/Schema-/Migrationsversion getrennt |
| D-02 | ergänzt 2026-11-02 | Jeweils neueste stabile PHP-Version als Ziel, vollständiger Funktionsumfang ab PHP 8.2 einschließlich Zwischenversionen. Syntax/APIs/Laufzeitabhängigkeiten PHP-8.2-kompatibel. Zielmatrix derzeit 8.2/8.3/8.4/8.5 gemäß masterplan.md; Mindestversion nicht automatisch anheben. PHP 7.4 muss nicht unterstützt werden |
| D-03 | beschlossen | Abläufe erhalten, Oberfläche schrittweise modernisieren |
| D-04 | beschlossen | Modulares WordPress-Plugin; kein vollständiger Framework-/SPA-Neubau erforderlich |
| D-05 | beschlossen | Historie/IDs/Orphans erhalten, keine automatische Bereinigung |
| D-06 | beschlossen | Dokumentation und Tests begleiten jeden Block |
| D-07 | beschlossen | Synthetische Testdaten, eigene DB/Uploads, kein echter Versand oder Zahlungszugriff |
| D-08 | beschlossen | Bestehende Shortcodes und Attribute kompatibel halten |
| D-09 | präzisiert 2026-11-05 | Für neue Spenden ausschließlich Betterplace (D-39/40); historische Methoden erhalten. Weitere Automatisierung und Anbieterfreigabe getrennt prüfen |
| D-10 | beschlossen | PHPUnit 11.5-Linie passt zu PHP 8.2; genaue Version in composer.lock |
| D-11 | beschlossen | Eigenständiges WordPress-Plugin mit vollständigem ZIP und Einrichtung; keine verpflichtenden Spezialthemes oder weiteren Fachplugins. Lebenszyklus und Abnahme gemäß installation.md |
| D-12 | präzisiert 2026-11-02 | Drei Begriffe getrennt führen: Rennregeln ändern; Zahlung/Haftungsverzicht pro Teilnehmer bestätigen oder zurücknehmen; Team automatisch zurückstufen. Ab Rennstart sind die beiden Backend-Aktionen gesperrt und die automatische Rückstufung samt dadurch verursachtem Routenverlust ausgeschlossen. Begriffliche Klarstellung der bereits beabsichtigten Logik, keine neue Funktion. Andere Einflusswege mit Phasenregeln abstimmen |
| D-13 | Kernregeln beschlossen 2026-11-02 | Einladungen betreffen Mitgliedsplätze im Team, reservieren keinen Platz und sind fachlich nicht in ihrer Anzahl begrenzt. Kapazität bei Annahme atomar prüfen/belegen; volles Team verhindert Annahme. Gültig bis zum Ende der offenen Anmeldung; danach und nach Rennstart keine Annahme. D-17 ergänzt: Versand/Annahme nur in Phase 1. Wiederöffnung durch D-18 geregelt; Wechsel in anderes Rennen gesondert behandeln |
| D-14 | beschlossen 2026-11-02 | Unvollständige Teams belegen keinen Routenplatz; Kapazität bei Freigabe/Zuweisung prüfen, keine Überbuchung durch parallele Zugriffe |
| D-15 | beschlossen 2026-11-02 | Historische Ergebnisse bei Berechnungsfehlern nicht rückwirkend verändern, auch nicht indirekt durch Migration/Anzeige. Historiengrenze durch D-18 ergänzt: alle Bestandsrennen mit Ausgangsstatus 3 werden ohne Neuberechnung archiviert |
| D-16 | Aufnahme in Liste bestätigt 2026-11-02 | Sammelbegriff Rennregeln durch acht Gruppen ersetzen: Grundstruktur/Kapazitäten, Teilnahmevoraussetzungen, Darstellung, Organisation/Ablauf, Funktionsschalter, Wertung, Teilnehmerbestätigungen, berechneter Teamstatus. Behandlungsvorschläge und Quellen in business-rules.md. Anfängliche Gruppierung; konkrete Änderungsregeln jetzt durch D-17 und phases.md ersetzt |
| D-17 | Zielvorgabe übernommen 2026-11-02 | Vom Benutzer korrigierte vollständige Vier-Phasen-Matrix in [phases.md](phases.md). Ersetzt frühere Vorschläge: Teamgröße/Teilnahmevoraussetzungen in gesamter Phase 0 änderbar; Routenkapazität/Startpunkte auch Phase 1; Darstellung und redaktionelle Texte ab Start gesperrt; organisatorische Hinweise weiterhin erlaubt. In Phase 1/2 automatische Rückstufung und Routenentfernung. Nachträge und fünfte Phase gemäß D-18 ersetzen die bisherigen offenen Vorbehalte. Noch nicht implementiert |
| D-18 | beschlossen 2026-11-02 | Rückwechsel zwischen 0..3 stellen die Rechte der Zielphase vollständig wieder her. Admin-Ausnahmen für Teilnehmerangelegenheiten, keine pauschalen Event-Overrides. Kapazitäts-/Startpunktkonflikte warnen, Änderungen ermöglichen, keine automatische Löschung/Umverteilung. Zusatzantworten vorläufig alle bearbeitbar; spätere Präzisierung vormerken. Beginn/Ende im Adminbereich auch nach Start änderbar, Wirkungen später prüfen. Neue Phase Rennen beendet: rennbezogener Stand unveränderlich, keine Neuberechnung; sämtliche Bestandsrennen mit Ausgangsstatus 3 beim Umstieg ohne Neuberechnung direkt dorthin migrieren. Details in phases.md/migration.md, keine Implementierung erfolgt |
| D-19 | technisch umgesetzt 2026-11-02 | WordPress-Referenzintegration getrennt von isolierten Funktions-Doubles, echtes Core/Benutzer/wpdb mit synthetischen Minimalfixtures und gesperrtem Versand. WordPress 7.1.4/Archivhash sowie MariaDB 11.4.13 für reproduzierbare Referenz festgelegt; PHP 8.2..8.5 geprüft. Keine Runtime- oder Neuinstallationsfreigabe daraus ableiten |

| D-20 | technisch umgesetzt 2026-11-02 | Sicherheitsgrenzen ab Block 1 gemäß block-1-security.md: keine schreibenden GET-Aktionen; Nonce plus Objektberechtigung; Ticker-Gateway nur als signierter POST mit Replay-Schutz. Alte Uploadskripte separat sperren. Unverifizierte browsergesteuerte Zahlungsbestätigungen bleiben gesperrt; kein neuer Zahlungsweg ohne Fachprüfung |
| D-21 | technisch umgesetzt 2026-11-02 | Historisches overlayer_maker-Formular über geschützten WordPress-Adapter erhalten, Anmeldung/Nonce und kontrollierte Bildverarbeitung verlangen. Alte freie Upload-URL HTTP 410; optionale Overlaygrafik/GD-Abhängigkeit bis Block 9 gesondert dokumentiert |

| D-22 | technisch umgesetzt 2026-11-03 | PSR-4/typisierte Adapter aus Block 2; Legacy-Einstiegspunkte bleiben hinter Kompositionswurzel erhalten. Neue Anwendungsfälle erhalten ihre Abhängigkeiten direkt, kein neuer globaler Service-Locator für Fachlogik |
| D-23 | technisch umgesetzt 2026-11-03 | Schemaerkennung bleibt lesend. Keine automatische Versionsanhebung oder Reparatur bei Aufrufen/Aktivierung. Neuer Bestand bleibt bis Block 3 einrichtungsbedürftig; unvollständiges/neueres Schema wird gesperrt. Deinstallation erhält Daten und bestehende Rechte |
| D-24 | technisch umgesetzt 2026-11-03 | Reproduzierbarer Paketbau mit separatem Composer-no-dev-Staging. Statische Prüfung streng für neue Klassen; Altbestand nicht durch pauschales Ignorieren als modernisiert ausweisen. Begrenzte PHPCompatibility-9-Abdeckung ausdrücklich dokumentieren |

| D-25 | technisch umgesetzt 2026-11-03 | Storage-Schema 3.0.0 und Schritt legacy-shadow-v1 getrennt von Plugin-Header 1.2.10 und Legacy-Marker 2.8. Erstinstallation erzeugt geprüfte eigene Tabellen; Bestandsmigration lässt Source unverändert und erzeugt laufbezogene Zwischenkopien. Keine automatische Aktivierung |
| D-26 | technisch umgesetzt 2026-11-03 | Private HEX-Payloads erhalten originale Zeichenbytes, NULL, unbekannte Spalten, IDs und Orphans. Keine geratene Cent-/Währungskonvertierung. Phasenmapping 3 nach 4 ist in der Kopie vorbereitet, vollständiger unveränderlicher Archivsnapshot mit Benutzer-/Medien-/Tickerbezug folgt mit den Fachmodulen |
| D-27 | technisch umgesetzt 2026-11-03 | Administratorrecht, POST und Nonce für die Datenbankwerkzeuge; Backup und gestoppte Writer für Kopierläufe voraussetzen. Advisory-Lock schützt kooperierende Werkzeugaufrufe, Quellhashvergleich erkennt Abweichungen, beides ersetzt kein Wartungsfenster bei MyISAM |

## Umsetzung Block 4 (2026-11-04)

- D-28: Beschlossene Phasenregeln für Verwaltungsfelder umgesetzt, keine zusätzliche Fachfreigabe. Routenname als Darstellung, Etappenziel/Land als Struktur, Treffpunkt als Organisation; gemischte Informationstexte ab Start sperren. Quellen/Zuordnung in block-4-events.md.
- D-29: Vor der vollständigen Archivierung bleibt Phase-4-Abschluss gesperrt. Bestehende Archivzeilen sind unveränderlich; kein unvollständiges Archiv durch bloßen Statuswechsel anbieten.
- D-30: Kompatibilitätsrepository bleibt vorerst maßgeblich. Warnbestätigung, Revisionen und Sperre schützen neue Verwaltungswriter; transaktional erforderliche Operationen auf MyISAM bis zur kontrollierten Schemaumstellung sperren. Kein stilles ALTER während Verwaltung oder Aktivierung.

## Umsetzung Block 5 (2026-11-04)

- D-31: Neue Codes erhalten einen Hash-/Verbrauchs-/Widerrufsspeicher. Alte Codes bleiben unverändert und werden kanonisch journalisiert; ihre Tabelle wird nur gelesen. Phasen schließen/öffnen die Annahme; kein erfundenes Kalenderablaufdatum.
- D-32: Team-/Profilwriter und Rennvorgaben verwenden dieselbe Sperre und Transaktion. Freigabe ist eine explizite Schreibaktion; is_complete ist lesend. Unvollständige Teams reservieren auch durch vorläufige Zuweisung keinen Platz.
- D-33: Teilnahme-Modulversion 1.0.0 mit eigenem Installationsmanifest; Storage-3.0.0-Pläne und bisherige Marker bleiben stabil. Einrichtung ausdrücklich oder bei Aktivierung, ohne DDL während gewöhnlicher Seitenaufrufe.
- D-34: Leere Teams und entfernte Beziehungen bleiben historisch erhalten. Unklare Mehrfachmitgliedschaften und Rennwechsel werden gesperrt statt geraten. Eigener Avataradapter mit optionalem Legacy-Leseadapter; kompletter Archivsnapshot und Versandqueue bleiben den Folgeblöcken zugeordnet.

## Umsetzung Block 6 (2026-11-05)

- D-35: Neue Wertung erhält ein ausdrückliches Rennprofil 2.0.0. Aktivierung nur Phase 0 ohne historische Ergebniswerte; historische Rennen behalten gespeicherte Summen und bisherigen Renderer. Der alte fehlerhafte Writer existiert nur noch als nicht ausgelieferte Testreferenz.
- D-36: Ergebnis-Modul 1.0.0 ist additiv und unabhängig von Storage 3.0.0, Legacy-Schema 2.8 und Plugin-Release. Ein Ergebnis gehört zu Team- und Etappen-ID; die ersten sechs Legacy-Slots und total_points sind bei neuen Wertungen nur eine Kompatibilitätsprojektion.
- D-37: Ergebnisschreiben ist nur Phase 3 mit Route-/Rennberechtigung zulässig. Validierung aller Teams vor Schreibbeginn, gemeinsame Sperre/Transaktion, Revision einschließlich Journalgeneration, gespeicherte Regelwerte und Änderungsjournal. Identische Wiederholung verändert das Journal nicht. Gewertete Etappen werden nicht strukturell umgehängt/gelöscht; Teams mit Ergebnissen wechseln nicht unkontrolliert die Route.
- D-38 (fachlich bestätigt 2026-11-05): Zusatzpunkte bleiben bei fehlender Platzierung (0) und Disqualifikation (99) möglich. Variable Zusatzpunkte bleiben bei aktiviertem Funktionsschalter auf Etappe 6 begrenzt. Beide Antworten bestätigen das bereits implementierte Verhalten; der bestehende technische Eingabebereich 0..255 bleibt unverändert. Historische Ergebnisse werden nicht neu berechnet. Weitere Etappen können weiterhin Platz-/feste Zusatzpunkte erhalten.

## Offene Fachprüfung

Die groben Fachfragen FACH-01 bis FACH-05 sind durch die Matrix und Nachträge D-17/18 beantwortet. Spätere Detailarbeit: Zusatzantworten genauer klassifizieren (bis dahin alle erlaubt), Wirkungen von Datumskorrekturen, konkrete Rollen-/Einzelrechte für Teilnehmer-Adminaktionen, Snapshotumfang des Archivs. Die beiden Zusatzpunktfragen sind durch D-38 beantwortet. Wechsel in ein anderes Rennen separat behandeln. Ein gewöhnlicher Rückwechsel aus dem eingefrorenen Archiv ist nicht beschlossen. Historische Datenfehler sind weiterhin nicht durch den Codebefund allein nachgewiesen.

- Block 7: Betterplace und bestehender Zuordnungsablauf durch D-39/40 bestätigt. Anbieterzugang, weitere Automatisierung, Rückerstattungen und Bestandsabgleich bleiben offen.
- Block 6: historische Punktefehler zunächst mit Ist-/Soll-Vergleich belegen; keine stille Neuberechnung der Historie.
- Block 3/7: Betragseinheiten pro Schreibpfad nachweisen; Mehrdeutiges berichten, keine pauschale Multiplikation.
- Block 9: Historische Zeichensätze, Zeitformate und Nachrichtentypen für den Datenerhalt ermitteln. Der frühere Auftrag zur Wiederanbindung des SMS-Gateways entfällt durch D-50; dessen alte Eingänge kontrolliert stilllegen.

Diese Fragen verhindern keine Dokumentation/Referenztests, aber die jeweilige unbelegte fachliche Umstellung.

## Umsetzung Block 7 (2026-11-05)

- D-39 (fachlich bestätigt): Für neue Spenden wird nur Betterplace genutzt. Historische Betterplace-/PayPal-/Lastschriftdatensätze bleiben erhalten; neue andere Zahlungen werden nicht angeboten.
- D-40 (fachlich bestätigt): Bestehenden Ablauf einschließlich „Fertig“ und serverseitiger Kommentarzuordnung beibehalten. Weitere Automatisierung ausdrücklich zur Prüfung vormerken. Browserklick/Rückleitung allein bestätigen nichts; keine erfundene manuelle Bestätigungsregel. Befunde und offene Anbieterfreigabe in [Block 7](block-7-donations.md).
- D-41 (technisch): Additives Spenden-Modul 1.0.0 mit exakten neuen EUR-Centbeträgen, einmaliger Opinion-Zuordnung und Journal. Historische Einheiten bleiben ungeklärt; alte Beträge werden nicht pauschal konvertiert. Legacy-Adminwriter dürfen modulverwaltete Bestätigungen nicht umgehen.

- D-42 (Anzeigevorgabe 2026-11-05): Frontend zeigt weiterhin ganze Euro; entsprechend vorhandener Ganzzahlkonvertierungen Nachkommastellen abschneiden. Centgenaue Speicherung und Verwaltung bleiben erhalten. Summen zuerst exakt bilden, dann anzeigen.
- D-43 (technisch): Kontrollierte Profil-/Sichtbarkeitsverwaltung mit Objektberechtigung, Revision und Journal. Alte offene Vorgänge nur ausdrücklich zur Anbieterprüfung vorbereiten; keine geratene Bestätigung, Betragseinheit oder neue Sitzungsberechtigung.

## Planung Block 8 (2026-11-05)

- D-44 (vom Benutzer vorgegeben): Autoresponses zwingend je Event unabhängig erzeugen und einstellen können. Zwei parallele Events dürfen nicht dieselbe globale Konfiguration verwenden. Auflösung je Veranstaltung/Aktion/Sprache; Kopien bleiben unabhängig. Veranstaltung des Auslösers maßgeblich, kein Rückgriff auf das aktive Rennen oder fremde Eventvorlagen. Bestandsübernahme ausdrücklich und datenerhaltend; Umsetzung und Tests gemäß [Block-8-Plan](block-8-communication-plan.md) noch offen.

- D-45 (technisch 2026-11-08): Vorlagenbasis als erstes Teilpaket mit unabhängigem Kommunikations-Installationsmanifest 1.0.0. Globale Originale unverändert erhalten; neue Konfiguration hat bis zur expliziten Auslöser-/Queue-Umschaltung keine Versandwirkung. Übergang in beiden Verwaltungsseiten kennzeichnen. Strikter neuer Resolver ohne Event-/Sprachfallback, Kopien nur nach Vorschau/Fingerabdruck und ohne Zielüberschreiben. Keine zusätzlichen Zahlungswege aktivieren.

- D-46 (technisch 2026-11-08): Queue-Grundlage mit eigenem unveränderlichem Installationsplan; Einreihen ausschließlich in der Fachtransaktion mit ausdrücklicher Vorgangsidentität und fixiertem Event-/Vorlagen-/Empfängersnapshot. Transport außerhalb der Transaktion, standardmäßig gesperrter und noch nicht registrierter Worker. Unklare Ausgänge niemals automatisch wiederholen. Auslöseranbindung und Verwaltungsfreigabe sind ein nachfolgender Schritt; kein vorgezogener Wechsel des alten Mailers. [Vertrag und Grenzen](block-8-queue.md).

- D-47 (technisch 2026-11-08): Teamereignisse und Queueeinträge atomar verbinden; eventbezogene Vorlagen sind für diese Auslöser wirksam. Dauerhafte Journal-ID als Vorgangsidentität, eigener Eventlink und kein globaler Mailerfallback. Native Queueverwaltung mit Objekt-/Nonce-/Revisionsschutz und Akteursjournal. Nur geprüfter Einzelversand nach Server- und Eventfreigabe; kein Scheduler. Fehlende Vorlagen nur ausdrücklich nachbereiten, bestehende Textsnapshots erhalten; unklare Ausgänge nur nach externer Prüfung schließen/bestätigen. Kein echter Versand durch Einrichtung oder Umsetzung. [Vertrag und Grenzen](block-8-delivery.md).

- D-48 (technisch 2026-11-08): Manuelle Kampagnen besitzen eigene unveränderliche Empfänger-/Text-/Absendersnapshots und technische Queue-Scope 0; keine erfundene Veranstaltung oder Archiveingriffe. Vorschau/Fingerabdruck und Anfrageschlüssel, Queue-Schritte maximal 100, bestätigte Versand-Schritte maximal 5, individuelle Freigabe/Pause und gemeinsame bestehende Transportgrenzen. Ausdrücklich gestarteter Browserlauf verarbeitet dieselben begrenzten Schritte mit Stop/Fortschritt; Rechte und Revision bei jedem Request, Fehler ohne automatischen Retry. Globale Gruppen und fremde Aufträge nur mit zusätzlichen Administratorrechten; Rechte bei jeder Aktion erneut prüfen. Keine automatische Übersetzung oder Auswahl bei widersprüchlichen gemeinsamen Adressen. [Vertrag](block-8-completion.md).
- D-49 (technisch 2026-11-08): Börse bleibt entsprechend bestätigtem Bestand global; keine stillschweigende Bindung an Event-Autoresponses. Native Formulare, paginierte Anzeige, Eigentum und Revisionen, transaktionales Journal sowie ausdrücklich administrative Moderation. Historische IDs/Duplikate und automatische Änderungszeit erhalten; keine automatische Engine-Konvertierung oder Löschung. Aufbewahrung zunächst nur lesend erfassen; destruktive Bereinigung erst nach ausdrücklicher Frist-/Anonymisierungsentscheidung.


## Integration Ticker und gemeinsame Abnahme (2026-11-09)

- D-50 (vom Benutzer vorgegeben): SMS-Ticker ist eingestellt. Der benötigte Webticker einschließlich Verwaltung wird vollständig in cor-mgmt integriert; cor-ticker wird beim kontrollierten Wechsel abgelöst. Kein neuer SMS-/MMS-Gatewaybetrieb. Historische Inhalte und IDs auch aus sms_ticker/sms_ticker_status erhalten; Bestandsbezeichnung nicht mit aktiver SMS-Funktion gleichsetzen. Alte Endpunkte und konkurrierende Writer gezielt stilllegen, notwendige Anzeigen/Shortcodes kompatibel erhalten. [Plan](block-9-ticker-plan.md). Dies ist die Zielentscheidung, keine bereits ausgeführte Abschaltung oder Datenmigration.
- D-51 (vom Benutzer vorgegeben): Fachliche und visuelle Abnahme aller Bereiche gemeinsam am Ende in Block 10. Die bisher offenen Modulabnahmen werden dorthin gebündelt; automatisierte Tests und technische Prüfungen laufen während der Umsetzung weiter. Keine vorgezogene Versand-/Anbieter- oder Produktionsfreigabe daraus ableiten.


- D-52 (technisch 2026-11-09): Eigenes additives Ticker-Modul statt Adoption der gemeinsamen Alttabelle. Begrenzte explizite Kopie und Zweitprüfung mit Original-IDs, bytegetreuen Snapshots und Quellstillstand; kein konkurrierender Writer. Historische Quellen und Statusdaten bleiben unverändert erhalten. Native Webbefehle werden protokolliert; SMS-/MMS-Eingänge liefern 410. [Umsetzung und Übergangsgrenzen](block-9-ticker.md).

- D-53 (technisch 2026-11-10): Rennarchive erhalten einen eigenen Installationsplan 1.0.0. Originalbytes und öffentliche Projektion getrennt speichern, lokale geprüfte Bilder kopieren, Kontopasswörter und Konto-E-Mail-Adressen nicht in das Archiv übernehmen. Erst nach Kopie, unabhängigem Vergleich und abschließendem Quellvergleich veröffentlichen. Neue Rennabschlüsse sowie historische Migration unterscheiden; keine Neuberechnung gespeicherter Punkte. Fehlende Medien ausdrücklich ausweisen. [Archivvertrag](block-10-archive.md).
- D-54 (technisch 2026-11-10): Aktive Migration über parallele, vollständig verglichene Fachtabellen mit allen bisherigen Spalten/IDs. Namen in einem gemeinsamen MariaDB-RENAME austauschen; Originale erhalten, Archivzeiger und Laufzeitgeneration kontrolliert veröffentlichen. Fehlerhafte Generationen und Wartungszustände sperren gewöhnliche Writer. MySQL-Umschaltung bis zur eigenen Prüfung abweisen. Rückweg nur bei unverändertem Fach-/Konto-/Profilfingerabdruck; keine neuen Daten verwerfen. Fremde Writer und Profile während der gesamten Wartung pausieren. [Umschalt- und Rückwegvertrag](block-10-archive.md).
