<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Event template module; independent of the legacy and shadow storage plans. */
final class CommunicationSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_response_templates' => ['columns' => [
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'action' => 'varchar(80) NOT NULL',
                'language' => 'varchar(16) NOT NULL',
                'enabled' => 'tinyint UNSIGNED NOT NULL DEFAULT 0',
                'subject' => 'text NOT NULL',
                'message' => 'longtext NOT NULL',
                'revision' => 'bigint UNSIGNED NOT NULL DEFAULT 1',
                'updated_at' => 'bigint UNSIGNED NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['race_id','action','language']]],
            'cor_mgmt_communication_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'actor_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'kind' => 'varchar(32) NOT NULL',
                'payload' => 'longblob NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'race_history' => ['race_id','id']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $manifest = json_decode($db->option('cor_mgmt_communication_install') ?? '', true);
        if (!is_array($manifest) || ($manifest['state'] ?? '') !== 'complete' || ($manifest['plan'] ?? '') !== hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))) {
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
