# Block 7 – Betterplace und Spenden

Stand und Quellcodeprüfung: 2026-11-05. Fachentscheidungen D-39/40. Technische Umsetzung im Kompatibilitätsbetrieb; keine Releasefreigabe.

## Bestätigter Ablauf

Für neue Spenden wird ausschließlich Betterplace verwendet. Team wählen, bei Betterplace spenden, anschließend mit „Fertig“ die Zuordnung prüfen und das öffentliche Spenderprofil ausfüllen. TeamSponsor und TeamOwner bleiben erhalten; TeamOwner setzt mindestens 100 EUR und einen freien Owner-Platz voraus. Die Verfügbarkeit wird bei der Bestätigung erneut geprüft; eine Absicht reserviert keinen Owner-Platz. Andere historische Zahlungswege und ihre Datensätze bleiben erhalten. Neue PayPal-/Lastschriftzahlungen werden nicht angeboten.

Der Benutzer hat ausdrücklich den bestehenden Bestätigungsablauf bestätigt und die Prüfung weiterer Automatisierung vorgemerkt. Dies ist keine Freigabe für neue Zahlungsdienste, eine erfundene manuelle Bestätigungsregel oder eine pauschale Umrechnung alter Beträge.

## Befunde und Belege

- Der alte Client-API-Pfad ist im Bestand deaktiviert: `donation_check()` gibt stets false zurück. Die davon getrennte neuere Zuordnung über öffentliche Betterplace-Kommentare war im Quellcode aktiv. Ob sie beim Anbieter tatsächlich erreichbar und für das konkrete Konto zulässig ist, wurde nicht produktiv geprüft.
- Die bisherige Kommentarzuordnung verwendete einen Code, ließ Centanteile bei der Umrechnung über Ganzzahlen verloren gehen und enthielt keine belastbare einmalige Belegung einer Anbieter-ID. Diese Writer wurden durch [DonationLedger](../src/Infrastructure/WordPress/DonationLedger.php) ersetzt.
- Browserbetrag und Browser-Rückleitung sind kein Zahlungsnachweis. Der alte `save_donation()`-Eingang bleibt gesperrt; `handle_betterplace_redirect()` ist nur ein Hinweis. Einstiegspunkte: [Sponsors](../includes/class-h3-mgmt-sponsors.php).
- Historische Zusammenfassungen zählen teilweise vor dem Filtern und behandeln unbezahlte Lastschrift anders als andere Methoden. Ihre Charakterisierung bleibt erhalten; eine fachliche Korrektur historischer Statistik gehört nicht zur neuen Bestätigung.
- Die öffentliche API kann den Betrag verbergen. Ohne sichtbaren positiven Centbetrag wird keine Zuordnung bestätigt. Ein öffentlicher Kommentar ist weder signierter Zahlungsbeleg noch Nachweis über Auszahlung, Storno oder Rückerstattung.

Anbieterquellen, geprüft 2026-11-05: [API-Übersicht](https://github.com/betterplace/betterplace_apidocs), [Opinions-Vertrag](https://github.com/betterplace/betterplace_apidocs/blob/master/sections/opinions_list.md). Client-API benötigt eine gesonderte Vereinbarung; deren Einsatz wird hier nicht implementiert. Kein echter Zahlungsaufruf wurde ausgeführt.

## Technische Umsetzung

[EuroAmount](../src/Domain/EuroAmount.php) rechnet neue Beträge als ganze Centbeträge mit EUR. [BetterplaceEvent](../src/Domain/BetterplaceEvent.php) akzeptiert nur zulässige HTTPS-Veranstaltungsadressen. [PublicOpinions](../src/Infrastructure/Betterplace/PublicOpinions.php) verwendet injizierbares HTTP, einen festen Anbieterhost, maximal fünf Seiten mit je höchstens 100 Datensätzen, strenge Antwortprüfung und exakte Codegrenzen. Fehlende, verborgene, mehrdeutige, veraltete oder unvollständig durchsuchte Nachweise lassen den Vorgang offen.

[DonationLedger](../src/Infrastructure/WordPress/DonationLedger.php) bindet zufällige Codes an Rennen, Team und Browser-Sitzung. Der Cookie wird nur gehasht gespeichert und ist kein Teil des öffentlichen Codes. Status: `pending` → `confirmed`; identische Bestätigung ruft den Anbieter nicht erneut auf. Eine Opinion-ID kann genau einen Vorgang bestätigen. HTTP erfolgt außerhalb der gemeinsamen Datenbanksperre, danach werden Phase, Veranstaltung und Verfügbarkeit unter Sperre erneut geprüft. Legacy-Projektion und Journal werden gemeinsam transaktional geschrieben. Archive und ausgeschaltete Spendenfunktionen sperren neue Änderungen.

[DonationFrontend](../src/WordPress/DonationFrontend.php) liefert native POST-Formulare mit Nonce, Sitzungsbindung, Honeypot, Ausgabebehandlung und kontrolliertem Bild-Upload. Die bestehenden Shortcodes bleiben bestehen, einschließlich Teamvorauswahl über `id` und deaktiviertem Hinweistext. Rennattribut `race` ist eine ID oder `active`, Standard 0 (keine erfundene Rennzuordnung). Außerhalb der WordPress-Produktionsumgebung beziehungsweise bei `WP_HTTP_BLOCK_EXTERNAL` werden echte Anbieterabfragen und eingebettete Zahlungsformulare unterbunden. Tests injizieren Antworten. Neue Profile werden erst nach erfolgreicher Zuordnung veröffentlicht; Name darf für anonyme Anzeige leer bleiben. Kein automatischer Mailversand.

## Installation und Datenhaltung

Eigenes additives Spenden-Modul 1.0.0, unabhängig von Plugin 1.2.10, Legacy-Marker 2.8 und Storage 3.0.0. [SQL-Referenz](schema-donations-1.0.0.sql), [Definitionen](../src/Infrastructure/WordPress/Schema/DonationSchema.php). Tabellen `cor_mgmt_donations` und `cor_mgmt_donation_history` mit InnoDB/utf8mb4. Einrichtung bei Aktivierung oder ausdrücklich unter Systemstatus; keine DDL bei normalen Anzeigen. Bestehende Namenskollisionen oder beschädigte fertige Manifeste werden nicht automatisch übernommen/repariert. Gemeinsame transaktionale Writer benötigen auch transaktionale Legacy-Tabellen; keine automatische Engine-Umstellung.

Keine Massendatenmigration: alte Spenden, Einheiten, Cookies, unbekannte Felder und verwaiste Beziehungen bleiben unverändert. Neue verifizierte Centbeträge sind im Modul maßgeblich; die alte Ganzzahlspalte ist nur eine Kompatibilitätsprojektion mit ganzen Euro. `get_sponsor`, `get_donation_amount`, `get_sponsors_meta` und jüngste Spenden lesen neue exakte Werte über den Adapter. Direkte SQL-Konsumenten müssen vor Freigabe auf diesen Vertrag umgestellt werden. Neue Summen werden mit Centarithmetik berechnet; historische Zählkonventionen bleiben erhalten. Öffentliche Euroanzeigen bleiben ganze Euro, interne Reader und Verwaltung behalten Centbeträge.

Neue modulverwaltete Datensätze dürfen über alte Admin-Aktionen nicht bezahlt/unbezahlt gesetzt, verschoben, gelöscht oder bearbeitet werden. Kontrollierte Metadatenpflege und Sichtbarkeit bietet die [neue Verwaltung](block-7-administration.md), getrennt von unveränderlichen Zahlungsnachweisen. Historische Verwaltungsaktionen bleiben bestehen. Systemstatus bietet [DonationAudit](../src/Infrastructure/WordPress/DonationAudit.php): nur aggregierte Zustände, Methoden, Beziehungsprobleme und bekannte neue Centbeträge; keine Personen-/Sitzungsdaten und keine automatische Umrechnung.

## Abnahme und verbleibende Arbeit

Automatisierte Belege und tatsächlich ausgeführte Versionen: [verification.md](verification.md). Fachtests und WordPress-Tests decken Centbeträge, Anbieterfehler, verborgene Beträge, Sitzungs-/Rennbindung, Replay, Owner-Verfügbarkeit, Profilpflege, Rollback und wiederholbare Einrichtung ab.

Vor Einsatz zusätzlich erforderlich:

1. Anbieterzugang und konkrete Produktionskonfiguration fachlich abnehmen; erhaltenen Ablauf samt öffentlichem Kommentar und Sichtbarkeit des Betrags bestätigen.
2. Automatisierung prüfen: geeignete Client-API/Webhooks, Vertragszugang, Authentizität, Wiederholung, Rückerstattungen und Abgleich. Keine automatische Hintergrundbestätigung ist eingerichtet.
3. Historische Betragseinheiten je Ursprung nachweisen und Bestandsabgleich durchführen. Die kontrollierte Vorprüfung/Protokollierung alter offener Vorgänge ist umgesetzt. Tatsächliche Übernahme nur mit nachgewiesenem ursprünglichem Vertrag; keine stillschweigende Sitzungsberechtigung.
4. Verwaltung und Geldanzeigen sind ergänzt, Quellkonsumenten geprüft; vollständigen Bestands-/Browserabgleich weiterhin abnehmen. Details und erhaltene Statistikregeln in [Verwaltung und Anzeigen](block-7-administration.md).
5. Echte Browser-/Tastatur-/Accessibility-Abnahme, positive multipart-Bildprüfung und vollständiger Archivsnapshot. Kein echter Spendenvorgang wurde zum Test ausgelöst.

Block 7 ist damit technisch begonnen und serverseitig abgesichert; die genannten Freigabe- und Integrationskriterien bleiben offen.

Die ergänzte Verwaltung, öffentliche Ganz-Euro-Anzeige und Vorbereitung offener Altvorgänge sind in [Block 7 – Verwaltung](block-7-administration.md) dokumentiert.
