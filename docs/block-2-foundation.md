# Block 2: technisches Fundament

Stand 2026-11-03. Umsetzung der ersten modularen Laufzeitbasis; Plugin-Version 1.2.10 und Schema-Version 2.8 bleiben erhalten. Keine Datenmigration oder historische Neuberechnung.

## Umgesetzt

- Composer PSR-4 unter `Cor\Mgmt`, PHP-8.2-kompatible Syntax, getrennte Application-, Domain-, Infrastructure- und WordPress-Bereiche.
- [Bootstrap](../h3-management.php) als Kompositionswurzel; bestehende Globals, Funktionen, Hooks, Adminseiten und 23 Shortcodes liegen weiterhin hinter [Legacy-Adapter](../runtime/legacy.php). Fachmodule werden in ihren jeweiligen Blöcken ersetzt, nicht parallel neu geschrieben.
- Typisierte Schnittstellen für Uhrzeit, kryptografischen Zufall, HTTP, Mail und Fehlerprotokollierung. Neue Anwendungsfälle erhalten Abhängigkeiten explizit. `Services` ist ausschließlich die austauschbare Brücke für den Altbestand.
- Bestehender Mailversand, Betterplace-Abruf, Token-/Einladungszufall und Ticker-Uhrzeit verwenden diese Adapter. WordPress-Filter für Versand-/HTTP-Sperren bleiben wirksam. Kein neuer Zahlungsweg.
- HTTP verwendet die WordPress-API mit maximal zehn Sekunden, keinen Weiterleitungen und zwei MiB Antwortgrenze. Transportfehler werden typisiert; das Protokoll enthält im Debugbetrieb nur Fehlercode und Ausnahmetyp, keine Nutzdaten oder Zugangsdaten. Kein globaler Handler, der beliebige Fehler verschluckt.
- [Lebenszyklus](../src/WordPress/Lifecycle.php): Voraussetzungen, rollenbezogene Einrichtung der bestehenden 18 Capabilities bei Aktivierung, datenerhaltende Deaktivierung und Deinstallation sowie lesende Systemstatusseite unter Werkzeuge.
- [Schemaerkennung](../src/Infrastructure/WordPress/SchemaInspector.php): neu, erkannter Bestand, unvollständig, älter und neuer werden unterschieden. Tabellenpräfix wird ermittelt. Erkennung prüft Tabellenanwesenheit und Versionsmarker, nicht Spaltenvollständigkeit oder Datenkonsistenz. Das bleibt Aufgabe der Migrationsvorprüfung.
- Der alte Seitenaufruf-Updater und destruktive Uninstaller entfallen. Die neun alten DDL-Anweisungen bleiben als nicht ausführbare [Quellreferenz](legacy-schema-reference.md) erhalten. Aktivierung legt keine Tabellen an und erhöht keinen Versionsmarker. Ein neuer Bestand kann aktiviert werden, bleibt bis zur Einrichtung aus Block 3 jedoch gesperrt. Nicht erkannte Bestände werden nicht automatisch repariert oder verändert. Der Release-Installer ist noch nicht geliefert.
- Fehlendes Autoload-Paket oder zu alte PHP-Version erhalten eine Administrationsmeldung. Die technischen Mindestvoraussetzungen dieser Entwicklungsbasis sind PHP 8.2, WordPress 6.3, mbstring und MariaDB 10.11 oder MySQL 8.0. Multisite ist bis zur gesonderten Umsetzung gesperrt. Bestandene Versionskombinationen stehen in verification.md; die Mindestwerte sind keine vollständige Supportfreigabe.
- PHP-Veraltungsstellen in `date_diff2` und numerischer Datumsdarstellung beseitigt. Format, Zeitzonenverhalten und bestehende Sprachpfadentscheidung bleiben erhalten. Plugin-Laden wird ohne Veraltungs-Ausnahmeliste geprüft.

## Qualitätsgrenzen

`composer check` prüft Metadaten, Syntax, Dokumentation, PHPStan Level 8 für `src`, PSR-12 mit WordPress-Ausgabe-/SQL-/Übersetzungsregeln für neue Klassen und den Uninstaller sowie einen ergänzenden PHPCompatibility-Lauf für das Fundament. Lange Übersetzungstexte sind von der PSR-Zeilenlängenregel ausgenommen. Kein pauschales PHPStan-Ignorieren und keine fingierte Gesamtbewertung des Altbestands.

PHPCompatibilityWP 2.1.8 verwendet den letzten stabilen PHPCompatibility-Stand 9.3.5. Dessen moderne PHP-8-Abdeckung ist begrenzt; ein grüner Lauf ersetzt weder PHPStan mit Syntaxziel 8.2 noch die strengen Laufzeittests auf 8.2/8.3/8.4/8.5. Ein Wechsel auf neuere stabile Regeln bleibt vorgemerkt; keine Alpha-Werkzeugversion ist Voraussetzung.

ESLint prüft alle eigenen `js/*.js` mit Recommended-Regeln und expliziten Browser-/jQuery-Globals. Unbenutzte Legacy-Bindungen werden beim jeweiligen Modulumbau bearbeitet; `no-unused-vars` ist für diese bestehenden Skripte ausgenommen. Bibliotheken und minifizierter Fremdcode werden nicht als eigene Quellen bewertet. Node-Tests prüfen weiter die vier Sicherheitsabläufe. Playwright-/Accessibility-Gesamtabnahme folgt mit den Bedienabläufen.

Werkzeuge und transitive Versionen sind gesperrt. `minimatch` ist wegen [GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249) vorübergehend auf 11.0.4 überschrieben; ESLint-Lauf und Node-Tests bestehen damit. Override entfernen, sobald die direkten Werkzeuge die korrigierte Linie selbst verwenden. npm audit und Composer-Audit wurden geprüft, Nachweise siehe verification.md.

## Paket

`composer build` erzeugt ein ZIP aus expliziten Runtime-Verzeichnissen und Dokumentation. Composer wird in einem getrennten Stagingverzeichnis mit `--no-dev --no-scripts --no-plugins` ausgeführt; das Entwicklungs-vendor bleibt erhalten. Das Paket enthält einen fertigen Loader, keine PHPUnit-/PHPStan-/Node-Werkzeuge, Tests, Git-Metadaten oder privaten Konfigurationen. Sortierte Einträge, feste ZIP-Zeitstempel und Rechte ermöglichen reproduzierbare Builds mit gleicher Werkzeugkette. `php tools/check-package.php` prüft Grenzen und lädt alle neuen Typen in einem gesonderten PHP-Prozess ohne Entwicklungsloader. Das ist keine Neuinstallations- oder Browserabnahme.

## Noch zugeordnet

- Block 3: exakte DDL, Feldvorprüfung, Erstinstallation, Versionsjournal, kontrollierte Migration, Wiederaufnahme und Archivsnapshot.
- Blöcke 4 bis 9: eigentliche Fachmodule, Repositories/Transaktionsgrenzen, moderne Admin- und Teilnehmeroberflächen, verbleibende Legacy-APIs und vollständige Entkopplung von Theme/Ticker.
- Block 10: fertiges ZIP auf frischem WordPress mit Standardtheme, Browser/Accessibility, fehlende optionale Plugins, MySQL und weitere erklärte WordPress-/DB-Versionen. Erst dann vollständige Installations-/Releasefreigabe.

## CI-Korrektur

Der [GitHub-Lauf 41537851183](https://github.com/Club-of-Roam/cor-mgmt/actions/runs/41537851183) scheiterte in allen vier PHP-Jobs ausschließlich beim WordPress-Medientest: Beim ersten Core-Start ohne vorhandene Optionen wurde `WP_CONTENT_URL` als relative Adresse initialisiert. Die isolierte Bootstrapdatei setzt jetzt ausdrücklich `https://example.invalid/wp-content`; ein Regressionstest verlangt absolute Upload-Adressen. Die Prüfung verwendet synthetische Datenbanken einschließlich frischer Erstläufe. Der Fehler war kein nachgewiesener Laufzeitfehler der Website. Der entfernte Lauf wurde nur gelesen, nicht neu gestartet; die Korrektur wird erst nach einer gesonderten Git-Veröffentlichung dort wirksam.
