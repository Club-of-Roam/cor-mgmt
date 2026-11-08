<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Infrastructure\WordPress\ResponseTemplates;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\CommunicationAdmin;
use PHPUnit\Framework\TestCase;

final class ResponseTemplatesTest extends TestCase
{
    private Database $db;
    private ResponseTemplates $templates;
    private array $saved;
    protected function setUp():void
    {
        global $wpdb;$wpdb->query('START TRANSACTION');wp_cache_flush();$this->db=new Database($wpdb);$this->saved=[$_GET,$_POST,$_SERVER];$_GET=$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
        foreach([301,302] as $race){self::assertSame(1,$wpdb->insert($this->db->table('h3_mgmt_races'),['id'=>$race,'name'=>'Synthetic event '.$race,'setting'=>json_encode(['status'=>1]),'active'=>$race===301?1:0]));}
        $clock=new class implements Clock {public function now():int{return 1794120000;}};
        $this->templates=new ResponseTemplates($this->db,$clock,static fn(int $race,int $actor,string $operation):bool=>$actor===42||($actor===99&&$race===302&&$operation!=='legacy'));
        $GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown():void
    {
        global $wpdb;$error=$wpdb->last_error;$wpdb->query('ROLLBACK');wp_cache_flush();wp_set_current_user(0);[$_GET,$_POST,$_SERVER]=$this->saved;self::assertSame('',$error);self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function save(int $race,string $subject='Subject',bool $enabled=true,string $language='en'):void
    {
        $context=$this->templates->context($race,42);$this->templates->save($race,42,new AutoResponseTemplate('team-creation',$language,$enabled,$subject,'Hello %team_name%'),$context['revision']);
    }
    private function rejected(string $reason,callable $call):void
    {
        try{$call();self::fail('Expected '.$reason);}catch(CommunicationException $error){self::assertSame($reason,$error->reason);}
    }
    private function globalTemplate(array $values=[]):void
    {
        self::assertSame(1,$GLOBALS['wpdb']->insert($this->db->table('h3_mgmt_auto_responses'),$values+['action'=>'team-creation','language'=>'en','switch'=>1,'subject'=>'Old %team_name%','message'=>'Hello %name%']));
    }
    public function testEventsHaveIndependentTemplatesAndSwitchesDespiteActiveRaceChanges():void
    {
        $this->save(301,'Event A');$this->save(302,'Event B',false);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['active'=>0],['id'=>301]);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['active'=>1],['id'=>302]);
        self::assertSame('Event A',$this->templates->resolve(301,'team-creation','en')->subject);self::assertFalse($this->templates->resolve(302,'team-creation','en')->enabled);self::assertSame('Event B',$this->templates->resolve(302,'team-creation','en')->subject);
    }
    public function testMissingEventOrLanguageNeverFallsBackToGlobalOrOtherEvent():void
    {
        $this->globalTemplate();$this->save(301);self::assertNull($this->templates->resolve(302,'team-creation','en'));self::assertNull($this->templates->resolve(301,'team-creation','de'));self::assertNull($this->templates->resolve(999,'team-creation','en'));
    }
    public function testCanonicalLanguageUpdatesOneRowAndIdenticalRepeatHasNoExtraHistory():void
    {
        $this->save(301,'Deutsch',true,'de-DE');$before=$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_communication_history')));$this->save(301,'Deutsch',true,'de_de');self::assertSame($before,$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_communication_history'))));self::assertCount(1,$this->templates->context(301,42)['templates']);
    }
    public function testStaleRevisionAndUnauthorizedEventChangesAreRejected():void
    {
        $revision=$this->templates->context(301,42)['revision'];$this->save(301,'First');$this->rejected('stale_templates',fn()=>$this->templates->save(301,42,new AutoResponseTemplate('team-creation','en',true,'Second','Body'),$revision));$this->rejected('forbidden',fn()=>$this->templates->context(301,99));$this->rejected('forbidden',fn()=>$this->templates->save(301,99,new AutoResponseTemplate('team-creation','en',true,'Second','Body'),$revision));
    }
    public function testArchiveReadableButNotWritableAndInvalidEventsAreRejected():void
    {
        $this->save(301);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode(['status'=>4])],['id'=>301]);$context=$this->templates->context(301,42);self::assertFalse($context['writable']);$this->rejected('archive_locked',fn()=>$this->templates->save(301,42,new AutoResponseTemplate('team-creation','en',false,'',''),$context['revision']));$this->rejected('invalid_event',fn()=>$this->templates->context(999,42));
    }
    public function testCopyFromEventIsIndependentAndNeverOverwritesEditedTarget():void
    {
        $this->save(301,'Source');$preview=$this->templates->previewTransfer(302,301,42);self::assertSame('1',$this->templates->transfer(302,301,42,$preview['fingerprint'])['created']);$this->save(302,'Independent');$this->save(301,'Changed source');$again=$this->templates->previewTransfer(302,301,42);self::assertSame('keep_existing',$again['rows'][0]['status']);self::assertSame('0',$this->templates->transfer(302,301,42,$again['fingerprint'])['created']);self::assertSame('Independent',$this->templates->resolve(302,'team-creation','en')->subject);
    }
    public function testGlobalImportRequiresPreviewAndPreservesOriginalBytesAndSwitches():void
    {
        $this->globalTemplate();$this->globalTemplate(['language'=>'de','switch'=>0,'subject'=>'Deutsch','message'=>'Text']);$source=$this->db->rows($this->db->prepare('SELECT * FROM %i ORDER BY id',$this->db->table('h3_mgmt_auto_responses')));$preview=$this->templates->previewTransfer(301,0,42);self::assertSame([],$preview['issues']);self::assertCount(2,$preview['rows']);self::assertSame('2',$this->templates->transfer(301,0,42,$preview['fingerprint'])['created']);self::assertFalse($this->templates->resolve(301,'team-creation','de')->enabled);self::assertSame($source,$this->db->rows($this->db->prepare('SELECT * FROM %i ORDER BY id',$this->db->table('h3_mgmt_auto_responses'))));self::assertSame('0',$this->templates->transfer(301,0,42,$this->templates->previewTransfer(301,0,42)['fingerprint'])['created']);
    }
    public function testDuplicateOrIncompleteLegacyTemplatesBlockWholeImport():void
    {
        $this->globalTemplate();$this->globalTemplate(['action'=>'invitation','subject'=>'']);$preview=$this->templates->previewTransfer(301,0,42);self::assertNotEmpty($preview['issues']);$this->rejected('source_review_required',fn()=>$this->templates->transfer(301,0,42,$preview['fingerprint']));self::assertSame([],$this->templates->context(301,42)['templates']);
        $GLOBALS['wpdb']->delete($this->db->table('h3_mgmt_auto_responses'),['action'=>'invitation']);$this->globalTemplate();$preview=$this->templates->previewTransfer(301,0,42);self::assertStringContainsString('duplicate_source_template',implode(',',$preview['issues']));$this->rejected('source_review_required',fn()=>$this->templates->transfer(301,0,42,$preview['fingerprint']));
    }
    public function testLanguageAliasDuplicatesCannotBeImportedArbitrarily():void
    {
        $this->globalTemplate(['language'=>'de-DE']);$this->globalTemplate(['language'=>'de_de']);$preview=$this->templates->previewTransfer(301,0,42);self::assertCount(2,$preview['issues']);self::assertSame([],$preview['rows']);
    }
    public function testChangingSourceOrTargetInvalidatesPreview():void
    {
        $this->globalTemplate();$preview=$this->templates->previewTransfer(301,0,42);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_auto_responses'),['subject'=>'Changed'],['action'=>'team-creation']);$this->rejected('stale_transfer',fn()=>$this->templates->transfer(301,0,42,$preview['fingerprint']));$preview=$this->templates->previewTransfer(301,0,42);$this->save(301);$this->rejected('stale_transfer',fn()=>$this->templates->transfer(301,0,42,$preview['fingerprint']));
    }
    public function testGlobalAndForeignSourceReadPermissionsAreChecked():void
    {
        $this->rejected('forbidden',fn()=>$this->templates->previewTransfer(302,0,99));$this->rejected('forbidden',fn()=>$this->templates->previewTransfer(302,301,99));$this->rejected('invalid_source',fn()=>$this->templates->previewTransfer(301,301,42));
    }
    public function testJournalFailureRollsBackAllImportedRows():void
    {
        $this->globalTemplate();$preview=$this->templates->previewTransfer(301,0,42);$filter=function(string $sql):string{if(str_starts_with($sql,'INSERT INTO `'.$this->db->table('cor_mgmt_communication_history').'`')){throw new \RuntimeException('injected_failure');}return $sql;};add_filter('query',$filter);
        try{try{$this->templates->transfer(301,0,42,$preview['fingerprint']);self::fail('Expected failure');}catch(\RuntimeException $error){self::assertSame('injected_failure',$error->getMessage());}}finally{remove_filter('query',$filter);}self::assertSame([],$this->templates->context(301,42)['templates']);
    }
    public function testJournalRecordsActorAndBeforeAfterForTemplateChanges():void
    {
        $this->save(301,'Before');$this->save(301,'After');$row=$this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=301 ORDER BY id DESC LIMIT 1',$this->db->table('cor_mgmt_communication_history')))[0];$payload=json_decode($row['payload'],true);self::assertSame('42',$row['actor_id']);self::assertSame('Before',$payload['old']['subject']);self::assertSame('After',$payload['new']['subject']);
    }
    public function testNativeBoundaryNeedsNonceEventRightsAndEscapesStoredContent():void
    {
        $admin=wp_insert_user(['user_login'=>'synthetic_response_admin','user_email'=>'response-admin@example.invalid','user_pass'=>'synthetic','role'=>'administrator']);wp_set_current_user($admin);$manager=CommunicationAdmin::manager();$revision=$manager->context(301,$admin)['revision'];$_SERVER['REQUEST_METHOD']='POST';$_POST=['response_operation'=>'save','race_id'=>'301','template_action'=>'team-creation','language'=>'en','enabled'=>'1','subject'=>'Event A','message'=>'<script>not executable</script> %team_name%','revision'=>$revision];$this->rejected('invalid_nonce',fn()=>CommunicationAdmin::submit());$_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-responses');$_SERVER['REQUEST_METHOD']='GET';$this->rejected('invalid_nonce',fn()=>CommunicationAdmin::submit());$_SERVER['REQUEST_METHOD']='POST';CommunicationAdmin::submit();$_SERVER['REQUEST_METHOD']='GET';$_POST=[];$_GET=['race_id'=>'301'];ob_start();try{CommunicationAdmin::page();$html=ob_get_contents();}finally{ob_end_clean();}self::assertStringContainsString('now control queued team notifications',$html);self::assertStringContainsString('Synthetic event 301',$html);self::assertStringContainsString('&lt;script&gt;',$html);self::assertStringNotContainsString('<script>not executable',$html);self::assertNull($manager->resolve(302,'team-creation','en'));
    }
    public function testRaceConfigurationChangeInvalidatesSaveAndReadDoesNotWrite():void
    {
        $context=$this->templates->context(301,42);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['name'=>'Changed'],['id'=>301]);$this->rejected('stale_templates',fn()=>$this->templates->save(301,42,new AutoResponseTemplate('team-creation','en',true,'Subject','Body'),$context['revision']));
        $queries=[];$capture=static function(string $sql)use(&$queries):string{$queries[]=$sql;return $sql;};add_filter('query',$capture);try{$this->templates->context(301,42);$this->templates->resolve(301,'team-creation','en');$this->templates->previewTransfer(301,0,42);}finally{remove_filter('query',$capture);}
        self::assertSame([],array_values(array_filter($queries,static fn(string $sql):bool=>preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP)\b/i',$sql)===1)));
    }
    public function testArchivedSourceCanBeCopiedWithoutBeingChanged():void
    {
        $this->save(301,'Archived source');$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode(['status'=>4])],['id'=>301]);$original=$this->templates->context(301,42);$preview=$this->templates->previewTransfer(302,301,42);self::assertSame('1',$this->templates->transfer(302,301,42,$preview['fingerprint'])['created']);self::assertSame($original,$this->templates->context(301,42));
    }
    public function testMissingModuleFailsClosedInsteadOfCreatingSchemaDuringRead():void
    {
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE option_name=%s',$GLOBALS['wpdb']->options,'cor_mgmt_communication_install'));$this->rejected('communication_setup_required',fn()=>$this->templates->resolve(301,'team-creation','en'));self::assertNull($this->db->option('cor_mgmt_communication_install'));$admin=wp_insert_user(['user_login'=>'synthetic_missing_response_admin','user_email'=>'missing-response@example.invalid','user_pass'=>'synthetic','role'=>'administrator']);wp_set_current_user($admin);ob_start();try{CommunicationAdmin::page();$html=ob_get_contents();}finally{ob_end_clean();}self::assertStringContainsString('Set up communication storage',$html);
    }
    public function testEditorWithoutEventRightsCannotUseNativeBoundary():void
    {
        $user=wp_insert_user(['user_login'=>'synthetic_mail_editor','user_email'=>'limited@example.invalid','user_pass'=>'synthetic','role'=>'subscriber']);$account=new \WP_User($user);$account->add_cap('h3_mgmt_edit_autoresponses');wp_set_current_user($user);$_SERVER['REQUEST_METHOD']='POST';$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-responses'),'race_id'=>'301','response_operation'=>'save','template_action'=>'team-creation','language'=>'en','subject'=>'Subject','message'=>'Message','revision'=>$this->templates->context(301,42)['revision']];$this->rejected('forbidden',fn()=>CommunicationAdmin::submit());
    }

}
