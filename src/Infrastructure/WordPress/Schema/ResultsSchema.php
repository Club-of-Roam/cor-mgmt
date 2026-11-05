<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Live results are separate from the immutable legacy-shadow-v1 migration projection. */
final class ResultsSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_scoring_profiles' => ['columns' => [
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'version' => 'varchar(16) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['race_id']]],
            'cor_mgmt_results' => ['columns' => [
                'team_id' => 'bigint UNSIGNED NOT NULL',
                'stage_id' => 'bigint UNSIGNED NOT NULL',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'route_id' => 'bigint UNSIGNED NOT NULL',
                'rank_value' => 'smallint UNSIGNED NOT NULL',
                'extra_one' => 'tinyint UNSIGNED NOT NULL',
                'extra_fixed' => 'tinyint UNSIGNED NOT NULL',
                'extra_variable' => 'smallint UNSIGNED NOT NULL',
                'points' => 'bigint UNSIGNED NOT NULL',
                'rules' => 'longblob NOT NULL',
                'version' => 'varchar(16) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['team_id', 'stage_id'], 'race_route' => ['race_id', 'route_id'], 'stage' => ['stage_id']]],
            'cor_mgmt_result_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'route_id' => 'bigint UNSIGNED NOT NULL',
                'actor_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'payload' => 'longblob NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'race' => ['race_id']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $state = json_decode($db->option('cor_mgmt_results_install') ?? '', true);
        if (!is_array($state) || ($state['state'] ?? '') !== 'complete' || ($state['plan'] ?? '') !== hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))) {
            return false;
        }
        foreach (self::definitions() as $suffix => $_) {
            if (!$db->exists($db->table($suffix))) {
                return false;
            }
        }
        return true;
    }
}
