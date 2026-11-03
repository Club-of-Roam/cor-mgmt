<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\Manifest;
use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Application\Migration\RawRow;
use Cor\Mgmt\Application\Migration\TextCodec;

/** The dry run never creates tables, options, locks or transient reports. */
final class Preflight
{
    public function __construct(private readonly Database $db)
    {
    }
    public function run(): Manifest
    {
        if ($this->db->option('h3_mgmt_db_version') !== '2.8') {
            throw new MigrationException('unsupported_source_version');
        }
        $tables = [];
        $issues = [];
        $archives = [];
        $stages = [];
        $source = new Source($this->db);
        $inspector = new Inspector($this->db);
        foreach (Catalogue::legacy() as $suffix => $definition) {
            $table = $this->db->table($suffix);
            $schema = $inspector->signature($table);
            $metadata = $inspector->metadata($table);
            $source->assertUniqueIds($suffix);
            $settingCollation = null;
            foreach ($metadata['columns'] as $column) {
                if ($column['Field'] === 'setting') {
                    $settingCollation = $column['Collation'];
                }
            }
            $columns = array_column($metadata['columns'], 'Field');
            $missing = count(array_diff(array_keys($definition['columns']), $columns));
            $unknown = count(array_diff($columns, array_keys($definition['columns'])));
            if ($missing) {
                $issues[] = 'missing_legacy_columns:' . $suffix . ':' . $missing;
            }
            if ($unknown) {
                $issues[] = 'unknown_columns_preserved:' . $suffix . ':' . $unknown;
            }
            if (strcasecmp($metadata['table']['ENGINE'] ?? '', 'InnoDB') !== 0) {
                $issues[] = 'nontransactional_source:' . $suffix;
            }
            $cursor = '';
            $count = 0;
            $hash = hash_init('sha256');
            $invalidPhases = 0;
            $unmatched = 0;
            while ($rows = $source->batch($suffix, $cursor, 250)) {
                foreach ($rows as $row) {
                    hash_update($hash, $row->id() . ':' . $row->hash() . "\n");
                    ++$count;
                    $cursor = $row->id();
                    if ($suffix === 'h3_mgmt_races') {
                        $phase = self::phase($row, $settingCollation);
                        if ($phase === null) {
                            ++$invalidPhases;
                        } elseif ($phase === 3) {
                            $archives[] = $row->id();
                        }
                    }
                    if ($suffix === 'h3_mgmt_stages') {
                        $key = ($row->value('race_id') ?? '') . ':' . ($row->value('route_id') ?? '') . ':' . ($row->value('number') ?? '');
                        $stages[$key] = ($stages[$key] ?? 0) + 1;
                    }
                    if ($suffix === 'h3_mgmt_teams') {
                        for ($slot = 1; $slot <= 6; ++$slot) {
                            $key = ($row->value('race_id') ?? '') . ':' . ($row->value('route_id') ?? '') . ':' . $slot;
                            if (($stages[$key] ?? 0) !== 1) {
                                ++$unmatched;
                            }
                        }
                    }
                }
            }
            if ($schema !== $inspector->signature($table)) {
                throw new MigrationException('source_schema_changed');
            }
            $tables[$suffix] = ['count' => $count,'hash' => hash_final($hash),'schema' => $schema];
            if ($invalidPhases) {
                $issues[] = 'unmapped_race_phase:' . $invalidPhases;
            }
            if ($unmatched) {
                $issues[] = 'unresolved_result_slots:' . $unmatched;
            }
            if ($suffix === 'h3_mgmt_sponsors' && $count) {
                $issues[] = 'unclassified_money:' . $count;
            }
            if ($suffix === 'h3_mgmt_teams' && $count) {
                $issues[] = 'unclassified_donation_goals:' . $count;
            }
        }
        foreach (
            [
            ['invitations','team_id','teams','id'],['routes','race_id','races','id'],
            ['stages','race_id','races','id'],['stages','route_id','routes','id'],
            ['teams','race_id','races','id'],['teams','route_id','routes','id'],
            ['teammates','team_id','teams','id'],['sponsors','team_id','teams','id'],['sponsors','race_id','races','id'],
            ] as [$child,$field,$parent,$key]
        ) {
            $columns = $inspector->metadata($this->db->table('h3_mgmt_' . $child))['columns'];
            if (!in_array($field, array_column($columns, 'Field'), true)) {
                continue;
            }
            $count = $this->db->scalar($this->db->prepare(
                'SELECT COUNT(*) FROM %i c LEFT JOIN %i p ON c.%i=p.%i WHERE c.%i IS NOT NULL AND c.%i<>0 AND p.%i IS NULL',
                $this->db->table('h3_mgmt_' . $child),
                $this->db->table('h3_mgmt_' . $parent),
                $field,
                $key,
                $field,
                $field,
                $key
            ));
            if ($count !== '0') {
                $issues[] = 'orphan:' . $child . ':' . $field . ':' . $count;
            }
        }
        if ($this->db->exists($this->db->wpdb->users)) {
            foreach ([['teammates','user_id'],['routes','user_id'],['xchange','user_id']] as [$child,$field]) {
                $columns = $inspector->metadata($this->db->table('h3_mgmt_' . $child))['columns'];
                if (!in_array($field, array_column($columns, 'Field'), true)) {
                    continue;
                }
                $count = $this->db->scalar($this->db->prepare(
                    'SELECT COUNT(*) FROM %i c LEFT JOIN %i p ON c.%i=p.ID WHERE c.%i IS NOT NULL AND c.%i<>0 AND p.ID IS NULL',
                    $this->db->table('h3_mgmt_' . $child),
                    $this->db->wpdb->users,
                    $field,
                    $field,
                    $field
                ));
                if ($count !== '0') {
                    $issues[] = 'orphan:' . $child . ':user_id:' . $count;
                }
            }
        } else {
            $issues[] = 'wordpress_user_references_not_checked';
        }
        return new Manifest($tables, $issues, $archives);
    }
    public static function phase(RawRow $row, ?string $collation = null): ?int
    {
        try {
            $text = TextCodec::utf8($row->value('setting') ?? '', $collation);
        } catch (MigrationException) {
            return null;
        }
        $setting = json_decode($text, true);
        $phase = is_array($setting) ? ($setting['status'] ?? null) : null;
        if (!is_int($phase) && !is_string($phase)) {
            return null;
        }
        return preg_match('/^[0-3]$/D', (string)$phase) ? (int)$phase : null;
    }
}
