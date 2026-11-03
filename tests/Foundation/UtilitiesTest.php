<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/class-h3-mgmt-utilities.php';
final class UtilitiesTest extends TestCase {
    public function testNullableComparisonTimestampWorksWithoutDeprecation(): void {
        $utility = new \H3_MGMT_Utilities();
        self::assertSame(1, $utility->date_diff2(1700000000,1700086400)->days);
        self::assertInstanceOf(\DateInterval::class, $utility->date_diff2(1700000000,null));
    }
    public function testNumericDateFormattingPreservesEnglishAndGermanOutput(): void {
        $timezone = date_default_timezone_get();
        $server = $_SERVER;
        date_default_timezone_set('UTC');
        try {
            $en = new \H3_MGMT_Utilities();
            $_SERVER['REQUEST_URI'] = '/en/';
            self::assertSame('2023-11-14',$en->h3_strftime(1700000000));
            $_SERVER['REQUEST_URI'] = '/de/example';
            self::assertSame('14. 11. 2023',$en->h3_strftime(1700000000));
        } finally { date_default_timezone_set($timezone); $_SERVER = $server; }
    }
}
