<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Independent manual-campaign envelopes; existing queue plans remain immutable. */
final class BroadcastSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_broadcasts' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'actor_id' => 'bigint UNSIGNED NOT NULL',
                'request_key' => 'char(64) NOT NULL', 'fingerprint' => 'char(64) NOT NULL', 'payload' => 'longblob NOT NULL',
                'enabled' => 'tinyint UNSIGNED NOT NULL DEFAULT 0', 'created_at' => 'bigint UNSIGNED NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'UNIQUE_request' => ['request_key'], 'actor' => ['actor_id','id']]],
            'cor_mgmt_broadcast_recipients' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'broadcast_id' => 'bigint UNSIGNED NOT NULL',
                'recipient_key' => 'char(64) NOT NULL', 'user_id' => 'bigint UNSIGNED NOT NULL',
                'recipient' => 'varchar(254) NOT NULL', 'language' => 'varchar(16) NOT NULL',
                'subject' => 'text NOT NULL', 'message' => 'longtext NOT NULL', 'headers' => 'text NOT NULL',
                'queue_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
            ], 'indexes' => ['PRIMARY' => ['id'], 'UNIQUE_recipient' => ['broadcast_id','recipient_key'], 'pending' => ['broadcast_id','queue_id','id']]],
            'cor_mgmt_mail_envelopes' => ['columns' => [
                'message_id' => 'bigint UNSIGNED NOT NULL', 'broadcast_id' => 'bigint UNSIGNED NOT NULL', 'headers' => 'text NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['message_id'], 'broadcast' => ['broadcast_id','message_id']]],
            'cor_mgmt_broadcast_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'broadcast_id' => 'bigint UNSIGNED NOT NULL',
                'actor_id' => 'bigint UNSIGNED NOT NULL', 'created_at' => 'bigint UNSIGNED NOT NULL', 'operation' => 'varchar(32) NOT NULL',
                'message_id' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
            ], 'indexes' => ['PRIMARY' => ['id'], 'broadcast' => ['broadcast_id','id']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $manifest = json_decode($db->option('cor_mgmt_broadcast_install') ?? '', true);
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
