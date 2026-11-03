<?php

/** Cryptographic randomness boundary. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application\Contracts;

interface RandomSource
{
    public function bytes(int $length): string;
    public function integer(int $minimum, int $maximum): int;
}
