# cor-mgmt / HitchHikingHub Management

WordPress-Plugin für Veranstaltungen, Teams, Teilnahme, Ranglisten, Spenden und Liveticker.

**Bestand:** Plugin 1.2.10, Schema 2.8. **Ziel:** 2.0.0 für die jeweils neueste stabile PHP-Version bei vollständiger Unterstützung ab PHP 8.2. Kompatibilitätsvertrag und Zielmatrix: [Masterplan](docs/masterplan.md).
Ziel ist ein eigenständig installierbares WordPress-Plugin ohne verpflichtendes Spezialtheme oder weiteres Fachplugin; siehe [Installationsvertrag](docs/installation.md).

Block 0 (Referenz) und Block 1 (Sicherheitsbasis) sind umgesetzt. Die Sicherheitskorrekturen und geänderten Integrationsverträge stehen in [Block 1](docs/block-1-security.md). Die technische Laufzeitbasis aus [Block 2](docs/block-2-foundation.md) ist eingeführt. Erstinstallation und kontrollierte Zwischenmigration aus [Block 3](docs/block-3-migration.md) sind umgesetzt. Die Rennen-/Routenverwaltung aus [Block 4](docs/block-4-events.md) verwendet neue Fachregeln, Writer und Verwaltungsoberflächen. Teams, Einladungen, Profile und Teilnehmerverwaltung aus [Block 5](docs/block-5-teams.md) verwenden eine gemeinsame transaktionale Fachlogik. Die versionierte Ergebnislogik aus [Block 6](docs/block-6-results.md) bietet normalisierte Etappenwerte für ausdrücklich eingerichtete neue Wertungen und einen schreibfreien historischen Vergleich. Die Betterplace-Grundlage aus [Block 7](docs/block-7-donations.md) erhält den bestehenden Zuordnungsablauf und ergänzt exakte Centbeträge und ein Journal. Die erste [Vorlagenbasis aus Block 8](docs/block-8-templates.md) ergänzt Eventkonfiguration und kontrollierte Übernahme. Die [Queue-Grundlage](docs/block-8-queue.md) ist implementiert; [Teamauslöser und Queueverwaltung](docs/block-8-delivery.md) sind angebunden. Teambenachrichtigungen verwenden Eventvorlagen und kontrollierte Einzelfreigaben. [Rundmails und Börse](docs/block-8-completion.md) sind ebenfalls modernisiert; fachliche, Anbieter- und Aufbewahrungsfreigaben bleiben ausdrücklich offen. Der [integrierte Webticker aus Block 9](docs/block-9-ticker.md) ergänzt native Eingaben, Medien, Moderation und eine kontrollierte Bestandsübernahme; SMS-Eingänge sind stillgelegt. Die Gesamtmodernisierung und Releaseabnahme sind noch offen.

## Einstieg

1. [Arbeitsregeln](AGENTS.md) lesen.
2. [Masterplan](docs/masterplan.md), [Arbeitsstatus](docs/status.md) und [Entscheidungen](docs/decisions.md) lesen.
3. Nächstes offenes Arbeitspaket mit seinen Abnahmeszenarien bearbeiten.
4. Code, Tests und Dokumentation gemeinsam aktualisieren.

| Dokument | Inhalt |
|---|---|
| [Archiv und aktive Umschaltung Block 10](docs/block-10-archive.md) | Geprüfte Snapshots, historische Anzeigen, parallele Fachtabellen, Wiederaufnahme und Rückweg |
| [Gesamtabnahme Block 10](docs/block-10-acceptance.md) | ZIP-Lebenszyklus, Seiteneinrichtung, Übernahmeprobe, Releaseblocker und gemeinsame Abnahmeliste |
| [Ticker und Medien Block 9](docs/block-9-ticker.md) | Native Webeingaben, Medien, Moderation, Installation und kontrollierte Übernahme |
| [Plan Ticker und Medien Block 9](docs/block-9-ticker-plan.md) | Integrierter Webticker, Ablösung von cor-ticker, Stilllegung der SMS-Strecke und Datenerhalt |
| [Mailauslöser und Verwaltung Block 8](docs/block-8-delivery.md) | Atomare Teambenachrichtigungen, Eventlinks, Vorschau und kontrollierter Versand |
| [Queue-Grundlage Block 8](docs/block-8-queue.md) | Nachrichtensnapshots, Ansprüche, Wiederholungen und offene Auslöseranbindung |
| [Vorlagenbasis Block 8](docs/block-8-templates.md) | Eventbezogene Vorlagen, Vorprüfung/Kopie und offene Versandumschaltung |
| [Plan Kommunikation Block 8](docs/block-8-communication-plan.md) | Autoresponses je Event, Übergang globaler Vorlagen, kontrollierter Versand und Börse |
| [Spendenverwaltung Block 7](docs/block-7-administration.md) | Profile, Sichtbarkeit, Altvorgangsprüfung und ganze Euro im Frontend |
| [Spenden Block 7](docs/block-7-donations.md) | Betterplace, neue Centbeträge, einmalige Zuordnung, Installation und offene Automatisierungsprüfung |
| [Ergebnisse Block 6](docs/block-6-results.md) | Versionierte Punkteberechnung, Etappenergebnisse, Ranglisten und Historienvergleich |
| [Teams und Profile Block 5](docs/block-5-teams.md) | Teilnahme, Einladungen, Mitgliedschaften, Profilbilder, Freigabe und konkurrierende Platzvergabe |
| [Rennen und Routen Block 4](docs/block-4-events.md) | Phasenregeln, sichere Verwaltungswriter, Formulare, Kapazitätswarnungen und Grenzen |
| [Installation und Migration Block 3](docs/block-3-migration.md) | Schema, Vorprüfung, Journal, Wiederaufnahme und Grenzen der Zwischenkopie |
| [Technisches Fundament Block 2](docs/block-2-foundation.md) | Autoloading, Adapter, Lebenszyklus, Prüfwerkzeuge und Paketbau |
| [Sicherheitsbasis Block 1](docs/block-1-security.md) | Rechte, POST/Nonces, Medien, Gateway und Auswirkungen beim Ausrollen |
| [Referenzabschluss Block 0](docs/block-0-acceptance.md) | Umfang, Belege, Grenzen und Übergabe an Block 1 |
| [Referenzkatalog](docs/reference-catalog.md) | Ausgangslagen, Aktionen und Abnahmefälle sämtlicher Funktionsbereiche |
| [Rollen und Schnittstellen](docs/interfaces.md) | Hooks, Objektgrenzen und Nachbarintegrationen |
| [Analyse](docs/analysis.md) | Befunde, Prioritäten und Belege |
| [Funktionen](docs/features.md) | Abläufe und Referenzszenarien |
| [Fachregeln](docs/business-rules.md) | Freigabe, Einladungen, Kapazitäten und Ist-Befunde |
| [Phasenmatrix](docs/phases.md) | Verbindliche Zielregeln für fünf Phasen einschließlich eingefrorenem Archiv |
| [Ergebnisreferenz](docs/ranking-reference.md) | Ausgeführte Referenzfälle und reproduzierte Punkte-/Ranglistenfehler |
| [Shortcodes](docs/shortcodes.md) | Generierte Namen, Handler und Standardattribute |
| [Aktionsinventar](docs/actions.md) | Request-Felder je Methode, kein Sicherheitsnachweis |
| [Architektur](docs/architecture.md) | Zielstruktur und Kompatibilität |
| [Migration](docs/migration.md) | Datenmodell, Datenbefunde und Übergang |
| [Installation und Eigenständigkeit](docs/installation.md) | ZIP, Einrichtung, Abhängigkeiten und Plugin-Lebenszyklus |
| [Tests](docs/testing.md) | Ausführbare und geplante Prüfungen |
| [Release](docs/release.md) | Abnahme und Wiederherstellung |

## Entwicklung

Für JavaScript-Prüfungen wird Node.js 24 benötigt. Weitere Voraussetzung: aktuelle Composer-2-Version und PHP 8.2 oder neuer im PATH; Prüfmatrix 8.2/8.3/8.4/8.5. Im Plugin-Repository:

```sh
composer install
npm ci --ignore-scripts
composer check
composer test
composer test:js
npm run lint
npm run test:browser
composer build
php tools/check-package.php
```

WordPress-Referenzsuite: composer test:integration. Benötigte Testsoftware und Prüfgrenzen stehen in [Tests](docs/testing.md); die CI stellt ausschließlich synthetische Testressourcen bereit.

Inventar regenerieren: `python tools/build-inventory.py` (Python >= 3.8), danach Diff prüfen.

Das laufende Plugin benötigt den Composer-Autoloader. Der ZIP-Build liefert ihn fertig mit und schließt Entwicklungsabhängigkeiten aus. Auf dem Zielserver sind keine Buildwerkzeuge erforderlich. Details zur technischen Basis: [Block 2](docs/block-2-foundation.md). Für Tests keine echten Mails oder Zahlungsaufrufe verwenden. Zugangsdaten, Dumps, private Medien und Sitzungen gehören nicht ins Repository.
