# Rollen, Einstiegspunkte und Integrationsverträge

Quellprüfung 2026-11-02. Keine neuen REST-Endpunkte implementiert. Literale Formular-/Requestfelder stehen vollständig im statischen [Aktionsinventar](actions.md); 23 Shortcodes mit Attributen/Handlern im [Shortcodeinventar](shortcodes.md). Alle 23 Registrierungen und ihre Aufrufbarkeit werden zusätzlich in echtem WordPress geprüft.

## Rollen und Objektgrenzen

| Akteur | Einstieg / Datenbereich | Ist-/Zielabgrenzung |
|---|---|---|
| Gast | Öffentliche Teams, Ticker/Karten, Ranglisten, Börsenliste, Spendenablauf | Anzeigen dürfen im Ziel nicht schreiben. Spenden benötigen sitzungs-/tokenbezogenen Schutz; keine pauschale Loginpflicht erfinden |
| Teilnehmer | Dashboard, Profil, eigene Börsennachricht, Teameinladung, Ticker | Eigene Benutzer-/Team-/Rennzuordnung serverseitig prüfen; Nonce allein reicht nicht. Aktuelle SQL-/Eigentumslücken in analysis.md |
| Routenverantwortlicher | Ergebniseingabe anhand routes.user_id | Objektbezogene Bindung an Route und Rennen, keine bloße Rolle. Legacy prüft aktive Race-Userliste, anschließende Routensuche ist nicht rennbezogen |
| Organisator/Admin | Menüs und Fachverwaltung | 18 Custom-Caps; ohne Members beim init an Administrator vergeben. Menüs unterscheiden Sicht-, Mail- und Vorlagenrechte, ersetzen keine Handlerrechte. Teilnehmerausnahmen gemäß Phasenvertrag, keine pauschalen Event-/Archiv-Overrides |
| Maschine/Gateway | Ticker-JSON, Providerbestätigung, Uploadpfade | Eigenständige Authentifizierung, Größen-/Typ-/Wiederholungsprüfung erforderlich; derzeit nicht als sicher nachgewiesen |

Die exakten 18 Capabilities liefert [extra_caps](../includes/class-h3-mgmt.php#L60); die Namen sind auch in features.md erfasst. Tests prüfen tatsächliche Administrator-/Subscriber-Zuweisung und Menücaps. Custom-Rollen und Routenobjektberechtigungen benötigen die negativen Tests aus Block 1.

## WordPress-Hooks

| Hook / Lebenszyklus | Handler / Bedeutung | Nachweis |
|---|---|---|
| init | H3_MGMT::init, Sprachinitialisierung, Capabilities, globale Module/Shortcodes | Bootstrap wird real geladen; init-Methode real ausgeführt. Testfixture installiert WordPress vor Plugininitialisierung, kein vollständiger HTTP-Aktivierungslauf |
| plugins_loaded | h3_mgmt_update_db_check | Quellprüfung; absichtlich keine v2-Migration beim Rendern |
| wp_enqueue_scripts / admin_enqueue_scripts | Front-/Adminassets einschließlich Leaflet, Bilder/Styles/JS | Quellprüfung; vollständige Browser-/Assetsprüfung in Modul-/Releaseabnahme |
| show_user_profile / edit_user_profile | Profilanzeige | Reale Hookregistrierungen getestet |
| personal_options_update / edit_user_profile_update | Profilspeichern | Reale Registrierung und edit_user-Fremdschutz getestet; Abschnittsfehler charakterisiert |
| admin_menu | Menüs aus H3_MGMT_Admin | Reale Menüregistrierung und unterschiedliche Caps getestet |
| members_get_capabilities | Optionale Integration, sonst direkte Admincaps | Quellprüfung; Members-Plugin nicht Voraussetzung der Tests oder Zielinstallation |
| embed_privacy_has_embed | Beim Kartenrendern auf true gesetzt | Quellprüfung; Karten-/Privacyadapter in 9/10 abstimmen |
| Aktivierung / Uninstall | h3_mgmt_install / h3_mgmt_uninstall | Quellprüfung der gefährlichen Legacy-Routinen; v2-Lebenszyklus in 2/3/10 neu prüfen |

## Benachbarte Komponenten und externe Grenzen

| Komponente | Beleg / Vertrag | Folgemaßnahme |
|---|---|---|
| cor-theme | profile-form.php prüft H3_MGMT_ABSPATH und bindet templates/frontend-form.php direkt ein; Theme übernimmt zusätzlich Anmeldung/Datenschutz/Layout | Eigene Formular-/Anmeldeoberfläche im Plugin, Adapter statt impliziter Pflicht; getrennte Themeänderungen dokumentieren |
| cor-ticker | Gemeinsame Tabellen sms_ticker und sms_ticker_status | Daten-/ID-/Typvertrag erhalten; neue eigene Verwaltung und optionaler Adapter. Keine parallelen unkoordinierten Writer |
| js/app.js → uploads/ticker_images/process.php | HTTPS aus window.location.hostname, fester Uploadpfad; umgeht WP-Pluginrouter | Geschützter Medienendpunkt im Plugin, Uploadgröße/MIME/Eigentum prüfen; Aufrufer und alten Eingang getrennt migrieren |
| Profile_Layer_Pics | Historischer ausgelagerter Endpunkt, aktuelle fachliche Verwendung nicht abschließend nachgewiesen | Nicht blind löschen; vor Medienumstellung Nutzung ermitteln |
| Betterplace | Clientcookie/Token, Fundraising-ID aus URL, API-Rückgabe, submitted-Schritte; ältere Weiterleitung und donation_check zusätzlich vorhanden | Erst fachliche Zahlungsprüfung in 7; Fehler/Timeout/Bestätigung/Wiederholung simulieren, Historie behalten |
| PayPal / Lastschrift | Historische Methoden, Verwaltungsfilter/-aktionen und Mailvorlagen | Archiv erhalten; keine Freigabe neuer Zahlungen aus Bestandsfunktion ableiten |
| WPML / Members / Avatar / ThemeMyLogin / Embed Privacy | Sprachkonstante, Capfilter, Avatarmetafeld, Loginshortcode, Kartenfilter | Optionale Adapter und plugininterne Fallbacks; Abwesenheit muss v2-Installation ermöglichen |
| Leaflet / Imagick | Kartenfrontend bzw. bestehende Bildrotation | Bibliothek nicht ohne Grund ersetzen; Bildrotation optional und mit klarer Voraussetzungenprüfung umsetzen |

Gateway-Payload und Nachrichtentypen sind im [Referenzkatalog](reference-catalog.md) beschrieben. Der leere Eingang und die lokale Nummernlogik sind getestet; der tatsächliche externe Gatewayvertrag ist in Block 9 zu verifizieren. Keine Produktionsanfragen für diese Referenzprüfung.

## Historie und Übergang

IDs, Benutzerzuordnungen, Sprachdaten und unbekannte Metadaten erhalten. Bekannte verwaiste Beziehungen stehen in migration.md; keine automatische Bereinigung. Vorhandene Phase 3 wird bei Bestandsmigration ohne Neuberechnung archiviert. Shortcodes, Attributnamen und benötigte Integrationspfade bleiben bis zu einem dokumentierten Ersatz bestehen. Sicherheitsfehler und öffentliche Wartungsaktionen erhalten geschützte Nachfolger, keinen unverändert offenen Kompatibilitätseingang.

## Sicherheitsverträge ab Block 1

POST-/Nonce-/Objektprüfungen, authentifiziertes Medien-AJAX, HMAC-Gateway und Ersatz der Uploadskripte gemäß [Sicherheitsbasis](block-1-security.md). Die frühere unbekannte Verwendung von Profile_Layer_Pics ist jetzt auf das separate overlayer_maker-Plugin zurückgeführt: profile_layer erhält einen geschützten WordPress-Formularadapter. Die alte Upload-URL bleibt gesperrt. Theme und cor-ticker wurden nicht geändert. Diese Bestandsadapter ersetzen keine vollständige Gesamtabnahme der Nachbarplugins.
