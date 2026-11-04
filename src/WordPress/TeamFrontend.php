<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Events\EventEditor;
use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Domain\TeamPolicy;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ParticipationSchema;

/** Native participant form; all writes delegate to the application service. */
final class TeamFrontend
{
    public static function invitedTeam(string $code): int
    {
        $repo = TeamBoundary::repository();
        global $wpdb;
        if (!ParticipationSchema::ready(new Database($wpdb))) {
            return 0;
        }
        try {
            if (preg_match('/^v2-[a-f0-9]{64}$/D', $code)) {
                $tokens = $repo->related('token', 'token_hash', hash('sha256', $code));
            } elseif (preg_match('/^[0-9]{1,19}$/D', $code)) {
                $tokens = $repo->related('invitation', 'code', ltrim($code, '0') ?: '0');
            } else {
                return 0;
            }
            if (count($tokens) !== 1) {
                return 0;
            }
            $team = $repo->find('team', (int) $tokens[0]['team_id']);
            return (int) TeamBoundary::manager()->invitation($code, (int) ($team['race_id'] ?? 0))['team_id'];
        } catch (\Throwable) {
            return 0;
        }
    }
    public static function dashboard(int $race): string
    {
        if (!is_user_logged_in()) {
            $redirect = get_permalink() ?: home_url('/');
            $code = is_string($_GET['invitation'] ?? null) ? wp_unslash($_GET['invitation']) : '';
            if (preg_match('/^(?:v2-[a-f0-9]{64}|[0-9]{1,19})$/D', $code)) {
                $redirect = add_query_arg('invitation', $code, $redirect);
            }
            return '<p><a href="' . esc_url(wp_login_url($redirect)) . '">' . esc_html__('Log in to manage your team.', 'h3-mgmt') . '</a></p>';
        }
        $message = '';
        try {
            if (($_POST['todo'] ?? '') === 'cancel-invitation') {
                if (!\H3_MGMT_Security::post('cor-mgmt-team-' . $race)) {
                    return \H3_MGMT_Security::error();
                }
                TeamBoundary::manager()->cancelInvitation($race, get_current_user_id(), TeamBoundary::value('invitation_email'));
                $message = __('Invitation revoked.', 'h3-mgmt');
            } elseif (($_POST['todo'] ?? '') === 'leave') {
                if (!\H3_MGMT_Security::post('cor-mgmt-team-' . $race)) {
                    return \H3_MGMT_Security::error();
                }
                TeamBoundary::notify(TeamBoundary::manager()->leave($race, get_current_user_id()));
                $message = __('You have left the team.', 'h3-mgmt');
            } elseif (isset($_POST['submitted'])) {
                TeamBoundary::save($race);
                $message = __('Changes saved.', 'h3-mgmt');
                unset($_POST['submitted']);
            }
        } catch (\Throwable $error) {
            $message = TeamBoundary::message($error);
        }
        ob_start();
        try {
            self::render($race, $message);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
    private static function render(int $raceId, string $message): void
    {
        global $wpdb;
        if (!ParticipationSchema::ready(new Database($wpdb))) {
            echo '<p>' . esc_html__('Participation setup is pending. Please contact the event organizers.', 'h3-mgmt') . '</p>';
            return;
        }
        $repo = TeamBoundary::repository();
        $race = $repo->find('race', $raceId);
        if (!$race) {
            echo '<p>' . esc_html__('This event is unavailable.', 'h3-mgmt') . '</p>';
            return;
        }
        $settings = EventEditor::flatten($race);
        $phase = RacePhase::tryFrom((int) ($settings['status'] ?? -1));
        if ($phase === null) {
            echo '<p>' . esc_html__('The event phase requires review.', 'h3-mgmt') . '</p>';
            return;
        }
        $user = get_current_user_id();
        $manager = TeamBoundary::manager();
        $own = $manager->owned($raceId, $user);
        $team = $own;
        $code = isset($_POST['invitation']) && is_string($_POST['invitation']) ? wp_unslash($_POST['invitation'])
            : (isset($_GET['invitation']) && is_string($_GET['invitation']) ? wp_unslash($_GET['invitation']) : '');
        $joining = false;
        if (!$own && $code !== '') {
            try {
                $token = $manager->invitation($code, $raceId);
                $team = $repo->find('team', (int) $token['team_id']);
                $joining = true;
            } catch (\Throwable $error) {
                $message = TeamBoundary::message($error);
            }
        }
        echo '<section class="cor-team-dashboard"><h2>' . esc_html((string) $race['name']) . '</h2><p>' . esc_html(EventList::phaseLabel($phase->value)) . '</p>';
        if ($message !== '') {
            echo '<p role="status">' . esc_html($message) . '</p>';
        }
        $profile = $repo->profile($user);
        if ($own) {
            echo '<h3>' . esc_html((string) $own['team_name']) . '</h3><p>' . esc_html($own['complete'] === '1' ? __('Team requirements fulfilled', 'h3-mgmt') : __('Team requirements still incomplete', 'h3-mgmt')) . '</p><ul>';
            foreach ($repo->related('teammate', 'team_id', (string) $own['id']) as $member) {
                $person = get_userdata((int) $member['user_id']);
                echo '<li>' . esc_html($person ? ($person->first_name ?: $person->display_name) : __('Unavailable account', 'h3-mgmt')) . '</li>';
            }
            echo '</ul>';
        }
        if (!$own && !$joining && $phase !== RacePhase::RegistrationOpen) {
            echo '<p>' . esc_html__('Team registration is closed. You can edit your general profile below.', 'h3-mgmt') . '</p>';
        }
        if (!$own && $phase === RacePhase::RegistrationOpen) {
            echo '<form method="get"><label for="cor-invitation">' . esc_html__('Invitation code', 'h3-mgmt') . '</label> <input id="cor-invitation" name="invitation" value="' . esc_attr($code) . '"><button type="submit">' . esc_html__('Show invitation', 'h3-mgmt') . '</button></form>';
        }
        $canRegister = $phase === RacePhase::RegistrationOpen;
        $canTeam = !$joining && ($own !== [] || $canRegister);
        $canProfile = $phase !== RacePhase::Archived;
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('cor-mgmt-team-' . $raceId, 'cor_mgmt_nonce', false);
        echo '<input type="hidden" name="submitted" value="1"><input type="hidden" name="cor_team_revision" value="' . esc_attr($manager->revision($raceId, (int) ($own['id'] ?? 0))) . '">';
        if (!$own && !$canRegister) {
            echo '<input type="hidden" name="cor_profile_only" value="1">';
        }
        if ($joining) {
            echo '<p>' . esc_html__('Accept the invitation to this team.', 'h3-mgmt') . ' ' . esc_html((string) $team['team_name']) . '</p><input type="hidden" name="invitation" value="' . esc_attr($code) . '">';
        }
        if ($canTeam) {
            echo '<fieldset><legend>' . esc_html__('Team profile', 'h3-mgmt') . '</legend>';
            self::field('team_name', __('Team name', 'h3-mgmt'), (string) ($team['team_name'] ?? ''), !TeamPolicy::allows('name', $phase));
            self::field('description', __('Description', 'h3-mgmt'), (string) ($team['description'] ?? ''), !TeamPolicy::allows('content', $phase), 'textarea');
            self::field('donation_goal', __('Donation goal', 'h3-mgmt'), (string) ($team['donation_goal'] ?? '0'), !TeamPolicy::allows('content', $phase));
            self::field('team_phone', __('Team phone', 'h3-mgmt'), (string) ($team['team_phone'] ?? ''), !TeamPolicy::allows('content', $phase));
            if (!empty($team['team_pic'])) {
                echo '<p><img width="150" src="' . esc_url((string) $team['team_pic']) . '" alt="' . esc_attr__('Current team picture', 'h3-mgmt') . '"></p>';
            }
            echo '<p><label for="cor-team-picture">' . esc_html__('Team picture', 'h3-mgmt') . '</label> <input type="file" id="cor-team-picture" name="team_pic" accept="image/jpeg,image/png,image/gif,image/webp"' . (TeamPolicy::allows('content', $phase) ? '' : ' disabled') . '></p>';
            $texts = json_decode((string) ($race['information_text'] ?? '{}'), true);
            $language = substr(determine_locale(), 0, 2);
            foreach (range(1, 5) as $number) {
                if (($settings['question_' . $number . '_invisible'] ?? '0') === '1') {
                    continue;
                }
                $label = is_array($texts) && is_string($texts[$number . $language] ?? null) ? wp_strip_all_tags($texts[$number . $language]) : (string) $number;
                if ($number === 4) {
                    $options = ['0' => '-'];
                    foreach (range(1, 4) as $option) {
                        $options[$option] = is_array($texts) && is_string($texts[($option + 11) . $language] ?? null) ? wp_strip_all_tags($texts[($option + 11) . $language]) : (string) $option;
                    }
                    $existing = (string) ($team['meta_4'] ?? '0');
                    if ($existing !== '' && !array_key_exists($existing, $options)) {
                        $options[$existing] = $existing;
                    }
                    self::select('meta_4', $label, $existing, $options, !TeamPolicy::allows('content', $phase));
                } else {
                    self::field('meta_' . $number, $label, (string) ($team['meta_' . $number] ?? ''), !TeamPolicy::allows('content', $phase), 'textarea');
                }
                $description = is_array($texts) && is_string($texts[($number + 5) . $language] ?? null) ? $texts[($number + 5) . $language] : '';
                echo '<p class="description">' . wp_kses_post($description) . '</p>';
            }
            echo '</fieldset>';
        }
        echo '<fieldset><legend>' . esc_html__('Your profile', 'h3-mgmt') . '</legend>';
        foreach (['first_name' => __('First name', 'h3-mgmt'), 'last_name' => __('Last name', 'h3-mgmt'), 'city' => __('City', 'h3-mgmt'), 'mobile' => __('Mobile phone', 'h3-mgmt'), 'addressMobile' => __('Address', 'h3-mgmt')] as $field => $label) {
            self::field($field, $label, $profile[$field] ?? '', !$canProfile, $field === 'addressMobile' ? 'textarea' : 'text');
        }
        ProfileAvatar::field($user, !$canProfile);
        self::field('birthday', __('Date of birth', 'h3-mgmt'), (int) ($profile['birthday'] ?? 0) ? (wp_date('Y-m-d', (int) $profile['birthday']) ?: '') : '', !$canProfile, 'date');
        self::select('shirt_size', __('Shirt size', 'h3-mgmt'), $profile['shirt_size'] ?? '', ['0' => '-', 'ms' => 'Unisex S', 'mm' => 'Unisex M', 'ml' => 'Unisex L', 'mx' => 'Unisex XL', 'gs' => 'Slimfit S', 'gm' => 'Slimfit M', 'gl' => 'Slimfit L'], !$canProfile);
        self::select('public_mobile_inf', __('Share details with the mobile partner', 'h3-mgmt'), $profile['public_mobile_inf'] ?? '', ['0' => '-', 'yes' => __('Yes', 'h3-mgmt'), 'no' => __('No', 'h3-mgmt')], !$canProfile);
        echo '</fieldset>';
        if ($own && TeamPolicy::allows('route', $phase) && $own['complete'] === '1' && ($phase === RacePhase::RegistrationOpen || (int) $own['route_id'] === 0)) {
            $options = ['0' => __('No route selected', 'h3-mgmt')];
            foreach ($repo->related('route', 'race_id', (string) $raceId) as $route) {
                $count = count(array_filter($repo->related('team', 'route_id', (string) $route['id']), static fn (array $row): bool => $row['complete'] === '1'));
                if ($count < (int) $route['max_teams'] || $route['id'] === $own['route_id']) {
                    $options[(string) $route['id']] = (string) $route['name'];
                }
            }
            self::select('route_id', __('Route', 'h3-mgmt'), (string) $own['route_id'], $options, ($settings['startingpoint'] ?? '1') === '0');
        }
        if ($canTeam && $canRegister) {
            self::field('invitations', __('Invite people (one email address per line; invitations do not reserve places)', 'h3-mgmt'), '', false, 'textarea');
        }
        if ($canProfile) {
            echo '<p><button type="submit">' . esc_html($joining ? __('Accept invitation', 'h3-mgmt') : __('Save changes', 'h3-mgmt')) . '</button></p>';
        }
        echo '</form>';
        if ($own && TeamPolicy::allows('leave', $phase)) {
            echo '<form method="post">';
            wp_nonce_field('cor-mgmt-team-' . $raceId, 'cor_mgmt_nonce', false);
            echo '<input type="hidden" name="todo" value="leave"><button type="submit" data-confirm="' . esc_attr__('Really leave this team?', 'h3-mgmt') . '">' . esc_html__('Leave team', 'h3-mgmt') . '</button></form>';
        }
        if ($own && $canRegister) {
            echo '<h3>' . esc_html__('Pending invitations', 'h3-mgmt') . '</h3><ul>';
            foreach ($manager->pending($raceId, $user) as $pending) {
                echo '<li>' . esc_html($pending['email']) . '<form method="post">';
                wp_nonce_field('cor-mgmt-team-' . $raceId, 'cor_mgmt_nonce', false);
                echo '<input type="hidden" name="todo" value="cancel-invitation"><input type="hidden" name="invitation_email" value="' . esc_attr($pending['email']) . '"><button type="submit">' . esc_html__('Revoke invitation', 'h3-mgmt') . '</button></form></li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }
    private static function field(string $key, string $label, string $value, bool $disabled, string $type = 'text'): void
    {
        echo '<p><label for="cor-team-' . esc_attr($key) . '">' . esc_html($label) . '</label><br>';
        if ($type === 'textarea') {
            echo '<textarea rows="4" id="cor-team-' . esc_attr($key) . '" name="' . esc_attr($key) . '"' . ($disabled ? ' disabled' : '') . '>' . esc_textarea($value) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr($type) . '" id="cor-team-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '"' . ($disabled ? ' disabled' : '') . '>';
        }
        echo '</p>';
    }
    /** @param array<int|string,string> $options */
    private static function select(string $key, string $label, string $value, array $options, bool $disabled): void
    {
        echo '<p><label for="cor-team-' . esc_attr($key) . '">' . esc_html($label) . '</label><br><select id="cor-team-' . esc_attr($key) . '" name="' . esc_attr($key) . '"' . ($disabled ? ' disabled' : '') . '>';
        foreach ($options as $choice => $text) {
            echo '<option value="' . esc_attr((string) $choice) . '"' . selected($value, (string) $choice, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select></p>';
    }
}
