# Arbeitsstatus und Übergabe

Stand 2026-11-01. Ziel 2.0.0; laufender Code 1.2.10 / Schema 2.8.

## Verbindliche Ergänzung: Eigenständigkeit

Installationsvertrag in installation.md festgehalten. Blöcke 2/3 liefern Voraussetzungenprüfung, Einrichtung und Lebenszyklus; Block 9 beseitigt die zwingende Ticker-Abhängigkeit; Block 10 prüft das fertige ZIP ohne Spezialtheme/Zusatzplugins. Diese Anforderungen sind dokumentiert, noch nicht implementiert oder abgenommen.

## Startphase

Masterplan, Analyse, Referenzszenarien, Entscheidungen, Architektur, Migration, Test- und Releasekonzept im Repository dokumentiert. Statisches Schnittstelleninventar und erste isolierte Tests mit CI-Matrix vorbereitet. Anwendungscode, Versionsheader, DB unverändert.

| Block | Status | Nächste Arbeit |
|---|---|---|
| 0 | begonnen | Statuskombinationen vertiefen, WordPress-Integrationstests ergänzen, Referenzabläufe ausführen |
| 1 | offen | SEC-01..06 synthetisch absichern und korrigieren |
| 2 | begonnen: Testgrundlage | Werkzeuge ausbauen, Autoloading/Schichten einführen; keine Runtime-Umstellung erfolgt |
| 3 | offen | Exakte DDL, Feldmapping und Migrationswerkzeug |
| 4–6 | offen | Rennen/Routen, Teams/Profile, Ergebnisse |
| 7 | offen | Fachliche Zahlungsprüfung zuerst |
| 8–9 | offen | Kommunikation/Börse, Ticker/Medien |
| 10 | offen | Gesamtabnahme und geprüfter Produktionswechsel |

Prüfergebnisse: [verification.md](verification.md). Workflow-Konfiguration ist kein belegter GitHub-Lauf. Keine WordPress-/Migrations-/Browserabnahme erfolgt.

## Nächste Bearbeitung

1. Git-Diff und AGENTS.md prüfen; vorhandenen Fortschritt erhalten.
2. composer install, composer check, composer test mit PHP >= 8.2.
3. Block 0 gemäß features.md vervollständigen. Schreibtests müssen bestehende Anwendungsdaten unangetastet lassen.
4. Befunde in analysis.md priorisieren; Altfehler von erhaltenswerter Funktion trennen.
5. Nach jedem Paket Status, Nachweise und Entscheidungen aktualisieren. Kein vorzeitiger Versionssprung.
