<?php

declare(strict_types=1);

use App\Entity\User\User;
use App\Kernel;
use App\Tests\Expense\BookingRaceBarrier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = dirname(__DIR__, 2);
require $projectDir.'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
putenv('APP_ENV=test');
putenv('APP_DEBUG=1');
(new Dotenv())->bootEnv($projectDir.'/.env', 'test');

$configPath = $argv[1] ?? '';
$config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
$resultFile = $config['resultFile'];
$trace = [
    'loaded' => 0,
    'peerSeen' => false,
    'state' => null,
    'claimId' => null,
    'status' => null,
    'exception' => null,
    'body' => null,
];

register_shutdown_function(static function () use (&$trace, $resultFile): void {
    $error = error_get_last();
    if (is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $trace['fatal'] = $error['message'].' in '.$error['file'].':'.$error['line'];
    }
    if (!is_file($resultFile)) {
        file_put_contents($resultFile, json_encode($trace, JSON_THROW_ON_ERROR));
    }
});

$kernel = new Kernel('test', true);
$kernel->boot();
$client = new KernelBrowser($kernel);
$client->disableReboot();
$client->catchExceptions(true);

$container = $client->getContainer();
$em = $container->get(EntityManagerInterface::class);
$barrier = new BookingRaceBarrier(
    $config['claimClass'],
    $config['readyFile'],
    $config['peerReadyFile'],
    (float) $config['timeoutSeconds'],
);
$em->getEventManager()->addEventListener(Events::postLoad, $barrier);

try {
    $user = $em->getRepository(User::class)->findOneBy(['username' => $config['username']]);
    if (!$user instanceof User) {
        throw new RuntimeException('Booking user was not found.');
    }
    $client->loginUser($user);
    $client->request('POST', $config['path'], [
        'dateFrom' => (string) $config['dateFrom'],
        'dateTo' => (string) $config['dateTo'],
        'booked' => '',
        'unbooked' => '',
        'date' => (string) $config['date'],
    ]);
    $response = $client->getResponse();
    $trace['status'] = $response->getStatusCode();
    $trace['body'] = substr((string) $response->getContent(), 0, 2000);
} catch (Throwable $e) {
    $trace['exception'] = $e::class.': '.$e->getMessage();
}

$trace['loaded'] = $barrier->loads;
$trace['peerSeen'] = $barrier->peerSeen;
$trace['state'] = $barrier->state;
$trace['claimId'] = $barrier->claimId;
$trace['loadedAt'] = $barrier->loadedAt;
$trace['releasedAt'] = $barrier->releasedAt;
file_put_contents($resultFile, json_encode($trace, JSON_THROW_ON_ERROR));
