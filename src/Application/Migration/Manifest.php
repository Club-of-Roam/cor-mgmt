<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Migration;

/** Private metadata, counts and digests; never contains copied row values. */
final class Manifest
{
    public const FORMAT = 1;
    /**
     * @param array<string,array{count:int,hash:string,schema:string}> $tables
     * @param list<string> $issues Aggregate issue codes, no personal data.
     * @param list<string> $archiveIds Race IDs whose source phase is 3.
     */
    public function __construct(public readonly array $tables, public readonly array $issues, public readonly array $archiveIds)
    {
    }
    public function json(): string
    {
        return json_encode(['format' => self::FORMAT,'tables' => $this->tables,'issues' => $this->issues,'archives' => $this->archiveIds], JSON_THROW_ON_ERROR);
    }
    public function fingerprint(): string
    {
        return hash('sha256', $this->json());
    }
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($data) || ($data['format'] ?? null) !== self::FORMAT || !is_array($data['tables'] ?? null)
            || !is_array($data['issues'] ?? null) || !is_array($data['archives'] ?? null)
        ) {
            throw new MigrationException('invalid_manifest');
        }
        $tables = [];
        foreach ($data['tables'] as $name => $table) {
            if (
                !is_string($name) || !is_array($table) || !is_int($table['count'] ?? null) || $table['count'] < 0
                || !is_string($table['hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $table['hash'])
                || !is_string($table['schema'] ?? null)
            ) {
                throw new MigrationException('invalid_manifest');
            }
            $tables[$name] = ['count' => $table['count'],'hash' => $table['hash'],'schema' => $table['schema']];
        }
        $issues = [];
        $archives = [];
        foreach ($data['issues'] as $issue) {
            if (!is_string($issue)) {
                throw new MigrationException('invalid_manifest');
            }
            $issues[] = $issue;
        }
        foreach ($data['archives'] as $id) {
            if (!is_string($id) || !preg_match('/^[0-9]{1,20}$/D', $id)) {
                throw new MigrationException('invalid_manifest');
            }
            $archives[] = $id;
        }
        return new self($tables, $issues, $archives);
    }
}
