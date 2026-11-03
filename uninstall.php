<?php

/** Data is retained on uninstall. Explicit cleanup requires a separate authorized tool. @package CorMgmt */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

foreach (['cor_mgmt_process_mail_queue', 'cor_mgmt_run_scheduled_tasks'] as $cor_mgmt_hook) {
    wp_unschedule_hook($cor_mgmt_hook);
}
