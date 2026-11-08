<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

use Cor\Mgmt\Application\Communication\CommunicationException;

/** Plain-text templates. Replacement values are never interpreted as more placeholders. */
final readonly class AutoResponseTemplate
{
    public const ACTIONS = ['team-creation','invitation','invitation-accepted-inviter','invitation-accepted-invitee','package-paid','waiver-reached','new-sponsor','new-owner','publishable','paypal-please-owner','paypal-please-sponsor','paypal-thanks','debit-thanks-owner','debit-thanks-sponsor'];
    public const FIELDS = ['name','names','team_name','link','code','inviter','invitee','donation','thumbs','event_name'];
    public string $language;
    public function __construct(public string $action, string $language, public bool $enabled, public string $subject, public string $message)
    {
        self::key($action, $language);
        $this->language = self::language($language);
        if (strlen($subject) > 998 || strlen($message) > 200000 || preg_match('/[\r\n\x00]/', $subject) || str_contains($message, "\0") || ($enabled && (trim($subject) === '' || trim($message) === ''))) {
            throw new CommunicationException('invalid_template');
        }
        foreach (self::tokens($subject . "\n" . $message) as $field) {
            if (!in_array($field, self::FIELDS, true)) {
                throw new CommunicationException('unknown_placeholder');
            }
        }
    }
    public static function language(string $language): string
    {
        if (strlen($language) > 16 || !preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/D', $language)) {
            throw new CommunicationException('invalid_template_key');
        }
        return strtolower(str_replace('-', '_', $language));
    }
    public static function key(string $action, string $language): void
    {
        self::language($language);
        if (!in_array($action, self::ACTIONS, true)) {
            throw new CommunicationException('invalid_template_key');
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
