<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Events\EventEditor;
use Cor\Mgmt\Application\Events\EventException;
use Cor\Mgmt\Domain\EventPolicy;
use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Infrastructure\WordPress\LegacyEventStore;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** WordPress request/presentation adapter; the legacy menu delegates to this boundary. */
final class EventAdmin
{
    public static function editor(?\Closure $account = null): EventEditor
    {
        global $wpdb;
        $db = new Database($wpdb);
        $participation = new \Cor\Mgmt\Infrastructure\WordPress\ParticipationRepository($db);
        return new EventEditor(
            new LegacyEventStore($db),
            static fn (string $type, string $action, int $id): bool => \H3_MGMT_Security::can_object($type . 's', $action, $id),
            $account,
            static fn (int $race): array => TeamBoundary::manager($participation)->reconcileRace($race)
        );
    }
    /** @return array<string,mixed> */
    public static function mutate(string $type, int $id, string $action): array
    {
        if (!\H3_MGMT_Security::admin_request($type . 's')) {
            throw new EventException('not_permitted');
        }
        $input = [];
        foreach ($_POST as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $input[$key] = wp_unslash((string) $value);
            }
        }
        if (str_contains($action, 'delete')) {
            $ids = $action === 'bulk-delete' ? array_values(array_map('intval', (array) ($_GET['bulk'] ?? []))) : [$id];
            return self::editor()->delete($type, $ids);
        }
        if ($action !== 'save') {
            $input = match ($action) {
                'active' => ['active' => '1'],
                'liveticker-set' => ['liveticker' => '1'], 'liveticker-unset' => ['liveticker' => '0'],
                'liveticker-front-set' => ['liveticker_front' => '1'], 'liveticker-front-unset' => ['liveticker_front' => '0'],
                'donation-set' => ['donation' => '1'], 'donation-unset' => ['donation' => '0'],
                default => throw new EventException('invalid_action'),
            } + $input;
        }
        global $wpdb;
        $original = $type === 'race' && $id ? (new LegacyEventStore(new Database($wpdb)))->find('race', $id) : [];
        foreach (['start', 'end'] as $date) {
            if ($type !== 'race' || !isset($input[$date])) {
                continue;
            }
            $value = trim($input[$date]);
            if ($value === '') {
                $input[$date] = '0';
                continue;
            }
            $parsed = false;
            foreach (['!d.m.Y', '!Y-m-d'] as $format) {
                $candidate = \DateTimeImmutable::createFromFormat($format, $value, wp_timezone());
                $errors = \DateTimeImmutable::getLastErrors();
                if ($candidate !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    $parsed = $candidate;
                    break;
                }
            }
            if ($parsed === false) {
                throw new EventException('invalid_dates');
            }
            $originalDate = (int) ($original[$date] ?? 0);
            $input[$date] = $originalDate && wp_date('Y-m-d', $originalDate, wp_timezone()) === $parsed->format('Y-m-d')
                ? (string) $originalDate : (string) $parsed->getTimestamp();
        }
        foreach (['name', 'country', 'destination', 'meeting_point'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = sanitize_text_field($input[$field]);
            }
        }
        foreach (['logo_url', 'betterplace_redirect_link', 'donation_link_link', 'team_overview_link'] as $field) {
            if (isset($input[$field])) {
                $clean = esc_url_raw($input[$field], ['http', 'https']);
                if ($input[$field] !== '' && $clean === '') {
                    throw new EventException('invalid_url');
                }
                $input[$field] = $clean;
            }
        }
        if (isset($input['country_3166_alpha_2'])) {
            $input['country_3166_alpha-2'] = strtoupper($input['country_3166_alpha_2']);
        }
        if ($type === 'race') {
            global $wpdb;
            $db = new Database($wpdb);
            $store = new LegacyEventStore($db);
            $row = $id ? $store->find('race', $id) : [];
            $information = $id ? self::information($row['information_text'] ?? '{}') : \Cor\Mgmt\Domain\EventTextDefaults::all();
            $changed = false;
            foreach ($input as $key => $value) {
                if (preg_match('/^(?:[1-9]|[1-3][0-9])(?:en|de)$/D', $key)) {
                    $clean = wp_kses_post($value);
                    if (($information[$key] ?? '') !== $clean) {
                        $information[$key] = $clean;
                        $changed = true;
                    }
                }
            }
            $copy = (int) ($input['race_id_inf'] ?? 0);
            if ($copy) {
                if (!\H3_MGMT_Security::can_object('races', 'view', $copy)) {
                    throw new EventException('not_permitted');
                }
                $source = $store->find('race', $copy);
                if ($source === []) {
                    throw new EventException('missing_record');
                }
                $information = self::information($source['information_text']);
                $changed = true;
            }
            // Do not reserialize untouched historical HTML/unknown keys.
            if ($changed || !$id) {
                $input['information_text'] = json_encode($information, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
        }
        $created = 0;
        $account = null;
        if ($type === 'route' && trim($input['route_account_login_name'] ?? '') !== '') {
            if (!current_user_can('create_users') || !current_user_can('h3_mgmt_edit_races')) {
                throw new EventException('not_permitted');
            }
            $login = sanitize_user($input['route_account_login_name'], true);
            $email = sanitize_email($input['route_account_email'] ?? '');
            $password = $input['route_account_pw1'] ?? '';
            if (!$login || !is_email($email) || $password === '' || $password !== ($input['route_account_pw2'] ?? '') || username_exists($login) || email_exists($email)) {
                throw new EventException('invalid_account');
            }
            $account = static function () use ($login, $email, $password, &$created): int {
                $user = wp_insert_user(['user_login' => $login, 'user_email' => $email, 'user_pass' => $password, 'role' => 'subscriber']);
                if (is_wp_error($user)) {
                    throw new EventException('invalid_account');
                }
                $created = $user;
                $accountUser = new \WP_User($user);
                foreach (['read', 'h3_mgmt_view_races', 'h3_mgmt_edit_own_races', 'h3_mgmt_view_teams', 'h3_mgmt_view_own_teams', 'h3_mgmt_edit_own_teams'] as $capability) {
                    $accountUser->add_cap($capability);
                }
                return $user;
            };
        }
        if ($type === 'route' && isset($input['user_id'])) {
            if (!current_user_can('h3_mgmt_edit_races') || ($input['user_id'] !== '0' && !get_userdata((int) $input['user_id']))) {
                throw new EventException('invalid_account');
            }
        }
        try {
            $result = self::editor($account)->save($type, $id, $input, $input['cor_event_revision'] ?? '', ($input['cor_event_confirm'] ?? '') === '1');
            TeamBoundary::notify($result);
            return $result;
        } catch (\Throwable $error) {
            if ($created) {
                if (defined('ABSPATH')) {
                    require_once constant('ABSPATH') . 'wp-admin/includes/user.php';
                }
                wp_delete_user($created);
            }
            throw $error;
        }
    }
    /** @return array<string,mixed> */
    private static function information(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new EventException('invalid_information');
        }
        return $data;
    }
    public static function errorCode(\Throwable $error): string
    {
        $code = $error instanceof EventException || $error instanceof \Cor\Mgmt\Application\Teams\ParticipationException ? $error->reason
            : ($error instanceof \Cor\Mgmt\Application\Migration\MigrationException ? $error->getMessage() : 'unexpected_error');
        return preg_match('/^[a-z_]{1,64}$/D', $code) ? $code : 'unexpected_error';
    }
    public static function message(string $code): string
    {
        return match ($code) {
            'transactional_storage_required' => __('Participant changes require InnoDB tables, including WordPress users and user metadata. A controlled storage review is required before saving.', 'h3-mgmt'),
            'participation_setup_required' => __('Team and invitation storage requires setup. Open Tools > cor-mgmt system status.', 'h3-mgmt'),
            'stale_record' => __('This record or its race changed. Reload before saving; your changes were not applied.', 'h3-mgmt'),
            'capacity_below_occupancy' => __('The capacity is below the number of complete teams. Existing assignments will remain. Confirm to save.', 'h3-mgmt'),
            'starting_points_have_assignments' => __('Teams already have assignments. Changing starting points will not move or remove them. Confirm to save.', 'h3-mgmt'),
            'archive_not_ready' => __('Prepare and verify the race snapshot under Tools > Archives and storage transition, then publish its completion there.', 'h3-mgmt'),
            'archived' => __('Archived races cannot be changed.', 'h3-mgmt'),
            'field_locked', 'structure_locked', 'change_phase_separately' => __('This change is locked in the current race phase. Change the phase separately if required.', 'h3-mgmt'),
            'dependent_records', 'active_race' => __('This record is still in use and cannot be removed.', 'h3-mgmt'),
            'invalid_dates' => __('Enter valid dates with the end on or after the start.', 'h3-mgmt'),
            'invalid_team_sizes' => __('Team sizes must be between 1 and 100; the minimum cannot exceed the maximum.', 'h3-mgmt'),
            'duplicate_stage_number' => __('That stage number is already used on this route.', 'h3-mgmt'),
            'transactional_storage_required' => __('This operation requires transactional tables. Run the controlled database review first.', 'h3-mgmt'),
            'invalid_account' => __('Check the route account, its email address and matching passwords.', 'h3-mgmt'),
            'invalid_number' => __('Enter whole numbers in the numeric fields. Numeric values must not be empty or negative.', 'h3-mgmt'),
            'invalid_name' => __('Enter a name or destination with 1 to 250 characters.', 'h3-mgmt'),
            'invalid_url' => __('Check the link fields and enter valid web addresses.', 'h3-mgmt'),
            'invalid_phase', 'create_in_phase_zero' => __('Create the event in the phase before registration opens.', 'h3-mgmt'),
            'not_permitted' => __('Your account is not allowed to change this event or record.', 'h3-mgmt'),
            'invalid_settings', 'invalid_information' => __('The stored event settings or information texts require review. Existing data is retained.', 'h3-mgmt'),
            'migration_locked', 'nested_migration_lock' => __('Another operation is using the plugin data. Wait for it to finish, then reload before trying again.', 'h3-mgmt'),
            'maintenance_in_progress', 'runtime_generation_requires_review' => __('The storage transition requires review before changes can be saved. Open the archive and storage transition tools.', 'h3-mgmt'),
            'database_query_failed' => __('The database rejected this change. Review the system status before trying again.', 'h3-mgmt'),
            default => __('The change could not be saved. Check the fields, permissions and system status.', 'h3-mgmt'),
        };
    }
    public static function fieldAllowed(string $type, string $field, int $race): bool
    {
        if (preg_match('/^[0-9]+(?:en|de)$/D', $field) || $field === 'race_id_inf') {
            $field = 'information_text';
        }
        if (str_starts_with($field, 'route_account_')) {
            $field = 'user_id';
        }
        if ($field === 'country_3166_alpha_2') {
            $field = 'country_3166_alpha-2';
        }
        $phase = $race ? RacePhase::tryFrom(\H3_MGMT_Security::phase($race)) : RacePhase::BeforeRegistration;
        return $phase !== null && EventPolicy::allows($type, $field, $phase);
    }
}
