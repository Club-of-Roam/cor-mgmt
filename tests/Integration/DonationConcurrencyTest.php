<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\DonationFrontend;
use PHPUnit\Framework\TestCase;

final class DonationConcurrencyTest extends TestCase
{
    public function testConcurrentObservationsCannotClaimOneOpinionTwice():void
    {
        global $wpdb;$db=new Database($wpdb);$base=random_int(20000000,29000000);$directory=sys_get_temp_dir().'/cor-mgmt-donations-'.bin2hex(random_bytes(8));mkdir($directory,0700);$jobs=[];
        self::assertSame(1,$wpdb->insert($db->table('h3_mgmt_races'),['id'=>$base,'name'=>'Synthetic donation concurrency','setting'=>json_encode(['status'=>1,'donation'=>1,'betterplace_redirect_link'=>'https://betterplace.org/de/fundraising-events/123'])]));
        self::assertSame(1,$wpdb->insert($db->table('h3_mgmt_teams'),['id'=>$base,'race_id'=>$base,'team_name'=>'Synthetic concurrent donor']));
        try {
            $rows=[];foreach([0,1] as $index){$rows[]=DonationFrontend::ledger()->begin($base,$base,'sponsor','synthetic-session-'.$index,'en');}
            $environment=getenv();$environment['COR_TEST_BARRIER_DIR']=$directory;$environment['COR_TEST_DONATION_MESSAGE']=implode(' ',array_column($rows,'token'));
            foreach($rows as $index=>$row){
                $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/tools/donation-worker.php',(string)$index,(string)$base,$row['token'],'synthetic-session-'.$index],[0=>['pipe','r'],1=>['file',$directory.'/out-'.$index,'w'],2=>['file',$directory.'/err-'.$index,'w']],$pipes,dirname(__DIR__,2),$environment);
                self::assertIsResource($process);fclose($pipes[0]);$jobs[]=$process;
            }
            $until=microtime(true)+45;
            while(count(glob($directory.'/ready-*')?:[])!==2){if(microtime(true)>$until){self::fail('Donation workers did not reach provider barrier');}usleep(20000);clearstatcache();}
            touch($directory.'/go');$results=[];
            foreach($jobs as $index=>$process){self::assertSame(0,proc_close($process));$jobs[$index]=null;$results[]=json_decode(file_get_contents($directory.'/out-'.$index),true,512,JSON_THROW_ON_ERROR);}
            self::assertCount(1,array_filter($results,static fn(array $row):bool=>$row['success']));
            $loser=$results[0]['success']?1:0;self::assertContains($results[$loser]['code'],['opinion_already_used','migration_locked']);
            self::assertSame('1',$db->scalar($db->prepare("SELECT COUNT(*) FROM %i WHERE race_id=%d AND state='confirmed'",$db->table('cor_mgmt_donations'),$base)));
            self::assertSame('1',$db->scalar($db->prepare("SELECT COUNT(*) FROM %i WHERE race_id=%d AND kind='confirmed'",$db->table('cor_mgmt_donation_history'),$base)));
        }finally{
            foreach($jobs as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}
            foreach(['cor_mgmt_donation_history','cor_mgmt_donations','h3_mgmt_sponsors','h3_mgmt_teams'] as $suffix){$db->execute($db->prepare('DELETE FROM %i WHERE race_id=%d',$db->table($suffix),$base));}
            $db->execute($db->prepare('DELETE FROM %i WHERE id=%d',$db->table('h3_mgmt_races'),$base));
            foreach(glob($directory.'/*')?:[] as $file){if(is_file($file)){unlink($file);}}rmdir($directory);wp_cache_flush();
        }
    }
}
