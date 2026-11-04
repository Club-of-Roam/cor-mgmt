<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Domain\TeamPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class TeamPolicyTest extends TestCase
{
    public static function rules(): array
    {
        $cases=[];
        foreach(['create'=>[1],'invite'=>[1],'accept'=>[1],'name'=>[1],'leave'=>[1,2],'content'=>[1,2,3],'route'=>[1,2],'confirm'=>[0,1,2],'profile'=>[0,1,2,3]] as $action=>$phases) {
            foreach(range(0,4) as $phase){$cases[$action.'-'.$phase]=[$action,$phase,in_array($phase,$phases,true)];}
        }
        return $cases;
    }
    #[DataProvider('rules')]
    public function testParticipantRules(string $action,int $phase,bool $expected): void {self::assertSame($expected,TeamPolicy::allows($action,RacePhase::from($phase)));}
    public function testOrganizerExceptionsDoNotOverrideConfirmationOrArchive(): void
    {
        self::assertTrue(TeamPolicy::allows('membership',RacePhase::Started,true));
        self::assertFalse(TeamPolicy::allows('confirm',RacePhase::Started,true));
        foreach(['create','membership','route','profile','name','content'] as $action){self::assertFalse(TeamPolicy::allows($action,RacePhase::Archived,true));}
    }
    public function testEligibilityHonorsMinimumMaximumAndActualMembers(): void
    {
        $settings=['num_teammember_min'=>'1','num_teammember'=>'2','dis_shirt_size'=>'1','dis_mobile_inf'=>'1'];
        $member=['user_id'=>'10','paid'=>'1','waiver'=>'1'];
        self::assertTrue(TeamPolicy::eligible($settings,[$member],[10=>['first_name'=>'Example']]));
        self::assertFalse(TeamPolicy::eligible($settings,[$member,$member],[10=>[]]));
        self::assertFalse(TeamPolicy::eligible($settings,[$member],[]));
        self::assertFalse(TeamPolicy::eligible($settings,[array_replace($member,['paid'=>'0'])],[10=>[]]));
        self::assertTrue(TeamPolicy::eligible($settings+['dis_fee'=>'1'],[array_replace($member,['paid'=>'0'])],[10=>[]]));
    }
}
