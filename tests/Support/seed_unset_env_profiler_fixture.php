<?php

use App\Entity\User\User;
use App\Kernel;
use App\Tests\Support\ProfilerFixtureSeeder;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

require_once dirname(__DIR__, 2).'/vendor/autoload_runtime.php';

return function (array $context) {
    $outputPath = $_SERVER['argv'][1] ?? null;
    if (!is_string($outputPath) || $outputPath === '') {
        throw new RuntimeException('Fixture output path is required.');
    }

    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();

    try {
        $container = $kernel->getContainer();
        $doctrine = $container->get('doctrine');
        if (!$doctrine instanceof Registry) {
            throw new RuntimeException('Doctrine registry is not available.');
        }
        // security.yaml configures this user hasher as algorithm "auto".
        $hasher = new UserPasswordHasher(new PasswordHasherFactory([
            User::class => ['algorithm' => 'auto'],
        ]));
        $fixture = (new ProfilerFixtureSeeder())->seed($doctrine->getManager(), $hasher);
        $fixture['app_env'] = $context['APP_ENV'];
        $fixture['app_debug'] = (bool) $context['APP_DEBUG'];
        file_put_contents($outputPath, json_encode($fixture, JSON_THROW_ON_ERROR));
    } finally {
        $kernel->shutdown();
    }

    return static function (): int {
        return 0;
    };
};
