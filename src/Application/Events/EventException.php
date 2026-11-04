<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Events;

final class EventException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
