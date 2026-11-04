<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Additive module schema; the frozen installation/migration plans remain unchanged. */
final class ParticipationSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_invitation_tokens' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'token_hash' => 'char(64) NOT NULL',
                'team_id' => 'bigint UNSIGNED NOT NULL',
                'email' => 'varchar(255) NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'consumed_at' => 'bigint UNSIGNED NULL',
                'consumed_by' => 'bigint UNSIGNED NULL',
                'revoked_at' => 'bigint UNSIGNED NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'UNIQUE_token_hash' => ['token_hash'], 'team_email' => ['team_id', 'email']]],
            'cor_mgmt_membership_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'team_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'kind' => 'varchar(32) NOT NULL',
                'payload' => 'longblob NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'race_team' => ['race_id', 'team_id']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $state = json_decode($db->option('cor_mgmt_participation_install') ?? '', true);
        if (
            !is_array($state) || ($state['state'] ?? '') !== 'complete'
            || ($state['plan'] ?? '') !== hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))
        ) {
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
