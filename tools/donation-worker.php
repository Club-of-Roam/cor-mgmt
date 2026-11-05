<?php
/** Guarded CLI helper for concurrent synthetic donation requests. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($argv[1],$argv[2],$argv[3],$argv[4])){exit(2);}
require dirname(__DIR__).'/vendor/autoload.php';
define('COR_TEST_PARTICIPATION_WORKER',true);
require dirname(__DIR__).'/tests/Integration/bootstrap.php';
$directory=realpath((string)getenv('COR_TEST_BARRIER_DIR'));
if(!$directory||!str_starts_with(basename($directory),'cor-mgmt-donations-')){exit(3);}
$runtime=\Cor\Mgmt\WordPress\Services::get();
$http=new class($directory,$argv[1]) implements \Cor\Mgmt\Application\Contracts\HttpClient {
    public function __construct(private string $directory,private string $worker){}
    public function get(string $url,array $options=[]):\Cor\Mgmt\Application\HttpResponse {
        touch($this->directory.'/ready-'.$this->worker);$until=microtime(true)+40;
        while(!is_file($this->directory.'/go')){if(microtime(true)>$until){throw new \RuntimeException('barrier_timeout');}usleep(20000);clearstatcache();}
        return new \Cor\Mgmt\Application\HttpResponse(200,json_encode(['current_page'=>1,'total_pages'=>1,'data'=>[['id'=>987654,'confirmed_at'=>gmdate('Y-m-d\TH:i:s\Z'),'message'=>(string)getenv('COR_TEST_DONATION_MESSAGE'),'donated_amount_in_cents'=>1234]]],JSON_THROW_ON_ERROR));
    }
};
\Cor\Mgmt\WordPress\Services::replace(new \Cor\Mgmt\Application\Runtime($runtime->clock,$runtime->random,$http,$runtime->mail,$runtime->logger));
try{
    \Cor\Mgmt\WordPress\DonationFrontend::ledger()->verify((int)$argv[2],$argv[3],$argv[4]);
    echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);
}catch(\Throwable $error){
    $code=$error instanceof \Cor\Mgmt\Application\Donations\DonationException?$error->reason:$error->getMessage();
    echo json_encode(['success'=>false,'code'=>preg_match('/^[a-z_]+$/D',$code)?$code:'worker_failed'],JSON_THROW_ON_ERROR);
}
