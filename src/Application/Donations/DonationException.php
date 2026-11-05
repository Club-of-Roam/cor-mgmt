<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Donations;

final class DonationException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
