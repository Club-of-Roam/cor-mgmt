# Block 8 – Kommunikation und Börse: Umsetzungsplan

Planstand: 2026-11-05. Seit 2026-11-08 ist das erste Vorlagen-Teilpaket umgesetzt; [aktueller Umfang und Übergangsgrenze](block-8-templates.md). Die [Queue-Grundlage](block-8-queue.md) ist als zweites Teilpaket umgesetzt. [Teamauslöser und Queueverwaltung](block-8-delivery.md) sind ebenfalls umgesetzt. Manueller Rundmailversand, Börse sowie die ausgewiesenen Gesamtabnahmen bleiben offen. Verbindliche Ergänzung des Benutzers: Autoresponses müssen je Event unabhängig angelegt und eingestellt werden können, damit parallele Events keine gemeinsamen Versandregeln verwenden (D-44).

## Bestätigter Ausgangsbefund

Die Legacy-Tabelle besitzt Aktion, Sprache, Schalter, Betreff und Nachricht, aber keine Eventzuordnung: [Catalogue::legacy](../src/Infrastructure/WordPress/Schema/Catalogue.php). [Mailer::auto_response](../includes/class-h3-mgmt-mailer.php) lädt ausschließlich nach Aktion und Sprache. Auch [autoresponses_edit](../admin/class-h3-mgmt-admin-emails.php) liest und schreibt diese globale Kombination. Die aktuelle Benachrichtigungsbrücke [TeamBoundary](../src/WordPress/TeamBoundary.php) muss beim Umbau den jeweiligen Veranstaltungskontext ausdrücklich weitergeben. Quellcodeprüfung am 2026-11-05; keine Anwendungstabellen untersucht oder verändert.

## Verbindliches Ziel: Autoresponses pro Event

- Jede Veranstaltung bekommt eigene Einstellungen pro Aktion und Sprache: aktiv/inaktiv, Betreff und Nachricht. Im bisherigen Modell wird die konkrete Veranstaltung über die ID in `h3_mgmt_races` bezeichnet; diese ID ist der fachliche Bezug. Keine zusätzliche Ebene namens Event ohne Abgleich des vorhandenen Modells erfinden.
- Verwaltung mit sichtbarer Veranstaltungsauswahl und Sprachwahl. Vorlagen können pro Veranstaltung angelegt und unabhängig geändert werden. Kopieren aus einer anderen Veranstaltung ist eine ausdrückliche Aktion und erzeugt unabhängige Datensätze, keine gemeinsame Referenz.
- Eine Änderung oder Deaktivierung bei Event A verändert Event B nicht. Die Auswahl „aktives Rennen“ ist kein Versandkontext: Auch bei zwei gleichzeitig laufenden Veranstaltungen entscheidet allein die zum Auslöser gehörende Veranstaltungs-ID.
- Auflösung ausschließlich nach Veranstaltung, Aktion und Sprache; diese Kombination im Zielmodell eindeutig absichern. Kein stiller Rückgriff auf die bisherige globale Vorlage oder die Vorlage eines anderen Events. Fehlende Konfiguration sichtbar melden und nicht mit fremdem Inhalt versenden.
- Veranstaltungsbezogene Platzhalter, Links, Teamzuordnung und Empfänger müssen aus demselben Kontext stammen. Derselbe Benutzer kann Nachrichten aus verschiedenen Events erhalten; die Versandvermeidung darf diese nicht miteinander verwechseln.
- Rechte für die konkret ausgewählte Veranstaltung prüfen. POST/Nonce, Objektberechtigung, Revision und Journal für Änderungen vorsehen. Eingefrorene Archive gemäß Phasenvertrag erhalten.
- Reine WordPress-Konto-/Systemmails getrennt behandeln; fehlt eine fachliche Eventzuordnung, diese nicht aus einem Benutzerkonto oder dem gerade aktiven Rennen erraten.

## Datenmodell, Einrichtung und Übergang

Das Kommunikationsmodul erhält ein additives, unabhängig versioniertes Schema. Geplant: veranstaltungsbezogene Vorlagen mit eindeutigem Schlüssel aus Veranstaltungs-ID/Aktion/Sprache, Aktivierungsschalter, Betreff, Nachricht und Revision; zusätzlich eine kontrollierte Versandwarteschlange mit Zustands-/Versuchsprotokoll. Bisherige Storage-Pläne und Tabellen nicht nachträglich umdeuten oder während gewöhnlicher Seitenaufrufe ändern.

Vorhandene globale Vorlagen einschließlich Sprachen und Schalter unverändert als Ausgangsbestand erhalten. Kontrollierte Übernahme bietet Vorprüfung und Vorschau sowie eine ausdrückliche Auswahl der Zielveranstaltungen. Keine willkürliche Zuordnung anhand des aktiven Rennens. Je Zielveranstaltung eigene Kopie erstellen; Wiederholung erzeugt keine Duplikate und überschreibt keine bereits individuell bearbeiteten Vorlagen. Fehlende Veranstaltungs-/Sprachzuordnung und doppelte Altvorlagen melden. Eine Übernahme löst weder rückwirkende Benachrichtigungen noch einen Versandtest aus.

Bei neuer Veranstaltung einen eigenen Vorlagensatz einrichten; tatsächlicher Versand setzt die geprüfte veranstaltungsbezogene Konfiguration voraus. Initiale Vorlageninhalte und Übernahmedetails beim Bestandsabgleich prüfen. Absender-/Antwortadressen beim Versandumbau gesondert erfassen; daraus folgt keine Anforderung nach getrennten SMTP-Zugängen.

## Geplante Reihenfolge

1. Auslöser, Texte, Sprachen, Platzhalter und Empfängergruppen vollständig erfassen; Börseneinträge und Eigentumsregeln aufnehmen.
2. Eigenes Kommunikationsschema und veranstaltungsbezogene Vorlagenverwaltung mit expliziter Übernahme einführen. Benachrichtigungsbrücken auf eindeutigen Veranstaltungskontext umstellen.
3. Kontrollierte Versandwarteschlange ergänzen: Event-ID, Auslöser und Empfänger festhalten; ausgewählte Sprache, Vorlagenrevision und gerenderten Inhalt beim Einreihen fixieren. Ein späterer Wechsel des aktiven Rennens oder einer Vorlage darf wartende Nachrichten nicht einem anderen Event zuordnen.
4. Wiederholungsschutz je fachlichem Vorgang inklusive Event-ID, begrenzte Wiederholungen, Fehler-/Versandstatus und geschützte Vorschau implementieren. Unklarer Transportausgang darf nicht unkontrolliert zu Mehrfachsendungen führen.
5. Börsenverwaltung und Formulare modernisieren; Eigentums-/Eventgrenzen und bestehende Abläufe erhalten.
6. PHP-Matrix, Integration, Migration, parallele Verarbeitung und Browser-/Tastaturabnahme prüfen; Dokumentation und Status aktualisieren. Echten Mailversand in Entwicklung und automatisierten Tests gesperrt halten.

## Abnahmeszenarien für die Eventtrennung

| Fall | Erwartung |
|---|---|
| A und B besitzen dieselbe Aktion/Sprache mit unterschiedlichen Texten | Jeweils nur Text, Links und Einstellungen des betreffenden Events verwenden |
| Autoresponse bei A aus, bei B an | A versendet nicht; B bleibt unverändert nutzbar |
| A bearbeiten oder aus A nach B kopieren | Keine gemeinsame Referenz; spätere Änderungen bleiben unabhängig |
| Vorlagensatz fehlt bei A, ist aber global oder bei B vorhanden | Kein stiller Fremd-/Globalfallback; Konfigurationsproblem sichtbar |
| Ein Benutzer nimmt an A und B teil | Vorlagen und Wiederholungsschutz unterscheiden die fachlichen Vorgänge beider Events |
| Aktives Rennen wechselt nach Einreihen einer Nachricht | Event, Empfänger, Sprache und vorgesehener Inhalt bleiben dem ursprünglichen Vorgang zugeordnet |
| Event A wird von einem Verantwortlichen für B manipuliert | Objektberechtigung verhindert Lesen/Ändern entsprechend den festgelegten Rechten |
| Globale Vorlagen werden erneut übernommen | Unveränderte Quelle, keine doppelten Kopien oder überschriebenen individuellen Änderungen, kein Versand |
| Zwei Worker verarbeiten denselben Vorgang | Kontrollierter Anspruch und Wiederholungsschutz innerhalb desselben Events |

Die Konfigurationsfälle sind im ersten Teilpaket automatisiert geprüft; Queue-Speicher und Workerzustände sind im zweiten Teilpaket separat geprüft; der vollständige Produzenten-/Versandpfad bleibt ein geplantes Abnahmekriterium. Tatsächliche Nachweise siehe verification.md. Die übrigen Kriterien für Kommunikation/Börse aus Masterplan und Referenzkatalog gelten weiter.
