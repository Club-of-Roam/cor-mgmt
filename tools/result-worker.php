<?php
/** Separate guarded CLI process for scoring concurrency tests. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($argv[1],$argv[2],$argv[3],$argv[4])){exit(2);}
require dirname(__DIR__).'/vendor/autoload.php';
define('COR_TEST_PARTICIPATION_WORKER',true);
require dirname(__DIR__).'/tests/Integration/bootstrap.php';
$directory=realpath((string)getenv('COR_TEST_BARRIER_DIR'));
if(!$directory||!str_starts_with(basename($directory),'cor-mgmt-results-')){exit(3);}
wp_set_current_user((int)$argv[2]);
touch($directory.'/ready-'.$argv[1]);$until=microtime(true)+40;
while(!is_file($directory.'/go')){if(microtime(true)>$until){exit(4);}usleep(20000);clearstatcache();}
try{
    $route=(int)$argv[3];$entries=[$route=>['rank'=>(int)$argv[4],'one'=>0,'fixed'=>0,'variable'=>0],$route+1=>['rank'=>2,'one'=>0,'fixed'=>0,'variable'=>0]];
    \Cor\Mgmt\WordPress\ResultBoundary::manager()->submit($route,1,$entries,(string)getenv('COR_TEST_RESULT_REVISION'),(int)$argv[2]);
    echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);
}catch(\Throwable $error){
    $code=$error instanceof \Cor\Mgmt\Application\Results\ResultException?$error->reason:$error->getMessage();
    echo json_encode(['success'=>false,'code'=>preg_match('/^[a-z_]+$/D',$code)?$code:'worker_failed'],JSON_THROW_ON_ERROR);
}
