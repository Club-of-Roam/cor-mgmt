# Teststrategie

## Ausführbare Startphase

PHPUnit-Referenztests prüfen die bestehende Platz-/Punktetabelle und Telefonnummern-Normalisierung. Klassen werden ohne Konstruktor verwendet; kein WordPress-Bootstrap, keine Datenbank, keine Netzwerk-/Mailzugriffe. Nur synthetische Eingaben. Das belegt keine Sicherheit oder Vollkompatibilität des übrigen Plugins.

- composer install: gesperrte Entwicklungsabhängigkeiten installieren (CLI PHP >= 8.2).
- composer test: isolierte Referenztests.
- composer check: Composer-Metadaten, PHP-Syntax und Dokumentations-/Inventarkonsistenz.
- python tools/build-inventory.py: Inventar regenerieren, danach Diff prüfen.

GitHub Actions prüft PHP 8.2/8.4 nach Push. Konfiguration ist kein bereits ausgeführter GitHub-Lauf.

Lint erfasst den Altbestand, blendet Veraltungsdiagnosen bewusst aus: Nur Syntaxfehler führen zum Abbruch. Veraltungen sind in analysis.md dokumentiert. PHPUnit läuft für die abgedeckten Pfade mit strenger Warnungsbehandlung.

## Weitere Testebenen

| Ebene | Werkzeug | Inhalt |
|---|---|---|
| Fachlogik | PHPUnit | Punkte, Zusatzpunkte, Status, Einladungen, Geld |
| WordPress | WordPress-PHPUnit-Umgebung | Hooks, Shortcodes, Objektberechtigungen, REST, Datenzugriff |
| Migration | PHPUnit + MariaDB | Neu/Alt, Zeichensätze, Orphans, Abbruch, Wiederaufnahme, Vergleich |
| Dienste | Injizierte HTTP-/Mail-/Zeit-/Zufallsadapter | Fehler, Timeout, Wiederholungen, ungültige Bestätigungen |
| Browser | Playwright | Teilnahme, Admin, Ergebnisse, simulierte Spenden, Medien/Ticker |
| Statische Analyse | PHPStan | Neue Fachmodule streng, Altbestand schrittweise mit belegter Baseline |
| Standards | PHPCS + WPCS + PHPCompatibility | Neue/geänderte Module zuerst, Altlasten sichtbar halten |
| Frontend | ESLint und Barrierefreiheitsprüfung | JS, Labels, Fokus, mobile Formulare; manuelle Tastaturtests ergänzen |

PHPStan, PHPCS, PHPCompatibility, Playwright, ESLint und WP-/Migrationstests sind geplant, noch nicht eingerichtet. Keine leeren Tests als Abdeckung ausweisen. Werkzeugversionen bei Einführung auf Kompatibilität prüfen und mit Lockdateien festschreiben.

## Allgemeine Testgrundsätze

Synthetische Testdaten verwenden und bestehende Anwendungsdaten unangetastet lassen. Externe Dienste einschließlich Mail- und Zahlungsversand werden simuliert. Echte Provider-Vertragstests nur separat aktiviert, nicht im Standard-PR-Lauf.

## Eigenständigkeit und Installationspaket

Die Fälle I-01 bis I-12 aus dem [Installationsvertrag](installation.md) sind verpflichtende Integrations-, Paket- und Browserprüfungen. Das tatsächliche Release-ZIP auf frischem WordPress mit Standardtheme und ohne weitere Fachplugins prüfen, nicht nur den Entwicklungscheckout. Neuinstallation und Bestandsupgrade getrennt abdecken; Wiederholbarkeit, fehlende Voraussetzungen, optionale Integrationen, Deaktivierung/Reaktivierung und datenerhaltende Deinstallation prüfen. Noch nicht implementiert oder ausgeführt.

## Qualitätsregeln

Tests sichern Verhalten und müssen bei plausiblen Fehländerungen scheitern. Bugfixes erhalten Regressionstests. Kritische Regeln benötigen Grenz-, Fehler- und Berechtigungsfälle. Keine bloßen Methodennamen-Tests oder pauschale Snapshot-Flut. Coverage ist Orientierung, kein Ersatz für Fachfälle.

CI-Artefakte nur mit synthetischen Daten, Screenshots/Traces bei Browserfehlern und mit begrenzter Aufbewahrung. Manuelle Fälle bleiben offen, bis ausgeführt.

## Primärquellen

- [PHPUnit 11: PHP >= 8.2](https://docs.phpunit.de/en/11.5/installation.html)
- [PHPStan](https://phpstan.org/user-guide/getting-started)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP)
