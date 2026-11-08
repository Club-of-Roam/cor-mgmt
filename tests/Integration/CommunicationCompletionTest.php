<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Infrastructure\WordPress\Broadcasts;
use Cor\Mgmt\Infrastructure\WordPress\Exchange;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\BroadcastSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ExchangeSchema;
use Cor\Mgmt\WordPress\Services;
use Cor\Mgmt\WordPress\BroadcastAdmin;
use Cor\Mgmt\WordPress\ExchangeBoundary;
use PHPUnit\Framework\TestCase;

/** Synthetic database, injected transport and real WordPress rights/forms. */
final class CommunicationCompletionTest extends TestCase
{
    private Database $db;
    private array $users=[];
    private array $saved;
    private MailTransport $transport;
    private bool $authorized=true;
    private bool $delivery=true;
    protected function setUp():void
    {
        global $wpdb;$this->db=new Database($wpdb);$this->saved=[$_GET,$_POST,$_SERVER];$_GET=$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
        foreach(['admin','one','two'] as $key){$this->users[$key]=wp_insert_user(['user_login'=>'completion_'.$key,'user_email'=>'completion-'.$key.'@example.invalid','user_pass'=>'synthetic','first_name'=>$key,'display_name'=>'Synthetic '.$key,'role'=>$key==='admin'?'administrator':'subscriber']);self::assertIsInt($this->users[$key]);}
        foreach([981,982] as $id){$wpdb->insert($this->db->table('h3_mgmt_races'),['id'=>$id,'name'=>'Synthetic event '.$id,'setting'=>'{"status":1}','information_text'=>'{}']);$wpdb->insert($this->db->table('h3_mgmt_teams'),['id'=>$id,'race_id'=>$id,'team_name'=>'Synthetic team '.$id]);}
        foreach([['one',981,'de'],['two',982,'en']] as [$key,$team,$lang]){$wpdb->insert($this->db->table('h3_mgmt_teammates'),['team_id'=>$team,'user_id'=>$this->users[$key],'language'=>$lang]);}
        $this->transport=new class implements MailTransport {public array $calls=[];public string $outcome='sent';public function send(string $recipient,string $subject,string $body,string $headers):bool{global $wpdb;if($wpdb->get_var('SELECT @@in_transaction')!=='0'){throw new \LogicException('Transport under transaction');}$this->calls[]=[$recipient,$subject,$body,$headers];if($this->outcome==='uncertain'){throw new \RuntimeException('Synthetic timeout');}return $this->outcome==='sent';}};
        wp_set_current_user($this->users['admin']);$GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown():void
    {
        $this->db->execute('ROLLBACK');
        foreach(['cor_mgmt_broadcast_history','cor_mgmt_broadcast_recipients','cor_mgmt_mail_envelopes','cor_mgmt_broadcasts'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE message_id IN (SELECT id FROM %i WHERE race_id=0)',$this->db->table('cor_mgmt_mail_history'),$this->db->table('cor_mgmt_mail_queue')));
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE race_id=0',$this->db->table('cor_mgmt_mail_queue')));
        $this->db->execute($this->db->prepare('DELETE FROM %i',$this->db->table('cor_mgmt_exchange_history')));
        foreach($this->users as $user){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE user_id=%d',$this->db->table('h3_mgmt_xchange'),$user));$this->db->wpdb->delete($this->db->wpdb->usermeta,['user_id'=>$user]);$this->db->wpdb->delete($this->db->wpdb->users,['ID'=>$user]);}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE team_id IN (981,982)',$this->db->table('h3_mgmt_teammates')));
        foreach(['h3_mgmt_teams','h3_mgmt_races'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE id IN (981,982)',$this->db->table($suffix)));}
        wp_set_current_user(0);wp_cache_flush();[$_GET,$_POST,$_SERVER]=$this->saved;self::assertSame([],$GLOBALS['cor_test_mail']);self::assertSame('',$this->db->wpdb->last_error);
    }
    private function manager():Broadcasts{$r=Services::get();return new Broadcasts($this->db,$r->clock,$r->random,$this->transport,fn(int $actor,array $selection):bool=>$this->authorized&&$actor===$this->users['admin'],fn():bool=>$this->delivery);}
    private function exchange():Exchange{return new Exchange($this->db,Services::get()->clock,fn(int $actor):bool=>$actor===$this->users['admin']);}
    private function rejected(string $reason,callable $call):void{try{$call();self::fail('Expected '.$reason);}catch(CommunicationException $e){self::assertSame($reason,$e->reason);}}
    private function create(array $selection=['mode'=>'race','id'=>'981'],string $body='Hello %name% / %team_name% / %event_name% / %link%'):int{$m=$this->manager();$p=$m->preview($this->users['admin'],$selection,'Synthetic subject',$body);self::assertSame([],$p['issues']);return $m->create($this->users['admin'],bin2hex(random_bytes(16)),$selection,'Synthetic subject',$body,$p['fingerprint']);}
    private function mutate(int $id,string $operation,int $message=0):string{$m=$this->manager();return $m->mutate($id,$this->users['admin'],$m->context($id,$this->users['admin'])['revision'],$operation,$message);}
    public function testAddonInstallationIsIdempotentAndPreservesMessages():void{$id=$this->exchange()->save($this->users['one'],0,'Synthetic',$this->exchange()->context($this->users['one'])['revision']);$i=new Installer($this->db);$i->broadcasts();$i->exchange();$i->broadcasts();$i->exchange();self::assertTrue(BroadcastSchema::ready($this->db));self::assertTrue(ExchangeSchema::ready($this->db));self::assertSame('Synthetic',$this->exchange()->context($this->users['one'],$id)['row']['message']);}
    public function testPreviewIsReadOnlyAndUsesConcreteEventLanguageAndSender():void{$m=$this->manager();$p=$m->preview($this->users['admin'],['mode'=>'race','id'=>'981','sender'=>'own'],'Hi %name%','%event_name% %team_name% %link%');self::assertSame([],$p['issues']);self::assertCount(1,$p['recipients']);self::assertSame('de',$p['recipients'][0]['language']);self::assertStringContainsString('race_id=981',$p['recipients'][0]['message']);self::assertSame('completion-admin@example.invalid',$p['sender']['email']);self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_broadcasts'))));}
    public function testChangedRecipientAfterPreviewRejectsCreation():void{$m=$this->manager();$p=$m->preview($this->users['admin'],['mode'=>'race','id'=>'981'],'Subject','Hello');$this->db->wpdb->update($this->db->wpdb->users,['user_email'=>'changed@example.invalid'],['ID'=>$this->users['one']]);clean_user_cache($this->users['one']);$this->rejected('stale_broadcast_preview',fn()=>$m->create($this->users['admin'],str_repeat('a',32),$p['selection'],'Subject','Hello',$p['fingerprint']));}
    public function testRequestReplayReturnsSameCampaignAndDifferentFingerprintConflicts():void{$m=$this->manager();$p=$m->preview($this->users['admin'],['mode'=>'self'],'Subject','Hello');$call=fn()=>$m->create($this->users['admin'],str_repeat('b',32),$p['selection'],'Subject','Hello',$p['fingerprint']);$id=$call();self::assertSame($id,$call());$this->rejected('request_conflict',fn()=>$m->create($this->users['admin'],str_repeat('b',32),$p['selection'],'Subject','Other',str_repeat('c',64)));}
    public function testIndependentQueueStoresImmutableMessagesAndSender():void{$id=$this->create(['mode'=>'race','id'=>'981','sender'=>'own']);$this->db->wpdb->update($this->db->wpdb->users,['user_email'=>'changed@example.invalid'],['ID'=>$this->users['one']]);update_user_meta($this->users['one'],'first_name','Changed');clean_user_cache($this->users['one']);$this->mutate($id,'queue');$c=$this->manager()->context($id,$this->users['admin']);$row=$c['rows'][0];self::assertSame('completion-one@example.invalid',$row['recipient']);self::assertStringContainsString('Hello one',$row['message']);$this->mutate($id,'enable');self::assertSame('sent',$this->mutate($id,'send',(int)$row['queue_id']));self::assertStringContainsString('completion-admin@example.invalid',$this->transport->calls[0][3]);self::assertSame('completion-one@example.invalid',$this->transport->calls[0][0]);self::assertSame('sent',$this->manager()->context($id,$this->users['admin'])['rows'][0]['status']);}
    public function testQueuePreparationDoesNotDuplicateAndStaleRevisionRejectsReplay():void{$id=$this->create();$revision=$this->manager()->context($id,$this->users['admin'])['revision'];$this->mutate($id,'queue');$this->rejected('stale_campaign',fn()=>$this->manager()->mutate($id,$this->users['admin'],$revision,'queue'));$this->mutate($id,'queue');self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=0',$this->db->table('cor_mgmt_mail_queue'))));}
    public function testPausedServerGateAndLostPermissionsNeverSend():void{$id=$this->create();$this->mutate($id,'queue');$q=(int)$this->manager()->context($id,$this->users['admin'])['rows'][0]['queue_id'];$this->rejected('mail_delivery_disabled',fn()=>$this->mutate($id,'send',$q));$this->mutate($id,'enable');$this->delivery=false;$this->rejected('mail_delivery_disabled',fn()=>$this->mutate($id,'send',$q));$this->authorized=false;$this->rejected('forbidden',fn()=>$this->manager()->context($id,$this->users['admin']));self::assertSame([],$this->transport->calls);}
    public function testMessageCannotBeSentThroughAnotherCampaign():void{$a=$this->create();$b=$this->create();$this->mutate($a,'queue');$q=(int)$this->manager()->context($a,$this->users['admin'])['rows'][0]['queue_id'];$this->mutate($b,'enable');$this->rejected('missing_campaign_message',fn()=>$this->mutate($b,'send',$q));self::assertSame([],$this->transport->calls);}
    public function testFailedAndAmbiguousOutcomesNeedExplicitDecisions():void{$id=$this->create();$this->mutate($id,'queue');$this->mutate($id,'enable');$q=(int)$this->manager()->context($id,$this->users['admin'])['rows'][0]['queue_id'];$this->transport->outcome='failed';self::assertSame('failed',$this->mutate($id,'send',$q));$this->mutate($id,'retry',$q);$this->transport->outcome='uncertain';self::assertSame('uncertain',$this->mutate($id,'send',$q));$this->rejected('mail_retry_not_allowed',fn()=>$this->mutate($id,'retry',$q));$this->mutate($id,'confirm-delivered',$q);self::assertSame('sent',$this->manager()->context($id,$this->users['admin'])['rows'][0]['status']);self::assertCount(2,$this->transport->calls);}
    public function testLanguageFilterAndMissingPersonalizationAreExplicit():void{$m=$this->manager();$p=$m->preview($this->users['admin'],['mode'=>'race','id'=>'981','language'=>'en'],'Subject','Hello');self::assertContains('empty_audience',$p['issues']);$p=$m->preview($this->users['admin'],['mode'=>'email','email'=>'explicit@example.invalid'],'Subject','%name%');self::assertContains('missing_placeholder:0',$p['issues']);$p=$m->preview($this->users['admin'],['mode'=>'email','email'=>'explicit@example.invalid'],'Subject','Literal text');self::assertSame([],$p['issues']);self::assertSame('explicit@example.invalid',$p['recipients'][0]['recipient']);}
    public function testSharedAddressesAreDeduplicatedOnlyForIdenticalContent():void{$this->db->execute($this->db->prepare('UPDATE %i SET user_email=%s WHERE ID=%d',$this->db->wpdb->users,'completion-one@example.invalid',$this->users['two']));clean_user_cache($this->users['two']);$this->db->execute($this->db->prepare('UPDATE %i SET team_id=981,language=%s WHERE user_id=%d',$this->db->table('h3_mgmt_teammates'),'de',$this->users['two']));$m=$this->manager();$p=$m->preview($this->users['admin'],['mode'=>'race','id'=>'981'],'Subject','Same text');self::assertSame([],$p['issues']);self::assertCount(1,$p['recipients']);$p=$m->preview($this->users['admin'],['mode'=>'race','id'=>'981'],'Subject','Hello %name%');self::assertContains('conflicting_shared_address:'.$this->users['two'],$p['issues']);}
    public function testAllParticipantsDeduplicatesRepeatedMemberships():void{$this->db->wpdb->insert($this->db->table('h3_mgmt_teammates'),['team_id'=>982,'user_id'=>$this->users['one'],'language'=>'en']);$p=$this->manager()->preview($this->users['admin'],['mode'=>'all'],'Subject','Hello %name%');self::assertSame([],$p['issues']);self::assertCount(2,$p['recipients']);}
    public function testBatchStopsOnFailureAndDoesNotRetryAutomatically():void{$id=$this->create(['mode'=>'all'],'Hello');$this->mutate($id,'queue');$this->mutate($id,'enable');$this->transport->outcome='failed';self::assertSame(0,$this->manager()->sendBatch($id,$this->users['admin'],$this->manager()->context($id,$this->users['admin'])['revision']));self::assertCount(1,$this->transport->calls);$rows=$this->manager()->context($id,$this->users['admin'])['rows'];self::assertSame(['failed','pending'],array_column($rows,'status'));}
    public function testArchiveDoesNotChangeWhenExplicitCampaignContactsFormerParticipants():void{$this->db->execute($this->db->prepare('UPDATE %i SET setting=%s WHERE id=981',$this->db->table('h3_mgmt_races'),'{"status":4}'));$before=$this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=981',$this->db->table('h3_mgmt_races')));$id=$this->create();$this->mutate($id,'queue');self::assertSame($before,$this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=981',$this->db->table('h3_mgmt_races'))));}
    public function testManualBoundaryRequiresNonceAndConfirmation():void{$_SERVER['REQUEST_METHOD']='POST';$this->rejected('invalid_nonce',fn()=>BroadcastAdmin::submit());$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-broadcast'),'broadcast_operation'=>'send-batch'];$this->rejected('confirmation_required',fn()=>BroadcastAdmin::submit());wp_set_current_user($this->users['one']);$this->rejected('forbidden',fn()=>BroadcastAdmin::manager()->preview($this->users['one'],['mode'=>'all'],'S','M'));}
    public function testPublicExchangeCreatesUpdatesDeletesWithRevisionAndAudit():void{$x=$this->exchange();$user=$this->users['one'];$c=$x->context($user);$id=$x->save($user,0,'First',$c['revision']);self::assertGreaterThan(0,$id);$c=$x->context($user,$id);$x->save($user,$id,'Second',$c['revision']);$this->rejected('stale_exchange',fn()=>$x->save($user,$id,'Overwrite',$c['revision']));$x->save($user,$id,'',$x->context($user,$id)['revision']);self::assertSame([],$x->context($user)['row']);self::assertCount(3,$x->history($this->users['admin'],$id));}
    public function testExchangeRejectsForeignWriterAndModeratorMustBeExplicit():void{$x=$this->exchange();$id=$x->save($this->users['one'],0,'Owner',$x->context($this->users['one'])['revision']);$this->rejected('forbidden',fn()=>$x->context($this->users['two'],$id));$this->rejected('forbidden',fn()=>$x->context($this->users['admin'],$id));$c=$x->context($this->users['admin'],$id,true);$x->save($this->users['admin'],$id,'Moderated',$c['revision'],true);self::assertSame('Moderated',$x->context($this->users['one'],$id)['row']['message']);$this->rejected('forbidden',fn()=>$x->messages(0,1,true,$this->users['two']));}
    public function testExchangeDoesNotCreateSecondOwnMessageFromStaleForm():void{$x=$this->exchange();$u=$this->users['one'];$revision=$x->context($u)['revision'];$x->save($u,0,'One',$revision);$this->rejected('stale_exchange',fn()=>$x->save($u,0,'Two',$revision));}
    public function testExchangeActiveRaceAndYearFilteringPreservesGlobalRecords():void{$x=$this->exchange();$u=$this->users['one'];$id=$x->save($u,0,'Global',$x->context($u)['revision']);self::assertSame([],$x->messages(981));self::assertSame([(string)$id],array_column($x->messages(982),'id'));$this->db->execute($this->db->prepare('UPDATE %i SET time=%s WHERE id=%d',$this->db->table('h3_mgmt_xchange'),'2001-06-01 12:00:00',$id));self::assertSame([],$x->messages(982));self::assertCount(1,$x->messages(0,1,true,$this->users['admin']));}
    public function testExchangeNonceHoneypotAndOwnFormAreNative():void{wp_set_current_user($this->users['one']);$_SERVER['REQUEST_METHOD']='POST';$this->rejected('invalid_nonce',fn()=>ExchangeBoundary::submit());$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-xchange'),'address'=>'bot'];$this->rejected('invalid_nonce',fn()=>ExchangeBoundary::submit());$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$html=ExchangeBoundary::shortcode();self::assertStringContainsString('name="revision"',$html);self::assertStringContainsString('name="message_id"',$html);self::assertStringNotContainsString('avia-',$html);self::assertStringNotContainsString('frontend-form.php',$html);}
    public function testExchangeMessageIsEscapedAndLengthIsBounded():void{$x=$this->exchange();$u=$this->users['one'];$this->rejected('invalid_exchange_message',fn()=>$x->save($u,0,str_repeat('x',10001),$x->context($u)['revision']));$x->save($u,0,'<script>alert(1)</script>',$x->context($u)['revision']);wp_set_current_user($u);$html=ExchangeBoundary::shortcode();self::assertStringNotContainsString('<script>alert(1)</script>',$html);self::assertStringContainsString('&lt;script&gt;',$html);}
    public function testExchangeTransactionalRequirementDoesNotAlterEngine():void{$table=$this->db->table('h3_mgmt_xchange');try{$this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=MyISAM',$table));$this->rejected('transactional_exchange_storage_required',fn()=>$this->exchange()->save($this->users['one'],0,'Rejected',$this->exchange()->context($this->users['one'])['revision']));self::assertSame('MyISAM',$this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)));}finally{$this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=InnoDB',$table));}}
    public function testQueueAndDeliveryBatchesAreBounded():void
    {
        $id=$this->create();$context=$this->manager()->context($id,$this->users['admin']);$row=$context['rows'][0];
        for($n=0;$n<104;$n++){$this->db->execute($this->db->prepare('INSERT INTO %i (broadcast_id,recipient_key,user_id,recipient,language,subject,message,headers) VALUES (%d,%s,0,%s,%s,%s,%s,%s)',$this->db->table('cor_mgmt_broadcast_recipients'),$id,hash('sha256','synthetic-batch-'.$n),'batch-'.$n.'@example.invalid','de','Synthetic','Body',$row['headers']));}
        $this->mutate($id,'queue');self::assertSame('100',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE broadcast_id=%d AND queue_id>0',$this->db->table('cor_mgmt_broadcast_recipients'),$id)));
        $this->mutate($id,'queue');self::assertSame('105',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE broadcast_id=%d AND queue_id>0',$this->db->table('cor_mgmt_broadcast_recipients'),$id)));
        $this->mutate($id,'enable');self::assertSame(5,$this->manager()->sendBatch($id,$this->users['admin'],$this->manager()->context($id,$this->users['admin'])['revision']));self::assertCount(5,$this->transport->calls);
    }
    public function testQueueBatchRollsBackIfOneEnvelopeIsInvalid():void
    {
        $id=$this->create(['mode'=>'all'],'Hello');$rows=$this->manager()->context($id,$this->users['admin'])['rows'];
        $this->db->execute($this->db->prepare('UPDATE %i SET headers=%s WHERE id=%d',$this->db->table('cor_mgmt_broadcast_recipients'),'Invalid',(int)$rows[1]['id']));
        $this->rejected('invalid_manual_snapshot',fn()=>$this->mutate($id,'queue'));
        self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=0',$this->db->table('cor_mgmt_mail_queue'))));
        self::assertSame(['0','0'],array_column($this->manager()->context($id,$this->users['admin'])['rows'],'queue_id'));
    }
    public function testExchangeDeleteAndRecreateCannotReplayAnOldEmptyForm():void
    {
        $x=$this->exchange();$u=$this->users['one'];$original=$x->context($u)['revision'];$id=$x->save($u,0,'First',$original);$x->save($u,$id,'',$x->context($u,$id)['revision']);$this->rejected('stale_exchange',fn()=>$x->save($u,0,'Replay',$original));
    }
    public function testReadOnlyStorageReportRequiresAdministratorAndDoesNotExposeContents():void
    {
        $this->create();$rows=\Cor\Mgmt\WordPress\CommunicationReport::data();self::assertNotEmpty($rows);self::assertSame(['module','count','bytes','oldest'],array_keys($rows[0]));wp_set_current_user($this->users['one']);$this->rejected('forbidden',fn()=>\Cor\Mgmt\WordPress\CommunicationReport::data());
    }

    public function testOwnFormRevisionWorksForTheRenderedMessageId():void
    {
        $x=$this->exchange();$u=$this->users['one'];$id=$x->save($u,0,'First',$x->context($u)['revision']);$form=$x->context($u);self::assertSame($form['revision'],$x->context($u,$id)['revision']);$x->save($u,(int)$form['row']['id'],'Edited through own form',$form['revision']);self::assertSame('Edited through own form',$x->context($u)['row']['message']);
    }

    public function testPreviewRejectsEmptyRenderedContentBeforeCreatingCampaign():void
    {
        update_user_meta($this->users['admin'],'first_name','');$p=$this->manager()->preview($this->users['admin'],['mode'=>'self'],'%name%','Body');self::assertContains('invalid_rendered_template:'.$this->users['admin'],$p['issues']);self::assertSame([],$p['recipients']);
    }

    public function testStepProgressStopsAfterTransportFailureAndCannotBypassGate():void
    {
        $id=$this->create(['mode'=>'all'],'Hello');$m=$this->manager();$actor=$this->users['admin'];$step=$m->step($id,$actor,$m->context($id,$actor)['revision'],'queue');self::assertTrue($step['done']);self::assertFalse($step['stop']);self::assertSame(0,$step['progress']['unprepared']);self::assertSame(2,$step['progress']['pending']);
        $this->rejected('mail_delivery_disabled',fn()=>$m->step($id,$actor,$step['revision'],'send-batch'));$this->mutate($id,'enable');$this->transport->outcome='failed';$step=$m->step($id,$actor,$m->context($id,$actor)['revision'],'send-batch');self::assertTrue($step['stop']);self::assertFalse($step['done']);self::assertSame(1,$step['progress']['failed']);self::assertSame(1,$step['progress']['pending']);self::assertCount(1,$this->transport->calls);
    }
    public function testStepBoundaryChecksNonceConfirmationAndFreshRevision():void
    {
        $id=$this->create();self::assertNotFalse(has_action('wp_ajax_cor_mgmt_broadcast_step',[BroadcastAdmin::class,'ajax']));self::assertFalse(has_action('wp_ajax_nopriv_cor_mgmt_broadcast_step'));$_SERVER['REQUEST_METHOD']='POST';$this->rejected('invalid_nonce',fn()=>BroadcastAdmin::step());$_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-broadcast'),'broadcast_operation'=>'send-batch'];$this->rejected('confirmation_required',fn()=>BroadcastAdmin::step());
        $_POST=['cor_mgmt_nonce'=>wp_create_nonce('cor-mgmt-broadcast'),'broadcast_operation'=>'queue','campaign_id'=>(string)$id,'revision'=>BroadcastAdmin::manager()->context($id,$this->users['admin'])['revision']];$result=BroadcastAdmin::step();self::assertTrue($result['done']);self::assertStringContainsString('Pending: 1',$result['message']);$this->rejected('stale_campaign',fn()=>BroadcastAdmin::step());
    }
    public function testDanglingQueueReferenceStopsInsteadOfLooping():void
    {
        $id=$this->create();$this->db->execute($this->db->prepare('UPDATE %i SET queue_id=999999999 WHERE broadcast_id=%d',$this->db->table('cor_mgmt_broadcast_recipients'),$id));$this->rejected('invalid_campaign_queue_reference',fn()=>$this->manager()->progress($id,$this->users['admin']));$other=$this->create();$this->mutate($other,'queue');$foreign=(int)$this->manager()->context($other,$this->users['admin'])['rows'][0]['queue_id'];$this->db->execute($this->db->prepare('UPDATE %i SET queue_id=%d WHERE broadcast_id=%d',$this->db->table('cor_mgmt_broadcast_recipients'),$foreign,$id));$this->rejected('invalid_campaign_queue_reference',fn()=>$this->manager()->progress($id,$this->users['admin']));$this->mutate($id,'enable');$this->rejected('missing_campaign_message',fn()=>$this->mutate($id,'send',$foreign));self::assertSame([],$this->transport->calls);
    }

}
