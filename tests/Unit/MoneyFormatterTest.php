<?php

namespace App\Tests\Unit;

use App\Money\MoneyFormatter;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function testFormatsSlovenianCurrency(): void
    {
        $this->assertSame('1 234,50 €', MoneyFormatter::format(1234.5));
        $this->assertSame('1 234,50', MoneyFormatter::amount(1234.5));
    }
}
