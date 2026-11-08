<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Infrastructure\WordPress\Exchange;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Native shortcode and moderation; no theme template or public GET mutation. */
final class ExchangeBoundary
{
    public static function manager(): Exchange
    {
        global $wpdb;
        return new Exchange(new Database($wpdb), Services::get()->clock, static fn (int $actor): bool => $actor === get_current_user_id() && current_user_can('manage_options'));
    }
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_management_page(__('HitchMate XChange moderation', 'h3-mgmt'), __('HitchMate XChange', 'h3-mgmt'), 'manage_options', 'cor-mgmt-exchange', [self::class, 'page']);
        });
    }
    public static function submit(bool $moderation = false): int
    {
        if (!\H3_MGMT_Security::post($moderation ? 'cor-mgmt-exchange-admin' : 'cor-mgmt-xchange') || TeamBoundary::value('address') !== '') {
            throw new CommunicationException('invalid_nonce');
        }
        return self::manager()->save(get_current_user_id(), TeamBoundary::number(TeamBoundary::value('message_id', '0')), sanitize_textarea_field(TeamBoundary::value('message')), TeamBoundary::value('revision'), $moderation);
    }
    /** @param array{row:array<string,string|null>,revision:string} $context */
    private static function form(array $context, bool $moderation): void
    {
        $id = (int) ($context['row']['id'] ?? 0);
        echo '<form method="post">';
        wp_nonce_field($moderation ? 'cor-mgmt-exchange-admin' : 'cor-mgmt-xchange', 'cor_mgmt_nonce', false);
        echo '<input type="hidden" name="submitted" value="1"><input type="hidden" name="message_id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="revision" value="' . esc_attr($context['revision']) . '"><div hidden><label>' . esc_html__('Leave blank', 'h3-mgmt') . '<input name="address" tabindex="-1" autocomplete="off"></label></div><p><label for="exchange-message-' . esc_attr((string) $id) . '">' . esc_html__('Your message (empty text deletes it)', 'h3-mgmt') . '</label><br><textarea id="exchange-message-' . esc_attr((string) $id) . '" name="message" rows="6" cols="65" maxlength="10000">' . esc_textarea($context['row']['message'] ?? '') . '</textarea></p><button type="submit">' . esc_html__('Save message', 'h3-mgmt') . '</button></form>';
    }
    private static function result(bool $moderation): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submitted'])) {
            try {
                self::submit($moderation);
                echo '<p role="status">' . esc_html__('Message saved. Empty messages were deleted.', 'h3-mgmt') . '</p>';
            } catch (\Throwable $error) {
                Services::get()->logger->error('exchange_rejected', $error);
                echo '<p role="alert">' . esc_html__('The change was rejected. Reload and check access, setup and concurrent edits. Your submitted text is shown below for copying.', 'h3-mgmt') . '</p><pre>' . esc_html(TeamBoundary::value('message')) . '</pre>';
            }
        }
    }
    public static function shortcode(): string
    {
        ob_start();
        echo '<section aria-label="' . esc_attr__('HitchMate XChange', 'h3-mgmt') . '"><h2>' . esc_html__('HitchMate XChange', 'h3-mgmt') . '</h2>';
        self::result(false);
        try {
            $manager = self::manager();
            $page = max(1, TeamBoundary::number(is_string($_GET['exchange_page'] ?? null) ? wp_unslash($_GET['exchange_page']) : '1'));
            global $h3_mgmt_races;
            $rows = $manager->messages((int) $h3_mgmt_races->get_active_race(), $page);
            foreach ($rows as $row) {
                $user = get_userdata((int) $row['user_id']);
                echo '<article><h3>' . esc_html($user ? (string) $user->first_name : '') . ' · ' . esc_html($row['time'] ?? '') . '</h3><p>' . nl2br(esc_html($row['message'] ?? '')) . '</p></article>';
            }
            self::pagination($page, count($rows), false);
            echo '<h3>' . esc_html__('Your own message', 'h3-mgmt') . '</h3><p>' . esc_html__('Messages are public. Include only contact details you want to publish.', 'h3-mgmt') . '</p>';
            if (is_user_logged_in()) {
                self::form($manager->context(get_current_user_id()), false);
            } else {
                echo '<p><a href="' . esc_url(wp_login_url(get_permalink() ?: home_url('/'))) . '">' . esc_html__('Log in to write a message', 'h3-mgmt') . '</a></p>';
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('exchange_read_failed', $error);
            echo '<p role="alert">' . esc_html__('The notice board needs a setup review.', 'h3-mgmt') . '</p>';
        }
        echo '</section>';
        return (string) ob_get_clean();
    }
    private static function pagination(int $page, int $count, bool $admin): void
    {
        $base = $admin ? admin_url('tools.php?page=cor-mgmt-exchange') : remove_query_arg('exchange_page');
        echo '<nav aria-label="' . esc_attr__('Notice board pages', 'h3-mgmt') . '">';
        if ($page > 1) {
            echo '<a href="' . esc_url(add_query_arg('exchange_page', $page - 1, $base)) . '">' . esc_html__('Previous', 'h3-mgmt') . '</a> ';
        }
        if ($count === 30) {
            echo '<a href="' . esc_url(add_query_arg('exchange_page', $page + 1, $base)) . '">' . esc_html__('Next', 'h3-mgmt') . '</a>';
        }
        echo '</nav>';
    }
    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('Not permitted.', 'h3-mgmt') . '</p>';
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('HitchMate XChange moderation', 'h3-mgmt') . '</h1>';
        self::result(true);
        try {
            $manager = self::manager();
            $page = TeamBoundary::number(is_string($_GET['exchange_page'] ?? null) ? wp_unslash($_GET['exchange_page']) : '1');
            $rows = $manager->messages(0, $page, true, get_current_user_id());
            echo '<p>' . esc_html__('Global notice board, including older entries. Moderation preserves an audit trail. No automatic cleanup is configured.', 'h3-mgmt') . '</p>';
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                echo '<details><summary>' . esc_html('#' . $id . ' · User ' . $row['user_id'] . ' · ' . $row['time']) . '</summary>';
                self::form($manager->context(get_current_user_id(), $id, true), true);
                foreach ($manager->history(get_current_user_id(), $id) as $entry) {
                    echo '<p>' . esc_html($entry['created_at'] . ' · ' . $entry['operation'] . ' · User ' . $entry['actor_id']) . '</p>';
                }
                echo '</details>';
            }
            self::pagination(max(1, $page), count($rows), true);
            echo '<h2>' . esc_html__('Recent moderation and message decisions', 'h3-mgmt') . '</h2>';
            foreach ($manager->recentHistory(get_current_user_id()) as $entry) {
                echo '<p>' . esc_html('#' . $entry['message_id'] . ' · ' . $entry['operation'] . ' · User ' . $entry['actor_id'] . ' · ' . $entry['created_at']) . '</p>';
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('exchange_admin_failed', $error);
            echo '<p role="alert">' . esc_html__('Review module setup and access rights.', 'h3-mgmt') . '</p>';
        }
        echo '</div>';
    }
}
