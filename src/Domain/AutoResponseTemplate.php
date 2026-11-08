<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

use Cor\Mgmt\Application\Communication\CommunicationException;

/** Plain-text templates. Replacement values are never interpreted as more placeholders. */
final readonly class AutoResponseTemplate
{
    public const ACTIONS = ['team-creation','invitation','invitation-accepted-inviter','invitation-accepted-invitee','package-paid','waiver-reached','new-sponsor','new-owner','publishable','paypal-please-owner','paypal-please-sponsor','paypal-thanks','debit-thanks-owner','debit-thanks-sponsor'];
    public const FIELDS = MessageText::FIELDS;
    public string $language;
    public function __construct(public string $action, string $language, public bool $enabled, public string $subject, public string $message)
    {
        self::key($action, $language);
        $this->language = self::language($language);
        new MessageText($subject, $message, $enabled);
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
    /** @param array<string,string> $values
     * @return array{subject:string,message:string}
     */
    public function render(array $values): array
    {
        return (new MessageText($this->subject, $this->message, $this->enabled))->render($values);
    }
}
