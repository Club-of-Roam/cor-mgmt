<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use PHPUnit\Framework\TestCase;

/** Real concurrent PHP processes, restricted to the explicitly isolated test database. */
final class TeamConcurrencyTest extends TestCase
{
    private \wpdb $connection;
    private Database $db;
    private int $base;
    private string $barrier;
    private bool $restoreEngine=false;
    protected function setUp(): void
    {
        global $wpdb;
        $this->connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$this->connection->set_prefix($wpdb->prefix);$this->db=new Database($this->connection);
        $this->base=random_int(2000000,9000000);
        $this->barrier=sys_get_temp_dir().'/cor-mgmt-participation-'.bin2hex(random_bytes(8));mkdir($this->barrier,0700);
        foreach(range(1,3) as $offset){
            $id=$this->base+$offset;
            self::assertNull($this->db->scalar($this->db->prepare('SELECT ID FROM %i WHERE ID=%d',$this->connection->users,$id)));
            self::assertSame(1,$this->connection->insert($this->connection->users,['ID'=>$id,'user_login'=>'race_worker_'.$id,'user_pass'=>'synthetic-unused','user_email'=>'worker_'.$id.'@example.invalid','display_name'=>'Synthetic']));
            foreach(['first_name'=>'Synthetic','last_name'=>'Participant',$wpdb->prefix.'capabilities'=>serialize(['subscriber'=>true])] as $key=>$value){$this->connection->insert($this->connection->usermeta,['user_id'=>$id,'meta_key'=>$key,'meta_value'=>$value]);}
        }
        $this->connection->insert($this->db->table('h3_mgmt_races'),['id'=>$this->base,'name'=>'Synthetic concurrency','setting'=>json_encode(['status'=>1,'num_teammember_min'=>1,'num_teammember'=>2,'startingpoint'=>1,'dis_fee'=>1,'dis_waiver'=>1,'dis_shirt_size'=>1,'dis_mobile_inf'=>1]),'information_text'=>'{}']);
        $this->connection->insert($this->db->table('h3_mgmt_routes'),['id'=>$this->base,'race_id'=>$this->base,'name'=>'One place','max_teams'=>1]);
    }
    protected function tearDown(): void
    {
        if($this->restoreEngine){$this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=InnoDB',$this->db->table('h3_mgmt_invitations')));}
        foreach(['h3_mgmt_invitations','h3_mgmt_teammates','cor_mgmt_invitation_tokens','cor_mgmt_membership_history'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE team_id IN (%d,%d)',$this->db->table($suffix),$this->base,$this->base+1));}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id IN (%d,%d)',$this->db->table('h3_mgmt_teams'),$this->base,$this->base+1));
        foreach(['h3_mgmt_routes','h3_mgmt_races'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE id=%d',$this->db->table($suffix),$this->base));}
        foreach([$this->connection->usermeta=>'user_id',$this->connection->users=>'ID'] as $table=>$field){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE %i IN (%d,%d,%d)',$table,$field,$this->base+1,$this->base+2,$this->base+3));}
        foreach(glob($this->barrier.'/*')?:[] as $file){if(is_file($file)){unlink($file);}}rmdir($this->barrier);$this->connection->close();wp_cache_flush();
    }
    private function team(int $id,int $user,int $complete): void
    {
        $this->connection->insert($this->db->table('h3_mgmt_teams'),['id'=>$id,'race_id'=>$this->base,'team_name'=>'Synthetic','complete'=>$complete,'route_id'=>0]);
        $this->connection->insert($this->db->table('h3_mgmt_teammates'),['team_id'=>$id,'user_id'=>$user,'paid'=>1,'waiver'=>1,'language'=>'en']);
    }
    /** @param list<array{mode:string,user:int,value:string}> $requests @return list<array<string,mixed>> */
    private function runWorkers(array $requests): array
    {
        foreach(glob($this->barrier.'/*')?:[] as $file){unlink($file);}
        $jobs=[];$environment=getenv();$environment['COR_TEST_BARRIER_DIR']=$this->barrier;
        try{
            foreach($requests as $index=>$request){
                $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/tools/participation-worker.php',$request['mode'],(string)$request['user'],(string)$this->base,$request['value']],
                    [0=>['pipe','r'],1=>['file',$this->barrier.'/out-'.$index,'w'],2=>['file',$this->barrier.'/err-'.$index,'w']],$pipes,dirname(__DIR__,2),$environment);
                self::assertIsResource($process);fclose($pipes[0]);$jobs[]=$process;
            }
            $until=microtime(true)+45;
            while(count(glob($this->barrier.'/ready-*')?:[])!==count($jobs)){
                if(microtime(true)>$until){self::fail('Synthetic workers did not reach the barrier.');}usleep(20000);clearstatcache();
            }
            touch($this->barrier.'/go');$results=[];
            foreach($jobs as $index=>$process){
                self::assertSame(0,proc_close($process),'Synthetic worker failed.');$jobs[$index]=null;
                $json=json_decode(file_get_contents($this->barrier.'/out-'.$index),true,512,JSON_THROW_ON_ERROR);self::assertIsArray($json);$results[]=$json;
            }
            return $results;
        }finally{foreach($jobs as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}}
    }
    public function testTwoProcessesCannotAllocateTheLastRoutePlaceTwice(): void
    {
        $this->team($this->base,$this->base+1,1);$this->team($this->base+1,$this->base+2,1);
        $requests=[['mode'=>'route','user'=>$this->base+1,'value'=>(string)$this->base],['mode'=>'route','user'=>$this->base+2,'value'=>(string)$this->base]];
        $results=$this->runWorkers($requests);
        self::assertCount(1,array_filter($results,static fn(array $result):bool=>$result['success']===true));
        self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE route_id=%d AND complete=1',$this->db->table('h3_mgmt_teams'),$this->base)));
        $loser=$results[0]['success']?1:0;self::assertContains($results[$loser]['code'],['migration_locked','route_full']);
        $retry=$this->runWorkers([$requests[$loser]]);self::assertSame('route_full',$retry[0]['code']);
    }
    public function testTwoLegacyInvitationsCannotConsumeTheLastMembershipPlaceTwice(): void
    {
        $this->team($this->base,$this->base+3,1);
        self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('h3_mgmt_invitations'))));
        $this->restoreEngine=true;$this->db->execute($this->db->prepare('ALTER TABLE %i ENGINE=MyISAM',$this->db->table('h3_mgmt_invitations')));
        $requests=[];
        foreach([1,2] as $offset){$code=(string)(1000000000000000+$this->base+$offset);$this->connection->insert($this->db->table('h3_mgmt_invitations'),['team_id'=>$this->base,'email'=>'legacy'.$offset.'@example.invalid','code'=>$code]);$requests[]=['mode'=>'join','user'=>$this->base+$offset,'value'=>$code];}
        $results=$this->runWorkers($requests);self::assertCount(1,array_filter($results,static fn(array $result):bool=>$result['success']===true));
        self::assertSame('2',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE team_id=%d',$this->db->table('h3_mgmt_teammates'),$this->base)));
        self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE team_id=%d AND consumed_at IS NOT NULL',$this->db->table('cor_mgmt_invitation_tokens'),$this->base)));
        self::assertSame('2',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE team_id=%d',$this->db->table('h3_mgmt_invitations'),$this->base)));
        $loser=$results[0]['success']?1:0;$retry=$this->runWorkers([$requests[$loser]]);self::assertSame('team_full',$retry[0]['code']);
    }
}
