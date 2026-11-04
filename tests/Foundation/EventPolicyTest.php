<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\EventPolicy;
use Cor\Mgmt\Domain\RacePhase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventPolicyTest extends TestCase
{
    public static function rules(): array
    {
        $cases=[];
        foreach ([['race','num_teammember',[0]],['race','dis_fee',[0]],['race','startingpoint',[0,1]],
            ['route','max_teams',[0,1]],['race','logo_url',[0,1,2]],['route','color_code',[0,1,2]],
            ['stage','number',[0]],['stage','meeting_point',[0,1,2,3]],['race','start',[0,1,2,3]],
            ['race','liveticker',[0,1,2,3]],['race','one_extra_point',[0,1,2]],['route','user_id',[0,1,2,3]]] as [$type,$field,$allowed]) {
            foreach (range(0,4) as $phase) {$cases[$type.'-'.$field.'-'.$phase]=[$type,$field,$phase,in_array($phase,$allowed,true)];}
        }
        return $cases;
    }
    #[DataProvider('rules')]
    public function testApprovedFieldGroups(string $type,string $field,int $phase,bool $allowed): void
    {
        self::assertSame($allowed,EventPolicy::allows($type,$field,RacePhase::from($phase)));
    }
    public function testUnknownFieldAndUnknownResourceFailClosed(): void
    {
        self::assertFalse(EventPolicy::allows('race','arbitrary',RacePhase::BeforeRegistration));
        self::assertFalse(EventPolicy::allows('arbitrary','name',RacePhase::BeforeRegistration));
    }
    public function testCapacityWarningBoundary(): void
    {
        self::assertSame([],EventPolicy::capacityWarnings(2,2));
        self::assertSame(['capacity_below_occupancy'],EventPolicy::capacityWarnings(1,2));
    }
}
