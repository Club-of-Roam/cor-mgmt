# Block 7 – Verwaltung, Anzeigen und offene Altvorgänge

Stand: 2026-11-05. Ergänzt [Betterplace-Grundlage](block-7-donations.md); keine Anbieter- oder Releasefreigabe.

## Geschützte Verwaltung

Unter Sponsoren steht „Profiles and pending reviews“ zur Verfügung. Die native Liste umfasst neue modulverwaltete Spenden und offene historische Betterplace-Einträge, jeweils in begrenzten Seiten. Einzelansicht mit exaktem beobachtetem Centbetrag, aktuellem Profil und letzten Journalereignissen. Neue Profile können mit Namen, Nachricht, Sichtbarkeit und bei Ownern Website gepflegt werden. Die bestehende Bildreferenz lässt sich entfernen; freies Eintragen einer Bild-URL ist ausgeschlossen. Ein Bildwechsel bleibt über das kontrollierte Spenderformular möglich; keine historische Mediendatei wird gelöscht.

[DonationAdmin](../src/WordPress/DonationAdmin.php) verlangt für Änderungen POST und Nonce. [DonationAdministration](../src/Infrastructure/WordPress/DonationAdministration.php) prüft zusätzlich die bestehenden objektbezogenen Sponsor-Rechte: globale Rechte oder eigenes zugewiesenes Routenteam. Sichtrecht ist kein Schreibrecht. Archive bleiben auch für Administratoren unveränderlich. Profilpflege ist in Phase 0..3 auch bei ausgeschalteter Spendenannahme möglich, ohne die Annahme wieder zu öffnen.

Betrag, Zahlungsstatus, Nachweis, Methode, Team- und Rennzuordnung sind keine bearbeitbaren Profilfelder. Ein unbestätigter Eintrag darf nicht veröffentlicht werden. Ein-/Ausblenden ist einzeln über das Profil und gesammelt für höchstens 50 verwaltete Einträge möglich. Jede Zeile benötigt ihre aktuelle Revision; Datensatz, Journalgeneration, Rennkonfiguration und Teamzugehörigkeit gehen in die Prüfung ein. Alle Berechtigungs-/Revisionsprüfungen erfolgen vor den ersten Writes unter gemeinsamer Sperre. Fehler rollen Änderungen und Journal zusammen zurück. Identische Wiederholung erzeugt keinen weiteren Profileintrag. Ein administrativ verstecktes Profil wird durch spätere Spenderpflege nicht automatisch erneut veröffentlicht.

Journal: handelnder Benutzer, Zeit, geänderte Felder mit Vorher-/Nachherwerten. Die Übersicht zeigt Ereignisse, keine Sitzungs-Cookies oder öffentlichen Zuordnungscodes. Alte Admin-Aktionen für verwaltete Zeilen einschließlich Sichtbarkeit sind gesperrt und verlinken den kontrollierten Editor. Historische Zeilen werden nicht durch neue Zahlungsregeln umgedeutet.

## Geldanzeige und Statistik

**Bestätigte Anzeigevorgabe:** Im Frontend weiterhin ganze Euro; Nachkommastellen werden entsprechend dem bisherigen Ganzzahlverhalten abgeschnitten. Gespeicherte neue Centbeträge, interne Abfragen und die Verwaltung behalten den exakten Wert. Summen entstehen aus exakten Beträgen; erst die Darstellung schneidet ab. Dies gilt für Zuordnungsbestätigung, jüngste Spenden, Teamhinweise, öffentliche Euro-Gesamtsumme und Spendenzähler. Thumbs/CPK sind eigene abgeleitete Größen und keine Euroanzeige.

[DonationAmounts](../src/Domain/DonationAmounts.php) kapselt exakte Summen-/Offsetdarstellung. Die Legacy-Zählkonvention bleibt erhalten: Lastschrift zählt historisch anders als PayPal/Betterplace; Filter einer Liste verändern nicht rückwirkend die alten Summenregeln. Der bereits berechnete, aber zuvor fehlende `race`-Zähler wird nun ausgegeben. Optionale Zusatzspalten erzeugen keine ausgefilterten unbezahlten Zeilen mehr. Historische Datensätze werden dafür weder geändert noch konvertiert.

Team-/Sponsorreader überlagern neue Beträge aus dem Centmodul; öffentliche Ausgabe kürzt erst anschließend. Der Spendenzähler zeigt auch ohne JavaScript einen ganzen Eurobetrag und schreibt beim Anzeigen keine Optionswerte mehr. Bereits gespeicherte Zähleroptionen werden nicht gelöscht. Abgeleitete Animationen bleiben Darstellung, keine finanzielle Berechnung.

Quellprüfung der PHP-Dateien in cor-theme, Enfold und cor-ticker: keine zusätzlichen direkten Sponsor-Betrags-/SQL-Konsumenten über die geprüften Namen gefunden. Keine Änderungen an diesen Repositories. Inhalte aus der WordPress-Datenbank und eine vollständige Browserabnahme sind damit nicht geprüft; neue externe Konsumenten müssen den Reader-/Darstellungsvertrag verwenden.

## Kontrollierte Vorbereitung alter offener Vorgänge

Die Einzelansicht bietet eine schreibfreie Vorprüfung und ausdrücklich „Prepare review“. Zulässig sind unbestätigte historische Betterplace-Einträge ohne Modulzuordnung mit gültiger Team-/Rennbeziehung, Phase 0..3 und gültiger Betterplace-Veranstaltung. Falsche Methode, bestätigte oder verwaiste Zeilen und Archive werden abgewiesen. Die Vorbereitung speichert nur einen Prüfplan im Journal: Ursprungsfingerabdruck, Veranstaltung, Vorhandensein eines Altcodes, ungeklärte historische Einheit und ausstehender Anbieterabgleich. Identische Vorbereitung ist wiederholbar. Änderungen der Konfiguration oder Daten verlangen eine neue aktuelle Prüfung.

Der Bearbeitungsstand wird angezeigt. Die Legacy-Zeile bleibt unverändert; es entsteht weder ein neuer Modulvorgang noch eine neue Zahlungsbestätigung oder Browserberechtigung. Alte Codes und Cookies werden nicht in den Plan übernommen oder ausgegeben. Die Übernahme bereits beim Anbieter bezahlter Altvorgänge setzt zunächst den Nachweis des ursprünglichen Zuordnungs-/Betragsvertrags voraus. Diese fachlich noch unbelegte Übernahme ist ausdrücklich kein automatisierter Bestandteil der Vorbereitung.

## Nachweise und Grenzen

Ausgeführte Matrix und Prüfungen in [verification.md](verification.md). Neue Regressionen decken Rechte, eigene/fremde Routen, Nonces, unveränderliche Finanzfelder, konkurrierende Revisionen, Sammelrollback, Journalfehler, Archive, Sichtbarkeit, exakte interne Summen bei ganzer Euroanzeige sowie unveränderte Altvorgänge ab.

Weiter offen: Anbieterabnahme, vollständiger Bestandsabgleich und tatsächliche Übernahme nachgewiesener alter Zahlungen, API-/Webhook-/Rückerstattungsautomatisierung, positiver Browser-/Upload-/Tastatur-/Accessibility-Nachweis und vollständiger Archivsnapshot. Keine echten Zahlungsaufrufe und keine Anwendungsmigration für diese Prüfung.
