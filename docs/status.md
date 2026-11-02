# Arbeitsstatus und Übergabe

Stand 2026-11-02. Ziel 2.0.0; laufender Code 1.2.10 / Schema 2.8.

PHP-Ziel: jeweils neueste stabile Version bei vollständiger Unterstützung ab 8.2. Matrix 8.2/8.3/8.4/8.5 in CI eingerichtet und mit isolierter sowie echter WordPress-Referenzsuite ausgeführt; Details in verification.md. Kein Remote-GitHub-Lauf.


## Verbindliche Ergänzung: Eigenständigkeit

Installationsvertrag in installation.md festgehalten. Blöcke 2/3 liefern Voraussetzungenprüfung, Einrichtung und Lebenszyklus; Block 9 beseitigt die zwingende Ticker-Abhängigkeit; Block 10 prüft das fertige ZIP ohne Spezialtheme/Zusatzplugins. Diese Anforderungen sind dokumentiert, noch nicht implementiert oder abgenommen.

## Historischer Ausgangspunkt der Startphase

Masterplan, Analyse, Referenzszenarien, Entscheidungen, Architektur, Migration, Test- und Releasekonzept im Repository dokumentiert. Statisches Schnittstelleninventar und erste isolierte Tests mit CI-Matrix vorbereitet. Anwendungscode, Versionsheader, DB unverändert.

| Block | Status | Nächste Arbeit |
|---|---|---|
| 0 | abgeschlossen: Referenz und Testgrundlage | Übergabe gemäß block-0-acceptance.md; keine Modul-/Releasefreigabe |
| 1 | abgeschlossen: Sicherheitsbasis | Sicherheitsbasis und Integrationsverträge gemäß block-1-security.md |
| 2 | begonnen: Testgrundlage | Werkzeuge ausbauen, Autoloading/Schichten einführen; keine Runtime-Umstellung erfolgt |
| 3 | offen | Exakte DDL, Feldmapping und Migrationswerkzeug |
| 4–6 | offen | Rennen/Routen, Teams/Profile, Ergebnisse |
| 7 | offen | Fachliche Zahlungsprüfung zuerst |
| 8–9 | offen | Kommunikation/Börse, Ticker/Medien |
| 10 | offen | Gesamtabnahme und geprüfter Produktionswechsel |

Prüfergebnisse: [verification.md](verification.md). Workflow-Konfiguration ist kein belegter GitHub-Lauf. WordPress-Referenzintegration ausgeführt; vollständige Modul-, Migrations- und Browserabnahme nicht erfolgt.

## Nächste Bearbeitung

1. AGENTS.md und vorhandenen Fortschritt prüfen; Git-Aktionen nur mit ausdrücklicher Freigabe.
2. composer install, composer check, composer test mit PHP >= 8.2.
3. Nach Block 1 mit Block 2 fortfahren: Namespaces/Autoloading, Schichten, Werkzeugprüfungen und Plugin-Lebenszyklus einschließlich Installationsvoraussetzungen. Nur synthetische Testdaten verwenden.
4. Befunde in analysis.md priorisieren; Altfehler von erhaltenswerter Funktion trennen.
5. Nach jedem Paket Status, Nachweise und Entscheidungen aktualisieren. Kein vorzeitiger Versionssprung.

## Frühere Zwischenstände: Fachregeln und Charakterisierung

Die korrigierte Phasenmatrix und Nachträge des Benutzers vom 2026-11-02 sind in [phases.md](phases.md) übernommen (D-17/18). Fünf Zielphasen: Vor Anmeldung, Anmeldung offen, Anmeldung geschlossen, Rennen gestartet, Rennen beendet/Archiv.

Die groben offenen Fachfragen sind beantwortet: Rückwechsel 0..3 stellt Rechte der Zielphase wieder her; Teilnehmer-Adminausnahmen getrennt von gesperrten Eventeinstellungen; Warnung bei Kapazitäts-/Startpunktkonflikten, Änderung bleibt möglich ohne automatische Löschung/Umverteilung; Zusatzantworten vorläufig alle bearbeitbar; Admin-Datumskorrektur auch nach Start erlaubt. Spätere Prüfung der Antwortkategorien und Datumsfolgen ist ausdrücklich vorgemerkt.

Alle Bestandsrennen mit Ausgangsstatus 3 bei der Migrations-Vorprüfung werden ohne Neuberechnung direkt in die neue Archivphase überführt. Rennen und historische Ergebnisse sind darin eingefroren; auch Admin-Ausnahmen dürfen das Archiv nicht verändern. Migration noch nicht implementiert/ausgeführt. Archivsnapshot, konkrete Teilnehmer-Adminrechte und Sonderfälle bei Wechsel in ein anderes Rennen bei der jeweiligen Umsetzung ausarbeiten.

Zum damaligen Zwischenstand blieb Block 0 wegen ausstehender Analyse-/Testarbeit begonnen; der Abschlussstand unten ersetzt dies. Die Charakterisierungstests beschreiben weiterhin den Altbestand. Frühere Phasen-/Archivpakete änderten nur Dokumentation; keine Git-Aktionen. Nachweise in verification.md.

## Paket: Ergebnisse und Ranglisten (2026-11-02)

22 zusätzliche isolierte Fälle in RankingSubmissionTest/RankingDisplayTest. Zusatzpunktefehler nun reproduziert, einschließlich TypeError und bereits ausgeführtem Schreibaufruf für ein früheres Team. Ranggleichheit, Phasenanzeige und fehlerhafte Top-/999-Punkte-Grenzen charakterisiert. [Ergebnisreferenz](ranking-reference.md) trennt Befunde von Zielregeln und Archivschutz. Anwendungscode und Anwendungsdaten unverändert.

Gesamtstand: 71 Tests, 244 Assertions unter PHP 8.2.35 und 8.4.27 erfolgreich; Composer-Prüfung, Syntax und Dokumentations-/Inventarprüfung bestanden. PHP 8.3/8.5, WordPress-/DB-/Browser-/Migrationstests weiterhin nicht ausgeführt. Keine Git-Aktionen. Nächste Arbeit: übrige Referenzbereiche weiter charakterisieren und WordPress-Integration vorbereiten; keine vollständige Abnahme von Block 0.


## Block 0 abgeschlossen – 2026-11-02

Dieser Abschnitt ersetzt die oben datierten Zwischenstände »begonnen/offen«. Vollständiger Referenzkatalog für sämtliche Fachbereiche, Rollen-/Hook-/Schnittstellenzuordnung und historisch relevante Sonderfälle angelegt. 84 isolierte Tests / 309 Assertions und 33 echte WordPress-Integrationstests / 298 Assertions mit WordPress 7.1.4 und MariaDB 11.4.13; beide Suiten auf PHP 8.2.35/8.3.36/8.4.27/8.5.12 ausgeführt. CI um dieselbe Matrix und Integration erweitert, kein Remote-Lauf.

Zusätzliche reproduzierte Befunde: Börsen-Update anhand fremder ID, Profilspeicherzugriff auf Abschnitt ohne id, Selbstmail an false statt Adresse, Spendenaggregation abweichend von gefilterter Liste/fehlender race-Zähler, Legacy-Freigaberückstufung trotz Status 3. Diese Fehler sind noch nicht korrigiert. Referenzfixtures sind keine bestandene Neuinstallation oder v2-Migration.

Bewusst vertagte Antwortklassifikation/Datumsauswirkungen, konkrete Adminrechte und Archivsnapshot sind vorgemerkt. Umfang und zugeordnete Restarbeiten: [block-0-acceptance.md](block-0-acceptance.md). Anwendungscode unverändert; keine bestehenden Anwendungsdaten verändert, keine Git-Aktionen. Nächster Block: Sicherheitsbasis.

## Block 1 – Sicherheitsbasis (2026-11-02)

Anwendungscode erstmals geändert: zentrale Request-/Objektprüfungen, geschützte Formulare, Börseneigentum, sichere Bildannahme, signierter Gateway-Eingang, kontrollierte Wartung und korrigierte Profil-/Mailgrenzen. Die beiden ausgelagerten Uploadskripte wurden in der Arbeitskopie durch HTTP-410-Antworten ersetzt. Separate Kompatibilität mit overlayer_maker dokumentiert; Theme und cor-ticker unverändert. Keine Datenmigration, keine Bestandsneuberechnung, kein Versionssprung, keine Git-Aktionen.

Umfang, geänderte Verträge und zugeordnete Restarbeit: [block-1-security.md](block-1-security.md). Vollständige Phasen-/Fachmodul-, Installations-, Browser- und Releaseabnahme bleiben in den Folgeblöcken. Alte Gateway-Absender müssen vor Ausrollen die Signatur liefern; ungesicherte Browser-Zahlungsbestätigung ist gesperrt. Abschlusszähler und Prüfgrenzen: [verification.md](verification.md).

Abschlussprüfung Block 1 bestanden: 180 PHP-Tests / 965 Assertions pro PHP-Version 8.2/8.3/8.4/8.5 sowie 4 JavaScript-Tests. Composer-, Syntax- und Dokumentationsprüfungen erfolgreich. Nächster Arbeitsblock: 2. Ein geeigneter manueller Commit-Zeitpunkt ist erreicht; keine Git-Aktionen ausgeführt.
