<?php

use App\Entity\User\User;
use App\Tests\Invoice\RaceDelayKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$role = $argv[1] ?? '';
$dir = $argv[2] ?? '';
$invoiceId = $argv[3] ?? '';
$username = $argv[4] ?? '';
$issueDate = $argv[5] ?? '';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');
putenv('INVOICE_RACE_ROLE='.$role);
putenv('INVOICE_RACE_DIR='.$dir);
$_SERVER['INVOICE_RACE_ROLE'] = $role;
$_SERVER['INVOICE_RACE_DIR'] = $dir;

(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

$resultPath = $dir.'/result-'.$role.'.json';

try {
    $kernel = new RaceDelayKernel('test', true);
    $kernel->boot();
    $client = new KernelBrowser($kernel);
    $client->disableReboot();

    $user = $kernel->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy([
        'username' => $username,
    ]);
    if (!$user instanceof User) {
        throw new RuntimeException('Issuing user was not found.');
    }
    $client->loginUser($user, 'main');

    file_put_contents($dir.'/ready-'.$role, (string) microtime(true));
    $deadline = microtime(true) + 30;
    while (microtime(true) < $deadline && (!is_file($dir.'/ready-A') || !is_file($dir.'/ready-B'))) {
        usleep(20000);
    }
    if (!is_file($dir.'/ready-A') || !is_file($dir.'/ready-B')) {
        throw new RuntimeException('The other issue worker did not become ready.');
    }

    if ($role === 'B') {
        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline && !is_file($dir.'/a-holding-write')) {
            usleep(20000);
        }
        if (!is_file($dir.'/a-holding-write')) {
            throw new RuntimeException('Worker A never reached the invoice write.');
        }
    }

    file_put_contents($dir.'/'.$role.'-started', (string) microtime(true));
    $client->request('POST', '/dashboard/invoice/issue', [
        'id' => $invoiceId,
        'date' => $issueDate,
    ]);
    $response = $client->getResponse();
    file_put_contents($resultPath, json_encode([
        'status' => $response->getStatusCode(),
        'body' => substr((string) $response->getContent(), 0, 800),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    file_put_contents($resultPath, json_encode([
        'error' => $e::class,
        'message' => $e->getMessage(),
        'at' => $e->getFile().':'.$e->getLine(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
