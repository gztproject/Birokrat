<?php

namespace App\Twig;

use App\Money\MoneyFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [self::class, 'money']),
            new TwigFilter('money_amount', [self::class, 'amount']),
        ];
    }

    public static function money(float|int|string|null $value, int $decimals = 2): string
    {
        return MoneyFormatter::format($value, true, $decimals);
    }

    public static function amount(float|int|string|null $value, int $decimals = 2): string
    {
        return MoneyFormatter::amount($value, $decimals);
    }
}
