<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Migration\MigrationException;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Catalogue;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ParticipationSchema;
use PHPUnit\Framework\TestCase;

/** DDL is confined to disposable prefixes in the guarded synthetic database. */
final class ParticipationInstallationTest extends TestCase
{
    private Database $db;
    protected function setUp(): void
    {
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        $connection->set_prefix('cortest_participation_'.bin2hex(random_bytes(4)).'_');$this->db=new Database($connection);
        $this->db->execute($this->db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL UNIQUE, option_value longtext NOT NULL, autoload varchar(20) NOT NULL) ENGINE=InnoDB',$connection->options));
    }
    protected function tearDown(): void
    {
        if(!preg_match('/^cortest_participation_[a-f0-9]{8}_$/D',$this->db->wpdb->prefix)){throw new \RuntimeException('Unsafe fixture cleanup');}
        foreach(array_keys(ParticipationSchema::definitions()) as $suffix){$this->db->execute($this->db->prepare('DROP TABLE IF EXISTS %i',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DROP TABLE %i',$this->db->wpdb->options));$this->db->wpdb->close();wp_cache_flush();
    }
    private function rejects(string $reason,callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(MigrationException $error){self::assertSame($reason,$error->getMessage());}
    }
    public function testAdditiveInstallationIsIdempotentAndDoesNotCreateOrChangeLegacyTables(): void
    {
        $installer=new Installer($this->db);self::assertFalse(ParticipationSchema::ready($this->db));$installer->participation();
        foreach(ParticipationSchema::definitions() as $suffix=>$definition){(new Inspector($this->db))->verify($this->db->table($suffix),$definition);}
        self::assertTrue(ParticipationSchema::ready($this->db));
        $this->db->execute($this->db->prepare('INSERT INTO %i (token_hash,team_id,email,created_at) VALUES (%s,42,%s,1)',$this->db->table('cor_mgmt_invitation_tokens'),str_repeat('a',64),'synthetic@example.invalid'));
        $before=$this->db->rows($this->db->prepare('SELECT * FROM %i',$this->db->table('cor_mgmt_invitation_tokens')));$installer->participation();
        self::assertSame($before,$this->db->rows($this->db->prepare('SELECT * FROM %i',$this->db->table('cor_mgmt_invitation_tokens'))));
        self::assertFalse($this->db->exists($this->db->table('h3_mgmt_teams')));self::assertNull($this->db->option('h3_mgmt_db_version'));
    }
    public function testExistingUnownedTablesAreNotAdoptedEvenWithStorageMarker(): void
    {
        $this->db->setOption('cor_mgmt_storage_version',Catalogue::STORAGE_VERSION);
        $table=$this->db->table('cor_mgmt_invitation_tokens');$this->db->execute($this->db->prepare('CREATE TABLE %i (id int PRIMARY KEY, retained varchar(10)) ENGINE=InnoDB',$table));
        $this->db->execute($this->db->prepare("INSERT INTO %i VALUES (1,'keep')",$table));
        $this->rejects('table_name_collision',fn()=>(new Installer($this->db))->participation());
        self::assertSame('keep',$this->db->scalar($this->db->prepare('SELECT retained FROM %i WHERE id=1',$table)));self::assertFalse(ParticipationSchema::ready($this->db));
    }
    public function testRunningManifestResumesMissingTableButCompletedManifestNeverRecreatesMissingData(): void
    {
        $installer=new Installer($this->db);$installer->participation();
        $state=json_decode($this->db->option('cor_mgmt_participation_install'),true);$state['state']='running';$this->db->setOption('cor_mgmt_participation_install',json_encode($state));
        $history=$this->db->table('cor_mgmt_membership_history');$this->db->execute($this->db->prepare('DROP TABLE %i',$history));
        $installer->participation();self::assertTrue($this->db->exists($history));
        $this->db->execute($this->db->prepare('DROP TABLE %i',$history));
        $this->rejects('target_schema_mismatch',fn()=>$installer->participation());
        self::assertFalse($this->db->exists($history));
    }
    public function testUniqueTokenIndexIsVerifiedAndPlanChangesRequireReview(): void
    {
        $installer=new Installer($this->db);$installer->participation();
        $table=$this->db->table('cor_mgmt_invitation_tokens');
        $this->db->execute($this->db->prepare('ALTER TABLE %i DROP INDEX %i, ADD INDEX %i (token_hash)',$table,'UNIQUE_token_hash','UNIQUE_token_hash'));
        $this->rejects('target_schema_mismatch',fn()=>$installer->participation());
        $state=json_decode($this->db->option('cor_mgmt_participation_install'),true);$state['plan']='unknown-future-plan';$this->db->setOption('cor_mgmt_participation_install',json_encode($state));
        $this->rejects('installation_plan_changed',fn()=>$installer->participation());
    }
}
