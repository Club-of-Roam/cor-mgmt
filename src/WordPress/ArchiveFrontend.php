<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Domain\DonationAmounts;
use Cor\Mgmt\Domain\EuroAmount;
use Cor\Mgmt\Infrastructure\WordPress\Archive;

/** Public projections read only sealed snapshot data, never current account/profile rows. */
final class ArchiveFrontend
{
    /** @return null|array<string,string|null> Null means ordinary live data. */
    public static function frozen(int $race, string $kind, int $key): ?array
    {
        $archive = ArchiveBoundary::archive();
        $id = $archive->published($race);
        return $id === '' ? null : $archive->row($id, $kind, $key);
    }

    public static function frozenImage(int $race, string $reference): ?string
    {
        $archive = ArchiveBoundary::archive();
        $id = $archive->published($race);
        return $id === '' ? null : $archive->imageUrl($id, $reference);
    }

    /**
     * @param array<string,string|null> $row
     * @return null|array<string,string|null>
     */
    public static function tickerContext(array $row): ?array
    {
        $archive = ArchiveBoundary::archive();
        $id = $archive->published((int) $row['race_id']);
        if ($id === '') {
            return null;
        }
        $ids = (int) $row['team_id'] > 0 ? [$row['team_id']] : array_column(array_filter($archive->all($id, 'teammate'), static fn (array $member): bool => $member['user_id'] === $row['actor_id']), 'team_id');
        $teams = array_values(array_filter($archive->all($id, 'team'), static fn (array $team): bool => in_array($team['id'], $ids, true)));
        if (count($teams) !== 1) {
            return [];
        }
        $team = $teams[0];
        $route = $archive->row($id, 'route', (int) $team['route_id']);
        return ['team_id' => $team['id'], 'team_name' => $team['team_name'], 'route_id' => $team['route_id'], 'route_name' => $route['name'] ?? '', 'color_code' => $route['color_code'] ?? ''];
    }

    private const TAGS = ['h3-teams','h3-rand-team','h3-ranking-table','h3-stages','h3-stages-isotope','h3-event-statistics',
        'h3-donation-counter','h3-list-sponsors','h3-recent-sponsors','h3-team-homework','h3-team-dashboard','h3-team-dashboard-homework','h3-submit-rankings','h3-sponsoring-form'];

    /**
     * @param array<string,mixed>|string $attributes
     * @param array<int|string,mixed> $match
     */
    public static function shortcode(mixed $output, string $tag, array|string $attributes, array $match): mixed
    {
        if ($output !== false || !in_array($tag, self::TAGS, true)) {
            return $output;
        }
        $args = is_array($attributes) ? $attributes : [];
        $default = in_array($tag, ['h3-ranking-table','h3-stages','h3-stages-isotope','h3-donation-counter','h3-event-statistics'], true) ? 1 : 0;
        $race = $args['race'] ?? $args['event'] ?? $default;
        if ($tag === 'h3-event-statistics' && (string) $race === '0') {
            $race = $args['event'] ?? 1;
        }
        global $wpdb;
        if ($race === 'active') {
            $race = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE active=1 ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'h3_mgmt_races'));
        }
        if (!is_scalar($race) || !ctype_digit((string) $race)) {
            return $output;
        }
        try {
            $archive = ArchiveBoundary::archive();
            $id = $archive->published((int) $race);
            if ($id === '') {
                return $output;
            }
            return self::render($archive, $id, (int) $race, $tag, $args);
        } catch (\Throwable $error) {
            Services::get()->logger->error('archive_view_failed', $error);
            return '<p>' . esc_html__('The verified archive view is unavailable. Please contact the organizers.', 'h3-mgmt') . '</p>';
        }
    }

    /** @param array<string,mixed> $args */
    private static function render(Archive $archive, string $id, int $race, string $tag, array $args): string
    {
        if (in_array($tag, ['h3-team-dashboard','h3-team-dashboard-homework','h3-submit-rankings','h3-sponsoring-form'], true)) {
            return '<p>' . esc_html__('This race has ended. Its archive is read-only.', 'h3-mgmt') . '</p>';
        }
        $teams = $archive->all($id, 'team');
        $routes = array_column($archive->all($id, 'route'), null, 'id');
        $selectedRoute = isset($args['route']) && is_scalar($args['route']) && ctype_digit((string) $args['route']) ? (int) $args['route'] : 0;
        if ($tag === 'h3-ranking-table' && is_string($_GET['ranking_route'] ?? null) && ctype_digit($_GET['ranking_route'])) {
            $selectedRoute = (int) $_GET['ranking_route'];
        }
        $selectedTeam = is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
        $complete = array_values(array_filter($teams, static fn (array $team): bool => (int) $team['complete'] === 1 && (!$selectedRoute || (int) $team['route_id'] === $selectedRoute)));
        if ($tag === 'h3-event-statistics') {
            return self::statistics($archive, $id, $race, $args, $teams, $complete);
        }
        ob_start();
        try {
            echo '<section class="cor-mgmt-archive" data-archive="' . esc_attr($id) . '"><p>' . esc_html__('Race archive — frozen results and content', 'h3-mgmt') . '</p>';
            if ($tag === 'h3-ranking-table') {
                usort($complete, static fn (array $a, array $b): int => (int) $b['total_points'] <=> (int) $a['total_points']);
                $previous = $archive->all($id, 'cor_mgmt_scoring_profiles') ? null : 999;
                $rank = 0;
                $skip = 1;
                $routePrevious = $routeRanks = $routeSkips = [];
                $top = is_scalar($args['top'] ?? null) ? max(0, (int) $args['top']) : 0;
                $showStages = (string) ($args['show_stages'] ?? '1') !== '0';
                $settings = json_decode($archive->row($id, 'race', $race)['setting'] ?? '', true);
                $stageRows = array_column($archive->all($id, 'stage'), null, 'id');
                $stageNumbers = range(1, 6);
                $storedPoints = [];
                foreach ($archive->all($id, 'cor_mgmt_results') as $result) {
                    $number = (int) ($stageRows[$result['stage_id'] ?? '']['number'] ?? 0);
                    if ($number > 0 && $number <= 255) {
                        $stageNumbers[] = $number;
                        $storedPoints[$result['team_id'] ?? ''][$number] = $result['points'];
                    }
                }
                $stageNumbers = array_values(array_unique($stageNumbers));
                sort($stageNumbers, SORT_NUMERIC);
                if (!empty($args['show_title'])) {
                    echo '<h2>' . esc_html($archive->row($id, 'race', $race)['name'] ?? '') . '</h2>';
                }
                if ((string) ($args['show_nav'] ?? '1') !== '0') {
                    echo '<nav aria-label="' . esc_attr__('Routes', 'h3-mgmt') . '"><a href="' . esc_url(remove_query_arg('ranking_route')) . '">' . esc_html__('All routes', 'h3-mgmt') . '</a> ';
                    foreach ($routes as $route) {
                        echo '<a href="' . esc_url(add_query_arg('ranking_route', $route['id'])) . '">' . esc_html($route['name'] ?? '') . '</a> ';
                    }
                    echo '</nav>';
                }
                echo '<table><thead><tr><th>' . esc_html__('Rank', 'h3-mgmt') . '</th><th>' . esc_html__('Team', 'h3-mgmt') . '</th><th>' . esc_html__('Route', 'h3-mgmt') . '</th><th>' . esc_html__('Route rank', 'h3-mgmt') . '</th><th>' . esc_html__('Points', 'h3-mgmt') . '</th>';
                if ($showStages) {
                    foreach ($stageNumbers as $stage) {
                        echo '<th>' . esc_html__('Stage', 'h3-mgmt') . ' ' . esc_html((string) $stage) . '</th>';
                    }
                }
                echo '</tr></thead><tbody>';
                foreach ($complete as $position => $team) {
                    if ($top && $position >= $top) {
                        break;
                    }
                    $points = (int) $team['total_points'];
                    if ($previous === null || $previous > $points) {
                        $rank += $skip;
                        $skip = 1;
                    } else {
                        ++$skip;
                    }
                    $previous = $points;
                    $routeKey = $team['route_id'] ?? '';
                    if (!array_key_exists($routeKey, $routePrevious)) {
                        $routePrevious[$routeKey] = $archive->all($id, 'cor_mgmt_scoring_profiles') ? null : 999;
                        $routeRanks[$routeKey] = 0;
                        $routeSkips[$routeKey] = 1;
                    }
                    if ($routePrevious[$routeKey] === null || $routePrevious[$routeKey] > $points) {
                        $routeRanks[$routeKey] += $routeSkips[$routeKey];
                        $routeSkips[$routeKey] = 1;
                    } else {
                        ++$routeSkips[$routeKey];
                    }
                    $routePrevious[$routeKey] = $points;
                    echo '<tr><td>' . esc_html((string) $rank) . '</td><td>' . esc_html($team['team_name'] ?? '') . '</td><td>' . esc_html($routes[$routeKey]['name'] ?? '') . '</td><td>' . esc_html((string) $routeRanks[$routeKey]) . '</td><td>' . esc_html((string) $points) . '</td>';
                    if ($showStages) {
                        foreach ($stageNumbers as $stage) {
                            $placing = (int) ($team['rank_stage_' . $stage] ?? 0);
                            $extra = (int) ($team['extra_stage_' . $stage] ?? 0)
                                + ((int) ($team['amount_extra_stage_' . $stage] ?? 0) === 1 ? (int) ($settings['extra_point_amount'] ?? 0) : 0)
                                + (int) ($team['vary_extra_stage_' . $stage] ?? 0);
                            $legacyDisplay = [0 => 0, 1 => 20, 2 => 17, 3 => 14, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 5, 9 => 4, 10 => 3, 11 => 2, 12 => 1, 99 => 'disq.'];
                            $display = $storedPoints[$team['id'] ?? ''][$stage] ?? ((string) ($legacyDisplay[$placing] ?? $placing) . ($extra ? ' (+' . $extra . ')' : ''));
                            echo '<td>' . esc_html($display) . '</td>';
                        }
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } elseif (in_array($tag, ['h3-stages','h3-stages-isotope'], true)) {
                echo '<ol>';
                foreach ($archive->all($id, 'stage') as $stage) {
                    if (!$selectedRoute || (int) $stage['route_id'] === $selectedRoute) {
                        echo '<li>' . esc_html(($stage['number'] ?? '') . ' · ' . ($stage['destination'] ?? '')) . '</li>';
                    }
                }
                echo '</ol>';
            } elseif (in_array($tag, ['h3-donation-counter','h3-list-sponsors','h3-recent-sponsors'], true)) {
                self::donations($archive, $id, $race, $tag, $args);
            } else {
                if ($tag === 'h3-rand-team') {
                    $choice = $complete ? unpack('nvalue', Services::get()->random->bytes(2)) : [];
                    $complete = $complete ? [$complete[($choice['value'] ?? 0) % count($complete)]] : [];
                }
                $users = array_column($archive->all($id, 'user'), null, 'ID');
                $members = $archive->all($id, 'teammate');
                $meta = [];
                foreach ($archive->all($id, 'usermeta') as $value) {
                    $meta[$value['user_id'] ?? ''][$value['meta_key'] ?? ''] = $value['meta_value'];
                }
                foreach ($complete as $team) {
                    if ($selectedTeam && (int) $team['id'] !== $selectedTeam) {
                        continue;
                    }
                    echo '<article><h2><a href="' . esc_url(add_query_arg('id', $team['id'] ?? '')) . '">' . esc_html($team['team_name'] ?? '') . '</a></h2><p>' . esc_html($routes[$team['route_id'] ?? '']['name'] ?? '') . '</p>';
                    self::image($archive, $id, $team['team_pic'] ?? '', $team['team_name'] ?? '');
                    self::image($archive, $id, $routes[$team['route_id'] ?? '']['logo_url'] ?? '', $routes[$team['route_id'] ?? '']['name'] ?? '');
                    if (!empty($team['donation_goal'])) {
                        echo '<p>' . esc_html__('Donation goal', 'h3-mgmt') . ': ' . esc_html((string) DonationAmounts::whole($team['donation_goal'])) . ' €</p>';
                    }
                    echo '<p>' . nl2br(esc_html($team['description'] ?? '')) . '</p><ul>';
                    foreach ($members as $member) {
                        if ($member['team_id'] === $team['id']) {
                            $user = $member['user_id'] ?? '';
                            $name = trim(($meta[$user]['first_name'] ?? '') . ' ' . ($meta[$user]['last_name'] ?? ''));
                            $name = $name !== '' ? $name : ($users[$user]['display_name'] ?? __('Former participant', 'h3-mgmt'));
                            $birthday = $meta[$user]['birthday'] ?? '';
                            $age = '';
                            if (ctype_digit($birthday) && (int) $birthday > 0) {
                                $asOf = (new \DateTimeImmutable('@' . $archive->run($id)['created_at']));
                                $birth = new \DateTimeImmutable('@' . $birthday);
                                $age = $birth <= $asOf ? ' (' . $birth->diff($asOf)->y . ')' : '';
                            }
                            echo '<li>' . esc_html($name . $age) . ' · ' . esc_html($meta[$user]['city'] ?? '');
                            $avatar = $meta[$user]['cor_mgmt_avatar'] ?? '';
                            if ($avatar === '' && is_serialized($meta[$user]['simple_local_avatar'] ?? '')) {
                                $oldAvatar = unserialize($meta[$user]['simple_local_avatar'] ?? '', ['allowed_classes' => false]);
                                $avatar = is_array($oldAvatar) && is_string($oldAvatar['full'] ?? null) ? $oldAvatar['full'] : '';
                            }
                            self::image($archive, $id, $avatar, $name);
                            echo '</li>';
                        }
                    }
                    echo '</ul>';
                    if ($selectedTeam || $tag === 'h3-team-homework') {
                        echo '<p>' . esc_html__('Points', 'h3-mgmt') . ': ' . esc_html($team['total_points'] ?? '0') . '</p>';
                        self::donations($archive, $id, $race, 'h3-list-sponsors', ['type' => 'all', 'team' => $team['id']]);
                        $settings = json_decode($archive->row($id, 'race', $race)['setting'] ?? '', true);
                        $texts = json_decode($archive->row($id, 'race', $race)['information_text'] ?? '', true);
                        $language = defined('ICL_LANGUAGE_CODE') ? (string) constant('ICL_LANGUAGE_CODE') : 'en';
                        foreach (range(1, 5) as $question) {
                            if (!empty($settings['question_' . $question . '_invisible'])) {
                                continue;
                            }
                            echo '<h3>' . esc_html(is_string($texts[$question . $language] ?? null) ? $texts[$question . $language] : '') . '</h3><p>' . nl2br(esc_html($team['meta_' . $question] ?? '')) . '</p>';
                        }
                        echo TickerBoundary::feed(['race' => $race, 'team' => (int) $team['id'], 'max' => max(1, min(100, (int) ($args['max'] ?? 10)))]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native snapshot feed.
                    }
                    echo '</article>';
                }
            }
            echo '</section>';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private static function image(Archive $archive, string $id, string $reference, string $alt): void
    {
        $url = $archive->imageUrl($id, $reference);
        if ($url !== '') {
            echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
        }
    }

    /** @param array<string,mixed> $args */
    private static function donations(Archive $archive, string $id, int $race, string $tag, array $args): void
    {
        $total = new EuroAmount(0);
        $modern = array_column($archive->all($id, 'cor_mgmt_donations'), null, 'sponsor_id');
        $rows = $archive->all($id, 'sponsor');
        if ($tag === 'h3-recent-sponsors') {
            $rows = array_reverse($rows);
        }
        $shown = 0;
        foreach ($rows as $row) {
            if ((int) $row['race_id'] !== $race || (isset($args['team']) && $row['team_id'] !== $args['team'])) {
                continue;
            }
            $value = $modern[$row['id'] ?? ''] ?? [];
            $amount = ($value['state'] ?? '') === 'confirmed' && $value['amount_minor'] !== null
                ? new EuroAmount((int) $value['amount_minor']) : EuroAmount::decimal($row['donation'] ?? '0');
            if ((int) $row['paid'] === 1 && $row['type'] !== 'structure') {
                $total = $total->add($amount);
            }
            if (
                $tag !== 'h3-donation-counter' && (int) $row['paid'] === 1 && (int) $row['var_show'] === 1
                && (($args['type'] ?? 'sponsor') === 'all' || $row['type'] === ($args['type'] ?? 'sponsor'))
                && ($tag !== 'h3-recent-sponsors' || $shown < max(1, min(100, (int) ($args['number'] ?? 3))))
            ) {
                echo '<p>' . esc_html($row['display_name'] ?? '') . ' · ' . esc_html((string) DonationAmounts::whole($amount->format())) . ' €</p>';
                self::image($archive, $id, $row['owner_pic'] ?? '', $row['display_name'] ?? '');
                if (!empty($row['owner_link'])) {
                    echo '<a href="' . esc_url($row['owner_link']) . '" rel="nofollow noopener">' . esc_html($row['display_name'] ?? '') . '</a>';
                }
                ++$shown;
            }
        }
        if ($tag === 'h3-donation-counter') {
            $offset = is_scalar($args['offset'] ?? null) ? (string) $args['offset'] : '0';
            echo '<p>' . esc_html((string) DonationAmounts::whole(DonationAmounts::offset($total->format(), $offset))) . ' €</p>';
        }
    }

    /**
     * @param array<string,mixed> $args
     * @param list<array<string,string|null>> $teams
     * @param list<array<string,string|null>> $complete
     */
    private static function statistics(Archive $archive, string $id, int $race, array $args, array $teams, array $complete): string
    {
        $members = $archive->all($id, 'teammate');
        $completeIds = array_column($complete, 'id');
        $sponsors = array_filter($archive->all($id, 'sponsor'), static fn (array $row): bool => (int) $row['race_id'] === $race && (int) $row['paid'] === 1);
        $counts = ['team-count' => count($teams), 'complete-count' => count($complete), 'participants-count' => count($members),
            'participants-complete-count' => count(array_filter($members, static fn (array $row): bool => in_array($row['team_id'], $completeIds, true))),
            'race-sponsors-count' => count(array_filter($sponsors, static fn (array $row): bool => $row['type'] !== 'structure'))];
        foreach (['sponsor','owner','patron','structure'] as $type) {
            $key = ['sponsor' => 'sponsors-count','owner' => 'owners-count','patron' => 'patrons-count','structure' => 'structure-count'][$type];
            $counts[$key] = count(array_filter($sponsors, static fn (array $row): bool => $row['type'] === $type));
        }
        $total = new EuroAmount(0);
        $modern = array_column($archive->all($id, 'cor_mgmt_donations'), null, 'sponsor_id');
        foreach ($sponsors as $row) {
            if ($row['type'] !== 'structure') {
                $value = $modern[$row['id']] ?? [];
                $total = $total->add(($value['state'] ?? '') === 'confirmed' && $value['amount_minor'] !== null ? new EuroAmount((int) $value['amount_minor']) : EuroAmount::decimal($row['donation'] ?? '0'));
            }
        }
        $counts['donations-total'] = DonationAmounts::whole(DonationAmounts::offset($total->format(), is_scalar($args['offset'] ?? null) ? (string) $args['offset'] : '0'));
        $counts['thumbs'] = $counts['cpk'] = DonationAmounts::thumbs($total);
        $stat = is_string($args['stat'] ?? null) ? $args['stat'] : 'all';
        if ($stat !== 'all') {
            return esc_html((string) ($counts[$stat] ?? ''));
        }
        $html = '<dl class="cor-mgmt-archive-statistics">';
        foreach ($counts as $name => $count) {
            if ($name !== 'cpk') {
                $html .= '<dt>' . esc_html($name) . '</dt><dd>' . esc_html((string) $count) . '</dd>';
            }
        }
        return $html . '</dl>';
    }
}
