# Block 8 – Mailauslöser und Queueverwaltung

Stand: 2026-11-08. Dieses Paket verbindet die vorhandenen Teamereignisse mit der [Queue](block-8-queue.md) und ergänzt deren Verwaltung. Eventvorlagen sind damit für diese Benachrichtigungen wirksam. Manueller Rundmailversand und Börse bleiben eigenständige Folgearbeiten; kein vollständiger Block-8- oder Releaseabschluss.

## Fachvorgang und Nachricht gemeinsam speichern

[TeamManager](../src/Application/Teams/TeamManager.php) übergibt seine Ereignisse innerhalb der bestehenden Fachtransaktion an einen injizierten Empfänger. Die Fachklasse bleibt unabhängig von WordPress und Mailtransport. [TeamBoundary](../src/WordPress/TeamBoundary.php) verbindet sie mit [TeamNotifications](../src/Infrastructure/WordPress/TeamNotifications.php); beide verwenden dieselbe Database-Instanz wie das Teilnahmerepository. Auch Profiländerungen, administrative Bestätigungen und der Phasenabgleich verwenden diesen Weg.

| Vorgang | Nachrichten |
|---|---|
| Teamgründung | `team-creation` an den Gründer |
| Neue Einladung | `invitation` an die eingegebene Adresse, Sprache des einladenden Mitglieds |
| Einladungsannahme | `invitation-accepted-invitee` an das neue Mitglied und `invitation-accepted-inviter` an die bisherigen Mitglieder |
| Wechsel zur vollständigen Teilnahme | `publishable` an alle betroffenen Mitglieder |
| Erneute Bestätigung durch Teilnehmer | Eigener ausdrücklicher `team-creation`-Vorgang; Wiederholung desselben Formulars erzeugt keine weitere Nachricht |

Je Ereignis wird ein dauerhafter `team_event`-Eintrag angelegt. Seine Datenbank-ID ist die Vorgangsidentität für die nachfolgenden Empfängernachrichten. Kein nachträglicher Höchststand und keine zufällige Identität bei einem späteren Seitenaufruf. Empfängeradresse, Mitgliedersprache, Namen, Event-/Teamname und Link stehen beim Einreihen fest. Eine echte neue Freigabe nach vorherigem Statusverlust bleibt ein eigener Vorgang. Identische Wiederholung ohne Statuswechsel erzeugt keine weitere Freigabenachricht.

Fehlen Eventvorlagen, bleiben Nachrichten sichtbar `blocked`; der Teamvorgang kann trotzdem abgeschlossen werden. Deaktivierte Vorlagen erzeugen `skipped`. Es gibt keinen globalen, fremden Event- oder sprachlichen Fallback. Ein fehlender historischer Sprachwert wird als `und` ausgewiesen, nicht als geratenes Englisch versendet. Technische Einrichtungs- oder Renderingfehler rollen die gesamte Fachänderung einschließlich bereits eingefügter Nachrichten und Einladungstokens zurück. Queue-/Historientabellen werden vor dem ersten Journalwrite verifiziert.

Neue Einladungsvorgänge halten die Tokenreferenz als Hash im Ereignisjournal fest. Bei der Annahme kann damit der ursprüngliche Einladende ermittelt werden. Bei historischen Tokens ohne Sendernachweis nennt der entsprechende Platzhalter die übrigen Teammitglieder; es wird keine Benutzer-ID geraten. Der bisherige `%name%`-Wert in Einladungen bleibt der Name des Einladenden. Bei Nachrichten an registrierte Mitglieder bezeichnet er den konkreten Empfänger. Die übrigen bekannten Platzhalter bleiben verfügbar.

## Eventlinks und bisherige Einstiegspunkte

[TeamPortal](../src/WordPress/TeamPortal.php) registriert `admin_post_cor_mgmt_team` und dessen Gastvariante. Die Linkadresse enthält die konkrete Event-ID; der Einladungslink zusätzlich den Token. Sie nutzt die vorhandene native Teamdarstellung und deren POST-/Nonce-/Eigentumsprüfungen. Kein aktives Rennen und keine gerade geöffnete beliebige Inhaltsseite bestimmen das Ziel. Gastanmeldung führt zu diesem Event zurück. Ausgabe ohne Spezialtheme, mit Cache-/Indexierungs- und Referer-Begrenzung für diesen Einstieg; Browserabnahme bleibt erforderlich.

Der bisherige `TeamBoundary::notify()`-Aufruf bleibt als wirkungslose Kompatibilitätsmethode erhalten: Die Nachricht ist bereits vor dem Commit gespeichert. Erneutes Aufrufen versendet und erzeugt nichts. `H3_MGMT_Mailer::auto_response()` behält die Signatur, verweigert aber kontextfreie Aufrufe mit `false`. Der globale Laufzeitfallback und die veralteten generischen Texte sind entfernt. Externe eigene Writer müssen TeamManager samt Transaktionsanbindung verwenden; ein späterer Aufruf des alten Mailers genügt nicht mehr. Die globale Vorlagentabelle bleibt unverändert als ausdrückliche Kopierquelle erhalten.

Das bisherige Bestätigungsformular im Homework-Shortcode stellt nun einen geschützten Queueauftrag. Sein eigener Anfrageschlüssel schützt vor POST-Wiederholung. Lesezugriffe und reine Eignungsabfragen senden oder schreiben weiterhin nichts. Der separate manuelle Mailer `send()` und die vorhandenen Rundmailformulare wurden in diesem Paket nicht umgestellt. Keine neuen Zahlungsbenachrichtigungen oder Zahlungswege aktiviert.

## Verwaltung und kontrollierter Versand

Unter **E-Mails → Event mail queue** bietet [MailQueueAdmin](../src/WordPress/MailQueueAdmin.php) eine Eventauswahl, paginierte Liste, konkrete Empfänger-/Sprach-/Textvorschau, Vorlagenrevision, Status-/Versuchsverlauf und die letzten protokollierten Bedienentscheidungen. Vorschau ist Textausgabe mit Escaping, kein ausführbares Nachrichten-HTML. Interner Platzhalterkontext und Anspruchstoken werden nicht über die Verwaltungsansicht ausgegeben.

[MailQueueManagement](../src/Infrastructure/WordPress/MailQueueManagement.php) verlangt das bestehende Versandrecht und Sicht-/Bearbeitungsrechte am gewählten Event. Jede Änderung benötigt POST, Nonce und eine aktuelle Queue-/Konfigurationsrevision. Nachrichten-ID und Event-ID werden gemeinsam geprüft. Änderungen an Vorlagen, Nachrichten oder Bedienfreigaben machen alte Formulare ungültig, auch ein Aus-/Einschaltzyklus. Teamereignisse entwerten dagegen keine unabhängigen Vorlagenbearbeitungen.

- **Freigeben/pausieren:** eigenes Versandkennzeichen pro Event, standardmäßig pausiert. Der Schalter versendet nichts.
- **Einzeln senden:** nur die ausgewählte, geprüfte wartende Nachricht, nach ausdrücklicher Bestätigung. Kein Abgreifen einer anderen Nachricht und kein automatischer Hintergrundversand. Sperre und Transaktion sind vor dem Transport beendet. Der bisherige Absender `Tramprennen <no-reply@tramprennen.org>` und Klartext/UTF-8 bleiben erhalten.
- **Fehlversuch wiederholen:** nur `failed`, höchstens drei Versuche insgesamt, derselbe Inhalt. Einreihen und Senden sind getrennte Aktionen.
- **Fehlende Vorlage nachbereiten:** nur `blocked` ohne bisherigen Versuch; der gespeicherte Kontext wird nach ausdrücklicher Aktion mit der aktuellen aktiven Eventvorlage gerendert. Anschließend Vorschau und gesonderter Versand. Bereits gerenderte, deaktivierte oder versuchte Nachrichten werden nicht neu interpretiert.
- **Veraltete Ansprüche prüfen:** begrenzt auf 50; nur Übergang nach `uncertain`, kein automatischer Neuversand.
- **Unklaren Ausgang klären:** nach externer Prüfung Transportannahme protokollieren oder ohne weiteren Versand schließen. Kein gewöhnliches Retry für `uncertain`.
- **Schließen:** wartende/blockierte/deaktivierte/fehlgeschlagene/unklare Nachrichten bleiben mit Inhalt und Verlauf erhalten, werden aber nicht mehr versendet.

Unmittelbar vor dem Anspruch auf eine Einladung werden Phase, Tokenstatus, Event und freier Mitgliedsplatz erneut geprüft. Verbrauchte/widerrufene Tokens, volle Teams oder eine geschlossene Anmeldung verhindern den Versand. Ein Versand reserviert keinen Platz. Archive bleiben für neue Bedienänderungen gesperrt; bestehende laufende Versandausgänge bleiben protokollierbar.

Zusätzlich zur Eventfreigabe verlangt realer Versand die ausdrückliche Serverkonfiguration `COR_MGMT_ALLOW_MAIL_DELIVERY = true` und WordPress-Umgebungstyp `production`. Fehlt diese Freigabe, bleibt der Versand auch bei aktivem Eventschalter gesperrt. Dieses Paket setzt die Serverfreigabe nicht. Keine Cronregistrierung, keine Testmails beim Einrichten/Kopieren/Freigeben und keine rückwirkende Erzeugung aus alten Teamdaten.

## Einrichtung und Abnahme

Vorhandene Kommunikations- und Queue-Manifeste/SQL-Pläne bleiben unverändert. Bestehende Installationen richten fehlende Module ausdrücklich über den Systemstatus oder die Pluginaktivierung ein. Pro Event aktive Vorlagen und Sprachen prüfen bzw. kontrolliert kopieren; globale Vorlagen wirken nicht mehr direkt auf Teambenachrichtigungen. Unvollständige aktive Altvorlagen mit früheren generischen Texten benötigen eine fachliche Inhaltsprüfung vor der Übernahme.

Die automatisierten Nachweise umfassen den vollständigen Team-/Einladungsweg bis zum injizierten Transport, Transaktionsrollback, getrennte Events, Rechte, Nonces, Vorschau, Revisions-/Replay-Schutz, Blockierung und Nachbereitung, Retrygrenzen, unklare Ausgänge sowie zwei gleichzeitig laufende echte Workerprozesse. Der eigene Teilnehmer-/Gasteinstieg ist in getrennten WordPress-Prozessen gerendert. Kein echter SMTP-/Anbieterversand, keine Browser-/Tastatur-/Accessibility- oder Produktionsbestandsabnahme. Zahlen und Laufzeitgrenzen in [verification.md](verification.md).
