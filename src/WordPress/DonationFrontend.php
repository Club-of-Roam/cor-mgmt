<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Domain\BetterplaceEvent;
use Cor\Mgmt\Domain\EuroAmount;
use Cor\Mgmt\Infrastructure\Betterplace\PublicOpinions;
use Cor\Mgmt\Infrastructure\WordPress\DonationLedger;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema;

final class DonationFrontend
{
    public static function ledger(): DonationLedger
    {
        global $wpdb;
        $runtime = Services::get();
        return new DonationLedger(new Database($wpdb), $runtime->clock, $runtime->random, new PublicOpinions($runtime->http));
    }
    /** @param array<string,mixed> $attributes */
    public static function render(array $attributes): string
    {
        global $wpdb, $h3_mgmt_races;
        $uploaded = null;
        try {
            $race = ($attributes['race'] ?? '') === 'active' ? (int) $h3_mgmt_races->get_active_race() : \H3_MGMT_Security::id($attributes['race'] ?? 0);
            $db = new Database($wpdb);
            $rules = json_decode($db->scalar($db->prepare('SELECT setting FROM %i WHERE id=%d', $db->table('h3_mgmt_races'), $race)) ?? '', true);
            if (is_array($rules) && !in_array($rules['donation'] ?? null, [1,'1'], true)) {
                $texts = $h3_mgmt_races->get_race_information_text($race);
                return \H3_MGMT_Security::html(is_string($texts[24] ?? null) ? $texts[24] : '');
            }
            if (!is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,'0','1','2','3'], true) || !DonationSchema::ready($db)) {
                throw new DonationException('donations_closed');
            }
            $event = new BetterplaceEvent(is_string($rules['betterplace_redirect_link'] ?? null) ? $rules['betterplace_redirect_link'] : '');
            $session = is_string($_COOKIE['client_token'] ?? null) ? $_COOKIE['client_token'] : '';
            $row = null;
            if (isset($_POST['submitted'])) {
                if (!\H3_MGMT_Security::donation_request($race) || TeamBoundary::value('address') !== '') {
                    return \H3_MGMT_Security::error();
                }
                $step = TeamBoundary::value('submitted');
                if ($step === '1') {
                    $row = self::ledger()->begin($race, TeamBoundary::number(TeamBoundary::value('team_id')), TeamBoundary::value('type'), $session, str_starts_with(determine_locale(), 'de') ? 'de' : 'en', isset($_POST['new']));
                } elseif ($step === '2') {
                    if (Services::get()->http instanceof \Cor\Mgmt\Infrastructure\WordPress\WordPressHttpClient && (wp_get_environment_type() !== 'production' || (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL))) {
                        throw new DonationException('provider_unavailable');
                    }
                    $row = self::ledger()->verify($race, TeamBoundary::value('donation_token'), $session);
                } elseif ($step === '3') {
                    $token = TeamBoundary::value('donation_token');
                    $old = self::ledger()->find($race, $token, $session);
                    if ($old['state'] !== 'confirmed') {
                        throw new DonationException('payment_not_confirmed');
                    }
                    $values = ['display_name' => sanitize_text_field(TeamBoundary::value('display_name')), 'message' => sanitize_textarea_field(TeamBoundary::value('message'))];
                    if ($old['type'] === 'owner') {
                        $values['owner_link'] = esc_url_raw(TeamBoundary::value('owner_link'), ['http','https']);
                        if (!empty($_FILES['owner_pic']['name'])) {
                            $uploaded = \H3_MGMT_Security::upload('owner_pic');
                            if (is_wp_error($uploaded)) {
                                $uploaded = null;
                                throw new DonationException('upload_failed');
                            }
                            $values['owner_pic'] = $uploaded['url'];
                        }
                    }
                    $row = self::ledger()->profile($race, $token, $session, $values);
                    $uploaded = null;
                    return '<p role="status">' . esc_html__('Your donation profile has been saved. Thank you!', 'h3-mgmt') . '</p>' . self::profileForm($race, $row);
                } else {
                    throw new DonationException('invalid_input');
                }
            }
            if ($row !== null) {
                return $row['state'] === 'confirmed' ? self::profileForm($race, $row) : self::checkout($race, $event, $row);
            }
            $teams = $db->rows($db->prepare('SELECT id,team_name FROM %i WHERE race_id=%d ORDER BY team_name,id', $db->table('h3_mgmt_teams'), $race));
            $type = isset($_GET['type']) && is_string($_GET['type']) && $_GET['type'] === 'owner' ? 'owner' : 'sponsor';
            $html = '<form method="post" class="cor-donation-form">' . self::fields($race, 1) . '<label>' . esc_html__('Team', 'h3-mgmt') . ' <select name="team_id" required>';
            foreach ($teams as $team) {
                $html .= '<option value="' . esc_attr($team['id'] ?? '') . '"' . selected((int) ($team['id'] ?? 0), \H3_MGMT_Security::id($_GET['id'] ?? 0), false) . '>' . esc_html($team['team_name'] ?? '') . '</option>';
            }
            $html .= '</select></label><label>' . esc_html__('Support type', 'h3-mgmt') . ' <select name="type"><option value="sponsor">' . esc_html__('TeamSponsor', 'h3-mgmt') . '</option>';
            if ((int) ($rules['kind_of_donation_tool'] ?? 0) !== 1) {
                $html .= '<option value="owner"' . selected($type, 'owner', false) . '>' . esc_html__('TeamOwner (at least EUR 100, subject to availability)', 'h3-mgmt') . '</option>';
            }
            $html .= '</select></label><button type="submit">' . esc_html__('Continue to Betterplace', 'h3-mgmt') . '</button></form>';
            return $teams ? $html : '<p>' . esc_html__('No teams are available.', 'h3-mgmt') . '</p>';
        } catch (\Throwable $error) {
            if (is_array($uploaded) && isset($uploaded['file'])) {
                wp_delete_file($uploaded['file']);
            }
            Services::get()->logger->error('donation_workflow_failed', $error);
            $message = $error instanceof DonationException ? match ($error->reason) {
                'amount_unavailable' => __('The donation amount is not public. Payment and TeamOwner status could not be confirmed. Please contact the organizers.', 'h3-mgmt'),
                'donation_not_found', 'provider_unavailable', 'search_incomplete' => __('The donation could not yet be assigned. Your entry remains pending. Return to the previous form and try Done again later, or contact the organizers.', 'h3-mgmt'),
                'owner_unavailable' => __('This team already has a TeamOwner. Choose TeamSponsor or another team.', 'h3-mgmt'),
                'opinion_already_used', 'ambiguous_donation' => __('This donation requires review by the organizers. It has not been assigned again.', 'h3-mgmt'),
                default => __('The donation form is unavailable or the request could not be accepted. Reload the form or contact the organizers.', 'h3-mgmt'),
            } : __('The donation request could not be completed. Reload the form or contact the organizers.', 'h3-mgmt');
            return '<p class="error">' . esc_html($message) . '</p>';
        }
    }
    private static function fields(int $race, int $step, string $token = ''): string
    {
        return \H3_MGMT_Security::nonce(\H3_MGMT_Security::donation_nonce($race)) . '<input type="hidden" name="submitted" value="' . esc_attr((string) $step) . '"><input type="hidden" name="donation_token" value="' . esc_attr($token) . '"><input type="text" name="address" class="cor-donation-trap" value="" tabindex="-1" autocomplete="off" aria-label="' . esc_attr__('Leave empty', 'h3-mgmt') . '" style="display:none">';
    }
    /** @param array<string,string|null> $row */
    private static function checkout(int $race, BetterplaceEvent $event, array $row): string
    {
        $language = str_starts_with(determine_locale(), 'de') ? 'de' : 'en';
        $external = wp_get_environment_type() !== 'production' || (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL);
        $provider = $external ? '<p>' . esc_html__('External donations are disabled in this environment.', 'h3-mgmt') . '</p>'
            : '<iframe title="' . esc_attr__('Betterplace donation form', 'h3-mgmt') . '" src="' . esc_url($event->form($language)) . '" width="100%" height="600"></iframe><p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($event->url) . '">' . esc_html__('Open Betterplace in a new tab', 'h3-mgmt') . '</a></p>';
        return '<section class="cor-donation-checkout"><h3>' . esc_html__('Donate on Betterplace', 'h3-mgmt') . '</h3><p>' . esc_html__('Copy this assignment code into the public comment on Betterplace. Keep the amount visible if you want automatic assignment. After donating, return here and press Done.', 'h3-mgmt') . '</p><p><code>' . esc_html($row['token'] ?? '') . '</code></p>' . $provider . '<form method="post">' . self::fields($race, 2, $row['token'] ?? '') . '<button type="submit">' . esc_html__('Done — check assignment', 'h3-mgmt') . '</button></form></section>';
    }
    /** @param array<string,string|null> $row */
    private static function profileForm(int $race, array $row): string
    {
        $html = '<p>' . esc_html__('Donation assigned:', 'h3-mgmt') . ' ' . esc_html((new EuroAmount((int) $row['amount_minor']))->format()) . ' EUR — ' . esc_html($row['type'] === 'owner' ? __('TeamOwner', 'h3-mgmt') : __('TeamSponsor', 'h3-mgmt')) . '</p><form method="post" enctype="multipart/form-data" class="cor-donation-form">' . self::fields($race, 3, $row['token'] ?? '');
        foreach (['display_name' => __('Display name (leave blank to stay anonymous)', 'h3-mgmt'), 'message' => __('Message to the team', 'h3-mgmt')] as $name => $label) {
            $html .= '<label>' . esc_html($label) . ' <textarea name="' . esc_attr($name) . '">' . esc_textarea($row[$name] ?? '') . '</textarea></label>';
        }
        if ($row['type'] === 'owner') {
            $html .= '<label>' . esc_html__('Website', 'h3-mgmt') . ' <input type="url" name="owner_link" value="' . esc_attr($row['owner_link'] ?? '') . '"></label><label>' . esc_html__('Picture or logo', 'h3-mgmt') . ' <input type="file" name="owner_pic" accept="image/jpeg,image/png,image/gif,image/webp"></label>';
        }
        return $html . '<button type="submit">' . esc_html__('Save profile', 'h3-mgmt') . '</button></form><form method="post">' . self::fields($race, 1) . '<input type="hidden" name="team_id" value="' . esc_attr($row['team_id'] ?? '') . '"><input type="hidden" name="type" value="' . esc_attr($row['type'] ?? 'sponsor') . '"><button type="submit" name="new" value="1">' . esc_html__('Start another donation', 'h3-mgmt') . '</button></form>';
    }
    /** @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function overlay(array $rows): array
    {
        global $wpdb;
        if (!($wpdb instanceof \wpdb) || !DonationSchema::ready($db = new Database($wpdb))) {
            return $rows;
        }
        foreach ($rows as &$row) {
            $minor = $db->scalar($db->prepare("SELECT amount_minor FROM %i WHERE sponsor_id=%d AND state='confirmed'", $db->table('cor_mgmt_donations'), (int) ($row['id'] ?? 0)));
            if ($minor !== null) {
                $row['donation'] = (new EuroAmount((int) $minor))->format();
            }
        }
        unset($row);
        return $rows;
    }
    public static function managed(int $id): bool
    {
        global $wpdb;
        $db = new Database($wpdb);
        if (!DonationSchema::ready($db)) {
            return $db->option('cor_mgmt_donation_install') !== null || $db->exists($db->table('cor_mgmt_donations'));
        }
        return $db->scalar($db->prepare('SELECT sponsor_id FROM %i WHERE sponsor_id=%d', $db->table('cor_mgmt_donations'), $id)) !== null;
    }
}
