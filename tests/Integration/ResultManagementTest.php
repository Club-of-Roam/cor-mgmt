<?php

declare(strict_types=1);

namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Results\HistoricalComparison;
use Cor\Mgmt\Application\Results\ResultException;
use Cor\Mgmt\Domain\Scoring;
use Cor\Mgmt\WordPress\ResultBoundary;
use Cor\Mgmt\WordPress\ResultFrontend;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ResultsSchema;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ResultManagementTest extends TestCase
{
    private array $saved;
    private int $admin;
    private int $owner;
    protected function setUp(): void
    {
        global $wpdb;
        $this->saved=[$_GET,$_POST,$_SERVER];$_GET=$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
        $wpdb->query('START TRANSACTION');wp_cache_flush();
        foreach(['admin','owner'] as $key){
            $id=wp_insert_user(['user_login'=>'results_'.$key,'user_email'=>'result-'.$key.'@example.invalid','user_pass'=>'synthetic-only','role'=>$key==='admin'?'administrator':'subscriber']);
            self::assertIsInt($id);$this->$key=$id;
        }
        $rules=['status'=>0,'extra_point_amount'=>5,'one_extra_point'=>1,'amount_extra_point'=>1,'vary_extra_point_field'=>1];
        $wpdb->insert($wpdb->prefix.'h3_mgmt_races',['id'=>900,'name'=>'Synthetic results','setting'=>wp_json_encode($rules),'information_text'=>'{}']);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>901,'race_id'=>900,'name'=>'Result route','user_id'=>$this->owner]);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>902,'race_id'=>900,'name'=>'Other route']);
        foreach([1,6,7] as $number){$wpdb->insert($wpdb->prefix.'h3_mgmt_stages',['id'=>910+$number,'race_id'=>900,'route_id'=>901,'number'=>$number]);}
        foreach([940,941] as $id){$wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>$id,'race_id'=>900,'route_id'=>901,'team_name'=>'Team '.$id,'complete'=>1]);}
        wp_set_current_user($this->admin);$GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown(): void
    {
        global $wpdb;$error=$wpdb->last_error;$wpdb->query('ROLLBACK');wp_cache_flush();wp_set_current_user(0);[$_GET,$_POST,$_SERVER]=$this->saved;
        self::assertSame('',$error);self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function phase(int $phase): void
    {
        global $wpdb;$row=ResultBoundary::repository()->race(900);$rules=$row->rules();$rules['status']=$phase;
        $wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($rules)],['id'=>900]);
    }
    private function ready(): void
    {
        ResultBoundary::manager()->enable(900);$this->phase(3);wp_set_current_user($this->owner);
    }
    private function entries(int $rank=1,int $variable=0): array
    {
        return [940=>['rank'=>$rank,'one'=>0,'fixed'=>0,'variable'=>$variable],941=>['rank'=>2,'one'=>0,'fixed'=>0,'variable'=>0]];
    }
    private function submit(int $number,array $entries): void
    {
        $manager=ResultBoundary::manager();$manager->submit(901,$number,$entries,$manager->view(901)->revision(),get_current_user_id());
    }
    private function rejected(string $reason,callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(ResultException $error){self::assertSame($reason,$error->reason);}
    }
    public function testActivationIsExplicitIdempotentAndVersioned(): void
    {
        self::assertSame('',ResultBoundary::repository()->race(900)->version);
        ResultBoundary::manager()->enable(900);ResultBoundary::manager()->enable(900);
        self::assertSame(Scoring::VERSION,ResultBoundary::repository()->race(900)->version);
        self::assertSame([],ResultBoundary::repository()->race(900)->results);
        self::assertTrue(ResultsSchema::ready(new Database($GLOBALS['wpdb'])));
    }
    public function testHistoricalTotalsIncludingUnassignedTeamsBlockActivation(): void
    {
        global $wpdb;$wpdb->update($wpdb->prefix.'h3_mgmt_teams',['total_points'=>999,'route_id'=>0],['id'=>940]);
        $this->rejected('historical_results_present',fn()=>ResultBoundary::manager()->enable(900));
        self::assertSame('999',ResultBoundary::repository()->race(900)->teams[0]['total_points']);
    }
    public static function phases(): array{return [[0],[1],[2],[4]];}
    #[DataProvider('phases')]
    public function testOnlyStartedRaceCanBeWritten(int $phase): void
    {
        $this->ready();$revision=ResultBoundary::manager()->view(901)->revision();$this->phase($phase);
        $this->rejected('phase_locked',fn()=>ResultBoundary::manager()->submit(901,1,$this->entries(),$revision,$this->owner));
        self::assertSame([],ResultBoundary::repository()->race(900)->results);
    }
    public function testExistingStartedRaceIsNeverRecalculatedImplicitly(): void
    {
        $this->phase(3);$this->rejected('scoring_setup_required',fn()=>ResultBoundary::manager()->view(901));
        $this->rejected('phase_locked',fn()=>ResultBoundary::manager()->enable(900));
    }
    public function testForeignRouteAndAnonymousWritesFail(): void
    {
        $this->ready();$this->rejected('not_permitted',fn()=>ResultBoundary::manager()->view(902));
        wp_set_current_user(0);$this->rejected('not_permitted',fn()=>ResultBoundary::manager()->view(901));
    }
    public function testVariablesOneAndSevenSurviveOtherStageUpdatesAndRepeat(): void
    {
        $this->ready();$this->submit(6,$this->entries(1,1));$this->submit(1,$this->entries());
        $context=ResultBoundary::repository()->context(901);self::assertSame('41',$context->teams[0]['total_points']);
        $this->submit(6,$this->entries(1,7));$this->submit(1,$this->entries());
        $context=ResultBoundary::repository()->context(901);self::assertSame('47',$context->teams[0]['total_points']);self::assertCount(4,$context->results);
        self::assertSame('7',$context->teams[0]['vary_extra_stage_6']);self::assertSame(47,array_sum(array_map(fn($row)=>(int)$row['points'],array_filter($context->results,fn($row)=>$row['team_id']==='940'))));
    }
    public function testSeventhStageIsNormalizedWithoutInventingLegacyColumns(): void
    {
        $this->ready();$this->submit(7,$this->entries());
        $context=ResultBoundary::repository()->context(901);self::assertSame('917',$context->results[0]['stage_id']);self::assertSame('20',$context->teams[0]['total_points']);self::assertArrayNotHasKey('rank_stage_7',$context->teams[0]);
    }
    public function testStaleFormCannotOverwriteEarlierSubmission(): void
    {
        $this->ready();$revision=ResultBoundary::manager()->view(901)->revision();$this->submit(1,$this->entries());
        $this->rejected('stale_results',fn()=>ResultBoundary::manager()->submit(901,1,$this->entries(3),$revision,$this->owner));
        self::assertSame('20',ResultBoundary::repository()->context(901)->teams[0]['total_points']);
    }
    public function testHistoryGenerationRejectsAbaOverwriteAndIdenticalRetryIsANoOp(): void
    {
        $this->ready();$this->submit(1,$this->entries());$revision=ResultBoundary::manager()->view(901)->revision();
        $this->submit(1,$this->entries());self::assertSame($revision,ResultBoundary::manager()->view(901)->revision());
        $this->submit(1,$this->entries(3));$this->submit(1,$this->entries());
        self::assertNotSame($revision,ResultBoundary::manager()->view(901)->revision());
        $this->rejected('stale_results',fn()=>ResultBoundary::manager()->submit(901,1,$this->entries(2),$revision,$this->owner));
    }
    public function testAllRowsValidatedBeforeFirstWriteAndDisabledFixedBonusIsRejected(): void
    {
        $this->ready();$entries=$this->entries();$entries[941]['rank']=13;
        $this->rejected('invalid_rank',fn()=>$this->submit(1,$entries));self::assertSame([],ResultBoundary::repository()->context(901)->results);
        global $wpdb;$context=ResultBoundary::repository()->context(901);$rules=$context->rules();$rules['amount_extra_point']=0;
        $wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($rules)],['id'=>900]);$entries=$this->entries();$entries[940]['fixed']=1;
        $this->rejected('bonus_disabled',fn()=>$this->submit(1,$entries));self::assertSame([],ResultBoundary::repository()->context(901)->results);
    }
    public function testMissingAndForeignTeamsAndInvalidStageAreRejected(): void
    {
        $this->ready();$this->rejected('team_selection_changed',fn()=>$this->submit(1,[940=>$this->entries()[940]]));
        $entries=$this->entries();$entries[999]=$entries[940];$this->rejected('team_selection_changed',fn()=>$this->submit(1,$entries));
        $this->rejected('ambiguous_stage',fn()=>$this->submit(0,$this->entries()));$this->rejected('ambiguous_stage',fn()=>$this->submit(2,$this->entries()));
    }
    public function testDuplicateStageNumberCannotWrite(): void
    {
        $this->ready();global $wpdb;$wpdb->insert($wpdb->prefix.'h3_mgmt_stages',['id'=>930,'race_id'=>900,'route_id'=>901,'number'=>1]);
        $this->rejected('ambiguous_stage',fn()=>$this->submit(1,$this->entries()));
    }
    public function testRevisionIncludesRosterRulesAndAllStageData(): void
    {
        $this->ready();$revision=ResultBoundary::manager()->view(901)->revision();global $wpdb;
        $wpdb->update($wpdb->prefix.'h3_mgmt_teams',['complete'=>0],['id'=>941]);
        $this->rejected('stale_results',fn()=>ResultBoundary::manager()->submit(901,1,$this->entries(),$revision,$this->owner));
        $wpdb->update($wpdb->prefix.'h3_mgmt_teams',['complete'=>1],['id'=>941]);$wpdb->update($wpdb->prefix.'h3_mgmt_stages',['meeting_point'=>'Changed'],['id'=>911]);
        $this->rejected('stale_results',fn()=>ResultBoundary::manager()->submit(901,1,$this->entries(),$revision,$this->owner));
    }
    public function testJournalAndCompatibilityTotalAreCommittedWithResults(): void
    {
        $this->ready();$this->submit(1,$this->entries());global $wpdb;$db=new Database($wpdb);
        $row=$db->rows($db->prepare('SELECT * FROM %i WHERE race_id=%d',$db->table('cor_mgmt_result_history'),900))[0];
        self::assertSame((string)$this->owner,$row['actor_id']);$payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);self::assertSame(20,$payload['totals'][940]);self::assertSame(Scoring::VERSION,$payload['version']);self::assertSame([],$payload['before']);
    }
    public static function failedWrites(): array{return [['result'],['history']];}
    #[DataProvider('failedWrites')]
    public function testLaterWriteFailureRollsBackAllRowsAndJournal(string $target): void
    {
        $this->ready();global $wpdb;$count=0;
        $filter=static function(string $sql)use($target,&$count,$wpdb):string{
            if(str_starts_with($sql,'INSERT INTO `'.$wpdb->prefix.'cor_mgmt_results`')){$count++;if($target==='result'&&$count===2){throw new \RuntimeException('injected_result_failure');}}
            if($target==='history'&&str_starts_with($sql,'INSERT INTO `'.$wpdb->prefix.'cor_mgmt_result_history`')){throw new \RuntimeException('injected_result_failure');}
            return $sql;
        };
        add_filter('query',$filter);
        try{try{$this->submit(1,$this->entries());self::fail('Expected rollback');}catch(\RuntimeException $error){self::assertSame('injected_result_failure',$error->getMessage());}}
        finally{remove_filter('query',$filter);}
        $context=ResultBoundary::repository()->context(901);self::assertSame([],$context->results);self::assertSame('0',$context->teams[0]['total_points']);self::assertSame('0',$context->teams[1]['rank_stage_1']);
        $db=new Database($wpdb);self::assertSame('0',$db->scalar($db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=%d',$db->table('cor_mgmt_result_history'),900)));
    }
    public function testExistingResultsPreventStageDeletionAndTeamRouteChangesAfterPhaseReset(): void
    {
        $this->ready();$this->submit(1,$this->entries());$this->phase(0);wp_set_current_user($this->admin);
        $db=new Database($GLOBALS['wpdb']);$store=new \Cor\Mgmt\Infrastructure\WordPress\LegacyEventStore($db);
        try{$store->exclusive(fn()=>[$store->remove('stage',$store->find('stage',911))],true);self::fail('Expected stage protection');}
        catch(\Cor\Mgmt\Application\Events\EventException $error){self::assertSame('stage_has_results',$error->getMessage());}
        $participation=new \Cor\Mgmt\Infrastructure\WordPress\ParticipationRepository($db);
        try{$participation->atomic(fn()=>[$participation->update('team',$participation->find('team',940),['route_id'=>'902'])]);self::fail('Expected route protection');}
        catch(\Cor\Mgmt\Application\Teams\ParticipationException $error){self::assertSame('results_prevent_route_change',$error->reason);}
        self::assertCount(2,ResultBoundary::repository()->context(901)->results);
    }
    public function testZeroPointNormalizedResultsStillPreventDeletingTheirTeam(): void
    {
        $this->ready();$entries=$this->entries(0);$entries[941]['rank']=0;$this->submit(1,$entries);wp_set_current_user($this->admin);
        try{\Cor\Mgmt\WordPress\TeamBoundary::manager()->delete(940);self::fail('Expected dependent result protection');}
        catch(\Cor\Mgmt\Application\Teams\ParticipationException $error){self::assertSame('dependent_records',$error->reason);}
        self::assertNotSame([],\Cor\Mgmt\WordPress\TeamBoundary::repository()->find('team',940));
        self::assertCount(2,ResultBoundary::repository()->context(901)->results);
    }
    public function testPhaseRollbackMayReleaseRouteWithoutChangingResultsAndSameRouteCanBeRestored(): void
    {
        $this->ready();$this->submit(1,$this->entries());$before=ResultBoundary::repository()->context(901)->results;wp_set_current_user($this->admin);
        $editor=\Cor\Mgmt\WordPress\EventAdmin::editor();$editor->save('race',900,['status'=>'1'],$editor->revision('race',900));
        $context=ResultBoundary::repository()->race(900);self::assertSame(1,(int)$context->rules()['status']);self::assertSame($before,$context->results);
        self::assertSame('0',$context->teams[0]['route_id']);self::assertSame('0',$context->teams[0]['complete']);self::assertSame('20',$context->teams[0]['total_points']);
        $repo=\Cor\Mgmt\WordPress\TeamBoundary::repository();$repo->atomic(function()use($repo):array{$repo->update('team',$repo->find('team',940),['route_id'=>'901']);return [];});
        self::assertSame('901',$repo->find('team',940)['route_id']);self::assertSame($before,ResultBoundary::repository()->race(900)->results);
    }
    public function testRequestRequiresNonceAndPostAndDoesNotWriteOnBadInput(): void
    {
        $this->ready();$_SERVER['REQUEST_METHOD']='POST';$_POST=['route'=>'901','stage'=>'1','rank-940'=>'1','rank-941'=>'2'];
        self::assertStringContainsString('not permitted',ResultBoundary::save());
        $_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-ranking-901');$_POST['cor_result_revision']=ResultBoundary::manager()->view(901)->revision();$_POST['rank-941']=['1'];
        self::assertStringContainsString('not permitted',ResultBoundary::save());self::assertSame([],ResultBoundary::repository()->context(901)->results);
        $_POST['rank-941']='2';self::assertStringContainsString('successfully saved',ResultBoundary::save());self::assertCount(2,ResultBoundary::repository()->context(901)->results);
    }
    public function testNativeFormKeepsIndependentBonusSwitchAndEscapesTeamName(): void
    {
        $this->ready();global $wpdb;$context=ResultBoundary::repository()->context(901);$rules=$context->rules();$rules['one_extra_point']=0;
        $wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($rules)],['id'=>900]);$wpdb->update($wpdb->prefix.'h3_mgmt_teams',['team_name'=>'<script>alert(1)</script>'],['id'=>940]);
        $form=ResultFrontend::form(901,1);self::assertStringContainsString('name="amount_extra-940"',$form);self::assertStringNotContainsString('name="one_extra-940"',$form);self::assertStringNotContainsString('<script>',$form);self::assertStringContainsString('cor_result_revision',$form);
        self::assertStringContainsString('name="vary_extra-940"',ResultFrontend::form(901,6));self::assertStringNotContainsString('name="vary_extra-940"',ResultFrontend::form(901,7));
    }
    public function testShortcodeSelectsOnlyRoutesInActiveRaceAndSupportsOrganizers(): void
    {
        $this->ready();global $wpdb;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['active'=>1],['id'=>900]);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>898,'race_id'=>899,'name'=>'Foreign race route','user_id'=>$this->owner]);
        $_GET=['stage'=>'7'];$html=$GLOBALS['h3_mgmt_races']->ranking_submission_control();
        self::assertStringContainsString('value="901"',$html);self::assertStringNotContainsString('Foreign race route',$html);self::assertStringContainsString('name="rank-940"',$html);
        $_GET=['cor_result_route'=>'898','stage'=>'1'];self::assertStringContainsString('unavailable',ResultFrontend::control());
        wp_set_current_user($this->admin);$_GET=[];self::assertStringContainsString('Other route',ResultFrontend::control());
    }
    public function testHistoricalComparisonIsReadOnlyAndDoesNotGuessUnknownMappings(): void
    {
        global $wpdb;$wpdb->update($wpdb->prefix.'h3_mgmt_teams',['total_points'=>37,'rank_stage_1'=>1,'rank_stage_6'=>2,'vary_extra_stage_6'=>5],['id'=>940]);
        $before=ResultBoundary::repository()->race(900);$report=HistoricalComparison::report($before);self::assertSame(42,$report[0]['candidate']);self::assertSame(5,$report[0]['delta']);self::assertSame($before->revision(),ResultBoundary::repository()->race(900)->revision());
        $wpdb->update($wpdb->prefix.'h3_mgmt_teams',['rank_stage_2'=>99],['id'=>940]);$report=HistoricalComparison::report(ResultBoundary::repository()->race(900));self::assertNull($report[0]['candidate']);self::assertContains('unmapped_slot_2',$report[0]['issues']);
        self::assertNull(ResultFrontend::table(['race'=>900]));self::assertSame('37',ResultBoundary::repository()->race(900)->teams[0]['total_points']);
    }
    public function testRankingKeepsGlobalAndRoutePositionsAndExactTop(): void
    {
        $this->ready();$this->submit(1,$this->entries());$html=ResultFrontend::table(['race'=>900,'top'=>'1','show_stages'=>'1','show_nav'=>'0']);
        self::assertSame(1,substr_count($html,'<tr><th scope="row">'));self::assertStringContainsString('Stage 7',$html);self::assertStringNotContainsString('<nav',$html);
        $html=ResultFrontend::table(['race'=>900,'top'=>'0','show_stages'=>'0']);self::assertSame(2,substr_count($html,'<tr><th scope="row">'));self::assertStringNotContainsString('Stage 1',$html);self::assertStringContainsString('Ranking routes',$html);
    }
}
