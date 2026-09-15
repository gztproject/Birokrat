<?php

namespace App\Formatting;

/**
 * Display formatting is always Slovenian, independent of the UI translation locale.
 */
final class SlovenianFormat
{
    public const LOCALE = 'sl_SI';
    public const LANGUAGE = 'sl';
    public const DATE = 'j. n. Y';
    public const DECIMAL_SEPARATOR = ',';
    public const THOUSANDS_SEPARATOR = ' ';

    public static function apply(): void
    {
        if (\extension_loaded('intl')) {
            \Locale::setDefault(self::LOCALE);
        }
    }

    public static function date(?\DateTimeInterface $date): string
    {
        return $date?->format(self::DATE) ?? '';
    }

    public static function number(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, self::DECIMAL_SEPARATOR, self::THOUSANDS_SEPARATOR);
    }

    /**
     * @return list<string>
     */
    public static function addressLines(string $line1, ?string $line2, string $postCode, string $postName, string $countryName): array
    {
        $lines = [$line1];
        if ($line2 !== null && $line2 !== '') {
            $lines[] = $line2;
        }
        $lines[] = trim($postCode.' '.$postName);
        if ($countryName !== '') {
            $lines[] = $countryName;
        }

        return $lines;
    }

    public static function address(string $line1, ?string $line2, string $postCode, string $postName, string $countryName): string
    {
        return implode(', ', self::addressLines($line1, $line2, $postCode, $postName, $countryName));
    }
}
