# Masterplan cor-mgmt 2.0

Freigegebene Grundlage: 2026-11-01. Dieses Dokument verbindet Bestandsanalyse, Modernisierung, Dokumentation und Tests.

## Ziel und Grenzen

Funktionen, historische Daten, IDs und nötige Integrationen erhalten. PHP >= 8.2; CI mindestens 8.2/8.4. Abläufe erhalten, Oberfläche schrittweise modernisieren. Zahlungswege separat fachlich prüfen. Tests ohne echten Versand und produktive Schnittstellen ausführen. 2.0.0 ist das Releaseziel, nicht der aktuelle Zustand.

Eigenständiges WordPress-Plugin als verbindliches Releasekriterium: Installation per vollständigem ZIP auf frischem WordPress mit Standardtheme, ohne andere Fachplugins oder Buildwerkzeuge. Einrichtung, Upgrade, Deaktivierung, Reaktivierung und datenerhaltende Deinstallation gehören dazu. Siehe [Installationsvertrag](installation.md).

## Arbeitspakete

| Block | Arbeit | Abnahme |
|---|---|---|
| 0 Referenz | Shortcodes/Attribute, Formularaktionen, Rollen, Statusregeln, Schnittstellen, historische Fälle und Testgrundlage | Nachvollziehbare Referenzfälle mit erwarteten Ergebnissen, Lücken dokumentiert |
| 1 Sicherheit | SQL, Sortierung, Ausgaben, Nonces, Objektberechtigungen, Schreibmethoden, Uploads und geschützte Wartung | Negative Tests verhindern unberechtigte Änderungen |
| 2 Fundament | Namespaces, Composer-Autoloading, Schichten, Typen, Voraussetzungenprüfung, Plugin-Lebenszyklus und CI | Reproduzierbarer Build; alte Einstiegspunkte erreichbar |
| 3 Migration | Ist-Schema, neue Tabellen, Vorprüfung, Probelauf, Journal, Wiederaufnahme und Vergleich | Neuinstallation samt Einrichtung/Bestand ohne Verlust, Wiederholung ohne Duplikate |
| 4 Rennen/Routen | Veranstaltungen, Einstellungen, Etappen, Zuständigkeiten und Kapazitäten | Phasen, Historie und konkurrierende Kapazitätsänderungen geprüft |
| 5 Teams/Profile | Anmeldung, Einladung, Mitgliedschaft, Freigabe, Dashboard, Bilder und Zusatzfragen | Vollständiger Teilnahmeablauf; Abfragen ohne Schreibnebenwirkungen |
| 6 Ergebnisse | Ergebnistabelle, Punkteberechnung, Eingabe und Ranglisten | Historie verglichen; Altfehler gesondert dokumentiert |
| 7 Spenden | Zuerst fachliche Prüfung; danach bestätigte Adapter, Centbeträge und wiederholbare Bestätigungen | Fachentscheidung plus Erfolgs-/Fehler-/Wiederholungsfälle |
| 8 Kommunikation/Börse | Vorlagen, Empfänger, kontrollierter Versand und eigene Nachrichten | Rechte, Sprache und Empfänger stimmen; keine unbeabsichtigten Mehrfachsendungen |
| 9 Ticker/Medien | Texte, Kommentare, Bilder, Videos, Karten, authentifizierte Eingänge und cor-ticker-Adapter | Historische/neue Inhalte; sichere Uploads/Eingänge |
| 10 Gesamtabnahme | Eigenständiges ZIP, Standardtheme ohne Zusatzplugins, optionale Integrationen, Übersetzungen, Statistiken, Bedienung, Leistung und Release | Release-Kandidat mit geprüfter Wiederherstellung |

Testgrundlage und CI beginnen in Block 0; nötige Infrastruktur aus Block 2 wird für Sicherheitskorrekturen vorgezogen. Fachmodule benötigen die geprüfte Migrationsbasis vor ihrer Datenumstellung. Alte Handler bleiben über Adapter erreichbar. Kein konkurrierender Schreibbetrieb zweier Plugin-Versionen.

## Daten und Schnittstellen

InnoDB/utf8mb4, geeignete Indizes, normalisierte Etappenergebnisse, sichere Einladungen und explizite Statuswerte. Geld als Cent plus Währung. Historisch unklare Werte nicht raten. Konten, Passwörter, IDs, Medien und Orphans erhalten. Alte Tabellen bleiben während des Übergangs. Bestehende Shortcodes/Attribute erhalten; neue REST-Endpunkte unter cor-mgmt/v2 mit expliziten Rechten. Siehe [Migration](migration.md), [Architektur](architecture.md).

## Definition of Done

Installationsvertrag einschließlich Eigenständigkeit bestanden, Fach- und Fehlerfälle bestehen, Migrationen geprüft, PHP-8.2/8.4-Prüfungen und CI erfolgreich, Dokumentation/Arbeitsstatus aktuell. Bugfixes erhalten Regressionstests. Coverage-Zahl ersetzt keine fachliche Abdeckung. Offene Kriterien verhindern einen Abschlussstatus.

Vor Release frische Produktionskopie vollständig prüfen, kontrolliertes Wartungsfenster und finale Sicherung vorsehen. Rückweg muss neue produktive Schreibvorgänge berücksichtigen. Details: [Tests](testing.md), [Release](release.md), [Status](status.md).
