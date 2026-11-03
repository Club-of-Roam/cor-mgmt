<?php

/** Time boundary for application services. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application\Contracts;

interface Clock
{
    public function now(): int;
}
