<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress\Schema;

use Cor\Mgmt\Application\Migration\RawRow;
use Cor\Mgmt\Application\Migration\MigrationException;

/** Staging projections only: no recalculation, currency guesses, tokens or live phase changes. */
final class Projection
{
    public function __construct(private readonly Database $db)
    {
    }
    public function copy(string $run, string $suffix, RawRow $row): void
    {
        foreach ($this->expected($run, $suffix, $row) as $record) {
            $this->insert($record['table'], $record['data']);
        }
    }
    /** @return list<array{table:string,data:array<string,string|int|null>}> */
    private function expected(string $run, string $suffix, RawRow $row): array
    {
        $records = [];
        $append = static function (string $table, array $data) use (&$records): void {
            $records[] = ['table' => $table,'data' => $data];
        };
        $common = ['run_id' => $run,'source_row_hash' => $row->hash()];
        if ($suffix === 'h3_mgmt_races') {
            $collation = null;
            foreach ((new Inspector($this->db))->metadata($this->db->table($suffix))['columns'] as $column) {
                if ($column['Field'] === 'setting') {
                    $collation = $column['Collation'];
                }
            }
            $phase = Preflight::phase($row, $collation);
            $append('cor_mgmt_race_states', $common + ['race_id' => $row->id(),'source_phase' => $phase,'target_phase' => $phase === 3 ? 4 : $phase]);
        }
        if ($suffix === 'h3_mgmt_stages') {
            $append('cor_mgmt_stage_keys', $common + ['stage_id' => $row->id(),'race_id' => self::unsigned($row->value('race_id')),
                'route_id' => self::unsigned($row->value('route_id')),'number' => self::unsigned($row->value('number'))]);
        }
        if ($suffix === 'h3_mgmt_teams') {
            for ($slot = 1; $slot <= 6; ++$slot) {
                $race = self::unsigned($row->value('race_id'));
                $route = self::unsigned($row->value('route_id'));
                $matches = ($race === null || $route === null) ? [] : $this->db->rows($this->db->prepare(
                    'SELECT stage_id FROM %i WHERE run_id=%s AND race_id=CAST(%s AS UNSIGNED) AND route_id=CAST(%s AS UNSIGNED) AND number=%d LIMIT 2',
                    $this->db->table('cor_mgmt_stage_keys'),
                    $run,
                    $race,
                    $route,
                    $slot
                ));
                $append('cor_mgmt_stage_results', $common + ['team_id' => $row->id(),'legacy_slot' => $slot,'race_id' => $race,'route_id' => $route,
                    'stage_id' => count($matches) === 1 ? $matches[0]['stage_id'] : null,
                    'rank_value' => self::number($row->value('rank_stage_' . $slot)),'extra_flag' => self::number($row->value('extra_stage_' . $slot)),
                    'extra_amount' => self::number($row->value('amount_extra_stage_' . $slot)),'extra_variable' => self::number($row->value('vary_extra_stage_' . $slot))]);
            }
        }
        if (in_array($suffix, ['h3_mgmt_sponsors','h3_mgmt_teams'], true)) {
            $field = $suffix === 'h3_mgmt_teams' ? 'donation_goal' : 'donation';
            $append('cor_mgmt_legacy_amounts', $common + ['source_table' => $suffix,'source_id' => $row->id(),'raw_value' => $row->value($field),
                'amount_cents' => null,'currency' => null,'unit_status' => 'unresolved']);
        }
        return $records;
    }
    public function verify(string $run, string $suffix, RawRow $row): void
    {
        foreach ($this->expected($run, $suffix, $row) as $record) {
            $definition = Catalogue::shadow()[$record['table']];
            $where = [];
            foreach ($definition['indexes']['PRIMARY'] as $key) {
                $value = $record['data'][$key];
                if ($value === null) {
                    throw new MigrationException('invalid_projection_key');
                }
                $numeric = preg_match('/^(?:bigint|tinyint|smallint|int)\b/i', $definition['columns'][$key]) === 1;
                $where[] = $numeric
                    ? $this->db->prepare('%i=CAST(%s AS UNSIGNED)', $key, $value)
                    : $this->db->prepare('%i=%s', $key, $value);
            }
            $sql = $this->db->prepare('SELECT * FROM %i WHERE ', $this->db->table($record['table'])) . implode(' AND ', $where);
            $actual = $this->db->rows($sql);
            $expected = [];
            foreach ($record['data'] as $key => $value) {
                $expected[$key] = $value === null ? null : (string)$value;
            }
            if (count($actual) !== 1) {
                throw new MigrationException('target_projection_changed');
            }
            $stored = $actual[0];
            ksort($expected);
            ksort($stored);
            if ($expected !== $stored) {
                throw new MigrationException('target_projection_changed');
            }
        }
    }
    private static function unsigned(?string $value): ?string
    {
        if (
            $value === null || !preg_match('/^(?:0|[1-9][0-9]{0,19})$/D', $value)
            || (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0)
        ) {
            return null;
        }
        return $value;
    }
    private static function number(?string $value): ?string
    {
        return $value !== null && preg_match('/^[0-9]{1,9}$/D', $value) ? $value : null;
    }
    /** @param array<string,string|int|null> $data */
    private function insert(string $suffix, array $data): void
    {
        if (!isset(Catalogue::shadow()[$suffix])) {
            throw new MigrationException('invalid_target_table');
        }
        // wpdb insert prepares all values; NULL stays distinct from zero or empty bytes.
        if ($this->db->wpdb->insert($this->db->table($suffix), $data) === false) {
            throw new MigrationException('target_write_failed');
        }
    }
}
