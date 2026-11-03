<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Application\Migration\Manifest;
use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Application\Migration\RawRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class RawRowTest extends TestCase {
    public function testBinaryNullEmptyAndUnknownFieldsRoundTripWithoutDecoding(): void {
        $row=new RawRow(['unknown'=>bin2hex("\0\xFF\xE4"),'empty'=>'','missing'=>null,'id'=>bin2hex('18446744073709551615')]);
        $copy=RawRow::fromPayload($row->payload());
        self::assertSame("\0\xFF\xE4",$copy->value('unknown')); self::assertSame('',$copy->value('empty')); self::assertNull($copy->value('missing'));
        self::assertSame('18446744073709551615',$copy->id()); self::assertSame($row->hash(),$copy->hash());
        self::assertSame($row->hash(),(new RawRow(array_reverse($row->hex,true)))->hash());
    }
    #[DataProvider('invalidIds')]
    public function testUnsupportedIdsFailWithoutNumericCoercion(string $id): void {
        $this->expectException(MigrationException::class); (new RawRow(['id'=>bin2hex($id)]))->id();
    }
    public static function invalidIds(): array { return [['-1'],['1.0'],['1e3'],['01'],['18446744073709551616'],['1 OR 1=1'],['']]; }
    #[DataProvider('invalidPayloads')]
    public function testMalformedPayloadCannotBecomeAnObject(string $payload): void {
        $this->expectException(MigrationException::class); RawRow::fromPayload($payload);
    }
    public static function invalidPayloads(): array { return [['null'],['{"id":12}'],['{"id":"xyz"}'],['{"id":"F"}'],['["00"]']]; }
    public function testManifestRoundTripPreservesFingerprintAndRejectsUnknownFormat(): void {
        $manifest=new Manifest(['h3_mgmt_teams'=>['count'=>1,'hash'=>str_repeat('a',64),'schema'=>'synthetic']],['unclassified_money:1'],['9']);
        self::assertSame($manifest->fingerprint(),Manifest::fromJson($manifest->json())->fingerprint());
        $this->expectException(MigrationException::class); Manifest::fromJson('{"format":999}');
    }
}
