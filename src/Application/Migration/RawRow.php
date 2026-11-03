<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Migration;

/** Canonical, byte-preserving envelope; HEX strings avoid character conversion. */
final class RawRow
{
    /** @param array<string, string|null> $hex */
    public function __construct(public readonly array $hex)
    {
        foreach ($hex as $value) {
            if ($value !== null && ($value !== '' && (!ctype_xdigit($value) || strlen($value) % 2 !== 0))) {
                throw new MigrationException('invalid_row_envelope');
            }
        }
    }
    public function value(string $field): ?string
    {
        $hex = $this->hex[$field] ?? null;
        if ($hex === null) {
            return null;
        }
        $value = hex2bin($hex);
        if ($value === false) {
            throw new MigrationException('invalid_row_envelope');
        }
        return $value;
    }
    public function id(): string
    {
        $id = $this->value('id');
        if (
            $id === null || !preg_match('/^(?:0|[1-9][0-9]{0,19})$/D', $id)
            || (strlen($id) === 20 && strcmp($id, '18446744073709551615') > 0)
        ) {
            throw new MigrationException('unsupported_source_id');
        }
        return $id;
    }
    public function payload(): string
    {
        $hex = $this->hex;
        ksort($hex, SORT_STRING);
        return json_encode($hex, JSON_THROW_ON_ERROR);
    }
    public function hash(): string
    {
        return hash('sha256', $this->payload());
    }
    public static function fromPayload(string $payload): self
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new MigrationException('invalid_row_envelope');
        }
        $hex = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || ($value !== null && !is_string($value))) {
                throw new MigrationException('invalid_row_envelope');
            }
            $hex[$key] = $value;
        }
        return new self($hex);
    }
}
