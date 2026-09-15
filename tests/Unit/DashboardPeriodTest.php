<?php

namespace App\Tests\Unit;

use App\Dashboard\DashboardPeriod;
use PHPUnit\Framework\TestCase;

class DashboardPeriodTest extends TestCase
{
    public function testFromIsFirstOfNovemberLastYear(): void
    {
        $period = DashboardPeriod::current(new \DateTimeImmutable('2026-09-09'));

        $this->assertSame('2025-11-01', $period->from()->format('Y-m-d'));
    }

    public function testFromInJanuaryStillIncludesLastTwoMonthsOfPreviousYear(): void
    {
        $period = DashboardPeriod::current(new \DateTimeImmutable('2026-01-15'));

        $this->assertSame('2025-11-01', $period->from()->format('Y-m-d'));
    }
}
