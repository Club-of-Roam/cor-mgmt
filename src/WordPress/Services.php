<?php

/** Composition root for the legacy transition; new use cases receive Runtime directly. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

use Cor\Mgmt\Application\Runtime;
use Cor\Mgmt\Infrastructure\SecureRandom;
use Cor\Mgmt\Infrastructure\SystemClock;
use Cor\Mgmt\Infrastructure\WordPress\WordPressHttpClient;
use Cor\Mgmt\Infrastructure\WordPress\WordPressLogger;
use Cor\Mgmt\Infrastructure\WordPress\WordPressMailTransport;

final class Services
{
    private static ?Runtime $runtime = null;

    public static function get(): Runtime
    {
        return self::$runtime ??= new Runtime(new SystemClock(), new SecureRandom(), new WordPressHttpClient(), new WordPressMailTransport(), new WordPressLogger());
    }

    public static function replace(?Runtime $runtime): void
    {
        self::$runtime = $runtime;
    }
}
