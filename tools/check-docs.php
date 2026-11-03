<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$inventory = json_decode(file_get_contents($root . '/docs/inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$normalize = static function (string $contents): string {
    return str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $contents));
};
foreach ($inventory['sources'] as $relative => $expected) {
    $path = $root . '/' . $relative;
    if (!is_file($path) || hash('sha256', $normalize(file_get_contents($path))) !== $expected) {
        $errors[] = 'Stale inventory source: ' . $relative . ' (run python tools/build-inventory.py)';
    }
}
$actualSources = ['h3-management.php'];
foreach (['includes', 'admin', 'templates', 'models', 'src', 'runtime'] as $folder) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS)) as $entry) {
        if (!$entry->isFile() || $entry->getExtension() !== 'php') { continue; }
        $path = $entry->getPathname();
        $actualSources[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
    }
}
$recordedSources = array_keys($inventory['sources']);
sort($actualSources);
sort($recordedSources);
if ($actualSources !== $recordedSources) {
    $errors[] = 'Source file set changed; regenerate inventory.';
}
$tags = array_column($inventory['shortcodes'], 'tag');
if (count($tags) !== count(array_unique($tags))) {
    $errors[] = 'Duplicate shortcode registration in inventory.';
}
foreach ($inventory['shortcodes'] as $item) {
    $source = file_get_contents($root . '/' . $item['file']);
    if (!preg_match('/function\s+' . preg_quote($item['handler'], '/') . '\s*\(/', $source)) {
        $errors[] = 'Missing shortcode handler: ' . $item['handler'];
    }
    if (!str_contains(file_get_contents($root . '/docs/shortcodes.md'), '`' . $item['tag'] . '`')) {
        $errors[] = 'Shortcode missing from documentation: ' . $item['tag'];
    }
}
$markdown = array_merge(glob($root . '/*.md'), glob($root . '/docs/*.md'));
foreach ($markdown as $document) {
    preg_match_all('/\[[^\]\n]+\]\(([^)\s]+)\)/', file_get_contents($document), $matches);
    foreach ($matches[1] as $link) {
        if (preg_match('~^(?:https?://|mailto:|#)~', $link)) {
            continue;
        }
        [$relative, $fragment] = array_pad(explode('#', $link, 2), 2, '');
        $target = dirname($document) . '/' . $relative;
        if (!is_file($target)) {
            $errors[] = basename($document) . ': broken link ' . $link;
        } elseif (preg_match('/^L(\d+)$/', $fragment, $line) && (int) $line[1] > count(file($target))) {
            $errors[] = basename($document) . ': line outside file ' . $link;
        }
    }
}
foreach ($errors as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}
printf("Documentation: %d Markdown files, %d shortcodes, %d failures\n", count($markdown), count($tags), count($errors));
exit($errors ? 1 : 0);
