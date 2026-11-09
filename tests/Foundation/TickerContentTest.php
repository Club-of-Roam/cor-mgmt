<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\Ticker\Content;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

final class TickerContentTest extends TestCase
{
    #[DataProvider('invalidCoordinates')]
    public function testRejectsInvalidCoordinates(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Content::coordinates($value);
    }
    public static function invalidCoordinates(): array
    {
        return [['nan,1'], ['91,1'], ['1,181'], ['1,2,3'], ['1e309,0'], ['1,<script>'], [''], ['1;2']];
    }
    public function testNormalizesCoordinatesWithoutLocaleOrPrecisionLoss(): void
    {
        self::assertSame('-90.0000000,180.0000000', Content::coordinates(' -90, 180 '));
        self::assertSame('52.1234567,13.0000000', Content::coordinates('52.1234567,13'));
    }
    #[DataProvider('videos')]
    public function testExtractsOnlySupportedVideoIds(string $value): void
    {
        self::assertSame('abcdefghijk', Content::video($value));
    }
    public static function videos(): array
    {
        return [['abcdefghijk'], ['https://youtu.be/abcdefghijk?t=1'], ['https://www.youtube.com/watch?v=abcdefghijk'], ['https://youtube.com/shorts/abcdefghijk']];
    }
    #[DataProvider('invalidVideos')]
    public function testRejectsArbitraryIframeHostsAndMalformedIds(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Content::video($value);
    }
    public static function invalidVideos(): array
    {
        return [['javascript:alert(1)'], ['https://evil.invalid/abcdefghijk'], ['http://youtu.be/abcdefghijk'], ['https://youtube.com@evil.invalid/watch?v=abcdefghijk'], ['https://youtube.com/watch?v[]=abcdefghijk'], ['synthetic'], ['https://youtu.be:443/abcdefghijk']];
    }
    public function testSnapshotRetainsUnknownBinaryFieldsAndNulls(): void
    {
        $row = ['extra' => "\x00\xff\xfe", 'msg' => null, 'id' => '7'];
        $snapshot = json_decode(Content::snapshot($row), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($row['extra'], base64_decode($snapshot['extra'], true));
        self::assertNull($snapshot['msg']);
        self::assertSame(Content::snapshot($row), Content::snapshot(array_reverse($row, true)));
    }
    public function testDoesNotGuessHistoricalTimeOrPhoneIdentity(): void
    {
        $post = Content::legacy(['type' => '55', 'timestamp' => '123456789', 'send_from' => '00491234', 'msg' => 'Historical &amp; text']);
        self::assertSame(0, $post['created']);
        self::assertSame('00491234', $post['author']);
        self::assertSame('Historical & text', $post['body']);
        self::assertSame(['unknown_timestamp','historical_author','unknown_type'], $post['issues']);
    }
    public function testUnknownAndOverflowingReferencesNeverBecomeDifferentValidIds(): void
    {
        foreach (['-1','01','9.1','1e2','999999999999999999999999999999'] as $value) {
            self::assertSame(0, Content::positiveId($value));
            self::assertSame(0, Content::identity('U_' . $value, 'U'));
        }
        self::assertSame(42, Content::identity('U_42', 'U'));
        self::assertSame(0, Content::identity('T_42', 'U'));
        self::assertSame(0, Content::legacy(['type'=>'100','img'=>'999999999999999999999999'])['parent']);
    }
}
