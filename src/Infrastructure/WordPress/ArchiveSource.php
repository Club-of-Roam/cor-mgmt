<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Migration\TextCodec;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Bounded original-byte capture. Account credentials are deliberately outside the archive. */
final class ArchiveSource
{
    public function __construct(private readonly Database $db, private readonly int $race)
    {
    }

    /** @return array<string,array{table:string,key:string,where:string,fields:list<string>}> */
    public function definitions(): array
    {
        $teams = $this->db->prepare('SELECT id FROM %i WHERE race_id=%d', $this->db->table('h3_mgmt_teams'), $this->race);
        $members = $this->db->prepare('SELECT user_id FROM %i WHERE team_id IN (', $this->db->table('h3_mgmt_teammates')) . $teams . ')';
        $users = $members . ' UNION ' . $this->db->prepare('SELECT user_id FROM %i WHERE race_id=%d', $this->db->table('h3_mgmt_routes'), $this->race);
        if ($this->db->exists($this->db->table('cor_mgmt_ticker_posts'))) {
            $users .= ' UNION ' . $this->db->prepare('SELECT actor_id FROM %i WHERE race_id=%d', $this->db->table('cor_mgmt_ticker_posts'), $this->race);
        }
        // Materialize the UNION once; a correlated IN(UNION...) otherwise scans it per account/meta row.
        $users = 'SELECT user_id FROM (' . $users . ') cor_archive_people';
        $definitions = [];
        foreach (['race','route','stage','team','teammate','sponsor','invitation'] as $kind) {
            $where = match ($kind) {
                'race' => $this->db->prepare('s.id=%d', $this->race),
                'teammate','invitation' => 's.team_id IN (' . $teams . ')',
                'sponsor' => $this->db->prepare('(s.race_id=%d OR s.team_id IN (', $this->race) . $teams . '))',
                default => $this->db->prepare('s.race_id=%d', $this->race),
            };
            $definitions[$kind] = ['table' => $this->db->table('h3_mgmt_' . $kind . 's'), 'key' => "LPAD(CAST(s.id AS CHAR),20,'0')", 'where' => $where, 'fields' => []];
        }
        $definitions['user'] = ['table' => $this->db->wpdb->users, 'key' => "LPAD(CAST(s.ID AS CHAR),20,'0')", 'where' => 's.ID IN (' . $users . ')', 'fields' => ['ID','display_name','user_nicename']];
        $definitions['usermeta'] = ['table' => $this->db->wpdb->usermeta, 'key' => "LPAD(CAST(s.umeta_id AS CHAR),20,'0')",
            'where' => "s.meta_key IN ('first_name','last_name','city','birthday','cor_mgmt_avatar','simple_local_avatar') AND s.user_id IN (" . $users . ')', 'fields' => ['umeta_id','user_id','meta_key','meta_value']];
        foreach (['cor_mgmt_scoring_profiles','cor_mgmt_results','cor_mgmt_result_history','cor_mgmt_ticker_posts','cor_mgmt_ticker_history','cor_mgmt_donations','cor_mgmt_donation_history'] as $suffix) {
            if (!$this->db->exists($this->db->table($suffix))) {
                continue;
            }
            $where = $suffix === 'cor_mgmt_ticker_history'
                ? 's.post_id IN (' . $this->db->prepare('SELECT id FROM %i WHERE race_id=%d', $this->db->table('cor_mgmt_ticker_posts'), $this->race) . ')'
                : $this->db->prepare('s.race_id=%d', $this->race);
            $key = match ($suffix) {
                'cor_mgmt_scoring_profiles' => "LPAD(CAST(s.race_id AS CHAR),20,'0')",
                'cor_mgmt_donations' => "LPAD(CAST(s.sponsor_id AS CHAR),20,'0')",
                'cor_mgmt_results' => "CONCAT(LPAD(CAST(s.team_id AS CHAR),20,'0'),':',LPAD(CAST(s.stage_id AS CHAR),20,'0'))",
                default => "LPAD(CAST(s.id AS CHAR),20,'0')",
            };
            $definitions[$suffix] = ['table' => $this->db->table($suffix), 'key' => $key, 'where' => $where, 'fields' => []];
        }
        return $definitions;
    }

    /** @return array<string,array{count:int,shape:string}> */
    public function plan(): array
    {
        $plan = [];
        foreach ($this->definitions() as $kind => $definition) {
            $count = (int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i s WHERE ', $definition['table']) . $definition['where']);
            $unique = (int) $this->db->scalar('SELECT COUNT(DISTINCT ' . $definition['key'] . ') ' . $this->db->prepare('FROM %i s WHERE ', $definition['table']) . $definition['where']);
            if ($count !== $unique) {
                throw new \RuntimeException('archive_source_key_not_unique');
            }
            $plan[$kind] = ['count' => $count, 'shape' => (new Inspector($this->db))->signature($definition['table'])];
        }
        return $plan;
    }

    /** @return list<array{key:string,payload:string,hash:string,view:array<string,string|null>}> */
    public function batch(string $kind, string $cursor = '', int $limit = 100): array
    {
        if ($limit < 1 || $limit > 100 || !isset($this->definitions()[$kind]) || !preg_match('/^[0-9:]{0,41}$/D', $cursor)) {
            throw new \RuntimeException('invalid_archive_batch');
        }
        $definition = $this->definitions()[$kind];
        $columns = (new Inspector($this->db))->metadata($definition['table'])['columns'];
        $fields = [];
        $collations = [];
        foreach ($columns as $column) {
            $field = $column['Field'] ?? '';
            if ($definition['fields'] && !in_array($field, $definition['fields'], true)) {
                continue;
            }
            if ($field === '_cor_key') {
                throw new \RuntimeException('archive_source_field_collision');
            }
            $fields[] = $this->db->prepare('HEX(CAST(s.%i AS BINARY)) AS %i', $field, $field);
            $collations[$field] = $column['Collation'];
        }
        $sql = 'SELECT ' . $definition['key'] . ' AS _cor_key,' . implode(',', $fields)
            . $this->db->prepare(' FROM %i s WHERE ', $definition['table']) . $definition['where']
            . ' AND ' . $definition['key'] . $this->db->prepare('>%s ORDER BY _cor_key LIMIT %d', $cursor, $limit);
        $out = [];
        foreach ($this->db->rows($sql) as $row) {
            $key = $row['_cor_key'] ?? '';
            unset($row['_cor_key']);
            ksort($row);
            $view = [];
            foreach ($row as $field => $hex) {
                $bytes = $hex === null ? null : hex2bin($hex);
                if ($bytes === false) {
                    throw new \RuntimeException('invalid_archive_source');
                }
                $view[$field] = $bytes === null ? null : ($collations[$field] === null
                    ? (mb_check_encoding($bytes, 'UTF-8') ? $bytes : null)
                    : TextCodec::utf8($bytes, $collations[$field]));
            }
            $payload = json_encode(['raw' => $row, 'view' => $view], JSON_THROW_ON_ERROR);
            $out[] = ['key' => $key, 'payload' => $payload, 'hash' => hash('sha256', $payload), 'view' => $view];
        }
        return $out;
    }
}
