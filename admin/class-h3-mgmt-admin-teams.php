<?php
/** Stable menu callbacks for the team application module. */
require_once dirname(__DIR__) . '/includes/class-h3-mgmt-security.php';
if (!class_exists('H3_MGMT_Admin_Teams')) {
    class H3_MGMT_Admin_Teams {
        public function teams_control() { \Cor\Mgmt\WordPress\TeamAdmin::control(); }
        public function participants_control() { \Cor\Mgmt\WordPress\TeamAdmin::control(true); }
    }
}
