<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Paginated, escaped WordPress tables for the three event resources. */
final class EventList
{
    /** @return array<string,string> */
    private static function columns(string $type): array
    {
        return match ($type) {
            'race' => ['name' => __('Event', 'h3-mgmt'), 'status' => __('Phase', 'h3-mgmt'), 'active' => __('Active?', 'h3-mgmt'), 'start' => __('Start', 'h3-mgmt'), 'end' => __('End', 'h3-mgmt')],
            'route' => ['name' => __('Route', 'h3-mgmt'), 'race_id' => __('Event', 'h3-mgmt'), 'max_teams' => __('Capacity', 'h3-mgmt'), 'user_id' => __('Route account', 'h3-mgmt')],
            default => ['destination' => __('Stage destination', 'h3-mgmt'), 'race_id' => __('Event', 'h3-mgmt'), 'route_id' => __('Route', 'h3-mgmt'), 'number' => __('Stage number', 'h3-mgmt'), 'country' => __('Country', 'h3-mgmt'), 'meeting_point' => __('Meeting point', 'h3-mgmt')],
        };
    }
    public static function phaseLabel(int $phase): string
    {
        return match ($phase) {
            0 => __('Before registration', 'h3-mgmt'), 1 => __('Registration open', 'h3-mgmt'),
            2 => __('Registration closed', 'h3-mgmt'), 3 => __('Race started', 'h3-mgmt'),
            4 => __('Race ended / archive', 'h3-mgmt'), default => __('Unknown phase', 'h3-mgmt'),
        };
    }
    /** @param list<array{type:string,message:string}> $messages */
    public static function render(string $type, array $messages): void
    {
        global $wpdb;
        if (!in_array($type, ['race', 'route', 'stage'], true)) {
            return;
        }
        $db = new Database($wpdb);
        $slug = 'h3-mgmt-' . $type . 's';
        $base = add_query_arg('page', $slug, admin_url('admin.php'));
        $columns = self::columns($type);
        $sort = isset($_GET['orderby']) && is_string($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'id';
        if (!isset($columns[$sort]) || $sort === 'status') {
            $sort = 'id';
        }
        $order = ($_GET['order'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
        $race = isset($_GET['race_filter']) && is_scalar($_GET['race_filter']) ? absint($_GET['race_filter']) : 0;
        $page = isset($_GET['event_page']) && is_scalar($_GET['event_page']) ? max(1, min(1000000, absint($_GET['event_page']))) : 1;
        $routeFilter = isset($_GET['route_filter']) && is_scalar($_GET['route_filter']) ? absint($_GET['route_filter']) : 0;
        $numberFilter = isset($_GET['number_filter']) && is_string($_GET['number_filter']) && preg_match('/^[0-9]+$/D', $_GET['number_filter']) ? $_GET['number_filter'] : '';
        $countryFilter = isset($_GET['country_filter']) && is_string($_GET['country_filter']) ? sanitize_text_field(wp_unslash($_GET['country_filter'])) : '';
        $filters = ['race_filter' => $race, 'route_filter' => $routeFilter, 'number_filter' => $numberFilter, 'country_filter' => $countryFilter];
        $where = $type !== 'race' && $race ? $db->prepare(' WHERE race_id=%d', $race) : '';
        if ($type === 'stage') {
            foreach (['route_id' => $routeFilter ? (string) $routeFilter : '', 'number' => $numberFilter, 'country' => $countryFilter] as $field => $filter) {
                if ($filter !== '') {
                    $where .= ($where === '' ? ' WHERE ' : ' AND ') . $db->prepare('%i=%s', $field, $filter);
                }
            }
        }
        $routes = $db->rows($db->prepare('SELECT id,name FROM %i ORDER BY name', $db->table('h3_mgmt_routes')));
        $routeNames = [];
        foreach ($routes as $route) {
            $routeNames[(int) $route['id']] = (string) $route['name'];
        }
        $table = $db->table('h3_mgmt_' . $type . 's');
        $total = (int) $db->scalar($db->prepare('SELECT COUNT(*) FROM %i', $table) . $where);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($page, $pages);
        $rows = $db->rows($db->prepare('SELECT * FROM %i', $table) . $where . $db->prepare(' ORDER BY %i ', $sort) . $order . $db->prepare(' LIMIT 50 OFFSET %d', ($page - 1) * 50));
        $races = $db->rows($db->prepare('SELECT id,name FROM %i ORDER BY name', $db->table('h3_mgmt_races')));
        $names = [];
        foreach ($races as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }
        $title = match ($type) {
            'race' => __('Events / Races', 'h3-mgmt'), 'route' => __('Routes', 'h3-mgmt'), default => __('Stages', 'h3-mgmt'),
        };
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        foreach ($messages as $message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message['message']) . '</p></div>';
        }
        if (\H3_MGMT_Security::can_object($type . 's', 'edit')) {
            echo '<p><a class="page-title-action" href="' . esc_url(add_query_arg('todo', 'new', $base)) . '">' . esc_html__('Add new', 'h3-mgmt') . '</a></p>';
        }
        echo '<p>' . esc_html__('Fields follow the race phase. Complete teams occupy route capacity; incomplete teams do not. Archived races are read-only.', 'h3-mgmt') . '</p>';
        if ($type !== 'race') {
            echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="' . esc_attr($slug) . '"><label for="cor-race-filter">' . esc_html__('Event', 'h3-mgmt') . '</label> <select id="cor-race-filter" name="race_filter"><option value="0">' . esc_html__('All events', 'h3-mgmt') . '</option>';
            foreach ($names as $id => $name) {
                echo '<option value="' . esc_attr((string) $id) . '"' . selected($race, $id, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select> ';
            if ($type === 'stage') {
                echo '<label for="cor-route-filter">' . esc_html__('Route', 'h3-mgmt') . '</label> <select id="cor-route-filter" name="route_filter"><option value="0">' . esc_html__('All routes', 'h3-mgmt') . '</option>';
                foreach ($routeNames as $id => $name) {
                    echo '<option value="' . esc_attr((string) $id) . '"' . selected($routeFilter, $id, false) . '>' . esc_html($name) . '</option>';
                }
                echo '</select> <label for="cor-number-filter">' . esc_html__('Stage number', 'h3-mgmt') . '</label> <input id="cor-number-filter" name="number_filter" value="' . esc_attr($numberFilter) . '" size="4"> ';
                echo '<label for="cor-country-filter">' . esc_html__('Country', 'h3-mgmt') . '</label> <input id="cor-country-filter" name="country_filter" value="' . esc_attr($countryFilter) . '"> ';
            }
            echo '<button class="button" type="submit">' . esc_html__('Filter', 'h3-mgmt') . '</button></form>';
        }
        $bulk = $type !== 'race';
        echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html($title) . '</caption><thead><tr><th scope="col">ID</th>';
        foreach ($columns as $key => $label) {
            echo '<th scope="col"><a href="' . esc_url(add_query_arg(['orderby' => $key, 'order' => $sort === $key && $order === 'ASC' ? 'DESC' : 'ASC'] + $filters, $base)) . '">' . esc_html($label) . '</a></th>';
        }
        if ($bulk) {
            echo '<th scope="col">' . esc_html__('Select', 'h3-mgmt') . '</th>';
        }
        echo '<th scope="col">' . esc_html__('Actions', 'h3-mgmt') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $parent = $type === 'race' ? $id : (int) $row['race_id'];
            $phase = \H3_MGMT_Security::phase($parent);
            echo '<tr><th scope="row">' . esc_html((string) $id) . '</th>';
            foreach ($columns as $key => $label) {
                $value = (string) ($row[$key] ?? '');
                if ($key === 'status') {
                    $value = self::phaseLabel($phase);
                } elseif ($key === 'race_id') {
                    $value = $names[(int) $value] ?? $value;
                } elseif ($key === 'route_id') {
                    $value = $routeNames[(int) $value] ?? $value;
                } elseif ($key === 'user_id') {
                    $account = get_userdata((int) $value);
                    $value = $account ? $account->user_login : '';
                } elseif ($key === 'active') {
                    $value = $value === '1' ? __('Yes', 'h3-mgmt') : __('No', 'h3-mgmt');
                } elseif (in_array($key, ['start', 'end'], true)) {
                    $value = (int) $value ? (wp_date('Y-m-d', (int) $value) ?: '') : '';
                } elseif ($key === 'max_teams') {
                    $occupied = $db->scalar($db->prepare('SELECT COUNT(*) FROM %i WHERE route_id=%d AND complete=1', $db->table('h3_mgmt_teams'), $id));
                    $value = (string) $occupied . ' / ' . $value;
                }
                echo '<td>' . esc_html($value) . '</td>';
            }
            if ($bulk) {
                echo '<td>';
                if ($phase === 0 && \H3_MGMT_Security::can_object($type . 's', 'delete', $id)) {
                    /* translators: %d: record identifier. */
                    echo '<input type="checkbox" name="bulk[]" form="cor-event-bulk" value="' . esc_attr((string) $id) . '" aria-label="' . esc_attr(sprintf(__('Select record %d', 'h3-mgmt'), $id)) . '">';
                }
                echo '</td>';
            }
            echo '<td><a href="' . esc_url(add_query_arg(['todo' => 'edit', 'id' => $id], $base)) . '">' . esc_html__('View / edit', 'h3-mgmt') . '</a>';
            if ($phase >= 0 && $phase < 4 && \H3_MGMT_Security::can_object($type . 's', 'edit', $id)) {
                if ($type === 'race') {
                    self::action($base, $type, $id, 'active', __('Make active', 'h3-mgmt'));
                }
                if ($phase === 0 && \H3_MGMT_Security::can_object($type . 's', 'delete', $id)) {
                    self::action($base, $type, $id, 'delete', __('Delete', 'h3-mgmt'));
                }
            }
            echo '</td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="' . esc_attr((string) (count($columns) + 2 + ($bulk ? 1 : 0))) . '">' . esc_html__('No records found.', 'h3-mgmt') . '</td></tr>';
        }
        echo '</tbody></table><nav aria-label="' . esc_attr__('Table pages', 'h3-mgmt') . '"><p>';
        /* translators: 1: current page, 2: total pages, 3: total records. */
        echo esc_html(sprintf(__('Page %1$d of %2$d (%3$d records)', 'h3-mgmt'), $page, $pages, $total));
        foreach ([-1 => __('Previous', 'h3-mgmt'), 1 => __('Next', 'h3-mgmt')] as $offset => $label) {
            if ($page + $offset > 0 && $page + $offset <= $pages) {
                echo ' <a class="button" href="' . esc_url(add_query_arg(['event_page' => $page + $offset, 'orderby' => $sort, 'order' => $order] + $filters, $base)) . '">' . esc_html($label) . '</a>';
            }
        }
        echo '</p></nav>';
        if ($bulk) {
            echo '<form id="cor-event-bulk" method="post" action="' . esc_url($base) . '">';
            wp_nonce_field(\H3_MGMT_Security::admin_nonce($type . 's'), 'cor_mgmt_nonce', false);
            echo '<input type="hidden" name="todo" value="bulk-delete"><button class="button" type="submit" data-confirm="' . esc_attr__('Really delete the selected records?', 'h3-mgmt') . '">' . esc_html__('Delete selected', 'h3-mgmt') . '</button></form>';
        }
        echo '</div>';
    }
    private static function action(string $url, string $type, int $id, string $action, string $label): void
    {
        echo '<form method="post" action="' . esc_url($url) . '">';
        wp_nonce_field(\H3_MGMT_Security::admin_nonce($type . 's'), 'cor_mgmt_nonce', false);
        foreach (['todo' => $action, 'id' => (string) $id, 'cor_event_revision' => EventAdmin::editor()->revision($type, $id)] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        echo '<button class="button-link" type="submit"' . ($action === 'delete' ? ' data-confirm="' . esc_attr__('Really delete this record?', 'h3-mgmt') . '"' : '') . '>' . esc_html($label) . '</button></form>';
    }
}
