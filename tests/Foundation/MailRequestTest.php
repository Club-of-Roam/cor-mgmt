<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\MailRequest;
use Cor\Mgmt\Application\Communication\CommunicationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MailRequestTest extends TestCase
{
    public function testStableCanonicalIdentityAndOrderedValues():void
    {
        $a=new MailRequest(1,'membership:71','team-creation',' MEMBER@example.invalid ','de-DE',['name'=>'Name','team_name'=>'Team']);
        $b=new MailRequest(1,'membership:71','team-creation','member@example.invalid','de_de',['team_name'=>'Team','name'=>'Name']);
        self::assertSame($a->key(),$b->key());self::assertSame($a->fingerprint(),$b->fingerprint());
        self::assertNotSame($a->key(),(new MailRequest(2,'membership:71','team-creation','member@example.invalid','de_de',[]))->key());
        self::assertNotSame($a->fingerprint(),(new MailRequest(1,'membership:71','team-creation','member@example.invalid','de_de',['name'=>'Changed']))->fingerprint());
    }
    public static function invalid():array
    {
        return [[0,'event:1','user@example.invalid',[]],[1,'','user@example.invalid',[]],[1,"event\n1",'user@example.invalid',[]],[1,'event:1',"user@example.invalid\r\nBcc:other@example.invalid",[]],[1,'event:1','not-an-address',[]],[1,'event:1','user@example.invalid',['unknown'=>'value']],[1,'event:1','user@example.invalid',['name'=>12]],[1,'event:1','user@example.invalid',['name'=>"nul\0value"]],[1,'event:1','user@example.invalid',['name'=>str_repeat('x',200001)]]];
    }
    #[DataProvider('invalid')]
    public function testInvalidRequestsAreRejected(int $race,string $occurrence,string $recipient,array $values):void
    {
        $this->expectException(CommunicationException::class);new MailRequest($race,$occurrence,'team-creation',$recipient,'en',$values);
    }
}
