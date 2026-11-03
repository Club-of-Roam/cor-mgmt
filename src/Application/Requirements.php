<?php

/** Pure prerequisite checks, without hooks or database writes. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application;

final class Requirements
{
    public const MINIMUM_PHP = '8.2';
    public const MINIMUM_WORDPRESS = '6.3';

    /**
     * @param list<string> $extensions Loaded extensions.
     * @return list<string>
     */
    public static function errors(string $php, string $wordpress, string $database, array $extensions, bool $multisite): array
    {
        $errors = [];
        if (version_compare($php, self::MINIMUM_PHP, '<')) {
            $errors[] = 'php_version';
        }
        if (version_compare($wordpress, self::MINIMUM_WORDPRESS, '<')) {
            $errors[] = 'wordpress_version';
        }
        if (!in_array('mbstring', $extensions, true)) {
            $errors[] = 'mbstring';
        }
        $maria = stripos($database, 'mariadb') !== false;
        $database = preg_replace('/^5\.5\.5-/', '', $database) ?? '';
        if (
            !preg_match('/^(\d+\.\d+\.\d+)/', $database, $version)
            || version_compare($version[1], $maria ? '10.11.0' : '8.0.0', '<')
        ) {
            $errors[] = 'database_version';
        }
        if ($multisite) {
            $errors[] = 'multisite';
        }
        return $errors;
    }
}
