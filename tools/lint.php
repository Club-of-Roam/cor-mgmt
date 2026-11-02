<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [$root . '/h3-management.php'];
foreach (['includes', 'admin', 'templates', 'models', 'legacy-endpoints', 'tests', 'tools'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }
}
$failed = 0;
foreach ($paths as $path) {
    // Syntax gate only. Legacy deprecations remain documented, not silently fixed.
    $command = escapeshellarg(PHP_BINARY) . ' -n -d error_reporting=0 -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    exec($command, $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        ++$failed;
    }
}
printf("Syntax: %d files, %d failures (PHP %s)\n", count($paths), $failed, PHP_VERSION);
exit($failed > 0 ? 1 : 0);
