<?php

/** Restricted operational reporting without request data. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application\Contracts;

use Throwable;

interface Logger
{
    public function error(string $code, Throwable $error): void;
}
