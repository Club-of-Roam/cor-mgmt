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
    ];

    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_management_page(__('Event pages', 'h3-mgmt'), __('Event pages', 'h3-mgmt'), 'manage_options', 'cor-mgmt-pages', [self::class, 'page']);
        });
        add_action('admin_post_cor_mgmt_pages', static function (): void {
            try {
                self::submit();
            } catch (\Throwable $error) {
                Services::get()->logger->error('page_setup_failed', $error);
                wp_die(esc_html__('Page setup was rejected. Check permissions, the event and the existing page content.', 'h3-mgmt'), '', ['response' => 400, 'back_link' => true]);
            }
            wp_safe_redirect(add_query_arg(['page' => 'cor-mgmt-pages', 'race_id' => self::number($_POST['race_id'] ?? null)], admin_url('tools.php')));
            exit;
        });
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

    /** Only a published page with the explicitly bound shortcode is a public destination. */
    public static function url(int $race, string $kind): string
    {
        try {
            $id = self::mapping($race)[$kind] ?? 0;
            $page = $id ? get_post($id) : null;
            if (
                !$page instanceof \WP_Post || $page->post_type !== 'page' || $page->post_status !== 'publish'
                || $page->post_password !== '' || !str_contains($page->post_content, self::content($race, $kind))
            ) {
                return '';
            }
            return (string) get_permalink($page);
        } catch (RuntimeException) {
            return '';
        }
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
                    'post_title' => sprintf(__('Event %1$d: %2$s', 'h3-mgmt'), $race, $kind),
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

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not permitted.', 'h3-mgmt'), '', ['response' => 403]);
        }
        global $wpdb;
        $db = new Database($wpdb);
        echo '<div class="wrap"><h1>' . esc_html__('Event pages', 'h3-mgmt') . '</h1><p>' . esc_html__('Choose an event. Create draft pages or assign existing pages containing the exact event shortcode. Existing content is never changed; publish reviewed drafts through WordPress.', 'h3-mgmt') . '</p>';
        if (!Lifecycle::ready()) {
            echo '<p>' . esc_html__('Complete database setup in the system status first.', 'h3-mgmt') . '</p></div>';
            return;
        }
        $race = 0;
        try {
            $race = self::number($_GET['race_id'] ?? null);
        } catch (RuntimeException) {
            // No selection on the initial read-only page.
        }
        echo '<form method="get"><input type="hidden" name="page" value="cor-mgmt-pages"><label>' . esc_html__('Event ID', 'h3-mgmt') . ' <input type="number" min="1" name="race_id" value="' . esc_attr((string) $race) . '" required></label> <button class="button">' . esc_html__('Select event', 'h3-mgmt') . '</button></form>';
        if ($race > 0 && $db->scalar($db->prepare('SELECT id FROM %i WHERE id=%d', $db->table('h3_mgmt_races'), $race)) !== null) {
            foreach (self::TAGS as $kind => $_tag) {
                echo '<h2>' . esc_html($kind) . '</h2><p><code>' . esc_html(self::content($race, $kind)) . '</code></p>';
                $url = self::url($race, $kind);
                if ($url !== '') {
                    echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Open published page', 'h3-mgmt') . '</a></p>';
                }
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cor_mgmt_pages"><input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><input type="hidden" name="kind" value="' . esc_attr($kind) . '">';
                wp_nonce_field('cor-mgmt-pages', 'cor_mgmt_nonce');
                echo '<p><button class="button" name="page_operation" value="create">' . esc_html__('Create or reuse draft', 'h3-mgmt') . '</button></p><p><label>' . esc_html__('Existing page ID', 'h3-mgmt') . ' <input type="number" min="1" name="page_id"></label> <button class="button" name="page_operation" value="assign">' . esc_html__('Assign existing page', 'h3-mgmt') . '</button></p></form>';
            }
        }
        echo '</div>';
    }
}
