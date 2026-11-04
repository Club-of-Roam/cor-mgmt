<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Events\EventEditor;
use Cor\Mgmt\Application\Teams\ParticipationException;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

final class TeamAdmin
{
    public static function control(bool $participants = false): void
    {
        $resource = $participants ? 'participants' : 'teams';
        if (!\H3_MGMT_Security::admin_request($resource)) {
            echo \H3_MGMT_Security::error(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped security message.
            return;
        }
        $action = is_string($_GET['todo'] ?? null) ? $_GET['todo'] : '';
        $id = \H3_MGMT_Security::id($_GET['id'] ?? 0);
        $message = '';
        try {
            if (!$participants && in_array($action, ['new','edit'], true)) {
                self::form($id);
                return;
            }
            if ($action !== '') {
                $manager = TeamBoundary::manager();
                if ($participants) {
                    $kind = str_replace('bulk-', '', $action);
                    $field = in_array($kind, ['paid','unpaid'], true) ? 'paid' : 'waiver';
                    $ids = str_starts_with($action, 'bulk-') ? array_values(array_map('intval', (array) ($_GET['bulk'] ?? []))) : [$id];
                    $result = $manager->confirm($ids, $field, in_array($kind, ['paid','waiver-set'], true));
                } elseif ($action === 'delete') {
                    $result = $manager->delete($id);
                } elseif ($action === 'save') {
                    $result = self::save($id);
                } else {
                    throw new ParticipationException('invalid_action');
                }
                TeamBoundary::notify($result);
                $message = __('Changes saved.', 'h3-mgmt');
            }
        } catch (\Throwable $error) {
            $message = TeamBoundary::message($error);
        }
        self::listing($participants, $message);
    }
    /** @return array<string,mixed> */
    private static function save(int $id): array
    {
        $repo = TeamBoundary::repository();
        $team = $id ? $repo->find('team', $id) : [];
        $raceId = TeamBoundary::number(TeamBoundary::value('race_id', (string) ($team['race_id'] ?? 0)));
        $race = EventEditor::flatten($repo->find('race', $raceId));
        $members = [];
        $languages = [];
        $count = max((int) ($race['num_teammember'] ?? 3), $id ? count($repo->related('teammate', 'team_id', (string) $id)) : 0);
        foreach (range(1, min(100, max(1, $count))) as $slot) {
            $raw = trim(TeamBoundary::value('member' . $slot));
            if ($raw === '' || $raw === '0') {
                continue;
            }
            $user = ctype_digit($raw) ? get_userdata(TeamBoundary::number($raw)) : (get_user_by('login', $raw) ?: get_user_by('email', $raw));
            if (!$user) {
                throw new ParticipationException('invalid_member');
            }
            $members[] = $user->ID;
            $languages[$user->ID] = TeamBoundary::language(TeamBoundary::value('member' . $slot . '_language', 'en'));
        }
        $values = TeamBoundary::teamInput();
        $upload = null;
        if (!empty($_FILES['team_pic']['name'])) {
            if (!\H3_MGMT_Security::can_object('teams', 'edit', $id) || (int) ($race['status'] ?? -1) === 4) {
                throw new ParticipationException('not_permitted');
            }
            $upload = \H3_MGMT_Security::upload('team_pic');
            if (is_wp_error($upload)) {
                throw new ParticipationException('invalid_upload');
            }
            $values['team_pic'] = $upload['url'];
        }
        try {
            return TeamBoundary::manager()->administer($raceId, $id, $values, $members, $languages, isset($_POST['route_id']) ? TeamBoundary::number(TeamBoundary::value('route_id')) : null, TeamBoundary::value('cor_event_revision'));
        } catch (\Throwable $error) {
            if (is_array($upload) && is_string($upload['file'] ?? null)) {
                wp_delete_file($upload['file']);
            }
            throw $error;
        }
    }
    private static function ownScope(): bool
    {
        return current_user_can('h3_mgmt_view_own_teams') && !current_user_can('h3_mgmt_edit_teams') && !current_user_can('manage_options');
    }
    private static function form(int $id): void
    {
        if (!\H3_MGMT_Security::can_object('teams', $id ? 'view' : 'edit', $id)) {
            throw new ParticipationException('not_permitted');
        }
        $repo = TeamBoundary::repository();
        $team = $id ? $repo->find('team', $id) : [];
        if ($id && self::ownScope() && !\H3_MGMT_Security::assigned_route((int) ($team['route_id'] ?? 0))) {
            throw new ParticipationException('not_permitted');
        }
        $raceId = (int) ($team['race_id'] ?? ($_GET['race_id'] ?? $_POST['race_id'] ?? 0));
        if (!$raceId) {
            global $wpdb;
            $db = new Database($wpdb);
            echo '<div class="wrap"><h1>' . esc_html__('Create team', 'h3-mgmt') . '</h1><form method="get"><input type="hidden" name="page" value="h3-mgmt-teams"><input type="hidden" name="todo" value="new"><label for="cor-team-race">' . esc_html__('Event', 'h3-mgmt') . '</label><select id="cor-team-race" name="race_id">';
            foreach ($db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races'))) as $race) {
                echo '<option value="' . esc_attr((string) $race['id']) . '">' . esc_html((string) $race['name']) . '</option>';
            }
            echo '</select><button class="button" type="submit">' . esc_html__('Continue', 'h3-mgmt') . '</button></form></div>';
            return;
        }
        $race = EventEditor::flatten($repo->find('race', $raceId));
        if (!$race) {
            throw new ParticipationException('missing_race');
        }
        $fields = [['type' => 'hidden','id' => 'race_id','value' => (string) $raceId]];
        foreach (['team_name' => __('Team name', 'h3-mgmt'), 'description' => __('Description', 'h3-mgmt'), 'team_phone' => __('Team phone', 'h3-mgmt'), 'donation_goal' => __('Donation goal', 'h3-mgmt')] as $field => $label) {
            $fields[] = ['type' => $field === 'description' ? 'textarea' : 'text','id' => $field,'label' => $label,'value' => $team[$field] ?? ($field === 'donation_goal' ? '0' : '')];
        }
        $fields[] = ['type' => 'file','id' => 'team_pic','label' => __('Replace team picture', 'h3-mgmt')];
        foreach (range(1, 5) as $number) {
            /* translators: %d: question number. */
            $fields[] = ['type' => 'textarea','id' => 'meta_' . $number,'label' => sprintf(__('Additional answer %d', 'h3-mgmt'), $number),'value' => $team['meta_' . $number] ?? ''];
        }
        $members = $id ? $repo->related('teammate', 'team_id', (string) $id) : [];
        $memberFields = [];
        foreach (range(1, min(100, max(1, (int) ($race['num_teammember'] ?? 3), count($members)))) as $slot) {
            $member = $members[$slot - 1] ?? [];
            $person = get_userdata((int) ($member['user_id'] ?? 0));
            /* translators: %d: member position. */
            $memberFields[] = ['type' => 'text','id' => 'member' . $slot,'label' => sprintf(__('Participant %d (username, email or user ID)', 'h3-mgmt'), $slot),'value' => $person ? $person->user_login : ''];
            $memberFields[] = ['type' => 'text','id' => 'member' . $slot . '_language','label' => __('Language code', 'h3-mgmt'),'value' => $member['language'] ?? 'en'];
        }
        $options = [['value' => 0,'label' => __('No route', 'h3-mgmt')]];
        foreach ($repo->related('route', 'race_id', (string) $raceId) as $route) {
            $options[] = ['value' => $route['id'],'label' => $route['name']];
        }
        $memberFields[] = ['type' => 'select','id' => 'route_id','label' => __('Route', 'h3-mgmt'),'value' => $team['route_id'] ?? '0','options' => $options];
        EventForm::render('team', $id, [['title' => __('Team profile', 'h3-mgmt'),'fields' => $fields],['title' => __('Memberships and route', 'h3-mgmt'),'fields' => $memberFields]], $id ? __('Edit team', 'h3-mgmt') : __('Create team', 'h3-mgmt'), TeamBoundary::manager()->revision($raceId, $id), false);
    }
    private static function listing(bool $participants, string $message): void
    {
        global $wpdb;
        $db = new Database($wpdb);
        $slug = $participants ? 'h3-mgmt-participants' : 'h3-mgmt-teams';
        $base = add_query_arg('page', $slug, admin_url('admin.php'));
        $race = isset($_GET['race_filter']) && is_scalar($_GET['race_filter']) ? absint($_GET['race_filter']) : 0;
        $page = isset($_GET['team_page']) && is_scalar($_GET['team_page']) ? max(1, min(1000000, absint($_GET['team_page']))) : 1;
        $routeFilter = isset($_GET['route_filter']) && is_scalar($_GET['route_filter']) ? absint($_GET['route_filter']) : 0;
        $state = isset($_GET['complete_filter']) && in_array($_GET['complete_filter'], ['0', '1'], true) ? $_GET['complete_filter'] : '';
        $sorts = $participants ? ['id' => 'm.id', 'team' => 't.team_name', 'name' => 'u.display_name', 'paid' => 'm.paid', 'waiver' => 'm.waiver'] : ['id' => 't.id', 'team' => 't.team_name', 'complete' => 't.complete', 'route' => 't.route_id'];
        $sort = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'id';
        $ascending = ($_GET['direction'] ?? '') === 'asc';
        [$sortAlias, $sortColumn] = explode('.', $sorts[$sort] ?? $sorts['id']);
        $joins = $participants ? $db->prepare(' FROM %i m JOIN %i t ON t.id=m.team_id LEFT JOIN %i u ON u.ID=m.user_id', $db->table('h3_mgmt_teammates'), $db->table('h3_mgmt_teams'), $wpdb->users) : $db->prepare(' FROM %i t', $db->table('h3_mgmt_teams'));
        $where = $race ? $db->prepare(' WHERE t.race_id=%d', $race) : ' WHERE 1=1';
        if ($routeFilter) {
            $where .= $db->prepare(' AND t.route_id=%d', $routeFilter);
        }
        if ($state !== '') {
            $where .= $db->prepare(' AND t.complete=%s', $state);
        }
        if (self::ownScope()) {
            $where .= $db->prepare(' AND t.route_id IN (SELECT id FROM %i WHERE user_id=%d)', $db->table('h3_mgmt_routes'), get_current_user_id());
        }
        $count = (int) $db->scalar('SELECT COUNT(*)' . $joins . $where);
        $pages = max(1, (int) ceil($count / 50));
        $page = min($pages, $page);
        $columns = $participants ? 'SELECT m.*,t.team_name,t.race_id,t.route_id' : 'SELECT t.*';
        $rows = $db->rows($columns . $joins . $where . $db->prepare(' ORDER BY %i.%i', $sortAlias, $sortColumn) . ($ascending ? ' ASC' : ' DESC') . $db->prepare(' LIMIT 50 OFFSET %d', ($page - 1) * 50));
        $title = $participants ? __('Participants', 'h3-mgmt') : __('Teams', 'h3-mgmt');
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        if ($message !== '') {
            echo '<p role="status">' . esc_html($message) . '</p>';
        }
        if (!$participants && \H3_MGMT_Security::can_object('teams', 'edit')) {
            echo '<p><a class="page-title-action" href="' . esc_url(add_query_arg('todo', 'new', $base)) . '">' . esc_html__('Add team', 'h3-mgmt') . '</a></p>';
        }
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr($slug) . '"><label for="cor-filter-race">' . esc_html__('Event', 'h3-mgmt') . '</label><select id="cor-filter-race" name="race_filter"><option value="0">' . esc_html__('All events', 'h3-mgmt') . '</option>';
        foreach ($db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races'))) as $event) {
            echo '<option value="' . esc_attr((string) $event['id']) . '"' . selected($race, (int) $event['id'], false) . '>' . esc_html((string) $event['name']) . '</option>';
        }
        echo '</select> <label for="cor-filter-route">' . esc_html__('Route', 'h3-mgmt') . '</label><select id="cor-filter-route" name="route_filter"><option value="0">' . esc_html__('All routes', 'h3-mgmt') . '</option>';
        $routeWhere = $race ? $db->prepare(' WHERE race_id=%d', $race) : ' WHERE 1=1';
        if (self::ownScope()) {
            $routeWhere .= $db->prepare(' AND user_id=%d', get_current_user_id());
        }
        foreach ($db->rows($db->prepare('SELECT id,name FROM %i', $db->table('h3_mgmt_routes')) . $routeWhere . ' ORDER BY id') as $route) {
            echo '<option value="' . esc_attr((string) $route['id']) . '"' . selected($routeFilter, (int) $route['id'], false) . '>' . esc_html((string) $route['name']) . '</option>';
        }
        echo '</select> <label for="cor-filter-complete">' . esc_html__('Team status', 'h3-mgmt') . '</label><select id="cor-filter-complete" name="complete_filter">';
        foreach (['' => __('All statuses', 'h3-mgmt'), '0' => __('Incomplete', 'h3-mgmt'), '1' => __('Complete', 'h3-mgmt')] as $key => $label) {
            echo '<option value="' . esc_attr((string) $key) . '"' . selected($state, (string) $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <label for="cor-team-sort">' . esc_html__('Sort by', 'h3-mgmt') . '</label><select id="cor-team-sort" name="sort">';
        foreach ($sorts as $key => $column) {
            $label = match ($key) {
                'id' => 'ID', 'team' => __('Team', 'h3-mgmt'), 'name' => __('Participant', 'h3-mgmt'), 'paid' => __('Payment', 'h3-mgmt'), 'waiver' => __('Waiver', 'h3-mgmt'), 'route' => __('Route', 'h3-mgmt'), default => __('Status', 'h3-mgmt'),
            };
            echo '<option value="' . esc_attr($key) . '"' . selected($sort, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <label for="cor-team-direction">' . esc_html__('Order', 'h3-mgmt') . '</label><select id="cor-team-direction" name="direction"><option value="desc"' . selected($ascending, false, false) . '>' . esc_html__('Descending', 'h3-mgmt') . '</option><option value="asc"' . selected($ascending, true, false) . '>' . esc_html__('Ascending', 'h3-mgmt') . '</option></select> <button class="button" type="submit">' . esc_html__('Filter', 'h3-mgmt') . '</button></form>';
        if ($participants) {
            echo '<form id="cor-participant-bulk" method="post" action="' . esc_url($base) . '">';
            wp_nonce_field(\H3_MGMT_Security::admin_nonce('participants'), 'cor_mgmt_nonce', false);
            echo '<p><label for="cor-bulk-action">' . esc_html__('Selected participants', 'h3-mgmt') . '</label> <select id="cor-bulk-action" name="todo">';
            foreach (['bulk-paid' => __('Confirm payment', 'h3-mgmt'), 'bulk-unpaid' => __('Undo payment confirmation', 'h3-mgmt'), 'bulk-waiver-set' => __('Confirm waiver', 'h3-mgmt'), 'bulk-waiver-unset' => __('Undo waiver confirmation', 'h3-mgmt')] as $action => $label) {
                echo '<option value="' . esc_attr($action) . '">' . esc_html($label) . '</option>';
            }
            echo '</select> <button class="button" type="submit">' . esc_html__('Apply', 'h3-mgmt') . '</button></p></form>';
        }
        echo '<table class="widefat striped"><thead><tr><th scope="col">ID</th><th scope="col">' . esc_html__('Team / event / route', 'h3-mgmt') . '</th><th scope="col">' . esc_html($participants ? __('Participant and contact details', 'h3-mgmt') : __('Status', 'h3-mgmt')) . '</th><th scope="col">' . esc_html__('Actions', 'h3-mgmt') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            echo '<tr><th scope="row">' . esc_html((string) $id) . '</th><td>' . esc_html((string) $row['team_name']) . '<br>' . esc_html__('Event', 'h3-mgmt') . ' ' . esc_html((string) $row['race_id']) . ' / ' . esc_html__('Route', 'h3-mgmt') . ' ' . esc_html((string) $row['route_id']) . '</td><td>';
            if ($participants) {
                $person = get_userdata((int) $row['user_id']);
                echo esc_html($person ? $person->display_name : __('Unavailable account', 'h3-mgmt'));
                if ($person) {
                    echo '<br>' . esc_html($person->user_email);
                    foreach (['mobile' => __('Phone', 'h3-mgmt'), 'shirt_size' => __('Shirt size', 'h3-mgmt'), 'public_mobile_inf' => __('Mobile partner choice', 'h3-mgmt')] as $key => $label) {
                        $value = get_user_meta($person->ID, $key, true);
                        echo '<br>' . esc_html($label) . ': ' . esc_html(is_scalar($value) ? (string) $value : '');
                    }
                }
                echo '<br>' . esc_html__('Language', 'h3-mgmt') . ': ' . esc_html((string) $row['language']);
                echo '<br>' . esc_html__('Payment', 'h3-mgmt') . ': ' . esc_html($row['paid'] === '1' ? __('Confirmed', 'h3-mgmt') : __('Pending', 'h3-mgmt'));
                echo '<br>' . esc_html__('Waiver', 'h3-mgmt') . ': ' . esc_html($row['waiver'] === '1' ? __('Confirmed', 'h3-mgmt') : __('Pending', 'h3-mgmt'));
            } else {
                echo esc_html($row['complete'] === '1' ? __('Complete', 'h3-mgmt') : __('Incomplete', 'h3-mgmt'));
            }
            echo '</td><td>';
            if ($participants && \H3_MGMT_Security::phase((int) $row['race_id']) < 3 && \H3_MGMT_Security::can_object('participants', 'edit', $id)) {
                echo '<label><input type="checkbox" name="bulk[]" form="cor-participant-bulk" value="' . esc_attr((string) $id) . '"> ' . esc_html__('Select participant', 'h3-mgmt') . '</label>' ;
                self::button($base, 'participants', $id, $row['paid'] === '1' ? 'unpaid' : 'paid', $row['paid'] === '1' ? __('Undo payment confirmation', 'h3-mgmt') : __('Confirm payment', 'h3-mgmt'));
                self::button($base, 'participants', $id, $row['waiver'] === '1' ? 'waiver-unset' : 'waiver-set', $row['waiver'] === '1' ? __('Undo waiver confirmation', 'h3-mgmt') : __('Confirm waiver', 'h3-mgmt'));
            } elseif (!$participants) {
                echo '<a href="' . esc_url(add_query_arg(['todo' => 'edit','id' => $id], $base)) . '">' . esc_html__('View / edit', 'h3-mgmt') . '</a>';
                if (\H3_MGMT_Security::can_object('teams', 'delete', $id) && !\H3_MGMT_Security::archived((int) $row['race_id'])) {
                    self::button($base, 'teams', $id, 'delete', __('Delete', 'h3-mgmt'));
                }
            }
            echo '</td></tr>';
        }
        echo '</tbody></table><p>';
        /* translators: 1: current page, 2: total pages, 3: record count. */
        echo esc_html(sprintf(__('Page %1$d of %2$d (%3$d records)', 'h3-mgmt'), $page, $pages, $count));
        foreach ([-1 => __('Previous', 'h3-mgmt'),1 => __('Next', 'h3-mgmt')] as $offset => $label) {
            if ($page + $offset > 0 && $page + $offset <= $pages) {
                echo ' <a class="button" href="' . esc_url(add_query_arg(['team_page' => $page + $offset,'race_filter' => $race,'route_filter' => $routeFilter,'complete_filter' => $state,'sort' => $sort,'direction' => $ascending ? 'asc' : 'desc'], $base)) . '">' . esc_html($label) . '</a>';
            }
        }
        echo '</p></div>';
    }
    private static function button(string $url, string $resource, int $id, string $action, string $label): void
    {
        echo '<form method="post" action="' . esc_url($url) . '">';
        wp_nonce_field(\H3_MGMT_Security::admin_nonce($resource), 'cor_mgmt_nonce', false);
        echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="todo" value="' . esc_attr($action) . '"><button class="button-link" type="submit"' . ($action === 'delete' ? ' data-confirm="' . esc_attr__('Really delete this team?', 'h3-mgmt') . '"' : '') . '>' . esc_html($label) . '</button></form>';
    }
}
