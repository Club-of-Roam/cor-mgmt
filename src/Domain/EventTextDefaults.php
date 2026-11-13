<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Initial editable information texts, never a backfill for existing events. */
final class EventTextDefaults
{
    /** @return array<string,string> Legacy text ID plus explicit language. */
    public static function all(): array
    {
        $texts = [
            11 => ['Choose an available route once your team meets the participation requirements. Incomplete teams do not occupy a route place.', 'Wählt eine verfügbare Route, sobald euer Team die Teilnahmevoraussetzungen erfüllt. Unvollständige Teams belegen keinen Routenplatz.'],
            16 => ['Read the liability waiver provided by the event organizers and follow their instructions for confirming acceptance.', 'Lies den von der Organisation bereitgestellten Haftungsverzicht und befolge die Hinweise zur Bestätigung.'],
            17 => ['Follow the payment instructions provided by the event organizers. Your participation fee must be confirmed by the organizers.', 'Beachte die Zahlungsinformationen der Organisation. Deine Teilnahmegebühr muss von der Organisation bestätigt werden.'],
            18 => ['Your team meets the participation requirements. Choose an available route and keep your team profile up to date.', 'Euer Team erfüllt die Teilnahmevoraussetzungen. Wählt eine verfügbare Route und haltet euer Teamprofil aktuell.'],
            19 => ['Continue to Betterplace to support a team. Follow the instructions after your donation to associate it with the team. Returning to this page alone does not confirm payment.', 'Unterstütze ein Team über Betterplace. Folge nach deiner Spende den Hinweisen zur Zuordnung zum Team. Die Rückkehr auf diese Seite allein bestätigt keinen Zahlungseingang.'],
            20 => ['Support this team through Betterplace. Confirmed contributions can be shown in the team profile.', 'Unterstütze dieses Team über Betterplace. Bestätigte Beiträge können im Teamprofil angezeigt werden.'],
            21 => ['TeamOwner support depends on the event settings and availability. Check the donation instructions before continuing.', 'Eine Unterstützung als TeamOwner richtet sich nach den Event-Einstellungen und der Verfügbarkeit. Beachte vor dem Fortfahren die Spendenhinweise.'],
            22 => ['Team registration has not opened yet. Please return when registration opens.', 'Die Teamanmeldung ist noch nicht geöffnet. Bitte schau zum Anmeldestart wieder vorbei.'],
            23 => ['Ticker submissions are disabled for this event.', 'Ticker-Eingaben sind für dieses Event deaktiviert.'],
            24 => ['Donations are currently disabled for this event.', 'Spenden sind für dieses Event derzeit deaktiviert.'],
            25 => ['Team registration has not opened yet. Teams will appear here once registration opens and they meet the participation requirements.', 'Die Teamanmeldung ist noch nicht geöffnet. Teams erscheinen hier nach Anmeldestart, sobald sie die Teilnahmevoraussetzungen erfüllen.'],
            26 => ['The race has not started yet. Results will appear here after the start.', 'Das Rennen hat noch nicht begonnen. Ergebnisse erscheinen hier nach dem Start.'],
            27 => ['Ticker display is disabled for this event.', 'Die Ticker-Anzeige ist für dieses Event deaktiviert.'],
            28 => ['Have your participation fee confirmed by the event organizers.', 'Lass deine Teilnahmegebühr von der Organisation bestätigen.'],
            29 => ['Your teammate must have their participation fee confirmed by the event organizers.', 'Dein Teammitglied muss die Teilnahmegebühr von der Organisation bestätigen lassen.'],
            30 => ['Invite your teammates by email. Invitations do not reserve member places.', 'Lade deine Teammitglieder per E-Mail ein. Einladungen reservieren keine Mitgliedsplätze.'],
            31 => ['Invitations can be accepted until registration closes, provided a member place is still available.', 'Einladungen können bis zum Anmeldeschluss angenommen werden, sofern noch ein Mitgliedsplatz frei ist.'],
            32 => ['Complete the required steps below. Your team can choose an available route once all participation requirements are met.', 'Erledigt die folgenden erforderlichen Schritte. Sobald alle Teilnahmevoraussetzungen erfüllt sind, kann euer Team eine verfügbare Route wählen.'],
            33 => ['Invite your teammate and ask them to accept the invitation before registration closes.', 'Lade dein Teammitglied ein und bitte um Annahme vor Anmeldeschluss.'],
            34 => ['Read the liability waiver and have your acceptance confirmed as instructed by the organizers.', 'Lies den Haftungsverzicht und lass deine Zustimmung gemäß den Hinweisen der Organisation bestätigen.'],
            35 => ['Your teammate must read the liability waiver and have their acceptance confirmed.', 'Dein Teammitglied muss den Haftungsverzicht lesen und die Zustimmung bestätigen lassen.'],
            36 => ['Select your shirt size in your personal profile.', 'Wähle deine Shirtgröße in deinem persönlichen Profil.'],
            37 => ['Your teammate must select their shirt size in their personal profile.', 'Dein Teammitglied muss die Shirtgröße im persönlichen Profil wählen.'],
            38 => ['Complete the additional participant information requested by the event organizers.', 'Ergänze die von der Organisation benötigten zusätzlichen Teilnehmerangaben.'],
            39 => ['Your teammate must complete the additional participant information requested by the event organizers.', 'Dein Teammitglied muss die von der Organisation benötigten zusätzlichen Teilnehmerangaben ergänzen.'],
        ];
        $result = [];
        foreach ($texts as $id => [$english, $german]) {
            $result[$id . 'en'] = $english;
            $result[$id . 'de'] = $german;
        }
        return $result;
    }
}
