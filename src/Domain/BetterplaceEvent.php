<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Fundraising URLs only; no browser-supplied host or callback controls provider requests. */
final readonly class BetterplaceEvent
{
    public string $id;
    public function __construct(public string $url)
    {
        $parts = parse_url($url);
        if (
            !is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || !in_array(strtolower($parts['host'] ?? ''), ['betterplace.org', 'www.betterplace.org'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !preg_match('~^/(?:de|en)/fundraising-events/([1-9][0-9]*)(?:-[A-Za-z0-9_-]+)?/?$~D', $parts['path'] ?? '', $match)
        ) {
            throw new \InvalidArgumentException('invalid_betterplace_url');
        }
        if (strlen($match[1]) > 32) {
            throw new \InvalidArgumentException('invalid_betterplace_url');
        }
        $this->id = $match[1];
    }
    public function form(string $language): string
    {
        return 'https://secure.betterplace.org/' . ($language === 'de' ? 'de' : 'en') . '/donate/platform/fundraising-events/' . $this->id;
    }
}
