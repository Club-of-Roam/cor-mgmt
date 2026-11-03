<?php

/** PHP cryptographic randomness adapter. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure;

use Cor\Mgmt\Application\Contracts\RandomSource;

final class SecureRandom implements RandomSource
{
    public function bytes(int $length): string
    {
        if ($length < 1) {
            throw new \ValueError('Random byte length must be positive.');
        } return random_bytes($length);
    }
    public function integer(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }
}
