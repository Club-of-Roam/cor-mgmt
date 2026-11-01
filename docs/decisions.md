# Entscheidungen

Stand 2026-11-01.

| ID | Status | Entscheidung |
|---|---|---|
| D-01 | beschlossen | 2.0.0 als Ziel; Plugin-/Schema-/Migrationsversion getrennt |
| D-02 | beschlossen | PHP >= 8.2, CI mindestens 8.2/8.4; v2 muss PHP 7.4 nicht unterstützen |
| D-03 | beschlossen | Abläufe erhalten, Oberfläche schrittweise modernisieren |
| D-04 | beschlossen | Modulares WordPress-Plugin; kein vollständiger Framework-/SPA-Neubau erforderlich |
| D-05 | beschlossen | Historie/IDs/Orphans erhalten, keine automatische Bereinigung |
| D-06 | beschlossen | Dokumentation und Tests begleiten jeden Block |
| D-07 | beschlossen | Synthetische Testdaten, eigene DB/Uploads, kein echter Versand oder Zahlungszugriff |
| D-08 | beschlossen | Bestehende Shortcodes und Attribute kompatibel halten |
| D-09 | zurückgestellt | Künftige Zahlungswege erst nach separater Fachprüfung; historische Methoden sind keine Freigabe |
| D-10 | beschlossen | PHPUnit 11.5-Linie passt zu PHP 8.2; genaue Version in composer.lock |
| D-11 | beschlossen | Eigenständiges WordPress-Plugin mit vollständigem ZIP und Einrichtung; keine verpflichtenden Spezialthemes oder weiteren Fachplugins. Lebenszyklus und Abnahme gemäß installation.md |

## Offene Fachprüfung

- Block 7: aktive Verfahren versus Archiv, maßgebliche Bestätigung, Testumgebung des Anbieters ermitteln; vor Implementierung dokumentieren.
- Block 6: historische Punktefehler zunächst mit Ist-/Soll-Vergleich belegen; keine stille Neuberechnung der Historie.
- Block 3/7: Betragseinheiten pro Schreibpfad nachweisen; Mehrdeutiges berichten, keine pauschale Multiplikation.
- Block 9: realen Gateway-Vertrag, Zeichensatz und Zeitformat ermitteln; vorher keine Rückwärtskompatibilität behaupten.

Diese Fragen verhindern keine Dokumentation/Referenztests, aber die jeweilige unbelegte fachliche Umstellung.
