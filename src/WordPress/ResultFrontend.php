<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Domain\Scoring;
use Cor\Mgmt\Application\Results\ResultException;

/** Native controls and versioned display; the legacy renderer remains responsible for history. */
final class ResultFrontend
{
    public static function control(): string
    {
        global $h3_mgmt_races;
        try {
            $race = (int) $h3_mgmt_races->get_active_race();
            $routes = array_values(array_filter(ResultBoundary::repository()->routes($race), static fn (array $row): bool => is_user_logged_in() && (\H3_MGMT_Security::assigned_route((int) $row['id']) || \H3_MGMT_Security::can_object('races', 'edit', $race))));
            if ($routes === []) {
                throw new ResultException('not_permitted');
            }
            $allowed = array_map(static fn (array $row): int => (int) $row['id'], $routes);
            if (isset($_POST['submitted'])) {
                $route = TeamBoundary::number(TeamBoundary::value('route'));
                if (!in_array($route, $allowed, true)) {
                    throw new ResultException('not_permitted');
                }
                return ResultBoundary::save();
            }
            $route = isset($_GET['cor_result_route']) && is_string($_GET['cor_result_route']) && ctype_digit($_GET['cor_result_route']) ? (int) $_GET['cor_result_route'] : $allowed[0];
            if (!in_array($route, $allowed, true)) {
                throw new ResultException('not_permitted');
            }
            $context = ResultBoundary::manager()->view($route);
            $number = isset($_GET['stage']) && is_string($_GET['stage']) && ctype_digit($_GET['stage']) ? (int) $_GET['stage'] : 0;
            $html = '<form method="get"><label>' . esc_html__('Route', 'h3-mgmt') . ' <select name="cor_result_route">';
            foreach ($routes as $row) {
                $html .= '<option value="' . esc_attr($row['id'] ?? '') . '"' . selected((int) $row['id'], $route, false) . '>' . esc_html($row['name'] ?? '') . '</option>';
            }
            $html .= '</select></label><label>' . esc_html__('Stage', 'h3-mgmt') . ' <select name="stage">';
            foreach ($context->stages as $stage) {
                if ((int) $stage['number'] > 0) {
                    $html .= '<option value="' . esc_attr($stage['number'] ?? '') . '"' . selected((int) $stage['number'], $number, false) . '>' . esc_html(($stage['number'] ?? '') . ' ' . ($stage['destination'] ?? '')) . '</option>';
                }
            }
            $html .= '</select></label>';
            foreach ($_GET as $name => $value) {
                if (is_string($name) && is_string($value) && !in_array($name, ['stage', 'cor_result_route'], true)) {
                    $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_unslash($value)) . '">';
                }
            }
            $html .= '<button type="submit">' . esc_html__('Choose stage', 'h3-mgmt') . '</button></form>';
            return $html . ($number ? self::form($route, $number) : '');
        } catch (\Throwable) {
            return '<p>' . esc_html__('Result entry is unavailable. Check permissions, race phase and scoring setup.', 'h3-mgmt') . '</p>';
        }
    }

    public static function form(int $route, int $number, string $message = ''): string
    {
        try {
            $context = ResultBoundary::manager()->view($route);
            $matches = array_values(array_filter($context->stages, static fn (array $stage): bool => (int) $stage['number'] === $number && $number > 0));
            if (count($matches) !== 1) {
                throw new ResultException('ambiguous_stage');
            }
            $stage = (int) $matches[0]['id'];
            $rules = $context->rules();
            $html = '<p role="status">' . esc_html($message) . '</p><form method="post" class="cor-result-form">' . wp_nonce_field('cor-mgmt-ranking-' . $route, 'cor_mgmt_nonce', false, false);
            foreach (['submitted' => 'y', 'route' => (string) $route, 'stage' => (string) $number, 'cor_result_revision' => $context->revision()] as $name => $value) {
                $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            }
            $count = 0;
            foreach ($context->teams as $team) {
                if ((int) $team['complete'] !== 1) {
                    continue;
                }
                $count++;
                $id = (int) $team['id'];
                $old = [];
                foreach ($context->results as $result) {
                    if ((int) $result['team_id'] === $id && (int) $result['stage_id'] === $stage) {
                        $old = $result;
                    }
                }
                $html .= '<fieldset><legend>' . esc_html($team['team_name'] ?? '') . '</legend><label for="cor-rank-' . esc_attr((string) $id) . '">' . esc_html__('Placement', 'h3-mgmt') . '</label><select id="cor-rank-' . esc_attr((string) $id) . '" name="rank-' . esc_attr((string) $id) . '">';
                foreach (Scoring::PLACEMENTS as $rank => $_) {
                    $label = $rank === 99 ? __('Disqualified', 'h3-mgmt') : ($rank === 0 ? __('No placement', 'h3-mgmt') : (string) $rank);
                    $html .= '<option value="' . esc_attr((string) $rank) . '"' . selected((string) ($old['rank_value'] ?? '0'), (string) $rank, false) . '>' . esc_html($label) . '</option>';
                }
                $html .= '</select>';
                foreach (['one_extra' => ['one_extra_point', 'extra_one'], 'amount_extra' => ['amount_extra_point', 'extra_fixed']] as $name => [$switch, $column]) {
                    if (!empty($rules[$switch])) {
                        $label = $name === 'one_extra' ? __('One extra point', 'h3-mgmt') : __('Fixed extra points', 'h3-mgmt');
                        $html .= '<label><input type="checkbox" name="' . esc_attr($name . '-' . $id) . '" value="1"' . checked((string) ($old[$column] ?? '0'), '1', false) . '> ' . esc_html($label) . '</label>';
                    }
                }
                if ($number === 6 && !empty($rules['vary_extra_point_field'])) {
                    $html .= '<label>' . esc_html__('Variable extra points', 'h3-mgmt') . ' <input type="number" min="0" max="255" name="vary_extra-' . esc_attr((string) $id) . '" value="' . esc_attr((string) ($old['extra_variable'] ?? '0')) . '"></label>';
                }
                $html .= '</fieldset>';
            }
            $html .= $count ? '<button type="submit">' . esc_html__('Save ranking', 'h3-mgmt') . '</button>' : '<p>' . esc_html__('No eligible teams.', 'h3-mgmt') . '</p>';
            return $html . '</form>';
        } catch (\Throwable) {
            return '<p>' . esc_html__('Result entry is unavailable. Check permissions, race phase and scoring setup.', 'h3-mgmt') . '</p>';
        }
    }
    /** Return null for history: retain the original renderer, totals and displayed ordering.
     * @param array<string,mixed> $attributes
     */
    public static function table(array $attributes): ?string
    {
        global $h3_mgmt_races;
        $race = ($attributes['race'] ?? '') === 'active' ? (int) $h3_mgmt_races->get_active_race() : (int) ($attributes['race'] ?? 1);
        $context = ResultBoundary::repository()->race($race);
        if ($context->version === '') {
            return null;
        }
        if ($context->version !== Scoring::VERSION) {
            return '<p>' . esc_html__('This scoring version is not supported.', 'h3-mgmt') . '</p>';
        }
        if ((int) $context->rules()['status'] < 3) {
            return '<p>' . esc_html__('The race has not started.', 'h3-mgmt') . '</p>';
        }
        $route = isset($_GET['ranking_route']) && is_string($_GET['ranking_route']) && ctype_digit($_GET['ranking_route']) ? (int) $_GET['ranking_route'] : (int) ($attributes['route'] ?? 0);
        $routes = [];
        foreach (ResultBoundary::repository()->routes($race) as $row) {
            $routes[(int) $row['id']] = $row['name'] ?? '';
        }
        if ($route && !isset($routes[$route])) {
            $route = 0;
        }
        $teams = [];
        $names = [];
        $teamRoutes = [];
        $scores = [];
        $details = [];
        $stageNumbers = [];
        $numbers = [];
        foreach ($context->stages as $stage) {
            $number = (int) $stage['number'];
            if ($number > 0) {
                $stageNumbers[(int) $stage['id']] = $number;
                if (!$route || (int) $stage['route_id'] === $route) {
                    $numbers[$number] = true;
                }
            }
        }
        ksort($numbers);
        foreach ($context->results as $result) {
            $id = (int) $result['team_id'];
            $scores[$id] = ($scores[$id] ?? 0) + (int) $result['points'];
            $number = $stageNumbers[(int) $result['stage_id']] ?? 0;
            $details[$id][$number] = $result;
        }
        $perRoute = [];
        foreach ($context->teams as $team) {
            $id = (int) $team['id'];
            if ((int) $team['complete'] !== 1) {
                continue;
            }
            $row = ['id' => $id, 'points' => $scores[$id] ?? 0];
            $teams[] = $row;
            $perRoute[(int) $team['route_id']][] = $row;
            $teamRoutes[$id] = (int) $team['route_id'];
            $names[$id] = $team['team_name'] ?? '';
        }
        $routePositions = [];
        foreach ($perRoute as $rows) {
            foreach (Scoring::ranking($rows) as $row) {
                $routePositions[$row['id']] = $row['position'];
            }
        }
        $ranked = Scoring::ranking($teams);
        if ($route) {
            $ranked = array_values(array_filter($ranked, static fn (array $row): bool => $teamRoutes[$row['id']] === $route));
        }
        $limit = max(0, (int) ($attributes['top'] ?? 0));
        if ($limit) {
            $ranked = array_slice($ranked, 0, $limit);
        }
        $title = $route ? $routes[$route] : __('All routes', 'h3-mgmt');
        $html = (int) ($attributes['show_title'] ?? 0) === 1 ? '<h3>' . esc_html($title) . '</h3>' : '';
        if ((int) ($attributes['show_nav'] ?? 1) === 1) {
            $html .= '<nav aria-label="' . esc_attr__('Ranking routes', 'h3-mgmt') . '"><ul><li><a href="' . esc_url(remove_query_arg('ranking_route')) . '">' . esc_html__('All routes', 'h3-mgmt') . '</a></li>';
            foreach ($routes as $id => $name) {
                $html .= '<li><a href="' . esc_url(add_query_arg('ranking_route', $id)) . '">' . esc_html($name) . '</a></li>';
            }
            $html .= '</ul></nav>';
        }
        $html .= '<div class="cor-ranking-scroll"><table class="cor-ranking ranking padded-table"><caption>' . esc_html($context->race['name'] ?? '') . '</caption><thead><tr><th scope="col">' . esc_html__('Team', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Total rank', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Route rank', 'h3-mgmt') . '</th><th scope="col">' . esc_html__('Total points', 'h3-mgmt') . '</th>';
        $showStages = (int) ($attributes['show_stages'] ?? 1) === 1;
        if ($showStages) {
            foreach ($numbers as $number => $_) {
                $html .= '<th scope="col">' . esc_html__('Stage', 'h3-mgmt') . ' ' . esc_html((string) $number) . '</th>';
            }
        }
        $html .= '</tr></thead><tbody>';
        global $h3_mgmt_teams;
        foreach ($ranked as $row) {
            $id = $row['id'];
            $name = esc_html($names[$id]);
            $link = SitePages::url($race, 'teams');
            if ($link !== '') {
                $name = '<a href="' . esc_url(add_query_arg('id', $id, $link)) . '">' . $name . '</a>';
            }
            $html .= '<tr><th scope="row">' . $name . '<br><small>' . wp_kses_post((string) $h3_mgmt_teams->mate_name_string($id, ' &amp; ', false)) . '</small></th><td>' . esc_html((string) $row['position']) . '</td><td>' . esc_html((string) $routePositions[$id]) . '</td><td>' . esc_html((string) $row['points']) . '</td>';
            if ($showStages) {
                foreach ($numbers as $number => $_) {
                    $entry = $details[$id][$number] ?? [];
                    $rank = (int) ($entry['rank_value'] ?? 0);
                    $base = Scoring::PLACEMENTS[$rank] ?? 0;
                    $bonus = (int) ($entry['points'] ?? 0) - $base;
                    $text = $rank === 99 ? __('Disqualified', 'h3-mgmt') : (string) $base;
                    if ($bonus) {
                        $text .= ' (+' . $bonus . ')';
                    }
                    $html .= '<td>' . esc_html($text) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }
}
