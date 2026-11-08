<?php
/** Build an installable, deterministic ZIP with a production Composer loader. */
declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? sys_get_temp_dir() . '/cor-mgmt-artifacts/cor-mgmt.zip';
$composer = getenv('COMPOSER_BINARY');
if (!$composer || !is_file($composer)) {
    fwrite(STDERR, "Run through composer build, or set COMPOSER_BINARY to composer.phar.\n");
    exit(1);
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The build requires the PHP zip extension.\n");
    exit(1);
}
$stage = sys_get_temp_dir() . '/cor-mgmt-build-' . bin2hex(random_bytes(12));
if (!mkdir($stage, 0700)) { throw new RuntimeException('Cannot create staging directory.'); }
$stage = realpath($stage);
$copy = static function (string $relative) use ($root, $stage): void {
    $source = $root . '/' . $relative;
    if (is_link($source)) { throw new RuntimeException('Symlinks cannot be packaged: ' . $relative); }
    $target = $stage . '/' . $relative;
    if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); }
    if (!copy($source, $target)) { throw new RuntimeException('Cannot copy package file.'); }
};
$cleanup = static function (string $directory) use ($stage): void {
    $resolved = realpath($directory);
    if ($resolved !== $stage || !str_starts_with(basename($resolved), 'cor-mgmt-build-')) {
        throw new RuntimeException('Refusing cleanup outside the build staging directory.');
    }
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($resolved);
};
try {
    foreach (['h3-management.php', 'uninstall.php', 'composer.json', 'composer.lock', 'README.md', 'docs/schema-3.0.0.sql', 'docs/schema-participation-1.0.0.sql', 'docs/schema-results-1.0.0.sql', 'docs/schema-donations-1.0.0.sql', 'docs/schema-communication-1.0.0.sql'] as $file) { $copy($file); }
    foreach (['src','runtime','includes','admin','templates','models','js','css','img','lib','languages','docs'] as $folder) {
        if (!is_dir($root . '/' . $folder)) { continue; }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($root) + 1));
            if (preg_match('~(?:^|/)\.[^/]+~', $relative)) { continue; }
            $extension = strtolower($entry->getExtension());
            $allowed = ['php','js','css','png','jpg','jpeg','gif','webp','svg','ico','woff','woff2','ttf','otf','eot','map','txt','json','mo','po','pot','md','html','htm'];
            if (!in_array($extension, $allowed, true) && !in_array($entry->getFilename(), ['LICENSE','COPYING','NOTICE'], true)) { continue; }
            if (preg_match('~(?:^|/)(?:error_log|debug\.log|[^/]+\.(?:log|sql|bak))$~i', $relative)) { continue; }
            if ($entry->isFile() || $entry->isLink()) { $copy($relative); }
        }
    }
    // Use the plugin header without asking Composer to infer a Git version.
    preg_match('/^Version:\s*(.+)$/m', file_get_contents($root . '/h3-management.php'), $header);
    $environment = getenv();
    $environment['COMPOSER_ROOT_VERSION'] = trim($header[1] ?? 'dev-main');
    // A separate installation leaves the development vendor directory intact.
    $process = proc_open([PHP_BINARY, $composer, 'install', '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress', '--optimize-autoloader'], [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $pipes, $stage, $environment);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start Composer.'); }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Production autoloader build failed.'); }
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $entry) {
        if ($entry->isFile()) { $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($stage) + 1)); }
    }
    sort($files, SORT_STRING);
    if (!is_dir(dirname($output))) { mkdir(dirname($output), 0700, true); }
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Cannot create ZIP.'); }
    foreach ($files as $file) {
        $name = 'cor-mgmt/' . $file;
        if (!$zip->addFile($stage . '/' . $file, $name)) { throw new RuntimeException('Cannot add ZIP entry.'); }
        $zip->setMtimeName($name, 946684800);
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
    }
    if (!$zip->close()) { throw new RuntimeException('Cannot finish ZIP.'); }
    printf("Package: %s\nSHA256: %s\nFiles: %d\n", $output, hash_file('sha256', $output), count($files));
} finally { $cleanup($stage); }
