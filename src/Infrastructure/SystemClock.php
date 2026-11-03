<?php

/** System time adapter. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure;

use Cor\Mgmt\Application\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
