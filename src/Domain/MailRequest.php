<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

use Cor\Mgmt\Application\Communication\CommunicationException;

/** Stable identity supplied by the domain operation, never by a later page view. */
final readonly class MailRequest
{
    public string $recipient;
    public string $language;
    /** @var array<string,string> */
    public array $values;

    /** @param array<string,mixed> $values */
    public function __construct(public int $race, public string $occurrence, public string $action, string $recipient, string $language, array $values)
    {
        AutoResponseTemplate::key($action, $language);
        if ($race < 1 || !preg_match('/^[A-Za-z0-9:_-]{1,128}$/D', $occurrence)) {
            throw new CommunicationException('invalid_occurrence');
        }
        $recipient = strtolower(trim($recipient));
        if (strlen($recipient) > 254 || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\x00]/', $recipient)) {
            throw new CommunicationException('invalid_recipient');
        }
        foreach ($values as $key => $value) {
            if (!in_array($key, AutoResponseTemplate::FIELDS, true) || !is_string($value) || str_contains($value, "\0")) {
                throw new CommunicationException('invalid_message_values');
            }
        }
        ksort($values);
        if (strlen(json_encode($values, JSON_THROW_ON_ERROR)) > 200000) {
            throw new CommunicationException('message_values_too_large');
        }
        $this->values = $values;
        $this->recipient = $recipient;
        $this->language = AutoResponseTemplate::language($language);
    }

    public function key(): string
    {
        return hash('sha256', json_encode([$this->race, $this->occurrence, $this->action, $this->recipient, $this->language], JSON_THROW_ON_ERROR));
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->key(), $this->values], JSON_THROW_ON_ERROR));
    }
}
