<?php

namespace App\Dashboard;

final class DashboardPeriod
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public static function current(?\DateTimeImmutable $now = null): self
    {
        return new self($now ?? new \DateTimeImmutable('today'));
    }

    /**
     * First day of this year, plus the last two months of last year (1 November).
     */
    public function from(): \DateTimeImmutable
    {
        return $this->now
            ->setTime(0, 0)
            ->setDate((int) $this->now->format('Y'), 1, 1)
            ->modify('-2 months');
    }
}
