<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Results\ResultManager;
use Cor\Mgmt\Application\Results\ResultException;
use Cor\Mgmt\Infrastructure\WordPress\ResultRepository;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

final class ResultBoundary
{
    public static function repository(): ResultRepository
    {
        global $wpdb;
        return new ResultRepository(new Database($wpdb));
    }
    public static function manager(): ResultManager
    {
        return new ResultManager(self::repository(), Services::get()->clock, static fn (string $type, int $id): bool => is_user_logged_in() && ($type === 'race'
            ? \H3_MGMT_Security::can_object('races', 'edit', $id)
            : (\H3_MGMT_Security::assigned_route($id) || \H3_MGMT_Security::can_object('races', 'edit', \H3_MGMT_Security::object_race('routes', $id)))));
    }
    public static function register(): void
    {
        add_action('admin_post_cor_mgmt_results_audit', static function (): void {
            try {
                $race = TeamBoundary::number(TeamBoundary::value('race'));
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options') || !wp_verify_nonce(TeamBoundary::value('cor_mgmt_nonce'), 'cor-mgmt-results-audit-' . $race)) {
                    throw new ResultException('not_permitted');
                }
                $context = self::repository()->race($race);
                if ($context->version !== '') {
                    throw new ResultException('historical_race_required');
                }
                $report = \Cor\Mgmt\Application\Results\HistoricalComparison::report($context);
            } catch (\Throwable $error) {
                Services::get()->logger->error('results_audit_failed', $error);
                wp_die(esc_html__('The historical comparison could not be prepared.', 'h3-mgmt'), '', ['response' => 400, 'back_link' => true]);
            }
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="cor-mgmt-result-comparison.json"');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, never embedded in HTML.
            echo wp_json_encode(['race' => $race, 'diagnostic_only' => true, 'rows' => $report], JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP);
            exit;
        });
        add_action('admin_post_cor_mgmt_results_enable', static function (): void {
            try {
                $race = TeamBoundary::number(TeamBoundary::value('race'));
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !wp_verify_nonce(TeamBoundary::value('cor_mgmt_nonce'), 'cor-mgmt-results-enable-' . $race)) {
                    throw new ResultException('not_permitted');
                }
                self::manager()->enable($race);
            } catch (\Throwable $error) {
                Services::get()->logger->error('results_enable_failed', $error);
                wp_die(esc_html__('Scoring could not be enabled. Check the race phase, schema and existing results.', 'h3-mgmt'), '', ['response' => 400, 'back_link' => true]);
            }
            wp_safe_redirect(add_query_arg(['page' => 'h3-mgmt-races', 'todo' => 'edit', 'id' => $race], admin_url('admin.php')));
            exit;
        });
    }
    public static function enableForm(int $race): void
    {
        if (!$race || !\H3_MGMT_Security::can_object('races', 'edit', $race)) {
            return;
        }
        try {
            $context = self::repository()->race($race);
            $context->rules();
        } catch (\Throwable) {
            return;
        }
        echo '<h2>' . esc_html__('Result calculation', 'h3-mgmt') . '</h2>';
        if ($context->version !== '') {
            echo '<p>' . esc_html__('Normalized result storage is enabled for this race.', 'h3-mgmt') . '</p>';
        } elseif ((int) $context->rules()['status'] === 0) {
            echo '<p>' . esc_html__('Enable corrected scoring before registration. Races with existing results cannot be converted here; historical values are retained.', 'h3-mgmt') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('cor-mgmt-results-enable-' . $race, 'cor_mgmt_nonce', false);
            echo '<input type="hidden" name="action" value="cor_mgmt_results_enable"><input type="hidden" name="race" value="' . esc_attr((string) $race) . '"><button class="button" type="submit">' . esc_html__('Enable scoring 2.0 for this race', 'h3-mgmt') . '</button></form>';
        } else {
            echo '<p>' . esc_html__('Historical scoring is retained. A controlled history migration is required before changing this race.', 'h3-mgmt') . '</p>';
        }
        if ($context->version === '' && current_user_can('manage_options')) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('cor-mgmt-results-audit-' . $race, 'cor_mgmt_nonce', false);
            echo '<input type="hidden" name="action" value="cor_mgmt_results_audit"><input type="hidden" name="race" value="' . esc_attr((string) $race) . '"><button class="button" type="submit">' . esc_html__('Download historical comparison (read only)', 'h3-mgmt') . '</button></form>';
        }
    }
    public static function save(): string
    {
        try {
            $route = TeamBoundary::number(TeamBoundary::value('route'));
            $stage = TeamBoundary::number(TeamBoundary::value('stage'));
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !wp_verify_nonce(TeamBoundary::value('cor_mgmt_nonce'), 'cor-mgmt-ranking-' . $route)) {
                throw new ResultException('not_permitted');
            }
            $entries = [];
            foreach ($_POST as $key => $_) {
                if (is_string($key) && preg_match('/^(rank|one_extra|amount_extra|vary_extra)-([1-9][0-9]*)$/D', $key, $match)) {
                    $id = TeamBoundary::number($match[2]);
                    if (!array_key_exists('rank-' . $id, $_POST)) {
                        throw new ResultException('invalid_input');
                    }
                    $entries[$id] = [
                        'rank' => TeamBoundary::number(TeamBoundary::value('rank-' . $id)),
                        'one' => isset($_POST['one_extra-' . $id]) ? TeamBoundary::number(TeamBoundary::value('one_extra-' . $id)) : 0,
                        'fixed' => isset($_POST['amount_extra-' . $id]) ? TeamBoundary::number(TeamBoundary::value('amount_extra-' . $id)) : 0,
                        'variable' => isset($_POST['vary_extra-' . $id]) ? TeamBoundary::number(TeamBoundary::value('vary_extra-' . $id)) : 0,
                    ];
                }
            }
            self::manager()->submit($route, $stage, $entries, TeamBoundary::value('cor_result_revision'), get_current_user_id());
            return ResultFrontend::form($route, $stage, __('Ranking successfully saved!', 'h3-mgmt'));
        } catch (\Throwable $error) {
            Services::get()->logger->error('ranking_write_failed', $error);
            return \H3_MGMT_Security::error() . '<p>' . esc_html__('Reload the result form and check the race phase, permissions and scoring setup before retrying.', 'h3-mgmt') . '</p>';
        }
    }
}
