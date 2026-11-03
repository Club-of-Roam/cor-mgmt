<?php

/** Privacy-conscious operational logger. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\Logger;
use Throwable;

final class WordPressLogger implements Logger
{
    public function error(string $code, Throwable $error): void
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        $code = preg_replace('/[^a-z0-9_-]/i', '', $code);
        // Exception messages, traces, paths and request values can contain secrets.
        // Anonymous exception class names contain their source file path after @.
        $type = explode('@', get_class($error), 2)[0];
        error_log('cor-mgmt: ' . $code . ' [' . $type . ']');
    }
}
