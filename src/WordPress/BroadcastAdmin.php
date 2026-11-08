<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Infrastructure\WordPress\Broadcasts;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;

/** Native manual mail UI: preview, immutable snapshot, bounded explicit processing.
 * @phpstan-import-type Campaign from Broadcasts
 */
final class BroadcastAdmin
{
    public static function register(): void
    {
        add_action('wp_ajax_cor_mgmt_broadcast_step', [self::class, 'ajax']);
        add_action('admin_enqueue_scripts', static function (): void {
            if (($_GET['page'] ?? '') === 'h3-mgmt-emails' && current_user_can('h3_mgmt_send_emails')) {
                wp_enqueue_script('cor-mgmt-broadcast', plugins_url('js/h3-mgmt-broadcast.js', dirname(__DIR__, 2) . '/h3-management.php'), [], '1', true);
            }
        });
    }

    /** @return array{revision:string,done:bool,stop:bool,message:string,progress:array<string,int>} */
    public static function step(): array
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-broadcast')) {
            throw new CommunicationException('invalid_nonce');
        }
        $operation = TeamBoundary::value('broadcast_operation');
        if ($operation === 'send-batch' && TeamBoundary::value('confirm') !== '1') {
            throw new CommunicationException('confirmation_required');
        }
        $result = self::manager()->step(TeamBoundary::number(TeamBoundary::value('campaign_id', '0')), get_current_user_id(), TeamBoundary::value('revision'), $operation);
        $progress = $result['progress'];
        // translators: The five integers count unprepared, pending, accepted, failed and uncertain campaign messages.
        $message = sprintf(__('Not prepared: %1$d · Pending: %2$d · Accepted: %3$d · Failed: %4$d · Needs review: %5$d', 'h3-mgmt'), $progress['unprepared'], $progress['pending'], $progress['sent'], $progress['failed'], $progress['uncertain'] + $progress['sending']);
        return $result + ['message' => $message];
    }

    public static function ajax(): void
    {
        try {
            wp_send_json_success(self::step());
        } catch (\Throwable $error) {
            Services::get()->logger->error('broadcast_batch_rejected', $error);
            wp_send_json_error(['code' => $error instanceof CommunicationException ? $error->reason : 'request_rejected'], 409);
        }
    }

    public static function manager(): Broadcasts
    {
        global $wpdb;
        $runtime = Services::get();
        $db = new Database($wpdb);
        return new Broadcasts($db, $runtime->clock, $runtime->random, $runtime->mail, static function (int $actor, array $selection) use ($db): bool {
            if ($actor !== get_current_user_id() || !current_user_can('h3_mgmt_send_emails')) {
                return false;
            }
            $mode = $selection['mode'] ?? '';
            if ($mode === 'all') {
                return current_user_can('manage_options');
            }
            if ($mode === 'race') {
                return \H3_MGMT_Security::can_object('races', 'edit', (int) ($selection['id'] ?? '0'));
            }
            if ($mode === 'team') {
                $race = (int) $db->scalar($db->prepare('SELECT race_id FROM %i WHERE id=%d', $db->table('h3_mgmt_teams'), (int) ($selection['id'] ?? '0')));
                return $race > 0 && \H3_MGMT_Security::can_object('races', 'edit', $race);
            }
            return in_array($mode, ['self','email'], true);
        }, MailQueueAdmin::serverEnabled(...));
    }
    /** @return array<string,string> */
    public static function selection(): array
    {
        $result = [];
        foreach (['mode','id','email','language','sender'] as $field) {
            $result[$field] = TeamBoundary::value($field, $field === 'id' ? '0' : ($field === 'sender' ? 'nr' : ($field === 'mode' ? 'self' : '')));
        }
        $audience = TeamBoundary::value('audience');
        if ($audience !== '') {
            if (preg_match('/^(race|team)-([1-9][0-9]*)$/D', $audience, $parts)) {
                $result['mode'] = $parts[1];
                $result['id'] = $parts[2];
            } elseif (in_array($audience, ['self','email','all'], true)) {
                $result['mode'] = $audience;
                $result['id'] = '0';
            } else {
                throw new CommunicationException('invalid_audience');
            }
        }
        return $result;
    }
    /** @return int|string */
    public static function submit(): int|string
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-broadcast')) {
            throw new CommunicationException('invalid_nonce');
        }
        $manager = self::manager();
        $operation = TeamBoundary::value('broadcast_operation');
        if ($operation === 'create') {
            return $manager->create(get_current_user_id(), TeamBoundary::value('request'), self::selection(), TeamBoundary::value('subject'), TeamBoundary::value('message'), TeamBoundary::value('fingerprint'));
        }
        if (in_array($operation, ['send-batch','send','confirm-delivered'], true) && TeamBoundary::value('confirm') !== '1') {
            throw new CommunicationException('confirmation_required');
        }
        $id = TeamBoundary::number(TeamBoundary::value('campaign_id', '0'));
        if ($operation === 'send-batch') {
            return 'sent:' . $manager->sendBatch($id, get_current_user_id(), TeamBoundary::value('revision'));
        }
        return $manager->mutate($id, get_current_user_id(), TeamBoundary::value('revision'), $operation, TeamBoundary::number(TeamBoundary::value('queue_id', '0')));
    }
    private static function hidden(string $key, string $value): void
    {
        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }
    /** @param Campaign $context */
    private static function action(array $context, string $operation, string $label, int $message = 0): void
    {
        echo '<form method="post" style="margin:1em 0">';
        wp_nonce_field('cor-mgmt-broadcast', 'cor_mgmt_nonce', false);
        foreach (['campaign_id' => (string) $context['job']['id'], 'revision' => $context['revision'], 'broadcast_operation' => $operation, 'queue_id' => (string) $message] as $key => $value) {
            self::hidden($key, $value);
        }
        if (in_array($operation, ['send-batch','send','confirm-delivered'], true)) {
            echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html($operation === 'confirm-delivered' ? __('I verified externally that this message was accepted. Record without sending again.', 'h3-mgmt') : __('I reviewed this campaign and authorize the displayed delivery step.', 'h3-mgmt')) . '</label></p>';
        }
        echo '<button class="button" type="submit">' . esc_html($label) . '</button></form>';
    }

    /** @param Campaign $context */
    private static function runner(array $context): void
    {
        echo '<section hidden data-cor-broadcast-run data-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('cor-mgmt-broadcast')) . '" data-campaign="' . esc_attr($context['job']['id'] ?? '') . '" data-revision="' . esc_attr($context['revision']) . '" data-error="' . esc_attr__('Processing stopped. Reload and review the campaign before continuing.', 'h3-mgmt') . '" data-complete="' . esc_attr__('Step processing finished. Reload the list to review message states.', 'h3-mgmt') . '" data-stopped="' . esc_attr__('Stopped after the current step. Reload before continuing.', 'h3-mgmt') . '"><h3>' . esc_html__('Process campaign in steps', 'h3-mgmt') . '</h3><p>' . esc_html__('Keep this page open. Each request prepares at most 100 messages or sends at most 5. Stop waits for the current step; errors and unclear transport outcomes stop processing without automatic retry.', 'h3-mgmt') . '</p><button type="button" class="button" data-batch-operation="queue">' . esc_html__('Prepare campaign in steps', 'h3-mgmt') . '</button> ';
        if ($context['server_enabled'] && $context['job']['enabled'] === '1') {
            echo '<p><label><input type="checkbox" data-batch-confirm> ' . esc_html__('I reviewed this campaign and authorize processing its pending messages in steps.', 'h3-mgmt') . '</label></p><button type="button" class="button" data-batch-operation="send-batch">' . esc_html__('Start campaign delivery', 'h3-mgmt') . '</button> ';
        }
        echo '<button type="button" class="button" data-batch-stop disabled>' . esc_html__('Stop after current step', 'h3-mgmt') . '</button><p><output data-batch-report role="status" aria-live="polite"></output></p><p><a href="' . esc_url(add_query_arg(['page' => 'h3-mgmt-emails', 'campaign_id' => $context['job']['id']], admin_url('admin.php'))) . '">' . esc_html__('Reload campaign list', 'h3-mgmt') . '</a></p></section>';
    }
    private static function compose(): void
    {
        $selection = self::selection();
        if (!isset($_POST['mode']) && is_string($_GET['email'] ?? null)) {
            $selection['mode'] = 'email';
            $selection['email'] = wp_unslash($_GET['email']);
        }
        echo '<h2>' . esc_html__('New manual email', 'h3-mgmt') . '</h2><form method="post">';
        wp_nonce_field('cor-mgmt-broadcast', 'cor_mgmt_nonce', false);
        self::hidden('broadcast_operation', 'preview');
        echo '<p><label>' . esc_html__('Audience', 'h3-mgmt') . ' <select name="audience">';
        foreach (['self' => __('Myself', 'h3-mgmt'), 'email' => __('Individual email address', 'h3-mgmt'), 'all' => __('All former participants (administrators)', 'h3-mgmt')] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($selection['mode'], $key, false) . '>' . esc_html($label) . '</option>';
        }

        global $wpdb;
        $db = new Database($wpdb);
        $events = $db->rows($db->prepare('SELECT id,name FROM %i ORDER BY id DESC', $db->table('h3_mgmt_races')));
        foreach ($events as $event) {
            if (!\H3_MGMT_Security::can_object('races', 'edit', (int) $event['id'])) {
                continue;
            }
            echo '<option value="' . esc_attr('race-' . ($event['id'] ?? '')) . '"' . selected($selection['mode'] === 'race' ? $selection['id'] : '', $event['id'], false) . '>' . esc_html(__('Event', 'h3-mgmt') . ' #' . $event['id'] . ': ' . $event['name']) . '</option>';
            foreach ($db->rows($db->prepare('SELECT id,team_name FROM %i WHERE race_id=%d ORDER BY id', $db->table('h3_mgmt_teams'), (int) $event['id'])) as $team) {
                echo '<option value="' . esc_attr('team-' . ($team['id'] ?? '')) . '"' . selected($selection['mode'] === 'team' ? $selection['id'] : '', $team['id'], false) . '>' . esc_html(__('Team', 'h3-mgmt') . ' #' . $team['id'] . ': ' . $team['team_name']) . '</option>';
            }
        }
        echo '</select></label></p>';
        foreach (['email' => __('Individual email address', 'h3-mgmt'), 'language' => __('Optional language filter, e.g. de or en', 'h3-mgmt')] as $key => $label) {
            echo '<p><label>' . esc_html($label) . ' <input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($selection[$key]) . '"></label></p>';
        }
        // translators: Percent-delimited words are literal mail template tokens, not printf placeholders.
        echo '<p><label>' . esc_html__('Sender', 'h3-mgmt') . ' <select name="sender"><option value="nr"' . selected($selection['sender'], 'nr', false) . '>no-reply@tramprennen.org</option><option value="own"' . selected($selection['sender'], 'own', false) . '>' . esc_html__('My own address', 'h3-mgmt') . '</option></select></label></p><p><label>' . esc_html__('Subject', 'h3-mgmt') . '<br><input type="text" name="subject" required size="70" value="' . esc_attr(TeamBoundary::value('subject')) . '"></label></p><p><label>' . esc_html__('Message', 'h3-mgmt') . '<br><textarea name="message" rows="10" cols="80" required>' . esc_textarea(TeamBoundary::value('message')) . '</textarea></label></p><p>' . esc_html__('Plain text. %name% uses the first name; event/team audiences also provide %team_name%, %event_name% and %link%. Missing or conflicting values block creation. Messages are not translated automatically.', 'h3-mgmt') . '</p><button class="button" type="submit">' . esc_html__('Preview audience and messages', 'h3-mgmt') . '</button></form>';
    }
    private static function preview(): void
    {
        if (!\H3_MGMT_Security::post('cor-mgmt-broadcast')) {
            throw new CommunicationException('invalid_nonce');
        }
        $preview = self::manager()->preview(get_current_user_id(), self::selection(), TeamBoundary::value('subject'), TeamBoundary::value('message'));
        echo '<h2>' . esc_html__('Preview', 'h3-mgmt') . '</h2><p>' . esc_html(__('Recipients:', 'h3-mgmt') . ' ' . count($preview['recipients'])) . '</p><p>' . esc_html($preview['sender']['name'] . ' <' . $preview['sender']['email'] . '>') . '</p>';
        foreach ($preview['issues'] as $issue) {
            echo '<p role="alert">' . esc_html($issue) . '</p>';
        }
        foreach (array_slice($preview['recipients'], 0, 20) as $row) {
            echo '<details><summary>' . esc_html($row['recipient'] . ' · ' . $row['language'] . ' · ' . $row['subject']) . '</summary><pre style="white-space:pre-wrap">' . esc_html($row['message']) . '</pre></details>';
        }
        echo '<p>' . esc_html__('The first 20 messages are shown. Creation stores the reviewed audience and content; it does not send mail.', 'h3-mgmt') . '</p>';
        if (!$preview['issues']) {
            echo '<form method="post">';
            wp_nonce_field('cor-mgmt-broadcast', 'cor_mgmt_nonce', false);
            foreach ($preview['selection'] + ['subject' => TeamBoundary::value('subject'), 'message' => TeamBoundary::value('message'), 'fingerprint' => $preview['fingerprint'], 'request' => bin2hex(Services::get()->random->bytes(16)), 'broadcast_operation' => 'create'] as $key => $value) {
                self::hidden($key, $value);
            }
            echo '<button class="button" type="submit">' . esc_html__('Create reviewed campaign without sending', 'h3-mgmt') . '</button></form>';
        }
    }
    public static function page(): void
    {
        if (!current_user_can('h3_mgmt_send_emails')) {
            echo '<p>' . esc_html__('Not permitted.', 'h3-mgmt') . '</p>';
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Manual emails and campaigns', 'h3-mgmt') . '</h1>';
        $id = TeamBoundary::number(TeamBoundary::value('campaign_id', is_string($_GET['campaign_id'] ?? null) ? wp_unslash($_GET['campaign_id']) : '0'));
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (TeamBoundary::value('broadcast_operation') === 'preview') {
                    self::preview();
                } else {
                    $result = self::submit();
                    if (is_int($result)) {
                        $id = $result;
                    }
                    echo '<p role="status">' . esc_html(__('Result:', 'h3-mgmt') . ' ' . $result) . '</p>';
                }
            }
        } catch (\Throwable $error) {
            Services::get()->logger->error('broadcast_rejected', $error);
            echo '<p role="alert">' . esc_html__('Request rejected. Reload and review audience, permissions, revision, message status and delivery settings. No automatic retry was started.', 'h3-mgmt') . '</p>';
        }
        try {
            $manager = self::manager();
            if ($id > 0) {
                $page = TeamBoundary::number(is_string($_GET['recipient_page'] ?? null) ? wp_unslash($_GET['recipient_page']) : '1');
                $context = $manager->context($id, get_current_user_id(), $page);
                self::runner($context);
                echo '<h2>' . esc_html(__('Campaign', 'h3-mgmt') . ' #' . $id) . '</h2><p>' . esc_html(__('Recipients:', 'h3-mgmt') . ' ' . $context['count'] . ' · ' . ($context['job']['enabled'] === '1' ? __('Released', 'h3-mgmt') : __('Paused', 'h3-mgmt'))) . '</p><p>' . esc_html($context['server_enabled'] ? __('Server delivery is enabled.', 'h3-mgmt') : __('Server delivery is disabled. No email can be sent.', 'h3-mgmt')) . '</p>';
                self::action($context, 'queue', __('Prepare next 100 messages', 'h3-mgmt'));
                self::action($context, $context['job']['enabled'] === '1' ? 'pause' : 'enable', $context['job']['enabled'] === '1' ? __('Pause campaign', 'h3-mgmt') : __('Release campaign', 'h3-mgmt'));
                if ($context['server_enabled'] && $context['job']['enabled'] === '1') {
                    self::action($context, 'send-batch', __('Send next 5 messages; stop on failure', 'h3-mgmt'));
                }
                foreach ($context['rows'] as $row) {
                    echo '<details><summary>' . esc_html($row['recipient'] . ' · ' . $row['language'] . ' · ' . ($row['status'] ?? __('Not queued', 'h3-mgmt')) . ' · ' . ($row['attempts'] ?? '0')) . '</summary><h3>' . esc_html($row['subject'] ?? '') . '</h3><pre style="white-space:pre-wrap">' . esc_html($row['message'] ?? '') . '</pre>';
                    $message = (int) $row['queue_id'];
                    $status = $row['status'] ?? '';
                    if ($status === 'pending' && $context['server_enabled'] && $context['job']['enabled'] === '1') {
                        self::action($context, 'send', __('Send this message', 'h3-mgmt'), $message);
                    }
                    if ($status === 'failed' && (int) $row['attempts'] < 3) {
                        self::action($context, 'retry', __('Prepare explicit retry', 'h3-mgmt'), $message);
                    }
                    if ($status === 'sending') {
                        self::action($context, 'recover', __('Mark expired claim as uncertain', 'h3-mgmt'), $message);
                    }
                    if (in_array($status, ['pending','failed','uncertain'], true)) {
                        self::action($context, 'cancel', __('Close without sending', 'h3-mgmt'), $message);
                    }
                    if ($status === 'uncertain') {
                        self::action($context, 'confirm-delivered', __('Record externally verified acceptance', 'h3-mgmt'), $message);
                    }
                    echo '</details>';
                }
                echo '<nav aria-label="' . esc_attr__('Recipient pages', 'h3-mgmt') . '">';
                foreach (['Previous' => $context['page'] - 1, 'Next' => $context['page'] + 1] as $label => $target) {
                    if ($target > 0 && $target <= $context['pages']) {
                        echo '<a href="' . esc_url(add_query_arg(['page' => 'h3-mgmt-emails', 'campaign_id' => $id, 'recipient_page' => $target], admin_url('admin.php'))) . '">' . esc_html($label) . '</a> ';
                    }
                }
                echo '</nav><h3>' . esc_html__('Campaign decisions', 'h3-mgmt') . '</h3>';
                foreach ($context['history'] as $entry) {
                    echo '<p>' . esc_html($entry['created_at'] . ' · User ' . $entry['actor_id'] . ' · ' . $entry['operation'] . ' · #' . $entry['message_id']) . '</p>';
                }
            }
            echo '<h2>' . esc_html__('Recent campaigns', 'h3-mgmt') . '</h2>';
            $jobsPage = TeamBoundary::number(is_string($_GET['campaign_page'] ?? null) ? wp_unslash($_GET['campaign_page']) : '1');
            $jobs = $manager->jobs(get_current_user_id(), $jobsPage);
            foreach ($jobs as $job) {
                echo '<p><a href="' . esc_url(add_query_arg(['page' => 'h3-mgmt-emails', 'campaign_id' => $job['id']], admin_url('admin.php'))) . '">' . esc_html('#' . $job['id'] . ' · ' . $job['created_at']) . '</a></p>';
            }
            foreach (['Previous' => $jobsPage - 1, 'Next' => $jobsPage + 1] as $label => $target) {
                if ($target > 0 && ($target < $jobsPage || count($jobs) === 30)) {
                    echo '<a href="' . esc_url(add_query_arg(['page' => 'h3-mgmt-emails', 'campaign_page' => $target], admin_url('admin.php'))) . '">' . esc_html($label) . '</a> ';
                }
            }
            self::compose();
        } catch (\Throwable $error) {
            Services::get()->logger->error('broadcast_read_failed', $error);
            echo '<p role="alert">' . esc_html__('Review additional module setup and access rights in the system tools.', 'h3-mgmt') . '</p>';
        }
        echo '</div>';
    }
}
