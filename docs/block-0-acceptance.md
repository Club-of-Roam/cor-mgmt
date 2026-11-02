# Block 0: Referenzabschluss und Übergabe

Stand 2026-11-02. **Block 0 als Analyse-, Funktionsreferenz- und Testgrundlagenpaket abgeschlossen.** Keine Sicherheitsfreigabe, keine Modernisierung der Laufzeit und keine Releaseabnahme. Plugin bleibt 1.2.10 / Schema 2.8, Anwendungscode unverändert.

## Abschlusskriterien und Belege

| Kriterium aus Masterplan | Ergebnis / Nachweis |
|---|---|
| Funktionen und nachvollziehbare Abnahmeszenarien je Bereich | [reference-catalog.md](reference-catalog.md): Rennen/Routen, Teams/Profile, Ergebnisse/Statistik, Spendenbestand, Kommunikation, Börse, Ticker/Medien, Verwaltung, Sprache und Lebenszyklus; konkrete Ausgangslagen, Aktionen, Erwartungen und Folgeblöcke |
| Shortcodes und Attribute | 23 Einträge in shortcodes.md / inventory.json, alle Registrierungen und Handler in echtem WordPress aufrufbar nachgewiesen. Nicht alle 23 Shortcodes vollständig gerendert |
| Formularaktionen und Eingabeflächen | 54 Methoden mit literalen Requestfeldern in actions.md. Quellprüfung der Einzel-/Bulkaktionen, schreibenden GETs und ausgelagerten Uploadpfade |
| Rollen, Statusregeln und Schnittstellen | interfaces.md, Phasenmatrix mit Archiv, business-rules.md und Entscheidungen D-01..18. Cap-/Menüregistrierung in WordPress geprüft; fehlende Objektprüfungen als Block-1-Arbeit erfasst |
| Historische Sonderfälle und Datenrisiken | migration.md, ranking-reference.md: Einladungspräzedenz, feste sechs Etappen, Spendenmethoden/-einheiten, Orphans, gemeinsame Tickertabellen, vorgegebene Archivierung ohne Neuberechnung |
| Ausführbare Fachreferenz | 84 isolierte Tests / 309 Assertions, echte Legacy-Methoden, synthetische Daten |
| WordPress-Integration als Prüfgrundlage | 33 Tests / 298 Assertions mit echtem WordPress 7.1.4 und MariaDB 11.4.13: Hooks/Shortcodes, 18 Caps, Benutzer/Metadaten, SQL, Mails abgefangen, HTTP gesperrt; minimale synthetische Legacy-Fixturetabellen |
| Reproduzierbare Werkzeuge und CI | PHPUnit/Composer-Lock, separate Konfigurationen, Core-Fixturewerkzeug, WordPress-Version/Archivhash und MariaDB-Version in Workflow festgelegt; Matrix PHP 8.2/8.3/8.4/8.5. Vier Versionen ausgeführt; kein Remote-GitHub-Lauf |
| Unveränderter Anwendungscode | Dokumentations-/Inventarprüfung bestätigt normalisierte Hashes aller 28 Legacy-PHP-Dateien. Keine Datenmigration, kein produktiver Versand, keine Git-Aktionen |
| Bewusst vertagte Fachdetails | Vorläufig alle Zusatzantworten bearbeitbar; Datumskorrektur erlaubt, Folgen später prüfen; konkrete Adminrollen/Archivsnapshot bei Umsetzung ausarbeiten. Keine pauschale Blockade der Referenzphase |

Aktuelle Laufbelege: [verification.md](verification.md). Syntaxprüfung blendet Veraltungen aus. Integration baseline-t nur bekannte Ladeveraltungen aus utilities/XChange; Laufzeittests verwenden E_ALL. Baseline ist keine Behebung oder PHP-Kompatibilitätsfreigabe.

## Tatsächlicher Umfang der Integrationstests

Die Tests laden den echten Plugin-Bootstrap, initialisieren das Plugin nach einer frischen Core-Installation und verwenden reale WordPress-APIs. Sie laufen in getrenntem Prozess von den Funktions-Doubles. Jede Testmethode verwendet eine Transaktion mit Rollback und Cachebereinigung; Fixtures enthalten ausschließlich synthetische Werte. Mail wird über pre_wp_mail aufgezeichnet, WP-HTTP über pre_http_request blockiert. Providerpfade mit direkten file_get_contents/get_headers werden nicht aufgerufen.

Dies ist eine schlanke PHPUnit-/WordPress-Integration, nicht die vollständige Core-WP_UnitTestCase-Testsuite. Die handgeschriebenen minimalen Fixturetabellen sind kein erfolgreiches Ausführen der defekten Legacy-Installation und keine Ziel-DDL. Keine Aktivierungs-, Browser-, Parallelitäts-, Medienupload- oder Liveprovider-Abnahme wird daraus abgeleitet.

## Restarbeiten nach Referenzabschluss

| Arbeitsblock | Konkrete noch nicht erledigte Arbeit |
|---|---|
| 1 Sicherheit | Nonce/Objektrechte/POST, SQL-/Sortier-/Ausgabeschutz, fremde Börsen-ID, Einladungs-/Ticker-/Uploadschutz mit negativen Integrationstests. Bekannte Fehler sind noch vorhanden |
| 2 Fundament | Runtime-Autoloading/Schichten, Fehlerbehandlung, PHP-Veraltungen beheben, PHPStan/PHPCS/PHPCompatibility/Frontendwerkzeuge ausbauen; eigenständigen Lebenszyklus implementieren |
| 3 Migration | Ziel-DDL, Journal/Probelauf/Wiederaufnahme, reale Schema-/Engine-/Zeichensatztests, Archivsnapshot und Statusmigration 3→Archiv, Werterhalt und Rückweg |
| 4 Rennen/Routen | Zielmatrix implementieren, vollständiges Admin-CRUD/Etappen, Warnungen bei Kapazitätsänderung, Konkurrenz-/Grenztests |
| 5 Teams/Profile | Vollständiger Teilnahme-/Einladungs-/Austrittsablauf, Upload/Avatar, Abfragen ohne Schreiben, Abschnittsfehler im Profil, phasenbezogene Rechte |
| 6 Ergebnisse | Reine korrigierte Berechnung, Validierung/atomare Fehlerbehandlung, vollständige Filter/Navigation und historische Rangfolge; keine Neuberechnung von Archiven |
| 7 Spenden | Separate Fachentscheidung der Zahlungswege, Betragseinheiten/Providerverträge, Timeout/Bestätigung/Wiederholung; historische Methoden bewahren |
| 8 Kommunikation/Börse | Selbstmailfehler, Empfänger und Sprache, Eigentums-/Ausgabeschutz, Queue/Deduplizierung und Wiederholungstests |
| 9 Ticker/Medien | Echter Gatewayvertrag, authentifizierte Eingänge, gemeinsame Tabellen/Adapter, sichere Bild-/Videopfade, Karte/Navigation/Privacy, Nachbarplugin-/Endpointübergang |
| 10 Gesamtabnahme | Vollständige Browser-/Barrierefreiheits-/Leistungs-/Übersetzungsprüfung und eigenständiges Release-ZIP mit Standardtheme, optionalen Integrationen und Wiederherstellung |

Diese Arbeiten gehören zur Umsetzung und Abnahme der jeweiligen Blöcke. Block 0 hat deren Referenzfälle und Prüfgrundlage geliefert; er behauptet keine schon bestehende Korrektheit des Altbestands oder der geplanten Version 2.

**Nächster Arbeitsschritt:** Block 1, zuerst die reproduzierte Eigentumslücke der Börse mit einem korrigierten negativen Test und anschließend die weiteren SEC-01..06-Pfade. Änderungen am Anwendungscode beginnen erst in diesem Umsetzungsblock; keine Commit-/Push-Erlaubnis ist damit verbunden.
