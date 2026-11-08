<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Communication;

final class CommunicationException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
