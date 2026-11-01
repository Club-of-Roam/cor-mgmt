# Prüfbelege Startphase

Prüfdatum: 2026-11-01 (Europe/Berlin). Anwendungscode weiterhin 1.2.10 / Schema 2.8.

| Prüfung | PHP 8.2.35 | PHP 8.4.27 |
|---|---|---|
| Composer validate --strict | bestanden | bestanden |
| Syntaxprüfung | 32 PHP-Dateien, 0 Fehler | 32 PHP-Dateien, 0 Fehler |
| Dokumentations-/Inventarprüfung | 14 Markdown-Dateien, 23 Shortcodes, 0 Fehler | 14 Markdown-Dateien, 23 Shortcodes, 0 Fehler |
| PHPUnit 11.5.57 | 22 Tests, 38 Assertions, bestanden | 22 Tests, 38 Assertions, bestanden |

Befehle: composer check und composer test, jeweils mit dem ausdrücklich ausgewählten PHP-Binary. Die Syntaxprüfung blendet Veraltungsdiagnosen aus; sie ist keine vollständige PHP-8-Freigabe. Die Unit-Tests selbst laufen mit strenger Warnungs-/Veraltungsbehandlung, ohne WordPress, Datenbank oder externe Dienste.

Verwendete Testsoftware: Composer 2.11.1 und PHPUnit 11.5.57. composer.lock hält die aufgelösten 27 Entwicklungsabhängigkeiten fest; vendor ist ignoriert.

Inventar: 23 Shortcodes, 54 Methoden mit literalen Request-Feldern und 28 Legacy-PHP-Dateien. Hashes normalisieren BOM/CRLF, damit Windows und Linux dieselben Quellen erkennen.

## Grenzen

- GitHub Actions konfiguriert, kein Remote-Lauf ausgeführt; kein Push.
- Keine vollständigen WordPress-, Browser-, Datenbankmigrations- oder Produktionsprüfungen.
- PHPUnit deckt nur F-RANK-01 und F-TICK-04 ab, nicht alle Funktionen.
- Keine Änderung an Anwendungscode, Versionsheader, Datenbank.
- Block 0 bleibt begonnen; weitere fachliche Charakterisierung und WordPress-Integrationstests stehen aus.
