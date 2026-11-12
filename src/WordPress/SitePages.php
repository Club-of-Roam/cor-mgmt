<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use RuntimeException;

/** Explicit page provisioning; activation never publishes or overwrites content. */
final class SitePages
{
    private const TAGS = [
        'team' => 'h3-team-dashboard',
        'teams' => 'h3-teams',
        'results' => 'h3-ranking-table',
        'ticker' => 'h3-liveticker',
        'map' => 'h3-ticker-map',
        'donations' => 'h3-sponsoring-form',
    ];

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', static function (string $hook): void {
            if ($hook === 'toplevel_page_h3-mgmt-races') {
                wp_enqueue_script('cor-mgmt-page-pickers', plugins_url('js/cor-mgmt-page-pickers.js', dirname(__DIR__, 2) . '/h3-management.php'), [], '1', true);
            }
        });
        add_action('admin_init', static function (): void {
            global $pagenow;
            if ($pagenow === 'tools.php' && ($_GET['page'] ?? '') === 'cor-mgmt-pages' && current_user_can('manage_options')) {
                $race = is_string($_GET['race_id'] ?? null) && preg_match('/^[1-9][0-9]{0,8}$/D', $_GET['race_id']) ? (int) $_GET['race_id'] : 0;
                wp_safe_redirect(self::settingsUrl($race));
                exit;
            }
        });
        add_action('admin_post_cor_mgmt_pages', static function (): void {
            try {
                self::submit();
            } catch (\Throwable $error) {
                Services::get()->logger->error('page_setup_failed', $error);
                wp_die(esc_html(self::problem($error)), '', ['response' => 400, 'back_link' => true]);
            }
            wp_safe_redirect(add_query_arg('cor_pages_saved', '1', self::settingsUrl(self::number($_POST['race_id'] ?? null))));
            exit;
        });
    }

    public static function settingsUrl(int $race): string
    {
        $arguments = ['page' => 'h3-mgmt-races'];
        if ($race > 0) {
            $arguments += ['todo' => 'edit', 'id' => $race];
        }
        return add_query_arg($arguments, admin_url('admin.php')) . ($race > 0 ? '#cor-mgmt-event-pages' : '');
    }

    private static function number(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,8}$/D', $value)) {
            throw new RuntimeException('invalid_page_reference');
        }
        return (int) $value;
    }

    public static function content(int $race, string $kind): string
    {
        if ($race < 1 || !isset(self::TAGS[$kind])) {
            throw new RuntimeException('invalid_page_kind');
        }
        return '[' . self::TAGS[$kind] . ' race="' . $race . '"]';
    }

    /** @return array<string,int> */
    private static function mapping(int $race): array
    {
        $stored = get_option('cor_mgmt_pages_' . $race, []);
        if (!is_array($stored)) {
            throw new RuntimeException('page_mapping_requires_review');
        }
        $result = [];
        foreach ($stored as $key => $id) {
            if (!isset(self::TAGS[$key]) || !is_int($id) || $id < 1) {
                throw new RuntimeException('page_mapping_requires_review');
            }
            $result[$key] = $id;
        }
        return $result;
    }

    /** Prefer a published bound page; retain validated internal legacy links during transition. */
    public static function url(int $race, string $kind): string
    {
        try {
            $id = self::mapping($race)[$kind] ?? 0;
            $page = $id ? get_post($id) : null;
            if (
                !$page instanceof \WP_Post || $page->post_type !== 'page' || $page->post_status !== 'publish'
                || $page->post_password !== '' || !str_contains($page->post_content, self::content($race, $kind))
            ) {
                return self::legacyUrl($race, $kind);
            }
            return (string) get_permalink($page);
        } catch (RuntimeException) {
            return '';
        }
    }

    /** Retain existing internal links until a matching page is published; never rewrite event data. */
    private static function legacyUrl(int $race, string $kind): string
    {
        $field = match ($kind) {
            'teams' => 'team_overview_link', 'donations' => 'donation_link_link', default => '',
        };
        if ($field === '') {
            return '';
        }
        global $h3_mgmt_races;
        $settings = $h3_mgmt_races->get_race_setting($race);
        $value = is_array($settings) ? ($settings[$field] ?? '') : '';
        if (!is_string($value) || trim($value) === '' || str_starts_with(trim($value), '//')) {
            return '';
        }
        $value = trim($value);
        $url = preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) ? $value : site_url('/' . ltrim($value, '/'));
        $parts = wp_parse_url($url);
        $site = wp_parse_url(site_url());
        if (
            !is_array($parts) || !is_array($site) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || strtolower($parts['host'] ?? '') !== strtolower($site['host'] ?? '')
            || ($parts['port'] ?? null) !== ($site['port'] ?? null) || isset($parts['user']) || isset($parts['pass'])
        ) {
            return '';
        }
        return esc_url_raw($url);
    }

    /** @param array<string,int|string> $arguments */
    public static function link(int $race, string $kind, array $arguments = []): string
    {
        $url = self::url($race, $kind);
        return $url === '' ? '' : add_query_arg($arguments, $url);
    }

    public static function submit(): int
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options')
            || !current_user_can('edit_pages') || !is_string($_POST['cor_mgmt_nonce'] ?? null)
            || !wp_verify_nonce(wp_unslash($_POST['cor_mgmt_nonce']), 'cor-mgmt-pages')
        ) {
            throw new RuntimeException('page_setup_forbidden');
        }
        if (!Lifecycle::ready()) {
            throw new RuntimeException('page_setup_requires_installation');
        }
        $race = self::number($_POST['race_id'] ?? null);
        $kind = $_POST['kind'] ?? null;
        $operation = $_POST['page_operation'] ?? null;
        if (!is_string($kind) || !isset(self::TAGS[$kind]) || !in_array($operation, ['create', 'assign'], true)) {
            throw new RuntimeException('invalid_page_operation');
        }
        global $wpdb;
        $db = new Database($wpdb);
        return $db->locked(static function () use ($db, $race, $kind, $operation): int {
            if ($db->scalar($db->prepare('SELECT id FROM %i WHERE id=%d', $db->table('h3_mgmt_races'), $race)) === null) {
                throw new RuntimeException('invalid_event');
            }
            $mapping = self::mapping($race);
            $content = self::content($race, $kind);
            if ($operation === 'assign') {
                $id = self::number($_POST['page_id'] ?? null);
            } elseif (isset($mapping[$kind])) {
                $id = $mapping[$kind];
            } else {
                $owned = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'numberposts' => 2,
                    'meta_key' => '_cor_mgmt_page', 'meta_value' => $race . ':' . $kind, 'fields' => 'ids']);
                if (count($owned) > 1) {
                    throw new RuntimeException('page_ownership_ambiguous');
                }
                $id = $owned ? (int) $owned[0] : wp_insert_post([
                    'post_type' => 'page', 'post_status' => 'draft',
                    /* translators: 1: event ID, 2: page function. */
                    'post_title' => sprintf(__('Event %1$d: %2$s', 'h3-mgmt'), $race, self::label($kind)),
                    'post_content' => $content, 'meta_input' => ['_cor_mgmt_page' => $race . ':' . $kind],
                ], true);
                if (is_wp_error($id) || !$id) {
                    throw new RuntimeException('page_creation_failed');
                }
            }
            $page = get_post($id);
            if (
                !$page instanceof \WP_Post || $page->post_type !== 'page' || !in_array($page->post_status, ['publish', 'draft', 'private', 'pending'], true)
                || !current_user_can('edit_post', $id) || !str_contains($page->post_content, $content)
            ) {
                throw new RuntimeException('page_content_requires_review');
            }
            $mapping[$kind] = $id;
            update_option('cor_mgmt_pages_' . $race, $mapping, false);
            if (self::mapping($race) !== $mapping) {
                throw new RuntimeException('page_mapping_failed');
            }
            return $id;
        });
    }

    private static function label(string $kind): string
    {
        return match ($kind) {
            'team' => __('Team Dashboard', 'h3-mgmt'),
            'teams' => __('Teams Overview', 'h3-mgmt'),
            'results' => __('Results and Rankings', 'h3-mgmt'),
            'ticker' => __('Live Ticker', 'h3-mgmt'),
            'map' => __('Ticker Map', 'h3-mgmt'),
            'donations' => __('Donations', 'h3-mgmt'),
            default => throw new RuntimeException('invalid_page_operation'),
        };
    }
    public static function problem(\Throwable $error): string
    {
        return match ($error->getMessage()) {
            'invalid_page_reference' => __('Select a matching existing WordPress page for this event. For a new page, use Create or reuse draft.', 'h3-mgmt'),
            'page_content_requires_review' => __('Choose an editable WordPress page containing the exact shortcode shown for this event and page role.', 'h3-mgmt'),
            'invalid_event' => __('The event no longer exists. Return to the event overview.', 'h3-mgmt'),
            'page_setup_forbidden' => __('Page setup requires permission to manage settings and edit pages. Reload the page and try again.', 'h3-mgmt'),
            'page_setup_requires_installation' => __('Complete the plugin setup under cor-mgmt system status before setting up event pages.', 'h3-mgmt'),
            default => __('Page setup was rejected. Check permissions, the event and the existing page content.', 'h3-mgmt'),
        };
    }
    public static function render(int $race): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response' => 403]);
        }
        global $wpdb;
        $db = new Database($wpdb);
        echo '<section id="cor-mgmt-event-pages"><h2>' . esc_html__('Event pages', 'h3-mgmt') . '</h2>';
        if (($_GET['cor_pages_saved'] ?? '') === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Page assignment saved. New drafts still need review and publication in WordPress.', 'h3-mgmt') . '</p></div>';
        }
        echo '<p>' . esc_html__('Manage event pages here in every race phase. Create drafts or assign matching existing pages. Existing content is never overwritten; review and publish drafts through WordPress.', 'h3-mgmt') . '</p>';
        if ($race < 1) {
            echo '<p>' . esc_html__('Save the new event first, then return to its settings to create or select the event pages.', 'h3-mgmt') . '</p></section>';
            return;
        }
        if (!Lifecycle::ready()) {
            echo '<p>' . esc_html__('Complete database setup in the system status first.', 'h3-mgmt') . '</p></section>';
            return;
        }
        if ($db->scalar($db->prepare('SELECT id FROM %i WHERE id=%d', $db->table('h3_mgmt_races'), $race)) !== null) {
            $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            $mapping = self::mapping($race);
            foreach (self::TAGS as $kind => $_tag) {
                echo '<h3>' . esc_html(self::label($kind)) . '</h3><p><code>' . esc_html(self::content($race, $kind)) . '</code></p>';
                $url = self::url($race, $kind);
                if ($url !== '') {
                    echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Open current page', 'h3-mgmt') . '</a></p>';
                }
                if (isset($mapping[$kind]) && current_user_can('edit_post', $mapping[$kind])) {
                    echo '<p><a href="' . esc_url((string) get_edit_post_link($mapping[$kind], 'raw')) . '">' . esc_html__('Edit assigned page or draft', 'h3-mgmt') . '</a></p>';
                }
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cor_mgmt_pages"><input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><input type="hidden" name="kind" value="' . esc_attr($kind) . '">';
                wp_nonce_field('cor-mgmt-pages', 'cor_mgmt_nonce');
                echo '<p>' . esc_html__('For a new page, create a draft here and review it in WordPress before publishing.', 'h3-mgmt') . '</p>';
                echo '<p><button class="button" name="page_operation" value="create">' . esc_html__('Create or reuse draft', 'h3-mgmt') . '</button></p></form>';
                $candidates = [];
                foreach ($pages as $page) {
                    if (current_user_can('edit_post', $page->ID) && str_contains($page->post_content, self::content($race, $kind))) {
                        $candidates[] = $page;
                    }
                }
                /* translators: %d: number of pages matching the search. */
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-search-picker data-result-text="' . esc_attr__('Matching pages: %d', 'h3-mgmt') . '"><input type="hidden" name="action" value="cor_mgmt_pages"><input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><input type="hidden" name="kind" value="' . esc_attr($kind) . '">';
                wp_nonce_field('cor-mgmt-pages', 'cor_mgmt_nonce');
                echo '<p id="cor-mgmt-page-help-' . esc_attr($kind) . '">' . esc_html__('Only editable WordPress pages containing the shortcode shown above are listed. If none exists, use Create or reuse draft.', 'h3-mgmt') . '</p>';
                echo '<p data-picker-search hidden><label for="cor-mgmt-page-search-' . esc_attr($kind) . '">' . esc_html__('Search matching pages', 'h3-mgmt') . '</label> <input type="search" id="cor-mgmt-page-search-' . esc_attr($kind) . '" autocomplete="off" aria-controls="cor-mgmt-existing-page-' . esc_attr($kind) . '"> <span role="status" aria-live="polite" data-picker-matches></span></p>';
                echo '<p><label for="cor-mgmt-existing-page-' . esc_attr($kind) . '">' . esc_html__('Existing WordPress page', 'h3-mgmt') . '</label> <select id="cor-mgmt-existing-page-' . esc_attr($kind) . '" name="page_id" style="max-width:100%" aria-describedby="cor-mgmt-page-help-' . esc_attr($kind) . '" required><option value="">' . esc_html__('Choose a matching page', 'h3-mgmt') . '</option>';
                foreach ($candidates as $page) {
                    echo '<option value="' . esc_attr((string) $page->ID) . '"' . selected((string) ($mapping[$kind] ?? 0), (string) $page->ID, false) . '>' . esc_html(($page->post_title ?: __('Untitled page', 'h3-mgmt')) . ' · #' . $page->ID) . '</option>';
                }
                echo '</select> <button class="button" name="page_operation" value="assign"' . disabled($candidates === [], true, false) . '>' . esc_html__('Assign existing page', 'h3-mgmt') . '</button></p></form>';
            }
        }
        echo '</section>';
    }
}
