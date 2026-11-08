<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Teams\TeamManager;
use Cor\Mgmt\Application\Teams\ParticipationException;
use Cor\Mgmt\Infrastructure\WordPress\ParticipationRepository;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Request validation and transactional notification composition for team workflows. */
final class TeamBoundary
{
    public static function repository(): ParticipationRepository
    {
        global $wpdb;
        return new ParticipationRepository(new Database($wpdb));
    }
    public static function manager(?ParticipationRepository $repository = null): TeamManager
    {
        $runtime = Services::get();
        $repository ??= self::repository();
        $notifications = new \Cor\Mgmt\Infrastructure\WordPress\TeamNotifications($repository->database(), $runtime->clock);
        return new TeamManager($repository, $runtime->clock, $runtime->random, static fn (string $action, int $id): bool => match ($action) {
            'reconcile' => \H3_MGMT_Security::can_object('races', 'edit', $id),
            'self' => is_user_logged_in() && get_current_user_id() === $id,
            'profile' => current_user_can('edit_user', $id),
            'team' => \H3_MGMT_Security::can_object('teams', 'edit', $id),
            'delete' => \H3_MGMT_Security::can_object('teams', 'delete', $id),
            'member' => \H3_MGMT_Security::can_object('participants', 'edit', $id),
            'route' => current_user_can('h3_mgmt_edit_teams') || \H3_MGMT_Security::assigned_route($id),
            default => false,
        }, $notifications->capture(...));
    }
    public static function value(string $key, string $default = ''): string
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        if (!is_string($_POST[$key])) {
            throw new ParticipationException('invalid_input');
        }
        return wp_unslash($_POST[$key]);
    }
    public static function number(string $value): int
    {
        if (
            !preg_match('/^(0|[1-9][0-9]*)$/D', $value) || strlen($value) > strlen((string) PHP_INT_MAX)
            || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)
        ) {
            throw new ParticipationException('invalid_number');
        }
        return (int) $value;
    }
    /** @return array<string,string> */
    public static function profileInput(): array
    {
        $values = [];
        foreach (ParticipationRepository::PROFILE_FIELDS as $field) {
            if (!isset($_POST[$field]) || in_array($field, ['birthday', 'cor_mgmt_avatar'], true)) {
                continue;
            }
            $raw = self::value($field);
            $values[$field] = $field === 'addressMobile' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
        }
        if (isset($values['shirt_size']) && !in_array($values['shirt_size'], ['', '0','ms','mm','ml','mx','gs','gm','gl'], true)) {
            throw new ParticipationException('invalid_shirt');
        }
        if (isset($values['public_mobile_inf']) && !in_array($values['public_mobile_inf'], ['', '0','yes','no'], true)) {
            throw new ParticipationException('invalid_mobile_choice');
        }
        $date = null;
        if (isset($_POST['birthday'])) {
            $date = self::value('birthday');
        } elseif (isset($_POST['birthday-year'], $_POST['birthday-month'], $_POST['birthday-day'])) {
            $date = sprintf('%04d-%02d-%02d', self::number(self::value('birthday-year')), self::number(self::value('birthday-month')), self::number(self::value('birthday-day')));
        }
        if ($date !== null && $date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$parsed || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $parsed->getTimestamp() > Services::get()->clock->now()) {
                throw new ParticipationException('invalid_birthday');
            }
            $values['birthday'] = (string) $parsed->getTimestamp();
        }
        return $values;
    }
    /** @return array<string,string> */
    public static function teamInput(): array
    {
        $values = [];
        foreach (TeamManager::TEAM_FIELDS as $field) {
            if (!isset($_POST[$field]) || $field === 'team_pic') {
                continue;
            }
            $raw = self::value($field);
            $values[$field] = $field === 'description' ? wp_kses_post($raw)
                : (str_starts_with($field, 'meta_') ? sanitize_textarea_field($raw) : sanitize_text_field($raw));
        }
        return $values;
    }
    public static function language(string $value): string
    {
        if (!preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/D', $value)) {
            throw new ParticipationException('invalid_language');
        }
        return $value;
    }
    /** @return list<string> */
    public static function emails(): array
    {
        $raw = $_POST['invitations'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/', wp_unslash($raw)) ?: [];
        }
        if (!is_array($raw) || count($raw) > 100) {
            throw new ParticipationException('invalid_invitations');
        }
        $output = [];
        foreach ($raw as $value) {
            if (!is_string($value)) {
                throw new ParticipationException('invalid_email');
            }
            $email = trim($value);
            if ($email !== '') {
                if (!is_email($email)) {
                    throw new ParticipationException('invalid_email');
                }
                $output[] = $email;
            }
        }
        return $output;
    }
    /** @return array<string,mixed> */
    public static function save(int $race): array
    {
        if (!is_user_logged_in() || !\H3_MGMT_Security::post('cor-mgmt-team-' . $race) || self::value('address') !== '') {
            throw new ParticipationException('not_permitted');
        }
        $manager = self::manager();
        $user = get_current_user_id();
        $team = $manager->owned($race, $user);
        $revision = self::value('cor_team_revision');
        if ($revision !== '' && !hash_equals($manager->revision($race, (int) ($team['id'] ?? 0)), $revision)) {
            throw new ParticipationException('stale_record');
        }
        if (self::value('cor_profile_only') === '1') {
            if (\H3_MGMT_Security::phase($race) === 4) {
                throw new ParticipationException('phase_locked');
            }
            ProfileAvatar::save($user);
            return ['events' => []];
        }
        $values = self::teamInput();
        $upload = null;
        $code = self::value('invitation');
        if (!empty($_FILES['team_pic']['name'])) {
            if ((!$team && $code !== '') || !\Cor\Mgmt\Domain\TeamPolicy::allows('content', \Cor\Mgmt\Domain\RacePhase::from(\H3_MGMT_Security::phase($race)))) {
                throw new ParticipationException('not_permitted');
            }
            $upload = \H3_MGMT_Security::upload('team_pic');
            if (is_wp_error($upload)) {
                throw new ParticipationException('invalid_upload');
            }
            $values['team_pic'] = $upload['url'];
        }
        $avatar = ['values' => [], 'file' => null];
        try {
            $profile = self::profileInput();
            $avatar = ProfileAvatar::prepare();
            $profile = array_replace($profile, $avatar['values']);
            $profile['language'] = self::language(substr(determine_locale(), 0, 2));
            $result = $manager->save($race, $user, $values, $profile, $code, self::emails(), isset($_POST['route_id']) ? self::number(self::value('route_id')) : null, $revision);
        } catch (\Throwable $error) {
            if ($avatar['file'] !== null) {
                wp_delete_file($avatar['file']);
            }
            if (is_array($upload) && isset($upload['file']) && is_string($upload['file'])) {
                wp_delete_file($upload['file']);
            }
            throw $error;
        }
        self::notify($result);
        return $result;
    }
    /** @param array<string,mixed> $result */
    public static function notify(array $result): void
    {
        // Compatibility hook: TeamManager has already persisted notifications before commit.
    }
    public static function message(\Throwable $error): string
    {
        $reason = $error instanceof ParticipationException ? $error->reason : '';
        return match ($reason) {
            'phase_locked' => __('This action is not available in the current race phase.', 'h3-mgmt'),
            'team_full' => __('This team has no free member places.', 'h3-mgmt'),
            'route_full' => __('This route has no free team places.', 'h3-mgmt'),
            'invalid_invitation' => __('This invitation is invalid, consumed or revoked.', 'h3-mgmt'),
            'stale_record' => __('The team or race changed. Reload before saving.', 'h3-mgmt'),
            'participation_setup_required', 'transactional_storage_required' => __('Participation requires database setup. Open Tools > cor-mgmt system status.', 'h3-mgmt'),
            'duplicate_membership_review', 'cross_race_review_required' => __('This membership needs a controlled review. No data was changed.', 'h3-mgmt'),
            'dependent_records' => __('This team has historical results or donations and cannot be deleted.', 'h3-mgmt'),
            default => __('The change could not be saved. Check required fields, permissions and available places.', 'h3-mgmt'),
        };
    }
}
