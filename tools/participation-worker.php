<?php
/** Separate PHP process for real, synthetic-only concurrency regressions. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !isset($argv[1],$argv[2],$argv[3],$argv[4])) { exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
define('COR_TEST_PARTICIPATION_WORKER', true);
require dirname(__DIR__).'/tests/Integration/bootstrap.php';
$directory=realpath((string)getenv('COR_TEST_BARRIER_DIR'));
if (!$directory || !str_starts_with(basename($directory),'cor-mgmt-participation-')) { exit(3); }
$user=(int)$argv[2]; $race=(int)$argv[3];
wp_set_current_user($user);
touch($directory.'/ready-'.$user);
$until=microtime(true)+40;
while(!is_file($directory.'/go')) { if(microtime(true)>$until){exit(4);} usleep(20000);clearstatcache(); }
try {
    $manager=\Cor\Mgmt\WordPress\TeamBoundary::manager();
    if($argv[1]==='route') {$manager->save($race,$user,[],[],'',[],(int)$argv[4]);}
    elseif($argv[1]==='join') {$manager->save($race,$user,[],[],$argv[4]);}
    else {exit(5);}
    echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);
} catch(\Throwable $error) {
    $code=$error instanceof \Cor\Mgmt\Application\Teams\ParticipationException ? $error->reason : $error->getMessage();
    echo json_encode(['success'=>false,'code'=>preg_match('/^[a-z_]+$/D',$code)?$code:'worker_failed'],JSON_THROW_ON_ERROR);
}
