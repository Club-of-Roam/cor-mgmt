# cor-mgmt / HitchHikingHub Management

WordPress-Plugin für Veranstaltungen, Teams, Teilnahme, Ranglisten, Spenden und Liveticker.

**Bestand:** Plugin 1.2.10, Schema 2.8. **Ziel:** 2.0.0 für die jeweils neueste stabile PHP-Version bei vollständiger Unterstützung ab PHP 8.2. Kompatibilitätsvertrag und Zielmatrix: [Masterplan](docs/masterplan.md).
Ziel ist ein eigenständig installierbares WordPress-Plugin ohne verpflichtendes Spezialtheme oder weiteres Fachplugin; siehe [Installationsvertrag](docs/installation.md).

Block 0 (Referenz) und Block 1 (Sicherheitsbasis) sind umgesetzt. Die Sicherheitskorrekturen und geänderten Integrationsverträge stehen in [Block 1](docs/block-1-security.md). Die technische Laufzeitbasis aus [Block 2](docs/block-2-foundation.md) ist eingeführt. Erstinstallation und kontrollierte Zwischenmigration aus [Block 3](docs/block-3-migration.md) sind umgesetzt. Die Rennen-/Routenverwaltung aus [Block 4](docs/block-4-events.md) verwendet neue Fachregeln, Writer und Verwaltungsoberflächen. Teams, Einladungen, Profile und Teilnehmerverwaltung aus [Block 5](docs/block-5-teams.md) verwenden eine gemeinsame transaktionale Fachlogik. Teams, Einladungen, Profile und Teilnehmerverwaltung aus [Block 5](docs/block-5-teams.md) verwenden eine gemeinsame transaktionale Fachlogik. Die fachliche Umschaltung, Gesamtmodernisierung und Releaseabnahme sind noch offen.

## Einstieg

1. [Arbeitsregeln](AGENTS.md) lesen.
2. [Masterplan](docs/masterplan.md), [Arbeitsstatus](docs/status.md) und [Entscheidungen](docs/decisions.md) lesen.
3. Nächstes offenes Arbeitspaket mit seinen Abnahmeszenarien bearbeiten.
4. Code, Tests und Dokumentation gemeinsam aktualisieren.

| Dokument | Inhalt |
|---|---|
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
composer build
php tools/check-package.php
```

WordPress-Referenzsuite: composer test:integration. Benötigte Testsoftware und Prüfgrenzen stehen in [Tests](docs/testing.md); die CI stellt ausschließlich synthetische Testressourcen bereit.

Inventar regenerieren: `python tools/build-inventory.py` (Python >= 3.8), danach Diff prüfen.

Das laufende Plugin benötigt den Composer-Autoloader. Der ZIP-Build liefert ihn fertig mit und schließt Entwicklungsabhängigkeiten aus. Auf dem Zielserver sind keine Buildwerkzeuge erforderlich. Details zur technischen Basis: [Block 2](docs/block-2-foundation.md). Für Tests keine echten Mails oder Zahlungsaufrufe verwenden. Zugangsdaten, Dumps, private Medien und Sitzungen gehören nicht ins Repository.
