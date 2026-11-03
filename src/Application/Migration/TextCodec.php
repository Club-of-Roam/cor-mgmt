<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Migration;

/** Decode only a declared charset, with a mandatory byte-identical reverse conversion. */
final class TextCodec
{
    public static function utf8(string $bytes, ?string $collation): string
    {
        $charset = $collation === null ? 'utf8mb4' : explode('_', strtolower($collation), 2)[0];
        $encoding = match ($charset) {
            'utf8','utf8mb3','utf8mb4'=>'UTF-8',
            'latin1'=>'Windows-1252',
            'utf32'=>'UTF-32BE',
            'utf16','ucs2'=>'UTF-16BE',
            'ascii'=>'ASCII',
            default=>throw new MigrationException('unsupported_text_charset'),
        };
        if (!mb_check_encoding($bytes, $encoding)) {
            throw new MigrationException('source_text_conversion_failed');
        }
        $utf8 = mb_convert_encoding($bytes, 'UTF-8', $encoding);
        if (mb_convert_encoding($utf8, $encoding, 'UTF-8') !== $bytes) {
            throw new MigrationException('source_text_conversion_failed');
        }
        return $utf8;
    }
}
