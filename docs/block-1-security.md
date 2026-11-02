# Block 1 – Sicherheitsbasis

Stand: 2026-11-02. Dieses Paket sichert die vorhandenen Einstiegspunkte ab. Es ersetzt weder die fachliche Modernisierung der Folgeblöcke noch die Gesamtabnahme von Version 2.

## Schutzmaßnahmen und Abnahmezuordnung

| Bereich | Umsetzung | Ausführbare Belege |
|---|---|---|
| SEC-01 SQL und Sortierung | Vorbereitete Abfragen für Tokens, Börse, Tickerfilter und neue Objektprüfungen; dezimale IDs und feste Sortierspalten für Legacy-Abfragen. Keine Übernahme von Request-Text als Sortierausdruck. | SecurityBoundaryTest: ungültige IDs/Einladungscodes; reale Börsen- und Ticker-SQL-Aufrufe |
| SEC-02 Schreibende Requests | POST und aktionsgebundene Nonces für Verwaltung, Teilnehmeraktionen, Teamformular, erneuten Mailversand, Profil, Börse und Ticker. Alte Aktionslinks werden zu POST-Schaltflächen. Löschbestätigung bleibt erhalten. | GET, fehlende/falsche Nonce, fremde Sitzung, gültiger POST; tatsächliche Admin-Tabelle und Frontendfelder |
| SEC-03 Rechte und Objektgrenzen | Globale Bearbeitungs-/Löschrechte getrennt prüfen. Eigene Rechte beziehen sich auf die zugewiesene Route, nicht auf sämtliche Teams desselben Rennens. Sammelaktionen vor Verarbeitung vollständig prüfen. Elternobjekte und Rennzugehörigkeit validieren. | Globale/ eigene Rechte, fremde Route, gemischte Sammelaktion, Verschieben in fremdes/archiviertes Rennen |
| SEC-04 Ausgaben | Attributwerte und wiederholbare Eingaben escapen; öffentliche Plugin-Shortcodes zusätzlich mit HTML-Erlaubnisliste filtern. Tickertext und Karten-Popupdaten separat behandeln. Skript-/Eventattribute und fremde iframe-Quellen verwerfen; vorhandene Karten-, Video- und Betterplace-Embeds zulassen. | XSS-Nutzlasten in Feldern/Tabellen/HTML; vorhandene Shortcodes und Formularaktionen |
| SEC-05 Uploads und Eingänge | Gemeinsame Bildprüfung: echtes HTTP-Upload, Fehlerstatus, Dateigröße, Bildtyp/Abmessungen, zufälliger Dateiname. Tickerbilder über authentifiziertes WordPress-AJAX, Eigentum/Rennen/Uploadverzeichnis prüfen. Gateway verlangt signierten POST und verhindert Wiederholung. | Abgewiesene PHP/SVG-/Schein-Uploads und Pfade; gültiges synthetisches PNG mit Eigentumsmetadaten; signierter Eingang, falsche Signatur, abgelaufene Zeit, Replay |
| SEC-06 Wartung | Öffentlicher Rewrite-Shortcode schreibt nicht mehr. Ersatz unter Werkzeuge mit manage_options, POST/Nonce, Rennen, Probelauf, mehrdeutigen Zuordnungen und wiederholbarer Anwendung. | Verweigerter öffentlicher/fremder Aufruf; Probelauf, Anwendung, Wiederholung und Rennabgrenzung |

Zentrale Implementierungen: [Sicherheitsprüfungen](../includes/class-h3-mgmt-security.php), [Medienadapter](../includes/class-h3-mgmt-media.php), [Wartungswerkzeug](../includes/class-h3-mgmt-maintenance.php). Regressionen: [WordPress-Sicherheitstests](../tests/Integration/SecurityBoundaryTest.php), [Referenzintegration](../tests/Integration/LegacyWordPressTest.php), [JavaScript-Abläufe](../tests/security-js.test.cjs).

## Weitere unmittelbar nötige Korrekturen

- Börsen-Update und -Löschung prüfen ID **und** Eigentümer. Bestehende Nachrichten werden nicht umgeschrieben.
- Profilwerte werden vor dem ersten Schreiben vollständig geprüft. Abschnittsüberschriften haben keine Feld-ID. Kontolöschung benötigt eine eigene Nonce zusätzlich zum WordPress-Profilrecht.
- Eigene Teammitgliedschaft und das Ziel einer Einladung werden serverseitig ermittelt. Bestehende Mitglieder dürfen dabei keine fremden Einladungen verbrauchen. Rennphase, Mitgliedergrenze und Route werden geprüft; das Zielteam wird gesperrt und der Einladungscode nach Erwerb der Sperre erneut gelesen.
- Neue numerische Legacy-Einladungscodes verwenden random_int. Neue Tokenstruktur, Ablauf-/Verbrauchsjournal und Bestandsübergang bleiben Block 3/5.
- Reine GET-Aufrufe dürfen weder Teamstatus speichern noch Freigabemails senden. Ein fremder POST löst ebenfalls keine Neuberechnung aus. Ab Rennstart bleibt der gespeicherte Freigabestatus erhalten; Teilnehmerbestätigungen und Archivdaten sind geschützt.
- Ranglistenannahme prüft zuständige Route, Etappe 1..6, Wertebereiche, vollständige Teammenge und fremde Teamfelder. Öffentliche Speicherung läuft in einer Transaktion und rollt bei Fehlern zurück. Die historische Berechnung selbst bleibt Referenz für Block 6.
- Selbstmail verwendet die tatsächliche Benutzeradresse. Mailer verwirft ungültige Empfänger und Zeilenumbrüche in Headerwerten.
- Providerzugriffe laufen über die WordPress-HTTP-Schnittstelle mit HTTPS, Zeit-/Größenlimit und ohne Weiterleitungen. Fehler dürfen keine Bestätigung erzeugen.
- Der alte browsergesteuerte Zahlungsbestätigungspfad darf keine Zahlung als bezahlt markieren. Seine Status-2-/4-Schreibzweige sind gesperrt; der neuere vorhandene Betterplacepfad verlangt weiterhin serverseitige Bestätigung und Cookie-/Tokenzuordnung. Dies ist keine Freigabe eines zukünftigen Zahlungswegs.

## Geänderte Integrationsverträge

### Ticker-Gateway

Der vorhandene Shortcode bleibt registriert. Ein GET-Aufruf oder unsignierter POST wird abgewiesen. Der Absender benötigt HTTPS und ein außerhalb des Repositorys gesetztes Geheimnis COR_MGMT_TICKER_SECRET mit mindestens 32 Zeichen.

POST-Feld message enthält das bestehende JSON-Objekt oder eine einelementige Liste. Signiert wird der unveränderte JSON-Text **nach** der WordPress-Entfernung von Request-Slashes, nicht eine neu serialisierte Darstellung.

| HTTP-Header | Wert |
|---|---|
| X-Cor-Timestamp | Unix-Zeit in Sekunden; maximal 300 Sekunden Abweichung |
| X-Cor-Request-Id | Eindeutige 8..128 Zeichen aus Buchstaben, Ziffern, Unterstrich oder Bindestrich |
| X-Cor-Signature | Hexadezimales HMAC-SHA256 über timestamp + LF + requestId + LF + JSON, mit dem gemeinsamen Geheimnis |

Antworten: OK bei Speicherung; 400 für ungültige Nutzdaten/Bilder, 403 für fehlende/ungültige Authentifizierung, 409 für Wiederholung/Archiv, 500 bei Speicherfehler. Innerhalb des Zeitfensters darf eine Request-ID nur einmal gespeichert werden. Abgelaufene Marker werden bereinigt. Zeitformat Sekunden/Millisekunden wird explizit behandelt. Unbekannte oder unvollständige Teilnehmer bleiben wie bisher außerhalb des Rennfeeds (race_id=0).

Ohne abgestimmte Absenderumstellung bleibt der Eingang absichtlich gesperrt. Geheimnisse nicht in URL, Dokumentation, Testfixture oder Repository ablegen. Ein echter Gateway-/Produktionslauf wurde nicht ausgeführt; Protokollprüfung mit synthetischem signiertem Absender ist automatisiert.

### Bilder und ausgelagerte PHP-Dateien

Der Ticker nutzt admin-ajax.php mit action=cor_mgmt_upload_image, angemeldeter Sitzung, Rennen und Nonce. Bildrotation und Veröffentlichung akzeptieren nur registrierte eigene Bilder desselben Rennens innerhalb der Uploadablage. Verarbeitungsfehler liefern eine Meldung statt beliebige Dateipfade zu öffnen.

Die früheren Dateien uploads/ticker_images/process.php und uploads/Profile_Layer_Pics/upload.php gehören nicht zum Plugin-Repository. Für bestehende Installationen liegen funktionslose HTTP-410-Ersatzdateien unter [legacy-endpoints](../legacy-endpoints/ticker-images-process.php) und [Profilbild-Ersatz](../legacy-endpoints/profile-layer-upload.php). Beim Ausrollen müssen die alten öffentlichen Dateien ersetzt oder serverseitig gesperrt werden; ein bloßes Plugin-Update beseitigt sie nicht. Historische Bilder bleiben erhalten. Beide Ersatzdateien wurden in der Arbeitskopie eingesetzt; ursprüngliche Dateien wurden außerhalb des Webverzeichnisses gesichert.

Der zweite Eingang wird vom separaten overlayer_maker-Plugin über profile_layer verwendet. cor-mgmt ersetzt dessen Formular über einen Kompatibilitätsfilter und verarbeitet Bilder am geschützten WordPress-admin-post-Eingang. Anmeldung und Nonce sind erforderlich; das alte frei aufrufbare Uploadskript entfällt. Die bestehende Overlaygrafik VCA_ori.png bleibt während dieser Übergangsphase in ihrer bisherigen Ablage. Für diese optionale Funktion wird GD benötigt; fehlende Grafik/Bildverarbeitung liefert einen kontrollierten Fehler. Eigenständige Paketierung dieses Zusatzwerkzeugs und Medien-Gesamtabnahme gehören Block 9/10.

Theme und cor-ticker wurden in diesem Paket nicht geändert. Benutzerdefinierte Formulare oder Integrationen müssen die neuen POST-/Nonce-Verträge einhalten. Das vorhandene Theme-Profil nutzt bereits die WordPress-Profilnonce; der zusätzliche Kontolösch-Token wird über den vorhandenen profile_personal_options-Hook geliefert.

## Grenzen und nächste Blöcke

Keine Schemaumstellung, Historienkorrektur, Versionsanhebung oder Produktionsfreigabe. Gemischte Tabellen-Engines bleiben erhalten; Journal/Enginewechsel und umfassende Nebenläufigkeits-/Migrationsabnahme folgen in Block 3/5. Transaktionen schützen die beteiligten InnoDB-Datensätze; sie machen MyISAM-Daten nicht transaktional.

Die vollständige Phasenmatrix, differenzierte Organisatorausnahmen, neue Installation, alle fachlichen CRUD-/Medienabläufe, Bedienbarkeit und Browser-Gesamtabnahme bleiben bei ihren Fachblöcken. PHPUnit prüft echte WordPress-Grenzen; die JavaScript-Tests prüfen Ereignisse mit DOM-Doubles und sind keine Playwright-Browserabnahme. PHPStan/PHPCS/PHPCompatibility/ESLint und die Schichtenarchitektur folgen in Block 2. Ein allgemeines Fehlerfreiheitsversprechen für den Altbestand lässt sich daraus nicht ableiten.

Ausgeführte Versionen, Zähler und nicht ausgeführte Prüfungen stehen in [verification.md](verification.md).
