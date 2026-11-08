# Eigenständigkeit, Installation und Plugin-Lebenszyklus

Status: verbindliche Zielanforderungen für Version 2.0.0. Voraussetzungenprüfung, Schemaerkennung, datenerhaltender Lebenszyklus und ZIP-Build sind in [Block 2](block-2-foundation.md) umgesetzt. Erstinstallation und kontrollierte Zwischenmigration sind in [Block 3](block-3-migration.md) umgesetzt; die aktive Fachmigration und vollständige Abnahme bleiben offen.

## Eigenständigkeit

cor-mgmt ist ein eigenständig installierbares WordPress-Plugin. WordPress und die dokumentierten PHP-/Datenbankvoraussetzungen bleiben erforderlich. Es ist keine Anwendung außerhalb von WordPress.

PHP-Vertrag: jeweils neueste stabile PHP-Version als Ziel; PHP 8.2 und alle stabilen Zwischenversionen bleiben mit vollständigem Funktionsumfang unterstützt. Installation, Upgrade und Migration sowie sämtliche verpflichtenden Laufzeitabhängigkeiten müssen diesen Bereich abdecken. Release-Abnahme mindestens unter PHP 8.2 und der neuesten stabilen Version; Zielmatrix gemäß [Masterplan](masterplan.md). Noch keine erreichte Kompatibilitätsfreigabe.

Für seine fachlichen Funktionen benötigt das fertige Plugin weder cor-theme/Enfold noch cor-ticker, Members, Theme My Login, ein Avatar- oder Sprachplugin. Bestehende Integrationen bleiben optional kompatibel. Funktionen, die bisher nur durch Theme oder Nachbarplugin bereitgestellt werden, werden nach Inventarisierung innerhalb des Plugins oder mit WordPress-Bordmitteln zugänglich gemacht. Fremde Erweiterungen werden dadurch nicht pauschal ersetzt oder kopiert.

- Eigene Templates, Styles und Formularverarbeitung funktionieren mit einem unveränderten WordPress-Standardtheme.
- Anmeldung und Registrierung verwenden WordPress; kein zwingendes Theme-Login-System.
- Berechtigungen werden vom Plugin eingerichtet und geprüft, nicht von einem optionalen Rollenplugin vorausgesetzt.
- Ticker-Verwaltung und Datenhaltung sind ohne cor-ticker nutzbar. Bestehende gemeinsame Tabellen werden über einen kontrollierten Übergang weiter unterstützt; keine konkurrierenden Writer.
- Sprach-/Avatar-Erweiterungen sind optional. Ohne sie existieren definierte Standardausgaben und WordPress-Fallbacks.
- Keine ausführbare PHP-Datei im Upload-Verzeichnis und keine manuellen Theme- oder Core-Änderungen erforderlich.
- Seiten, URLs, IDs und Tabellenpräfixe werden ermittelt oder konfiguriert statt aus einer vorhandenen Website vorausgesetzt.

Zahlungsanbieter und externe Gateways benötigen weiterhin ihre jeweilige Konfiguration/Zugangsdaten. Ohne Konfiguration bleibt die zugehörige Integration deaktiviert und wird verständlich als nicht eingerichtet angezeigt. Der restliche Funktionsumfang bleibt nutzbar. Welche Zahlungswege angeboten werden, entscheidet weiterhin die separate Fachprüfung.

## Installationspaket und Erstaktivierung

Liefergegenstand ist ein reproduzierbares ZIP für den WordPress-Plugin-Upload. Es enthält alle benötigten Runtime-Dateien, fertige Assets, Autoloader und Runtime-Abhängigkeiten. Auf dem Zielserver sind kein Composer, npm, Build oder Git-Checkout nötig.

1. Voraussetzungen wie unterstützte WordPress-/PHP-/DB-Version und erforderliche Extensions prüfen. Bei fehlenden Voraussetzungen keine Teilinstallation und keine Schemaänderung; verständliche Administrationsmeldung.
2. Neue Installation getrennt von einem erkannten Altbestand behandeln. Neue Installation erstellt nur eigene Tabellen, Standardoptionen und Capabilities; keine Beispielpersonen oder echten Anbieterzugänge.
3. Einrichtungsablauf im WordPress-Admin zeigt fehlende Konfiguration. Benötigte Frontend-Seiten können darin ausdrücklich angelegt oder vorhandene Seiten zugeordnet werden. Wiederholung erzeugt keine Duplikate und überschreibt keine bestehenden Seiteninhalte.
4. Ohne fertiggestellte Einrichtung klare Statusanzeige statt Laufzeitfehler oder ungefragter externer Aufrufe.

## Updates und bestehende Installationen

Neuinstallation und Upgrade besitzen getrennte, getestete Pfade. Updates erkennen das vorhandene Schema und bieten kontrollierte Migration mit Vorprüfung, Journal und Wiederaufnahme. Keine umfangreichen Migrationen bei normalen Seitenaufrufen. Wiederholte Aktivierung verändert keine fachlichen Bestandsdaten.

Normale WordPress-Updates per vollständigem Plugin-Paket müssen funktionieren. Eine zusätzliche automatische Updatequelle ist damit noch nicht beschlossen. PHP-/WordPress-Mindestversionen und Schemafortschritt werden getrennt vom Plugin-Header behandelt.

## Deaktivierung, Reaktivierung und Deinstallation

- Deaktivierung stoppt eigene geplante Aufgaben und anwendungsbezogene Hintergrundverarbeitung. Fachliche Daten, Medien und Einstellungen bleiben erhalten.
- Reaktivierung nimmt den Betrieb ohne doppelte Aufgaben, Rollen oder Standarddaten auf.
- Deinstallation erhält Daten standardmäßig. Eine vollständige Bereinigung ist ein gesonderter, autorisierter Vorgang mit klar benanntem Umfang.
- Kein Löschen von WordPress-Benutzern, fremden Tabellen oder gemeinsam genutzten Medien. Eigene Seiten oder zusätzliche Rollen nur mit dokumentierter Eigentümerschaft behandeln.
- Installation, Fehlerbehebung und Wiederherstellung werden allgemein und rechnerunabhängig dokumentiert.

## Verbindliche Abnahme

| ID | Szenario | Erwartung |
|---|---|---|
| I-01 | ZIP auf frischem WordPress mit Standardtheme, ohne weitere Plugins | Aktivierung, Einrichtung und alle Kernfunktionen verfügbar |
| I-02 | Keine Composer-/npm-/Git-Werkzeuge auf Zielsystem | Fertiges Paket läuft vollständig |
| I-03 | Fehlende Mindestvoraussetzung | Klare Meldung, keine Teilmigration |
| I-04 | Seiten neu anlegen oder vorhandene zuordnen, Einrichtung wiederholen | Keine Duplikate oder überschriebenen Inhalte |
| I-05 | Keine Zahlungs-/Gateway-Konfiguration | Integration aus, verständlicher Status, andere Funktionen verfügbar |
| I-06 | Mehrfach aktivieren, deaktivieren und reaktivieren | Keine Datenverluste oder doppelten Aufgaben |
| I-07 | Upgrade des Bestands und unterbrochene Migration | Wiederaufnahme und nachweisbarer Datenerhalt |
| I-08 | Plugin löschen und später erneut installieren | Daten bleiben standardmäßig erhalten und werden erkannt |
| I-09 | Explizite Bereinigung | Nur eindeutig eigene freigegebene Daten betroffen |
| I-10 | Optionales Theme-/Sprach-/Rollen-/Ticker-Plugin fehlt oder wird deaktiviert | Kernfunktionen bleiben nutzbar, keine Fatal Errors |
| I-11 | Unterverzeichnis, anderes Präfix, andere Domain | Keine fest eingebauten Website-Pfade |
| I-12 | Bestehende Shortcodes/Links und historische Inhalte nach Upgrade | Kompatibilität gemäß Funktionsreferenz |

Diese Prüfungen werden als Integrations-, Paket- und Browserprüfungen umgesetzt. Einzelne Lebenszyklus- und Paketgrenzen werden bereits automatisiert geprüft; I-01 bis I-12 sind insgesamt noch nicht abgenommen. Die unterstützten WordPress-/Datenbankversionen werden vor dem Release aus der tatsächlich bestandenen Matrix erklärt; Multisite-Unterstützung darf ohne entsprechende Prüfungen nicht behauptet werden.

## Teilnahme-Modul ab Block 5

Aktivierung richtet zusätzlich das Teilnahme-Modul 1.0.0 ein. Bereits aktive Installationen bieten dafür eine geschützte Aktion im Systemstatus an. Der Aufbau ergänzt ausschließlich Token-/Historientabellen, prüft Eigentum und exakte Struktur und ist wiederaufnehmbar. Keine automatischen Änderungen vorhandener Tabellenengines. Bei fehlender Einrichtung bleiben neue Teamwriter gesperrt; gewöhnliche Seitenaufrufe erzeugen keine Tabellen. Der übergeordnete Release-/Archivvertrag gilt weiter. Details: [Block 5](block-5-teams.md).


## Ergebnis-Modul

Das additive Ergebnis-Modul 1.0.0 wird bei Aktivierung oder ausdrücklich über den Systemstatus eingerichtet. Normale Seitenaufrufe führen kein DDL aus. [SQL-Referenz](schema-results-1.0.0.sql); vorhandene Tabellen ohne passendes Manifest werden nicht übernommen. Anschließend kann ein berechtigter Organisator im Rennformular die Wertung 2.0 ausdrücklich aktivieren: nur Phase 0 und ohne bisherige Ergebniswerte. Schemaeinrichtung allein schaltet kein Rennen um. Einzelheiten und Historiengrenzen in [Block 6](block-6-results.md).

## Ergänzung Block 7 (2026-11-05)

[Betterplace und Spenden](block-7-donations.md) dokumentiert den bestätigten Ablauf, das additive Modul 1.0.0, den Schutz historischer Daten und die noch offene Automatisierungs-/Anbieter-/Browserabnahme. Neue Beträge werden in EUR-Cent geführt; keine geratene Bestandskonvertierung. Ausgeführte Prüfungen: [verification.md](verification.md).

## Kommunikations-Vorlagenmodul

Additives Modul 1.0.0 mit eigenem Manifest und zwei eigenen Tabellen; ausdrücklich unter Systemstatus oder bei Aktivierung einrichten. Keine alten Vorlagen automatisch zuordnen und keine bestehenden Pläne/Marker ändern. Einrichtung und Kopieren lösen keinen Versand aus. Die Eventkonfiguration ist bis zum Anschluss der Versandereignisse Vorbereitung ohne Laufzeitwirkung. [Umfang](block-8-templates.md).


Die Queue-Grundlage aus [Block 8](block-8-queue.md) besitzt ein separates additives Manifest `cor_mgmt_mail_queue_install` und die [Schema-Referenz](schema-mail-queue-1.0.0.sql). Einrichtung bei Aktivierung oder über das geschützte Datenbankwerkzeug; keine Änderung bestehender Installationspläne und kein Versandstart. Bestehende Mailauslöser sind noch nicht umgestellt.


Nachtrag: [Teamauslöser und Queueverwaltung](block-8-delivery.md) verwenden die bestehenden additiven Module ohne Änderung ihrer Manifeste. Eventvorlagen ersetzen den globalen automatischen Mailer. Fehlende Module vor Teilnahmeänderungen ausdrücklich einrichten. Eventvorlagen vor Verwendung prüfen/kopieren; fehlende Vorlagen führen zu blockierten Nachrichten. Die Eventfreigabe allein startet keinen Versand. Reale Einzelverarbeitung erfordert zusätzlich `COR_MGMT_ALLOW_MAIL_DELIVERY = true` und WordPress-Umgebung `production`; standardmäßig gesperrt. Keine automatische Cronaktivierung oder rückwirkende Mailerzeugung.
