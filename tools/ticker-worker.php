<?php
/** Synthetic-only concurrent writer probe; never part of the release package. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !preg_match('/^cor_ticker_parallel_[a-f0-9]{12}_$/D', $argv[1] ?? '') || !in_array($argv[2] ?? '', ['0','1'], true)) { exit(2); }
require dirname(__DIR__) . '/vendor/autoload.php';
define('COR_TEST_PARTICIPATION_WORKER', true);
require dirname(__DIR__) . '/tests/Integration/bootstrap.php';
$directory=realpath((string)getenv('COR_TEST_BARRIER_DIR'));
if (!$directory || !str_starts_with(basename($directory),'cor-mgmt-ticker-')) { exit(3); }
$connection=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
$connection->set_prefix($argv[1]);
$db=new \Cor\Mgmt\Infrastructure\WordPress\Schema\Database($connection);
$manager=new \Cor\Mgmt\Infrastructure\WordPress\Ticker($db,new \Cor\Mgmt\Infrastructure\SystemClock(),static fn(int $actor,int $race,string $action):bool=>$actor===1234 && $race===1 && $action==='write');
touch($directory . '/ready-' . $argv[2]);
$until=microtime(true)+40;
while (!is_file($directory . '/go')) { if(microtime(true)>$until){exit(4);} usleep(20000); clearstatcache(); }
try {
    $id=$manager->create(1234,1,'text','Concurrent synthetic post','',0,0,str_repeat('9',32));
    echo json_encode(['success'=>true,'id'=>$id],JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['success'=>false,'code'=>$error->getMessage()],JSON_THROW_ON_ERROR);
}
$connection->close();
