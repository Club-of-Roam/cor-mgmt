<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Infrastructure\WordPress\Archive;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Ticker;
use Cor\Mgmt\Infrastructure\WordPress\TickerImport;
use Cor\Mgmt\WordPress\ArchiveBoundary;
use Cor\Mgmt\WordPress\Services;
use PHPUnit\Framework\TestCase;

final class ArchiveTest extends TestCase
{
    private Database $db;
    private Archive $archive;
    private int $user;
    private ?string $tickerState;
    private array $saved;
    private string $image;
    private string $url;
    protected function setUp(): void
    {
        global $wpdb;
        $this->saved=[$_POST,$_GET,$_SERVER['REQUEST_METHOD']??null,get_current_user_id()];
        $this->db=new Database($wpdb);$this->archive=ArchiveBoundary::archive();
        $this->tickerState=$this->db->option('cor_mgmt_ticker_transition');(new TickerImport($this->db))->start(true);
        $this->user=wp_create_user('synthetic_archive_member','synthetic-password','archive@example.invalid');self::assertIsInt($this->user);
        wp_update_user(['ID'=>$this->user,'display_name'=>'Frozen participant']);update_user_meta($this->user,'city','Frozen city');
        $upload=wp_upload_bits('synthetic-archive-'.bin2hex(random_bytes(4)).'.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));self::assertFalse($upload['error']);$this->image=$upload['file'];$this->url=$upload['url'];update_user_meta($this->user,'cor_mgmt_avatar',$this->url);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_races',['id'=>880191,'name'=>'Frozen event','setting'=>'{"status":3,"liveticker":1,"liveticker_front":1}','active'=>1]);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_routes',['id'=>880192,'race_id'=>880191,'name'=>'Frozen route']);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teams',['id'=>880193,'race_id'=>880191,'route_id'=>880192,'team_name'=>'Frozen team','team_pic'=>$this->url,'complete'=>1,'total_points'=>321]);
        $wpdb->insert($wpdb->prefix.'h3_mgmt_teammates',['team_id'=>880193,'user_id'=>$this->user]);
        (new Ticker($this->db,Services::get()->clock,static fn()=>true))->create($this->user,880191,'text','Frozen post','',0,0,str_repeat('a',32));
        $_POST=$_GET=[];$_SERVER['REQUEST_METHOD']='GET';wp_set_current_user(0);
    }
    protected function tearDown(): void
    {
        global $wpdb;
        foreach($this->db->rows($this->db->prepare('SELECT snapshot_id FROM %i WHERE race_id=880191',$this->db->table('cor_mgmt_archive_runs'))) as $run){foreach(['cor_mgmt_archive_rows','cor_mgmt_archive_media','cor_mgmt_archive_runs'] as $table){$wpdb->delete($this->db->table($table),['snapshot_id'=>$run['snapshot_id']]);}}
        delete_option('cor_mgmt_archive_active_880191');
        foreach(['h3_mgmt_teammates','h3_mgmt_invitations'] as $table){$wpdb->delete($this->db->table($table),['team_id'=>880193]);}
        foreach(['h3_mgmt_teams','h3_mgmt_routes','h3_mgmt_races','cor_mgmt_ticker_posts'] as $table){$wpdb->delete($this->db->table($table),[$table==='h3_mgmt_races'?'id':'race_id'=>880191]);}
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE actor_id=%d',$this->db->table('cor_mgmt_ticker_history'),$this->user));
        if($this->tickerState===null){delete_option('cor_mgmt_ticker_transition');}else{$this->db->setOption('cor_mgmt_ticker_transition',$this->tickerState);}
        wp_delete_user($this->user);if(is_file($this->image)){unlink($this->image);}
        [$_POST,$_GET,$_SERVER['REQUEST_METHOD'],$user]=$this->saved;wp_set_current_user($user);wp_cache_flush();self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function ready(string $mode='completion'): string
    {
        $id=$this->archive->start(880191,1,$mode);
        for($i=0;$i<150;++$i){if($this->archive->step($id,2)['state']==='ready'){return $id;}}
        self::fail('Snapshot did not verify');
    }
    private function rejects(string $reason,callable $operation): void
    {
        try{$operation();self::fail('Expected '.$reason);}catch(\RuntimeException $error){self::assertSame($reason,$error->getMessage());}
    }
    public function testCompletionFreezesProfilesScoresTickerAndImageBytes(): void
    {
        $id=$this->ready();$this->archive->complete($id);self::assertSame('sealed',$this->archive->run($id)['state']);self::assertSame($id,$this->archive->published(880191));
        $url=$this->archive->imageUrl($id,$this->url);self::assertStringContainsString('/cor-mgmt-archive/',$url);$original=hash_file('sha256',$this->image);
        wp_update_user(['ID'=>$this->user,'display_name'=>'Changed participant']);update_user_meta($this->user,'city','Changed city');
        $this->db->execute($this->db->prepare("UPDATE %i SET team_name='Changed team',total_points=1 WHERE id=880193",$this->db->table('h3_mgmt_teams')));
        $this->db->execute($this->db->prepare("UPDATE %i SET body='Changed post' WHERE race_id=880191",$this->db->table('cor_mgmt_ticker_posts')));
        file_put_contents($this->image,'Changed source file');
        $html=do_shortcode('[h3-teams race="880191"] [h3-ranking-table race="880191"] [h3-liveticker race="880191"]');
        foreach(['Frozen team','Frozen participant','Frozen city','321','Frozen post'] as $text){self::assertStringContainsString($text,$html);}
        foreach(['Changed team','Changed participant','Changed city','Changed post'] as $text){self::assertStringNotContainsString($text,$html);}
        self::assertSame($url,$this->archive->imageUrl($id,$this->url));self::assertNotSame($original,hash_file('sha256',$this->image));
        $this->archive->complete($id);self::assertSame('sealed',$this->archive->run($id)['state']);$this->rejects('archive_immutable',fn()=>$this->archive->abandon($id));
    }
    public function testPendingSnapshotBlocksCommandsBeforeFinalPhaseChange(): void
    {
        $id=$this->archive->start(880191,1,'completion');self::assertTrue(\H3_MGMT_Security::archived(880191));
        $this->rejects('archive_locked',fn()=>(new Ticker($this->db,Services::get()->clock,static fn()=>true))->create($this->user,880191,'text','No write','',0,0,str_repeat('b',32)));
        $this->archive->abandon($id);self::assertFalse(\H3_MGMT_Security::archived(880191));self::assertSame('abandoned',$this->archive->run($id)['state']);
    }
    public function testChangedSourceAndDamagedSnapshotFailWithoutPublishing(): void
    {
        $id=$this->archive->start(880191,1,'completion');$this->archive->step($id,1);$this->db->wpdb->insert($this->db->table('h3_mgmt_teams'),['id'=>880194,'race_id'=>880191,'team_name'=>'Late writer']);
        $this->rejects('archive_source_changed',fn()=>$this->archive->step($id));self::assertSame('',$this->archive->published(880191));
        $this->db->wpdb->delete($this->db->table('h3_mgmt_teams'),['id'=>880194]);$this->archive->abandon($id);$id=$this->ready();
        $this->db->execute($this->db->prepare("UPDATE %i SET payload='broken' WHERE snapshot_id=%s AND kind='team'",$this->db->table('cor_mgmt_archive_rows'),$id));
        $this->rejects('archive_content_changed',fn()=>$this->archive->complete($id));self::assertSame('ready',$this->archive->run($id)['state']);
    }
    public function testMigrationArchiveDoesNotMutateSourceOrPublishBeforeSwitch(): void
    {
        $before=$this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=880191',$this->db->table('h3_mgmt_races')));$id=$this->ready('migration');
        self::assertSame($before,$this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=880191',$this->db->table('h3_mgmt_races'))));self::assertSame('',$this->archive->published(880191));
        $this->rejects('invalid_archive_request',fn()=>$this->archive->complete($id));self::assertSame($id,$this->archive->start(880191,1,'migration'));
        foreach($this->archive->run($id) as $value){self::assertStringNotContainsString('synthetic-password',(string)$value);}
        $source=new \Cor\Mgmt\Infrastructure\WordPress\ArchiveSource($this->db,880191);$user=$source->batch('user')[0]['view'];self::assertArrayNotHasKey('user_pass',$user);self::assertArrayNotHasKey('user_email',$user);
    }
    public function testBoundaryRejectsGuestsGetForgedNonceAndMissingConfirmation(): void
    {
        $_SERVER['REQUEST_METHOD']='POST';$_POST=['operation'=>'archive-start-completion','race'=>'880191'];$this->rejects('archive_command_forbidden',fn()=>ArchiveBoundary::submit());
        wp_set_current_user(get_user_by('login','synthetic_admin')->ID);$_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-archives');$this->rejects('archive_maintenance_confirmation_required',fn()=>ArchiveBoundary::submit());
        $_POST['backup']=$_POST['quiesced']='1';$_SERVER['REQUEST_METHOD']='GET';$this->rejects('archive_command_forbidden',fn()=>ArchiveBoundary::submit());
        $_SERVER['REQUEST_METHOD']='POST';$_POST['cor_mgmt_nonce']='forged';$this->rejects('archive_command_forbidden',fn()=>ArchiveBoundary::submit());
    }
    public function testUnverifiedAndWrongModeSnapshotsCannotBePublished(): void
    {
        $id=$this->archive->start(880191,1,'completion');$this->rejects('archive_not_verified',fn()=>$this->archive->complete($id));
        $this->rejects('archive_not_published',fn()=>$this->archive->rows($id,'team'));$this->rejects('archive_mode_conflict',fn()=>$this->archive->start(880191,1,'migration'));
        self::assertSame('',$this->archive->published(880191));
    }

    public function testFrozenStatisticsKeepScalarSelectorsAndRankingStageOptions(): void
    {
        $this->db->wpdb->update($this->db->table('h3_mgmt_teams'),['rank_stage_1'=>2,'extra_stage_1'=>1],['id'=>880193]);
        $id=$this->ready();$this->archive->complete($id);
        self::assertSame('1',do_shortcode('[h3-event-statistics race="880191" stat="team-count"]'));
        self::assertSame('1',do_shortcode('[h3-event-statistics race="880191" stat="participants-complete-count"]'));
        $html=do_shortcode('[h3-ranking-table race="880191" show_stages="1" show_title="1"]');self::assertStringContainsString('17 (+1)',$html);self::assertStringContainsString('Frozen event',$html);
        self::assertStringNotContainsString('17 (+1)',do_shortcode('[h3-ranking-table race="880191" show_stages="0" show_nav="0"]'));
    }

    public function testProfileChangeAfterVerificationPreventsPublication(): void
    {
        $id=$this->ready();update_user_meta($this->user,'city','Changed after verification');
        $this->rejects('archive_content_changed',fn()=>$this->archive->complete($id));self::assertSame('',$this->archive->published(880191));self::assertSame('ready',$this->archive->run($id)['state']);
    }
}
