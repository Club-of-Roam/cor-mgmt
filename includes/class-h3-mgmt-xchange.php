<?php
require_once __DIR__ . '/class-h3-mgmt-security.php';

/** Legacy signatures delegate to the global, audited notice board. */
class H3_MGMT_XChange {
    private function get_messages(): array {
        global $h3_mgmt_races;
        return \Cor\Mgmt\WordPress\ExchangeBoundary::manager()->messages((int) $h3_mgmt_races->get_active_race());
    }
    private function getOwnMessage(): ?array {
        if (!is_user_logged_in()) { return null; }
        $row = \Cor\Mgmt\WordPress\ExchangeBoundary::manager()->context(get_current_user_id())['row'];
        return $row ?: null;
    }
    public function insertUpdateDeleteMessage(string $newMessage, ?int $id) {
        if (!is_user_logged_in()) { return null; }
        if (!H3_MGMT_Security::post('cor-mgmt-xchange') || !empty($_POST['address'])) { return false; }
        try {
            $manager = \Cor\Mgmt\WordPress\ExchangeBoundary::manager();
            // A foreign ID remains a denied no-op for the public compatibility API.
            try { $manager->context(get_current_user_id(), $id ?? 0); }
            catch (\Cor\Mgmt\Application\Communication\CommunicationException) { return 0; }
            $manager->save(get_current_user_id(), $id ?? 0, sanitize_textarea_field($newMessage), \Cor\Mgmt\WordPress\TeamBoundary::value('revision'));
            return ($id === null && trim($newMessage) === '') ? 0 : 1;
        } catch (\Throwable $error) {
            \Cor\Mgmt\WordPress\Services::get()->logger->error('exchange_legacy_rejected', $error);
            return false;
        }
    }
    public function xchange_control(): string {
        return \Cor\Mgmt\WordPress\ExchangeBoundary::shortcode();
    }
    public function __construct() {
        add_shortcode('h3-mate-xchange', [$this, 'xchange_control']);
    }
}
