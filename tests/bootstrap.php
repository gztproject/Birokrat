<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

// Rate limiter state is kept in a persistent cache pool; start each run without attempts left over from an earlier one.
$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$kernel->getContainer()->get('cache.global_clearer')->clearPool('cache.rate_limiter');
$kernel->shutdown();
