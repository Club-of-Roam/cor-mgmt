<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\Scoring;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

final class ScoringTest extends TestCase
{
    private const RULES = ['extra_point_amount' => 5, 'one_extra_point' => 1, 'amount_extra_point' => 1, 'vary_extra_point_field' => 1];
    public static function placements(): array
    {
        return [[0,0],[1,20],[2,17],[3,14],[4,12],[5,10],[6,8],[7,6],[8,5],[9,4],[10,3],[11,2],[12,1],[99,0]];
    }
    #[DataProvider('placements')]
    public function testPlacementTableIsUnchanged(int $rank, int $points): void
    {
        self::assertSame($points, Scoring::points(['rank'=>$rank,'one'=>0,'fixed'=>0,'variable'=>0], self::RULES, 1));
    }
    public function testFixedBonusHasIndependentSwitchAndNoPlacementCanReceiveBonus(): void
    {
        $rules=self::RULES;$rules['one_extra_point']=0;
        self::assertSame(5, Scoring::points(['rank'=>99,'one'=>0,'fixed'=>1,'variable'=>0],$rules,1));
        self::assertSame(13, Scoring::points(['rank'=>0,'one'=>1,'fixed'=>1,'variable'=>7],self::RULES,6));
    }
    public static function invalid(): array
    {
        return [[13,0,0,0,1],[-1,0,0,0,1],[1,2,0,0,1],[1,0,2,0,1],[1,0,0,256,6],[1,0,0,-1,6],[1,0,0,1,5],[1,0,0,0,0]];
    }
    #[DataProvider('invalid')]
    public function testInvalidInputsDoNotBecomePoints(int $rank,int $one,int $fixed,int $variable,int $stage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Scoring::points(compact('rank','one','fixed','variable'),self::RULES,$stage);
    }
    public function testDisabledBonusesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Scoring::points(['rank'=>1,'one'=>1,'fixed'=>0,'variable'=>0],['extra_point_amount'=>5],1);
    }
    public function testRankingHandlesHighScoresTiesAndExactLimits(): void
    {
        $teams=[['id'=>4,'points'=>999],['id'=>2,'points'=>1000],['id'=>1,'points'=>1000],['id'=>3,'points'=>0]];
        self::assertSame([1,2,4,3],array_column(Scoring::ranking($teams),'id'));
        self::assertSame([1,1,3,4],array_column(Scoring::ranking($teams),'position'));
        self::assertCount(2,Scoring::ranking($teams,2));
        self::assertCount(4,Scoring::ranking($teams,8));
        self::assertSame([],Scoring::ranking([]));
    }
}
