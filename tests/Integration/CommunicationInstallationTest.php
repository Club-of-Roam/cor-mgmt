<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema;
use Cor\Mgmt\Application\Migration\MigrationException;
use PHPUnit\Framework\TestCase;

final class CommunicationInstallationTest extends TestCase
{
    private Database $db;
    protected function setUp():void
    {
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$connection->set_prefix('cortest_communication_'.bin2hex(random_bytes(4)).'_');$this->db=new Database($connection);
        $this->db->execute($this->db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY,option_name varchar(191) UNIQUE,option_value longtext,autoload varchar(20)) ENGINE=InnoDB',$connection->options));
    }
    protected function tearDown():void
    {
        if(!preg_match('/^cortest_communication_[a-f0-9]{8}_$/D',$this->db->wpdb->prefix)){throw new \RuntimeException('Unsafe fixture cleanup');}
        foreach(array_keys(CommunicationSchema::definitions()) as $suffix){$this->db->execute($this->db->prepare('DROP TABLE IF EXISTS %i',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DROP TABLE %i',$this->db->wpdb->options));$this->db->wpdb->close();wp_cache_flush();
    }
    private function rejected(string $reason,callable $operation):void{try{$operation();self::fail('Expected '.$reason);}catch(MigrationException $error){self::assertSame($reason,$error->getMessage());}}
    public function testAdditiveRepeatedInstallPreservesDataAndMarkers():void
    {
        $installer=new Installer($this->db);$installer->communication();self::assertTrue(CommunicationSchema::ready($this->db));
        $this->db->execute($this->db->prepare("INSERT INTO %i (race_id,action,language,enabled,subject,message,revision,updated_at) VALUES (1,'team-creation','en',0,'Synthetic','Body',1,1)",$this->db->table('cor_mgmt_response_templates')));
        $installer->communication();self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_response_templates'))));self::assertNull($this->db->option('h3_mgmt_db_version'));self::assertFalse($this->db->exists($this->db->table('h3_mgmt_sponsors')));
    }
    public function testUnownedTableCollisionIsNeverAdopted():void
    {
        $table=$this->db->table('cor_mgmt_response_templates');$this->db->execute($this->db->prepare('CREATE TABLE %i (id int PRIMARY KEY) ENGINE=InnoDB',$table));
        $this->rejected('table_name_collision',fn()=>(new Installer($this->db))->communication());self::assertFalse(CommunicationSchema::ready($this->db));
    }
    public function testOnlyRunningManifestMayRecreateAnUnfinishedTable():void
    {
        $installer=new Installer($this->db);$installer->communication();$table=$this->db->table('cor_mgmt_communication_history');$this->db->execute($this->db->prepare('DROP TABLE %i',$table));
        $this->rejected('target_schema_mismatch',fn()=>$installer->communication());
        $state=json_decode($this->db->option('cor_mgmt_communication_install'),true);$state['state']='running';$this->db->setOption('cor_mgmt_communication_install',json_encode($state));$installer->communication();self::assertTrue(CommunicationSchema::ready($this->db));
    }
    public function testEventActionLanguageKeyIsPartOfTheVerifiedPlan():void
    {
        $installer=new Installer($this->db);$installer->communication();$table=$this->db->table('cor_mgmt_response_templates');$this->db->execute($this->db->prepare('ALTER TABLE %i DROP PRIMARY KEY, ADD PRIMARY KEY (race_id,action,language,revision)',$table));
        $this->rejected('target_schema_mismatch',fn()=>$installer->communication());
    }
}
