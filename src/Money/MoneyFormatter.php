<?php

namespace App\Money;

final class MoneyFormatter
{
    public static function amount(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }

    public static function format(float|int|string|null $value, bool $withSymbol = true, int $decimals = 2): string
    {
        $formatted = self::amount($value, $decimals);

        return $withSymbol ? $formatted.' €' : $formatted;
    }
}
