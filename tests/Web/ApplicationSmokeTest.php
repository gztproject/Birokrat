<?php

namespace App\Tests\Web;

use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApplicationSmokeTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $fixtures = [];

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
    }

    public function testLoginPageIsReachable(): void
    {
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.app-topbar-actions [data-controller="theme"]');
    }

    public function testDashboardRedirectsAnonymousUsers(): void
    {
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects();

        $this->client->request('GET', '/');
        $this->assertResponseRedirects();
    }

    public function testLoginSucceedsWithCsrf(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => SmokeFixtures::USER_USERNAME,
            '_password' => SmokeFixtures::USER_PASSWORD,
            '_csrf_token' => $token,
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserPages(): void
    {
        $this->client->loginUser($this->fixtures['user']);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.app-sidenav-account');
        $this->assertSelectorExists('.app-sidenav-account [data-controller="theme"]');
        $this->assertSelectorExists('.app-topbar.d-lg-none');
        $this->assertSelectorNotExists('.app-content .app-topbar-actions');

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.app-sidenav-account');

        $this->client->request('GET', '/dashboard/invoice');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="infinite-scroll"]');

        $this->client->request('GET', '/dashboard/invoice?partial=1');
        $this->assertResponseIsSuccessful();
        $this->assertJson((string) $this->client->getResponse()->getContent());
        $partial = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($partial);
        $this->assertArrayHasKey('rows', $partial);
        $this->assertArrayHasKey('cards', $partial);
        $this->assertArrayHasKey('lastPage', $partial);

        $this->client->request('GET', '/dashboard/invoice/new');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/travelExpense');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/transaction');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/report');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="org-filter"]');
        $this->assertSelectorExists('#dateFieldYear');
        $this->assertSelectorExists('#dateFieldFrom');
        $this->assertSelectorExists('#dateFieldTo');

        $this->client->request('GET', '/dashboard/lunchExpense');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/user/2fa');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('img[alt="Authenticator QR code"]');

        $crawler = $this->client->request('GET', '/dashboard/lunchExpense/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#lunch_expense_sum');

        $form = $crawler->filter('form')->form();
        $settings = $this->fixtures['organization']->getOrganizationSettings();
        if ($settings->getIncurredTravelExpenseDebit() && $settings->getIncurredTravelExpenseCredit()) {
            $this->client->submit($form, [
                'lunch_expense[organization]' => $this->fixtures['organization']->getId()->toString(),
                'lunch_expense[sum]' => '6.12',
            ]);
            $this->assertResponseRedirects();
        }
    }

    public function testAdminPages(): void
    {
        $this->client->loginUser($this->fixtures['admin']);

        $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/user');
        $this->assertResponseIsSuccessful();
    }

    public function testInvoicePdfAndTransactionExport(): void
    {
        $this->client->loginUser($this->fixtures['admin']);
        $invoiceId = $this->fixtures['invoice']->getId()->toString();

        $this->client->request('GET', '/dashboard/invoice/'.$invoiceId.'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('pdf', (string) $this->client->getResponse()->headers->get('content-type'));

        $this->client->request('GET', '/dashboard/transaction/export');
        $this->assertResponseIsSuccessful();
        $contentType = (string) $this->client->getResponse()->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'spreadsheet')
            || str_contains($contentType, 'octet-stream')
            || str_contains($contentType, 'excel'),
            'Export should look like a spreadsheet download, got: '.$contentType
        );
    }
}
