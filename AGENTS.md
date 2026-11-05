# Arbeitsregeln cor-mgmt

Vor Änderungen README.md, docs/masterplan.md, docs/status.md und docs/decisions.md lesen.

## Verbindliche Entscheidungen

- Ziel 2.0.0 für die jeweils neueste stabile PHP-Version; PHP 8.2 bleibt vollständig unterstützt. Syntax, APIs und Laufzeitabhängigkeiten müssen PHP-8.2-kompatibel sein. Zielmatrix und Releasevertrag gemäß docs/masterplan.md.
- Eigenständig installierbares WordPress-Plugin gemäß docs/installation.md: keine verpflichtenden Spezialthemes/Fachplugins, keine Server-Buildwerkzeuge oder manuell kopierten PHP-Endpunkte.
- Fachliche Funktionen, IDs, historische Daten, Shortcodes und benötigte Integrationen erhalten.
- Abläufe erhalten; Oberfläche schrittweise modernisieren.
- Zahlungswege erst nach dokumentierter fachlicher Prüfung implementieren.
- Plugin-/Schema-/Migrationsversionen unabhängig führen. Header erst beim entsprechenden Release ändern.

## Dokumentationsregeln

Beschreibungen bleiben rechnerunabhängig. Keine lokale Testeinrichtung, Rechnerpfade, Vorschau-Adressen, WAMP-Konfiguration oder Geschichte der Werkzeuginstallation dokumentieren. Benötigte Testsoftware, Versionen, allgemeine Prüfkommandos und Prüfergebnisse dürfen genannt werden.

## Umsetzung und Prüfung

1. Git-Diff prüfen, fremde Änderungen erhalten.
2. Paket und Abnahmekriterien aus docs/status.md bearbeiten.
3. Relevante Fach-, Fehler- und Berechtigungsfälle testen. Bugfixes erhalten Regressionstests.
4. Kleine überprüfbare Änderungen, keine reine Formatierungswelle im Altbestand.
5. composer check und composer test für PHP 8.2 bis zur neuesten stabilen Version ausführen; Zielmatrix gemäß docs/masterplan.md. Fehlende Läufe ausdrücklich ausweisen. Betroffene Integrations-/Browserprüfungen ergänzen, sobald vorhanden.
6. Nach Schnittstellenänderungen python tools/build-inventory.py ausführen und Diff prüfen.
7. Status, Entscheidungen und Prüfbelege aktualisieren. Nicht ausgeführte Prüfungen ausdrücklich ausweisen.

## Isolation

Keine produktiven DB-Verbindungen, Mails, Zahlungen oder externen Ticker-Schreibzugriffe aus Entwicklung/CI. Synthetische Testdaten verwenden; bestehende Anwendungsdaten und Zugangsdaten nicht für automatisierte Tests übernehmen.

Seit Block 5 ist is_complete() ausschließlich lesend. Statusänderungen und Benachrichtigungsereignisse laufen über TeamManager; keine Schreibnebenwirkungen in Reader zurückbringen. Ergebniswriter verwenden nur ausdrücklich eingerichtete Wertungsprofile; historische Summen/Darstellung nicht mit neuer Scoring-Logik überschreiben. Ergebnis-Modul und legacy-shadow-v1 getrennt halten. Übrige Legacy-Module weiterhin nur mit passenden Isolationen prüfen. Alte Tabellen/Orphans nicht automatisch löschen; keine Massendatenmigration bei Seitenaufrufen.

Theme-, cor-ticker- und ausgelagerte Endpoint-Änderungen getrennt dokumentieren. Keine Geheimnisse, Dumps oder privaten Medien einchecken.

Fachlogik unabhängig von WP/SQL/HTTP halten. Zeit, Zufall und Dienste injizieren. Rechte am Objekt prüfen; Nonces ersetzen keine Autorisierung. Abfragen ohne Schreibnebenwirkungen.

Keine Pflicht zur Delegation oder Nutzung von Subagenten. Commit, Push und Deployment entsprechend der jeweiligen Benutzerfreigabe.

Bei einem geeigneten Commit-Zwischenstand direkt eine englische Commit-Message mitliefern. Commit und Push führt der Benutzer aus.
