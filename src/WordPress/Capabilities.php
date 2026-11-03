<?php

/** Existing capabilities, centralized without changing role meanings. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

final class Capabilities
{
    /** @return list<string> */
    public static function all(): array
    {
        return ['h3_mgmt_send_emails','h3_mgmt_edit_autoresponses','h3_mgmt_view_races','h3_mgmt_edit_races',
            'h3_mgmt_edit_own_races','h3_mgmt_delete_races','h3_mgmt_delete_own_races','h3_mgmt_view_teams',
            'h3_mgmt_view_own_teams','h3_mgmt_edit_teams','h3_mgmt_edit_own_teams','h3_mgmt_view_sponsors',
            'h3_mgmt_view_own_sponsors','h3_mgmt_edit_sponsors','h3_mgmt_edit_own_sponsors','h3_mgmt_delete_teams',
            'h3_mgmt_delete_own_teams','h3-mgmt-view-statistics'];
    }
}
