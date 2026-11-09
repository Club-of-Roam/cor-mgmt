<?php
/** Synthetic-only subprocess for portal rendering and parallel queue dispatch. */
declare(strict_types=1);
if (PHP_SAPI!=='cli'||!isset($argv[1],$argv[2],$argv[3],$argv[4],$argv[5])){exit(2);}
require dirname(__DIR__).'/vendor/autoload.php';
define('COR_TEST_PARTICIPATION_WORKER',true);
// Reproduce a clean Linux CLI environment even on a developer web server.
unset($_SERVER['SERVER_NAME']);
require dirname(__DIR__).'/tests/Integration/bootstrap.php';
$user=(int)$argv[2];$race=(int)$argv[3];$id=(int)$argv[4];
if(!in_array($race,[951,952],true)){exit(3);}
wp_set_current_user($user);
if($argv[1]==='portal'){
    if($user&&!str_starts_with(wp_get_current_user()->user_login,'queue_delivery_')){exit(3);}
    $_GET=['race_id'=>(string)$race];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
    do_action($user ? 'admin_post_cor_mgmt_team' : 'admin_post_nopriv_cor_mgmt_team');
}
$directory=realpath((string)getenv('COR_TEST_MAIL_BARRIER'));
if($argv[1]!=='send'||!$directory||!str_starts_with(basename($directory),'cor-mgmt-communication-')||wp_get_current_user()->user_login!=='queue_delivery_admin'){exit(4);}
$transport=new class($directory) implements \Cor\Mgmt\Application\Contracts\MailTransport {
    public function __construct(private string $directory){}
    public function send(string $recipient,string $subject,string $body,string $headers):bool{
        $db=new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($GLOBALS['wpdb']);
        if($db->connectionHasLock()||$db->scalar('SELECT @@in_transaction')!=='0'){throw new RuntimeException('Unsafe transport context');}
        file_put_contents($this->directory.'/accepted',"accepted\n",FILE_APPEND|LOCK_EX);usleep(100000);return true;
    }
};
$runtime=\Cor\Mgmt\WordPress\Services::get();
$manager=new \Cor\Mgmt\Infrastructure\WordPress\MailQueueManagement(new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($GLOBALS['wpdb']),$runtime->clock,$runtime->random,$transport,static fn(int $event,int $actor,string $operation):bool=>$event===$race&&$actor===$user,static fn():bool=>true);
touch($directory.'/ready-'.getmypid());$until=microtime(true)+40;
while(!is_file($directory.'/go')){if(microtime(true)>$until){exit(5);}usleep(20000);clearstatcache();}
try{$status=$manager->mutate($race,$user,$argv[5],'send',$id);echo json_encode(['success'=>true,'status'=>$status],JSON_THROW_ON_ERROR);}
catch(Throwable $error){$code=$error instanceof \Cor\Mgmt\Application\Communication\CommunicationException?$error->reason:$error->getMessage();echo json_encode(['success'=>false,'code'=>preg_match('/^[a-z_]+$/D',$code)?$code:'worker_failed'],JSON_THROW_ON_ERROR);}
