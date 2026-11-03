<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Application\Migration\TextCodec;
use Cor\Mgmt\Application\Migration\MigrationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class TextCodecTest extends TestCase {
    #[DataProvider('encodings')]
    public function testDeclaredCharsetsRoundTrip(string $encoding,string $collation): void {
        $utf8=hex2bin('7B22737461747573223A332C226E616D65223A22C3A4227D');
        $source=mb_convert_encoding($utf8,$encoding,'UTF-8');
        self::assertSame($utf8,TextCodec::utf8($source,$collation));
    }
    public static function encodings(): array {
        return [['UTF-8','utf8mb4_unicode_ci'],['UTF-8','utf8mb3_general_ci'],['Windows-1252','latin1_swedish_ci'],['UTF-32BE','utf32_general_ci'],['UTF-16BE','utf16_general_ci']];
    }
    public function testInvalidEncodingAndUnsupportedCharsetAreNeverGuessed(): void {
        try { TextCodec::utf8(hex2bin('FF'),'utf8mb4_unicode_ci'); self::fail('Invalid bytes decoded'); }
        catch (MigrationException $error) { self::assertSame('source_text_conversion_failed',$error->getMessage()); }
        $this->expectExceptionMessage('unsupported_text_charset'); TextCodec::utf8('synthetic','unknown_general_ci');
    }
}
