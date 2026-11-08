# Block 8 – Grundlage der Versandwarteschlange

Stand: 2026-11-08. Zweites technisches Teilpaket nach der [Vorlagenverwaltung](block-8-templates.md). Die Queue und der Worker sind implementiert, aber noch nicht an die bestehenden Mailauslöser angebunden. Bisheriger Versand und dessen globale Vorlagen bleiben bis zu dieser Umschaltung unverändert. Kein vollständiger Abschluss von Block 8.

## Einrichtung und Schnittstellengrenze

[MailQueueSchema](../src/Infrastructure/WordPress/Schema/MailQueueSchema.php) ergänzt zwei eigene InnoDB-/utf8mb4-Tabellen mit einem unabhängigen Manifest `cor_mgmt_mail_queue_install`. Der bestehende Kommunikationsplan 1.0.0 bleibt unverändert. [SQL-Referenz](schema-mail-queue-1.0.0.sql). Einrichtung bei Pluginaktivierung oder ausdrücklich über das geschützte Datenbankwerkzeug `mail-queue-setup`; kein Schemaaufbau während gewöhnlicher Anzeigen, kein automatischer Versand oder Cron-Start.

[MailRequest](../src/Domain/MailRequest.php) verlangt Event-ID, stabile fachliche Vorgangsidentität, Aktion, konkrete Empfängeradresse, Sprache und Platzhalterwerte. Die Vorgangsidentität muss der Produzent innerhalb seiner Fachtransaktion erzeugen bzw. aus einem dauerhaft gespeicherten Ereignis beziehen. Weder aktuelle Uhrzeit noch ein später ausgelesener Journalhöchststand ersetzen diese Identität. Event, Aktion, Vorgang, normalisierte Adresse und Sprache bilden den Wiederholungsschlüssel. Unterschiedliche Events und echte Folgeereignisse bleiben getrennt. Derselbe Schlüssel mit abweichenden Eingabewerten wird als Konflikt zurückgewiesen.

[MailQueue](../src/Infrastructure/WordPress/MailQueue.php) ist eine interne Speicherschnittstelle, kein öffentliches Formular oder REST-Endpunkt. `enqueueWithinTransaction()` verlangt dieselbe Database-Instanz, gehaltene gemeinsame Sperre und laufende Transaktion des Fachwriters. Der Aufrufer muss Fehler weiterreichen und die gesamte Fachtransaktion zurückrollen. Eventtabelle und Modultabellen müssen transaktional sein; keine automatische Engine-Änderung. Der Integrationsnachweis zeigt das gemeinsame Zurückrollen von Fachänderung und Nachricht.

Beim Einreihen werden Adresse, Sprache, Vorlagenrevision, Platzhalterkontext und bereits gerenderte Texte dauerhaft festgehalten. Spätere Änderungen am aktiven Rennen oder an Vorlagen verändern diesen Stand nicht. Auflösung ausschließlich nach ausdrücklichem Event/Aktion/Sprache, ohne globalen oder sprachlichen Fallback. Empfänger-, Header-, Platzhalter- und Größenvalidierung vor dem Schreiben. Ein Einreihungsfehler hinterlässt keine halbe Nachricht.

## Zustände und Transport

| Zustand | Bedeutung und Fortsetzung |
|---|---|
| `pending` | Aktive Vorlage erfolgreich aufgelöst; für einen ausdrücklich freigegebenen Worker verfügbar |
| `blocked` | Eventvorlage fehlt; Kontext bleibt zur Prüfung erhalten, kein automatischer Versand nach späterer Konfiguration |
| `skipped` | Vorlage war beim Auslöser deaktiviert; keine nachträgliche automatische Aktivierung |
| `sending` | Ein Worker besitzt den Anspruch mit zufälligem Token; Versuch ist protokolliert |
| `sent` | Transport meldet Annahme; keine Zusage über Zustellung ins Postfach |
| `failed` | Transport meldet Ablehnung; nur ausdrückliche Wiederholung, höchstens drei Versuche insgesamt |
| `uncertain` | Ausnahme oder veralteter Anspruch; Annahme möglicherweise erfolgt, kein automatisches oder gewöhnliches Fehler-Retry |

Ein Anspruch wird atomar unter der gemeinsamen Sperre vergeben. Ein zweiter Worker kann dieselbe Nachricht nicht übernehmen; Abschluss erfordert passendes Event, Nachricht und Token. Der [Worker](../src/Infrastructure/WordPress/MailQueueWorker.php) verarbeitet pro Aufruf höchstens eine Nachricht und ruft den injizierten Transport erst nach Commit und Freigabe der Sperre auf. Ein äußerer offener Transaktionskontext verhindert die Verarbeitung. Die Freigabefunktion ist standardmäßig gesperrt und muss je Event ausdrücklich injiziert werden. Es existiert keine produktive Registrierung, kein Scheduler und kein Versandknopf für diesen Worker.

Nach 900 Sekunden kann eine begrenzte Aufräumaktion höchstens 50 veraltete Ansprüche auf `uncertain` setzen. Sie stellt sie niemals wieder auf `pending`. Ein später zurückkehrender alter Worker verliert seinen Abschlussanspruch. Transportausnahmen werden nicht mitsamt möglicherweise privaten Anbieterdetails ins Statusjournal geschrieben. Zwischen Mailannahme und Speicherung des Ergebnisses bleibt ein unvermeidbarer Unsicherheitsbereich; keine Exactly-once-Zusage für `wp_mail`.

Archive erlauben weder neue Nachrichten noch neue Ansprüche oder Fehler-Retries. Der Ausgang eines bereits laufenden Versuchs und die Erkennung veralteter Ansprüche dürfen noch protokolliert werden. Das verändert keine eingefrorenen Fach- oder Ergebnisdaten.

## Noch offene Anbindung

- Flüchtige Teamereignisse durch dauerhafte Fachvorgänge ersetzen und dieselbe Transaktion/Database mit der Queue teilen. Empfänger, Mitgliedersprache, Namen und eventbezogene Links müssen vor dem Commit bestimmt sein. Die Queue allein beweist diesen Produzentenpfad noch nicht.
- Bisherige `auto_response`-Aufrufer und manuelle Rundmails kontrolliert umstellen; alten Globalfallback erst mit dieser Anbindung entfernen. Absender-/Antwortadressen und Sprachwahl dabei erhalten und prüfen.
- Geschützte Eventverwaltung für Queuevorschau, Freigabe, explizite Fehlerwiederholung und gesonderte Klärung ungewisser Ausgänge ergänzen. Interne Methoden ersetzen keine Objektberechtigungs-, Nonce- und Revisionsprüfung an dieser zukünftigen Grenze.
- Verfahren für Aufbewahrung, Bereinigung und gezieltes erneutes Auslösen blockierter Nachrichten festlegen. Keine automatische rückwirkende Verarbeitung durch Vorlagenkopien oder Aktivierung.
- Vollständigen Produzenten-/Workerpfad mit parallelen Prozessen sowie Browser-/Tastaturabnahme prüfen. Anschließend Börse bearbeiten.

Ausgeführte Prüfungen und Grenzen: [verification.md](verification.md). Ausschließlich synthetische Daten und injizierte Transport-Doubles; kein echter Mailversand.
