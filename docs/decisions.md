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
| D-09 | zurückgestellt | Künftige Zahlungswege erst nach separater Fachprüfung; historische Methoden sind keine Freigabe |
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

## Offene Fachprüfung

Die groben Fachfragen FACH-01 bis FACH-05 sind durch die Matrix und Nachträge D-17/18 beantwortet. Spätere Detailarbeit: Zusatzantworten genauer klassifizieren (bis dahin alle erlaubt), Wirkungen von Datumskorrekturen, konkrete Rollen-/Einzelrechte für Teilnehmer-Adminaktionen, Snapshotumfang des Archivs und zulässige variable Punkte. Wechsel in ein anderes Rennen separat behandeln. Ein gewöhnlicher Rückwechsel aus dem eingefrorenen Archiv ist nicht beschlossen. Historische Datenfehler sind weiterhin nicht durch den Codebefund allein nachgewiesen.

- Block 7: aktive Verfahren versus Archiv, maßgebliche Bestätigung, Testumgebung des Anbieters ermitteln; vor Implementierung dokumentieren.
- Block 6: historische Punktefehler zunächst mit Ist-/Soll-Vergleich belegen; keine stille Neuberechnung der Historie.
- Block 3/7: Betragseinheiten pro Schreibpfad nachweisen; Mehrdeutiges berichten, keine pauschale Multiplikation.
- Block 9: realen Gateway-Vertrag, Zeichensatz und Zeitformat ermitteln; vorher keine Rückwärtskompatibilität behaupten.

Diese Fragen verhindern keine Dokumentation/Referenztests, aber die jeweilige unbelegte fachliche Umstellung.
