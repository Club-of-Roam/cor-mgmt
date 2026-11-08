<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

/** Event-specific, theme-independent destination for queued participant links. */
final class TeamPortal
{
    public static function register(): void
    {
        add_action('admin_post_cor_mgmt_team', [self::class, 'serve']);
        add_action('admin_post_nopriv_cor_mgmt_team', [self::class, 'serve']);
    }

    public static function url(int $race): string
    {
        return add_query_arg(['action' => 'cor_mgmt_team', 'race_id' => $race], admin_url('admin-post.php'));
    }

    public static function serve(): void
    {
        try {
            $race = isset($_GET['race_id']) && is_string($_GET['race_id']) ? TeamBoundary::number(wp_unslash($_GET['race_id'])) : 0;
        } catch (\Cor\Mgmt\Application\Teams\ParticipationException) {
            $race = 0;
        }
        if ($race < 1) {
            wp_die(esc_html__('Invalid event.', 'h3-mgmt'), '', ['response' => 400]);
        }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('Referrer-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow');
        echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__('Manage your team', 'h3-mgmt') . '</title>';
        wp_head();
        echo '</head><body><main>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native renderer escapes its individual values and form attributes.
        echo TeamFrontend::dashboard($race);
        echo '</main>';
        wp_footer();
        echo '</body></html>';
        exit;
    }
}
