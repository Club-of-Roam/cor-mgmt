# cor-mgmt / HitchHikingHub Management

WordPress-Plugin für Veranstaltungen, Teams, Teilnahme, Ranglisten, Spenden und Liveticker.

**Bestand:** Plugin 1.2.10, Schema 2.8. **Ziel:** 2.0.0, PHP >= 8.2.
Ziel ist ein eigenständig installierbares WordPress-Plugin ohne verpflichtendes Spezialtheme oder weiteres Fachplugin; siehe [Installationsvertrag](docs/installation.md).

Dokumentation und erste isolierte Referenztests sind angelegt. Die Modernisierung ist noch nicht abgeschlossen; der Bestand ist nicht als sicher oder PHP-8-kompatibel freigegeben.

## Einstieg

1. [Arbeitsregeln](AGENTS.md) lesen.
2. [Masterplan](docs/masterplan.md), [Arbeitsstatus](docs/status.md) und [Entscheidungen](docs/decisions.md) lesen.
3. Nächstes offenes Arbeitspaket mit seinen Abnahmeszenarien bearbeiten.
4. Code, Tests und Dokumentation gemeinsam aktualisieren.

| Dokument | Inhalt |
|---|---|
| [Analyse](docs/analysis.md) | Befunde, Prioritäten und Belege |
| [Funktionen](docs/features.md) | Abläufe und Referenzszenarien |
| [Shortcodes](docs/shortcodes.md) | Generierte Namen, Handler und Standardattribute |
| [Aktionsinventar](docs/actions.md) | Request-Felder je Methode, kein Sicherheitsnachweis |
| [Architektur](docs/architecture.md) | Zielstruktur und Kompatibilität |
| [Migration](docs/migration.md) | Datenmodell, Datenbefunde und Übergang |
| [Installation und Eigenständigkeit](docs/installation.md) | ZIP, Einrichtung, Abhängigkeiten und Plugin-Lebenszyklus |
| [Tests](docs/testing.md) | Ausführbare und geplante Prüfungen |
| [Release](docs/release.md) | Abnahme und Wiederherstellung |

## Entwicklung

Voraussetzung: aktuelle Composer-2-Version und PHP 8.2/8.4 im PATH. Im Plugin-Repository:

```sh
composer install
composer check
composer test
```

Inventar regenerieren: `python tools/build-inventory.py` (Python >= 3.8), danach Diff prüfen.

Composer installiert derzeit ausschließlich Entwicklungswerkzeuge; das laufende Plugin lädt vendor nicht. Diese Abhängigkeiten nicht mit dem Produktionspaket ausliefern. Für Tests keine echten Mails oder Zahlungsaufrufe verwenden. Zugangsdaten, Dumps, private Medien und Sitzungen gehören nicht ins Repository.
