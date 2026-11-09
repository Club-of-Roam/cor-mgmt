<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain\Ticker;

/** Pure validation and byte-preserving historical snapshots. */
final class Content
{
    public static function positiveId(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            return 0;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? 0 : $id;
    }

    public static function identity(string $author, string $prefix): int
    {
        return preg_match('/^' . preg_quote($prefix, '/') . '_([1-9][0-9]*)$/D', $author, $match) ? self::positiveId($match[1]) : 0;
    }
    public static function coordinates(string $value): string
    {
        $parts = explode(',', $value);
        if (count($parts) !== 2 || !is_numeric(trim($parts[0])) || !is_numeric(trim($parts[1]))) {
            throw new \InvalidArgumentException('invalid_coordinates');
        }
        $lat = (float) trim($parts[0]);
        $lon = (float) trim($parts[1]);
        if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) {
            throw new \InvalidArgumentException('invalid_coordinates');
        }
        return sprintf('%.7F,%.7F', $lat, $lon);
    }

    public static function video(string $value): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/D', $value)) {
            return $value;
        }
        $url = parse_url($value);
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port'])) {
            throw new \InvalidArgumentException('invalid_video');
        }
        $host = strtolower($url['host'] ?? '');
        $id = '';
        if ($host === 'youtu.be') {
            $id = ltrim($url['path'] ?? '', '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if (($url['path'] ?? '') === '/watch') {
                parse_str($url['query'] ?? '', $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : '';
            } elseif (preg_match('~^/(?:shorts|embed)/([a-zA-Z0-9_-]{11})$~D', $url['path'] ?? '', $match)) {
                $id = $match[1];
            }
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/D', $id)) {
            throw new \InvalidArgumentException('invalid_video');
        }
        return $id;
    }

    /** @param array<string,string|null> $row */
    public static function snapshot(array $row): string
    {
        ksort($row);
        return json_encode(array_map(static fn (?string $value): ?string => $value === null ? null : base64_encode($value), $row), JSON_THROW_ON_ERROR);
    }

    /** No speculative repairs of old timestamps, phone identities or message types.
     * @param array<string,string|null> $row
     * @return array{kind:string,body:string,media:string,parent:int,created:int,author:string,race:int,issues:list<string>}
     */
    public static function legacy(array $row): array
    {
        $kind = match ((int) ($row['type'] ?? 0)) {
            1 => 'text', 2, 3 => 'image', 4 => 'location', 5 => 'video',
            default => (int) ($row['type'] ?? 0) >= 100 ? 'comment' : 'unknown',
        };
        $stamp = $row['timestamp'] ?? '';
        $created = preg_match('/^[0-9]{10}$/D', $stamp) ? (int) $stamp : 0;
        $author = $row['send_from'] ?? $row['from'] ?? '';
        $media = $row['img_url'] ?? '';
        $issues = [];
        if (!$created) {
            $issues[] = 'unknown_timestamp';
        }
        if (!preg_match('/^[UT]_[1-9][0-9]*$/D', $author)) {
            $issues[] = 'historical_author';
        }
        if ($kind === 'unknown') {
            $issues[] = 'unknown_type';
        }
        if ($kind === 'location' || $kind === 'video') {
            try {
                $kind === 'location' ? self::coordinates($media) : self::video($media);
            } catch (\InvalidArgumentException) {
                $issues[] = 'unknown_media_reference';
            }
        }
        $parent = $kind === 'comment' ? self::positiveId($row['img'] ?? '') : 0;
        if ($kind === 'comment' && !$parent) {
            $issues[] = 'unknown_parent';
        }
        return ['kind' => $kind, 'body' => html_entity_decode(stripslashes($row['msg'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'media' => $media,
            'parent' => $parent, 'created' => $created, 'author' => $author, 'race' => self::positiveId($row['race_id'] ?? ''), 'issues' => $issues];
    }
}
