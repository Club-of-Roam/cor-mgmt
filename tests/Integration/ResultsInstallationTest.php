<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ResultsSchema;
use PHPUnit\Framework\TestCase;

/** DDL is confined to disposable prefixes in the guarded synthetic database. */
final class ResultsInstallationTest extends TestCase
{
    private Database $db;
    protected function setUp(): void
    {
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        $connection->set_prefix('cortest_results_'.bin2hex(random_bytes(4)).'_');$this->db=new Database($connection);
        $this->db->execute($this->db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL UNIQUE, option_value longtext NOT NULL, autoload varchar(20) NOT NULL) ENGINE=InnoDB',$connection->options));
    }
    protected function tearDown(): void
    {
        if(!preg_match('/^cortest_results_[a-f0-9]{8}_$/D',$this->db->wpdb->prefix)){throw new \RuntimeException('Unsafe fixture cleanup');}
        foreach(array_keys(ResultsSchema::definitions()) as $suffix){$this->db->execute($this->db->prepare('DROP TABLE IF EXISTS %i',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DROP TABLE %i',$this->db->wpdb->options));$this->db->wpdb->close();wp_cache_flush();
    }
    private function rejects(string $reason,callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(MigrationException $error){self::assertSame($reason,$error->getMessage());}
    }
    public function testAdditiveInstallationIsIdempotentAndDoesNotCreateOrChangeLegacyTables(): void
    {
        $installer=new Installer($this->db);self::assertFalse(ResultsSchema::ready($this->db));$installer->results();
        foreach(ResultsSchema::definitions() as $suffix=>$definition){(new Inspector($this->db))->verify($this->db->table($suffix),$definition);}
        self::assertTrue(ResultsSchema::ready($this->db));
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,version) VALUES (42,%s)',$this->db->table('cor_mgmt_scoring_profiles'),'2.0.0'));
        $before=$this->db->rows($this->db->prepare('SELECT * FROM %i',$this->db->table('cor_mgmt_scoring_profiles')));$installer->results();
        self::assertSame($before,$this->db->rows($this->db->prepare('SELECT * FROM %i',$this->db->table('cor_mgmt_scoring_profiles'))));
        self::assertFalse($this->db->exists($this->db->table('h3_mgmt_teams')));self::assertNull($this->db->option('h3_mgmt_db_version'));
    }
    public function testExistingUnownedTablesAreNotAdoptedEvenWithStorageMarker(): void
    {
        $this->db->setOption('cor_mgmt_storage_version',Catalogue::STORAGE_VERSION);
        $table=$this->db->table('cor_mgmt_scoring_profiles');$this->db->execute($this->db->prepare('CREATE TABLE %i (id int PRIMARY KEY, retained varchar(10)) ENGINE=InnoDB',$table));
        $this->db->execute($this->db->prepare("INSERT INTO %i VALUES (1,'keep')",$table));
        $this->rejects('table_name_collision',fn()=>(new Installer($this->db))->results());
        self::assertSame('keep',$this->db->scalar($this->db->prepare('SELECT retained FROM %i WHERE id=1',$table)));self::assertFalse(ResultsSchema::ready($this->db));
    }
    public function testRunningManifestResumesMissingTableButCompletedManifestNeverRecreatesMissingData(): void
    {
        $installer=new Installer($this->db);$installer->results();
        $state=json_decode($this->db->option('cor_mgmt_results_install'),true);$state['state']='running';$this->db->setOption('cor_mgmt_results_install',json_encode($state));
        $history=$this->db->table('cor_mgmt_result_history');$this->db->execute($this->db->prepare('DROP TABLE %i',$history));
        $installer->results();self::assertTrue($this->db->exists($history));
        $this->db->execute($this->db->prepare('DROP TABLE %i',$history));
        $this->rejects('target_schema_mismatch',fn()=>$installer->results());
        self::assertFalse($this->db->exists($history));
    }
    public function testEngineAndPlanChangesRequireReview(): void
    {
        $installer=new Installer($this->db);$installer->results();
        $table=$this->db->table('cor_mgmt_results');$this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=MyISAM',$table));
        $this->rejects('target_schema_mismatch',fn()=>$installer->results());
        $state=json_decode($this->db->option('cor_mgmt_results_install'),true);$state['plan']='unknown-future-plan';$this->db->setOption('cor_mgmt_results_install',json_encode($state));
        $this->rejects('installation_plan_changed',fn()=>$installer->results());
    }
}
