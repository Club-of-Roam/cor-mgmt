<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Teams\ParticipationException;

/** Native avatar uploads; an installed legacy avatar remains an optional read adapter. */
final class ProfileAvatar
{
    public static function register(): void
    {
        add_filter('get_avatar_data', [self::class, 'data'], 30, 2);
    }
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data(array $args, mixed $identity): array
    {
        $user = null;
        if (is_numeric($identity)) {
            $user = get_userdata((int) $identity);
        } elseif ($identity instanceof \WP_User) {
            $user = $identity;
        } elseif ($identity instanceof \WP_Post) {
            $user = get_userdata((int) $identity->post_author);
        } elseif ($identity instanceof \WP_Comment) {
            $user = $identity->user_id ? get_userdata((int) $identity->user_id) : get_user_by('email', $identity->comment_author_email);
        } elseif (is_string($identity)) {
            $user = get_user_by('email', $identity);
        }
        if (!$user) {
            return $args;
        }
        $url = get_user_meta($user->ID, 'cor_mgmt_avatar', true);
        if ($url === 'none') {
            $args['url'] = includes_url('images/blank.gif');
            $args['found_avatar'] = false;
            return $args;
        }
        if ($url === '') {
            $legacy = get_user_meta($user->ID, 'simple_local_avatar', true);
            $url = is_array($legacy) ? ($legacy['full'] ?? '') : '';
        }
        if (is_string($url) && preg_match('~^https?://~i', $url) && esc_url_raw($url) !== '') {
            $args['url'] = esc_url_raw($url);
            $args['found_avatar'] = true;
        }
        return $args;
    }
    public static function field(int $user, bool $disabled = false): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress constructs the escaped avatar image.
        echo get_avatar($user, 96);
        echo '<p><label for="cor-profile-avatar">' . esc_html__('Replace avatar', 'h3-mgmt') . '</label> <input id="cor-profile-avatar" type="file" name="cor_profile_avatar" accept="image/jpeg,image/png,image/gif,image/webp"' . ($disabled ? ' disabled' : '') . '></p><p><label><input type="checkbox" name="cor_remove_avatar" value="1"' . ($disabled ? ' disabled' : '') . '> ' . esc_html__('Remove avatar', 'h3-mgmt') . '</label></p>';
    }
    /** @return array{values:array<string,string>,file:?string} */
    public static function prepare(): array
    {
        if (!empty($_FILES['cor_profile_avatar']['name'])) {
            $upload = \H3_MGMT_Security::upload('cor_profile_avatar');
            if (is_wp_error($upload)) {
                throw new ParticipationException('invalid_upload');
            }
            return ['values' => ['cor_mgmt_avatar' => $upload['url']], 'file' => $upload['file']];
        }
        return ['values' => TeamBoundary::value('cor_remove_avatar') === '1' ? ['cor_mgmt_avatar' => 'none'] : [], 'file' => null];
    }
    public static function save(int $user): void
    {
        if (!current_user_can('edit_user', $user)) {
            throw new ParticipationException('not_permitted');
        }
        $values = TeamBoundary::profileInput();
        $avatar = self::prepare();
        try {
            $result = TeamBoundary::manager()->profile($user, array_replace($values, $avatar['values']));
        } catch (\Throwable $error) {
            if ($avatar['file'] !== null) {
                wp_delete_file($avatar['file']);
            }
            throw $error;
        }
        TeamBoundary::notify($result);
    }
}
