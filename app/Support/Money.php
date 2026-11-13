<?php

namespace App\Support;

use InvalidArgumentException;

/** Float-free money helpers. Amounts are DECIMAL(10,2) strings in the DB and integer cents in calculations. */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        $value = trim((string) ($amount ?? 0));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $m)) {
            throw new InvalidArgumentException("Not a decimal amount: {$value}");
        }

        $fraction = substr(str_pad($m[3] ?? '', 2, '0'), 0, 2);
        $cents = ((int) $m[2]) * 100 + (int) $fraction;

        return $m[1] === '-' ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $abs = abs($cents);

        return ($cents < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
