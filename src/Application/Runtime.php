<?php

/** Explicit dependencies shared by the legacy adapters during transition. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application;

use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Contracts\HttpClient;
use Cor\Mgmt\Application\Contracts\Logger;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;

final class Runtime
{
    public function __construct(
        public readonly Clock $clock,
        public readonly RandomSource $random,
        public readonly HttpClient $http,
        public readonly MailTransport $mail,
        public readonly Logger $logger,
    ) {
    }
}
