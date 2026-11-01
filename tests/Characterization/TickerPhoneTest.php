<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/includes/class-h3-mgmt-ticker.php';

/** F-TICK-04: synthetic values only; no claim of full phone-number validation. */
final class TickerPhoneTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function testEquivalentSenderFormats(string $input, string $expected): void
    {
        $ticker = (new ReflectionClass(\H3_MGMT_Ticker::class))->newInstanceWithoutConstructor();
        self::assertSame($expected, $ticker->normalize_phone_number($input));
    }

    public static function phoneNumbers(): array
    {
        return [
            'local DE' => ['0151 00000000', '4915100000000'],
            'plus DE' => ['+49 151 00000000', '4915100000000'],
            'international DE' => ['0049-151/00000000', '4915100000000'],
            'already normalized' => ['4915100000000', '4915100000000'],
            'line breaks' => ["+49\r\n151 00000000", '4915100000000'],
            'non German international' => ['0033 600000000', '33600000000'],
            'empty remains empty' => ['', ''],
        ];
    }
}
