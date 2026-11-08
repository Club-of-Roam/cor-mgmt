<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

use Cor\Mgmt\Application\Communication\CommunicationException;

final readonly class MailSender
{
    public function __construct(public string $name, public string $email)
    {
        if (trim($name) === '' || strlen($name) > 200 || preg_match('/[\x00-\x1f\x7f]/', $name) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[<>\r\n\x00]/', $email)) {
            throw new CommunicationException('invalid_sender');
        }
    }
    public function headers(): string
    {
        return 'From: "' . addcslashes($this->name, '\\"') . '" <' . $this->email . ">\r\nContent-Type: text/plain; charset=UTF-8";
    }
}
