<?php

/** WordPress mail adapter; core filters and interception remain effective. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\MailTransport;

final class WordPressMailTransport implements MailTransport
{
    public function send(string $recipient, string $subject, string $body, string $headers): bool
    {
        return wp_mail($recipient, $subject, $body, $headers);
    }
}
