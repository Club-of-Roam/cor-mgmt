<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;
use Cor\Mgmt\Domain\MailRequest;
use Cor\Mgmt\Infrastructure\WordPress\MailQueue;
use Cor\Mgmt\Infrastructure\WordPress\MailQueueWorker;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Application\Migration\MigrationException;
use PHPUnit\Framework\TestCase;

final class MailQueueTest extends TestCase
{
    private Database $db;
    private MailQueue $queue;
    private Clock $clock;
    protected function setUp():void
    {
        global $wpdb;$this->db=new Database($wpdb);
        self::assertSame('0',$this->db->scalar('SELECT @@in_transaction'));
        foreach([901,902] as $race){self::assertSame(1,$wpdb->insert($this->db->table('h3_mgmt_races'),['id'=>$race,'name'=>'Synthetic queue '.$race,'setting'=>json_encode(['status'=>1]),'active'=>$race===901?1:0]));}
        $this->clock=new class implements Clock {public int $time=1794120000;public function now():int{return $this->time;}};
        $this->queue=new MailQueue($this->db,$this->clock);$GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown():void
    {
        $this->db->execute('ROLLBACK');
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE message_id IN (SELECT id FROM %i WHERE race_id IN (901,902))',$this->db->table('cor_mgmt_mail_history'),$this->db->table('cor_mgmt_mail_queue')));
        foreach(['cor_mgmt_mail_queue','cor_mgmt_response_templates'] as $table){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE race_id IN (901,902)',$this->db->table($table)));}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id IN (901,902)',$this->db->table('h3_mgmt_races')));wp_cache_flush();self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function rejected(string $reason,callable $call):void
    {
        try{$call();self::fail('Expected '.$reason);}catch(CommunicationException $error){self::assertSame($reason,$error->reason);}
    }
    private function template(int $race=901,bool $enabled=true):void
    {
        $this->db->execute($this->db->prepare("INSERT INTO %i (race_id,action,language,enabled,subject,message,revision,updated_at) VALUES (%d,'team-creation','en',%d,'Event %event_name%','Hello %name%',1,1)",$this->db->table('cor_mgmt_response_templates'),$race,(int)$enabled));
    }
    private function request(int $race=901,string $name='Member',string $occurrence='created:1'):MailRequest
    {
        return new MailRequest($race,$occurrence,'team-creation','member@example.invalid','en',['name'=>$name,'event_name'=>'Race '.$race]);
    }
    private function enqueue(?MailRequest $request=null):int
    {
        return $this->db->locked(function()use($request):int{$this->db->execute('START TRANSACTION');try{$id=$this->queue->enqueueWithinTransaction($request??$this->request());$this->db->execute('COMMIT');return $id;}catch(\Throwable $error){$this->db->execute('ROLLBACK');throw $error;}});
    }
    private function row(int $id):array{return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d',$this->db->table('cor_mgmt_mail_queue'),$id))[0];}
    private function worker(string $outcome='sent',?callable $gate=null):MailQueueWorker
    {
        $db=$this->db;$transport=new class($db,$outcome) implements MailTransport {
            public function __construct(private Database $db,private string $outcome){}
            public function send(string $recipient,string $subject,string $body,string $headers):bool{
                TestCase::assertSame('0',$this->db->scalar('SELECT @@in_transaction'));TestCase::assertFalse($this->db->hasLock());
                TestCase::assertSame('member@example.invalid',$recipient);TestCase::assertStringStartsWith('Event Race ',$subject);TestCase::assertSame('Hello Member',$body);TestCase::assertSame('Content-Type: text/plain; charset=UTF-8',$headers);
                if($this->outcome==='uncertain'){throw new \RuntimeException('Synthetic ambiguous provider outcome');}return $this->outcome==='sent';
            }
        };
        $random=new class implements RandomSource {public function bytes(int $length):string{return random_bytes($length);}public function integer(int $minimum,int $maximum):int{return $minimum;}};
        return new MailQueueWorker($this->queue,$transport,$random,$gate);
    }
    public function testSnapshotAndIdentitySurviveTemplateAndActiveEventChanges():void
    {
        $this->template();$id=$this->enqueue();$before=$this->row($id);
        $this->db->execute($this->db->prepare("UPDATE %i SET subject='Changed',revision=2 WHERE race_id=901",$this->db->table('cor_mgmt_response_templates')));
        $this->db->execute($this->db->prepare('UPDATE %i SET active=0 WHERE id=901',$this->db->table('h3_mgmt_races')));
        self::assertSame($id,$this->enqueue());self::assertSame($before,$this->row($id));self::assertSame('Event Race 901',$before['subject']);self::assertSame('1',$before['template_revision']);
        $this->rejected('mail_occurrence_conflict',fn()=>$this->enqueue($this->request(901,'Changed')));
    }
    public function testSeparateEventsAndOccurrencesDoNotDeduplicateEachOther():void
    {
        $this->template();$this->template(902);$one=$this->enqueue();$two=$this->enqueue($this->request(902));$three=$this->enqueue($this->request(901,'Member','created:2'));
        self::assertCount(3,array_unique([$one,$two,$three]));self::assertSame('Event Race 902',$this->row($two)['subject']);
    }
    public function testMissingAndDisabledTemplatesAreDurableAndNeverFallback():void
    {
        $this->template(902,false);$missing=$this->enqueue();$disabled=$this->enqueue($this->request(902));self::assertSame('blocked',$this->row($missing)['status']);self::assertSame('skipped',$this->row($disabled)['status']);self::assertSame('missing_event_template',$this->row($missing)['reason']);self::assertSame('',$this->row($missing)['subject']);
        $this->template();self::assertSame($missing,$this->enqueue());self::assertSame('blocked',$this->row($missing)['status']);self::assertNull($this->queue->claim(901,str_repeat('a',64)));self::assertNull($this->queue->claim(902,str_repeat('a',64)));
    }
    public function testProducerRequiresOwningTransactionAndRollsBackWithDomainChange():void
    {
        $this->template();try{$this->queue->enqueueWithinTransaction($this->request());self::fail();}catch(MigrationException $error){self::assertSame('migration_lock_lost',$error->getMessage());}
        $this->db->locked(function():void{$this->rejected('mail_transaction_required',fn()=>$this->queue->enqueueWithinTransaction($this->request()));$this->db->execute('START TRANSACTION');$this->db->execute($this->db->prepare("UPDATE %i SET name='Rollback' WHERE id=901",$this->db->table('h3_mgmt_races')));$this->queue->enqueueWithinTransaction($this->request());$this->db->execute('ROLLBACK');});
        self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=901',$this->db->table('cor_mgmt_mail_queue'))));self::assertSame('Synthetic queue 901',$this->db->scalar($this->db->prepare('SELECT name FROM %i WHERE id=901',$this->db->table('h3_mgmt_races'))));
    }
    public function testDefaultWorkerCannotSendAndTransportRunsAfterCommit():void
    {
        $this->template();$id=$this->enqueue();$this->rejected('mail_delivery_disabled',fn()=>$this->worker()->runOne(901));self::assertSame('pending',$this->row($id)['status']);
        self::assertSame('sent',$this->worker('sent',fn(int $race):bool=>$race===901)->runOne(901));self::assertSame('sent',$this->row($id)['status']);self::assertSame('transport_accepted',$this->row($id)['reason']);self::assertSame('empty',$this->worker('sent',fn()=>true)->runOne(901));
    }
    public function testFailedDeliveryNeedsExplicitBoundedRetry():void
    {
        $this->template();$id=$this->enqueue();$worker=$this->worker('failed',fn()=>true);
        for($attempt=1;$attempt<=3;$attempt++){self::assertSame('failed',$worker->runOne(901));self::assertSame((string)$attempt,$this->row($id)['attempts']);self::assertSame('empty',$worker->runOne(901));if($attempt<3){$this->queue->retryFailed(901,$id);}}
        $this->rejected('mail_retry_not_allowed',fn()=>$this->queue->retryFailed(901,$id));
    }
    public function testAmbiguousTransportNeverRetriesAutomaticallyOrThroughFailedRetry():void
    {
        $this->template();$id=$this->enqueue();$worker=$this->worker('uncertain',fn()=>true);self::assertSame('uncertain',$worker->runOne(901));self::assertSame('uncertain',$this->row($id)['status']);self::assertSame('empty',$worker->runOne(901));$this->rejected('mail_retry_not_allowed',fn()=>$this->queue->retryFailed(901,$id));
    }
    public function testExpiredClaimBecomesUncertainAndOldWorkerLosesOwnership():void
    {
        $this->template();$id=$this->enqueue();$token=str_repeat('a',64);$this->queue->claim(901,$token);self::assertSame(0,$this->queue->recoverStale(901));$this->clock->time+=MailQueue::CLAIM_SECONDS;self::assertSame(1,$this->queue->recoverStale(901));self::assertSame('uncertain',$this->row($id)['status']);$this->rejected('mail_claim_lost',fn()=>$this->queue->finish(901,$id,$token,'sent'));self::assertNull($this->queue->claim(901,str_repeat('b',64)));
    }
    public function testAnotherConnectionCannotClaimAnAlreadyClaimedMessage():void
    {
        $this->template();$id=$this->enqueue();$this->queue->claim(901,str_repeat('a',64));$connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$connection->set_prefix($this->db->wpdb->prefix);$other=new MailQueue(new Database($connection),$this->clock);
        try{self::assertNull($other->claim(901,str_repeat('b',64)));$this->rejected('mail_claim_lost',fn()=>$other->finish(901,$id,str_repeat('b',64),'sent'));$this->db->locked(function()use($other):void{try{$other->claim(901,str_repeat('c',64));self::fail();}catch(MigrationException $error){self::assertSame('migration_locked',$error->getMessage());}});}finally{$connection->close();}
    }
    public function testArchiveBlocksNewMessagesAndClaimsButAllowsRecordingAnInFlightOutcome():void
    {
        $this->template();$id=$this->enqueue();$token=str_repeat('a',64);$this->queue->claim(901,$token);$this->db->execute($this->db->prepare('UPDATE %i SET setting=%s WHERE id=901',$this->db->table('h3_mgmt_races'),json_encode(['status'=>4])));$this->rejected('event_not_writable',fn()=>$this->enqueue());$this->rejected('event_not_writable',fn()=>$this->queue->claim(901,str_repeat('b',64)));$this->queue->finish(901,$id,$token,'sent');self::assertSame('sent',$this->row($id)['status']);
    }
    public function testWorkerCannotSendInsideAnOuterTransaction():void
    {
        $this->template();$id=$this->enqueue();$this->db->execute('START TRANSACTION');try{$this->rejected('mail_dispatch_inside_transaction',fn()=>$this->worker('sent',fn()=>true)->runOne(901));}finally{$this->db->execute('ROLLBACK');}self::assertSame('pending',$this->row($id)['status']);
    }
    public function testASecondAdapterCannotHideTheSameConnectionLock():void
    {
        $this->db->locked(function():void{$other=new MailQueue(new Database($this->db->wpdb),$this->clock);$this->rejected('mail_dispatch_inside_transaction',fn()=>$other->assertDispatchable());});
    }
    public function testJournalContainsOutcomesWithoutProviderExceptionsOrContent():void
    {
        $this->template();$id=$this->enqueue();$this->worker('uncertain',fn()=>true)->runOne(901);$rows=$this->db->rows($this->db->prepare('SELECT status,attempt,reason FROM %i WHERE message_id=%d ORDER BY id',$this->db->table('cor_mgmt_mail_history'),$id));self::assertSame(['pending','sending','uncertain'],array_column($rows,'status'));self::assertSame(['0','1','1'],array_column($rows,'attempt'));self::assertStringNotContainsString('provider',json_encode($rows));
    }
    public function testMissingSetupDoesNotCreateStorageOrSendDuringAReadinessCheck():void
    {
        $manifest=$this->db->option('cor_mgmt_mail_queue_install');try{$this->db->execute($this->db->prepare('DELETE FROM %i WHERE option_name=%s',$this->db->wpdb->options,'cor_mgmt_mail_queue_install'));$this->rejected('mail_queue_setup_required',fn()=>$this->queue->claim(901,str_repeat('a',64)));self::assertNull($this->db->option('cor_mgmt_mail_queue_install'));}finally{$this->db->setOption('cor_mgmt_mail_queue_install',$manifest);}
    }
    public function testInvalidRenderingRollsBackInsteadOfLeavingAPartialMessage():void
    {
        $this->template();$this->expectException(CommunicationException::class);try{$this->enqueue(new MailRequest(901,'created:1','team-creation','member@example.invalid','en',[]));}finally{self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=901',$this->db->table('cor_mgmt_mail_queue'))));}
    }
}
