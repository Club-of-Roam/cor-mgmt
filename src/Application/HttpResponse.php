<?php

/** Transport-independent HTTP result. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application;

final class HttpResponse
{
    public function __construct(public readonly int $status, public readonly string $body)
    {
    }
}
