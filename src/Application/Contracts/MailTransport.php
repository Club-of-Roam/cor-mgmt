<?php

/** Mail transport boundary; recipients remain an application concern. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Application\Contracts;

interface MailTransport
{
    public function send(string $recipient, string $subject, string $body, string $headers): bool;
}
