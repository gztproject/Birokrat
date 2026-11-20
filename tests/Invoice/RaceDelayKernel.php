<?php

namespace App\Tests\Invoice;

use App\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Boots the application with the race-delay database middleware.
 * Uses its own cache directory so it does not reuse the default test container.
 */
final class RaceDelayKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->register(RaceDelayMiddleware::class)
            ->setAutowired(true)
            ->addTag('doctrine.middleware');
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/test-invoice-race';
    }
}
