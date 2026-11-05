<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\EuroAmount;
use Cor\Mgmt\Domain\BetterplaceEvent;
use Cor\Mgmt\Application\Contracts\HttpClient;
use Cor\Mgmt\Application\HttpResponse;
use Cor\Mgmt\Application\TransportException;
use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Infrastructure\Betterplace\PublicOpinions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__,2).'/vendor/autoload.php';

final class DonationCoreTest extends TestCase
{
    public static function amounts(): array{return [['0',0],['0.01',1],['12,34',1234],['12.3',1230],['100',10000],[' 1.20 ',120]];}
    #[DataProvider('amounts')]
    public function testDecimalAmountsUseExactCents(string $raw,int $cents): void
    {
        self::assertSame($cents,EuroAmount::decimal($raw)->cents);self::assertSame($cents,EuroAmount::decimal((new EuroAmount($cents))->format())->cents);
    }
    public static function invalidAmounts(): array{return [['-1'],['1.001'],['1e3'],['1,234.00'],['01'],[''],['NaN'],['+1'],['9999999999999999999999999']];}
    #[DataProvider('invalidAmounts')]
    public function testAmbiguousOrOverflowingAmountsAreRejected(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);EuroAmount::decimal($raw);
    }
    public function testAdditionDoesNotUseFloatingPoint(): void
    {
        self::assertSame('0.30',EuroAmount::decimal('0.1')->add(EuroAmount::decimal('0.2'))->format());
        $this->expectException(\InvalidArgumentException::class);(new EuroAmount(PHP_INT_MAX))->add(new EuroAmount(1));
    }
    public static function urls(): array{return [['https://betterplace.org/de/fundraising-events/123'],['https://www.betterplace.org/en/fundraising-events/123-example?client_id=old']];}
    #[DataProvider('urls')]
    public function testValidatedEventNeverChangesTheProviderHost(string $url): void
    {
        $event=new BetterplaceEvent($url);self::assertSame('123',$event->id);self::assertSame('https://secure.betterplace.org/en/donate/platform/fundraising-events/123',$event->form('invalid'));
    }
    public static function invalidUrls(): array{return [['http://betterplace.org/de/fundraising-events/1'],['https://betterplace.org.evil.invalid/de/fundraising-events/1'],['https://user@betterplace.org/de/fundraising-events/1'],['https://betterplace.org:444/de/fundraising-events/1'],['https://betterplace.org/de/projects/1'],['https://betterplace.org/de/fundraising-events/0']];}
    #[DataProvider('invalidUrls')]
    public function testInvalidDestinationsAreNotEmbedded(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);new BetterplaceEvent($url);
    }
    private function token():string{return 't42-'.str_repeat('a',32);}
    private function opinion(array $overrides=[]):array{return $overrides+['id'=>123,'message'=>'Before '.$this->token().' after','confirmed_at'=>'2026-11-05T12:01:00Z','donated_amount_in_cents'=>1234];}
    private function lookup(array $responses,array &$requests=[]):PublicOpinions
    {
        return new PublicOpinions(new class($responses,$requests) implements HttpClient{
            private array $requests;
            public function __construct(private array $responses,array &$requests){$this->requests=&$requests;}
            public function get(string $url,array $options=[]):HttpResponse{$this->requests[]=$url;$item=array_shift($this->responses);if($item instanceof \Throwable){throw $item;}return $item;}
        });
    }
    private function response(array $rows,int $page=1,int $pages=1):HttpResponse{return new HttpResponse(200,json_encode(['data'=>$rows,'current_page'=>$page,'total_pages'=>$pages],JSON_THROW_ON_ERROR));}
    private function created():int{return strtotime('2026-11-05T12:00:00Z');}
    public function testPaginatedExactTokenAndCentsAreObserved():void
    {
        $requests=[];$provider=$this->lookup([$this->response([$this->opinion(['id'=>1,'message'=>'no match'])],1,2),$this->response([$this->opinion()],2,2)],$requests);
        $proof=$provider->find('12',$this->token(),$this->created());self::assertSame('123',$proof->opinion);self::assertSame(1234,$proof->amount->cents);self::assertCount(2,$requests);self::assertStringContainsString('page=2',$requests[1]);
    }
    public static function failures():array{return [['hidden','amount_unavailable'],['malformed','invalid_provider_response'],['network','provider_unavailable'],['unavailable','provider_unavailable'],['ambiguous','ambiguous_donation'],['old','donation_not_found'],['substring','donation_not_found'],['zero','amount_unavailable'],['float','amount_unavailable']];}
    #[DataProvider('failures')]
    public function testUnsafeProviderObservationsNeverBecomeProof(string $mode,string $expected):void
    {
        $rows=[$this->opinion()];
        if($mode==='hidden'){$rows[0]['donated_amount_in_cents']=null;}if($mode==='zero'){$rows[0]['donated_amount_in_cents']=0;}if($mode==='float'){$rows[0]['donated_amount_in_cents']='1234';}
        if($mode==='old'){$rows[0]['confirmed_at']='2026-11-04T12:00:00Z';}if($mode==='substring'){$rows[0]['message']=$this->token().'f';}
        if($mode==='ambiguous'){$rows[]=$this->opinion(['id'=>124]);}
        $response=match($mode){'malformed'=>new HttpResponse(200,'not JSON'),'unavailable'=>new HttpResponse(503,''),'network'=>new TransportException('synthetic'),default=>$this->response($rows)};
        try{$this->lookup([$response])->find('12',$this->token(),$this->created());self::fail('Expected '.$expected);}catch(DonationException $error){self::assertSame($expected,$error->reason);}
    }
    public function testBoundedSearchDoesNotConfirmAPartialResult():void
    {
        $responses=[];foreach(range(1,5) as $page){$responses[]=$this->response([$this->opinion(['id'=>$page,'message'=>'other'])],$page,6);}
        $this->expectExceptionMessage('search_incomplete');$this->lookup($responses)->find('12',$this->token(),$this->created());
    }
    public function testUnorderedOrOversizedPagesCannotConfirm(): void
    {
        foreach ([[$this->opinion(['confirmed_at'=>'2026-11-05T11:59:00Z']),$this->opinion()],array_fill(0,101,$this->opinion())] as $rows) {
            try {$this->lookup([$this->response($rows)])->find('12',$this->token(),$this->created());self::fail('Expected invalid response');}
            catch(DonationException $error){self::assertSame('invalid_provider_response',$error->reason);}
        }
    }

    public function testDisplayArithmeticNeverRoundsCentsThroughFloats():void
    {
        self::assertSame(12,\Cor\Mgmt\Domain\DonationAmounts::whole('12.99'));self::assertSame(0,\Cor\Mgmt\Domain\DonationAmounts::whole('0.99'));self::assertSame('0.20',\Cor\Mgmt\Domain\DonationAmounts::offset('0.30','0.10'));self::assertSame('-0.10',\Cor\Mgmt\Domain\DonationAmounts::offset('0.10','0.20'));self::assertSame('0.40',\Cor\Mgmt\Domain\DonationAmounts::offset('0.30','-0.10'));self::assertSame(10,\Cor\Mgmt\Domain\DonationAmounts::compatible(new EuroAmount(1000)));self::assertSame('1.234',\Cor\Mgmt\Domain\DonationAmounts::thumbs(new EuroAmount(1234)));
    }

}
