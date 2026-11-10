<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Cutover;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Preflight;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Runner;
use Cor\Mgmt\WordPress\Services;
use PHPUnit\Framework\TestCase;

final class CutoverTest extends TestCase
{
    private Database $db;
    private Cutover $cutover;
    protected function setUp(): void
    {
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        $connection->set_prefix('cortest_cutover_'.bin2hex(random_bytes(4)).'_');$this->db=new Database($connection);
        $this->db->execute($this->db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL UNIQUE, option_value longtext NOT NULL, autoload varchar(20) NOT NULL) ENGINE=InnoDB',$connection->options));
        (new Installer($this->db))->fresh();
        $this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=MyISAM',$this->db->table('h3_mgmt_invitations')));
        $this->db->execute($this->db->prepare('ALTER TABLE %i ADD old_binary blob NULL',$this->db->table('h3_mgmt_races')));
        $connection->insert($this->db->table('h3_mgmt_races'),['id'=>91,'name'=>'Synthetic current event','setting'=>'{"status":1}','old_binary'=>"\x00\xff"]);
        $connection->insert($this->db->table('h3_mgmt_teams'),['id'=>42,'race_id'=>91,'team_name'=>'Synthetic team','total_points'=>678]);
        $this->cutover=new Cutover($this->db,Services::get()->clock,Services::get()->random);
    }
    protected function tearDown(): void
    {
        $prefix=$this->db->wpdb->prefix;
        if(!preg_match('/^cortest_cutover_[a-f0-9]{8}_$/D',$prefix)){throw new \RuntimeException('Unsafe cleanup');}
        foreach($this->db->wpdb->get_col($this->db->prepare('SHOW TABLES LIKE %s',$this->db->wpdb->esc_like($prefix).'%')) as $table){$this->db->execute($this->db->prepare('DROP TABLE %i',$table));}
        $this->db->wpdb->close();wp_cache_flush();
    }
    private function prepare(): void
    {
        $runtime=Services::get();$runner=new Runner($this->db,$runtime->clock,$runtime->random);$fingerprint=(new Preflight($this->db))->run()->fingerprint();$id=$runner->start($fingerprint);
        for($i=0;$i<50;++$i){if($runner->step($id)['state']==='verified'){break;}}
        $this->cutover->start($id,$fingerprint,1);
    }
    private function ready(): void
    {
        for($i=0;$i<100;++$i){if($this->cutover->step()['state']==='ready'){return;}}
        self::fail('Copy did not verify');
    }
    private function rejects(string $reason, callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(\RuntimeException $error){self::assertSame($reason,$error->getMessage());}
    }
    public function testModernCopySwitchAndRollbackKeepOriginalBytesAndAllIds(): void
    {
        $original=(new Preflight($this->db))->run()->fingerprint();$this->prepare();$this->ready();self::assertSame($original,(new Preflight($this->db))->run()->fingerprint());
        $this->cutover->activate();self::assertTrue($this->cutover->runtimeReady());$state=$this->cutover->state();self::assertSame('active',$state['state']);
        self::assertSame('InnoDB',$this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$this->db->table('h3_mgmt_invitations'))));
        self::assertSame('00FF',$this->db->scalar($this->db->prepare('SELECT HEX(old_binary) FROM %i WHERE id=91',$this->db->table('h3_mgmt_races'))));
        self::assertSame('678',$this->db->scalar($this->db->prepare('SELECT total_points FROM %i WHERE id=42',$this->db->table('h3_mgmt_teams'))));
        foreach($state['tables'] as $table){self::assertTrue($this->db->exists($table['backup']));}
        $this->cutover->rollback();self::assertSame('rolled_back',$this->cutover->state()['state']);self::assertSame($original,(new Preflight($this->db))->run()->fingerprint());self::assertTrue($this->cutover->runtimeReady());
        foreach($state['tables'] as $table){self::assertTrue($this->db->exists($table['target']));}
    }
    public function testMaintenanceRejectsOrdinaryWritersAndReleasesItsLock(): void
    {
        $this->prepare();self::assertFalse($this->cutover->runtimeReady());$this->rejects('maintenance_in_progress',fn()=>$this->db->locked(static fn()=>true));
        self::assertFalse($this->db->hasLock());self::assertFalse($this->db->connectionHasLock());self::assertSame('prepare',$this->cutover->state()['state']);
    }
    public function testUnknownColumnsAndUtf32TextSurviveTheActiveCopy(): void
    {
        $this->db->execute($this->db->prepare('ALTER TABLE %i CONVERT TO CHARACTER SET utf32 COLLATE utf32_general_ci',$this->db->table('h3_mgmt_teams')));
        $this->db->wpdb->update($this->db->table('h3_mgmt_teams'),['team_name'=>"Gr\u{00fc}\u{00df}e"],['id'=>42]);
        $this->prepare();$this->ready();$this->cutover->activate();self::assertSame("Gr\u{00fc}\u{00df}e",$this->db->scalar($this->db->prepare('SELECT team_name FROM %i WHERE id=42',$this->db->table('h3_mgmt_teams'))));
        self::assertSame('utf8mb4_unicode_ci',$this->db->scalar($this->db->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$this->db->table('h3_mgmt_teams'))));
    }
    public function testChangedSourceAndChangedPreparedTargetPreventActivation(): void
    {
        $this->prepare();$this->ready();$state=$this->cutover->state();$target=$state['tables']['h3_mgmt_teams']['target'];
        $this->db->execute($this->db->prepare("UPDATE %i SET team_name='changed' WHERE id=42",$target));$this->rejects('live_target_content_changed',fn()=>$this->cutover->activate());
        $this->db->execute($this->db->prepare("UPDATE %i SET team_name='source changed' WHERE id=42",$this->db->table('h3_mgmt_teams')));$this->rejects('live_source_changed',fn()=>$this->cutover->activate());
        self::assertSame('ready',$this->cutover->state()['state']);self::assertFalse($this->db->exists($state['tables']['h3_mgmt_teams']['backup']));
    }
    public function testNewDataAfterActivationPreventsSilentRollback(): void
    {
        $this->prepare();$this->ready();$this->cutover->activate();$this->db->wpdb->insert($this->db->table('h3_mgmt_teams'),['id'=>43,'race_id'=>91,'team_name'=>'New after switch']);
        $this->rejects('new_writes_prevent_rollback',fn()=>$this->cutover->rollback());self::assertSame('active',$this->cutover->state()['state']);self::assertSame('New after switch',$this->db->scalar($this->db->prepare('SELECT team_name FROM %i WHERE id=43',$this->db->table('h3_mgmt_teams'))));
    }
    public function testInterruptedCreateAndPreRenameSwitchCanBeResumed(): void
    {
        $this->prepare();$state=$this->cutover->state();$table=$state['tables']['h3_mgmt_auto_responses'];$this->db->execute($this->db->prepare('CREATE TABLE %i LIKE %i',$table['target'],$this->db->table('h3_mgmt_auto_responses')));
        $this->ready();$state=$this->cutover->state();$state['state']='switching';$this->db->setOption('cor_mgmt_live_transition',json_encode($state,JSON_THROW_ON_ERROR));$this->cutover->recover();self::assertSame('ready',$this->cutover->state()['state']);
        $this->cutover->activate();self::assertTrue($this->cutover->runtimeReady());
    }
    public function testInterruptedPostRenamePublicationCanBeRecovered(): void
    {
        $this->prepare();$this->ready();$state=$this->cutover->state();$state['state']='switching';$this->db->setOption('cor_mgmt_live_transition',json_encode($state,JSON_THROW_ON_ERROR));$rename=[];
        foreach($state['tables'] as $suffix=>$table){$rename[]=$this->db->prepare('%i TO %i',$this->db->table($suffix),$table['backup']);$rename[]=$this->db->prepare('%i TO %i',$table['target'],$this->db->table($suffix));}
        $this->db->execute('RENAME TABLE '.implode(',',$rename));self::assertFalse($this->cutover->runtimeReady());$this->cutover->recover();self::assertTrue($this->cutover->runtimeReady());self::assertSame('active',$this->cutover->state()['state']);
    }
    public function testForgedTargetNameCannotRenameUnrelatedTables(): void
    {
        $this->prepare();$state=$this->cutover->state();$state['tables']['h3_mgmt_races']['target']=$this->db->wpdb->options;$this->db->setOption('cor_mgmt_live_transition',json_encode($state,JSON_THROW_ON_ERROR));
        $this->rejects('live_transition_state_invalid',fn()=>$this->cutover->step());self::assertTrue($this->db->exists($this->db->wpdb->options));
    }

    public function testAbandonedPreparationRetainsCopiesAndRestoresOrdinaryWriters(): void
    {
        $this->prepare(); $this->ready(); $tables=$this->cutover->state()['tables']; $this->cutover->abandon();
        self::assertTrue($this->cutover->runtimeReady()); self::assertTrue($this->db->locked(static fn()=>true));
        foreach($tables as $table){self::assertTrue($this->db->exists($table['target']));self::assertFalse($this->db->exists($table['backup']));}
        self::assertSame('678',$this->db->scalar($this->db->prepare('SELECT total_points FROM %i WHERE id=42',$this->db->table('h3_mgmt_teams'))));
    }

    public function testInterruptedRollbackIsRecoveredOnEitherSideOfRename(): void
    {
        $this->prepare();$this->ready();$this->cutover->activate();$state=$this->cutover->state();$state['state']='reverting';
        $this->db->setOption('cor_mgmt_live_transition',json_encode($state,JSON_THROW_ON_ERROR));$this->cutover->recover();self::assertSame('active',$this->cutover->state()['state']);
        $state=$this->cutover->state();$state['state']='reverting';$this->db->setOption('cor_mgmt_live_transition',json_encode($state,JSON_THROW_ON_ERROR));$rename=[];
        foreach($state['tables'] as $suffix=>$table){$rename[]=$this->db->prepare('%i TO %i',$this->db->table($suffix),$table['target']);$rename[]=$this->db->prepare('%i TO %i',$table['backup'],$this->db->table($suffix));}
        $this->db->execute('RENAME TABLE '.implode(',',$rename));$this->cutover->recover();self::assertSame('rolled_back',$this->cutover->state()['state']);self::assertTrue($this->cutover->runtimeReady());
    }

    public function testHistoricalArchiveIsPublishedOnlyWithTheVerifiedSwitchAndUnpublishedOnRollback(): void
    {
        global $wpdb;
        foreach(['users','usermeta'] as $table){$this->db->execute($this->db->prepare('CREATE TABLE %i LIKE %i',$this->db->wpdb->$table,$wpdb->$table));}
        $installer=new Installer($this->db);$installer->ticker();$installer->archive();
        (new \Cor\Mgmt\Infrastructure\WordPress\TickerImport($this->db))->start(true);
        $this->db->wpdb->update($this->db->table('h3_mgmt_races'),['setting'=>'{"status":3}'],['id'=>91]);
        $archive=new \Cor\Mgmt\Infrastructure\WordPress\Archive($this->db,Services::get()->clock,Services::get()->random);
        $id=$archive->start(91,1,'migration');
        for($i=0;$i<100;++$i){if($archive->step($id)['state']==='ready'){break;}}
        self::assertSame('ready',$archive->run($id)['state']);self::assertSame('',$archive->published(91));
        $this->prepare();$this->ready();$this->cutover->activate();self::assertSame($id,$archive->published(91));
        self::assertSame('678',$archive->row($id,'team',42)['total_points']);
        $state=$this->cutover->state();
        self::assertSame(4,json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=91',$this->db->table('h3_mgmt_races'))),true)['status']);
        self::assertSame(3,json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=91',$state['tables']['h3_mgmt_races']['backup'])),true)['status']);
        $this->cutover->rollback();self::assertSame('',$archive->published(91));self::assertSame('sealed',$archive->run($id)['state']);
    }

    public function testCustomSourceTriggerRequiresReviewInsteadOfBeingSilentlyLost(): void
    {
        $this->db->execute($this->db->prepare('CREATE TRIGGER %i BEFORE UPDATE ON %i FOR EACH ROW SET NEW.name=NEW.name',$this->db->table('audit_trigger'),$this->db->table('h3_mgmt_races')));
        $this->rejects('live_source_dependencies_require_review',fn()=>$this->prepare());self::assertNull($this->cutover->state());self::assertTrue($this->cutover->runtimeReady());
    }

    public function testRollbackGuardTraversesNumericAndCompositeKeysAcrossBatchBoundaries(): void
    {
        (new Installer($this->db))->archive();
        $teams=$rows=[];
        for($i=0;$i<102;++$i){
            $teams[]=$this->db->prepare('(%s,91,%s)',(string)(9007199254740993+$i),'Synthetic pagination');
            $rows[]=$this->db->prepare('(%s,%s,%s,%s,%s)',str_repeat('a',32),'team',str_pad((string)$i,20,'0',STR_PAD_LEFT),'{}',hash('sha256','{}'));
        }
        $this->db->execute($this->db->prepare('INSERT INTO %i (id,race_id,team_name) VALUES ',$this->db->table('h3_mgmt_teams')).implode(',',$teams));
        $this->db->execute($this->db->prepare('INSERT INTO %i (snapshot_id,kind,source_key,payload,row_hash) VALUES ',$this->db->table('cor_mgmt_archive_rows')).implode(',',$rows));
        $this->prepare();$this->ready();$this->cutover->activate();
        $id='9007199254741094';
        $this->db->execute($this->db->prepare('UPDATE %i SET team_name=%s WHERE id=CAST(%s AS UNSIGNED)',$this->db->table('h3_mgmt_teams'),'Changed on second page',$id));
        $this->rejects('new_writes_prevent_rollback',fn()=>$this->cutover->rollback());
        $this->db->execute($this->db->prepare('UPDATE %i SET team_name=%s WHERE id=CAST(%s AS UNSIGNED)',$this->db->table('h3_mgmt_teams'),'Synthetic pagination',$id));
        $this->db->execute($this->db->prepare("UPDATE %i SET payload='changed' WHERE source_key=%s",$this->db->table('cor_mgmt_archive_rows'),str_pad('101',20,'0',STR_PAD_LEFT)));
        $this->rejects('new_writes_prevent_rollback',fn()=>$this->cutover->rollback());
        $this->db->execute($this->db->prepare("UPDATE %i SET payload='{}' WHERE source_key=%s",$this->db->table('cor_mgmt_archive_rows'),str_pad('101',20,'0',STR_PAD_LEFT)));
        $this->cutover->rollback();self::assertSame('rolled_back',$this->cutover->state()['state']);
    }
}
