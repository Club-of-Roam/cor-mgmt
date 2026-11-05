<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Exact display arithmetic; does not infer or persist historical units. */
final class DonationAmounts
{
    public static function compatible(EuroAmount $amount): int|string
    {
        return $amount->cents % 100 === 0 ? intdiv($amount->cents, 100) : $amount->format();
    }
    public static function thumbs(EuroAmount $amount): int|string
    {
        return $amount->cents % 1000 === 0 ? intdiv($amount->cents, 1000) : intdiv($amount->cents, 1000) . '.' . rtrim(str_pad((string) ($amount->cents % 1000), 3, '0', STR_PAD_LEFT), '0');
    }
    public static function whole(int|string|float $value): int
    {
        $raw = trim((string) $value);
        $negative = str_starts_with($raw, '-');
        $whole = intdiv(EuroAmount::decimal($negative ? substr($raw, 1) : $raw)->cents, 100);
        return $negative ? -$whole : $whole;
    }
    public static function offset(int|string|float $value, int|string|float $offset): int|string
    {
        $amount = EuroAmount::decimal((string) $value);
        $raw = trim((string) $offset);
        $negative = str_starts_with($raw, '-');
        $deduction = EuroAmount::decimal($negative ? substr($raw, 1) : $raw);
        if ($negative) {
            return self::compatible($amount->add($deduction));
        }
        $difference = $amount->cents - $deduction->cents;
        $result = self::compatible(new EuroAmount(abs($difference)));
        return $difference < 0 ? (is_int($result) ? -$result : '-' . $result) : $result;
    }
}
