# Block 8 – Rundmails, Börse und technische Abnahme

Stand: 2026-11-08. Ergänzt die bereits implementierten [Eventvorlagen](block-8-templates.md), [Queue](block-8-queue.md) sowie [Teamauslöser und Verwaltung](block-8-delivery.md). Die unabhängig umsetzbaren Kommunikations-/Börsenarbeiten sind hier beschrieben. Prüfergebnisse und tatsächlich verbleibende Abnahmen stehen in [verification.md](verification.md).

## Manueller Versand

[Broadcasts](../src/Infrastructure/WordPress/Broadcasts.php) und [BroadcastAdmin](../src/WordPress/BroadcastAdmin.php) ersetzen das unmittelbare Rundmailformular unter E-Mails. Erhaltene Auswahl: eigene Adresse, konkrete Einzeladresse, Teilnehmer eines konkreten Rennens und alle bisherigen Teilnehmer. Ergänzt: Mitglieder eines ausgewählten Teams und optionaler Sprachfilter. Rennen und Teams werden namentlich mit ID angeboten; das aktive Rennen ist kein stiller Empfängerkontext. Der bisherige Einzeladress-Link mit `email` bleibt als Formularvorbelegung erhalten.

Vorschau löst tatsächliche Adressen, Mitgliedersprachen, verfügbare Platzhalter und Absender auf. Nachrichten bleiben Klartext; es gibt keine automatische Übersetzung. `%name%` bezeichnet den Vornamen; Event-/Teamgruppen stellen zusätzlich `%event_name%`, `%team_name%` und `%link%` bereit. Unbekannte oder nicht verfügbare Werte, fehlende Konten, ungültige Adressen und widersprüchliche Personalisierung blockieren die Auftragserstellung. Für alle bisherigen Teilnehmer werden Konten über wiederholte Mitgliedschaften dedupliziert; die Kontosprache ersetzt eine nicht eindeutig zuordenbare Event-Mitgliedersprache. Ein Filter `de` umfasst auch `de_de`, keine Übersetzung oder fremde Vorlage.

Eine Adresse bekommt nur dann eine Nachricht, wenn ihre ausgewählte Sprache und ihr gerenderter Inhalt eindeutig sind. Gleiche Adressen mit unterschiedlichen Namen/Inhalten werden zur Prüfung gemeldet. Keine stille Auswahl einer Person oder ihrer Eventzuordnung. Sicherheitsgrenze: maximal 10.000 aufgelöste Mitgliedschaftszeilen; größere Zielgruppen werden abgewiesen und müssen aufgeteilt werden. Die Vorschau zeigt Anzahl, Probleme und die ersten 20 Inhalte. Nach Erstellung sind sämtliche Snapshots paginiert einsehbar.

Vor Erstellung wird die Vorschau innerhalb der gemeinsamen Sperre erneut aufgelöst und ihr Fingerabdruck verglichen. Kontakt-/Mitgliedschafts-/Textänderungen erfordern eine neue Vorschau. Ein eigener Anfrageschlüssel verhindert doppelte Aufträge bei Wiederholung desselben Formulars. Absendername und -adresse, Empfängeradresse, Sprache, Betreff und Text werden unveränderlich gespeichert. Spätere Profil-/Vorlagenänderungen verändern bestehende Nachrichten nicht.

Eigener oder bisheriger generischer Absender sind möglich. [MailSender](../src/Domain/MailSender.php) verhindert Header-Injektion; [MessageText](../src/Domain/MessageText.php) ersetzt Platzhalter gleichzeitig und nicht rekursiv. Zustellbarkeit und Zulässigkeit persönlicher Absenderadressen beim tatsächlichen Anbieter bleiben eine gesonderte Abnahme; keine zusätzliche SMTP-Konfiguration wird vorausgesetzt oder eingerichtet.

## Queue, Freigabe und Rechte

Manuelle Kampagnen besitzen eine eigene Identität und technische Queue-Scope `race_id=0`, `action=manual`. Dies ist keine Veranstaltung mit ID 0. Event-Autoresponses bleiben ausschließlich an positive Event-IDs gebunden. Die ausdrückliche Kontaktaufnahme mit historischen Teilnehmern verändert weder archivierte Rennen noch ihre Vorlagen oder Ergebnisse. Neue automatische Archivbenachrichtigungen bleiben gesperrt.

Nachrichten werden in Schritten von höchstens 100 in die vorhandene Queue übernommen. Der Wiederholungsschlüssel enthält Kampagne und Empfängersnapshot. Nachricht, Absenderhülle, Empfängerzuordnung und Journal entstehen atomar; ein fehlerhafter Datensatz rollt den gesamten Schritt zurück. Bestehende Queue- und Kommunikations-Installationspläne bleiben unverändert.

Versand verlangt die bestehende Serverfreigabe `COR_MGMT_ALLOW_MAIL_DELIVERY === true`, WordPress-Umgebung `production` und eine ausdrücklich freigegebene Kampagne. Neue Aufträge sind pausiert. Zusätzlich wird der konkrete Einzelversand, der nächste Schritt mit maximal fünf Nachrichten oder ein Browserlauf für die noch offenen Nachrichten ausdrücklich bestätigt. Kein Scheduler und kein Start beim Laden der Seite. Ein bewusst gestarteter Browserlauf verarbeitet begrenzte Requests nacheinander; jeder Request prüft Rechte und Revision erneut. Der Stop-Schalter wartet auf den laufenden Schritt und verhindert den nächsten Request. Fehler, ungültige Antworten und unklare Transportausgänge beenden den Lauf ohne automatischen Retry. Ein Mengenfortschritt und ein Link zum erneuten Prüfen der Nachrichten bleiben sichtbar. Zwischen Schritten kann pausiert werden. Ein fehlgeschlagener oder unklarer Transport beendet den Schritt; Wiederholung ist eine gesonderte Entscheidung.

Transport erfolgt außerhalb von Sperre und Transaktion. Atomarer Anspruch verhindert konkurrierende Verarbeitung derselben Nachricht. Höchstens drei Versuche, keine automatische Wiederholung unklarer Ausgänge. Die Oberfläche bietet begrenzten Retry, Klärung abgelaufener Ansprüche, Abschluss ohne Versand und ausdrückliche Dokumentation einer extern überprüften Annahme. `sent` bedeutet Annahme durch den Transport, keine belegte Zustellung im Posteingang. Serverseitige Transport-Idempotenz wird nicht behauptet.

Alle Änderungen verwenden POST/Nonce und Revision; Akteur und Entscheidung werden journalisiert. Jeder Schritt prüft erneut das bestehende Mailrecht und bei Event-/Teamgruppen das konkrete Eventrecht. Globale Zielgruppen verlangen zusätzlich `manage_options`. Kampagnen sind dem Ersteller zugeordnet; Administratoren können fremde Aufträge nur bei ebenfalls passender Zielgruppenberechtigung verwalten. Eine fremde Kampagne kann keine Nachricht eines anderen Auftrags beanspruchen. Empfängersnapshot, Queue-Nachricht und Absenderhülle müssen derselben Kampagne zugeordnet sein; beschädigte oder fremde Verknüpfungen stoppen die Verarbeitung. Der AJAX-Einstieg ist nur für angemeldete Benutzer registriert und verlangt dieselben Rechte, Nonce, Revision und Versandbestätigung. Der Browser sendet ausschließlich an dieselbe Herkunft und folgt keinen Weiterleitungen. Alte unmittelbare `mail_send()`-Aufrufe liefern `false`, statt die Queue zu umgehen. Der niedrigere Mailtransport ist kein weiterer öffentlicher Formularpfad.

## Börse / HitchMate XChange

[Exchange](../src/Infrastructure/WordPress/Exchange.php) und [ExchangeBoundary](../src/WordPress/ExchangeBoundary.php) ersetzen die vermischte Verwaltung/Darstellung. Shortcode `h3-mate-xchange`, alte Klasse und vorhandene Eintrags-IDs bleiben erhalten. Die Börse bleibt global. Der öffentliche Jahresfilter und der Ausschluss von Personen mit Team im aktiven Rennen bleiben erhalten; Event-Autoresponses erzeugen keine neue Börsen-Eventbindung.

Native, beschriftete Formulare benötigen kein Spezialtheme. Öffentlich werden 30 Einträge je Seite angezeigt, stabil nach Zeit/ID sortiert. Eigener letzter Eintrag bleibt bearbeitbar, auch wenn der Jahres-/Teilnahmefilter ihn öffentlich ausblendet. Ein leerer Text löscht wie bisher den eigenen Eintrag. Historische Mehrfacheinträge werden nicht automatisch gelöscht; neue gleichzeitige Formulare erzeugen keinen zweiten eigenen Eintrag. Ausgaben werden maskiert, Kontaktangaben nur als ausdrücklich veröffentlichter Nachrichtentext gezeigt.

Schreibzugriff verlangt Anmeldung, POST/Nonce, Eigentum, maximal 10.000 Textbytes und eine geprüfte Revision. Gleichzeitige Änderungen und das Wiederverwenden alter Leerformulare nach Anlegen/Löschen werden abgewiesen. Bestandsformular-Aufrufer der alten `insertUpdateDeleteMessage()`-Signatur müssen zusätzlich `revision` aus dem neuen Kontext mitsenden. Fremde IDs bleiben für diese Kompatibilitätsmethode verweigerte Nulloperationen; Nonce-/Revisionsfehler liefern `false`.

Änderung und Auditjournal entstehen gemeinsam unter Sperre/Transaktion. Die bestehende `time`-Spalte behält ihre automatische Aktualisierung bei Bearbeitung; sie wird nicht nachträglich als unveränderlicher Erstellungszeitpunkt ausgelegt. MyISAM wird nicht bei einem Seitenaufruf konvertiert: Writer verlangen InnoDB und melden sonst den notwendigen kontrollierten Schemaabgleich. Leser bleiben ohne Journal-Schreibnebenwirkungen.

Werkzeuge → HitchMate XChange bietet ausdrücklich administrative Moderation mit `manage_options`, einschließlich älterer und verwaister Einträge. Moderation wird separat autorisiert und protokolliert; Administratorrechte erlauben keinen stillen Eigentümerwechsel. Die letzten Entscheidungen bleiben auch nach dem Löschen eines Eintrags sichtbar. Der öffentliche Pfad kann Moderationsrechte nicht durch zusätzliche Formularparameter einschalten.

## Einrichtung und Datenhaltung

Zwei additive Module, jeweils 1.0.0, mit unabhängigen Manifesten und SQL-Referenzen:

| Modul | Neue Tabellen | Einrichtung |
|---|---|---|
| [Rundmails](schema-broadcasts-1.0.0.sql) | Kampagnen, Empfängersnapshots, Absenderhüllen, Kampagnenjournal | Aktivierung oder ausdrückliche Systemstatus-Aktion |
| [Börsenjournal](schema-exchange-1.0.0.sql) | Änderungsjournal; bisherige Börsentabelle bleibt maßgeblich | Aktivierung oder ausdrückliche Systemstatus-Aktion |

Keine umfangreiche Migration während gewöhnlicher Seitenaufrufe, keine Änderung alter Storage-Marker, keine Löschung alter Tabellen, Originalvorlagen oder Nachrichten. Einrichtung allein erzeugt weder Kampagnen noch Versand. Das ZIP enthält fertigen Produktionsautoloader und SQL-Referenzen; die Browserwerkzeuge gehören ausschließlich zur Entwicklung.

Der geschützte [Speicherbericht](../src/WordPress/CommunicationReport.php) zeigt nur Mengen, geschätzten Platzbedarf und älteste Zeitstempel. Nachrichtentexte, Token oder Adressen werden nicht in diesen Bericht aufgenommen. Keine automatische Bereinigung und keine erfundene Aufbewahrungsfrist. Journalpayloads und Queue-Kontexte können personenbezogene Daten bzw. Einladungstoken enthalten; Aufbewahrung und Anonymisierung müssen diese Tabellen gemeinsam berücksichtigen.

## Bewusst erhaltene Grenzen und nächster menschlicher Schritt

| Benötigte Mitwirkung | Konkrete Entscheidung / Prüfung |
|---|---|
| Inhaltliche Abnahme | Event-/Sprachvorlagen, persönliche/generische Absender, Gruppen und Beispielvorschauen fachlich gegenprüfen; keine echten Empfänger für automatisierte Tests verwenden |
| Anbieterfreigabe | Kontrollierten echten Versand separat freigeben und Annahme/Zustellbarkeit sowie Verhalten persönlicher Absender prüfen; derzeit kein echter Versand durchgeführt |
| Aufbewahrung | Fristen für Inhalte/Adressen, Fehlversuche, Einladungskontexte und Journale sowie anonymisierende oder löschende Behandlung bestimmen; erst danach einen destruktiven Bereinigungslauf implementieren |
| Bestandsabnahme | Vorhandene Vorlagen/Sprachen, Gruppen und Börsentexte mit berechtigten Verantwortlichen vergleichen; keine automatische Umdeutung historischer Daten |
| Endgültige Bedienungsabnahme | Tatsächliche Texte, Darstellung im vorgesehenen Theme und Tastaturbedienung manuell bestätigen; automatische Prüfungen ersetzen diese Freigabe nicht |

Die übrigen historischen Vorlagenaktionen `package-paid`, `waiver-reached`, `new-sponsor`, `new-owner`, PayPal und Lastschrift bleiben als Daten/Kopierquelle erhalten. Die Quellprüfung in [Vorlagenbasis](block-8-templates.md) hat für diese Aktionen keine aktiven Auslöser nachgewiesen. Es werden deshalb keine neuen Gebühren-/Spenden-/Zahlungsbenachrichtigungen erfunden oder alte Zahlungswege aktiviert. Eine spätere fachliche Aktivierung verlangt einen bestätigten Auslöser samt atomarer Anbindung und eigenen Tests. Bestehende aktive Teamauslöser sind bereits angebunden.

Block 8 ist technisch bis zu diesen Mitwirkungs-/Freigabepunkten umgesetzt; keine pauschale Release- oder Produktionsfreigabe. Die nächste unabhängige Facharbeit ist Block 9 (Ticker/Medien).
