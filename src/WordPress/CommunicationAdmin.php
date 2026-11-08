<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Infrastructure\WordPress\ResponseTemplates;
use Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Configuration preparation only; never invokes the old sender or a mail transport. */
final class CommunicationAdmin
{
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_submenu_page('h3-mgmt-emails', __('Event autoresponses', 'h3-mgmt'), __('Event autoresponses', 'h3-mgmt'), 'h3_mgmt_edit_autoresponses', 'cor-mgmt-responses', [self::class, 'page']);
        });
    }
    public static function label(string $action): string
    {
        return match ($action) {
            'team-creation' => __('Team created', 'h3-mgmt'),
            'invitation' => __('Team invitation', 'h3-mgmt'),
            'invitation-accepted-inviter' => __('Invitation accepted: team notification', 'h3-mgmt'),
            'invitation-accepted-invitee' => __('Invitation accepted: participant confirmation', 'h3-mgmt'),
            'package-paid' => __('Participation fee received', 'h3-mgmt'),
            'waiver-reached' => __('Liability waiver received', 'h3-mgmt'),
            'new-sponsor' => __('New TeamSponsor', 'h3-mgmt'),
            'new-owner' => __('New TeamOwner', 'h3-mgmt'),
            'publishable' => __('Team ready for route selection', 'h3-mgmt'),
            'paypal-please-owner' => __('Historical PayPal request: TeamOwner', 'h3-mgmt'),
            'paypal-please-sponsor' => __('Historical PayPal request: TeamSponsor', 'h3-mgmt'),
            'paypal-thanks' => __('Historical PayPal confirmation', 'h3-mgmt'),
            'debit-thanks-owner' => __('Historical direct debit confirmation: TeamOwner', 'h3-mgmt'),
            'debit-thanks-sponsor' => __('Historical direct debit confirmation: TeamSponsor', 'h3-mgmt'),
            default => __('Unknown notification', 'h3-mgmt'),
        };
    }
    public static function manager(): ResponseTemplates
    {
        global $wpdb;
        return new ResponseTemplates(new Database($wpdb), Services::get()->clock, static fn (int $race, int $actor, string $operation): bool => $actor === get_current_user_id() && current_user_can('h3_mgmt_edit_autoresponses') && ($operation === 'legacy' ? current_user_can('manage_options') : \H3_MGMT_Security::can_object('races', $operation, $race)));
    }
    /** @return array<string,string> */
    public static function submit(): array
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-responses')) {
            throw new CommunicationException('invalid_nonce');
        }
        $race = TeamBoundary::number(TeamBoundary::value('race_id', '0'));
        $actor = get_current_user_id();
        $action = TeamBoundary::value('response_operation');
        if ($action === 'save') {
            $template = new AutoResponseTemplate(TeamBoundary::value('template_action'), TeamBoundary::value('language'), isset($_POST['enabled']), TeamBoundary::value('subject'), TeamBoundary::value('message'));
            return self::manager()->save($race, $actor, $template, TeamBoundary::value('revision'));
        }
        if ($action === 'transfer') {
            return self::manager()->transfer($race, TeamBoundary::number(TeamBoundary::value('source_id', '0')), $actor, TeamBoundary::value('fingerprint'));
        }
        throw new CommunicationException('invalid_operation');
    }
    public static function page(): void
    {
        if (!current_user_can('h3_mgmt_edit_autoresponses')) {
            echo '<p>' . esc_html__('You are not allowed to manage autoresponses.', 'h3-mgmt') . '</p>';
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Event autoresponses', 'h3-mgmt') . '</h1><p class="notice notice-warning">' . esc_html__('Preparation: these event templates do not control delivery yet. Current automatic responses still use the existing global configuration. Saving, previewing and copying here sends no email.', 'h3-mgmt') . '</p>';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                self::submit();
                echo '<p role="status">' . esc_html__('Configuration saved. No email was sent.', 'h3-mgmt') . '</p>';
            } catch (\Throwable $error) {
                Services::get()->logger->error('communication_config_failed', $error);
                echo '<p role="alert">' . esc_html__('The change was rejected. Reload the selected event and preview the source again. Check permissions, archive status, template contents and placeholders.', 'h3-mgmt') . '</p>';
            }
        }
        global $wpdb;
        $db = new Database($wpdb);
        if (!CommunicationSchema::ready($db) || !$db->exists($db->table('h3_mgmt_races'))) {
            echo '<p>' . esc_html__('Set up communication storage in system status before preparing event templates.', 'h3-mgmt') . '</p></div>';
            return;
        }
        $race = \H3_MGMT_Security::id($_GET['race_id'] ?? 0);
        echo '<form method="get"><input type="hidden" name="page" value="cor-mgmt-responses"><label>' . esc_html__('Event', 'h3-mgmt') . ' <select name="race_id"><option value="0">' . esc_html__('Choose an event', 'h3-mgmt') . '</option>';
        foreach ($db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races'))) as $event) {
            if (\H3_MGMT_Security::can_object('races', 'view', (int) $event['id'])) {
                echo '<option value="' . esc_attr($event['id'] ?? '') . '"' . selected((int) $event['id'], $race, false) . '>' . esc_html(($event['name'] ?? '') . ' (#' . ($event['id'] ?? '') . ')') . '</option>';
            }
        }
        echo '</select></label> <button class="button" type="submit">' . esc_html__('Open', 'h3-mgmt') . '</button></form>';
        if ($race) {
            try {
                self::event($race, $db);
            } catch (\Throwable $error) {
                Services::get()->logger->error('communication_config_read_failed', $error);
                echo '<p>' . esc_html__('This event is unavailable or communication storage needs setup in system status.', 'h3-mgmt') . '</p>';
            }
        }
        echo '</div>';
    }
    private static function fields(int $race): string
    {
        return \H3_MGMT_Security::nonce('cor-mgmt-responses') . '<input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '">';
    }
    private static function event(int $race, Database $db): void
    {
        $context = self::manager()->context($race, get_current_user_id());
        $action = isset($_GET['template_action']) && is_string($_GET['template_action']) ? $_GET['template_action'] : 'team-creation';
        $language = isset($_GET['language']) && is_string($_GET['language']) ? AutoResponseTemplate::language($_GET['language']) : 'en';
        AutoResponseTemplate::key($action, $language);
        echo '<h2>' . esc_html($context['race']['name'] ?? '') . '</h2><form method="get"><input type="hidden" name="page" value="cor-mgmt-responses"><input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><label>' . esc_html__('Action', 'h3-mgmt') . ' <select name="template_action">';
        foreach (AutoResponseTemplate::ACTIONS as $choice) {
            echo '<option value="' . esc_attr($choice) . '"' . selected($choice, $action, false) . '>' . esc_html(self::label($choice)) . '</option>';
        }
        echo '</select></label> <label>' . esc_html__('Language', 'h3-mgmt') . ' <input name="language" maxlength="16" value="' . esc_attr($language) . '" required></label> <button class="button" type="submit">' . esc_html__('Open template', 'h3-mgmt') . '</button></form>';
        $template = self::manager()->resolve($race, $action, $language);
        echo '<form method="post">' . self::fields($race) . '<input type="hidden" name="revision" value="' . esc_attr($context['revision']) . '"><input type="hidden" name="response_operation" value="save"><input type="hidden" name="template_action" value="' . esc_attr($action) . '"><input type="hidden" name="language" value="' . esc_attr($language) . '"><fieldset' . ($context['writable'] ? '' : ' disabled') . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native nonce and fields.
        echo '<p><label><input type="checkbox" name="enabled"' . checked($template->enabled ?? false, true, false) . '>' . esc_html__('Enable for this event', 'h3-mgmt') . '</label></p><p><label>' . esc_html__('Subject', 'h3-mgmt') . '<br><input class="large-text" name="subject" value="' . esc_attr($template->subject ?? '') . '"></label></p><p><label>' . esc_html__('Message', 'h3-mgmt') . '<br><textarea class="large-text" name="message" rows="12">' . esc_textarea($template->message ?? '') . '</textarea></label></p><p>' . esc_html__('Available placeholders', 'h3-mgmt') . ': ' . esc_html(implode(', ', array_map(static fn (string $key): string => '%' . $key . '%', AutoResponseTemplate::FIELDS))) . '</p><button class="button button-primary" type="submit">' . esc_html__('Save event template', 'h3-mgmt') . '</button></fieldset></form>';
        echo '<h3>' . esc_html__('Stored templates for this event', 'h3-mgmt') . '</h3><ul>';
        foreach ($context['templates'] as $row) {
            $url = add_query_arg(['page' => 'cor-mgmt-responses','race_id' => $race,'template_action' => $row['action'],'language' => $row['language']], admin_url('admin.php'));
            echo '<li><a href="' . esc_url($url) . '">' . esc_html(self::label($row['action'] ?? '') . ' / ' . ($row['language'] ?? '')) . '</a> — ' . esc_html($row['enabled'] === '1' ? __('Enabled', 'h3-mgmt') : __('Disabled', 'h3-mgmt')) . '</li>';
        }
        echo '</ul>';
        if ($context['writable']) {
            self::copyPreview($race, $db);
        }
        echo '<h3>' . esc_html__('Recent configuration changes', 'h3-mgmt') . '</h3><ul>';
        foreach ($db->rows($db->prepare('SELECT id,actor_id,created_at,kind FROM %i WHERE race_id=%d ORDER BY id DESC LIMIT 10', $db->table('cor_mgmt_communication_history'), $race)) as $history) {
            echo '<li>' . esc_html(($history['id'] ?? '') . ' · ' . ($history['kind'] ?? '') . ' · ' . ($history['actor_id'] ?? '') . ' · ' . gmdate('Y-m-d H:i:s', (int) $history['created_at']) . ' UTC') . '</li>';
        }
        echo '</ul>';
    }
    private static function copyPreview(int $race, Database $db): void
    {
        echo '<h3>' . esc_html__('Copy templates without overwriting', 'h3-mgmt') . '</h3><form method="get"><input type="hidden" name="page" value="cor-mgmt-responses"><input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><label>' . esc_html__('Source', 'h3-mgmt') . ' <select name="source_id">';
        if (current_user_can('manage_options')) {
            echo '<option value="0">' . esc_html__('Existing global templates', 'h3-mgmt') . '</option>';
        }
        foreach ($db->rows($db->prepare('SELECT id,name FROM %i WHERE id<>%d ORDER BY id DESC', $db->table('h3_mgmt_races'), $race)) as $source) {
            if (\H3_MGMT_Security::can_object('races', 'view', (int) $source['id'])) {
                echo '<option value="' . esc_attr($source['id'] ?? '') . '">' . esc_html(($source['name'] ?? '') . ' (#' . ($source['id'] ?? '') . ')') . '</option>';
            }
        }
        echo '</select></label> <button class="button" type="submit">' . esc_html__('Preview copy', 'h3-mgmt') . '</button></form>';
        if (!isset($_GET['source_id']) || !is_string($_GET['source_id'])) {
            return;
        }
        $source = TeamBoundary::number($_GET['source_id']);
        $preview = self::manager()->previewTransfer($race, $source, get_current_user_id());
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Action / language', 'h3-mgmt') . '</th><th>' . esc_html__('Result', 'h3-mgmt') . '</th><th>' . esc_html__('Subject and message', 'h3-mgmt') . '</th></tr></thead><tbody>';
        foreach ($preview['rows'] as $row) {
            echo '<tr><td>' . esc_html(self::label($row['action']) . ' / ' . $row['language']) . '</td><td>' . esc_html($row['status'] === 'create' ? __('Create independent copy', 'h3-mgmt') : __('Keep existing event template', 'h3-mgmt')) . '</td><td><strong>' . esc_html($row['subject']) . '</strong><pre>' . esc_html($row['message']) . '</pre></td></tr>';
        }
        echo '</tbody></table>';
        if ($preview['issues']) {
            echo '<p role="alert">' . esc_html__('Source review required. Duplicate, incomplete or invalid templates block this copy. Original content remains unchanged.', 'h3-mgmt') . '</p><ul>';
            foreach ($preview['issues'] as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        } elseif ($preview['rows']) {
            echo '<form method="post">' . self::fields($race) . '<input type="hidden" name="source_id" value="' . esc_attr((string) $source) . '"><input type="hidden" name="fingerprint" value="' . esc_attr($preview['fingerprint']) . '"><button name="response_operation" value="transfer" class="button" type="submit">' . esc_html__('Copy missing templates to this event', 'h3-mgmt') . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped native nonce and fields.
        }
    }
}
