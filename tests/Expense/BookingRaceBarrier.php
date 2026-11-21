<?php

declare(strict_types=1);

namespace App\Tests\Expense;

use Doctrine\ORM\Event\PostLoadEventArgs;

/**
 * Holds the booking request open after the unbooked-claim query has hydrated
 * and before the controller builds a bundle or inserts a journal entry.
 *
 * The wait ends when the other request has hydrated the same way, or when the
 * timeout elapses. A lock taken around that query keeps the other request from
 * arriving, so the timeout lets the lock holder finish and the waiter see the
 * committed row.
 */
final class BookingRaceBarrier
{
    public int $loads = 0;
    public bool $peerSeen = false;
    public ?int $state = null;
    public ?string $claimId = null;
    public ?float $loadedAt = null;
    public ?float $releasedAt = null;

    public function __construct(
        private readonly string $claimClass,
        private readonly string $readyFile,
        private readonly string $peerReadyFile,
        private readonly float $timeoutSeconds,
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof $this->claimClass) {
            return;
        }
        if ($this->loads > 0) {
            return;
        }

        $this->loads = 1;
        $this->state = $this->readState($entity);
        $this->claimId = (string) $entity->getId();
        $this->loadedAt = microtime(true);
        file_put_contents($this->readyFile, (string) $this->loadedAt, LOCK_EX);

        $deadline = microtime(true) + $this->timeoutSeconds;
        while (microtime(true) < $deadline) {
            clearstatcache(true, $this->peerReadyFile);
            if (is_file($this->peerReadyFile)) {
                $this->peerSeen = true;
                break;
            }
            usleep(20000);
        }
        $this->releasedAt = microtime(true);
    }

    private function readState(object $entity): ?int
    {
        $class = new \ReflectionClass($entity);
        while ($class instanceof \ReflectionClass && !$class->hasProperty('state')) {
            $class = $class->getParentClass();
        }
        if (!$class instanceof \ReflectionClass) {
            return null;
        }
        $property = $class->getProperty('state');
        $property->setAccessible(true);
        $value = $property->getValue($entity);

        return $value === null ? null : (int) $value;
    }
}
