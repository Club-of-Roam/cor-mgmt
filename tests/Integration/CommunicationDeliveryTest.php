<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Infrastructure\WordPress\MailQueueManagement;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\MailQueueAdmin;
use Cor\Mgmt\WordPress\TeamBoundary;
use Cor\Mgmt\WordPress\TeamPortal;
use Cor\Mgmt\WordPress\Services;
use PHPUnit\Framework\TestCase;

final class CommunicationDeliveryTest extends TestCase
{
    private Database $db;
    private array $users=[];
    private array $saved;
    private MailTransport $transport;
    protected function setUp():void
    {
        global $wpdb;$this->db=new Database($wpdb);$this->saved=[$_GET,$_POST,$_SERVER];$_GET=$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
        foreach(['admin','one','two'] as $key){$this->users[$key]=wp_insert_user(['user_login'=>'queue_delivery_'.$key,'user_email'=>'queue-delivery-'.$key.'@example.invalid','user_pass'=>'synthetic','first_name'=>$key==='one'?'Alpha':'Beta','last_name'=>'Person','role'=>$key==='admin'?'administrator':'subscriber']);self::assertIsInt($this->users[$key]);}
        foreach([951,952] as $race){self::assertSame(1,$wpdb->insert($this->db->table('h3_mgmt_races'),['id'=>$race,'name'=>'Synthetic event '.$race,'setting'=>json_encode(['status'=>1,'num_teammember_min'=>2,'num_teammember'=>2,'startingpoint'=>1,'dis_fee'=>1,'dis_waiver'=>1,'dis_shirt_size'=>1,'dis_mobile_inf'=>1]),'information_text'=>'{}']));}
        $this->transport=new class implements MailTransport {public array $calls=[];public string $outcome='sent';public function send(string $recipient,string $subject,string $body,string $headers):bool{$this->calls[]=[$recipient,$subject,$body,$headers];if($this->outcome==='uncertain'){throw new \RuntimeException('Synthetic provider timeout');}return $this->outcome==='sent';}};
        $GLOBALS['cor_test_mail']=[];wp_set_current_user($this->users['one']);
    }
    protected function tearDown():void
    {
        $this->db->execute('ROLLBACK');wp_set_current_user(0);
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE message_id IN (SELECT id FROM %i WHERE race_id IN (951,952))',$this->db->table('cor_mgmt_mail_history'),$this->db->table('cor_mgmt_mail_queue')));
        foreach(['h3_mgmt_teammates','h3_mgmt_invitations','cor_mgmt_invitation_tokens'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE team_id IN (SELECT id FROM %i WHERE race_id IN (951,952))',$this->db->table($suffix),$this->db->table('h3_mgmt_teams')));}
        foreach(['cor_mgmt_mail_queue','cor_mgmt_response_templates','cor_mgmt_communication_history','cor_mgmt_membership_history','h3_mgmt_teams'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE race_id IN (951,952)',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id IN (951,952)',$this->db->table('h3_mgmt_races')));
        foreach([951,952] as $race){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE option_name=%s',$this->db->wpdb->options,'cor_mgmt_mail_delivery_'.$race));}
        foreach($this->users as $user){$this->db->wpdb->delete($this->db->wpdb->usermeta,['user_id'=>$user]);$this->db->wpdb->delete($this->db->wpdb->users,['ID'=>$user]);}
        wp_cache_flush();[$_GET,$_POST,$_SERVER]=$this->saved;self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function rejected(string $reason,callable $call):void{try{$call();self::fail('Expected '.$reason);}catch(CommunicationException $error){self::assertSame($reason,$error->reason);}}
    private function templates(int $race=951):void
    {
        foreach(['team-creation','invitation','invitation-accepted-inviter','invitation-accepted-invitee','publishable'] as $action){foreach(['en','de'] as $language){$this->db->execute($this->db->prepare('INSERT INTO %i (race_id,action,language,enabled,subject,message,revision,updated_at) VALUES (%d,%s,%s,1,%s,%s,1,1)',$this->db->table('cor_mgmt_response_templates'),$race,$action,$language,$race.'-'.$action.'-'.$language,'%name% / %inviter% / %invitee% / %team_name% / %event_name% / %link%'));}}
    }
    private function create(int $race=951,array $emails=[]):array{return TeamBoundary::manager()->save($race,$this->users['one'],['team_name'=>'Synthetic team'],['language'=>'en'],'',$emails);}
    private function rows(int $race=951):array{return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d ORDER BY id',$this->db->table('cor_mgmt_mail_queue'),$race));}
    private function manager(bool $delivery=true):MailQueueManagement
    {
        $runtime=Services::get();return new MailQueueManagement($this->db,$runtime->clock,$runtime->random,$this->transport,fn(int $race,int $actor,string $operation):bool=>$actor===$this->users['admin']&&$race===951,fn():bool=>$delivery);
    }
    private function mutate(MailQueueManagement $manager,string $operation,int $id=0):string{return $manager->mutate(951,$this->users['admin'],$manager->context(951,$this->users['admin'])['revision'],$operation,$id);}
    public function testCompleteTeamWorkflowSnapshotsEachRecipientLanguageAndEventLink():void
    {
        $this->templates();$result=$this->create(951,['invitee@example.invalid']);self::assertCount(2,$this->rows());$invitation=$this->rows()[1];$values=json_decode($invitation['context'],true);self::assertSame('Alpha',$values['inviter']);self::assertSame('Alpha',$values['name']);self::assertStringContainsString('race_id=951',$values['link']);self::assertStringContainsString('action=cor_mgmt_team',$values['link']);self::assertStringNotContainsString('tramprennen.org',$values['link']);
        wp_set_current_user($this->users['two']);TeamBoundary::manager()->save(951,$this->users['two'],[],['language'=>'de'],$values['code']);$rows=$this->rows();self::assertCount(6,$rows);$invitee=array_values(array_filter($rows,fn($row)=>$row['action']==='invitation-accepted-invitee'))[0];self::assertSame('de',$invitee['language']);self::assertSame('queue-delivery-two@example.invalid',$invitee['recipient']);self::assertStringStartsWith('Beta / Alpha / Beta / Synthetic team',$invitee['message']);
        $ready=array_values(array_filter($rows,fn($row)=>$row['action']==='publishable'));self::assertSame(['en','de'],array_column($ready,'language'));TeamBoundary::notify($result);TeamBoundary::notify($result);self::assertCount(6,$this->rows());
        wp_set_current_user($this->users['one']);TeamBoundary::manager()->save(951,$this->users['one'],[],[]);self::assertSame($rows,$this->rows());
    }
    public function testSameAccountInTwoEventsUsesSeparateSnapshots():void
    {
        $this->templates();$this->templates(952);$this->create();$this->create(952);self::assertCount(1,$this->rows());self::assertCount(1,$this->rows(952));self::assertNotSame($this->rows()[0]['message_key'],$this->rows(952)[0]['message_key']);self::assertStringContainsString('Synthetic event 952',$this->rows(952)[0]['message']);
    }
    public function testMissingEventConfigurationIsBlockedWithoutGlobalDelivery():void
    {
        $this->create();self::assertSame('blocked',$this->rows()[0]['status']);self::assertSame('missing_event_template',$this->rows()[0]['reason']);self::assertNotSame([],$this->db->rows($this->db->prepare('SELECT id FROM %i WHERE race_id=951',$this->db->table('h3_mgmt_teams'))));
    }
    public function testQueueSetupFailureRollsBackTeamProfileAndNotificationJournal():void
    {
        $manifest=$this->db->option('cor_mgmt_mail_queue_install');try{$this->db->execute($this->db->prepare('DELETE FROM %i WHERE option_name=%s',$this->db->wpdb->options,'cor_mgmt_mail_queue_install'));$this->rejected('mail_queue_setup_required',fn()=>TeamBoundary::manager()->save(951,$this->users['one'],['team_name'=>'Rollback'],['first_name'=>'Changed','language'=>'en']));self::assertSame('Alpha',get_user_meta($this->users['one'],'first_name',true));self::assertSame([],$this->rows());self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=951',$this->db->table('h3_mgmt_teams'))));self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=951',$this->db->table('cor_mgmt_communication_history'))));}finally{$this->db->setOption('cor_mgmt_mail_queue_install',$manifest);}
    }
    public function testConfirmationRequestReplayDoesNotCreateAnotherNotification():void
    {
        $this->templates();$this->create();$manager=TeamBoundary::manager();$manager->resendConfirmation(951,$this->users['one'],str_repeat('a',32));$manager->resendConfirmation(951,$this->users['one'],str_repeat('a',32));self::assertCount(2,$this->rows());$manager->resendConfirmation(951,$this->users['one'],str_repeat('b',32));self::assertCount(3,$this->rows());
    }
    public function testFailureInSecondMessageRollsBackFirstMessageTeamAndInvitationToken():void
    {
        $this->templates();$this->db->execute($this->db->prepare("UPDATE %i SET message='Invalid %unknown%' WHERE race_id=951 AND action='invitation'",$this->db->table('cor_mgmt_response_templates')));$this->rejected('unknown_placeholder',fn()=>$this->create(951,['invitee@example.invalid']));self::assertSame([],$this->rows());self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=951',$this->db->table('h3_mgmt_teams'))));self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=951',$this->db->table('cor_mgmt_communication_history'))));
    }
    public function testValidInvitationCanBeSentAndThenAccepted():void
    {
        $this->templates();$this->create(951,['invitee@example.invalid']);$row=$this->rows()[1];$values=json_decode($row['context'],true);$manager=$this->manager();$this->mutate($manager,'enable');self::assertSame('sent',$this->mutate($manager,'send',(int)$row['id']));self::assertSame('invitee@example.invalid',$this->transport->calls[0][0]);wp_set_current_user($this->users['two']);TeamBoundary::manager()->save(951,$this->users['two'],[],['language'=>'de'],$values['code']);self::assertCount(6,$this->rows());self::assertSame('sent',$this->rows()[1]['status']);
    }
    public function testManagerSendsOnlyReviewedMessageAndRejectsReplay():void
    {
        $this->templates();$this->create(951,['invitee@example.invalid']);$manager=$this->manager();$this->mutate($manager,'enable');$revision=$manager->context(951,$this->users['admin'])['revision'];$id=(int)$this->rows()[0]['id'];self::assertSame('sent',$manager->mutate(951,$this->users['admin'],$revision,'send',$id));self::assertCount(1,$this->transport->calls);self::assertSame('pending',$this->rows()[1]['status']);self::assertStringContainsString('From: Tramprennen <no-reply@tramprennen.org>',$this->transport->calls[0][3]);$this->rejected('stale_mail_queue',fn()=>$manager->mutate(951,$this->users['admin'],$revision,'send',$id));self::assertCount(1,$this->transport->calls);
    }
    public function testPausedOrServerDisabledEventCannotSend():void
    {
        $this->templates();$this->create();$manager=$this->manager();$id=(int)$this->rows()[0]['id'];$this->rejected('mail_delivery_disabled',fn()=>$this->mutate($manager,'send',$id));$this->mutate($manager,'enable');$disabled=$this->manager(false);$this->rejected('mail_delivery_disabled',fn()=>$this->mutate($disabled,'send',$id));self::assertSame([],$this->transport->calls);self::assertSame('pending',$this->rows()[0]['status']);
    }
    public function testBlockedMessageNeedsExplicitPreparationWithoutChangingAnExistingSnapshot():void
    {
        $this->create();$manager=$this->manager();$id=(int)$this->rows()[0]['id'];$this->rejected('active_event_template_required',fn()=>$this->mutate($manager,'prepare',$id));$this->templates();self::assertSame('blocked',$this->rows()[0]['status']);$this->mutate($manager,'prepare',$id);self::assertSame('pending',$this->rows()[0]['status']);self::assertSame('1',$this->rows()[0]['template_revision']);self::assertStringContainsString('Synthetic event 951',$this->rows()[0]['message']);$before=$this->rows();$this->rejected('mail_preparation_not_allowed',fn()=>$this->mutate($manager,'prepare',$id));self::assertSame($before,$this->rows());self::assertSame([],$this->transport->calls);
    }
    public function testFailedSnapshotCanOnlyBeRetriedUpToTheAttemptLimit():void
    {
        $this->templates();$this->create();$manager=$this->manager();$this->mutate($manager,'enable');$id=(int)$this->rows()[0]['id'];$this->transport->outcome='failed';for($attempt=1;$attempt<=3;$attempt++){self::assertSame('failed',$this->mutate($manager,'send',$id));if($attempt<3){$this->mutate($manager,'retry',$id);}}$this->rejected('mail_retry_not_allowed',fn()=>$this->mutate($manager,'retry',$id));self::assertCount(3,$this->transport->calls);
    }
    public function testWrongEventAndChangedRevisionCannotExposeOrSendAnotherMessage():void
    {
        $this->templates();$this->templates(952);$this->create();$this->create(952);$manager=$this->manager();$actor=$this->users['admin'];$this->rejected('forbidden',fn()=>$manager->context(952,$actor));$this->rejected('forbidden',fn()=>$manager->context(951,$this->users['one']));$this->rejected('missing_event_message',fn()=>$manager->context(951,$actor,1,(int)$this->rows(952)[0]['id']));$revision=$manager->context(951,$actor)['revision'];$this->mutate($manager,'enable');$this->rejected('stale_mail_queue',fn()=>$manager->mutate(951,$actor,$revision,'cancel',(int)$this->rows()[0]['id']));self::assertSame([],$this->transport->calls);
    }
    public function testConsumedAndClosedRegistrationInvitationsCannotBeDispatched():void
    {
        $this->templates();$this->create(951,['invitee@example.invalid']);$manager=$this->manager();$this->mutate($manager,'enable');$id=(int)$this->rows()[1]['id'];$this->db->execute($this->db->prepare('UPDATE %i SET consumed_at=1 WHERE team_id IN (SELECT id FROM %i WHERE race_id=951)',$this->db->table('cor_mgmt_invitation_tokens'),$this->db->table('h3_mgmt_teams')));$this->rejected('invitation_no_longer_valid',fn()=>$this->mutate($manager,'send',$id));$this->db->execute($this->db->prepare('UPDATE %i SET consumed_at=NULL WHERE team_id IN (SELECT id FROM %i WHERE race_id=951)',$this->db->table('cor_mgmt_invitation_tokens'),$this->db->table('h3_mgmt_teams')));$setting=json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=951',$this->db->table('h3_mgmt_races'))),true);$setting['status']=2;$this->db->execute($this->db->prepare('UPDATE %i SET setting=%s WHERE id=951',$this->db->table('h3_mgmt_races'),json_encode($setting)));$this->rejected('invitation_no_longer_valid',fn()=>$this->mutate($manager,'send',$id));self::assertSame([],$this->transport->calls);self::assertSame('pending',$this->rows()[1]['status']);
    }
    public function testAmbiguousOutcomeRequiresRecordedDecisionAndNeverRetry():void
    {
        $this->templates();$this->create();$manager=$this->manager();$this->mutate($manager,'enable');$id=(int)$this->rows()[0]['id'];$this->transport->outcome='uncertain';self::assertSame('uncertain',$this->mutate($manager,'send',$id));$this->rejected('mail_retry_not_allowed',fn()=>$this->mutate($manager,'retry',$id));$this->mutate($manager,'confirm-delivered',$id);self::assertSame('sent',$this->rows()[0]['status']);self::assertSame('operator_confirmed_delivery',$this->rows()[0]['reason']);self::assertCount(1,$this->transport->calls);
    }
    public function testCancelledMessageAndArchiveCannotBeSent():void
    {
        $this->templates();$this->create();$manager=$this->manager();$id=(int)$this->rows()[0]['id'];$this->mutate($manager,'cancel',$id);self::assertSame('cancelled',$this->rows()[0]['status']);$this->db->execute($this->db->prepare('UPDATE %i SET setting=%s WHERE id=951',$this->db->table('h3_mgmt_races'),json_encode(['status'=>4])));$this->rejected('archive_locked',fn()=>$this->mutate($manager,'enable'));self::assertSame([],$this->transport->calls);
    }
    public function testDeliveryToggleCycleCannotReviveAnOldSendAuthorization():void
    {
        $this->templates();$this->create();$manager=$this->manager();$this->mutate($manager,'enable');$revision=$manager->context(951,$this->users['admin'])['revision'];$this->mutate($manager,'disable');$this->mutate($manager,'enable');$this->rejected('stale_mail_queue',fn()=>$manager->mutate(951,$this->users['admin'],$revision,'send',(int)$this->rows()[0]['id']));self::assertSame([],$this->transport->calls);
    }
    public function testTeamEventsDoNotInvalidateIndependentTemplateEdits():void
    {
        $this->templates();$templates=new \Cor\Mgmt\Infrastructure\WordPress\ResponseTemplates($this->db,Services::get()->clock,fn(int $race,int $actor,string $operation):bool=>$actor===$this->users['admin']);$revision=$templates->context(951,$this->users['admin'])['revision'];$this->create();self::assertSame($revision,$templates->context(951,$this->users['admin'])['revision']);$templates->save(951,$this->users['admin'],new \Cor\Mgmt\Domain\AutoResponseTemplate('team-creation','en',true,'Changed','Hi %name%'),$revision);self::assertSame('951-team-creation-en',$this->rows()[0]['subject']);
    }
    public function testNativeAdminRequiresPostNonceConfirmationAndObjectRights():void
    {
        wp_set_current_user($this->users['admin']);$_SERVER['REQUEST_METHOD']='POST';$_POST=['race_id'=>'951','queue_operation'=>'enable'];$this->rejected('invalid_nonce',fn()=>MailQueueAdmin::submit());$_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-mail-queue');$_POST['queue_operation']='send';$this->rejected('confirmation_required',fn()=>MailQueueAdmin::submit());$_POST['queue_operation']='enable';$limited=new \WP_User($this->users['one']);$limited->add_cap('h3_mgmt_send_emails');wp_set_current_user($this->users['one']);$_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-mail-queue');$this->rejected('forbidden',fn()=>MailQueueAdmin::submit());$_SERVER['REQUEST_METHOD']='GET';$this->rejected('invalid_nonce',fn()=>MailQueueAdmin::submit());
    }
    public function testIndependentPortalRendersParticipantFormAndGuestLogin():void
    {
        $this->templates();$this->create();foreach([$this->users['one'],0] as $user){$process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/tools/communication-worker.php','portal',(string)$user,'951','0','unused'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),getenv());self::assertIsResource($process);fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error);self::assertSame('',$error);self::assertStringContainsString('<!doctype html>',$html);if($user){self::assertStringContainsString('Synthetic event 951',$html);self::assertStringContainsString('cor_mgmt_nonce',$html);self::assertStringContainsString('Synthetic team',$html);}else{self::assertStringContainsString('wp-login.php',$html);self::assertStringContainsString('race_id%3D951',$html);}}
    }
    public function testTwoRealWorkersCannotDispatchTheSameReviewedMessageTwice():void
    {
        $this->templates();$this->create();$manager=$this->manager();$this->mutate($manager,'enable');$id=(int)$this->rows()[0]['id'];$revision=$manager->context(951,$this->users['admin'])['revision'];$directory=sys_get_temp_dir().'/cor-mgmt-communication-'.bin2hex(random_bytes(8));mkdir($directory,0700);$jobs=[];$environment=getenv();$environment['COR_TEST_MAIL_BARRIER']=$directory;
        try{foreach([0,1] as $index){$process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/tools/communication-worker.php','send',(string)$this->users['admin'],'951',(string)$id,$revision],[0=>['pipe','r'],1=>['file',$directory.'/out-'.$index,'w'],2=>['file',$directory.'/err-'.$index,'w']],$pipes,dirname(__DIR__,2),$environment);self::assertIsResource($process);fclose($pipes[0]);$jobs[]=$process;}$until=microtime(true)+45;while(count(glob($directory.'/ready-*')?:[])!==2){if(microtime(true)>$until){self::fail('Queue workers did not reach barrier');}usleep(20000);clearstatcache();}touch($directory.'/go');$results=[];foreach($jobs as $index=>$process){self::assertSame(0,proc_close($process),file_get_contents($directory.'/err-'.$index));$jobs[$index]=null;$results[]=json_decode(file_get_contents($directory.'/out-'.$index),true,512,JSON_THROW_ON_ERROR);}self::assertCount(1,array_filter($results,fn($row)=>$row['success']));self::assertSame(["accepted"],file($directory.'/accepted',FILE_IGNORE_NEW_LINES));self::assertSame('sent',$this->rows()[0]['status']);self::assertSame('1',$this->rows()[0]['attempts']);}finally{foreach($jobs as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}if(realpath($directory)!==false&&str_starts_with(basename($directory),'cor-mgmt-communication-')){foreach(glob($directory.'/*')?:[] as $file){if(is_file($file)){unlink($file);}}rmdir($directory);}}
    }
    public function testNativePreviewEscapesContentAndNeverTriggersDelivery():void
    {
        $this->templates();$this->create();$id=(int)$this->rows()[0]['id'];$this->db->execute($this->db->prepare('UPDATE %i SET message=%s WHERE id=%d',$this->db->table('cor_mgmt_mail_queue'),'<script>bad()</script>',$id));wp_set_current_user($this->users['admin']);$_GET=['race_id'=>'951','message_id'=>(string)$id];ob_start();try{MailQueueAdmin::page();$html=ob_get_contents();}finally{ob_end_clean();}self::assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;',$html);self::assertStringNotContainsString('<script>bad()',$html);self::assertStringContainsString('Server delivery is disabled',$html);self::assertSame([],$GLOBALS['cor_test_mail']);self::assertStringContainsString('race_id=951',TeamPortal::url(951));
    }
}
