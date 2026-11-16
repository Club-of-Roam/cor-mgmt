<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Independent plain-text starting points for new events; never a runtime fallback. */
final class AutoResponseDefaults
{
    /** @return list<AutoResponseTemplate> */
    public static function all(): array
    {
        $messages = [
            'en' => [
                'team-creation' => ['Your team for %event_name%', "Hello %name%,\n\nYour team %team_name% has been created for %event_name%. Invite your teammates and check the participation requirements in your Team Dashboard:\n%link%\n\nSee you at %event_name%!"],
                'invitation' => ['Join %team_name% at %event_name%', "Hello,\n\n%inviter% invites you to join %team_name% at %event_name%. Open the following link and log in or create an account to accept:\n%link%\n\nYou can accept while registration is open and a place in the team is available. This invitation does not reserve a place. If you were not expecting it, you can ignore this message."],
                'invitation-accepted-inviter' => ['%invitee% has joined %team_name%', "Hello %name%,\n\n%invitee% has joined your team %team_name% for %event_name%. Check your team and the remaining participation requirements here:\n%link%"],
                'invitation-accepted-invitee' => ['Welcome to %team_name%', "Hello %name%,\n\nYou have joined %team_name% for %event_name%. Visit your Team Dashboard to review your details and participation requirements:\n%link%"],
                'publishable' => ['%team_name% is ready to choose a route', "Hello %name%,\n\nYour team %team_name% meets the participation requirements for %event_name%. Check your Team Dashboard to choose an available route:\n%link%\n\nMeeting the requirements does not reserve a route place."],
                'package-paid' => ['Participation fee received for %event_name%', "Hello %name%,\n\nYour participation fee for %event_name% has been confirmed. Check the remaining requirements for %team_name% in your Team Dashboard:\n%link%"],
                'waiver-reached' => ['Liability waiver received for %event_name%', "Hello %name%,\n\nYour liability waiver for %event_name% has been confirmed. Check the remaining requirements for %team_name% in your Team Dashboard:\n%link%"],
            ],
            'de' => [
                'team-creation' => ['Dein Team für %event_name%', "Hallo %name%,\n\ndein Team %team_name% wurde für %event_name% angelegt. Lade deine Teammitglieder ein und prüfe die Teilnahmevoraussetzungen in deinem Team Dashboard:\n%link%\n\nWir sehen uns bei %event_name%!"],
                'invitation' => ['Einladung zu %team_name% bei %event_name%', "Hallo,\n\n%inviter% lädt dich in das Team %team_name% bei %event_name% ein. Öffne diesen Link und melde dich an oder erstelle ein Konto, um die Einladung anzunehmen:\n%link%\n\nDie Annahme ist während der offenen Anmeldung möglich, solange ein Mitgliedsplatz frei ist. Diese Einladung reserviert keinen Platz. Falls du sie nicht erwartet hast, kannst du diese Nachricht ignorieren."],
                'invitation-accepted-inviter' => ['%invitee% ist %team_name% beigetreten', "Hallo %name%,\n\n%invitee% ist deinem Team %team_name% für %event_name% beigetreten. Dein Team und die noch offenen Teilnahmevoraussetzungen findest du hier:\n%link%"],
                'invitation-accepted-invitee' => ['Willkommen bei %team_name%', "Hallo %name%,\n\ndu bist dem Team %team_name% für %event_name% beigetreten. Prüfe deine Angaben und die Teilnahmevoraussetzungen im Team Dashboard:\n%link%"],
                'publishable' => ['%team_name% ist bereit für die Routenwahl', "Hallo %name%,\n\ndein Team %team_name% erfüllt die Teilnahmevoraussetzungen für %event_name%. Im Team Dashboard kannst du eine verfügbare Route auswählen:\n%link%\n\nErfüllte Voraussetzungen reservieren noch keinen Routenplatz."],
                'package-paid' => ['Teilnahmegebühr für %event_name% bestätigt', "Hallo %name%,\n\ndeine Teilnahmegebühr für %event_name% wurde bestätigt. Die übrigen Voraussetzungen für %team_name% findest du im Team Dashboard:\n%link%"],
                'waiver-reached' => ['Haftungsverzicht für %event_name% bestätigt', "Hallo %name%,\n\ndein Haftungsverzicht für %event_name% wurde bestätigt. Die übrigen Voraussetzungen für %team_name% findest du im Team Dashboard:\n%link%"],
            ],
        ];
        $templates = [];
        foreach ($messages as $language => $actions) {
            foreach ($actions as $action => [$subject, $message]) {
                $templates[] = new AutoResponseTemplate($action, $language, true, $subject, $message);
            }
        }
        return $templates;
    }
}
