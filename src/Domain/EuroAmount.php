<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Exact EUR amounts. Historical raw values require separate provenance before conversion. */
final readonly class EuroAmount
{
    public function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('invalid_amount');
        }
    }
    public static function decimal(string $value): self
    {
        if (!preg_match('/^(0|[1-9][0-9]*)(?:[.,]([0-9]{1,2}))?$/D', trim($value), $parts)) {
            throw new \InvalidArgumentException('invalid_amount');
        }
        $digits = ltrim($parts[1] . str_pad($parts[2] ?? '', 2, '0'), '0');
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new \InvalidArgumentException('amount_overflow');
        }
        return new self($digits === '' ? 0 : (int) $digits);
    }
    public function format(): string
    {
        return intdiv($this->cents, 100) . '.' . str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public function add(self $other): self
    {
        if ($this->cents > PHP_INT_MAX - $other->cents) {
            throw new \InvalidArgumentException('amount_overflow');
        }
        return new self($this->cents + $other->cents);
    }
}
