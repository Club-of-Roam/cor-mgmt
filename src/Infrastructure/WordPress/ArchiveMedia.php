<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

/** Content-addressed image copies; never fetch remote URLs or overwrite originals. */
final class ArchiveMedia
{
    public function __construct(private readonly ?string $sourceRoot = null)
    {
    }

    private function source(string $reference): string
    {
        if ($this->sourceRoot === null) {
            return \H3_MGMT_Media::safe_image_path($reference);
        }
        $reference = rawurldecode(explode('?', explode('#', $reference, 2)[0], 2)[0]);
        $base = realpath($this->sourceRoot);
        $marker = 'wp-content/uploads/';
        $position = strpos($reference, $marker);
        if (!$base || $position === false || str_contains($reference, '..') || str_contains($reference, "\0")) {
            return '';
        }
        $path = realpath($base . DIRECTORY_SEPARATOR . substr($reference, $position + strlen($marker)));
        if (
            !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)
            || !preg_match('/\.(?:jpg|jpeg|jpe|png|gif|webp|bmp|avif)$/i', $path)
            || preg_match('/\.(?:php[0-9]*|phtml|phar|cgi|asp|aspx|jsp|sh)(?:\.|$)/i', basename($path))
        ) {
            return '';
        }
        $info = @getimagesize($path);
        return $info && in_array($info['mime'], ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/x-ms-bmp','image/avif'], true) ? $path : '';
    }
    /** @return array{file_name:string,file_hash:string,state:string} */
    public function capture(string $reference): array
    {
        $path = $this->source($reference);
        if ($path === '') {
            return ['file_name' => '', 'file_hash' => '', 'state' => 'unavailable'];
        }
        $before = hash_file('sha256', $path);
        if ($before === false) {
            throw new \RuntimeException('archive_image_unreadable');
        }
        $uploads = wp_upload_dir();
        $directory = $uploads['basedir'] . '/cor-mgmt-archive';
        if (!wp_mkdir_p($directory)) {
            throw new \RuntimeException('archive_image_directory');
        }
        $base = realpath($uploads['basedir']);
        $resolved = realpath($directory);
        if (!$base || !$resolved || is_link($directory) || !str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('archive_image_directory');
        }
        $name = $before . '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $target = $resolved . DIRECTORY_SEPARATOR . $name;
        if (is_link($target)) {
            throw new \RuntimeException('archive_image_collision');
        }
        if (!is_file($target)) {
            $temporary = tempnam($resolved, 'copy-');
            if ($temporary === false) {
                throw new \RuntimeException('archive_image_write');
            }
            try {
                if (!copy($path, $temporary) || hash_file('sha256', $temporary) !== $before || hash_file('sha256', $path) !== $before) {
                    throw new \RuntimeException('archive_image_changed');
                }
                if (!rename($temporary, $target)) {
                    throw new \RuntimeException('archive_image_write');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        if (hash_file('sha256', $target) !== $before) {
            throw new \RuntimeException('archive_image_changed');
        }
        return ['file_name' => $name, 'file_hash' => $before, 'state' => 'copied'];
    }

    public function url(string $name, string $hash): string
    {
        if (
            !preg_match('/^[a-f0-9]{64}\.(?:jpg|jpeg|jpe|png|gif|webp|bmp|avif)$/D', $name)
            || !str_starts_with($name, $hash . '.')
        ) {
            return '';
        }
        $uploads = wp_upload_dir();
        $path = $uploads['basedir'] . '/cor-mgmt-archive/' . $name;
        $base = realpath($uploads['basedir']);
        $resolved = realpath($path);
        return $base && $resolved && str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)
            && !is_link(dirname($path)) && !is_link($path) && is_file($path) && hash_file('sha256', $path) === $hash
            ? $uploads['baseurl'] . '/cor-mgmt-archive/' . rawurlencode($name) : '';
    }
}
