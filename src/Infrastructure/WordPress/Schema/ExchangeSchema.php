<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Audit only: global notice IDs, timestamps and legacy messages remain authoritative. */
final class ExchangeSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return ['cor_mgmt_exchange_history' => ['columns' => [
            'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'message_id' => 'bigint UNSIGNED NOT NULL',
            'actor_id' => 'bigint UNSIGNED NOT NULL', 'created_at' => 'bigint UNSIGNED NOT NULL',
            'operation' => 'varchar(32) NOT NULL', 'payload' => 'longblob NOT NULL',
        ], 'indexes' => ['PRIMARY' => ['id'], 'message' => ['message_id','id']]]];
    }
    public static function ready(Database $db): bool
    {
        $manifest = json_decode($db->option('cor_mgmt_exchange_install') ?? '', true);
        return is_array($manifest) && ($manifest['state'] ?? '') === 'complete'
            && ($manifest['plan'] ?? '') === hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))
            && $db->exists($db->table('cor_mgmt_exchange_history'));
    }
}
