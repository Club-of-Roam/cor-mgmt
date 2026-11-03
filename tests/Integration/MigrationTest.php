<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;
use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Application\Migration\RawRow;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Preflight;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Runner;
use Cor\Mgmt\WordPress\Services;
use PHPUnit\Framework\TestCase;

/** Separate synthetic prefixes, real DDL and real transactions; no application data. */
final class MigrationTest extends TestCase {
    private static Database $db;
    private static string $prefix;
    public static function setUpBeforeClass(): void {
        self::$prefix='cortest_migration_'.bin2hex(random_bytes(4)).'_';
        self::$db=self::context(self::$prefix);
        (new Installer(self::$db))->fresh();
    }
    private static function context(string $prefix): Database {
        if (!preg_match('/^cortest_(?:migration|install)_[a-f0-9]{8}_$/D',$prefix)) { throw new \RuntimeException('Unsafe synthetic prefix'); }
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        $connection->set_prefix($prefix);
        $db=new Database($connection);
        $db->execute($db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL UNIQUE, option_value longtext NOT NULL, autoload varchar(20) NOT NULL) ENGINE=InnoDB',$connection->options));
        return $db;
    }
    private static function cleanup(Database $db): void {
        $prefix=$db->wpdb->prefix;
        if (!preg_match('/^cortest_(?:migration|install)_[a-f0-9]{8}_$/D',$prefix)) { throw new \RuntimeException('Unsafe cleanup'); }
        foreach (array_keys(Catalogue::legacy()+Catalogue::shadow()) as $suffix) { $db->execute($db->prepare('DROP TABLE IF EXISTS %i',$db->table($suffix))); }
        $db->execute($db->prepare('DROP TABLE %i',$db->wpdb->options));
        $db->wpdb->close();
    }
    public static function tearDownAfterClass(): void { self::cleanup(self::$db); }
    protected function setUp(): void {
        foreach (array_keys(Catalogue::legacy()+Catalogue::shadow()) as $suffix) { self::$db->execute(self::$db->prepare('DELETE FROM %i',self::$db->table($suffix))); }
        self::$db->setOption('cor_mgmt_current_migration','');
        $db=self::$db->wpdb;
        $db->insert(self::$db->table('h3_mgmt_races'),['id'=>9,'name'=>'Synthetic race','setting'=>'{"status":3,"historic":"keep"}']);
        $db->insert(self::$db->table('h3_mgmt_routes'),['id'=>7,'race_id'=>9,'name'=>'Synthetic route']);
        $db->insert(self::$db->table('h3_mgmt_stages'),['id'=>5,'race_id'=>9,'route_id'=>7,'number'=>1]);
        $db->insert(self::$db->table('h3_mgmt_teams'),['id'=>42,'race_id'=>9,'route_id'=>7,'team_name'=>'Synthetic','total_points'=>999,
            'rank_stage_1'=>2,'extra_stage_1'=>1,'amount_extra_stage_1'=>6,'vary_extra_stage_1'=>3,'donation_goal'=>100]);
        $db->insert(self::$db->table('h3_mgmt_teammates'),['id'=>8,'team_id'=>42,'user_id'=>991,'language'=>'de']);
        $db->insert(self::$db->table('h3_mgmt_invitations'),['id'=>6,'team_id'=>999,'email'=>'synthetic@example.invalid','code'=>'1234567890123456']);
        $db->insert(self::$db->table('h3_mgmt_sponsors'),['id'=>10,'team_id'=>777,'race_id'=>9,'method'=>'betterplace','donation'=>127]);
        self::assertSame('',$db->last_error);
    }
    private function runner(?Database $db=null): Runner {
        $runtime=Services::get();
        return new Runner($db ?? self::$db,$runtime->clock,$runtime->random);
    }
    private function start(): string { return $this->runner()->start((new Preflight(self::$db))->run()->fingerprint()); }
    private function finish(string $run,int $limit=1): array {
        for ($i=0;$i<100;++$i) {
            $state=$this->runner()->step($run,$limit);
            if ($state['state']==='verified') { return $state; }
        }
        self::fail('Bounded migration did not finish');
    }
    public function testFreshSchemaHasModernEnginesIndexesDefaultsAndLanguage(): void {
        foreach (Catalogue::legacy()+Catalogue::shadow() as $suffix=>$definition) { (new Inspector(self::$db))->verify(self::$db->table($suffix),$definition); }
        self::assertSame('2.8',self::$db->option('h3_mgmt_db_version'));
        self::assertSame(Catalogue::STORAGE_VERSION,self::$db->option('cor_mgmt_storage_version'));
        self::assertSame('de',self::$db->scalar(self::$db->prepare('SELECT language FROM %i WHERE id=8',self::$db->table('h3_mgmt_teammates'))));
        self::assertSame('0',self::$db->scalar(self::$db->prepare('SELECT rank_stage_6 FROM %i WHERE id=42',self::$db->table('h3_mgmt_teams'))));
    }
    public function testPreflightIsReadOnlyAndReportsOrphansAndUnresolvedMoney(): void {
        $before=$this->sourceState();
        $report=(new Preflight(self::$db))->run();
        self::assertSame(['9'],$report->archiveIds);
        self::assertContains('orphan:invitations:team_id:1',$report->issues);
        self::assertContains('orphan:sponsors:team_id:1',$report->issues);
        self::assertContains('unclassified_money:1',$report->issues);
        self::assertContains('unresolved_result_slots:5',$report->issues);
        self::assertSame($before,$this->sourceState());
        self::assertSame('0',self::$db->scalar(self::$db->prepare('SELECT COUNT(*) FROM %i',self::$db->table('cor_mgmt_migration_runs'))));
        self::assertStringNotContainsString('synthetic@example.invalid',$report->json());
    }
    private function sourceState(): array {
        $state=[];
        foreach (Catalogue::legacy() as $suffix=>$_) { $state[$suffix]=self::$db->rows(self::$db->prepare('SELECT * FROM %i ORDER BY id',self::$db->table($suffix))); }
        return $state;
    }
    public function testCopyResumesPreservesIdsHistoryAndAmbiguousAmountsWithoutCutover(): void {
        $before=$this->sourceState();
        $run=$this->start();
        $this->runner()->step($run,1);
        $this->runner()->step($run,1);
        $status=$this->finish($run);
        self::assertSame('verified',$status['state']);
        self::assertSame(7,$status['copied']);
        self::assertSame($status,$this->runner()->step($run,1));
        self::assertSame($run,$this->start());
        self::assertSame($before,$this->sourceState());
        self::assertNull(self::$db->option('cor_mgmt_active_generation'));
        self::assertSame('2.8',self::$db->option('h3_mgmt_db_version'));
        $race=self::$db->rows(self::$db->prepare('SELECT source_phase,target_phase FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_race_states'),$run));
        self::assertSame([['source_phase'=>'3','target_phase'=>'4']],$race);
        $rows=self::$db->rows(self::$db->prepare('SELECT * FROM %i WHERE run_id=%s ORDER BY legacy_slot',self::$db->table('cor_mgmt_stage_results'),$run));
        self::assertCount(6,$rows);
        self::assertSame('5',$rows[0]['stage_id']);
        self::assertSame('2',$rows[0]['rank_value']);
        self::assertSame('6',$rows[0]['extra_amount']);
        self::assertNull($rows[1]['stage_id']);
        $amount=self::$db->rows(self::$db->prepare("SELECT raw_value,amount_cents,currency,unit_status FROM %i WHERE run_id=%s AND source_table='h3_mgmt_sponsors'",self::$db->table('cor_mgmt_legacy_amounts'),$run))[0];
        self::assertSame('127',$amount['raw_value']); self::assertNull($amount['amount_cents']); self::assertNull($amount['currency']); self::assertSame('unresolved',$amount['unit_status']);
        $payload=self::$db->scalar(self::$db->prepare("SELECT payload FROM %i WHERE run_id=%s AND source_table='h3_mgmt_teams'",self::$db->table('cor_mgmt_migration_rows'),$run));
        self::assertSame('999',RawRow::fromPayload($payload)->value('total_points'));
    }
    public function testStalePreflightIsRejectedBeforeRunCreation(): void {
        $report=(new Preflight(self::$db))->run();
        self::$db->wpdb->update(self::$db->table('h3_mgmt_teams'),['team_name'=>'Changed'],['id'=>42]);
        try { $this->runner()->start($report->fingerprint()); self::fail('Stale preflight accepted'); }
        catch (MigrationException $e) { self::assertSame('preflight_changed',$e->getMessage()); }
        self::assertNull($this->runner()->current());
    }
    public function testSourceChangeFailsVerificationAndAbandonRetainsEvidence(): void {
        $run=$this->start();
        self::$db->wpdb->update(self::$db->table('h3_mgmt_teams'),['team_name'=>'Changed'],['id'=>42]);
        try { $this->finish($run); self::fail('Changed source verified'); }
        catch (MigrationException $e) { self::assertSame('source_changed',$e->getMessage()); }
        self::assertSame('failed',$this->runner()->status($run)['state']);
        self::assertNull(self::$db->option('cor_mgmt_active_generation'));
        $this->runner()->abandon($run);
        self::assertSame('7',self::$db->scalar(self::$db->prepare('SELECT COUNT(*) FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_migration_rows'),$run)));
        $next=$this->start(); self::assertNotSame($run,$next); self::assertSame('verified',$this->finish($next)['state']);
    }
    public function testCorruptedTargetPayloadPreventsVerification(): void {
        $run=$this->start();
        for ($i=0;$i<count(Catalogue::legacy());++$i) { $this->runner()->step($run,100); }
        self::$db->wpdb->update(self::$db->table('cor_mgmt_migration_rows'),['payload'=>'{"id":"3432","team_name":"424144"}'],['run_id'=>$run,'source_table'=>'h3_mgmt_teams']);
        $this->expectExceptionMessage('target_row_changed'); $this->runner()->step($run);
    }
    public function testBatchFailureRollsBackRowsProjectionsAndCursorBeforeResume(): void {
        $run=$this->start();
        $teamIndex=array_search('h3_mgmt_teams',array_keys(Catalogue::legacy()),true);
        for ($i=0;$i<$teamIndex;++$i) { $this->runner()->step($run,100); }
        $before=$this->runner()->status($run)['copied'];
        $table=self::$db->table('cor_mgmt_stage_results');
        $filter=static fn($query)=>str_starts_with($query,'INSERT INTO') && str_contains($query,$table)?'INVALID SYNTHETIC SQL':$query;
        add_filter('query',$filter);
        $suppressed=self::$db->wpdb->suppress_errors(true);
        try { $this->runner()->step($run,100); self::fail('Failure injection did not run'); }
        catch (MigrationException $e) { self::assertSame('target_write_failed',$e->getMessage()); }
        finally { remove_filter('query',$filter); self::$db->wpdb->suppress_errors($suppressed); }
        self::assertSame($before,$this->runner()->status($run)['copied']);
        self::assertSame('0',self::$db->scalar(self::$db->prepare("SELECT COUNT(*) FROM %i WHERE run_id=%s AND source_table='h3_mgmt_teams'",self::$db->table('cor_mgmt_migration_rows'),$run)));
        self::assertSame('verified',$this->finish($run)['state']);
        self::assertSame('6',self::$db->scalar(self::$db->prepare('SELECT COUNT(*) FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_stage_results'),$run)));
    }
    public function testJournalRecoversStartEvenIfConveniencePointerWasLost(): void {
        $run=$this->start(); self::$db->setOption('cor_mgmt_current_migration','');
        self::assertSame($run,$this->start());
        self::assertSame('1',self::$db->scalar(self::$db->prepare('SELECT COUNT(*) FROM %i',self::$db->table('cor_mgmt_migration_runs'))));
    }
    public function testCompetingConnectionCannotAcquireMigrationLock(): void {
        $otherConnection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $otherConnection->set_prefix(self::$prefix);
        $other=new Database($otherConnection);
        try {
            self::$db->locked(function () use ($other): void {
                try { $other->locked(static fn()=>true); self::fail('Competing lock was acquired'); }
                catch (MigrationException $e) { self::assertSame('migration_locked',$e->getMessage()); }
            });
            self::assertTrue($other->locked(static fn()=>true));
        } finally { $otherConnection->close(); }
    }
    public function testUnownedTableCollisionIsNotAdoptedOrDeleted(): void {
        $db=self::context('cortest_install_'.bin2hex(random_bytes(4)).'_');
        try {
            $db->execute($db->prepare('CREATE TABLE %i (id bigint PRIMARY KEY, sentinel text) ENGINE=InnoDB',$db->table('h3_mgmt_races')));
            $db->wpdb->insert($db->table('h3_mgmt_races'),['id'=>1,'sentinel'=>'preserve']);
            try { (new Installer($db))->fresh(); self::fail('Collision was adopted'); }
            catch (MigrationException $e) { self::assertSame('table_name_collision',$e->getMessage()); }
            self::assertSame('preserve',$db->scalar($db->prepare('SELECT sentinel FROM %i',$db->table('h3_mgmt_races'))));
            self::assertNull($db->option('h3_mgmt_db_version'));
        } finally { self::cleanup($db); }
    }
    public function testInterruptedFreshDdlCanResumeWithoutAdvancingVersionEarly(): void {
        $db=self::context('cortest_install_'.bin2hex(random_bytes(4)).'_');
        $table=$db->table('h3_mgmt_stages');
        $filter=static fn($query)=>str_starts_with($query,'CREATE TABLE') && str_contains($query,$table)?'INVALID SYNTHETIC SQL':$query;
        try {
            add_filter('query',$filter); $db->wpdb->suppress_errors(true);
            try { (new Installer($db))->fresh(); self::fail('Failure injection did not run'); }
            catch (MigrationException $e) { self::assertSame('database_query_failed',$e->getMessage()); }
            finally { remove_filter('query',$filter); $db->wpdb->suppress_errors(false); }
            self::assertNull($db->option('h3_mgmt_db_version')); self::assertTrue((new Installer($db))->pendingFresh());
            (new Installer($db))->fresh();
            self::assertSame('2.8',$db->option('h3_mgmt_db_version'));
            $db->wpdb->insert($db->table('h3_mgmt_teams'),['team_name'=>'Keep data']);
            (new Installer($db))->fresh();
            self::assertSame('Keep data',$db->scalar($db->prepare('SELECT team_name FROM %i',$db->table('h3_mgmt_teams'))));
        } finally { self::cleanup($db); }
    }
    public function testInvalidSourceIdsOrMissingUniqueIndexStopPreflight(): void {
        self::$db->execute(self::$db->prepare('ALTER TABLE %i DROP PRIMARY KEY, MODIFY id bigint unsigned NOT NULL',self::$db->table('h3_mgmt_xchange')));
        try {
            $this->expectExceptionMessage('source_id_not_unique'); (new Preflight(self::$db))->run();
        } finally {
            self::$db->execute(self::$db->prepare('ALTER TABLE %i ADD PRIMARY KEY (id), MODIFY id bigint unsigned NOT NULL AUTO_INCREMENT',self::$db->table('h3_mgmt_xchange')));
        }
    }
    public function testUnknownBinaryColumnsAndLatin1ArePreservedByteForByte(): void {
        $table=self::$db->table('h3_mgmt_xchange');
        self::$db->execute(self::$db->prepare('ALTER TABLE %i ADD opaque varbinary(16) NULL, ADD legacy_name varchar(80) CHARACTER SET latin1 NULL, ADD legacy_wide varchar(16) CHARACTER SET utf32 NULL',$table));
        try {
            self::$db->execute(self::$db->prepare("INSERT INTO %i (id,message,opaque,legacy_name,legacy_wide) VALUES (1,'synthetic',UNHEX('00FF81'),UNHEX('E4'),CONVERT(CONVERT(UNHEX('C3A4') USING utf8mb4) USING utf32))",$table));
            $manifest=(new Preflight(self::$db))->run();
            self::assertContains('unknown_columns_preserved:h3_mgmt_xchange:3',$manifest->issues);
            $run=$this->start(); $this->finish($run);
            $payload=self::$db->scalar(self::$db->prepare("SELECT payload FROM %i WHERE run_id=%s AND source_table='h3_mgmt_xchange'",self::$db->table('cor_mgmt_migration_rows'),$run));
            $row=RawRow::fromPayload($payload);
            self::assertSame(hex2bin('00FF81'),$row->value('opaque'));
            self::assertSame(hex2bin('E4'),$row->value('legacy_name'));
            self::assertSame(hex2bin('000000E4'),$row->value('legacy_wide'));
        } finally { self::$db->execute(self::$db->prepare('ALTER TABLE %i DROP opaque, DROP legacy_name, DROP legacy_wide',$table)); }
    }
    public function testZeroAndMaximumUnsignedIdsSurviveSmallBatches(): void {
        $mode=self::$db->scalar('SELECT @@SESSION.sql_mode');
        self::$db->execute("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        try {
            self::$db->execute(self::$db->prepare("INSERT INTO %i (id,message) VALUES (0,'zero'),(18446744073709551615,'maximum')",self::$db->table('h3_mgmt_xchange')));
            $run=$this->start(); $this->finish($run,1);
            $ids=self::$db->rows(self::$db->prepare("SELECT source_id FROM %i WHERE run_id=%s AND source_table='h3_mgmt_xchange' ORDER BY source_id",self::$db->table('cor_mgmt_migration_rows'),$run));
            self::assertSame([['source_id'=>'0'],['source_id'=>'18446744073709551615']],$ids);
        } finally { self::$db->execute(self::$db->prepare('SET SESSION sql_mode=%s',$mode)); }
    }
    public function testNontransactionalSourceIsCopiedWithoutConvertingIt(): void {
        $table=self::$db->table('h3_mgmt_invitations');
        self::$db->execute(self::$db->prepare('ALTER TABLE %i ENGINE=MyISAM',$table));
        try {
            self::assertContains('nontransactional_source:h3_mgmt_invitations',(new Preflight(self::$db))->run()->issues);
            self::assertSame('verified',$this->finish($this->start())['state']);
            self::assertSame('MyISAM',(new Inspector(self::$db))->metadata($table)['table']['ENGINE']);
        } finally { self::$db->execute(self::$db->prepare('ALTER TABLE %i ENGINE=InnoDB',$table)); }
    }
    public function testCorruptedProjectionAndUnexpectedExtraRowsFailVerification(): void {
        $run=$this->start();
        for ($i=0;$i<count(Catalogue::legacy());++$i) { $this->runner()->step($run,100); }
        self::$db->wpdb->update(self::$db->table('cor_mgmt_stage_results'),['rank_value'=>999],['run_id'=>$run,'team_id'=>42,'legacy_slot'=>1]);
        try { $this->runner()->step($run); self::fail('Changed rank verified'); }
        catch (MigrationException $e) { self::assertSame('target_projection_changed',$e->getMessage()); }
        self::$db->wpdb->update(self::$db->table('cor_mgmt_stage_results'),['rank_value'=>2],['run_id'=>$run,'team_id'=>42,'legacy_slot'=>1]);
        self::$db->wpdb->insert(self::$db->table('cor_mgmt_stage_results'),['run_id'=>$run,'team_id'=>999,'legacy_slot'=>1,'source_row_hash'=>str_repeat('a',64)]);
        try { $this->runner()->step($run); self::fail('Extra row verified'); }
        catch (MigrationException $e) { self::assertSame('target_comparison_failed',$e->getMessage()); }
    }
    public function testLostAdvisoryLockIsDetected(): void {
        self::$db->locked(function (): void {
            self::$db->assertLock();
            self::$db->scalar('SELECT RELEASE_ALL_LOCKS()');
            $this->expectExceptionMessage('migration_lock_lost'); self::$db->assertLock();
        });
    }
    public function testChangedTargetDefaultIsRejectedWithoutRepair(): void {
        self::$db->execute(self::$db->prepare("ALTER TABLE %i ALTER COLUMN unit_status SET DEFAULT 'changed'",self::$db->table('cor_mgmt_legacy_amounts')));
        try {
            $this->expectExceptionMessage('target_schema_mismatch'); (new Installer(self::$db))->shadow();
        } finally { self::$db->execute(self::$db->prepare("ALTER TABLE %i ALTER COLUMN unit_status SET DEFAULT 'unresolved'",self::$db->table('cor_mgmt_legacy_amounts'))); }
    }
    public function testUnknownPhaseIsPreservedAndNeverGuessed(): void {
        self::$db->wpdb->update(self::$db->table('h3_mgmt_races'),['setting'=>'{"status":99}'],['id'=>9]);
        self::assertContains('unmapped_race_phase:1',(new Preflight(self::$db))->run()->issues);
        $run=$this->start(); $this->finish($run);
        self::assertSame([['source_phase'=>null,'target_phase'=>null]],self::$db->rows(self::$db->prepare('SELECT source_phase,target_phase FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_race_states'),$run)));
    }

    public function testAmbiguousStageNumbersRemainUnassignedInsteadOfPickingOne(): void {
        self::$db->wpdb->insert(self::$db->table('h3_mgmt_stages'),['id'=>6,'race_id'=>9,'route_id'=>7,'number'=>1]);
        self::assertContains('unresolved_result_slots:6',(new Preflight(self::$db))->run()->issues);
        $run=$this->start(); $this->finish($run);
        self::assertNull(self::$db->scalar(self::$db->prepare('SELECT stage_id FROM %i WHERE run_id=%s AND team_id=42 AND legacy_slot=1',self::$db->table('cor_mgmt_stage_results'),$run)));
    }
    public function testUnsupportedFutureStepVersionCannotResume(): void {
        $run=$this->start();
        self::$db->wpdb->update(self::$db->table('cor_mgmt_migration_runs'),['migration_version'=>'future-step-v99'],['run_id'=>$run]);
        $this->expectExceptionMessage('unknown_migration_run'); $this->runner()->step($run);
    }
    public function testSelectedGenerationCannotBeAbandoned(): void {
        $run=$this->start(); self::$db->setOption('cor_mgmt_active_generation',$run);
        try {
            $this->expectExceptionMessage('active_generation_in_use'); $this->runner()->abandon($run);
        } finally { self::$db->execute(self::$db->prepare('DELETE FROM %i WHERE option_name=%s',self::$db->wpdb->options,'cor_mgmt_active_generation')); }
    }

    public function testActivationOnFreshWordPressCreatesTablesAndCapabilities(): void {
        $db=self::context('cortest_install_'.bin2hex(random_bytes(4)).'_');
        $oldDb=$GLOBALS['wpdb']; $oldRoles=$GLOBALS['wp_roles'];
        try {
            $GLOBALS['wpdb']=$db->wpdb; wp_cache_flush(); $GLOBALS['wp_roles']=new \WP_Roles();
            $missing=\Cor\Mgmt\WordPress\Lifecycle::activate();
            self::assertInstanceOf(\WP_Error::class,$missing); self::assertSame('cor_mgmt_role',$missing->get_error_code());
            self::assertFalse($db->exists($db->table('h3_mgmt_races')));
            populate_roles();
            self::assertTrue(\Cor\Mgmt\WordPress\Lifecycle::activate());
            self::assertTrue(\Cor\Mgmt\WordPress\Lifecycle::ready());
            self::assertSame('2.8',$db->option('h3_mgmt_db_version'));
            self::assertSame(Catalogue::STORAGE_VERSION,$db->option('cor_mgmt_storage_version'));
            self::assertTrue(get_role('administrator')->has_cap('h3_mgmt_edit_races'));
            self::assertTrue(\Cor\Mgmt\WordPress\Lifecycle::activate());
        } finally {
            $GLOBALS['wpdb']=$oldDb; $GLOBALS['wp_roles']=$oldRoles; wp_cache_flush(); self::cleanup($db);
        }
    }

    public function testRacePhaseIsDecodedFromDeclaredUtf32WithoutChangingSource(): void {
        $table=self::$db->table('h3_mgmt_races');
        self::$db->execute(self::$db->prepare('ALTER TABLE %i MODIFY setting longtext CHARACTER SET utf32 NULL',$table));
        try {
            self::assertSame(['9'],(new Preflight(self::$db))->run()->archiveIds);
            $before=$this->sourceState(); $run=$this->start(); $this->finish($run);
            self::assertSame('4',self::$db->scalar(self::$db->prepare('SELECT target_phase FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_race_states'),$run)));
            self::assertSame($before,$this->sourceState());
        } finally { self::$db->execute(self::$db->prepare('ALTER TABLE %i MODIFY setting longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',$table)); }
    }

    public function testAdjacentHighTeamIdsRemainDistinctInResultProjection(): void {
        foreach (['18446744073709551614','18446744073709551615'] as $id) {
            self::$db->execute(self::$db->prepare('INSERT INTO %i (id,race_id,route_id,team_name) VALUES (CAST(%s AS UNSIGNED),9,7,%s)',self::$db->table('h3_mgmt_teams'),$id,'Synthetic high ID'));
        }
        $run=$this->start();
        self::assertSame('verified',$this->finish($run,1)['state']);
        self::assertSame('18',self::$db->scalar(self::$db->prepare('SELECT COUNT(*) FROM %i WHERE run_id=%s',self::$db->table('cor_mgmt_stage_results'),$run)));
    }
    public function testNumericIdPaginationNeverUsesHexAliasOrdering(): void {
        $ids=['1','2','9','10','11','19','100','101','999'];
        foreach ($ids as $id) { self::$db->execute(self::$db->prepare("INSERT INTO %i (id,message) VALUES (CAST(%s AS UNSIGNED),'Synthetic numeric ordering')",self::$db->table('h3_mgmt_xchange'),$id)); }
        $run=$this->start(); self::assertSame('verified',$this->finish($run,2)['state']);
        $rows=self::$db->rows(self::$db->prepare("SELECT source_id FROM %i WHERE run_id=%s AND source_table='h3_mgmt_xchange' ORDER BY source_id",self::$db->table('cor_mgmt_migration_rows'),$run));
        self::assertSame($ids,array_column($rows,'source_id'));
    }

}
