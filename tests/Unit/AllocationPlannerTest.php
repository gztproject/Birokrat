<?php

namespace App\Tests\Unit;

use App\Entity\Konto\Konto;
use App\Entity\Transaction\AllocationPlanner;
use App\Entity\Transaction\CreateAllocationCommand;
use PHPUnit\Framework\TestCase;

class AllocationPlannerTest extends TestCase
{
    public function testFallsBackToDefaultKonto(): void
    {
        $konto = $this->createMock(Konto::class);
        $lines = AllocationPlanner::lines($konto, 100, []);

        $this->assertCount(1, $lines);
        $this->assertSame(100.0, $lines[0]['amount']);
        $this->assertSame($konto, $lines[0]['konto']);
    }

    public function testRejectsMismatchedSums(): void
    {
        $konto = $this->createMock(Konto::class);
        $line = new CreateAllocationCommand();
        $line->konto = $konto;
        $line->amount = 40;

        $this->expectException(\InvalidArgumentException::class);
        AllocationPlanner::lines($konto, 100, [$line]);
    }
}
