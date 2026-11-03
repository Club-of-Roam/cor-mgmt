<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Fresh compatibility schema and parallel migration storage. Never ALTER the source. */
final class Catalogue
{
    public const STORAGE_VERSION = '3.0.0';
    public const OWNER = 'cor-mgmt:3.0.0';
    public const MIGRATION_VERSION = 'legacy-shadow-v1';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function legacy(): array
    {
        return [
        'h3_mgmt_auto_responses' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'action' => 'varchar(80) NOT NULL DEFAULT \'\'',
                'language' => 'varchar(16) NOT NULL DEFAULT \'en\'',
                'switch' => 'tinyint UNSIGNED DEFAULT 0',
                'subject' => 'text NOT NULL DEFAULT (\'\')',
                'message' => 'longtext NOT NULL DEFAULT (\'\')',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'action_language' => ['action','language'],
            ],
        ],
        'h3_mgmt_invitations' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'team_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'email' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'code' => 'bigint NOT NULL DEFAULT 0',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'team_id' => ['team_id'],
                'email' => ['email'],
            ],
        ],
        'h3_mgmt_races' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'name' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'start' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'end' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'logo_url' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'setting' => 'longtext NULL',
                'information_text' => 'longtext NULL',
                'active' => 'int NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'active' => ['active'],
            ],
        ],
        'h3_mgmt_routes' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'name' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'color_code' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'logo_url' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'max_teams' => 'smallint NOT NULL DEFAULT 0',
                'user_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'race_id' => ['race_id'],
                'user_id' => ['user_id'],
            ],
        ],
        'h3_mgmt_stages' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'route_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'number' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'destination' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'country' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'meeting_point' => 'text NOT NULL DEFAULT (\'\')',
                'country_3166_alpha-2' => 'varchar(255) NOT NULL DEFAULT \'\'',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'race_route_number' => ['race_id','route_id','number'],
            ],
        ],
        'h3_mgmt_sponsors' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'type' => 'varchar(80) NOT NULL DEFAULT \'\'',
                'method' => 'varchar(80) NOT NULL DEFAULT \'\'',
                'donation' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'language' => 'varchar(16) NOT NULL DEFAULT \'en\'',
                'display_name' => 'text NOT NULL DEFAULT (\'\')',
                'first_name' => 'text NOT NULL DEFAULT (\'\')',
                'last_name' => 'text NOT NULL DEFAULT (\'\')',
                'account_id' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'bank_id' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'bank_name' => 'text NOT NULL DEFAULT (\'\')',
                'paid' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'message' => 'text NOT NULL DEFAULT (\'\')',
                'team_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'var_show' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'race_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'email' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'owner_pic' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'owner_link' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'street' => 'text NOT NULL DEFAULT (\'\')',
                'zip_code' => 'text NOT NULL DEFAULT (\'\')',
                'city' => 'text NOT NULL DEFAULT (\'\')',
                'country' => 'text NOT NULL DEFAULT (\'\')',
                'address_additional' => 'text NOT NULL DEFAULT (\'\')',
                'receipt' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'debit_confirmation' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'donation_client_reference' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'donation_token' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'timestamp' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'race_team' => ['race_id','team_id'],
                'token' => ['donation_token'],
            ],
        ],
        'h3_mgmt_teams' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'description' => 'text NOT NULL DEFAULT (\'\')',
                'race_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'team_name' => 'text NOT NULL DEFAULT (\'\')',
                'team_pic' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'complete' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'route_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'team_phone' => 'varchar(255) NOT NULL DEFAULT \'\'',
                'rank_stage_1' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'rank_stage_2' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'rank_stage_3' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'rank_stage_4' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'rank_stage_5' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'rank_stage_6' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_1' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_2' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_3' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_4' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_5' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'extra_stage_6' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'total_points' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'meta_1' => 'text NOT NULL DEFAULT (\'\')',
                'meta_2' => 'text NOT NULL DEFAULT (\'\')',
                'meta_3' => 'text NOT NULL DEFAULT (\'\')',
                'meta_4' => 'text NOT NULL DEFAULT (\'\')',
                'meta_5' => 'text NOT NULL DEFAULT (\'\')',
                'donation_goal' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_1' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_2' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_3' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_4' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_5' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'amount_extra_stage_6' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_1' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_2' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_3' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_4' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_5' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'vary_extra_stage_6' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'race_complete' => ['race_id','complete'],
                'route_id' => ['route_id'],
            ],
        ],
        'h3_mgmt_teammates' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'team_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'user_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'paid' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'waiver' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'language' => 'varchar(16) NOT NULL DEFAULT \'en\'',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'team_id' => ['team_id'],
                'user_id' => ['user_id'],
            ],
        ],
        'h3_mgmt_xchange' => [
            'columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'user_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'message' => 'text NOT NULL DEFAULT (\'\')',
                'time' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'indexes' => [
                'PRIMARY' => ['id'],
                'user_time' => ['user_id','time'],
            ],
        ],
        ];
    }
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function shadow(): array
    {
        return [
        'cor_mgmt_migration_runs' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'migration_version' => 'varchar(64) NOT NULL',
                'state' => 'varchar(16) NOT NULL DEFAULT \'running\'',
                'manifest' => 'longtext NOT NULL',
                'table_index' => 'smallint UNSIGNED NOT NULL DEFAULT 0',
                'cursor' => 'varchar(20) NOT NULL DEFAULT \'\'',
                'copied' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'error_code' => 'varchar(64) NOT NULL DEFAULT \'\'',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'updated_at' => 'bigint UNSIGNED NOT NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['run_id'],
                'state' => ['state'],
            ],
        ],
        'cor_mgmt_migration_rows' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'source_table' => 'varchar(32) NOT NULL',
                'source_id' => 'bigint UNSIGNED NOT NULL',
                'payload' => 'longblob NOT NULL',
                'row_hash' => 'char(64) NOT NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['run_id','source_table','source_id'],
            ],
        ],
        'cor_mgmt_stage_keys' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'stage_id' => 'bigint UNSIGNED NOT NULL',
                'race_id' => 'bigint UNSIGNED NULL',
                'route_id' => 'bigint UNSIGNED NULL',
                'number' => 'bigint UNSIGNED NULL',
                'source_row_hash' => 'char(64) NOT NULL',
            ],
            'indexes' => ['PRIMARY' => ['run_id','stage_id'],'race_route_number' => ['run_id','race_id','route_id','number']],
        ],
        'cor_mgmt_stage_results' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'team_id' => 'bigint UNSIGNED NOT NULL',
                'legacy_slot' => 'tinyint UNSIGNED NOT NULL',
                'race_id' => 'bigint UNSIGNED NULL',
                'route_id' => 'bigint UNSIGNED NULL',
                'stage_id' => 'bigint UNSIGNED NULL',
                'rank_value' => 'bigint NULL',
                'extra_flag' => 'bigint NULL',
                'extra_amount' => 'bigint NULL',
                'extra_variable' => 'bigint NULL',
                'source_row_hash' => 'char(64) NOT NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['run_id','team_id','legacy_slot'],
                'team_stage' => ['run_id','team_id','stage_id'],
            ],
        ],
        'cor_mgmt_race_states' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'source_phase' => 'tinyint UNSIGNED NULL',
                'target_phase' => 'tinyint UNSIGNED NULL',
                'source_row_hash' => 'char(64) NOT NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['run_id','race_id'],
                'target_phase' => ['run_id','target_phase'],
            ],
        ],
        'cor_mgmt_legacy_amounts' => [
            'columns' => [
                'run_id' => 'char(32) NOT NULL',
                'source_table' => 'varchar(32) NOT NULL',
                'source_id' => 'bigint UNSIGNED NOT NULL',
                'raw_value' => 'longblob NULL',
                'amount_cents' => 'bigint NULL',
                'currency' => 'char(3) NULL',
                'unit_status' => 'varchar(32) NOT NULL DEFAULT \'unresolved\'',
                'source_row_hash' => 'char(64) NOT NULL',
            ],
            'indexes' => [
                'PRIMARY' => ['run_id','source_table','source_id'],
            ],
        ],
        ];
    }
}
