<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Integration;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\HttpClient;
use Cor\Mgmt\Application\HttpResponse;
use Cor\Mgmt\Application\Runtime;
use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\WordPress\DonationFrontend;
use Cor\Mgmt\WordPress\Services;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DonationWorkflowTest extends TestCase
{
    private Runtime $runtime;
    private array $saved;
    private Clock $clock;
    private HttpClient $http;
    private Database $db;
    protected function setUp():void
    {
        global $wpdb;$wpdb->query('START TRANSACTION');wp_cache_flush();
        $this->saved=[$_GET,$_POST,$_FILES,$_COOKIE,$_SERVER];$_GET=$_POST=$_FILES=[];$_SERVER['REQUEST_METHOD']='GET';$_COOKIE['client_token']='synthetic-donor-session';
        $this->runtime=Services::get();
        $this->clock=new class implements Clock {public int $time=1793871000;public function now():int{return $this->time;}};
        $this->http=new class implements HttpClient {public array $rows=[];public int $calls=0;public int $status=200;public function get(string $url,array $options=[]):HttpResponse{$this->calls++;return new HttpResponse($this->status,json_encode(['data'=>$this->rows,'current_page'=>1,'total_pages'=>1],JSON_THROW_ON_ERROR));}};
        Services::replace(new Runtime($this->clock,$this->runtime->random,$this->http,$this->runtime->mail,$this->runtime->logger));
        $this->db=new Database($wpdb);
        $wpdb->insert($this->db->table('h3_mgmt_races'),['id'=>700,'name'=>'Synthetic donation','setting'=>json_encode(['status'=>1,'donation'=>1,'kind_of_donation_tool'=>0,'team_overview_link'=>'/teams','betterplace_redirect_link'=>'https://www.betterplace.org/de/fundraising-events/123-synthetic']),'information_text'=>'{}']);
        foreach([710,711] as $id){$wpdb->insert($this->db->table('h3_mgmt_teams'),['id'=>$id,'race_id'=>700,'team_name'=>'<Synthetic '.$id.'>']);}
        $GLOBALS['cor_test_mail']=[];
    }
    protected function tearDown():void
    {
        global $wpdb;$error=$wpdb->last_error;$wpdb->query('ROLLBACK');wp_cache_flush();Services::replace($this->runtime);wp_set_current_user(0);
        [$_GET,$_POST,$_FILES,$_COOKIE,$_SERVER]=$this->saved;self::assertSame('',$error);self::assertSame([],$GLOBALS['cor_test_mail']);
    }
    private function begin(string $type='sponsor',string $session='synthetic-donor-session',bool $new=false):array{return DonationFrontend::ledger()->begin(700,710,$type,$session,'en',$new);}
    private function evidence(array $row,int $cents=1234,int $opinion=900):void
    {
        $this->http->rows=[['id'=>$opinion,'message'=>'Code '.$row['token'],'confirmed_at'=>gmdate('Y-m-d\TH:i:s\Z',$this->clock->now()),'donated_amount_in_cents'=>$cents]];
    }
    private function verify(array $row):array{return DonationFrontend::ledger()->verify(700,$row['token'],'synthetic-donor-session');}
    private function rejected(string $reason,callable $operation):void{try{$operation();self::fail('Expected '.$reason);}catch(DonationException $error){self::assertSame($reason,$error->reason);}}
    private function post(array $input):void{$_SERVER['REQUEST_METHOD']='POST';$_POST=$input+['cor_mgmt_nonce'=>wp_create_nonce(\H3_MGMT_Security::donation_nonce(700)),'address'=>''];}
    public function testBeginIsIdempotentAndStoresNoPlainSessionCookie():void
    {
        $one=$this->begin();$two=$this->begin();self::assertSame($one['token'],$two['token']);self::assertSame('pending',$one['state']);self::assertNull($one['amount_minor']);self::assertSame(hash('sha256','synthetic-donor-session'),$one['session_hash']);
        $legacy=$this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d',$this->db->table('h3_mgmt_sponsors'),(int)$one['sponsor_id']))[0];self::assertNotSame('synthetic-donor-session',$legacy['donation_client_reference']);
        self::assertNotSame($one['token'],$this->begin(new:true)['token']);
    }
    public function testCentsAreExactAndOnlyAssignedAfterServerObservation():void
    {
        $row=$this->begin();$this->evidence($row);$result=$this->verify($row);self::assertSame('1234',$result['amount_minor']);self::assertSame('EUR',$result['currency']);self::assertSame('confirmed',$result['state']);
        $legacy=$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id']);self::assertSame('12.34',$legacy['donation']);self::assertSame('1',$legacy['paid']);
        self::assertEquals(12.34,$GLOBALS['h3_mgmt_sponsors']->get_donation_amount(['id'=>710]));
    }
    public function testConfirmedRetryDoesNotCallProviderOrDuplicateJournal():void
    {
        $row=$this->begin();$this->evidence($row);$this->verify($row);$before=$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history')));
        $this->http->status=503;$this->verify($row);self::assertSame(1,$this->http->calls);self::assertSame($before,$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history'))));
    }
    public function testBrowserAmountsAndDoneWithoutOpinionDoNotConfirm():void
    {
        $row=$this->begin();$_GET=['amount'=>'100000','status'=>'DONATION_COMPLETE'];$this->post(['submitted'=>'2','donation_token'=>$row['token'],'amount'=>'100000']);
        self::assertStringContainsString('pending',DonationFrontend::render(['race'=>700]));self::assertSame('pending',DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session')['state']);
        self::assertStringContainsString('does not confirm',$GLOBALS['h3_mgmt_sponsors']->handle_betterplace_redirect());
    }
    public function testHiddenAmountRemainsPendingAndGivesActionableFeedback():void
    {
        $row=$this->begin('owner');$this->evidence($row);$this->http->rows[0]['donated_amount_in_cents']=null;$this->post(['submitted'=>'2','donation_token'=>$row['token']]);
        self::assertStringContainsString('amount is not public',DonationFrontend::render(['race'=>700]));self::assertNull(DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session')['amount_minor']);
    }
    public function testTokensAreBoundToRaceAndBrowserSession():void
    {
        $GLOBALS['wpdb']->insert($this->db->table('h3_mgmt_races'),['id'=>701,'name'=>'Other','setting'=>json_encode(['status'=>1,'donation'=>1,'betterplace_redirect_link'=>'https://betterplace.org/de/fundraising-events/123'])]);
        $row=$this->begin();$this->rejected('invalid_reference',fn()=>DonationFrontend::ledger()->find(701,$row['token'],'synthetic-donor-session'));
        $this->rejected('invalid_reference',fn()=>DonationFrontend::ledger()->find(700,$row['token'],'another-browser'));
        $this->rejected('invalid_team',fn()=>DonationFrontend::ledger()->begin(701,710,'sponsor','synthetic-donor-session','en'));
    }
    public function testProfileCannotBeSavedBeforeConfirmation():void
    {
        $row=$this->begin();$this->rejected('payment_not_confirmed',fn()=>DonationFrontend::ledger()->profile(700,$row['token'],'synthetic-donor-session',['display_name'=>'Unverified']));
    }
    public function testProfileUpdatePreservesPaymentAndIsIdempotent():void
    {
        $row=$this->begin();$this->evidence($row);$this->verify($row);
        $this->post(['submitted'=>'3','donation_token'=>$row['token'],'display_name'=>'<script>bad</script> Name','message'=>'Hello','owner_link'=>'https://evil.invalid']);
        self::assertStringContainsString('profile has been saved',DonationFrontend::render(['race'=>700]));$result=DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session');self::assertStringNotContainsString('<script>',$result['display_name']);self::assertSame('',$result['owner_link']);self::assertSame('1234',$result['amount_minor']);self::assertSame('1',$result['var_show']);
        $before=$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history')));DonationFrontend::render(['race'=>700]);self::assertSame($before,$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history'))));
    }
    public function testOneOpinionCannotBeAssignedToTwoEntries():void
    {
        $one=$this->begin();$this->evidence($one);$this->verify($one);$two=$this->begin(new:true);$this->evidence($two);
        $this->rejected('opinion_already_used',fn()=>$this->verify($two));self::assertSame('pending',DonationFrontend::ledger()->find(700,$two['token'],'synthetic-donor-session')['state']);
    }
    public function testOwnerThresholdAndConcurrentAvailabilityAreCheckedAtConfirmation():void
    {
        $one=$this->begin('owner');$two=$this->begin('owner',new:true);$this->evidence($one,10000);self::assertSame('owner',$this->verify($one)['type']);
        $this->evidence($two,10099,901);self::assertSame('sponsor',$this->verify($two)['type']);
        $this->rejected('owner_unavailable',fn()=>$this->begin('owner',new:true));
        $third=$this->begin('sponsor',new:true);$this->evidence($third,9999,902);self::assertSame('sponsor',$this->verify($third)['type']);
    }
    public function testArchiveAndDisabledDonationSwitchPreventFurtherWrites():void
    {
        $row=$this->begin();$this->evidence($row);global $wpdb;$rules=json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=700',$this->db->table('h3_mgmt_races'))),true);$rules['status']=4;
        $wpdb->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode($rules)],['id'=>700]);$this->rejected('donations_closed',fn()=>$this->verify($row));
        $rules['status']=1;$rules['donation']=0;$wpdb->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode($rules)],['id'=>700]);$this->rejected('donations_closed',fn()=>$this->begin());
    }
    public function testJournalFailureRollsBackEvidenceAndLegacyProjection():void
    {
        $row=$this->begin();$this->evidence($row);$filter=function(string $sql):string{if(str_starts_with($sql,'INSERT INTO `'.$this->db->table('cor_mgmt_donation_history').'`')){throw new \RuntimeException('injected_failure');}return $sql;};add_filter('query',$filter);
        try{try{$this->verify($row);self::fail('Expected failure');}catch(\RuntimeException $error){self::assertSame('injected_failure',$error->getMessage());}}finally{remove_filter('query',$filter);}
        $current=DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session');self::assertSame('pending',$current['state']);self::assertNull($current['amount_minor']);self::assertSame('0',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id'])['paid']);
    }
    public function testNativeFormsNeedNonceAndDoNotLoadExternalCheckoutWhenBlocked():void
    {
        $html=DonationFrontend::render(['race'=>700]);self::assertStringContainsString('name="team_id"',$html);self::assertStringNotContainsString('<Synthetic',$html);
        $_POST=['submitted'=>'1','type'=>'sponsor','team_id'=>'710'];$_SERVER['REQUEST_METHOD']='POST';self::assertStringContainsString('not permitted',DonationFrontend::render(['race'=>700]));
        $this->post(['submitted'=>'1','type'=>'sponsor','team_id'=>'710']);$html=DonationFrontend::render(['race'=>700]);self::assertStringContainsString('External donations are disabled',$html);self::assertStringNotContainsString('<iframe',$html);
    }
    public function testLegacyRowsAreNotConvertedAndManagedPaymentEditsAreBlocked():void
    {
        global $wpdb;$wpdb->insert($this->db->table('h3_mgmt_sponsors'),['id'=>799,'race_id'=>700,'team_id'=>710,'method'=>'paypal','donation'=>23,'paid'=>1]);
        $before=$GLOBALS['h3_mgmt_sponsors']->get_sponsor(799);$row=$this->begin();self::assertSame($before,$GLOBALS['h3_mgmt_sponsors']->get_sponsor(799));
        require_once dirname(__DIR__,2).'/admin/class-h3-mgmt-admin-sponsors.php';
        $admin=wp_insert_user(['user_login'=>'donation_admin','user_email'=>'donation-admin@example.invalid','user_pass'=>'synthetic','role'=>'administrator']);wp_set_current_user($admin);
        $_GET=['todo'=>'paid','id'=>$row['sponsor_id'],'bulk'=>[799]];$this->post([]);$_POST['cor_mgmt_nonce']=wp_create_nonce(\H3_MGMT_Security::admin_nonce('sponsors'));
        ob_start();try{(new \H3_MGMT_Admin_Sponsors())->sponsors_control();$html=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('legacy editing is disabled',$html);self::assertSame('pending',DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session')['state']);
    }
    public function testChangedEventDuringProviderObservationRejectsCommit():void
    {
        $row=$this->begin();$this->evidence($row);$http=$this->http;$db=$this->db;
        $changed=new class($http,$db) implements HttpClient {
            public function __construct(private HttpClient $inner,private Database $db){}
            public function get(string $url,array $options=[]):HttpResponse{
                $rules=json_decode($this->db->scalar($this->db->prepare('SELECT setting FROM %i WHERE id=700',$this->db->table('h3_mgmt_races'))),true);$rules['betterplace_redirect_link']='https://betterplace.org/de/fundraising-events/456';
                $this->db->execute($this->db->prepare('UPDATE %i SET setting=%s WHERE id=700',$this->db->table('h3_mgmt_races'),json_encode($rules)));return $this->inner->get($url,$options);
            }
        };
        $runtime=Services::get();Services::replace(new Runtime($runtime->clock,$runtime->random,$changed,$runtime->mail,$runtime->logger));
        $this->rejected('invalid_evidence',fn()=>$this->verify($row));self::assertSame('pending',DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session')['state']);
    }
    public function testRouteReaderAndRecentActivityKeepNewCents():void
    {
        global $wpdb;$wpdb->update($this->db->table('h3_mgmt_teams'),['route_id'=>720],['id'=>710]);$row=$this->begin();$this->evidence($row);$this->verify($row);DonationFrontend::ledger()->profile(700,$row['token'],'synthetic-donor-session',['display_name'=>'Synthetic donor']);
        $rows=$GLOBALS['h3_mgmt_sponsors']->get_sponsors_meta(['parent_type'=>'route','parent'=>720]);self::assertCount(1,$rows[0]);self::assertSame('12.34',array_values($rows[0])[0]['donation']);
        self::assertStringContainsString('12 Euros',$GLOBALS['h3_mgmt_sponsors']->recent_sponsors(['race'=>700]));
    }

    private function admin():int
    {
        $user=wp_insert_user(['user_login'=>'synthetic_donation_editor','user_email'=>'editor@example.invalid','user_pass'=>'synthetic','role'=>'administrator']);wp_set_current_user($user);return $user;
    }
    private function managedConfirmed():array
    {
        $row=$this->begin();$this->evidence($row);return $this->verify($row);
    }
    private function change(array $row,array $values,?string $revision=null):array
    {
        $id=(int)$row['sponsor_id'];$manager=\Cor\Mgmt\WordPress\DonationAdmin::manager();return $manager->update([$id=>['revision'=>$revision??$manager->inspect($id,get_current_user_id())['revision'],'values'=>$values]],get_current_user_id());
    }
    public function testAdminMetadataAndVisibilityAreJournaledWithoutFinancialChanges():void
    {
        $row=$this->managedConfirmed();$actor=$this->admin();$before=$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id']);
        $this->change($row,['display_name'=>'<b>Editor name</b>','message'=>"Hi\nTeam",'var_show'=>'1']);
        $after=$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id']);self::assertSame('Editor name',$after['display_name']);self::assertSame('1',$after['var_show']);
        foreach(['donation','paid','team_id','race_id','method','donation_token','donation_client_reference'] as $key){self::assertSame($before[$key],$after[$key]);}
        $journal=json_decode($this->db->scalar($this->db->prepare("SELECT payload FROM %i WHERE sponsor_id=%d AND kind='admin_profile_saved' ORDER BY id DESC LIMIT 1",$this->db->table('cor_mgmt_donation_history'),(int)$row['sponsor_id'])),true);
        self::assertSame($actor,$journal['actor']);self::assertSame('Editor name',$journal['changes']['display_name']['new']);$this->change($row,['var_show'=>'0']);self::assertSame('0',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id'])['var_show']);
    }
    public function testAdminMetadataRejectsStaleInputAndFinancialFields():void
    {
        $row=$this->managedConfirmed();$this->admin();$context=\Cor\Mgmt\WordPress\DonationAdmin::manager()->inspect((int)$row['sponsor_id'],get_current_user_id());$this->change($row,['message'=>'First']);
        $this->rejected('stale_donation',fn()=>$this->change($row,['message'=>'Second'],$context['revision']));
        foreach(['paid','donation','amount_minor','team_id','race_id','opinion_id','state'] as $field){$this->rejected('invalid_field',fn()=>$this->change($row,[$field=>'1']));}
    }
    public function testAdminIdenticalUpdateDoesNotDuplicateJournalAndArchiveIsImmutable():void
    {
        $row=$this->managedConfirmed();$this->admin();$this->change($row,['display_name'=>'Name']);$before=$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history')));$this->change($row,['display_name'=>'Name']);self::assertSame($before,$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i',$this->db->table('cor_mgmt_donation_history'))));
        $GLOBALS['wpdb']->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode(['status'=>4])],['id'=>700]);$this->rejected('donations_closed',fn()=>$this->change($row,['var_show'=>'0']));
    }
    public function testPendingCannotBePublishedAndUnauthorizedEditorCannotReadOrWrite():void
    {
        $row=$this->begin();$this->admin();$this->rejected('payment_not_confirmed',fn()=>$this->change($row,['var_show'=>'1']));wp_set_current_user(0);
        $this->rejected('forbidden',fn()=>\Cor\Mgmt\WordPress\DonationAdmin::manager()->inspect((int)$row['sponsor_id'],0));
        $this->rejected('forbidden',fn()=>$this->change($row,['message'=>'Bad'],'irrelevant'));
    }
    public function testBulkValidationIsAllOrNothing():void
    {
        $one=$this->managedConfirmed();$two=$this->begin(new:true);$this->admin();$manager=\Cor\Mgmt\WordPress\DonationAdmin::manager();$requests=[];
        foreach([$one,$two] as $row){$id=(int)$row['sponsor_id'];$requests[$id]=['revision'=>$manager->inspect($id,get_current_user_id())['revision'],'values'=>['var_show'=>'1']];}
        $this->rejected('payment_not_confirmed',fn()=>$manager->update($requests,get_current_user_id()));self::assertSame('0',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$one['sponsor_id'])['var_show']);
    }
    public function testAdminJournalFailureRollsBackMetadata():void
    {
        $row=$this->managedConfirmed();$this->admin();$filter=function(string $sql):string{if(str_starts_with($sql,'INSERT INTO `'.$this->db->table('cor_mgmt_donation_history').'`')){throw new \RuntimeException('injected_failure');}return $sql;};add_filter('query',$filter);
        try{try{$this->change($row,['display_name'=>'Rolled back']);self::fail('Expected failure');}catch(\RuntimeException $error){self::assertSame('injected_failure',$error->getMessage());}}finally{remove_filter('query',$filter);}
        self::assertSame('',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id'])['display_name']);
    }
    public function testLegacyReviewIsExplicitIdempotentAndNeverAdoptsOrConverts():void
    {
        global $wpdb;$wpdb->insert($this->db->table('h3_mgmt_sponsors'),['id'=>799,'race_id'=>700,'team_id'=>710,'method'=>'Betterplace','donation'=>23,'paid'=>0,'donation_token'=>'old-private-code','donation_client_reference'=>'old-cookie']);$before=$GLOBALS['h3_mgmt_sponsors']->get_sponsor(799);$actor=$this->admin();$manager=\Cor\Mgmt\WordPress\DonationAdmin::manager();$context=$manager->inspect(799,$actor);self::assertTrue($context['plan']['eligible']);self::assertFalse($context['plan']['session_transfer']);self::assertSame('unresolved',$context['plan']['historical_units']);
        foreach([0,1] as $attempt){$context=$manager->inspect(799,$actor);self::assertSame('provider_reconciliation_required',$manager->prepareLegacy(799,$actor,$context['revision'])['status']);}
        self::assertTrue($manager->inspect(799,$actor)['prepared']);self::assertSame($before,$GLOBALS['h3_mgmt_sponsors']->get_sponsor(799));self::assertSame('0',$this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE sponsor_id=799',$this->db->table('cor_mgmt_donations'))));
        $entries=$this->db->rows($this->db->prepare('SELECT payload FROM %i WHERE sponsor_id=799',$this->db->table('cor_mgmt_donation_history')));self::assertCount(1,$entries);self::assertStringNotContainsString('old-cookie',$entries[0]['payload']);self::assertStringNotContainsString('old-private-code',$entries[0]['payload']);self::assertSame(0,$this->http->calls);
    }
    public function testLegacyReviewRejectsOrphansPaidRowsAndConfigurationDrift():void
    {
        global $wpdb;$wpdb->insert($this->db->table('h3_mgmt_sponsors'),['id'=>799,'race_id'=>700,'team_id'=>710,'method'=>'Betterplace','paid'=>0]);$actor=$this->admin();$manager=\Cor\Mgmt\WordPress\DonationAdmin::manager();$old=$manager->inspect(799,$actor)['revision'];
        $wpdb->update($this->db->table('h3_mgmt_races'),['setting'=>json_encode(['status'=>1,'betterplace_redirect_link'=>'https://betterplace.org/de/fundraising-events/456'])],['id'=>700]);$this->rejected('stale_donation',fn()=>$manager->prepareLegacy(799,$actor,$old));
        foreach([['team_id'=>999999],['team_id'=>710,'paid'=>1]] as $values){$wpdb->update($this->db->table('h3_mgmt_sponsors'),$values,['id'=>799]);$context=$manager->inspect(799,$actor);self::assertFalse($context['plan']['eligible']);$this->rejected('legacy_review_blocked',fn()=>$manager->prepareLegacy(799,$actor,$context['revision']));}
    }
    public function testAdminBoundaryRequiresPostNonceAndRendersEscapedProfile():void
    {
        $row=$this->managedConfirmed();$this->admin();$context=\Cor\Mgmt\WordPress\DonationAdmin::manager()->inspect((int)$row['sponsor_id'],get_current_user_id());
        $_POST=['donation_action'=>'save','sponsor_id'=>$row['sponsor_id'],'revision'=>$context['revision'],'display_name'=>'<script>evil</script> Good'];$_SERVER['REQUEST_METHOD']='POST';$this->rejected('invalid_nonce',fn()=>\Cor\Mgmt\WordPress\DonationAdmin::submit());
        $_POST['cor_mgmt_nonce']=wp_create_nonce('cor-mgmt-donation-admin');\Cor\Mgmt\WordPress\DonationAdmin::submit();$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$_GET=['donation_id'=>$row['sponsor_id']];ob_start();try{\Cor\Mgmt\WordPress\DonationAdmin::page();$html=ob_get_contents();}finally{ob_end_clean();}
        self::assertStringContainsString('Observed amount',$html);self::assertStringContainsString('12.34 EUR',$html);self::assertStringNotContainsString('<script>',$html);self::assertStringNotContainsString('synthetic-donor-session',$html);
    }
    public function testExactAggregationKeepsCentsAndHistoricalCountingConvention():void
    {
        $one=$this->managedConfirmed();$two=$this->begin(new:true);$this->evidence($two,10,901);$this->verify($two);global $wpdb;$wpdb->insert($this->db->table('h3_mgmt_sponsors'),['race_id'=>700,'team_id'=>710,'method'=>'debit','donation'=>10,'paid'=>0,'type'=>'sponsor']);
        [$rows,$counts,$amounts]=$GLOBALS['h3_mgmt_sponsors']->get_sponsors_meta(['parent'=>700,'exclude_unpaid'=>true,'extra_fields'=>['code']]);self::assertCount(2,$rows);self::assertSame('22.44',$amounts['race']);self::assertSame('2.244',$amounts['thumbs']);self::assertSame(3,$counts['race']);self::assertSame('12.44',$GLOBALS['h3_mgmt_sponsors']->get_donation_amount(['id'=>710]));
    }

    public function testOwnRouteEditorCannotModifyForeignDonationAndBulkRollsBack():void
    {
        $one=$this->managedConfirmed();$two=DonationFrontend::ledger()->begin(700,711,'sponsor','synthetic-donor-session','en');$this->evidence($two,2000,901);$this->verify($two);
        $user=wp_insert_user(['user_login'=>'synthetic_route_donor_editor','user_email'=>'route@example.invalid','user_pass'=>'synthetic','role'=>'subscriber']);$account=new \WP_User($user);$account->add_cap('h3_mgmt_edit_own_sponsors');$account->add_cap('h3_mgmt_view_own_sponsors');
        $GLOBALS['wpdb']->insert($this->db->table('h3_mgmt_routes'),['id'=>720,'race_id'=>700,'user_id'=>$user]);$GLOBALS['wpdb']->update($this->db->table('h3_mgmt_teams'),['route_id'=>720],['id'=>710]);wp_set_current_user($user);
        $this->change($one,['display_name'=>'Own edit']);$_GET=[];ob_start();try{\Cor\Mgmt\WordPress\DonationAdmin::page();$listing=ob_get_contents();}finally{ob_end_clean();}self::assertStringContainsString('Own edit',$listing);self::assertStringNotContainsString('donation_id='.$two['sponsor_id'],$listing);$this->rejected('forbidden',fn()=>\Cor\Mgmt\WordPress\DonationAdmin::manager()->inspect((int)$two['sponsor_id'],$user));
        $manager=\Cor\Mgmt\WordPress\DonationAdmin::manager();$requests=[(int)$one['sponsor_id']=>['revision'=>$manager->inspect((int)$one['sponsor_id'],$user)['revision'],'values'=>['var_show'=>'1']],(int)$two['sponsor_id']=>['revision'=>'foreign','values'=>['var_show'=>'1']]];
        $this->rejected('forbidden',fn()=>$manager->update($requests,$user));self::assertSame('0',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$one['sponsor_id'])['var_show']);
    }
    public function testFrontendShowsWholeEurosWhileStorageAndAdministrationKeepCents():void
    {
        $row=$this->managedConfirmed();$this->post(['submitted'=>'2','donation_token'=>$row['token']]);$html=DonationFrontend::render(['race'=>700]);self::assertStringContainsString('12 EUR',$html);self::assertStringNotContainsString('12.34 EUR',$html);self::assertSame('1234',DonationFrontend::ledger()->find(700,$row['token'],'synthetic-donor-session')['amount_minor']);
        wp_register_script('h3-mgmt-donation-counter',false);$before=[];foreach(['min','current','max'] as $field){$before[$field]=get_option('h3_'.$field.'_counter_700','absent');}
        $counter=$GLOBALS['h3_mgmt_races']->donation_counter(['race'=>700,'offset'=>'0.10']);self::assertStringContainsString('id="counter-value">12</span>',$counter);
        foreach($before as $field=>$value){self::assertSame($value,get_option('h3_'.$field.'_counter_700','absent'));}
    }

    public function testDonorProfileCannotRepublishAnAdministrativelyHiddenProfile():void
    {
        $row=$this->managedConfirmed();DonationFrontend::ledger()->profile(700,$row['token'],'synthetic-donor-session',['display_name'=>'First']);$this->admin();$this->change($row,['var_show'=>'0']);wp_set_current_user(0);
        DonationFrontend::ledger()->profile(700,$row['token'],'synthetic-donor-session',['display_name'=>'Updated']);self::assertSame('0',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id'])['var_show']);
    }
    public function testOwnerMetadataCannotInjectImageUrlsOrExecutableWebsiteSchemes():void
    {
        $row=$this->begin('owner');$this->evidence($row,10000);$this->verify($row);$this->admin();$this->rejected('invalid_input',fn()=>$this->change($row,['owner_pic'=>'https://untrusted.invalid/image.png']));$this->change($row,['owner_link'=>'javascript:alert(1)']);self::assertSame('',$GLOBALS['h3_mgmt_sponsors']->get_sponsor((int)$row['sponsor_id'])['owner_link']);
    }

}
