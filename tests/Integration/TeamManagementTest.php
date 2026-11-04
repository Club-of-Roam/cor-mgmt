<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Teams\ParticipationException;
use Cor\Mgmt\WordPress\TeamBoundary;
use Cor\Mgmt\WordPress\TeamFrontend;
use Cor\Mgmt\Infrastructure\WordPress\ParticipationRepository;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeamManagementTest extends TestCase
{
    private array $saved;
    private int $admin;
    private int $one;
    private int $two;
    private int $three;
    private ParticipationRepository $repo;
    protected function setUp(): void
    {
        global $wpdb;
        $this->saved=[$_GET,$_POST,$_FILES,$_SERVER];$_GET=$_POST=$_FILES=[];$_SERVER['REQUEST_METHOD']='GET';
        $wpdb->query('START TRANSACTION');wp_cache_flush();
        foreach(['admin','one','two','three'] as $key) {
            $id=wp_insert_user(['user_login'=>'team_'.$key,'user_email'=>'team-module-'.$key.'@example.invalid','user_pass'=>'synthetic-only','first_name'=>'Example','last_name'=>'Person','role'=>$key==='admin'?'administrator':'subscriber']);
            self::assertIsInt($id);$this->$key=$id;
        }
        $this->repo=TeamBoundary::repository();
        foreach(range(0,4) as $phase) {
            $id=$phase+1;
            $wpdb->insert($wpdb->prefix.'h3_mgmt_races',['id'=>$id,'name'=>'Synthetic','setting'=>wp_json_encode(['status'=>$phase,'num_teammember_min'=>1,'num_teammember'=>2,'startingpoint'=>1,'dis_fee'=>1,'dis_waiver'=>1,'dis_shirt_size'=>1,'dis_mobile_inf'=>1]),'information_text'=>'{}']);
            $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>10+$id,'race_id'=>$id,'name'=>'Route','max_teams'=>1]);
        }
        wp_set_current_user($this->one);$GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown(): void
    {
        global $wpdb;$error=$wpdb->last_error;$wpdb->query('ROLLBACK');wp_cache_flush();wp_set_current_user(0);
        [$_GET,$_POST,$_FILES,$_SERVER]=$this->saved;self::assertSame('',$error);
    }
    private function team(int $race=2,int $id=40,?int $user=null,int $complete=1,int $route=0): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>$id,'race_id'=>$race,'team_name'=>'Keep team','complete'=>$complete,'route_id'=>$route,'meta_5'=>'Keep answer']);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teammates',['id'=>100+$id,'team_id'=>$id,'user_id'=>$user??$this->one,'paid'=>1,'waiver'=>1,'language'=>'en']);
    }
    private function rejected(string $reason,callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(ParticipationException $error){self::assertSame($reason,$error->reason);}
    }
    public static function phases(): array {return [[0],[1],[2],[3],[4]];}
    #[DataProvider('phases')]
    public function testNewTeamRequiresOpenRegistration(int $phase): void
    {
        $call=fn()=>TeamBoundary::manager()->save($phase+1,$this->one,['team_name'=>'New'],[]);
        if($phase===1){$result=$call();self::assertSame('New',$this->repo->find('team',$result['team'])['team_name']);}
        else{$this->rejected('phase_locked',$call);}
    }
    #[DataProvider('phases')]
    public function testContentAndNamesFollowIndependentRules(int $phase): void
    {
        $this->team($phase+1);
        $manager=TeamBoundary::manager();
        if($phase>=1&&$phase<=3){$manager->save($phase+1,$this->one,['description'=>'Changed','meta_5'=>'Answer'],[]);self::assertSame('Answer',$this->repo->find('team',40)['meta_5']);}
        else{$this->rejected('phase_locked',fn()=>$manager->save($phase+1,$this->one,['description'=>'Changed'],[]));}
        if($phase===1){$manager->save($phase+1,$this->one,['team_name'=>'Renamed'],[]);self::assertSame('Renamed',$this->repo->find('team',40)['team_name']);}
        else{$this->rejected('phase_locked',fn()=>$manager->save($phase+1,$this->one,['team_name'=>'Renamed'],[]));}
    }
    #[DataProvider('phases')]
    public function testLeavingFollowsPhasesAndPreservesHistory(int $phase): void
    {
        $this->team($phase+1);
        if(in_array($phase,[1,2],true)){
            TeamBoundary::manager()->leave($phase+1,$this->one);
            self::assertSame([],$this->repo->related('teammate','team_id','40'));
            self::assertSame('0',$this->repo->find('team',40)['complete']);
            self::assertNotSame([],$this->repo->find('team',40));
            global $wpdb;self::assertSame('1',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cor_mgmt_membership_history WHERE team_id=40"));
        }else{$this->rejected('phase_locked',fn()=>TeamBoundary::manager()->leave($phase+1,$this->one));}
    }
    public function testUnlimitedInvitationsDoNotReserveMemberPlacesAndOnlyStoreHashes(): void
    {
        $this->team();$manager=TeamBoundary::manager();
        $emails=array_map(static fn(int $i):string=>'invite'.$i.'@example.invalid',range(1,8));
        $result=$manager->save(2,$this->one,[],[],'',$emails);
        $invites=array_values(array_filter($result['events'],static fn(array $event):bool=>$event['action']==='invitation'));
        self::assertCount(8,$invites);self::assertCount(1,$this->repo->related('teammate','team_id','40'));
        $tokens=$this->repo->related('token','team_id','40');self::assertCount(8,$tokens);
        foreach($invites as $index=>$invite){self::assertSame(67,strlen($invite['code']));self::assertSame(hash('sha256',$invite['code']),$tokens[$index]['token_hash']);self::assertArrayNotHasKey('code',$tokens[$index]);}
        $repeat=$manager->save(2,$this->one,[],[],'',$emails);self::assertSame([],$repeat['events']);
        wp_set_current_user($this->two);
        $manager->save(2,$this->two,['team_name'=>'Must not overwrite'],[],$invites[0]['code']);
        self::assertSame('Keep team',$this->repo->find('team',40)['team_name']);self::assertCount(2,$this->repo->related('teammate','team_id','40'));
        wp_set_current_user($this->three);
        $this->rejected('team_full',fn()=>$manager->save(2,$this->three,[],[],$invites[1]['code']));
        $this->rejected('invalid_invitation',fn()=>$manager->save(2,$this->three,[],[],$invites[0]['code']));
    }
    public function testClosedInvitationCanBeUsedAgainAfterExplicitReopening(): void
    {
        global $wpdb;$this->team();$manager=TeamBoundary::manager();$result=$manager->save(2,$this->one,[],[],'',['invite@example.invalid']);$code=$result['events'][0]['code'];
        $settings=json_decode($this->repo->find('race',2)['setting'],true);$settings['status']=2;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>2]);
        $this->rejected('phase_locked',fn()=>$manager->invitation($code,2));
        $settings['status']=1;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>2]);
        self::assertSame('40',$manager->invitation($code,2)['team_id']);
    }
    public function testLegacyNumericCodeIsConsumedWithoutDeletingSourceAndRejectsAliases(): void
    {
        global $wpdb;$this->team();
        $wpdb->insert($wpdb->prefix.'h3_mgmt_invitations',['team_id'=>40,'email'=>'old@example.invalid','code'=>123456]);
        wp_set_current_user($this->two);TeamBoundary::manager()->save(2,$this->two,[],[],'000123456');
        self::assertSame('1',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_invitations"));
        wp_set_current_user($this->three);$this->rejected('invalid_invitation',fn()=>TeamBoundary::manager()->save(2,$this->three,[],[],'123456'));
    }
    public function testRouteCapacityIsCheckedAndIncompleteTeamsDoNotOccupyPlaces(): void
    {
        $this->team(2,40,$this->one,1,0);$this->team(2,41,$this->two,0,12);
        $manager=TeamBoundary::manager();$manager->save(2,$this->one,[],[],'',[],12);self::assertSame('12',$this->repo->find('team',40)['route_id']);
        wp_set_current_user($this->two);$this->rejected('route_full',fn()=>$manager->save(2,$this->two,[],[],'',[],12));
        self::assertSame('0',$this->repo->find('team',41)['complete']);
    }
    public function testClosedRegistrationAllowsFirstRouteButNotChangingAnExistingRoute(): void
    {
        global $wpdb;$this->team(3,40,$this->one,1,0);$manager=TeamBoundary::manager();$manager->save(3,$this->one,[],[],'',[],13);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>20,'race_id'=>3,'name'=>'Other','max_teams'=>2]);
        $this->rejected('route_change_locked',fn()=>$manager->save(3,$this->one,[],[],'',[],20));self::assertSame('13',$this->repo->find('team',40)['route_id']);
    }
    public function testConfirmationPromotesOnceAndReversalDemotesBeforeStart(): void
    {
        global $wpdb;$this->team(2,40,$this->one,0,0);$settings=json_decode($this->repo->find('race',2)['setting'],true);$settings['dis_fee']=0;$settings['startingpoint']=0;$settings['start_route_id']=12;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>2]);
        $wpdb->update($wpdb->prefix.'h3_mgmt_teammates',['paid'=>0],['id'=>140]);wp_set_current_user($this->admin);
        $first=TeamBoundary::manager()->confirm([140],'paid',true);self::assertSame('1',$this->repo->find('team',40)['complete']);self::assertSame('12',$this->repo->find('team',40)['route_id']);self::assertCount(1,$first['events']);
        self::assertSame([],TeamBoundary::manager()->confirm([140],'paid',true)['events']);
        TeamBoundary::manager()->confirm([140],'paid',false);self::assertSame('0',$this->repo->find('team',40)['complete']);self::assertSame('0',$this->repo->find('team',40)['route_id']);
    }
    public function testMixedBulkConfirmationRollsBackAllEarlierUpdates(): void
    {
        global $wpdb;$this->team(2,40,$this->one);$this->team(4,41,$this->two);$wpdb->update($wpdb->prefix.'h3_mgmt_teammates',['paid'=>0],['id'=>140]);wp_set_current_user($this->admin);
        $this->rejected('phase_locked',fn()=>TeamBoundary::manager()->confirm([140,141],'paid',true));self::assertSame('0',$this->repo->find('teammate',140)['paid']);
    }
    public function testStartedStatusIsNotAutomaticallyLostAfterProfileChanges(): void
    {
        global $wpdb;$this->team(4,40,$this->one,1,14);$settings=json_decode($this->repo->find('race',4)['setting'],true);$settings['dis_shirt_size']=0;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>4]);
        TeamBoundary::manager()->profile($this->one,['shirt_size'=>'0']);self::assertSame('1',$this->repo->find('team',40)['complete']);self::assertSame('14',$this->repo->find('team',40)['route_id']);
    }
    public function testProfileUpdateRecalculatesOtherOpenTeamsWithoutRenderingWrites(): void
    {
        global $wpdb;$this->team(2,40,$this->one,1,12);$settings=json_decode($this->repo->find('race',2)['setting'],true);$settings['dis_shirt_size']=0;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>2]);update_user_meta($this->one,'shirt_size','ms');
        TeamBoundary::manager()->profile($this->one,['shirt_size'=>'0']);self::assertSame('0',$this->repo->find('team',40)['complete']);self::assertSame('0',$this->repo->find('team',40)['route_id']);
        $_SERVER['REQUEST_METHOD']='POST';$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-team-2')];
        $writes=[];$capture=static function($sql)use(&$writes){if(preg_match('/^(INSERT|UPDATE|DELETE)\b/',$sql)){$writes[]=$sql;}return $sql;};add_filter('query',$capture);
        try{$GLOBALS['h3_mgmt_teams']->is_complete(40);$html=TeamFrontend::dashboard(2);}finally{remove_filter('query',$capture);}
        self::assertSame([],$writes);self::assertSame([],$GLOBALS['cor_test_mail']);self::assertStringContainsString('cor_team_revision',$html);
    }
    public function testOrganizerKeepsExistingConfirmationIdsAndLanguages(): void
    {
        $this->team();$before=$this->repo->find('teammate',140);wp_set_current_user($this->admin);
        TeamBoundary::manager()->administer(2,40,['description'=>'Changed'],[$this->one],[$this->one=>'de'],null);
        $after=$this->repo->find('teammate',140);self::assertSame($before['paid'],$after['paid']);self::assertSame($before['waiver'],$after['waiver']);self::assertSame('de',$after['language']);
        TeamBoundary::manager()->administer(2,40,[],[$this->two],[$this->two=>'en'],null);
        self::assertSame([],$this->repo->find('teammate',140));$rows=$this->repo->related('teammate','team_id','40');self::assertSame('0',$rows[0]['paid']);self::assertSame('0',$rows[0]['waiver']);
    }
    public function testDuplicateAndCrossRaceMembershipChangesAreNotGuessed(): void
    {
        $this->team(2,40,$this->one);$this->team(2,41,$this->two);wp_set_current_user($this->admin);
        $this->rejected('duplicate_membership_review',fn()=>TeamBoundary::manager()->administer(2,40,[],[$this->two],[],null));
        $this->rejected('cross_race_review_required',fn()=>TeamBoundary::manager()->administer(3,40,[],[$this->one],[],null));
    }
    public function testDeletionProtectsHistoricalResultsAndArchivedTeams(): void
    {
        global $wpdb;$this->team();wp_set_current_user($this->admin);$wpdb->update($wpdb->prefix.'h3_mgmt_teams',['total_points'=>4],['id'=>40]);
        $this->rejected('dependent_records',fn()=>TeamBoundary::manager()->delete(40));$wpdb->update($wpdb->prefix.'h3_mgmt_teams',['total_points'=>0,'race_id'=>5],['id'=>40]);
        $this->rejected('phase_locked',fn()=>TeamBoundary::manager()->delete(40));
    }
    public function testStaleRevisionAndForeignWriterFailBeforeChangingData(): void
    {
        $this->team();$manager=TeamBoundary::manager();$revision=$manager->revision(2,40);$manager->save(2,$this->one,['description'=>'First'],[]);
        $this->rejected('stale_record',fn()=>$manager->save(2,$this->one,['description'=>'Old'],[],'',[],null,$revision));
        $this->rejected('not_permitted',fn()=>$manager->save(2,$this->two,['description'=>'Foreign'],[]));
    }
    public function testProfileBoundaryValidatesBeforeWritingAndParticipantFormUsesNativeControls(): void
    {
        $this->team();$_SERVER['REQUEST_METHOD']='POST';$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-team-2'),'birthday'=>'2026-02-30','city'=>'Do not save'];
        $this->rejected('invalid_birthday',fn()=>TeamBoundary::save(2));self::assertSame('',get_user_meta($this->one,'city',true));
        $_POST=['meta_1'=>"First line\nSecond line"];self::assertSame("First line\nSecond line",TeamBoundary::teamInput()['meta_1']);
        $_POST=[];$html=TeamFrontend::dashboard(2);self::assertStringContainsString('type="date"',$html);self::assertStringContainsString('name="invitations"',$html);self::assertStringNotContainsString(get_userdata($this->one)->user_pass,$html);
        wp_set_current_user(0);$_GET=['invitation'=>'v2-'.str_repeat('a',64)];
        self::assertStringContainsString('invitation%3Dv2-',TeamFrontend::dashboard(2));
    }
    public function testForeignActorCannotLeaveOrReadOrCancelInvitations(): void
    {
        $this->team();
        $manager=TeamBoundary::manager();
        $this->rejected('not_permitted',fn()=>$manager->leave(2,$this->two));
        $this->rejected('not_permitted',fn()=>$manager->pending(2,$this->two));
        $this->rejected('not_permitted',fn()=>$manager->cancelInvitation(2,$this->two,'someone@example.invalid'));
        self::assertCount(1,$this->repo->related('teammate','team_id','40'));
    }
    public function testRevocationPreservesLegacyCodesAndAllowsFreshInvitation(): void
    {
        global $wpdb;$this->team();$manager=TeamBoundary::manager();
        $result=$manager->save(2,$this->one,[],[],'',['new@example.invalid']);$code=$result['events'][0]['code'];
        $wpdb->insert($wpdb->prefix.'h3_mgmt_invitations',['team_id'=>40,'email'=>'old@example.invalid','code'=>'9876543210123456']);
        self::assertCount(2,$manager->pending(2,$this->one));
        $manager->cancelInvitation(2,$this->one,'new@example.invalid');$manager->cancelInvitation(2,$this->one,'old@example.invalid');
        self::assertSame([],$manager->pending(2,$this->one));
        $this->rejected('invalid_invitation',fn()=>$manager->invitation($code,2));
        $this->rejected('invalid_invitation',fn()=>$manager->invitation('9876543210123456',2));
        self::assertSame('1',$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h3_mgmt_invitations"));
        self::assertCount(1,$manager->save(2,$this->one,[],[],'',['new@example.invalid'])['events']);
    }
    public function testAdminListSortsFiltersAndBulkConfirmsWithObjectPermissions(): void
    {
        global $wpdb;$this->team(2,40,$this->one,1,12);$this->team(2,41,$this->two,1,12);
        wp_set_current_user($this->admin);$_GET=['sort'=>'team','direction'=>'asc','race_filter'=>'2','route_filter'=>'12'];
        ob_start();try{\Cor\Mgmt\WordPress\TeamAdmin::control(true);$html=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('form="cor-participant-bulk"',$html);
        self::assertStringContainsString('team-module-one@example.invalid',$html);
        self::assertStringContainsString('name="sort"',$html);
        $_SERVER['REQUEST_METHOD']='POST';$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-admin-participants'),'todo'=>'bulk-unpaid','bulk'=>['140','141']];
        ob_start();try{\Cor\Mgmt\WordPress\TeamAdmin::control(true);}finally{ob_end_clean();}
        self::assertSame('0',$this->repo->find('teammate',140)['paid']);self::assertSame('0',$this->repo->find('teammate',141)['paid']);
        $_GET=[];$_POST=[];wp_set_current_user($this->three);
        $person=wp_get_current_user();$person->add_cap('h3_mgmt_view_teams');$person->add_cap('h3_mgmt_view_own_teams');$person->add_cap('h3_mgmt_edit_own_teams');
        $wpdb->update($wpdb->prefix.'h3_mgmt_routes',['user_id'=>$this->three],['id'=>12]);
        $wpdb->update($wpdb->prefix.'h3_mgmt_teams',['route_id'=>13],['id'=>41]);
        ob_start();try{\Cor\Mgmt\WordPress\TeamAdmin::control(true);$scoped=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('team-module-one@example.invalid',$scoped);
        self::assertStringNotContainsString('team-module-two@example.invalid',$scoped);
        $_GET=['todo'=>'edit','id'=>'41'];
        ob_start();try{\Cor\Mgmt\WordPress\TeamAdmin::control();$denied=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringNotContainsString('name="member1"',$denied);
    }
    public function testAvatarIsNativeAndCannotBeInjectedAsPostedUrl(): void
    {
        $_POST=['cor_mgmt_avatar'=>'https://untrusted.invalid/picture.jpg'];
        self::assertArrayNotHasKey('cor_mgmt_avatar',TeamBoundary::profileInput());
        update_user_meta($this->one,'simple_local_avatar',['full'=>'https://example.invalid/legacy.png']);
        self::assertSame('https://example.invalid/legacy.png',get_avatar_data($this->one)['url']);
        $_POST=['cor_remove_avatar'=>'1'];\Cor\Mgmt\WordPress\ProfileAvatar::save($this->one);
        self::assertSame('none',get_user_meta($this->one,'cor_mgmt_avatar',true));
        self::assertNotSame('https://example.invalid/legacy.png',get_avatar_data($this->one)['url']);
        self::assertNotSame('https://example.invalid/legacy.png',\Cor\Mgmt\WordPress\ProfileAvatar::data(['url'=>'https://example.invalid/legacy.png'],$this->one)['url']);
        self::assertSame(['full'=>'https://example.invalid/legacy.png'],get_user_meta($this->one,'simple_local_avatar',true));
    }
    public function testDuplicateRowsRequireReviewAndNeverSilentlyRewriteConfirmations(): void
    {
        global $wpdb;$this->team();$wpdb->insert($wpdb->prefix.'h3_mgmt_teammates',['team_id'=>40,'user_id'=>$this->one,'paid'=>0,'waiver'=>0,'language'=>'de']);
        $this->rejected('duplicate_membership_review',fn()=>TeamBoundary::manager()->save(2,$this->one,['description'=>'Ambiguous'],[]));
        $this->rejected('duplicate_membership_review',fn()=>TeamBoundary::manager()->profile($this->one,['city'=>'Must not persist']));
        self::assertSame('',get_user_meta($this->one,'city',true));
        self::assertSame('1',$this->repo->find('teammate',140)['paid']);
    }
    public function testPendingSetupDoesNotRunDdlOrRenderABrokenForm(): void
    {
        $this->team();delete_option('cor_mgmt_participation_install');
        $html=TeamFrontend::dashboard(2);
        self::assertStringContainsString('Participation setup is pending',$html);self::assertStringNotContainsString('name="submitted"',$html);
    }
    public function testFailedInvitationInsertRollsBackTeamProfileAndMemberWithNoMail(): void
    {
        global $wpdb;$database=new Database($wpdb);
        $store=new class(new ParticipationRepository($database)) implements \Cor\Mgmt\Application\Teams\ParticipationStore {
            public function __construct(private ParticipationRepository $inner){}
            public function find(string $type,int $id):array{return $this->inner->find($type,$id);}
            public function related(string $type,string $field,string $value):array{return $this->inner->related($type,$field,$value);}
            public function insert(string $type,array $values):int{if($type==='token'){throw new ParticipationException('injected_failure');}return $this->inner->insert($type,$values);}
            public function update(string $type,array $old,array $values):void{$this->inner->update($type,$old,$values);}
            public function remove(string $type,array $old):void{$this->inner->remove($type,$old);}
            public function profile(int $user):array{return $this->inner->profile($user);}
            public function saveProfile(int $user,array $values):void{$this->inner->saveProfile($user,$values);}
            public function atomic(callable $operation):array{return $this->inner->atomic($operation);}
        };
        $runtime=\Cor\Mgmt\WordPress\Services::get();
        $manager=new \Cor\Mgmt\Application\Teams\TeamManager($store,$runtime->clock,$runtime->random,static fn()=>true);
        $this->rejected('injected_failure',fn()=>$manager->save(2,$this->one,['team_name'=>'Rollback'],['city'=>'Must roll back'],'',['trigger@example.invalid']));
        self::assertSame([],$this->repo->related('teammate','user_id',(string)$this->one));
        self::assertSame([],$this->repo->related('team','race_id','2'));self::assertSame('',get_user_meta($this->one,'city',true));self::assertSame([],$GLOBALS['cor_test_mail']);
        $database->locked(function()use($manager):void{
            $this->rejected('injected_failure',fn()=>$manager->save(2,$this->one,['team_name'=>'Nested rollback'],['city'=>'Must roll back'],'',['trigger@example.invalid']));
            self::assertSame([],$this->repo->related('team','race_id','2'));
            self::assertSame('',get_user_meta($this->one,'city',true));
        });
    }
    public function testRaceRequirementsAndPhaseRollbackRefreshTeamsInTheSameTransaction(): void
    {
        global $wpdb;$this->team(1,40,$this->one,1,11);wp_set_current_user($this->admin);
        $editor=\Cor\Mgmt\WordPress\EventAdmin::editor();
        $editor->save('race',1,['dis_fee'=>'0'],$editor->revision('race',1));
        self::assertSame('1',$this->repo->find('team',40)['complete']);
        $editor->save('race',1,['num_teammember_min'=>'2'],$editor->revision('race',1));
        self::assertSame('0',$this->repo->find('team',40)['complete']);self::assertSame('0',$this->repo->find('team',40)['route_id']);
        $this->team(4,41,$this->two,1,14);
        $wpdb->update($wpdb->prefix.'h3_mgmt_teammates',['paid'=>0],['id'=>141]);
        $settings=json_decode($this->repo->find('race',4)['setting'],true);$settings['dis_fee']=0;$wpdb->update($wpdb->prefix.'h3_mgmt_races',['setting'=>wp_json_encode($settings)],['id'=>4]);
        $editor->save('race',4,['status'=>'1'],$editor->revision('race',4));
        self::assertSame('0',$this->repo->find('team',41)['complete']);self::assertSame('0',$this->repo->find('team',41)['route_id']);
    }
    public function testRaceChangeRollsBackWhenParticipationCannotBeReconciled(): void
    {
        $this->team(1);wp_set_current_user($this->admin);$before=$this->repo->find('race',1);delete_option('cor_mgmt_participation_install');
        $editor=\Cor\Mgmt\WordPress\EventAdmin::editor();
        $this->rejected('participation_setup_required',fn()=>$editor->save('race',1,['num_teammember_min'=>'2'],$editor->revision('race',1)));
        self::assertSame($before,$this->repo->find('race',1));self::assertSame('1',$this->repo->find('team',40)['complete']);
    }
}
