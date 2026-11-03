<?php

/** HTTP boundary without WordPress response types. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application\Contracts;

use Cor\Mgmt\Application\HttpResponse;

interface HttpClient
{
    /** @param array<string, mixed> $options Request options. */
    public function get(string $url, array $options = []): HttpResponse;
}
