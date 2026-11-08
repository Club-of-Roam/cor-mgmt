<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Additive outbox; the established template installation plan remains immutable. */
final class MailQueueSchema
{
    public const VERSION = '1.0.0';

    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_mail_queue' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'message_key' => 'char(64) NOT NULL',
                'input_hash' => 'char(64) NOT NULL',
                'race_id' => 'bigint UNSIGNED NOT NULL',
                'occurrence' => 'varchar(128) NOT NULL',
                'action' => 'varchar(80) NOT NULL',
                'recipient' => 'varchar(254) NOT NULL',
                'language' => 'varchar(16) NOT NULL',
                'template_revision' => 'bigint UNSIGNED NOT NULL',
                'context' => 'longblob NOT NULL',
                'subject' => 'text NOT NULL',
                'message' => 'longtext NOT NULL',
                'status' => 'varchar(16) NOT NULL',
                'attempts' => 'int UNSIGNED NOT NULL DEFAULT 0',
                'claim_token' => 'char(64) NOT NULL',
                'claimed_at' => 'bigint UNSIGNED NOT NULL DEFAULT 0',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'updated_at' => 'bigint UNSIGNED NOT NULL',
                'reason' => 'varchar(80) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'UNIQUE_message' => ['message_key'], 'dispatch' => ['race_id','status','id']]],
            'cor_mgmt_mail_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT',
                'message_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL',
                'status' => 'varchar(16) NOT NULL',
                'attempt' => 'int UNSIGNED NOT NULL',
                'reason' => 'varchar(80) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'message_history' => ['message_id','id']]],
        ];
    }

    public static function ready(Database $db): bool
    {
        $manifest = json_decode($db->option('cor_mgmt_mail_queue_install') ?? '', true);
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
