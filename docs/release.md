# Veröffentlichung und Wiederherstellung

Checkliste für Block 10; noch kein Release oder Deployment freigegeben.

## Vorbedingungen

Alle erforderlichen Blöcke und Zahlungsentscheidung abgeschlossen. Erst dann Versionsheader 2.0.0/Requires PHP >= 8.2 setzen. Unterstützte WordPress-Version anhand Tests deklarieren. Reproduzierbares Paket ohne Dumps, private Daten, Tests und Entwicklungsabhängigkeiten; benötigte Runtime-Abhängigkeiten/Autoloader einschließen.

Die jeweils neueste stabile PHP-Version ist das Releaseziel; PHP 8.2 bleibt mit allen Funktionen unterstützt. Freigabe nur mit erfolgreichen Prüfungen über die vollständige Zielmatrix aus masterplan.md und vollständiger Paketabnahme mindestens auf PHP 8.2 und der neuesten stabilen Version. Neue Laufzeitabhängigkeiten dürfen die Mindestversion nicht erhöhen. Zielversion vor jeder Veröffentlichung erneut prüfen; Beta-/RC-Versionen ersetzen keine stabile Abnahme.

CI, Bestandsmigration, Referenzfälle, Theme-/cor-ticker-Verträge und Medien prüfen. Die gesamte aktive Website auf PHP >= 8.2 prüfen; Plugin-Kompatibilität allein genügt nicht zum Umschalten.

Vor Release zusätzlich alle Fälle des [Installationsvertrags](installation.md) am fertigen ZIP bestehen: frisches WordPress mit Standardtheme ohne zusätzliche Fachplugins, keine Buildwerkzeuge am Ziel, Einrichtung und gesamter Plugin-Lebenszyklus.

## Probe und Wechsel

1. Frische Produktionskopie isolieren; Mail/Jobs/produktive Schnittstellen vor erstem Aufruf blockieren.
2. Vorprüfung/Probelauf, Abweichungen klassifizieren.
3. Migration und vollständige Daten-/Funktionsvergleiche, Abbruch/Wiederaufnahme prüfen.
4. Wiederherstellung von Code UND Daten nachweisen. DDL nicht als transaktional rückrollbar voraussetzen.
5. Wartungsfenster, Schreibzugriffe und externe Eingänge kontrolliert pausieren; finale Sicherung, Paket und Migration anwenden.
6. Startseite, Historie, Login, Admin, Teams, Ergebnisse, Medien und Ticker prüfen. Dienste erst nach erfüllten Kriterien aktivieren.

## Beobachtung und Rückweg

PHP-/Apache-Fehler, Migration, Zahlungen, Versandfehler und Gateway-Wiederholungen beobachten. Keine Tokens, Kontodaten oder vollständigen privaten Payloads loggen.

Vor neuen Schreibvorgängen definieren und testen: Rückweg nur während Wartung oder geprüfte Übernahme neuer Daten? Keine alte Sicherung über neue produktive Änderungen spielen. Verantwortliche, Trigger, Zeitgrenze und Sicherungsort vor tatsächlichem Wechsel konkret eintragen.
