<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\CheckRequirementsSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CheckRequirementsSubscriberTest extends TestCase
{
    public function testMariaDbDriverExceptionDoesNotCallRemovedPlatformGetName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MariaDB110700Platform());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $original = $this->createMock(DriverException::class);
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $original,
        );

        (new CheckRequirementsSubscriber($entityManager))->handleKernelException($event);

        $this->assertSame($original, $event->getThrowable());
    }
}
