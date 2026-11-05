<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use PHPUnit\Framework\TestCase;

/** Concurrent real requests on committed synthetic fixtures, never application data. */
final class ResultConcurrencyTest extends TestCase
{
    private \wpdb $connection;
    private Database $db;
    private int $base;
    private string $barrier;
    protected function setUp(): void
    {
        global $wpdb;
        $this->connection=new \wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$this->connection->set_prefix($wpdb->prefix);$this->db=new Database($this->connection);
        $this->base=random_int(10000000,19000000);$this->barrier=sys_get_temp_dir().'/cor-mgmt-results-'.bin2hex(random_bytes(8));mkdir($this->barrier,0700);
        $owner=$this->base+1;
        self::assertNull($this->db->scalar($this->db->prepare('SELECT ID FROM %i WHERE ID=%d',$this->connection->users,$owner)));
        self::assertSame(1,$this->connection->insert($this->connection->users,['ID'=>$owner,'user_login'=>'result_worker_'.$owner,'user_pass'=>'synthetic-unused','user_email'=>'result-worker@example.invalid','display_name'=>'Synthetic']));
        $this->connection->insert($this->connection->usermeta,['user_id'=>$owner,'meta_key'=>$wpdb->prefix.'capabilities','meta_value'=>serialize(['subscriber'=>true])]);
        $this->connection->insert($this->db->table('h3_mgmt_races'),['id'=>$this->base,'name'=>'Synthetic result concurrency','setting'=>json_encode(['status'=>3,'extra_point_amount'=>5]),'information_text'=>'{}']);
        $this->connection->insert($this->db->table('h3_mgmt_routes'),['id'=>$this->base,'race_id'=>$this->base,'name'=>'Results','user_id'=>$owner]);
        $this->connection->insert($this->db->table('h3_mgmt_stages'),['id'=>$this->base,'race_id'=>$this->base,'route_id'=>$this->base,'number'=>1]);
        $this->connection->insert($this->db->table('cor_mgmt_scoring_profiles'),['race_id'=>$this->base,'version'=>'2.0.0']);
        foreach([0,1] as $offset){$this->connection->insert($this->db->table('h3_mgmt_teams'),['id'=>$this->base+$offset,'race_id'=>$this->base,'route_id'=>$this->base,'team_name'=>'Synthetic '.$offset,'complete'=>1]);}
    }
    protected function tearDown(): void
    {
        foreach(['cor_mgmt_result_history','cor_mgmt_results','cor_mgmt_scoring_profiles','h3_mgmt_teams','h3_mgmt_stages','h3_mgmt_routes'] as $suffix){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE race_id=%d',$this->db->table($suffix),$this->base));}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id=%d',$this->db->table('h3_mgmt_races'),$this->base));
        foreach([$this->connection->usermeta=>'user_id',$this->connection->users=>'ID'] as $table=>$field){$this->db->execute($this->db->prepare('DELETE FROM %i WHERE %i=%d',$table,$field,$this->base+1));}
        foreach(glob($this->barrier.'/*')?:[] as $file){if(is_file($file)){unlink($file);}}rmdir($this->barrier);$this->connection->close();wp_cache_flush();
    }
    /** @param list<array{mode:string,user:int,value:string}> $requests @return list<array<string,mixed>> */
    private function runWorkers(array $requests): array
    {
        foreach(glob($this->barrier.'/*')?:[] as $file){unlink($file);}
        $jobs=[];$environment=getenv();$environment['COR_TEST_BARRIER_DIR']=$this->barrier;$environment['COR_TEST_RESULT_REVISION']=(new \Cor\Mgmt\Infrastructure\WordPress\ResultRepository($this->db))->context($this->base)->revision();
        try{
            foreach($requests as $index=>$request){
                $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/tools/result-worker.php',$request['mode'],(string)$request['user'],(string)$this->base,$request['value']],
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
    public function testConcurrentSubmissionsHaveOneWinnerWithoutMixedRows(): void
    {
        $results=$this->runWorkers([['mode'=>'0','user'=>$this->base+1,'value'=>'1'],['mode'=>'1','user'=>$this->base+1,'value'=>'3']]);
        self::assertCount(1,array_filter($results,static fn(array $result):bool=>$result['success']===true));
        $loser=$results[0]['success']?1:0;self::assertContains($results[$loser]['code'],['migration_locked','stale_results']);
        $rows=$this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d ORDER BY team_id',$this->db->table('cor_mgmt_results'),$this->base));
        self::assertCount(2,$rows);self::assertSame('17',$rows[1]['points']);
        $winner=$loser===0?1:0;self::assertSame($winner===0?'20':'14',$rows[0]['points']);
        self::assertSame('1',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE race_id=%d',$this->db->table('cor_mgmt_result_history'),$this->base)));
    }
}
