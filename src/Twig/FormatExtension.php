<?php

namespace App\Twig;

use App\Formatting\SlovenianFormat;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('number', [SlovenianFormat::class, 'number']),
            new TwigFilter('sl_date', [SlovenianFormat::class, 'date']),
        ];
    }
}
