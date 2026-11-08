<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

use Cor\Mgmt\Application\Communication\CommunicationException;

/** Shared plain-text content; substitutions are simultaneous and header-safe. */
final readonly class MessageText
{
    public const FIELDS = ['name','names','team_name','link','code','inviter','invitee','donation','thumbs','event_name'];
    public function __construct(public string $subject, public string $message, bool $enabled = true)
    {
        if (strlen($subject) > 998 || strlen($message) > 200000 || preg_match('/[\r\n\x00]/', $subject) || str_contains($message, "\0") || ($enabled && (trim($subject) === '' || trim($message) === ''))) {
            throw new CommunicationException('invalid_template');
        }
        foreach (self::tokens($subject . "\n" . $message) as $field) {
            if (!in_array($field, self::FIELDS, true)) {
                throw new CommunicationException('unknown_placeholder');
            }
        }
    }
    /** @return list<string> */
    private static function tokens(string $text): array
    {
        preg_match_all('/%([A-Za-z_][A-Za-z0-9_]*)%/', $text, $matches);
        return array_values(array_unique($matches[1]));
    }
    /** @param array<string,string> $values
     * @return array{subject:string,message:string}
     */
    public function render(array $values): array
    {
        $replace = [];
        foreach (self::tokens($this->subject . "\n" . $this->message) as $field) {
            if (!array_key_exists($field, $values)) {
                throw new CommunicationException('missing_placeholder');
            }
            $replace['%' . $field . '%'] = $values[$field];
        }
        $subject = strtr($this->subject, $replace);
        $message = strtr($this->message, $replace);
        if (strlen($subject) > 998 || strlen($message) > 200000 || preg_match('/[\r\n\x00]/', $subject) || str_contains($message, "\0")) {
            throw new CommunicationException('invalid_rendered_template');
        }
        return ['subject' => $subject, 'message' => $message];
    }
}
