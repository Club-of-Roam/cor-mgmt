<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Results;

final class ResultException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
