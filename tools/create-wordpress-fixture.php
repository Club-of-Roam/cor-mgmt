<?php

declare(strict_types=1);

// Copy only distributable core files into a NEW fixture; never copy site configuration/content.
[$script, $source, $target] = array_pad($argv, 3, '');
$source = realpath($source);
if (!$source || !is_file($source . '/wp-includes/version.php') || $target === '' || file_exists($target)) {
    throw new RuntimeException('Usage: php tools/create-wordpress-fixture.php CORE_SOURCE NEW_EMPTY_TARGET');
}
if (!mkdir($target, 0700, true)) { throw new RuntimeException('Cannot create fixture'); }
$target = realpath($target);
$copy = static function (string $from, string $to) use (&$copy): void {
    if (is_link($from)) { throw new RuntimeException('Core fixture source must not contain symlinks'); }
    if (is_dir($from)) {
        if (!mkdir($to, 0700)) { throw new RuntimeException('Cannot create fixture directory'); }
        foreach (new DirectoryIterator($from) as $entry) {
            if (!$entry->isDot()) { $copy($entry->getPathname(), $to . '/' . $entry->getFilename()); }
        }
    } elseif (!copy($from, $to)) { throw new RuntimeException('Cannot copy core file'); }
};
foreach (['wp-includes', 'wp-admin'] as $directory) { $copy($source . '/' . $directory, $target . '/' . $directory); }
foreach (glob($source . '/*.php') as $file) {
    if (basename($file) !== 'wp-config.php') { $copy($file, $target . '/' . basename($file)); }
}
foreach (['plugins', 'themes'] as $directory) { mkdir($target . '/wp-content/' . $directory, 0700, true); }
file_put_contents($target . '/.cor-mgmt-test-fixture', "synthetic-wordpress-only\n");
echo "Core-only WordPress fixture created; no database installed by this command.\n";
