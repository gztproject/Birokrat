<?php

namespace App\Tests\Unit;

use App\Formatting\SlovenianFormat;
use PHPUnit\Framework\TestCase;

class SlovenianFormatTest extends TestCase
{
    public function testFormatsSlovenianDateWithoutLeadingZeros(): void
    {
        $date = new \DateTimeImmutable('2026-09-05');

        $this->assertSame('5. 9. 2026', SlovenianFormat::date($date));
        $this->assertSame('', SlovenianFormat::date(null));
    }

    public function testFormatsSlovenianNumberIndependentOfPhpLocale(): void
    {
        if (\extension_loaded('intl')) {
            \Locale::setDefault('en_US');
        }

        $this->assertSame('1 234,50', SlovenianFormat::number(1234.5));
        $this->assertSame('1 234', SlovenianFormat::number(1234, 0));
    }

    public function testFormatsSlovenianAddressLines(): void
    {
        $this->assertSame(
            ['Cankarjeva 1', '1000 Ljubljana', 'Slovenija'],
            SlovenianFormat::addressLines('Cankarjeva 1', null, '1000', 'Ljubljana', 'Slovenija'),
        );
        $this->assertSame(
            'Cankarjeva 1, 2. nadstropje, 1000 Ljubljana, Slovenija',
            SlovenianFormat::address('Cankarjeva 1', '2. nadstropje', '1000', 'Ljubljana', 'Slovenija'),
        );
    }

    public function testApplyForcesIntlDefaultLocale(): void
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required');
        }

        \Locale::setDefault('en_US');
        SlovenianFormat::apply();

        $this->assertSame(SlovenianFormat::LOCALE, \Locale::getDefault());

        $formatter = new \NumberFormatter(SlovenianFormat::LOCALE, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);
        $formatted = $formatter->format(1234.5);

        $this->assertMatchesRegularExpression('/,50$/', $formatted);
    }
}
