<?php
/** Verify package boundaries and test the bundled loader in an isolated PHP process. */
declare(strict_types=1);
$archive = $argv[1] ?? sys_get_temp_dir() . '/cor-mgmt-artifacts/cor-mgmt.zip';
$zip = new ZipArchive();
if ($zip->open($archive) !== true) { throw new RuntimeException('Cannot open package.'); }
$files = [];
for ($index = 0; $index < $zip->numFiles; ++$index) {
    $name = $zip->getNameIndex($index);
    if (!is_string($name) || !str_starts_with($name, 'cor-mgmt/') || str_contains($name, '..') || str_contains($name, '\\')) {
        throw new RuntimeException('Unsafe archive entry.');
    }
    if (preg_match('~(?:^|/)(?:tests|tools|node_modules|phpunit|phpstan|\.git|\.env)(?:/|$)~', $name)) {
        throw new RuntimeException('Development or private files included: '.$name);
    }
    if (preg_match('~(?:^|/)(?:error_log|[^/]+\.(?:log|sql|bak))$~i', $name)) { throw new RuntimeException('Private artifact included.'); }
    $files[] = $name;
}
foreach (['h3-management.php','uninstall.php','vendor/autoload.php','runtime/legacy.php','src/WordPress/Lifecycle.php','js/app.js','docs/installation.md'] as $required) {
    if (!in_array('cor-mgmt/'.$required, $files, true)) { throw new RuntimeException('Missing runtime file: '.$required); }
}
$installed = json_decode($zip->getFromName('cor-mgmt/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
if ($installed['packages'] !== [] || $installed['dev'] !== false) { throw new RuntimeException('Unexpected runtime dependencies.'); }
$directory = sys_get_temp_dir() . '/cor-mgmt-package-test-' . bin2hex(random_bytes(12));
mkdir($directory,0700);
$directory = realpath($directory);
try {
    if (!$zip->extractTo($directory)) { throw new RuntimeException('Cannot extract package.'); }
    $script = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
if (class_exists('PHPUnit\Framework\TestCase')) { throw new RuntimeException('Development loader leaked.'); }
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1].'/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $entry) {
    if ($entry->getExtension() !== 'php') { continue; }
    $relative = substr($entry->getPathname(), strlen($argv[1].'/src/'), -4);
    $class = 'Cor\\Mgmt\\'.str_replace(['/',DIRECTORY_SEPARATOR], '\\', $relative);
    if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) { throw new RuntimeException('Missing class: '.$class); }
}
$runtime = Cor\Mgmt\WordPress\Services::get();
if (strlen($runtime->random->bytes(16)) !== 16) { throw new RuntimeException('Runtime adapter failed.'); }
echo "Production loader and all foundation types verified.\n";
PHP;
    $process = proc_open([PHP_BINARY,'-r',$script,$directory.'/cor-mgmt'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start package smoke test.'); }
    fclose($pipes[0]);
    if (proc_close($process)!==0) { throw new RuntimeException('Package smoke test failed.'); }
    printf("Package boundaries: %d files, no development dependencies.\n", count($files));
} finally {
    $zip->close();
    if (!str_starts_with(basename($directory),'cor-mgmt-package-test-') || realpath($directory)!==$directory) { throw new RuntimeException('Unsafe cleanup target.'); }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($directory);
}
