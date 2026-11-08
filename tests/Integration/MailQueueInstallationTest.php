<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Installer;
use Cor\Mgmt\Infrastructure\WordPress\Schema\MailQueueSchema;
use Cor\Mgmt\Application\Migration\MigrationException;
use PHPUnit\Framework\TestCase;

final class MailQueueInstallationTest extends TestCase
{
    private Database $db;
    protected function setUp():void
    {
        $connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$connection->set_prefix('cortest_mail_queue_'.bin2hex(random_bytes(4)).'_');$this->db=new Database($connection);
        $this->db->execute($this->db->prepare('CREATE TABLE %i (option_id bigint unsigned AUTO_INCREMENT PRIMARY KEY,option_name varchar(191) UNIQUE,option_value longtext,autoload varchar(20)) ENGINE=InnoDB',$connection->options));
    }
    protected function tearDown():void
    {
        if(!preg_match('/^cortest_mail_queue_[a-f0-9]{8}_$/D',$this->db->wpdb->prefix)){throw new \RuntimeException('Unsafe fixture cleanup');}
        foreach(array_keys(MailQueueSchema::definitions()) as $suffix){$this->db->execute($this->db->prepare('DROP TABLE IF EXISTS %i',$this->db->table($suffix)));}
        $this->db->execute($this->db->prepare('DROP TABLE %i',$this->db->wpdb->options));$this->db->wpdb->close();wp_cache_flush();
    }
    private function rejected(string $reason,callable $operation):void{try{$operation();self::fail('Expected '.$reason);}catch(MigrationException $error){self::assertSame($reason,$error->getMessage());}}
    public function testAdditiveRepeatedInstallPreservesDataAndMarkers():void
    {
        $installer=new Installer($this->db);$installer->mailQueue();self::assertTrue(MailQueueSchema::ready($this->db));
        $this->db->execute($this->db->prepare("INSERT INTO %i (message_key,input_hash,race_id,occurrence,action,recipient,language,template_revision,context,subject,message,status,claim_token,created_at,updated_at,reason) VALUES (%s,%s,1,'synthetic:1','team-creation','member@example.invalid','en',1,'{}','Subject','Body','pending','',1,1,'')",$this->db->table('cor_mgmt_mail_queue'),str_repeat('a',64),str_repeat('b',64)));
        $installer->mailQueue();self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_mail_queue'))));self::assertNull($this->db->option('h3_mgmt_db_version'));self::assertFalse($this->db->exists($this->db->table('h3_mgmt_sponsors')));
    }
    public function testUnownedTableCollisionIsNeverAdopted():void
    {
        $table=$this->db->table('cor_mgmt_mail_queue');$this->db->execute($this->db->prepare('CREATE TABLE %i (id int PRIMARY KEY) ENGINE=InnoDB',$table));
        $this->rejected('table_name_collision',fn()=>(new Installer($this->db))->mailQueue());self::assertFalse(MailQueueSchema::ready($this->db));
    }
    public function testOnlyRunningManifestMayRecreateAnUnfinishedTable():void
    {
        $installer=new Installer($this->db);$installer->mailQueue();$table=$this->db->table('cor_mgmt_mail_history');$this->db->execute($this->db->prepare('DROP TABLE %i',$table));
        $this->rejected('target_schema_mismatch',fn()=>$installer->mailQueue());
        $state=json_decode($this->db->option('cor_mgmt_mail_queue_install'),true);$state['state']='running';$this->db->setOption('cor_mgmt_mail_queue_install',json_encode($state));$installer->mailQueue();self::assertTrue(MailQueueSchema::ready($this->db));
    }
    public function testDeduplicationKeyIsPartOfTheVerifiedPlan():void
    {
        $installer=new Installer($this->db);$installer->mailQueue();$table=$this->db->table('cor_mgmt_mail_queue');$this->db->execute($this->db->prepare('ALTER TABLE %i DROP INDEX UNIQUE_message',$table));
        $this->rejected('target_schema_mismatch',fn()=>$installer->mailQueue());
    }
}
