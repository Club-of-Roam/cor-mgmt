<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Infrastructure\WordPress\MailQueueManagement;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Native administration; every POST rechecks event rights, nonce and queue revision.
 * @phpstan-import-type QueueContext from MailQueueManagement
 */
final class MailQueueAdmin
{
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_submenu_page('h3-mgmt-emails', __('Event mail queue', 'h3-mgmt'), __('Event mail queue', 'h3-mgmt'), 'h3_mgmt_send_emails', 'cor-mgmt-mail-queue', [self::class, 'page']);
        });
    }

    public static function serverEnabled(): bool
    {
        return defined('COR_MGMT_ALLOW_MAIL_DELIVERY') && COR_MGMT_ALLOW_MAIL_DELIVERY === true && wp_get_environment_type() === 'production';
    }

    public static function manager(): MailQueueManagement
    {
        global $wpdb;
        $runtime = Services::get();
        return new MailQueueManagement(new Database($wpdb), $runtime->clock, $runtime->random, $runtime->mail, static fn (int $race, int $actor, string $operation): bool => $actor === get_current_user_id() && current_user_can('h3_mgmt_send_emails') && \H3_MGMT_Security::can_object('races', $operation, $race), self::serverEnabled(...));
    }

    public static function submit(): string
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-mail-queue')) {
            throw new CommunicationException('invalid_nonce');
        }
        $operation = TeamBoundary::value('queue_operation');
        if (in_array($operation, ['send','confirm-delivered'], true) && TeamBoundary::value('confirm') !== '1') {
            throw new CommunicationException('confirmation_required');
        }
        return self::manager()->mutate(TeamBoundary::number(TeamBoundary::value('race_id', '0')), get_current_user_id(), TeamBoundary::value('revision'), $operation, TeamBoundary::number(TeamBoundary::value('message_id', '0')));
    }

    /** @param QueueContext $context */
    private static function form(int $race, array $context, string $operation, string $label, int $message = 0, bool $disabled = false): void
    {
        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field('cor-mgmt-mail-queue', 'cor_mgmt_nonce', false);
        echo '<input type="hidden" name="race_id" value="' . esc_attr((string) $race) . '"><input type="hidden" name="revision" value="' . esc_attr((string) $context['revision']) . '"><input type="hidden" name="message_id" value="' . esc_attr((string) $message) . '"><input type="hidden" name="queue_operation" value="' . esc_attr($operation) . '">';
        if (in_array($operation, ['send','confirm-delivered'], true)) {
            echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html($operation === 'send' ? __('I reviewed the recipient and this message and authorize this single send.', 'h3-mgmt') : __('I verified externally that this message was accepted. This records the decision without sending again.', 'h3-mgmt')) . '</label></p>';
        }
        echo '<button class="button" type="submit"' . disabled($disabled || !$context['writable'], true, false) . '>' . esc_html($label) . '</button></form>';
    }

    public static function page(): void
    {
        if (!current_user_can('h3_mgmt_send_emails')) {
            echo '<p>' . esc_html__('Not permitted.', 'h3-mgmt') . '</p>';
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Event mail queue', 'h3-mgmt') . '</h1>';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                $outcome = self::submit();
                echo '<p role="status">' . esc_html__('Result:', 'h3-mgmt') . ' ' . esc_html($outcome) . '</p>';
            } catch (\Throwable $error) {
                Services::get()->logger->error('mail_queue_admin_rejected', $error);
                $message = $error instanceof CommunicationException && $error->reason === 'invitation_no_longer_valid' ? __('This invitation can no longer be accepted. Check registration, membership capacity and token status, then close the message if appropriate. No email was sent.', 'h3-mgmt') : __('The request was rejected. Reload and check event rights, queue status, revision and delivery settings.', 'h3-mgmt');
                echo '<p role="alert">' . esc_html($message) . '</p>';
            }
        }
        try {
            global $wpdb;
            $db = new Database($wpdb);
            $events = $db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races')));
            $raw = $_POST['race_id'] ?? $_GET['race_id'] ?? '0';
            $race = is_string($raw) ? TeamBoundary::number(wp_unslash($raw)) : 0;
            echo '<form method="get"><input type="hidden" name="page" value="cor-mgmt-mail-queue"><label>' . esc_html__('Event', 'h3-mgmt') . ' <select name="race_id"><option value="0">' . esc_html__('Select an event', 'h3-mgmt') . '</option>';
            foreach ($events as $event) {
                $id = (int) $event['id'];
                if (\H3_MGMT_Security::can_object('races', 'view', $id)) {
                    echo '<option value="' . esc_attr((string) $id) . '"' . selected($race, $id, false) . '>' . esc_html($event['name'] ?? '') . '</option>';
                }
            }
            echo '</select></label> <button type="submit" class="button">' . esc_html__('Show', 'h3-mgmt') . '</button></form>';
            if ($race < 1) {
                echo '</div>';
                return;
            }
            $rawMessage = $_POST['message_id'] ?? $_GET['message_id'] ?? '0';
            $message = is_string($rawMessage) ? TeamBoundary::number(wp_unslash($rawMessage)) : 0;
            $rawPage = $_GET['queue_page'] ?? '1';
            $page = is_string($rawPage) ? TeamBoundary::number(wp_unslash($rawPage)) : 1;
            $context = self::manager()->context($race, get_current_user_id(), $page, $message);
            echo '<h2>' . esc_html($context['event']['name'] ?? '') . '</h2><p>' . esc_html__('Messages use the event template and recipient snapshot recorded at the time of the action. No automatic background delivery is active.', 'h3-mgmt') . '</p>';
            if (!$context['server_enabled']) {
                echo '<p class="notice notice-warning">' . esc_html__('Server delivery is disabled. Preview and administration remain available; no real email can be sent here.', 'h3-mgmt') . '</p>';
            }
            echo '<p>' . esc_html($context['enabled'] ? __('Event delivery is enabled.', 'h3-mgmt') : __('Event delivery is paused.', 'h3-mgmt')) . '</p>';
            self::form($race, $context, $context['enabled'] ? 'disable' : 'enable', $context['enabled'] ? __('Pause event delivery', 'h3-mgmt') : __('Enable controlled event delivery', 'h3-mgmt'));
            self::form($race, $context, 'recover', __('Review expired claims', 'h3-mgmt'));
            echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__('Action', 'h3-mgmt') . '</th><th>' . esc_html__('Recipient', 'h3-mgmt') . '</th><th>' . esc_html__('Language', 'h3-mgmt') . '</th><th>' . esc_html__('Status / attempts', 'h3-mgmt') . '</th></tr></thead><tbody>';
            if (!$context['rows']) {
                echo '<tr><td colspan="5">' . esc_html__('No messages for this event.', 'h3-mgmt') . '</td></tr>';
            }
            foreach ($context['rows'] as $row) {
                $url = add_query_arg(['page' => 'cor-mgmt-mail-queue', 'race_id' => $race, 'message_id' => $row['id']], admin_url('admin.php'));
                echo '<tr><td><a href="' . esc_url($url) . '">' . esc_html($row['id'] ?? '') . '</a></td><td>' . esc_html(CommunicationAdmin::label($row['action'] ?? '')) . '</td><td>' . esc_html($row['recipient'] ?? '') . '</td><td>' . esc_html($row['language'] ?? '') . '</td><td>' . esc_html(($row['status'] ?? '') . ' / ' . ($row['attempts'] ?? '') . ' ' . ($row['reason'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table><p>' . esc_html__('Pages:', 'h3-mgmt') . ' ';
            foreach (array_unique([1, max(1, $context['page'] - 1), $context['page'], min($context['pages'], $context['page'] + 1), $context['pages']]) as $number) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'cor-mgmt-mail-queue', 'race_id' => $race, 'queue_page' => $number], admin_url('admin.php'))) . '">' . esc_html((string) $number) . '</a> ';
            }
            echo '</p>';
            echo '<h3>' . esc_html__('Recent operator decisions', 'h3-mgmt') . '</h3><ul>';
            foreach ($context['decisions'] as $decision) {
                echo '<li>' . esc_html(implode(' / ', array_map(static fn (?string $value): string => $value ?? '', $decision))) . '</li>';
            }
            echo '</ul>';
            $selected = $context['selected'];
            if ($selected) {
                echo '<h2>' . esc_html__('Message preview', 'h3-mgmt') . '</h2><p>' . esc_html(($selected['recipient'] ?? '') . ' / ' . ($selected['language'] ?? '') . ' / ' . ($selected['occurrence'] ?? '') . ' / revision ' . ($selected['template_revision'] ?? '')) . '</p><h3>' . esc_html($selected['subject'] ?? '') . '</h3><pre style="white-space:pre-wrap">' . esc_html($selected['message'] ?? '') . '</pre>';
                $status = $selected['status'] ?? '';
                if ($status === 'blocked') {
                    self::form($race, $context, 'prepare', __('Prepare with the current event template without sending', 'h3-mgmt'), $message);
                }
                if ($status === 'pending') {
                    self::form($race, $context, 'send', __('Send this message once', 'h3-mgmt'), $message, !$context['enabled'] || !$context['server_enabled']);
                }
                if ($status === 'failed' && (int) $selected['attempts'] < 3) {
                    self::form($race, $context, 'retry', __('Queue the same snapshot for another attempt', 'h3-mgmt'), $message);
                }
                if ($status === 'uncertain') {
                    echo '<p>' . esc_html__('Delivery may already have happened. Verify the provider outcome before recording a decision; automatic retry is blocked.', 'h3-mgmt') . '</p>';
                    self::form($race, $context, 'confirm-delivered', __('Record confirmed transport acceptance', 'h3-mgmt'), $message);
                }
                if (in_array($status, ['pending','blocked','skipped','failed','uncertain'], true)) {
                    self::form($race, $context, 'cancel', __('Close without further delivery', 'h3-mgmt'), $message);
                }
                echo '<h3>' . esc_html__('Status history', 'h3-mgmt') . '</h3><ul>';
                foreach ($context['history'] as $entry) {
                    echo '<li>' . esc_html(implode(' / ', array_map(static fn (?string $value): string => $value ?? '', $entry))) . '</li>';
                }
                echo '</ul>';
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('mail_queue_view_rejected', $error);
            echo '<p role="alert">' . esc_html__('Queue unavailable. Check event permissions and set up communication and queue storage in system status.', 'h3-mgmt') . '</p>';
        }
        echo '</div>';
    }
}
