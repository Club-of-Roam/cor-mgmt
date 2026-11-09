# Block 9 – Integrierter Ticker und Medien

Planstand: 2026-11-09. Fachliche Vorgabe des Benutzers: SMS-Ticker ist eingestellt; der weiterhin benötigte Webticker wird vollständig in cor-mgmt integriert (D-50). Dieses Dokument hält den freigegebenen Umfang fest; Umsetzung und technische Grenzen stehen in [Block 9](block-9-ticker.md).

## Umfang und Grenzen

- Tickeranzeige, Teilnehmerbeiträge, Kommentare, Bilder, Videos, Standorte/Karten und Verwaltung liegen künftig in cor-mgmt. Das separate Plugin cor-ticker wird für diese Funktionen abgelöst.
- SMS-Empfang und die zugehörige alte MMS-/Gateway-Strecke gehören nicht mehr zum Zielbetrieb. Kein neuer SMS-Anbieter, kein Wiederaufbau des alten Gateways und keine Freigabe alter Telefonnummern-Eingänge.
- Historische Beiträge, Bilder, Kommentare, Zuordnungen und IDs einschließlich früherer SMS/MMS-Inhalte bleiben erhalten. Der Tabellenname sms_ticker bezeichnet auch den gemeinsamen historischen/Webticker-Bestand und ist kein Löschkriterium.
- Bestehende benötigte Ticker-Shortcodes und deren Attribute bleiben kompatibel. Darstellung und Verwaltung funktionieren ohne Spezialtheme und ohne separates cor-ticker.
- Kontakttelefonnummern für die Organisation bleiben von der eingestellten SMS-Funktion getrennt; keine pauschale Löschung von Profilangaben ableiten. Hinweise, die eine aktive SMS-Einsendung versprechen, beim Umbau ersetzen.

## Bestätigte Ausgangsbefunde vor dem Umbau

Quellprüfung vor der Umsetzung am 2026-11-09; keine Anwendungstabellen oder externen Dienste untersucht. Der heutige Laufzeitstand und die abgelösten Eingänge stehen im [Umsetzungsbericht](block-9-ticker.md):

- cor-ticker/sms_ticker.php enthält das Plugin „SMS Ticker“ 1.0.1, eine Verwaltungsanzeige mit Löschaktion sowie Einrichtung für sms_ticker und sms_ticker_status. Dies ist übernehmbarer Funktionsumfang, kein Grund zur unveränderten Übernahme der alten Implementierung.
- cor-ticker/interfaces/sms.php enthält einen eigenständigen SMS/MMS-Eingang; interfaces/sms-status.php liefert eine Zeitantwort. Die betriebliche Einstellung laut Benutzer beweist nicht, dass diese PHP-Dateien technisch unerreichbar sind. Ihre Stilllegung gehört ausdrücklich zur Umschaltung.
- [Tickerklasse](../includes/class-h3-mgmt-ticker.php), [Medienadapter](../includes/class-h3-mgmt-media.php) und [Wartung](../includes/class-h3-mgmt-maintenance.php) greifen bereits in cor-mgmt auf den Tickerbestand zu. Der vorhandene geschützte Gateway-Einstieg ist ebenfalls beim Umbau stillzulegen.
- Historische Ausgangsbefunde zu Tabellen, Zeichensätzen und Abhängigkeiten stehen in [Migration](migration.md), [Schnittstellen](interfaces.md) und [Referenzkatalog](reference-catalog.md). Das ist keine neue Bestandszählung oder bereits geprüfte Datenübernahme.

## Umsetzungspakete

| Paket | Arbeit | Technischer Nachweis |
|---|---|---|
| 9.1 Bestandsvertrag | Aktive Webfunktionen, Shortcodes, Nachrichtentypen, Medienpfade, Eltern-/Kommentar-, Team-/Eventbezüge und Alteinträge erfassen; SMS-Wege als stillzulegen kennzeichnen | Synthetische Referenzen für Webbeiträge und historische SMS/MMS-Inhalte |
| 9.2 Daten und Einrichtung | Eigenes versioniertes Tickermodul mit Neuinstallation, kontrolliertem Bestandsübergang, Journal und geeigneten Indizes; bestehende Pläne unverändert erhalten | Neuinstallation ohne cor-ticker; wiederholbare Übernahme mit ID-/Inhaltsvergleich und Fehlerbericht |
| 9.3 Webticker | Reader, Writer und native Formulare für Beiträge/Kommentare/Medien; Objektberechtigungen, Nonces, Revisionen, Pagination und Eventbindung | Positive Abläufe, fremde IDs, Manipulation, Archivschutz und gleichzeitige Änderungen geprüft |
| 9.4 Verwaltung und Karten | Bisherige benötigte Moderation/Anzeige innerhalb cor-mgmt modernisieren; Karten-/Medienabhängigkeiten und Datenschutzgrenzen erfassen | Standardtheme, erforderliche Filter, Moderation und fehlende optionale Dienste geprüft |
| 9.5 Umschaltung | Alte und neue Datenhaltung/Writer kontrolliert abstimmen; separates Plugin beim Wechsel deaktivieren und verbliebene freie Endpunkte gezielt stilllegen | Keine doppelten Registrierungen oder konkurrierenden Writer; alte SMS-/Gateway-Eingänge nehmen nichts mehr an |
| 9.6 Gesamtnachweise | PHP-Matrix, Migration, Medien-/Rechte-/Browser-/Tastatur-/Accessibility-Tests und Paketprüfung ergänzen | Automatisierte Belege je Teilpaket; fachliche und visuelle Gesamtabnahme in Block 10 |

## Übergang und Datenerhalt

Kein unverändertes Kopieren des alten Plugins. Die benötigten Funktionen werden in die vorhandene modulare Architektur übernommen. Das alte Plugin bleibt bis zum kontrollierten Wechsel eine ausdrücklich erfasste Nachbarkomponente; sein Status wird nicht während gewöhnlicher Seitenaufrufe verändert. Vor der Umschaltung doppelte Menüs, Klassen-/Hooknamen, Shortcodes und alle Schreibpfade prüfen. Nur einen maßgeblichen Writer betreiben.

Alte Tabellen und Medien nicht automatisch entfernen. Unklare Typen, Telefonnummern als historische Absender, verwaiste Zuordnungen, unbekannte Zeit-/Zeichensatzformate und fehlende Dateien im Übernahmebericht ausweisen und unverändert sichern; keine heutigen Benutzer-/Eventzuordnungen erraten. Bestehende Archive bleiben eingefroren.

Die alten Eingänge werden bei ihrer Stilllegung ohne Datenannahme beendet, etwa mit HTTP 410 für ausgemusterte direkte URLs. Welche Dateien außerhalb von cor-mgmt geändert werden, wird getrennt dokumentiert. Eine weiterhin sinnvolle alte Anzeige-URL bzw. ein Shortcode kann über einen rein lesenden Kompatibilitätsadapter erhalten bleiben; daraus folgt kein Wiederaufleben des SMS-Empfangs.

## Abnahme und aktueller Status

Fachliche und visuelle Prüfung sämtlicher Blöcke gesammelt in Block 10 (D-51). Automatisierte Prüfungen laufen weiter mit jedem Umsetzungspaket. Eine abschließende Freigabe ersetzt keine technische Einrichtung, sichere Umschaltung oder Datenerhaltungsprüfung.

Die technischen Pakete sind im [Umsetzungsbericht](block-9-ticker.md) beschrieben. SMS-Eingänge sind im Code stillgelegt; ein Bestandsübergang läuft ausschließlich ausdrücklich. Ausgeführte Prüfungen und offene Bestands-/Gesamtabnahme: [Prüfbelege](verification.md).
