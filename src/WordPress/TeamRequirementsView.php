<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Domain\EventTextDefaults;
use Cor\Mgmt\Domain\TeamPolicy;
use Cor\Mgmt\Infrastructure\WordPress\ParticipationRepository;

/** Read-only explanation of the same requirements used by the participation writer. */
final class TeamRequirementsView
{
    /**
     * @param array<string,string|null> $race
     * @param array<string,string|null> $event
     * @param array<string,string|null> $team
     * @param list<array<string,string|null>> $members
     */
    public static function render(array $race, array $members, ParticipationRepository $repository, RacePhase $phase, array $event, array $team, bool $pending): void
    {
        echo '<section class="cor-team-requirements"><h4>' . esc_html__('Homework Assignments', 'h3-mgmt') . '</h4>';
        $texts = json_decode((string) ($event['information_text'] ?? '{}'), true);
        $texts = is_array($texts) ? $texts : [];
        $language = substr(determine_locale(), 0, 2);
        $defaults = EventTextDefaults::all();
        $text = static function (int $id) use ($texts, $language, $defaults): string {
            $value = $texts[$id . $language] ?? '';
            return wp_kses_post(is_string($value) && trim($value) !== '' ? $value : ($defaults[$id . $language] ?? $defaults[$id . 'en'] ?? ''));
        };
        if ($phase->value >= RacePhase::Started->value) {
            echo '<p>' . esc_html__('Participation status was fixed at race start. Contact the organizers if a correction is needed.', 'h3-mgmt') . '</p></section>';
            return;
        }
        $profiles = [];
        foreach ($members as $member) {
            $user = (int) ($member['user_id'] ?? 0);
            $profile = $repository->profile($user);
            if ($profile !== []) {
                $profiles[$user] = $profile;
            }
        }
        $report = TeamPolicy::requirements($race, $members, $profiles);
        if (!$report['configuration_valid']) {
            echo '<p>' . esc_html__('The organizers need to review the permitted team size.', 'h3-mgmt') . '</p></section>';
            return;
        }
        /* translators: 1: current members, 2: minimum members, 3: maximum members. */
        echo '<p>' . esc_html(sprintf(__('Team members: %1$d. Required: %2$d to %3$d.', 'h3-mgmt'), $report['count'], $report['minimum'], $report['maximum'])) . ' <strong>' . esc_html($report['size_met'] ? __('Complete', 'h3-mgmt') : __('Still incomplete', 'h3-mgmt')) . '</strong></p>';
        if (!$report['membership_valid']) {
            echo '<p>' . esc_html__('A membership or account needs review by the organizers.', 'h3-mgmt') . '</p>';
        }
        echo wp_kses_post($text(32));
        echo '<h5>' . esc_html__('Before publishing', 'h3-mgmt') . '</h5><ul class="list homework-list">';
        self::task($text(33), count($members) > 1 || $pending);
        $checks = ['waiver' => [34, 35], 'paid' => [28, 29], 'shirt_size' => [36, 37], 'public_mobile_inf' => [38, 39]];
        foreach ($checks as $key => [$ownText, $othersText]) {
            $ownMet = false;
            $others = [];
            $enabled = false;
            foreach ($report['members'] as $member) {
                if (!array_key_exists($key, $member['requirements'])) {
                    continue;
                }
                $enabled = true;
                if ($member['user_id'] === get_current_user_id()) {
                    $ownMet = $member['requirements'][$key];
                } else {
                    $others[] = $member['requirements'][$key];
                }
            }
            if ($enabled) {
                self::task($text($ownText), $ownMet);
                self::task($text($othersText), $others !== [] && !in_array(false, $others, true));
            }
        }
        echo '</ul>';
        foreach (['dis_waiver' => 16, 'dis_fee' => 17] as $setting => $id) {
            if (($race[$setting] ?? '0') !== '1') {
                echo '<div class="cor-homework-instructions">' . wp_kses_post($text($id)) . '</div>';
            }
        }
        echo '<h5>' . esc_html__('After Team is complete', 'h3-mgmt') . '</h5><ul class="list homework-list">';
        if (($team['complete'] ?? '0') === '1') {
            self::task(esc_html__('Your Team is fully registered!', 'h3-mgmt'), true);
            if (($race['startingpoint'] ?? '1') === '1') {
                $route = $repository->find('route', (int) ($team['route_id'] ?? 0));
                self::task($route !== [] ? esc_html__('Starting point chosen', 'h3-mgmt') . ': ' . esc_html((string) $route['name']) : esc_html__('You may pick your starting point now!', 'h3-mgmt'), $route !== []);
            }
            self::support($race, $team, $repository);
        } else {
            self::task(esc_html__('Your team has not yet completed the registration process...', 'h3-mgmt'), false);
            if (($race['startingpoint'] ?? '1') === '1') {
                echo '<li>' . esc_html__('You cannot yet choose a starting point.', 'h3-mgmt') . '</li>';
            }
        }
        echo '</ul>';
        echo '<p>' . esc_html__('The organizers confirm fees and waivers. Each team member completes their own profile settings.', 'h3-mgmt') . '</p></section>';
    }
    /**
     * @param array<string,string|null> $race
     * @param array<string,string|null> $team
     */
    private static function support(array $race, array $team, ParticipationRepository $repository): void
    {
        $rows = DonationFrontend::overlay($repository->related('sponsor', 'team_id', (string) $team['id']));
        $sponsors = 0;
        $owners = [];
        $total = new \Cor\Mgmt\Domain\EuroAmount(0);
        foreach ($rows as $row) {
            if (($row['paid'] ?? '0') !== '1') {
                continue;
            }
            $total = $total->add(\Cor\Mgmt\Domain\EuroAmount::decimal((string) ($row['donation'] ?? '0')));
            if (($row['type'] ?? '') === 'sponsor') {
                ++$sponsors;
            } elseif (($row['type'] ?? '') === 'owner' && ($row['var_show'] ?? '0') === '1') {
                $owners[] = (string) ($row['name'] ?? '');
            }
        }
        if (($race['kind_of_donation_tool'] ?? '1') !== '1') {
            echo '<li>' . esc_html($owners !== [] ? __('You have a TeamOwner', 'h3-mgmt') : __("You don't have a TeamOwner yet. You can hunt for one now!", 'h3-mgmt')) . '</li>';
        }
        /* translators: %d: number of team sponsors. */
        echo '<li>' . esc_html($sponsors > 0 ? sprintf(__('You have %d TeamSponsors', 'h3-mgmt'), $sponsors) : __("You don't have TeamSponsors yet. You can hunt for them now!", 'h3-mgmt')) . '</li>';
        if (($race['show_donation_amount'] ?? '0') === '1') {
            /* translators: %d: team donations in whole euros. */
            echo '<li>' . esc_html(sprintf(__('Your team donations counter: %d EUR', 'h3-mgmt'), intdiv($total->cents, 100))) . '</li>';
        }
    }
    private static function task(string $label, bool $met): void
    {
        echo '<li><span class="' . ($met ? 'to-do-done' : 'to-do-do') . '">' . wp_kses_post($label) . '</span> <strong>' . esc_html($met ? __('Complete', 'h3-mgmt') : __('Missing', 'h3-mgmt')) . '</strong></li>';
    }
}
