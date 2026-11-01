<?php

namespace App\Tests\Web;

use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UpdatePageTest extends WebTestCase
{
    private KernelBrowser $client;

    private array $fixtures = [];

    private bool $channelReady = false;

    private bool $channelExisted = false;

    private ?string $channelBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $em = $container->get(EntityManagerInterface::class);
            $em->getConnection()->executeQuery('SELECT 1');
            $this->fixtures = (new SmokeFixtures(
                $em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }

        $path = $this->channelPath();
        $this->channelExisted = is_file($path);
        $this->channelBackup = $this->channelExisted ? (string) file_get_contents($path) : null;
        $this->channelReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->channelReady) {
            $path = $this->channelPath();
            if ($this->channelExisted) {
                file_put_contents($path, (string) $this->channelBackup);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testAdminCanOpenTheUpdatePage(): void
    {
        $this->client->loginUser($this->fixtures['admin']);

        $this->client->request('GET', '/admin/update');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="update"]');
        $this->assertSelectorExists('[data-channel="stable"]');
        $this->assertSelectorExists('[data-channel="prerelease"]');
        $this->assertSelectorExists('[data-step="requirements"]');
        $this->assertSelectorExists('[data-step="cache"]');
        $this->assertSelectorExists('[data-controller="update-badge"]');
    }

    public function testUserCannotOpenThePageOrChangeTheChannel(): void
    {
        $this->client->loginUser($this->fixtures['user']);

        $this->client->request('GET', '/admin/update');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/update/channel', [
            'channel' => 'prerelease',
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertChannelUnchanged();
    }

    public function testUnknownChannelIsRejected(): void
    {
        $this->client->loginUser($this->fixtures['admin']);
        $token = $this->token();

        $this->client->request('POST', '/admin/update/channel', [
            'token' => $token,
            'channel' => 'beta',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertChannelUnchanged();
    }

    public function testChannelChangeRequiresAValidTokenAndSavesTheChoice(): void
    {
        $this->client->loginUser($this->fixtures['admin']);

        $this->client->request('POST', '/admin/update/channel', [
            'channel' => 'prerelease',
        ]);
        $this->assertResponseStatusCodeSame(403);

        $token = $this->token();
        $this->client->request('POST', '/admin/update/channel', [
            'token' => $token,
            'channel' => 'prerelease',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('prerelease', trim((string) file_get_contents($this->channelPath())));
    }

    public function testUpdateCannotStartOutsideProductionOrWithoutAToken(): void
    {
        $this->client->loginUser($this->fixtures['admin']);

        $this->client->request('POST', '/admin/update');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/update', [
            'token' => $this->token(),
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('prod', (string) $this->client->getResponse()->getContent());
    }

    private function token(): string
    {
        $crawler = $this->client->request('GET', '/admin/update');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('[data-controller="update"]')->attr('data-update-token-value');
    }

    private function assertChannelUnchanged(): void
    {
        if ($this->channelExisted) {
            $this->assertSame($this->channelBackup, (string) file_get_contents($this->channelPath()));

            return;
        }

        $this->assertFileDoesNotExist($this->channelPath());
    }

    private function channelPath(): string
    {
        return dirname(__DIR__, 2).'/var/update-channel';
    }
}
