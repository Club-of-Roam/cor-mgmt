<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Domain\Ticker\Content;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\TickerSchema;

/** Explicit bounded copy and second-pass verification. Never invoked by feed readers. */
final class TickerImport
{
    /** @var list<array<string,string|null>> */
    private array $columns = [];
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array{state:string,cursor:int,count:int,max:int,shape:string,verified:int,issues:int} */
    public function status(): array
    {
        $row = json_decode($this->db->option('cor_mgmt_ticker_transition') ?? '', true);
        return [
            'state' => is_array($row) && is_string($row['state'] ?? null) ? $row['state'] : 'pending',
            'cursor' => is_array($row) ? (int) ($row['cursor'] ?? 0) : 0,
            'count' => is_array($row) ? (int) ($row['count'] ?? 0) : 0,
            'max' => is_array($row) ? (int) ($row['max'] ?? 0) : 0,
            'shape' => is_array($row) && is_string($row['shape'] ?? null) ? $row['shape'] : '',
            'verified' => is_array($row) ? (int) ($row['verified'] ?? 0) : 0,
            'issues' => is_array($row) ? (int) ($row['issues'] ?? 0) : 0,
        ];
    }

    /** This is an explicit operator precondition, not a claim that advisory locks stop third-party SQL. */
    public function start(bool $writersStopped): void
    {
        if (!$writersStopped) {
            throw new \RuntimeException('ticker_writers_must_be_stopped');
        }
        $this->db->locked(function (): void {
            $this->verifyTarget();
            if ($this->status()['state'] !== 'pending') {
                return;
            }
            if ($this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $this->db->table('cor_mgmt_ticker_posts'))) !== '0') {
                throw new \RuntimeException('ticker_target_not_empty');
            }
            $source = $this->source();
            $this->save(['state' => $source['count'] === 0 ? 'native' : 'copy', 'cursor' => 0, 'count' => $source['count'], 'max' => $source['max'], 'shape' => $source['shape'], 'verified' => 0, 'issues' => 0]);
        });
    }

    public function step(): void
    {
        $this->db->locked(function (): void {
            $this->verifyTarget();
            $state = $this->status();
            if ($state['state'] === 'native') {
                return;
            }
            if (!in_array($state['state'], ['copy','verify'], true)) {
                throw new \RuntimeException('ticker_import_not_started');
            }
            $source = $this->source();
            if ($source['count'] !== $state['count'] || $source['max'] !== $state['max'] || $source['shape'] !== $state['shape']) {
                throw new \RuntimeException('ticker_source_changed');
            }
            $rows = $this->sourceRows($state['cursor']);
            $nested = $this->db->scalar('SELECT @@in_transaction') === '1';
            $this->db->execute($nested ? 'SAVEPOINT cor_ticker_import' : 'START TRANSACTION');
            try {
                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= $state['cursor'] || $id < 1 || (string) $id !== $row['id']) {
                        throw new \RuntimeException('ticker_invalid_source_id');
                    }
                    $payload = Content::snapshot($row);
                    $hash = hash('sha256', $payload);
                    $stored = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->db->table('cor_mgmt_ticker_posts'), $id))[0] ?? null;
                    if ($stored !== null && ($stored['source_hash'] !== $hash || $stored['source_payload'] !== $payload)) {
                        throw new \RuntimeException('ticker_source_changed');
                    }
                    if ($state['state'] === 'verify') {
                        if ($stored === null) {
                            throw new \RuntimeException('ticker_import_missing_row');
                        }
                        $projection = Content::legacy($this->displayRow($row));
                        foreach (['race_id' => (string) $projection['race'], 'actor_id' => (string) Content::identity($projection['author'], 'U'), 'team_id' => (string) Content::identity($projection['author'], 'T'), 'author_ref' => mb_substr($projection['author'], 0, 120), 'kind' => $projection['kind'], 'body' => $projection['body'], 'media_ref' => $projection['media'], 'parent_id' => (string) $projection['parent'], 'created_at' => (string) $projection['created'], 'visibility' => 'visible', 'revision' => '1', 'attachment_id' => '0'] as $field => $expected) {
                            if ($stored[$field] !== $expected) {
                                throw new \RuntimeException('ticker_target_content_mismatch');
                            }
                        }
                        $issues = $projection['issues'];
                        if ($projection['race'] < 1 || !$this->db->scalar($this->db->prepare('SELECT id FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), $projection['race']))) {
                            $issues[] = 'orphan_event';
                        }
                        if ($projection['parent'] > 0 && $this->db->scalar($this->db->prepare("SELECT COUNT(*) FROM %i WHERE id=%d AND race_id=%d AND kind<>'comment'", $this->db->table('cor_mgmt_ticker_posts'), $projection['parent'], $projection['race'])) !== '1') {
                            $issues[] = 'orphan_parent';
                        }
                        if ((int) $stored['actor_id'] > 0 && !$this->db->scalar($this->db->prepare('SELECT ID FROM %i WHERE ID=%d', $this->db->wpdb->users, (int) $stored['actor_id']))) {
                            $issues[] = 'former_participant';
                        }
                        if ((int) $stored['team_id'] > 0 && !$this->db->scalar($this->db->prepare('SELECT id FROM %i WHERE id=%d AND race_id=%d', $this->db->table('h3_mgmt_teams'), (int) $stored['team_id'], $projection['race']))) {
                            $issues[] = 'orphan_team';
                        }
                        if ($projection['kind'] === 'image') {
                            $reference = $projection['media'];
                            $uploads = wp_upload_dir();
                            if (($row['type'] ?? '') === '2' && !str_contains($reference, '/') && !str_contains($reference, '\\')) {
                                $reference = trailingslashit($uploads['basedir']) . 'mms/' . $reference;
                            }
                            if (preg_match('~^https?://~i', $reference)) {
                                $reference = (string) parse_url($reference, PHP_URL_PATH);
                            }
                            if (\H3_MGMT_Media::safe_image_path(rawurldecode($reference)) === '') {
                                $issues[] = 'unresolved_image';
                            }
                        }
                        $state['issues'] += count($issues) - count($projection['issues']);
                        $this->db->execute($this->db->prepare('UPDATE %i SET issues=%s WHERE id=%d', $this->db->table('cor_mgmt_ticker_posts'), json_encode($issues, JSON_THROW_ON_ERROR), $id));
                        $state['verified']++;
                    } elseif ($stored === null) {
                        $post = Content::legacy($this->displayRow($row));
                        // Unknown identities and relationships are preserved, never matched against today's phone book.
                        $actor = Content::identity($post['author'], 'U');
                        $team = Content::identity($post['author'], 'T');
                        $this->db->execute($this->db->prepare("INSERT INTO %i (id,race_id,actor_id,team_id,author_ref,kind,body,media_ref,attachment_id,parent_id,created_at,visibility,revision,source_hash,source_payload,issues) VALUES (%d,%d,%d,%d,%s,%s,%s,%s,0,%d,%d,'visible',1,%s,%s,%s)", $this->db->table('cor_mgmt_ticker_posts'), $id, $post['race'], $actor, $team, mb_substr($post['author'], 0, 120), $post['kind'], mb_convert_encoding($post['body'], 'UTF-8', 'UTF-8'), mb_convert_encoding($post['media'], 'UTF-8', 'UTF-8'), $post['parent'], $post['created'], $hash, $payload, json_encode($post['issues'], JSON_THROW_ON_ERROR)));
                        $state['issues'] += count($post['issues']);
                    }
                    $state['cursor'] = $id;
                }
                if (count($rows) < 100) {
                    if ($state['state'] === 'copy') {
                        $state['state'] = 'verify';
                        $state['cursor'] = 0;
                    } else {
                        $count = (int) $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i', $this->db->table('cor_mgmt_ticker_posts')));
                        if ($state['verified'] !== $state['count'] || $count !== $state['count']) {
                            throw new \RuntimeException('ticker_import_count_mismatch');
                        }
                        $state['state'] = 'native';
                    }
                }
                $this->save($state);
                $this->db->assertLock();
                $this->db->execute($nested ? 'RELEASE SAVEPOINT cor_ticker_import' : 'COMMIT');
            } catch (\Throwable $error) {
                $this->db->execute($nested ? 'ROLLBACK TO SAVEPOINT cor_ticker_import' : 'ROLLBACK');
                throw $error;
            }
        });
    }

    private function verifyTarget(): void
    {
        if (!TickerSchema::ready($this->db)) {
            throw new \RuntimeException('ticker_setup_required');
        }
        if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->db->wpdb->options)) !== 'InnoDB') {
            throw new \RuntimeException('transactional_ticker_journal_required');
        }
        foreach (TickerSchema::definitions() as $suffix => $definition) {
            (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
        }
    }

    /** @return array{count:int,max:int,shape:string} */
    private function source(): array
    {
        $table = $this->db->table('sms_ticker');
        if (!$this->db->exists($table)) {
            return ['count' => 0, 'max' => 0, 'shape' => 'absent'];
        }
        $columns = $this->db->rows($this->db->prepare('SHOW FULL COLUMNS FROM %i', $table));
        $this->columns = $columns;
        $names = array_column($columns, 'Field');
        foreach (['id','msg','type','timestamp'] as $required) {
            if (!in_array($required, $names, true)) {
                throw new \RuntimeException('ticker_unknown_source_schema');
            }
        }
        if (!in_array('send_from', $names, true) && !in_array('from', $names, true)) {
            throw new \RuntimeException('ticker_unknown_source_schema');
        }
        $ids = $this->db->rows($this->db->prepare('SELECT COUNT(*) AS total,COUNT(DISTINCT id) AS ids,MIN(id) AS smallest,MAX(id) AS largest FROM %i', $table))[0];
        if ($ids['total'] !== $ids['ids'] || ((int) $ids['total'] > 0 && (int) $ids['smallest'] < 1)) {
            throw new \RuntimeException('ticker_invalid_source_id');
        }
        return ['count' => (int) $ids['total'], 'max' => (int) $ids['largest'], 'shape' => hash('sha256', json_encode($columns, JSON_THROW_ON_ERROR))];
    }

    /** Read stored bytes without client charset conversion; retain extra columns too.
     * @return list<array<string,string|null>>
     */
    private function sourceRows(int $cursor): array
    {
        $table = $this->db->table('sms_ticker');
        $columns = $this->db->rows($this->db->prepare('SHOW COLUMNS FROM %i', $table));
        $fields = [];
        foreach ($columns as $column) {
            $name = (string) $column['Field'];
            $fields[] = $this->db->prepare('HEX(CAST(%i AS BINARY)) AS %i', $name, $name);
        }
        $sql = 'SELECT ' . implode(',', $fields) . $this->db->prepare(' FROM %i s WHERE s.id>%d ORDER BY s.id LIMIT 100', $table, $cursor);
        return array_map(static fn (array $row): array => array_map(static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }
            $bytes = hex2bin($value);
            return $bytes === false ? throw new \RuntimeException('invalid_hex_source') : $bytes;
        }, $row), $this->db->rows($sql));
    }

    /** Decode only the display projection; the original source payload remains byte-identical.
     * @param array<string,string|null> $row
     * @return array<string,string|null>
     */
    private function displayRow(array $row): array
    {
        foreach ($this->columns as $column) {
            $field = (string) $column['Field'];
            $collation = $column['Collation'] ?? '';
            if (!in_array($field, ['msg','send_from','from','img_url','img','timestamp','race_id','type'], true)) {
                continue;
            }
            if (($field === 'img' && (int) ($row['type'] ?? 0) < 100) || ($collation === '' && !in_array($field, ['msg','send_from','from','img_url'], true))) {
                continue;
            }
            $encoding = match (strtok($collation, '_')) {
                'utf32' => 'UTF-32BE', 'utf16' => 'UTF-16BE', 'utf16le' => 'UTF-16LE', 'ucs2' => 'UCS-2BE', 'latin1' => 'Windows-1252',
                'utf8', 'utf8mb3', 'utf8mb4', 'ascii', false => 'UTF-8',
                default => throw new \RuntimeException('ticker_source_charset_review'),
            };
            if (isset($row[$field]) && !mb_check_encoding($row[$field], $encoding)) {
                throw new \RuntimeException('ticker_source_charset_review');
            }
            if (isset($row[$field]) && $collation !== '') {
                $row[$field] = mb_convert_encoding($row[$field], 'UTF-8', $encoding);
            }
        }
        return $row;
    }

    /** @param array{state:string,cursor:int,count:int,max:int,shape:string,verified:int,issues:int} $state */
    private function save(array $state): void
    {
        $this->db->setOption('cor_mgmt_ticker_transition', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
