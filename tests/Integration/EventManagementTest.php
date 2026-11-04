<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Events\EventEditor;
use Cor\Mgmt\Application\Events\EventException;
use Cor\Mgmt\Domain\EventPolicy;
use Cor\Mgmt\Domain\RacePhase;
use Cor\Mgmt\Infrastructure\WordPress\LegacyEventStore;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\EventAdmin;
use Cor\Mgmt\WordPress\EventList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventManagementTest extends TestCase
{
    private array $request;
    private LegacyEventStore $store;
    private EventEditor $editor;
    private int $admin;
    protected function setUp(): void
    {
        global $wpdb;
        $this->request = [$_GET, $_POST, $_SERVER];
        $_GET = $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $wpdb->query('START TRANSACTION');
        wp_cache_flush();
        $this->admin = wp_insert_user(['user_login'=>'events_admin','user_email'=>'events@example.invalid','user_pass'=>'synthetic-password','role'=>'administrator']);
        wp_set_current_user($this->admin);
        $this->store = new LegacyEventStore(new Database($wpdb));
        $this->editor = EventAdmin::editor();
        foreach (range(0,4) as $phase) {
            $id=$phase+1;
            $wpdb->insert($wpdb->prefix.'h3_mgmt_races',['id'=>$id,'name'=>'Synthetic '.$phase,'start'=>1,'end'=>1000000000,'active'=>0,
                'setting'=>wp_json_encode(['status'=>$phase,'num_teammember_min'=>2,'num_teammember'=>3,'startingpoint'=>1,'start_route_id'=>0,'dis_fee'=>0,'one_extra_point'=>0,'liveticker'=>0,'unknown'=>['binary-looking'=>'001','nested'=>[null,1]]]),
                'information_text'=>wp_json_encode(['1en'=>'Existing text','unknown-key'=>['keep'=>true]])]);
            $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>$id+10,'race_id'=>$id,'name'=>'Route '.$phase,'max_teams'=>3,'color_code'=>'00ff00','user_id'=>0]);
            $wpdb->insert($wpdb->prefix.'h3_mgmt_stages',['id'=>$id+20,'race_id'=>$id,'route_id'=>$id+10,'number'=>1,'destination'=>'Town','meeting_point'=>'Old meeting point','country_3166_alpha-2'=>'DE']);
        }
        $GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown(): void
    {
        global $wpdb;
        $error=$wpdb->last_error;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();
        wp_set_current_user(0);
        [$_GET,$_POST,$_SERVER]=$this->request;
        self::assertSame('', $error);
    }
    private function save(string $type,int $id,array $values,bool $confirm=false): array
    {
        return $this->editor->save($type,$id,$values,$this->editor->revision($type,$id),$confirm);
    }
    private function rejects(string $reason,callable $call): void
    {
        try { $call(); self::fail('Expected '.$reason); }
        catch(EventException $error) { self::assertSame($reason,$error->reason); }
    }
    public static function phases(): array { return [[0],[1],[2],[3],[4]]; }
    #[DataProvider('phases')]
    public function testParticipationRequirementsFollowEveryCurrentPhase(int $phase): void
    {
        $id=$phase+1;
        $before=$this->store->find('race',$id);
        if($phase===0) {
            $this->save('race',$id,['num_teammember'=>'5','dis_fee'=>'1']);
            self::assertSame('5',EventEditor::flatten($this->store->find('race',$id))['num_teammember']);
        } else {
            $this->rejects($phase===4?'archived':'field_locked',fn()=>$this->save('race',$id,['num_teammember'=>'5','dis_fee'=>'1']));
            self::assertSame($before,$this->store->find('race',$id));
        }
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    #[DataProvider('phases')]
    public function testCapacityChangesThroughRegistrationOnly(int $phase): void
    {
        $id=$phase+11;
        if($phase<2) {
            $this->save('route',$id,['max_teams'=>'7']);
            self::assertSame('7',$this->store->find('route',$id)['max_teams']);
        } else {
            $this->rejects($phase===4?'archived':'field_locked',fn()=>$this->save('route',$id,['max_teams'=>'7']));
        }
    }
    #[DataProvider('phases')]
    public function testOrganizationalMeetingPointAllowedUntilArchive(int $phase): void
    {
        $id=$phase+21;
        if($phase<4) {
            $this->save('stage',$id,['meeting_point'=>'New point']);
            self::assertSame('New point',$this->store->find('stage',$id)['meeting_point']);
        } else {
            $this->rejects('archived',fn()=>$this->save('stage',$id,['meeting_point'=>'New point']));
        }
    }
    #[DataProvider('phases')]
    public function testRouteDesignAndStageStructureUseSeparateRules(int $phase): void
    {
        if($phase<=2) {
            $this->save('route',11+$phase,['color_code'=>'ffffff']);
            self::assertSame('ffffff',$this->store->find('route',11+$phase)['color_code']);
        } else {
            $this->rejects($phase===4?'archived':'field_locked',fn()=>$this->save('route',11+$phase,['color_code'=>'ffffff']));
        }
        if($phase===0) {
            $this->save('stage',21,['destination'=>'Another town']);
            self::assertSame('Another town',$this->store->find('stage',21)['destination']);
        } else {
            $this->rejects($phase===4?'archived':'field_locked',fn()=>$this->save('stage',21+$phase,['destination'=>'Another town']));
        }
    }
    public function testPhaseRollbackRestoresRightsButMustBeSavedSeparately(): void
    {
        $this->rejects('field_locked',fn()=>$this->save('race',4,['status'=>'0','num_teammember'=>'4']));
        $this->save('race',4,['status'=>'0']);
        $this->save('race',4,['num_teammember'=>'4']);
        self::assertSame('4',EventEditor::flatten($this->store->find('race',4))['num_teammember']);
        $this->rejects('change_phase_separately',fn()=>$this->save('race',4,['status'=>'1','num_teammember'=>'5']));
        $this->rejects('archive_not_ready',fn()=>$this->save('race',4,['status'=>'4']));
        $this->rejects('archived',fn()=>$this->save('race',5,['status'=>'1']));
    }
    public function testStaleParentAndStaleRecordAreRejected(): void
    {
        $revision=$this->editor->revision('route',11);
        $this->save('race',1,['status'=>'1']);
        $this->rejects('stale_record',fn()=>$this->editor->save('route',11,['max_teams'=>'5'],$revision));
        $revision=$this->editor->revision('race',1);
        $this->save('race',1,['name'=>'Changed elsewhere']);
        $this->rejects('stale_record',fn()=>$this->editor->save('race',1,['name'=>'Lost update'],$revision));
        self::assertSame('Changed elsewhere',$this->store->find('race',1)['name']);
    }
    public function testCapacityReductionRequiresConfirmationAndRetainsAllAssignments(): void
    {
        global $wpdb;
        foreach([1,1,0] as $index=>$complete) {
            $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>50+$index,'race_id'=>1,'route_id'=>11,'complete'=>$complete,'team_name'=>'Synthetic']);
        }
        $before=$this->store->matching('team','route_id',11);
        self::assertSame(1,$GLOBALS['h3_mgmt_races']->get_free_slots(11));
        $result=$this->save('route',11,['max_teams'=>'1']);
        self::assertTrue($result['confirmation_required']);
        self::assertSame('3',$this->store->find('route',11)['max_teams']);
        $this->save('route',11,['max_teams'=>'1'],true);
        self::assertSame('1',$this->store->find('route',11)['max_teams']);
        self::assertSame(0,$GLOBALS['h3_mgmt_races']->get_free_slots(11));
        self::assertSame($before,$this->store->matching('team','route_id',11));
    }
    public function testStartingPointWarningDoesNotReassignTeams(): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>50,'race_id'=>1,'route_id'=>11,'complete'=>1,'team_name'=>'Synthetic']);
        self::assertTrue($this->save('race',1,['startingpoint'=>'0','start_route_id'=>'11'])['confirmation_required']);
        self::assertSame('1',EventEditor::flatten($this->store->find('race',1))['startingpoint']);
        $this->save('race',1,['startingpoint'=>'0','start_route_id'=>'11'],true);
        self::assertSame('11',$this->store->find('team',50)['route_id']);
        $this->rejects('invalid_start_route',fn()=>$this->save('race',1,['start_route_id'=>'12'],true));
    }
    public function testUnknownSettingsAndInformationSurviveUnrelatedChanges(): void
    {
        $before=$this->store->find('race',1);
        $settings=json_decode($before['setting'],true);
        $this->save('race',1,['liveticker'=>'1']);
        $after=$this->store->find('race',1);
        self::assertSame($settings['unknown'],json_decode($after['setting'],true)['unknown']);
        self::assertSame($before['information_text'],$after['information_text']);
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    public function testRealNonceBoundaryAndSafeInformationMerge(): void
    {
        $_SERVER['REQUEST_METHOD']='POST';
        $_GET=['page'=>'h3-mgmt-races','todo'=>'save','id'=>1];
        $_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-admin-races'),'cor_event_revision'=>$this->editor->revision('race',1),'1en'=>'<p>New <script>alert(1)</script>text</p>'];
        EventAdmin::mutate('race',1,'save');
        $info=json_decode($this->store->find('race',1)['information_text'],true);
        self::assertSame(['keep'=>true],$info['unknown-key']);
        self::assertStringNotContainsString('<script>',$info['1en']);
        $_POST['cor_mgmt_nonce']='invalid';
        $this->rejects('not_permitted',fn()=>EventAdmin::mutate('race',1,'save'));
    }
    public function testActiveRaceSwitchIsUniqueAndCannotModifyArchive(): void
    {
        $this->save('race',1,['active'=>'1']);
        $this->save('race',2,['active'=>'1']);
        self::assertSame('0',$this->store->find('race',1)['active']);
        self::assertSame('1',$this->store->find('race',2)['active']);
        self::assertCount(1,$this->store->matching('race','active',1));
        $this->rejects('archived',fn()=>$this->save('race',5,['active'=>'1']));
    }
    public function testCreationValidatesPhaseParentsSizesAndStageNumbers(): void
    {
        $new=$this->editor->save('race',0,['name'=>'New race'],'');
        self::assertSame('0',EventEditor::flatten($this->store->find('race',$new['id']))['status']);
        $this->rejects('invalid_team_sizes',fn()=>$this->save('race',1,['num_teammember_min'=>'4','num_teammember'=>'2']));
        $this->rejects('structure_locked',fn()=>$this->editor->save('route',0,['race_id'=>'2','name'=>'No','max_teams'=>'2'],''));
        $this->rejects('duplicate_stage_number',fn()=>$this->editor->save('stage',0,['route_id'=>'11','destination'=>'No','number'=>'1'],''));
        $stage=$this->editor->save('stage',0,['route_id'=>'11','destination'=>'Next','number'=>'2'],'');
        self::assertSame('1',$this->store->find('stage',$stage['id'])['race_id']);
        $this->rejects('missing_field',fn()=>$this->editor->save('route',0,['race_id'=>'1'],''));
    }
    public function testDeletingUsedStructureAndMovingUsedRoutesIsRejected(): void
    {
        $this->rejects('dependent_records',fn()=>$this->editor->delete('race',[1]));
        $this->rejects('dependent_records',fn()=>$this->editor->delete('route',[11]));
        $this->rejects('structure_locked',fn()=>$this->editor->delete('stage',[22]));
        $this->editor->delete('stage',[21]);
        $this->editor->delete('route',[11]);
        $this->editor->delete('race',[1]);
        self::assertSame([],$this->store->find('race',1));
    }
    public function testMixedBulkIsValidatedBeforeDeletingAnything(): void
    {
        $this->rejects('structure_locked',fn()=>$this->editor->delete('stage',[21,22]));
        self::assertNotSame([],$this->store->find('stage',21));
    }
    public function testUnprivilegedAccountCannotUseApplicationWriter(): void
    {
        wp_set_current_user(0);
        $this->rejects('not_permitted',fn()=>$this->save('race',1,['name'=>'No']));
        self::assertSame('Synthetic 0',$this->store->find('race',1)['name']);
    }
    public function testTablesArePaginatedEscapedAndFormsDoNotExposePasswordHashes(): void
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix.'h3_mgmt_races',['name'=>'<script>evil</script>'],['id'=>1]);
        $_GET=['page'=>'h3-mgmt-races'];
        ob_start(); EventList::render('race',[]); $html=ob_get_clean();
        self::assertStringContainsString('&lt;script&gt;evil&lt;/script&gt;',$html);
        self::assertStringNotContainsString('<script>evil',$html);
        self::assertStringContainsString('Page 1 of 1 (5 records)',$html);
        self::assertStringContainsString('cor_event_revision',$html);
        foreach (['admin/class-h3-mgmt-admin-races.php','templates/class-h3-mgmt-admin-form.php','templates/class-h3-mgmt-admin-page.php'] as $file) { require_once dirname(__DIR__,2).'/'.$file; }
        $_GET=['page'=>'h3-mgmt-routes','todo'=>'edit','id'=>11];
        $wpdb->update($wpdb->prefix.'h3_mgmt_routes',['user_id'=>$this->admin],['id'=>11]);
        ob_start(); (new \H3_MGMT_Admin_Races())->edit('route',11); $html=ob_get_clean();
        self::assertStringNotContainsString(get_userdata($this->admin)->user_pass,$html);
        self::assertStringContainsString('cor_event_revision',$html);
    }
    public function testRepositoryCompareAndSwapRejectsExternalChange(): void
    {
        global $wpdb;
        $old=$this->store->find('route',11);
        $wpdb->update($wpdb->prefix.'h3_mgmt_routes',['name'=>'Outside writer'],['id'=>11]);
        $this->rejects('stale_record',fn()=>$this->store->exclusive(function()use($old){$this->store->write('route',$old,['name'=>'Lost change']);return [];},false));
        self::assertSame('Outside writer',$this->store->find('route',11)['name']);
    }
    public function testCompetingEditorCannotEnterTheSameMutationLock(): void
    {
        global $wpdb;
        $other=new \wpdb((string)getenv('COR_TEST_DB_USER'),(string)getenv('COR_TEST_DB_PASSWORD'),(string)getenv('COR_TEST_DB_NAME'),(string)getenv('COR_TEST_DB_HOST'));
        $other->set_prefix($wpdb->prefix);
        $rival=new EventEditor(new LegacyEventStore(new Database($other)),static fn()=>true);
        try {
            (new Database($wpdb))->locked(function()use($rival):void {
                try { $rival->save('race',1,['name'=>'Contended'],'untrusted'); self::fail('Expected lock contention'); }
                catch(\Cor\Mgmt\Application\Migration\MigrationException $error) { self::assertSame('migration_locked',$error->getMessage()); }
            });
            self::assertSame('Synthetic 0',$this->store->find('race',1)['name']);
        } finally { $other->close(); }
    }
    public function testActiveSelectionRollsBackIfSecondWriteFails(): void
    {
        global $wpdb;
        $this->save('race',1,['active'=>'1']);
        $filter=static function(string $query)use($wpdb):string {
            return str_starts_with($query,'UPDATE') && str_contains($query,"SET `active`='1'")
                ? "UPDATE {$wpdb->prefix}h3_mgmt_races SET name=name WHERE id=0" : $query;
        };
        add_filter('query',$filter);
        try { $this->rejects('stale_record',fn()=>$this->save('race',2,['active'=>'1'])); }
        finally { remove_filter('query',$filter); }
        self::assertSame('1',$this->store->find('race',1)['active']);
        self::assertSame('0',$this->store->find('race',2)['active']);
    }
    public function testDatesAndScoringAreIndependentOfStartedPhase(): void
    {
        $this->save('race',4,['start'=>'10','end'=>'20']);
        self::assertSame('10',$this->store->find('race',4)['start']);
        self::assertSame('3',EventEditor::flatten($this->store->find('race',4))['status']);
        $this->rejects('field_locked',fn()=>$this->save('race',4,['one_extra_point'=>'1']));
        $this->rejects('invalid_dates',fn()=>$this->save('race',4,['start'=>'21']));
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    public function testAccountProvisioningOnlyRunsAfterValidationAndConfirmation(): void
    {
        $_SERVER['REQUEST_METHOD']='POST';
        $_GET=['page'=>'h3-mgmt-routes','todo'=>'save','id'=>11];
        $_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-admin-routes'),'cor_event_revision'=>$this->editor->revision('route',11),
            'route_account_login_name'=>'synthetic_new_route','route_account_email'=>'route-new@example.invalid','route_account_pw1'=>'synthetic-password','route_account_pw2'=>'synthetic-password','max_teams'=>'-1'];
        $this->rejects('invalid_number',fn()=>EventAdmin::mutate('route',11,'save'));
        self::assertFalse(username_exists('synthetic_new_route'));
        $_POST['max_teams']='5';
        EventAdmin::mutate('route',11,'save');
        $user=username_exists('synthetic_new_route');
        self::assertIsInt($user);
        self::assertSame((string)$user,$this->store->find('route',11)['user_id']);
        self::assertTrue(user_can($user,'h3_mgmt_edit_own_races'));
        self::assertFalse(user_can($user,'h3_mgmt_edit_races'));
        self::assertContains((string)$user,array_map('strval',array_column($GLOBALS['h3_mgmt_races']->options_array(['data'=>'route_account','value'=>'ID','label'=>'user_login','orderby'=>'user_login']),'value')));
        self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    public function testMalformedDatesAndCrossRaceMovesAreRejectedWithoutWrites(): void
    {
        $_SERVER['REQUEST_METHOD']='POST';
        $_GET=['page'=>'h3-mgmt-races','todo'=>'save','id'=>1];
        $_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-admin-races'),'cor_event_revision'=>$this->editor->revision('race',1),'start'=>'2026-02-30'];
        $before=$this->store->find('race',1);
        $this->rejects('invalid_dates',fn()=>EventAdmin::mutate('race',1,'save'));
        self::assertSame($before,$this->store->find('race',1));
        $this->rejects('structure_locked',fn()=>$this->save('route',11,['race_id'=>'2']));
        $this->save('race',2,['status'=>'0']);
        $this->rejects('dependent_records',fn()=>$this->save('route',11,['race_id'=>'2']));
    }
    public function testAllThreeNewFormsRenderWithoutWritesAndArchiveFieldsStayDisabled(): void
    {
        require_once dirname(__DIR__,2).'/admin/class-h3-mgmt-admin-races.php';
        foreach (['race'=>1,'route'=>11,'stage'=>21] as $type=>$id) {
            $_GET=['page'=>'h3-mgmt-'.$type.'s','todo'=>'edit','id'=>$id];
            $writes=[];
            $capture=static function($query)use(&$writes){if(preg_match('/^(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP)\b/',$query)){$writes[]=$query;}return $query;};
            add_filter('query',$capture);
            ob_start();
            try { (new \H3_MGMT_Admin_Races())->edit($type,$id); $html=ob_get_contents(); }
            finally {ob_end_clean();remove_filter('query',$capture);}
            self::assertSame([],$writes);
            self::assertStringContainsString('cor_mgmt_nonce',$html);
            self::assertStringContainsString('aria-describedby',$html);
            if ($type === 'race') { self::assertStringContainsString('type="date"',$html); }
        }
        $_GET=['page'=>'h3-mgmt-races','todo'=>'edit','id'=>5];
        ob_start();
        try {(new \H3_MGMT_Admin_Races())->edit('race',5);$html=ob_get_contents();}
        finally {ob_end_clean();}
        self::assertMatchesRegularExpression('/name="status"[^>]*disabled/',$html);
        self::assertStringContainsString('Race ended / archive',$html);
    }

    public function testSavingAnUnchangedCalendarDatePreservesOriginalTimestamp(): void
    {
        $_SERVER['REQUEST_METHOD']='POST';
        $_GET=['page'=>'h3-mgmt-races','todo'=>'save','id'=>1];
        $_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-admin-races'),'cor_event_revision'=>$this->editor->revision('race',1),
            'start'=>wp_date('Y-m-d',1),'end'=>wp_date('Y-m-d',1000000000),'name'=>'Only the title changed'];
        EventAdmin::mutate('race',1,'save');
        self::assertSame('1',$this->store->find('race',1)['start']);
        self::assertSame('1000000000',$this->store->find('race',1)['end']);
    }

    public function testMyisamSourceRequiresControlledReviewForMultiRecordChanges(): void
    {
        $other=new \wpdb((string)getenv('COR_TEST_DB_USER'),(string)getenv('COR_TEST_DB_PASSWORD'),(string)getenv('COR_TEST_DB_NAME'),(string)getenv('COR_TEST_DB_HOST'));
        $other->set_prefix('cor_event_engine_');
        $db=new Database($other);
        $editor=new EventEditor(new LegacyEventStore($db),static fn()=>true);
        $tables=[];
        try {
            foreach(['race','route','stage','team'] as $type) {
                $table=$db->table('h3_mgmt_'.$type.'s');
                self::assertFalse($db->exists($table));
                $db->execute($db->prepare("CREATE TABLE %i (id bigint PRIMARY KEY,name varchar(255),setting text,active int) ENGINE=MyISAM",$table));
                $tables[]=$table;
            }
            $db->execute($db->prepare('INSERT INTO %i (id,name,setting,active) VALUES (1,%s,%s,0)',$tables[0],'Keep','{"status":0}'));
            $this->rejects('transactional_storage_required',fn()=>$editor->save('race',1,['active'=>'1'],$editor->revision('race',1)));
            self::assertSame('0',$db->scalar($db->prepare('SELECT active FROM %i WHERE id=1',$tables[0])));
            $editor->save('race',1,['name'=>'Safe single-record update'],$editor->revision('race',1));
            self::assertSame('Safe single-record update',$db->scalar($db->prepare('SELECT name FROM %i WHERE id=1',$tables[0])));
        } finally {
            foreach(array_reverse($tables) as $table) {$db->execute($db->prepare('DROP TABLE %i',$table));}
            $other->close();
        }
    }

    public function testStageFiltersKeepRouteNumberAndCountrySelection(): void
    {
        $_GET=['page'=>'h3-mgmt-stages','race_filter'=>'1','route_filter'=>'11','number_filter'=>'1','country_filter'=>'Missing'];
        ob_start();
        try { EventList::render('stage',[]); $html=ob_get_contents(); } finally {ob_end_clean();}
        self::assertStringContainsString('No records found.',$html);
        $_GET['country_filter']='';
        ob_start();
        try { EventList::render('stage',[]); $html=ob_get_contents(); } finally {ob_end_clean();}
        self::assertStringContainsString('Page 1 of 1 (1 records)',$html);
        self::assertStringContainsString('name="bulk[]"',$html);
        self::assertStringContainsString('route_filter=11',$html);
    }

}
