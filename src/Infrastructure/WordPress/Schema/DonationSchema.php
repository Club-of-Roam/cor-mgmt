<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Additive cents/evidence storage; no reinterpretation of legacy financial records. */
final class DonationSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_donations' => ['columns' => [
                'sponsor_id' => 'bigint UNSIGNED NOT NULL',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'team_id' => 'bigint UNSIGNED NOT NULL',
                'session_hash' => 'char(64) NOT NULL',
                'token' => 'varchar(128) NOT NULL',
                'event_id' => 'varchar(32) NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'state' => 'varchar(16) NOT NULL',
                'amount_minor' => 'bigint UNSIGNED NULL',
                'currency' => 'char(3) NOT NULL',
                'opinion_id' => 'varchar(32) NULL',
                'confirmed_at' => 'bigint UNSIGNED NULL',
            ], 'indexes' => ['PRIMARY' => ['sponsor_id'], 'UNIQUE_token' => ['token'], 'UNIQUE_opinion' => ['opinion_id'], 'session_team' => ['race_id', 'team_id', 'session_hash']]],
            'cor_mgmt_donation_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'sponsor_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'kind' => 'varchar(32) NOT NULL',
                'payload' => 'longblob NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'race_sponsor' => ['race_id', 'sponsor_id']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $state = json_decode($db->option('cor_mgmt_donation_install') ?? '', true);
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
