<?php

namespace App\Money;

use App\Formatting\SlovenianFormat;

final class MoneyFormatter
{
    public static function amount(float|int|string|null $value, int $decimals = 2): string
    {
        return SlovenianFormat::number($value, $decimals);
    }

    public static function format(float|int|string|null $value, bool $withSymbol = true, int $decimals = 2): string
    {
        $formatted = self::amount($value, $decimals);

        return $withSymbol ? $formatted.' €' : $formatted;
    }
}
