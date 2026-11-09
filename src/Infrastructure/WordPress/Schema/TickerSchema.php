<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Independent additive module; shared historical tables are never adopted or dropped. */
final class TickerSchema
{
    public const VERSION = '1.0.0';
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_ticker_posts' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'race_id' => 'bigint UNSIGNED NOT NULL',
                'actor_id' => 'bigint UNSIGNED NOT NULL', 'team_id' => 'bigint UNSIGNED NOT NULL',
                'author_ref' => 'varchar(120) NOT NULL', 'kind' => 'varchar(16) NOT NULL', 'body' => 'longtext NOT NULL',
                'media_ref' => 'text NOT NULL', 'attachment_id' => 'bigint UNSIGNED NOT NULL', 'parent_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL', 'visibility' => 'varchar(16) NOT NULL', 'revision' => 'bigint UNSIGNED NOT NULL',
                'source_hash' => 'char(64) NOT NULL', 'source_payload' => 'longblob NOT NULL', 'issues' => 'text NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'feed' => ['race_id','visibility','id'], 'team' => ['race_id','team_id','id'], 'parent' => ['parent_id','visibility','id']]],
            'cor_mgmt_ticker_history' => ['columns' => [
                'id' => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT', 'post_id' => 'bigint UNSIGNED NOT NULL', 'actor_id' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL', 'operation' => 'varchar(32) NOT NULL', 'request_key' => 'varchar(120) NOT NULL', 'payload' => 'longblob NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['id'], 'post' => ['post_id','id'], 'UNIQUE_request' => ['request_key']]],
        ];
    }
    public static function ready(Database $db): bool
    {
        $manifest = json_decode($db->option('cor_mgmt_ticker_install') ?? '', true);
        return is_array($manifest) && ($manifest['state'] ?? '') === 'complete'
            && ($manifest['plan'] ?? '') === hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))
            && $db->exists($db->table('cor_mgmt_ticker_posts')) && $db->exists($db->table('cor_mgmt_ticker_history'));
    }
}
