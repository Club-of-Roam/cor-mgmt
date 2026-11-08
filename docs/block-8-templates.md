# Block 8 – Erstes Teilpaket: eventbezogene Vorlagen

Aktueller Nachtrag 2026-11-08: [Teamauslöser und Queueverwaltung](block-8-delivery.md) sind inzwischen angebunden; Eventvorlagen steuern diese Nachrichten. Die weiter unten beschriebene Grenze ohne Versandwirkung bezeichnet den früheren Abschluss dieses Vorlagen-Teilpakets.
Stand: 2026-11-08. Umsetzung der Vorlagenbasis aus [Plan](block-8-communication-plan.md), noch kein Abschluss von Block 8.

## Umfang und wichtige Übergangsgrenze

Eigene Vorlagen je Veranstaltung, Aktion und Sprache sind implementiert. Neue Verwaltungsseite „Event autoresponses“ im E-Mail-Bereich: Eventwahl, Sprachwahl, einzelne Vorlagen anlegen/ändern, aktivieren/deaktivieren, vorhandene Konfiguration anzeigen und fehlende Vorlagen kontrolliert aus einem anderen Event oder dem globalen Bestand kopieren. Jede Kopie ist unabhängig. Neue Events beginnen ohne fremde Vorlagen; ungefüllte Vorlagen sind nicht automatisch aktiviert.

**Diese Konfiguration bereitet die Versandumschaltung vor und steuert den bestehenden Versand noch nicht.** Der bisherige Mailer benutzt weiterhin globale Vorlagen; beide Verwaltungsseiten benennen diese Grenze sichtbar. Dieses Teilpaket enthält keinen neuen Mailtransport, keine Queue-Verarbeitung und keine automatische Benachrichtigung. Es ist kein deploybarer Abschluss der Eventtrennung. Als nächstes müssen die Auslöser mit explizitem Eventkontext an eine kontrollierte Warteschlange angeschlossen und der globale Laufzeitfallback entfernt werden. Keine Anwendungskopie wurde eingerichtet oder umgeschaltet.

## Bestandsaufnahme

Quellcodeprüfung: [Mailer](../includes/class-h3-mgmt-mailer.php), [E-Mail-Verwaltung](../admin/class-h3-mgmt-admin-emails.php), [Teilnahmebrücke](../src/WordPress/TeamBoundary.php), [Börse](../includes/class-h3-mgmt-xchange.php).

| Aktion | Zweck / aktueller Bezug | Weiteres Vorgehen |
|---|---|---|
| team-creation | Bestätigung nach Teamgründung; TeamBoundary und alter Homework-Pfad | Team-/Renn-ID und einmaligen Gründungsvorgang mitführen |
| invitation | Einladung an E-Mail-Adresse mit Code und Link | Event über Einladung/Team; einmaliger Einladungsvorgang |
| invitation-accepted-invitee | Beitrittsbestätigung an neues Mitglied | Event und dauerhafte Mitgliedschafts-/Annahmeidentität verwenden |
| invitation-accepted-inviter | Nachricht an bisherige Mitglieder nach Annahme | Empfänger und jeweilige Sprache ereignisbezogen erfassen |
| publishable | Nachricht nach erfüllten Teilnahmevoraussetzungen | Wiederholte echte Statuswechsel von Wiederholung desselben Vorgangs unterscheiden |
| package-paid, waiver-reached | Vorlagen für Teilnehmergebühr und Haftungsverzicht | Von Spenden unterscheiden; Aufruf-/Statusübergänge beim Versandumbau vervollständigen |
| new-sponsor, new-owner | Vorlagen für Teamnachrichten bei Spenden | Neuer Spendenpfad sendet noch nicht; nur bestätigte Fachereignisse anbinden |
| paypal-please-owner, paypal-please-sponsor, paypal-thanks | Historische PayPal-Anfragen/Bestätigung | Texte erhalten; keine Freigabe neuer PayPal-Zahlungen |
| debit-thanks-owner, debit-thanks-sponsor | Historische Lastschrifttexte | Texte erhalten; kein neuer Lastschriftprozess |

Die Quellsuche umfasst src, admin, includes, templates, models, runtime und legacy-endpoints: fünf Autoresponse-Aufrufe in TeamBoundary, ein weiterer im alten Team-Homework-Pfad und der manuelle Versand über mail_send. Für die übrigen katalogisierten Aktionen wurden dort keine direkten aktuellen Aufrufe gefunden; vorhandene Vorlagen sind kein Nachweis eines aktiven Auslösers. In den geprüften Themes/cor-ticker wurden keine zusätzlichen Aufrufer dieser Plugin-API gefunden. Enfold besitzt eigene Kontaktformular-Aufrufe von wp_mail; diese sind keine Plugin-Autoresponses und werden nicht stillschweigend übernommen.

Alle 14 Aktionsnamen stimmen zwischen globalem Editor und Mailer überein. Bestehende Platzhalter: name, names, team_name, link, code, inviter, invitee, donation, thumbs. Neu vorbereitet: event_name. Vorlagen sind wie bisher Klartext. Alte Leertexte können im Mailer auf fest codierte Inhalte mit TR15/2014/2015-Bezug zurückfallen; solche aktiven Leertexte werden nicht blind übernommen. Die Vorprüfung fordert eine Inhaltsprüfung.

Manueller Versand kennt self, all und race-ID; Benutzer-IDs werden teilweise dedupliziert, Mailadressen im Transport noch nicht. Globale Standard-Absenderdaten und optionaler eigener Absender bleiben Bestandsverhalten. Der Self-Zweig liest aktuell user_email als Eigenschaft; ältere Referenzbefunde dazu sind historisch. Zustands-/Sprach-/Adressauflösung und Versandstatus müssen bei der Queue-Umstellung zentralisiert werden. Die Übernahme eines einzelnen Formularadressaten ist gesondert zu prüfen; die aktuelle Dispatch-Unterscheidung deckt ausdrücklich self/all/race ab. Für allgemeine WordPress-Systemmails keine Eventzuordnung erfinden.

Die Börse hat eigene Nachrichten mit Eigentumsprüfung und einen Jahresfilter. Die Anzeige benutzt das aktive Rennen nur zur Erkennung bereits zugeteilter Teilnehmer; Nachrichten besitzen keine eigene Eventzuordnung. Parallelbetrieb ist deshalb auch dort gesondert zu prüfen, ohne D-44 automatisch auf eine neue Börsen-Fachregel auszudehnen. In diesem Teilpaket wurde die Börse nicht geändert.

## Modell, Rechte und Übernahme

Kommunikations-Vorlagenmodul 1.0.0 mit eigenem Manifest, unabhängig von Pluginversion, Legacy 2.8, Storage 3.0.0 und anderen Fachmodulen. [SQL-Referenz](schema-communication-1.0.0.sql), [Schema](../src/Infrastructure/WordPress/Schema/CommunicationSchema.php). Zwei additive Tabellen: cor_mgmt_response_templates mit eindeutigem Primärschlüssel race_id/action/language, Aktivierung, Betreff, Nachricht, Revision und Änderungszeit; cor_mgmt_communication_history mit Event, Akteur, Zeit, Aktion und Vorher-/Nachherwerten bzw. Kopiernachweis. Einrichtung ausdrücklich unter Systemstatus oder bei Aktivierung; keine DDL während normaler Anzeigen. Alte Tabellen und Marker bleiben erhalten. Queue-Schema erst mit dessen Umsetzung hinzufügen, diesen fertigen Installationsplan danach nicht stillschweigend verändern.

[AutoResponseTemplate](../src/Domain/AutoResponseTemplate.php) validiert Schlüssel, Textgrenzen und bekannte Platzhalter, normalisiert Sprachvarianten wie de-DE/de_DE auf de_de und ersetzt Werte ohne rekursive Interpretation. Headerzeilenumbrüche und Nullbytes werden zurückgewiesen. Kein sprachlicher, globaler oder fremder Eventfallback. Fehlende Platzhalterwerte liefern einen Fehler statt erfundener Werte; die render-Methode versendet nichts.

[ResponseTemplates](../src/Infrastructure/WordPress/ResponseTemplates.php) verwaltet Konfiguration mit injizierter Uhr und Objektberechtigung. [CommunicationAdmin](../src/WordPress/CommunicationAdmin.php) verlangt für Änderungen POST/Nonce, bestehendes Autoresponse-Recht und Bearbeitungsrecht für das ausgewählte Event. Globaler Quellbestand ist nur für Administratoren mit manage_options übernehmbar; andere Events benötigen deren Sichtrecht. Archive bleiben lesbar und als Kopierquelle nutzbar, aber unveränderlich. Sprache allein oder das aktive Rennen genügt niemals als Schlüssel des neuen Resolvers.

Vor Änderung vollständige Eventrevision vergleichen: Veranstaltungseinstellungen, Vorlagen und Journalgeneration. Gemeinsame Sperre und Transaktion sichern Änderungen und Journal; MyISAM wird nicht automatisch umgestellt. Identische Wiederholung schreibt keinen neuen Eintrag.

Kopieren verlangt zunächst eine schreibfreie Vorschau: alle Sprach-/Aktionsschlüssel, Inhalte und Übernahmestatus, anschließend ausdrücklichen POST mit Fingerabdruck des geprüften Quell-/Zielstands. Doppelte Quellschlüssel einschließlich Sprachaliasen, unbekannte Aktionen/Platzhalter, unvollständige aktive Texte und ungültige Schalter blockieren den gesamten Lauf. Änderungen nach Vorschau machen die Freigabe ungültig. Bereits bestehende Zielvorlagen bleiben erhalten, auch wenn sie abweichend bearbeitet wurden. Wiederholung mit frischer Vorschau erzeugt keine Duplikate und keine unnötigen Journale. Globale Originale bleiben bytegleich; kein Kopierlauf versendet Mails.

## Fortsetzung und Abnahme

- Benachrichtigungsereignisse dauerhaft mit Event, Team, Empfänger, Sprache und fachlicher Vorgangsidentität erfassen; kein flüchtiger Rückgriff auf spätere Zustände.
- Rendering und gewählte Vorlagenrevision in einem dauerhaften Outbox-/Queue-Snapshot fixieren, Produzent und Fachänderung atomar verbinden.
- Autoresponse-Einstiegspunkte auf diesen Kontext umstellen, globale Laufzeitauflösung entfernen; vorhandene API-Aufrufer gesondert dokumentieren.
- Kontrollierter Worker mit Anspruch, begrenztem Retry und Behandlung unklarer Transportausgänge; keine Exactly-once-Zusage gegenüber einem nicht idempotenten Mailtransport.
- Manuellen Versand und anschließend Börse modernisieren. Absender-/Antwortadressen und Empfängerauswahl abnehmen.
- Browser-/Tastatur-/Accessibility- und Bestandsabnahme sowie Paralleltests für die Worker ergänzen.

Ausgeführte Prüfungen und Grenzen: [verification.md](verification.md). Vorlagentests belegen Eventtrennung und Konfigurationsmigration; daraus folgt keine fertige Versand- oder Queue-Abnahme. Keine echten Mails, keine produktiven Schnittstellen, keine Git-Aktionen.


Nachtrag 2026-11-08: Die [Queue-Grundlage](block-8-queue.md) ergänzt das Rendering und die Workerzustände. Die oben beschriebene offene Auslöseranbindung und Umschaltung bleibt weiterhin erforderlich; dieser Nachtrag ändert keine bisherigen Vorlagen oder Mailauslöser.
