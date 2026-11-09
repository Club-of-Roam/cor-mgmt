# Arbeitsstatus und Übergabe

Stand 2026-11-09. Ziel 2.0.0; laufender Code 1.2.10 / Schema 2.8.

PHP-Ziel: jeweils neueste stabile Version bei vollständiger Unterstützung ab 8.2. Matrix 8.2/8.3/8.4/8.5 in CI eingerichtet und mit isolierter sowie echter WordPress-Referenzsuite ausgeführt; Details in verification.md. Der fehlgeschlagene GitHub-Lauf wurde analysiert; für die Korrektur gibt es noch keinen entfernten Wiederholungslauf.


## Verbindliche Ergänzung: Eigenständigkeit

Installationsvertrag in installation.md festgehalten. Blöcke 2/3 liefern Voraussetzungenprüfung, Einrichtung und Lebenszyklus; Block 9 beseitigt die zwingende Ticker-Abhängigkeit; Block 10 prüft das fertige ZIP ohne Spezialtheme/Zusatzplugins. Voraussetzungen, Schemaerkennung, datenerhaltender Lebenszyklus und ZIP-Bau sind in Block 2 umgesetzt; Einrichtung/Migration und vollständige Eigenständigkeit bleiben offen.

## Historischer Ausgangspunkt der Startphase

Masterplan, Analyse, Referenzszenarien, Entscheidungen, Architektur, Migration, Test- und Releasekonzept im Repository dokumentiert. Statisches Schnittstelleninventar und erste isolierte Tests mit CI-Matrix vorbereitet. Anwendungscode, Versionsheader, DB unverändert.

| Block | Status | Nächste Arbeit |
|---|---|---|
| 0 | abgeschlossen: Referenz und Testgrundlage | Übergabe gemäß block-0-acceptance.md; keine Modul-/Releasefreigabe |
| 1 | abgeschlossen: Sicherheitsbasis | Sicherheitsbasis und Integrationsverträge gemäß block-1-security.md |
| 2 | technische Laufzeitbasis umgesetzt | Autoloading, Adapter, Lebenszyklus, Qualitätsprüfungen und Paketbau gemäß block-2-foundation.md; Releaseabnahme separat |
| 3 | Installation und kontrollierte Zwischenmigration umgesetzt | Fachliche Umschaltung, kompletter Archivsnapshot und abschließende Modul-/Releaseabnahme noch offen; block-3-migration.md |
| 4 | Verwaltungsumbau im Kompatibilitätsbetrieb umgesetzt | block-4-events.md; Teilnehmer-Kapazitätsvertrag, aktive Migration und vollständiges Archiv mit Folgeblöcken abnehmen |
| 5 | Fachumbau serverseitig geprüft | block-5-teams.md; Browser-/Medienabnahme noch offen |
| 6 | neue Wertung und Historienvergleich umgesetzt; Abnahme offen | block-6-results.md; vollständiges Archiv, Bestandsvergleich und Browserabnahme bleiben offen |
| 7 | Betterplace-Grundlage umgesetzt; Freigabe offen | block-7-donations.md; Automatisierung, Anbieter-/Bestands-/Browserabnahme und nachgewiesene Altübernahme offen |
| 8 | Kommunikation, Queue, Rundmails und Börse technisch umgesetzt | block-8-completion.md; Inhalts-/Bestands-/Anbieter- und Aufbewahrungsfreigaben bleiben zur Gesamtabnahme offen |
| 9 | Webticker und Medien technisch integriert | block-9-ticker.md; eigenständige Einrichtung, kontrollierte Altübernahme, Moderation und stillgelegte SMS-Eingänge; Bestands-/Gesamtabnahme in Block 10 |
| 10 | offen | Gesamtabnahme und geprüfter Produktionswechsel |

Prüfergebnisse: [verification.md](verification.md). Workflow-Konfiguration ist kein belegter GitHub-Lauf. WordPress-Referenzintegration ausgeführt; vollständige Modul-, Migrations- und Browserabnahme nicht erfolgt.

## Nächste Bearbeitung

1. AGENTS.md und vorhandenen Fortschritt prüfen; Git-Aktionen nur mit ausdrücklicher Freigabe.
2. composer install, composer check, composer test mit PHP >= 8.2.
3. Block 10 vorbereiten: Block-3-Grenzen, vollständigen Archivsnapshot, Bestandsmigrationen und alle Modulabnahmen zusammenführen. Kontrollierte Tickerübernahme und gesonderte Stilllegung alter direkter Eingänge gemäß block-9-ticker.md berücksichtigen. Keine Kopie automatisch aktivieren; echte Zustellung und Zahlungsautomatisierung bleiben gesondert freizugeben.
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

## Block 2 – technische Laufzeitbasis (2026-11-03)

Umfang und zugeordnete Restarbeiten: [block-2-foundation.md](block-2-foundation.md). Namespaces und Composer-Lader sind aktiv. Der Altbestand bleibt funktional hinter einem Adapter. Mail/HTTP/Uhrzeit/Zufall sind austauschbar; PHPStan, PSR-/WordPress-Regeln, PHPCompatibility-Ergänzung und ESLint sind gesperrt eingerichtet. Aktivierung liest das Schema und richtet die bestehenden Capabilities ein; sie migriert oder installiert noch keine Tabellen. Deinstallation löscht keine fachlichen Daten. ZIP enthält einen fertigen Produktionsloader ohne Entwicklungswerkzeuge.

Der GitHub-Lauf 41537851183 wurde nur gelesen: frischer WordPress-Teststart ohne absolute Upload-URL war die Ursache. Bootstrap und Regressionstest sind korrigiert. Kein entfernter Wiederholungslauf und keine Git-Aktionen. Vollständige Installations-/Migrations- und Browserabnahme bleibt bei Block 3/10. Aktuelle Prüfbelege in verification.md ersetzen frühere Werkzeug-/Laufangaben.

Abschlussbeleg für die technische Basis: 209 PHP-Tests / 1.071 Assertions je PHP-Version 8.2/8.3/8.4/8.5, 4 Node-Tests, eingeführte statische Prüfungen und Paketgrenzen erfolgreich geprüft. Keine vollständige Installations- oder Releasefreigabe; Details in verification.md. Der Stand ist für einen manuellen Commit geeignet. Nächster fachlicher Arbeitsblock: 3.

## Block 3 – Installation und Zwischenmigration (2026-11-03)

Die [Umsetzung](block-3-migration.md) liefert wiederholbare Erstinstallation, strukturelle Vorprüfung, schreibfreien Probelauf, geschützte Administrationsaktionen, begrenzte Migrationsbatches, Lock/Journal, Wiederaufnahme und Vergleiche. Neue Speicherstruktur 3.0.0 unabhängig von Plugin 1.2.10 und Legacy-Marker 2.8. Rohwerte, unbekannte Felder und Orphans bleiben erhalten; historische Punkte werden nicht berechnet. Phasenmapping 3 nach 4 nur in der getrennten Kopie vorbereitet. Geldbeträge bleiben ungeklärt statt geraten.

Keine Anwendungskopie wurde migriert oder umgeschaltet. Vollständige neue Fachrepositories, Benutzer-/Medien-/Ticker-Archivsnapshot, Betragseinheiten und Releaseabnahme bleiben offen. Die teilweise offenen Fachentscheidungen haben die technische Basis nicht blockiert und wurden nicht eigenmächtig ersetzt. Prüfbelege in verification.md. Keine Git-Aktionen.

Prüfabschluss Block-3-Paket: 267 PHP-Tests / 1.261 Assertions auf jeder Version 8.2/8.3/8.4/8.5 und 7 Node-Tests bestanden. Die zusätzliche ID-Sortierregression ist korrigiert und durch erneute Vollsuiten abgesichert. Neue Strukturen und kontrollierte Kopierwerkzeuge sind implementiert; aktive Fachmigration und gesamter Block 3 bleiben bis zur beschriebenen Modul-/Archivumschaltung offen. Keine vorhandenen Anwendungsdaten migriert.

## Block 4 – Rennen- und Routenverwaltung (2026-11-04)

[Umsetzung und Grenzen](block-4-events.md): neue Domainregeln, Anwendungsfälle und Kompatibilitätsrepository ersetzen die bisherigen Verwaltungswriter. Fünf Phasen, gruppierte Feldsperren, Phase-0-Bearbeitung der Teilnahmevoraussetzungen, Revisionen, Kapazitäts-/Startpunktwarnungen, Eltern-/Löschschutz und atomare aktive Rennwahl implementiert. Neue Tabellenansichten und native Formulare; historische Zusatzfelder und exakte unveränderte Zeitstempel bleiben erhalten. Platzanzeige zählt nur vollständige Teams.

Keine Anwendungsmigration und keine aktive Generation ausgewählt. Rennen abschließen bleibt bis zum vollständigen Archivsnapshot gesperrt. Teilnehmerwriter/Routenfreigabe und ihre gemeinsame Nebenläufigkeit gehören zu Block 5; komplette Historie zu Block 3/6/7/9. MyISAM-Mehrfachaktionen benötigen zuerst kontrollierte Engine-Prüfung. Browser-/Releaseabnahme bleibt offen. Ausgeführte Prüfungen in verification.md. Keine Git-Aktionen.

Prüfabschluss des Verwaltungspakets: Vollsuiten mit 370 Tests / 1.492 Assertions je PHP-Version 8.2/8.3/8.4/8.5 bestanden; betroffene Suite nach Etappenfilter-Ergänzung mit 42 Tests / 172 Assertions auf allen vier Versionen wiederholt, zusätzliche Kontoauswahlregression ebenfalls bestanden. Composer-Prüfungen, 7 Node-Tests und ESLint erfolgreich. Paketbau und Produktionsloader ohne Entwicklungsabhängigkeiten geprüft. Nachweise und Grenzen in verification.md. Geeigneter manueller Commit-Zeitpunkt; keine automatischen Commits oder Pushes.

## Block 5 – Teams, Einladungen und Profile (2026-11-04)

[Umsetzung und Grenzen](block-5-teams.md): TeamPolicy/TeamManager und transaktionales Kompatibilitätsrepository ersetzen die bisherigen Team-/Teilnehmerwriter. Anmeldung, unbegrenzte Einladungen ohne Reservierung, einmaliger Verbrauch, Widerruf, Altcode-Übergang, Mitgliedschaften, Freigabe, Routenwahl und Profilpflege folgen der Phasenmatrix. Teamstatus und Routenbelegung werden bei relevanten Änderungen ausdrücklich abgeglichen; reine Anzeigen schreiben und senden nichts. Letzte Plätze sind mit parallelen Prozessen geprüft.

Native Team-/Profilformulare, eigene Avatarannahme und paginierte, sortierbare sowie filterbare Verwaltungslisten eingeführt. Objektberechtigte Einzel-/Sammelbestätigungen, unveränderte Bestätigungs-IDs und gesonderte Mitgliedschaftshistorie erhalten den Teilnahmeablauf. Additives Teilnahme-Modul 1.0.0 wird ausdrücklich oder bei Aktivierung eingerichtet, ohne automatische Engine-Änderungen oder Datenumschaltung.

Prüfabschluss: 466 Tests / 1.963 Assertions je PHP-Version 8.2/8.3/8.4/8.5; ergänzter Formularfall danach auf allen vier Versionen erneut bestanden. Composer-Qualitätsprüfungen, 7 Node-Tests, ESLint und Produktionspaket mit 236 Dateien geprüft; Belege in verification.md. Browser-/Tastatur-/Accessibility- und positiver multipart-Nachweis stehen noch aus. Gesamter Archivsnapshot, Ergebnisnormalisierung, Zahlungsverfahren/-einheiten und Versandqueue bleiben in den zugeordneten Folgeblöcken. Keine historischen Ergebnisse geändert, keine Anwendungsmigration und keine Git-Aktionen. Nächster fachlicher Umbau: Block 6.


## Block 6 – Ergebnisse und Wertung (2026-11-05)

[Umsetzung und Grenzen](block-6-results.md): reine versionierte Punkteberechnung, normalisierte Team-/Etappenergebnisse, transaktionale Mehrteam-Eingabe mit gemeinsamer Sperre, Revision und Änderungsjournal. Die bestehenden Ergebnis-Shortcodes delegieren an native Formulare; Ranglisten unterstützen Gesamt-/Routenränge, Gleichstände, exakte Begrenzung und dynamische Etappen. Fehlerhafte variable Summen betreffen die neue Berechnung; historische Summen und die bisherige historische Darstellung bleiben unverändert.

Zusätzliches Ergebnis-Modul 1.0.0 mit eigenem Installationsmanifest; bestehende Storage-/Migrationspläne bleiben unverändert. Wertung 2.0 wird ausdrücklich pro Rennen in Phase 0 eingeschaltet und bei vorhandenen historischen Punkten/Flags abgewiesen. Ohne Wertungsprofil bleiben Ergebniswriter gesperrt; das ist kein stiller Wechsel der Historie. Ein nur lesender Vergleich meldet hypothetische Summenabweichungen und unklare Etappenzuordnungen.

Keine Anwendungskopie migriert, keine Wertung aktiviert, keine vorhandenen Ergebnisse neu berechnet. Vollständiger Archivsnapshot, fachlicher Bestandsabgleich und Browser-/Tastaturabnahme bleiben offen. Zahlungswege weiterhin erst nach fachlicher Prüfung in Block 7. Prüfbelege in verification.md. Keine Git-Aktionen.

Der erlaubte Phasenrückwechsel erhält auch bei vorhandenen Ergebnissen Freigaberückstufung und Freigabe von Routenplätzen. Die bisherigen Ergebniszeilen bleiben erhalten; dieselbe Route kann wieder zugewiesen werden. Gespeicherte Null-Punkte-Ergebnisse schützen ihr Team vor Löschung. Die abschließend betroffenen Suiten sind auf allen vier PHP-Versionen erfolgreich geprüft und gesondert in verification.md ausgewiesen.

Fachbestätigung 2026-11-05 (D-38): Zusatzpunkte bei Platz 0/99 bleiben möglich; variable Zusatzpunkte bleiben bei aktiviertem Schalter ausschließlich auf Etappe 6. Beide zuvor vorläufigen Regeln sind damit verbindlich bestätigt. Kein Anwendungscode geändert; bestehende Tests decken dieses Verhalten bereits ab. Bestands-/Archiv- und Browserabnahme bleiben offen.

## Block 7 – Betterplace (2026-11-05)

Fachentscheidung: ausschließlich Betterplace für neue Spenden, vorhandenen Zuordnungsablauf erhalten und Automatisierung prüfen (D-39/40). Umsetzung: exakte neue Centbeträge, injizierter begrenzter Kommentaradapter, transaktionale einmalige Bestätigung, Journal, native Formulare, Sitzungs-/Nonce-Bindung und additives Installationsmodul. Historische Datensätze und Einheiten werden nicht konvertiert. Alte Browserbestätigung bleibt gesperrt. Umfang und offene Abnahmen in [Block 7](block-7-donations.md). Keine Anbieter-/Releasefreigabe, Anwendungsmigration oder Git-Aktion.

Prüfabschluss dieses Pakets: je 295 isolierte und 297 WordPress-Tests auf PHP 8.2/8.3/8.4/8.5 bestanden; zuletzt betroffene Spendenfälle danach erneut auf allen vier Versionen geprüft. Composer-Prüfungen, 7 Node-Tests und ESLint bestanden. Ein geeigneter Zwischenstand für einen manuellen Commit; Block 7 bleibt bis zu den ausgewiesenen Abnahmen offen.

## Block 7 – Verwaltungs- und Anzeigepaket

[Umsetzung](block-7-administration.md): geschützte Metadaten-/Sichtbarkeitsverwaltung, atomare Sammelaktionen, Revisionen und Journal. Kontrollierte Vorbereitung offener Altvorgänge ohne finanzielle/zeitliche Umdeutung. Exakte interne Summen, öffentlich ganze Euro gemäß Benutzerkorrektur; öffentliche Zähler schreiben keine Optionen. Anbieter-/Bestands-/Browserabnahme und belegte Altübernahme bleiben offen. Keine Anwendungsmigration oder Git-Aktionen.

Prüfabschluss des Verwaltungs-/Anzeigepakets: PHP 8.2/8.3/8.4/8.5 geprüft, abschließend sämtliche 30 Spendenfälle mit je 185 Assertions auf allen Versionen bestanden. Composer-Prüfungen, 7 Node-Tests, ESLint und bytegleiche Paketbuilds mit Produktionsloader geprüft. Details und zeitliche Grenzen der Gesamtsuiten in verification.md. Geeigneter manueller Commit-Zwischenstand; englische Message direkt mitliefern.

## Block 8 – Planergänzung Autoresponses (2026-11-05)

Benutzeranforderung D-44 aufgenommen: Vorlagen und Aktivierung je Event/Aktion/Sprache unabhängig verwalten. Bestätigter Quellbefund: Bestand speichert und lädt nur Aktion/Sprache, ohne Eventzuordnung. [Plan](block-8-communication-plan.md) enthält Eventkontext, unabhängiges Kopieren, ausdrückliche Übernahme globaler Vorlagen, Versandbindung und Parallel-Event-Abnahmefälle. Nur Dokumentation geändert; kein Anwendungscode, Schema oder Versand geändert. Keine Laufzeittests für diese Planergänzung ausgeführt.

## Block 8 – Vorlagenbasis (2026-11-08)

[Teilpaket](block-8-templates.md): Bestandsaufnahme, additive Einrichtung, unabhängige Event-/Aktions-/Sprachvorlagen, native Verwaltungsseite, Revisionen/Journal und kontrollierte globale bzw. Event-Kopie. Globaler Mailer bleibt unverändert; UI kennzeichnet die neue Konfiguration als Vorbereitung ohne Versandwirkung. Nächster Schritt: dauerhafte Fachereignisse, atomare Outbox-/Queue-Erzeugung und explizite Eventbindung der Auslöser. Erst danach Versandumschaltung und Worker, anschließend Börse. Kein vollständiger Block-8-Abschluss oder Releasekandidat. Keine Anwendungsmigration, kein Versand und keine Git-Aktionen.

Prüfabschluss des ersten Block-8-Teilpakets: isolierte Suite und echte WordPress-Gesamtsuite unter PHP 8.2/8.3/8.4/8.5 erfolgreich; abschließende 18 Konfigurationsfälle nach Setup-Hinweis erneut auf allen Versionen bestanden. Qualitätsprüfungen, 7 Node-Tests, ESLint und Produktionspaket geprüft; Details in verification.md. Geeigneter manueller Commit-Zwischenstand. Versandumschaltung und Queue sind ausdrücklich die nächste Arbeit.


## Block 8 – Queue-Grundlage (2026-11-08)

[Zweites Teilpaket](block-8-queue.md): additives Queue-Modul mit eigener Installation, unveränderlichen Nachrichtensnapshots und Wiederholungsschlüssel je Event/Vorgang/Empfänger. Einreihen verlangt die gemeinsame Fachtransaktion. Worker mit atomarem Anspruch, expliziter Freigabe, höchstens drei Versuchen und getrenntem unklarem Transportausgang. Kein Mailaufruf unter Sperre oder offener Transaktion; Archive blockieren neue Versuche. Keine Registrierung oder automatische Verarbeitung.

Nächster Arbeitsauftrag bleibt die atomare Anbindung der tatsächlichen Teamereignisse und übrigen Mailauslöser einschließlich Empfänger-/Sprach-/Linkauflösung. Danach geschützte Queueverwaltung, ausdrückliche Versandumschaltung, manueller Versand und Börse. Bestehender Mailer bleibt bis dahin unverändert; neue Eventvorlagen steuern ihn weiterhin nicht. Block 8 bleibt offen. Keine Anwendungsmigration, kein echter Versand, keine Git-Aktionen.


Prüfabschluss der Queue-Grundlage: isolierte Suiten mit je 319 Tests auf PHP 8.2/8.3/8.4/8.5 bestanden. WordPress-Gesamtläufe und abschließende Queue-/Einrichtungsfälle ebenfalls erfolgreich; zeitliche Grenzen und genaue Zahlen in verification.md. Composer-Prüfungen auf allen vier Versionen, 7 Node-Tests, ESLint und reproduzierbarer Paketbau mit Produktionsloader geprüft. Geeigneter manueller Commit-Zwischenstand; Block 8 bleibt bis zur oben genannten Anbindung und Abnahme offen.


## Block 8 – Teamauslöser und Queueverwaltung (2026-11-08)

[Umgesetztes Paket](block-8-delivery.md): TeamManager speichert Benachrichtigungen vor dem Commit in derselben Transaktion. Gründung, Einladung, Annahme, Freigabe und ausdrückliche erneute Bestätigung verwenden konkrete Events, Empfänger, Mitgliedersprachen und feste Inhalte. Neuer eigenständiger Teilnehmer-/Gasteinstieg für Eventlinks. Globaler automatischer Mailerfallback entfernt; notify bleibt kompatibel wirkungslos, globale Originalvorlagen bleiben Kopierquelle.

Native Eventverwaltung mit paginierter Liste, Textvorschau, Freigabe/Pause, geprüftem Einzelversand, ausdrücklicher Nachbereitung fehlender Vorlagen, begrenztem Retry, Anspruchsklärung, Abschluss unklarer Nachrichten und Akteursjournal. POST/Nonce, Objektberechtigung, Event-/Nachrichtenbindung, Revision und zusätzliche Versandbestätigung. Reale Zustellung bleibt ohne Serverfreigabe und Eventaktivierung gesperrt; kein Hintergrundjob und kein Versand aus dieser Umsetzung.

Die zuvor offene Teamanbindung und Queueverwaltung sind damit implementiert. Als Nächstes manuellen Rundmailversand modernisieren, anschließend Börse. Aufbewahrung/Bereinigung, Browser-/Tastatur-/Accessibility-, Anbieter- und Bestandsabnahme sowie vollständiger Block-8-/Releaseabschluss bleiben offen. Bisherige Schema-/Installationspläne bleiben unverändert; keine Anwendungsmigration und keine Git-Aktionen.


Prüfabschluss der Auslöser-/Verwaltungslogik: vollständige isolierte und WordPress-Suiten unter PHP 8.2/8.3/8.4/8.5 bestanden; nach letzter Einladungs-Kompatibilitätsprüfung alle 21 betroffenen Integrationsfälle mit je 235 Assertions auf allen Versionen wiederholt. Composer-Prüfungen, 7 Node-Tests und ESLint erfolgreich. Zwei echte Workerprozesse verhindern doppelten Transport, Teilnehmer-/Gasteinstieg in getrennten WordPress-Prozessen geprüft. Prüfgrenzen und exakte Gesamtzahlen in verification.md. Geeigneter Zwischenstand für einen manuellen Commit; kein vollständiger Block-8-Abschluss.

Paketabschluss: 282 Dateien, bytegleiche Wiederholungsbuilds, Paketgrenzen und Produktionsautoloader unter PHP 8.2/8.5 geprüft. Reale Zustellung und Browserabnahme weiterhin nicht ausgeführt. Keine Git-Aktionen.


## Block 8 – Rundmails und Börse (2026-11-08)

[Abschlusspaket und Mitwirkung](block-8-completion.md): manuelle Einzel-/Event-/Team-/globale Kampagnen mit schreibfreier Vorschau, geprüftem Fingerabdruck, Empfänger-/Sprach-/Text-/Absendersnapshots und wiederholungsfestem Auftrag. Native Verwaltung mit expliziten Queue-Schritten (100), bestätigten Versand-Schritten (5), Pause, Retry und Klärung unklarer Transportausgänge. Größere Gruppen können nach ausdrücklichem Start mit sichtbarem Fortschritt und Stop nach dem aktuellen Schritt verarbeitet werden; kein Hintergrundjob oder automatischer Seitenstart. Aktive Teambenachrichtigungen verwenden weiterhin konkrete Eventvorlagen; manuelle Kontaktaufnahme ist davon getrennt und ändert keine Archive. Unmittelbarer Altformularversand entfernt.

Globale Börse mit Jahres-/Teilnahmefilter erhalten, native Formulare und Pagination, Eigentums-/Nonce-/Revisionsschutz, gemeinsame Transaktion und Änderungsjournal ergänzt. Explizite Moderation einschließlich alter/verwaister Einträge sowie sichtbare Entscheidungen nach Löschung. Zwei additive Installationsmodule; bestehende Pläne und IDs bleiben unverändert. Lesender Speicherbericht ohne automatische Löschung. Browser-/Tastatur-/Accessibility-Regressionen mit Playwright und axe-core samt CI-Schritt ergänzt.

Unabhängig umsetzbare technische Arbeiten dieses Blocks sind erledigt. Ausdrücklich nötig bleiben fachliche Inhalts-/Bestands-/Bedienungsabnahme, gesondert freigegebener echter Anbieterversand und Aufbewahrungs-/Anonymisierungsentscheidung. Keine neuen historischen Zahlungsbenachrichtigungen, keine Löschfristen geraten, keine Anwendungsmigration oder Git-Aktionen. Ausgeführte Matrix und Browsergrenzen ausschließlich nach verification.md. Nächster unabhängiger Block: 9, Ticker/Medien.


Prüfabschluss des Rundmail-/Börsenpakets: isolierte Gesamtsuite auf PHP 8.2/8.3/8.4/8.5 bestanden, WordPress-Gesamtläufe unter 8.3/8.5 und betroffene 228er-Auswahl unter 8.2/8.4. Nach den letzten Ergänzungen sämtliche 31 Rundmail-/Börsen-/Stapelfälle mit je 277 Assertions auf allen vier Versionen erfolgreich. Vier HTTP-/Browser-/Tastatur-/axe-Szenarien einschließlich AJAX-Queueverarbeitung unter PHP 8.2/8.5 bestanden. 14 Node-Tests, ESLint, Qualitätsprüfungen und bytegleiche ZIP-Builds mit 295 Dateien geprüft. Zeitliche Grenzen und offene echte Abnahmen in verification.md. Geeigneter manueller Commit-Zwischenstand; englische Message mitliefern. Block 8 bleibt nur für die oben genannten Mitwirkungs-/Freigabepunkte offen.


## Block 9 – Fachliche Festlegung (2026-11-09)

SMS-Ticker laut Benutzer eingestellt. Der weiterhin benötigte Ticker einschließlich Verwaltung wird vollständig in cor-mgmt integriert; separates cor-ticker beim kontrollierten Wechsel ablösen. Historische Inhalte/IDs und Medien erhalten, keine automatische Löschung nach Tabellenname oder Nachrichtentyp. Alte SMS-/MMS-/Gateway-Eingänge gezielt stilllegen, keine neue Anbieteranbindung. Plan, Quellbefunde und technische Kriterien in [Block 9](block-9-ticker-plan.md), D-50.

Fachliche und visuelle Prüfung aller Module gemeinsam in Block 10 gemäß D-51. Automatisierte Prüfungen bleiben laufend erforderlich. In diesem Schritt ausschließlich Dokumentation angepasst; kein Laufzeitcode, keine Nachbarplugins oder Anwendungsdaten geändert und keine Eingänge bereits technisch abgeschaltet. Dokumentationsprüfung ausgeführt; keine Laufzeitsuiten für diese Planänderung.

## Block 9 – Webticker und Medien umgesetzt (2026-11-09)

[Umsetzung und Übergangsvertrag](block-9-ticker.md): eigenständige native Eingaben, Kommentare, Bilder mit getrennten Drehkopien, Videos, Standorte, Karten und eventbezogene Moderation. Bestehende Shortcodes bleiben erhalten. Neue Schreibwege verwenden Objektberechtigungen, Nonces, Wiederholungsschutz, Transaktionen und ein Journal; Archive bleiben lesbar. SMS-/MMS-Eingänge sind stillgelegt.

Eigenes additives Tickerschema mit ausdrücklicher Einrichtung und begrenzter, wiederaufnehmbarer Übernahme. Original-IDs und bytegetreue Rohdaten bleiben erhalten; zweite Prüfung vor Umschaltung. Historische Tabellen und Originalmedien werden nicht gelöscht. Kein Wechsel oder umfangreicher Import bei gewöhnlichen Seitenaufrufen. Die drei gesonderten cor-ticker-Dateien sind außerhalb des cor-mgmt-Pakets geändert und müssen beim Ausrollen separat berücksichtigt werden.

Technische Umsetzung ohne weitere fachliche Mitwirkung abgeschlossen. Abschließende Fachsuite (350 Tests / 678 Assertions) und WordPress-Gesamtsuite (431 / 3.129) sowie Composer-Qualitätsprüfungen auf PHP 8.2/8.3/8.4/8.5 erfolgreich. Browser-, JavaScript- und Paketnachweise samt Grenzen stehen in [Prüfbelegen](verification.md). Keine Anwendungsdaten migriert, kein echter Versand und keine Git-Aktionen. Geeigneter Zwischenstand für einen manuellen Commit; englische Message mitliefern.

Nächster Block: 10, gemeinsame Bestands-, Funktions-, Oberflächen- und Releaseabnahme. Dazu gehören die kontrollierte Übernahme einer frischen Bestandskopie, historische Sonderfälle, Theme-/Nachbarintegration, Übersetzungen, Leistung sowie Sicherung und Wiederherstellung. Eine Produktions- oder Releasefreigabe ist mit diesem technischen Abschluss nicht erteilt.
