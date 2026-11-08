# Masterplan cor-mgmt 2.0

Freigegebene Grundlage: 2026-11-01. Dieses Dokument verbindet Bestandsanalyse, Modernisierung, Dokumentation und Tests.

## Ziel und Grenzen

Funktionen, historische Daten, IDs und nötige Integrationen erhalten. Zielplattform ist die jeweils neueste stabile PHP-Version; PHP 8.2 bleibt vollständig unterstützt. Abläufe erhalten, Oberfläche schrittweise modernisieren. Zahlungswege separat fachlich prüfen. Tests ohne echten Versand und produktive Schnittstellen ausführen. 2.0.0 ist das Releaseziel, nicht der aktuelle Zustand.

### PHP-Kompatibilitätsvertrag (ergänzt 2026-11-02)

- Neueste stabile PHP-Version als Entwicklungs- und Releaseziel verwenden, keine Beta-/RC-Version als Pflichtplattform. Stand 2026-11-02: PHP 8.5, aktuelles Patchrelease 8.5.12 gemäß [PHP](https://www.php.net/).
- PHP 8.2 bleibt die Mindestversion mit vollständigem Funktionsumfang, einschließlich Installation, Upgrade, Migration und aller Fachmodule. Auch die stabilen Zwischenversionen werden unterstützt.
- Syntax, APIs und verpflichtende Laufzeitabhängigkeiten müssen unter PHP 8.2 funktionieren. Neuere PHP-Funktionen dürfen diesen Vertrag nicht einschränken; für notwendige Anpassungen kompatible Implementierungen verwenden.
- Verbindliche CI-Matrix: 8.2, 8.3, 8.4 und 8.5, jeweils aktueller Patchstand. Isolierte Fachreferenz und WordPress-Referenzintegration sind eingerichtet und auf diesen Versionen geprüft. Sicherheits- und Migrationstests ergänzen, sobald die betreffende Umsetzung bereitsteht. Installationspaket mindestens auf 8.2 und der neuesten stabilen Version vollständig abnehmen.
- Bei neuen stabilen PHP-Versionen Ziel und Matrix aktualisieren, ohne die Mindestversion 8.2 stillschweigend anzuheben. Entwicklungswerkzeuge dürfen versionsabhängig gewählt werden; das Releasepaket und seine Laufzeitabhängigkeiten müssen den gesamten unterstützten Bereich abdecken.

Dies ist der Zielvertrag, keine bereits erreichte Kompatibilitätsfreigabe. Vorhandene Prüfergebnisse und noch fehlende Testläufe stehen in [Prüfbelegen](verification.md).

Eigenständiges WordPress-Plugin als verbindliches Releasekriterium: Installation per vollständigem ZIP auf frischem WordPress mit Standardtheme, ohne andere Fachplugins oder Buildwerkzeuge. Einrichtung, Upgrade, Deaktivierung, Reaktivierung und datenerhaltende Deinstallation gehören dazu. Siehe [Installationsvertrag](installation.md).

## Referenzabschluss

Block 0 ist am 2026-11-02 als Analyse-/Referenz-/Testgrundlagenpaket abgeschlossen. Belege und klare Grenzen: [block-0-acceptance.md](block-0-acceptance.md). Sämtliche Bereiche besitzen nachvollziehbare Referenzszenarien; isolierte und echte WordPress-Integration sind auf PHP 8.2/8.3/8.4/8.5 geprüft. Keine Sicherheits- oder Releasefreigabe des Altbestands. Die Definition of Done unten betrifft die fertige Version 2, nicht den Referenzabschluss.

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
| 8 Kommunikation/Börse | Autoresponses je Event/Aktion/Sprache, Empfänger, kontrollierter Versand und eigene Nachrichten | Parallele Events strikt getrennt; Rechte, Sprache und Empfänger stimmen; keine unbeabsichtigten Mehrfachsendungen |
| 9 Ticker/Medien | Texte, Kommentare, Bilder, Videos, Karten, authentifizierte Eingänge und cor-ticker-Adapter | Historische/neue Inhalte; sichere Uploads/Eingänge |
| 10 Gesamtabnahme | Eigenständiges ZIP, Standardtheme ohne Zusatzplugins, optionale Integrationen, Übersetzungen, Statistiken, Bedienung, Leistung und Release | Release-Kandidat mit geprüfter Wiederherstellung |

Testgrundlage und CI beginnen in Block 0; nötige Infrastruktur aus Block 2 wird für Sicherheitskorrekturen vorgezogen. Fachmodule benötigen die geprüfte Migrationsbasis vor ihrer Datenumstellung. Alte Handler bleiben über Adapter erreichbar. Kein konkurrierender Schreibbetrieb zweier Plugin-Versionen.

## Daten und Schnittstellen

Für die Phasenabstimmung ersetzt die Liste der acht Einstellungsgruppen in [Fachregeln](business-rules.md) den zu pauschalen Begriff »Rennregeln ändern«: Grundstruktur/Kapazitäten, Teilnahmevoraussetzungen, Darstellung, Organisation/Ablauf, Funktionsschalter, Wertung, Teilnehmerbestätigungen und berechneter Teamstatus. Die korrigierte [Phasenmatrix](phases.md) ist als Zielvorgabe übernommen. Teamgröße/Teilnahmevoraussetzungen in Phase 0 bearbeiten; Routenkapazität/Startpunktmodus auch in Phase 1. Darstellung und redaktionelle Texte ab Start sperren, organisatorische Hinweise weiter aktualisieren. Ergänzungen: Rückwechsel 0..3 stellen die Rechte der Zielphase wieder her; Zusatzantworten vorläufig alle änderbar, Admin-Datumskorrekturen auch nach Start erlaubt, Auswirkungen später prüfen. Teilnehmer-Adminausnahmen sind von Eventeinstellungen getrennt. Kapazitäts-/Startpunktkonflikte warnen, erlaubte Änderungen ermöglichen.

Fachentscheidungen vom 2026-11-02 sind verbindlich: Einstellungen gemäß Phasenmatrix getrennt führen; Teilnehmerbestätigungen und berechneten Teamstatus davon unterscheiden. In Phase 3 sind freigaberelevante Vorgaben und Teilnehmerbestätigungen gesperrt, automatische Rückstufung ausgeschlossen; ein expliziter Rückwechsel richtet sich wieder nach der Zielphase. Einladungen reservieren keine Plätze und sind fachlich nicht in ihrer Anzahl begrenzt, freie Kapazität bei Annahme atomar prüfen; unvollständige Teams belegen keinen Routenplatz; historische Ergebnisse auch bei erkannten Rechenfehlern unverändert erhalten. Details und noch offene Übergangs-/Historiengrenzen: [Fachregeln](business-rules.md), [Entscheidungen](decisions.md).

Einladungsplätze sind bestätigte Mitgliedsplätze innerhalb eines Teams, getrennt von Routenplätzen. Versand und Annahme sind nur bei offener Anmeldung erlaubt. Einladungen gelten bis Anmeldeschluss. Noch offene Sonderfälle und Detaildefinitionen stehen in den Fachregeln und der Phasenmatrix.

Neue fünfte Phase »Rennen beendet« friert den rennbezogenen Stand ein: keine Änderungen oder Neuberechnungen, auch keine Teilnehmer-Adminausnahmen. Alle Bestandsrennen mit Ausgangsstatus »Rennen gestartet« bei der Migrations-Vorprüfung gehen beim Umstieg ohne Neuberechnung direkt ins Archiv; Zielmenge journalisieren, Werte/Rangfolge erhalten. Neue Rennen beim Abschluss ebenso einfrieren. Snapshotumfang und Migrationsnachweise in Block 3/6/9 umsetzen.

InnoDB/utf8mb4, geeignete Indizes, normalisierte Etappenergebnisse, sichere Einladungen und explizite Statuswerte. Geld als Cent plus Währung. Historisch unklare Werte nicht raten. Konten, Passwörter, IDs, Medien und Orphans erhalten. Alte Tabellen bleiben während des Übergangs. Bestehende Shortcodes/Attribute erhalten; neue REST-Endpunkte unter cor-mgmt/v2 mit expliziten Rechten. Siehe [Migration](migration.md), [Architektur](architecture.md).

## Definition of Done

Installationsvertrag einschließlich Eigenständigkeit bestanden, Fach- und Fehlerfälle bestehen, Migrationen geprüft, Prüfungen und CI für PHP 8.2 bis zur neuesten stabilen Version erfolgreich, Dokumentation/Arbeitsstatus aktuell. Bugfixes erhalten Regressionstests. Coverage-Zahl ersetzt keine fachliche Abdeckung. Offene Kriterien verhindern einen Abschlussstatus.

Vor Release frische Produktionskopie vollständig prüfen, kontrolliertes Wartungsfenster und finale Sicherung vorsehen. Rückweg muss neue produktive Schreibvorgänge berücksichtigen. Details: [Tests](testing.md), [Release](release.md), [Status](status.md).

## Abschluss Sicherheitsbasis

Block 1 ist am 2026-11-02 mit negativen und positiven WordPress-Sicherheitstests sowie JavaScript-Regressionen abgeschlossen. Umfang und geänderte Integrationsverträge in [block-1-security.md](block-1-security.md); ausgeführte Matrix und Grenzen in [verification.md](verification.md). Keine Schemaumstellung oder Releasefreigabe. Fortsetzung mit Block 2.

## Umsetzung Teams/Profile

Das Fachpaket aus Block 5 ist im Kompatibilitätsbetrieb implementiert: gemeinsame transaktionale Teilnahme-/Profil-/Adminwriter, phasenabhängige Rechte, sichere Codes mit Legacy-Übergang und eigener Avataradapter. Additive Einrichtung und ausdrücklich verbleibende Abnahmen stehen in [Block 5](block-5-teams.md). Versionsmarker und aktive Datenhaltung werden nicht vorzeitig umgeschaltet. Die neue versionierte Ergebnislogik folgt in [Block 6](block-6-results.md); offene fachliche/visuelle Abnahmen bleiben ausgewiesen.


## Umsetzung Ergebnisse

Block 6 liefert normalisierte Ergebnisse für ausdrücklich eingerichtete Wertungen, native Formulare, neue Ranglisten und einen nur lesenden historischen Vergleich. Historische Ergebnisse bleiben unverändert. Vollständiger Archivsnapshot und Bestands-/Browserabnahme bleiben offen; Umfang und Einrichtung in [Block 6](block-6-results.md). Betterplace ist als aktiver Zahlungsweg bestätigt; Umsetzung und offene Freigaben in Block 7.

## Ergänzung Block 7 (2026-11-05)

[Betterplace und Spenden](block-7-donations.md) dokumentiert den bestätigten Ablauf, das additive Modul 1.0.0, den Schutz historischer Daten und die noch offene Automatisierungs-/Anbieter-/Browserabnahme. Neue Beträge werden in EUR-Cent geführt; keine geratene Bestandskonvertierung. Ausgeführte Prüfungen: [verification.md](verification.md).

## Ergänzung Block 8: Autoresponses je Event

D-44 verlangt unabhängig anlegbare und einstellbare Vorlagen pro Veranstaltung. Keine globale Auswahl über das aktive Rennen; Vorlage, Empfänger und Versandwarteschlange bleiben an die Veranstaltung des Auslösers gebunden. Explizite datenerhaltende Übernahme globaler Vorlagen und Tests mit zwei parallelen Events gehören zum Block. [Umsetzungsplan](block-8-communication-plan.md). Erstes Vorlagen-Teilpaket seit 2026-11-08 umgesetzt; [Umfang und Grenze](block-8-templates.md). Versandumschaltung, Queue und Börse bleiben offen.
