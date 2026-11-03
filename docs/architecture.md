# Zielarchitektur

Status: Schichten und Adapter aus [Block 2](block-2-foundation.md) eingeführt; Fachmodule werden schrittweise umgestellt.

## Schichten

- Domain: typisierte Fachregeln für Rennen, Teilnahme, Ergebnisse, Spenden und Nachrichten; keine WP-Globals oder SQL-/HTTP-Aufrufe.
- Application: Anwendungsfälle und explizite Transaktionsgrenzen; Zeit, Zufall, Mail und Zahlungsdienste über Schnittstellen injizieren.
- Infrastructure: Repositories mit wpdb, WP HTTP API, WordPress-Medienverwaltung und kontrollierter Versandverarbeitung.
- WordPress: Hooks, Shortcodes, REST-Controller, Authentifizierung, Objektberechtigungen, Request-Validierung und Übersetzung.
- Presentation: kontextgerecht ausgegebene Templates, modulare Assets und zugängliche Formulare. Zunächst serverseitiges HTML mit gezielten JavaScript-Modulen, keine vollständige SPA.

Composer PSR-4 unter Cor\Mgmt ist aktiv. Der Bootstrap lädt neue Klassen, der Adapter runtime/legacy.php erhält bisherige Einstiegspunkte. Entwicklungsabhängigkeiten nicht ins Produktionspaket. REST unter cor-mgmt/v2; konkrete Ressourcen mit den Fachmodulen definieren.

## Eigenständiger Betrieb

[Installationsvertrag](installation.md) ist verpflichtend. WordPress bleibt Voraussetzung; Spezialtheme, cor-ticker, Members, Sprach-/Avatar-/Login-Erweiterungen bleiben optionale Integrationen. Eigene Darstellung, Rechte, Konfiguration und benötigte Ticker-Verwaltung liegen im Plugin. Vollständiges ZIP enthält fertige Runtime-Dateien; Composer/npm werden nur für Entwicklung/Build benötigt.

## Übergang

Plugin-Basename, Shortcodes/Attribute, Links und IDs erhalten. Legacy-Globals und Theme-Includes erst nach belegtem Ersatz entfernen. Handler delegieren schrittweise an Anwendungsfälle. Pro Datenbereich genau eine schreibende Implementierung; keine unkontrollierten Dual Writes.

Lesen verändert keine Daten. Änderungen prüfen Nonce und Objektberechtigung; sichtbare Menüs reichen nicht. öffentliche Spenden benötigen sitzungs-/tokenbezogenen Schutz, nicht pauschal eine Anmeldung. Externe Gateways benötigen maschinelle Authentifizierung und Wiederholungsschutz statt Browser-Nonces.

## Externe Kopplungen

| Komponente | Kopplung | Behandlung |
|---|---|---|
| cor-theme | Templates, Anmeldung, Datenschutz, Layout | Eigene funktionsfähige Oberfläche; Adapter für bestehendes Theme |
| cor-ticker | Gemeinsame sms_ticker-Tabelle | Eigene Ticker-Verwaltung; optionaler Adapter, kontrollierter Datenübergang |
| uploads/ticker_images/process.php | Eigenständiger Upload aus app.js | Geschützter Plugin-Endpunkt, Aufrufer umstellen, alten Eingang stilllegen |
| Profile_Layer_Pics | Historische Bildverarbeitung | Nutzung vor Änderung klären |
| WPML/Members/Avatar/Embed Privacy | Integrationen | WordPress-Fallbacks; Abwesenheit, Sprache und Version prüfen |

Umgebungskonfiguration bleibt außerhalb der Fachlogik. Tests dürfen keine produktiven Verbindungen aktivieren.

## Datenübergang ab Block 3

[Schema und Migration](block-3-migration.md) trennen Erstinstallation, bytegenaue Quellkopie, geprüfte Zwischenprojektionen und spätere aktive Modulumschaltung. Die Laufzeit verwendet weiter den Bestandsadapter; ein verified-Lauf ist keine aktive Generation. Neue Fachrepositories dürfen nicht gleichzeitig mit Legacy-Writern dieselben fachlichen Daten führen. Vor der Umschaltung erneute Quellenprüfung, Archivsnapshot und Rückweg abnehmen.
