<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Domain\EuroAmount;
use Cor\Mgmt\Infrastructure\WordPress\DonationAdministration;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema;

/** Native administration for verified profiles and explicit legacy review plans. */
final class DonationAdmin
{
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            $capability = current_user_can('h3_mgmt_view_sponsors') ? 'h3_mgmt_view_sponsors' : 'h3_mgmt_view_own_sponsors';
            add_submenu_page('h3-mgmt-sponsors', __('Donation profiles and pending reviews', 'h3-mgmt'), __('Profiles and pending reviews', 'h3-mgmt'), $capability, 'cor-mgmt-donations', [self::class, 'page']);
        });
    }
    public static function manager(): DonationAdministration
    {
        global $wpdb;
        return new DonationAdministration(new Database($wpdb), Services::get()->clock, static fn (int $actor, int $id, string $operation): bool => $actor === get_current_user_id() && \H3_MGMT_Security::can_object('sponsors', $operation, $id));
    }
    /** @return array<string,string|null> */
    public static function submit(): array
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-donation-admin')) {
            throw new DonationException('invalid_nonce');
        }
        $actor = get_current_user_id();
        $action = TeamBoundary::value('donation_action');
        $id = TeamBoundary::number(TeamBoundary::value('sponsor_id', '0'));
        if ($action === 'prepare') {
            return self::manager()->prepareLegacy($id, $actor, TeamBoundary::value('revision'));
        }
        if ($action === 'save') {
            $values = ['display_name' => TeamBoundary::value('display_name'), 'message' => TeamBoundary::value('message'), 'var_show' => isset($_POST['visible']) ? '1' : '0'];
            $record = self::manager()->inspect($id, $actor)['record'];
            if ($record['type'] === 'owner') {
                $values['owner_link'] = TeamBoundary::value('owner_link');
                if (isset($_POST['remove_image'])) {
                    $values['owner_pic'] = '';
                }
            }
            return self::manager()->update([$id => ['revision' => TeamBoundary::value('revision'), 'values' => $values]], $actor);
        }
        if (!in_array($action, ['show','hide'], true) || !is_array($_POST['donation_ids'] ?? null) || !is_array($_POST['revisions'] ?? null)) {
            throw new DonationException('invalid_input');
        }
        $requests = [];
        foreach ($_POST['donation_ids'] as $raw) {
            if (!is_string($raw)) {
                throw new DonationException('invalid_input');
            }
            $selected = TeamBoundary::number($raw);
            $revision = $_POST['revisions'][$selected] ?? null;
            if (!is_string($revision)) {
                throw new DonationException('invalid_input');
            }
            $requests[$selected] = ['revision' => wp_unslash($revision), 'values' => ['var_show' => $action === 'show' ? '1' : '0']];
        }
        return self::manager()->update($requests, $actor);
    }
    public static function page(): void
    {
        if (!current_user_can('h3_mgmt_view_sponsors') && !current_user_can('h3_mgmt_view_own_sponsors')) {
            echo \H3_MGMT_Security::error(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed safe notice.
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Donation profiles and pending reviews', 'h3-mgmt') . '</h1>';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                self::submit();
                echo '<p role="status">' . esc_html__('Changes recorded. Payment evidence and historical amounts are unchanged.', 'h3-mgmt') . '</p>';
            } catch (\Throwable $error) {
                Services::get()->logger->error('donation_admin_failed', $error);
                echo '<p role="alert">' . esc_html__('The request could not be accepted. Check your permissions and reload the current record. Archives, unverified publication and stale revisions are protected.', 'h3-mgmt') . '</p>';
            }
        }
        try {
            global $wpdb;
            $db = new Database($wpdb);
            if (!DonationSchema::ready($db)) {
                throw new DonationException('donation_setup_required');
            }
            $id = \H3_MGMT_Security::id($_GET['donation_id'] ?? 0);
            if ($id) {
                self::detail($id, $db);
            } else {
                self::listing($db);
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('donation_admin_read_failed', $error);
            echo '<p>' . esc_html__('This record is unavailable or donation storage needs setup in system status.', 'h3-mgmt') . '</p>';
        }
        echo '</div>';
    }
    private static function detail(int $id, Database $db): void
    {
        $context = self::manager()->inspect($id, get_current_user_id());
        $row = $context['record'];
        $editable = \H3_MGMT_Security::can_object('sponsors', 'edit', $id) && !\H3_MGMT_Security::archived((int) $row['race_id']);
        echo '<p>' . esc_html__('Donation ID', 'h3-mgmt') . ': ' . esc_html((string) $id) . ' · ' . esc_html__('Team', 'h3-mgmt') . ': ' . esc_html($row['team_id'] ?? '') . '</p>';
        $fields = \H3_MGMT_Security::nonce('cor-mgmt-donation-admin') . '<input type="hidden" name="sponsor_id" value="' . esc_attr((string) $id) . '"><input type="hidden" name="revision" value="' . esc_attr($context['revision']) . '">';
        if ($row['managed_id'] !== null) {
            echo '<p>' . esc_html__('Observed amount', 'h3-mgmt') . ': ' . esc_html($row['amount_minor'] === null ? __('Pending', 'h3-mgmt') : (new EuroAmount((int) $row['amount_minor']))->format() . ' EUR') . '</p>';
            echo '<form method="post">' . $fields . '<input type="hidden" name="donation_action" value="save"><fieldset' . ($editable ? '' : ' disabled') . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped fields above.
            foreach (['display_name' => __('Display name', 'h3-mgmt'), 'message' => __('Message', 'h3-mgmt')] as $field => $label) {
                echo '<p><label>' . esc_html($label) . '<br><textarea name="' . esc_attr($field) . '" rows="3" cols="60">' . esc_textarea($row[$field] ?? '') . '</textarea></label></p>';
            }
            if ($row['type'] === 'owner') {
                echo '<p><label>' . esc_html__('Website', 'h3-mgmt') . ' <input type="url" name="owner_link" value="' . esc_attr($row['owner_link'] ?? '') . '"></label></p><p><label><input type="checkbox" name="remove_image">' . esc_html__('Remove profile image reference', 'h3-mgmt') . '</label></p>';
            }
            echo '<p><label><input type="checkbox" name="visible"' . checked($row['var_show'], '1', false) . '>' . esc_html__('Show publicly after confirmation', 'h3-mgmt') . '</label></p><button class="button button-primary" type="submit">' . esc_html__('Save profile', 'h3-mgmt') . '</button></fieldset></form>';
        } else {
            echo '<h2>' . esc_html__('Legacy pending donation review', 'h3-mgmt') . '</h2><p>' . esc_html__('Reconcile this entry with the provider before transferring it. Preparing a review does not confirm payment, change amounts or transfer browser access.', 'h3-mgmt') . '</p><p>' . esc_html__('Review status', 'h3-mgmt') . ': ' . esc_html($context['plan']['eligible'] ? ($context['prepared'] ? __('Review prepared; provider reconciliation outstanding', 'h3-mgmt') : __('Ready for provider reconciliation', 'h3-mgmt')) : (string) $context['plan']['reason']) . '</p>';
            if ($context['plan']['eligible'] && $editable) {
                echo '<form method="post">' . $fields . '<button type="submit" class="button" name="donation_action" value="prepare">' . esc_html__('Prepare review', 'h3-mgmt') . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped fields above.
            }
        }
        echo '<h2>' . esc_html__('Recent audit entries', 'h3-mgmt') . '</h2><ul>';
        foreach ($db->rows($db->prepare('SELECT id,created_at,kind FROM %i WHERE sponsor_id=%d ORDER BY id DESC LIMIT 20', $db->table('cor_mgmt_donation_history'), $id)) as $entry) {
            echo '<li>' . esc_html(($entry['id'] ?? '') . ' · ' . gmdate('Y-m-d H:i:s', (int) $entry['created_at']) . ' UTC · ' . ($entry['kind'] ?? '')) . '</li>';
        }
        echo '</ul>';
    }
    private static function listing(Database $db): void
    {
        $after = \H3_MGMT_Security::id($_GET['after'] ?? 0);
        $scope = current_user_can('h3_mgmt_view_sponsors') ? '' : $db->prepare(' AND s.team_id IN (SELECT t.id FROM %i t JOIN %i r ON r.id=t.route_id WHERE r.user_id=%d)', $db->table('h3_mgmt_teams'), $db->table('h3_mgmt_routes'), get_current_user_id());
        $query = $db->prepare("SELECT s.id,s.race_id,s.team_id,s.display_name,d.state,d.amount_minor FROM %i s LEFT JOIN %i d ON d.sponsor_id=s.id WHERE s.id>%d AND (d.sponsor_id IS NOT NULL OR (s.method='Betterplace' AND s.paid=0))", $db->table('h3_mgmt_sponsors'), $db->table('cor_mgmt_donations'), $after);
        $rows = $db->rows($query . $scope . ' ORDER BY s.id LIMIT 50');
        echo '<form method="post">' . \H3_MGMT_Security::nonce('cor-mgmt-donation-admin') . '<table class="widefat striped"><thead><tr><th>' . esc_html__('Select', 'h3-mgmt') . '</th><th>ID</th><th>' . esc_html__('Team', 'h3-mgmt') . '</th><th>' . esc_html__('Profile', 'h3-mgmt') . '</th><th>' . esc_html__('Observed amount / review', 'h3-mgmt') . '</th></tr></thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce field.
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!\H3_MGMT_Security::can_object('sponsors', 'view', $id)) {
                continue;
            }
            $context = self::manager()->inspect($id, get_current_user_id());
            $url = add_query_arg(['page' => 'cor-mgmt-donations', 'donation_id' => $id], admin_url('admin.php'));
            echo '<tr><td>';
            if ($row['state'] !== null && \H3_MGMT_Security::can_object('sponsors', 'edit', $id) && !\H3_MGMT_Security::archived((int) $row['race_id'])) {
                echo '<input type="checkbox" name="donation_ids[]" value="' . esc_attr((string) $id) . '" aria-label="' . esc_attr__('Select donation', 'h3-mgmt') . '"><input type="hidden" name="revisions[' . esc_attr((string) $id) . ']" value="' . esc_attr($context['revision']) . '">';
            }
            echo '</td><td><a href="' . esc_url($url) . '">' . esc_html((string) $id) . '</a></td><td>' . esc_html($row['team_id'] ?? '') . '</td><td>' . esc_html($row['display_name'] ?? '') . '</td><td>' . esc_html($row['state'] === null ? __('Legacy review', 'h3-mgmt') : ($row['amount_minor'] === null ? __('Pending', 'h3-mgmt') : (new EuroAmount((int) $row['amount_minor']))->format() . ' EUR')) . '</td></tr>';
        }
        echo '</tbody></table><p><button type="submit" class="button" name="donation_action" value="show">' . esc_html__('Show selected', 'h3-mgmt') . '</button> <button type="submit" class="button" name="donation_action" value="hide">' . esc_html__('Hide selected', 'h3-mgmt') . '</button></p></form>';
        if (count($rows) === 50) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'cor-mgmt-donations','after' => $rows[49]['id']], admin_url('admin.php'))) . '">' . esc_html__('Next', 'h3-mgmt') . '</a>';
        }
    }
}
