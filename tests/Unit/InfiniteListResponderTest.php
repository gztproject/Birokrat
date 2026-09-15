<?php

namespace App\Tests\Unit;

use App\Http\InfiniteListResponder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class InfiniteListResponderTest extends TestCase
{
    public function testLastPageRoundsUp(): void
    {
        $list = new InfiniteListResponder($this->createMock(Environment::class));
        $pagination = $this->createMock(PaginationInterface::class);
        $pagination->method('getItemNumberPerPage')->willReturn(25);
        $pagination->method('getTotalItemCount')->willReturn(26);

        $this->assertSame(2, $list->lastPage($pagination));
    }

    public function testLastPageIsAtLeastOneWhenEmpty(): void
    {
        $list = new InfiniteListResponder($this->createMock(Environment::class));
        $pagination = $this->createMock(PaginationInterface::class);
        $pagination->method('getItemNumberPerPage')->willReturn(25);
        $pagination->method('getTotalItemCount')->willReturn(0);

        $this->assertSame(1, $list->lastPage($pagination));
    }
}
