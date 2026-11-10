<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

/** Immutable snapshots have their own install plan, independent of live module schemas. */
final class ArchiveSchema
{
    public const VERSION = '1.0.0';

    /** @return array<string,array{columns:array<string,string>,indexes:array<string,list<string>>}> */
    public static function definitions(): array
    {
        return [
            'cor_mgmt_archive_runs' => ['columns' => [
                'snapshot_id' => 'char(32) NOT NULL', 'race_id' => 'bigint UNSIGNED NOT NULL',
                'mode' => 'varchar(16) NOT NULL', 'state' => 'varchar(16) NOT NULL',
                'plan' => 'longblob NOT NULL', 'kind_index' => 'smallint UNSIGNED NOT NULL',
                'cursor' => "varchar(80) NOT NULL DEFAULT ''", 'copied' => 'bigint UNSIGNED NOT NULL',
                'created_at' => 'bigint UNSIGNED NOT NULL', 'actor_id' => 'bigint UNSIGNED NOT NULL',
                'digest' => "char(64) NOT NULL DEFAULT ''",
            ], 'indexes' => ['PRIMARY' => ['snapshot_id'], 'event' => ['race_id','created_at','snapshot_id']]],
            'cor_mgmt_archive_rows' => ['columns' => [
                'snapshot_id' => 'char(32) NOT NULL', 'kind' => 'varchar(32) NOT NULL',
                'source_key' => 'varchar(80) NOT NULL', 'payload' => 'longblob NOT NULL',
                'row_hash' => 'char(64) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['snapshot_id','kind','source_key']]],
            'cor_mgmt_archive_media' => ['columns' => [
                'snapshot_id' => 'char(32) NOT NULL', 'ref_hash' => 'char(64) NOT NULL',
                'source_ref' => 'text NOT NULL', 'file_name' => 'varchar(100) NOT NULL',
                'file_hash' => 'char(64) NOT NULL', 'state' => 'varchar(16) NOT NULL',
            ], 'indexes' => ['PRIMARY' => ['snapshot_id','ref_hash']]],
        ];
    }

    public static function ready(Database $db): bool
    {
        $state = json_decode($db->option('cor_mgmt_archive_install') ?? '', true);
        if (
            !is_array($state) || ($state['state'] ?? '') !== 'complete'
            || ($state['plan'] ?? '') !== hash('sha256', json_encode(self::definitions(), JSON_THROW_ON_ERROR))
        ) {
            return false;
        }
        foreach (self::definitions() as $table => $_) {
            if (!$db->exists($db->table($table))) {
                return false;
            }
        }
        return true;
    }
}
