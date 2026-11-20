<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Money\MoneyFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ordinary users may only read and post journal lines for companies they belong to.
 * These tests fail while company id is taken from the query string or the journal form
 * without that check.
 */
#[Group('ledger-authorization')]
class LedgerAuthorizationTest extends WebTestCase
{
    private const DATABASE = 'birokrat_ledger_authz';
    private const USERNAME = 'ledger-reader';
    private const OWN_TX = 'own-ledger-line';
    private const FOREIGN_TX = 'foreign-ledger-line';
    private const POSTED_TX = 'posted-into-foreign-books';
    private const TAMPERED_TX = 'tampered-foreign-ledger-line';
    private const CLONED_TX = 'cloned-foreign-ledger-line';
    private const OWN_ORG = 'OwnCo';
    private const FOREIGN_ORG = 'ForeignCo';
    private const OWN_KONTO = 'own-income-konto';
    private const FOREIGN_INCOME_KONTO = 'foreign-income-konto';
    private const FOREIGN_TAX_KONTO = 'foreign-tax-konto';
    private const FOREIGN_CLIENT = 'foreign-client-marker';
    private const FOREIGN_SUPPLIER = 'foreign-supplier-marker';
    private const FOREIGN_INVOICE = '86421001';
    private const FOREIGN_BILL = 'BILL-13579';
    private const OWN_SUM = 11.0;
    private const FOREIGN_INCOME = 86421.0;
    private const FOREIGN_TAX = 13579.0;
    private const FOREIGN_BALANCE = 45678.0;
    private const FOREIGN_INVOICE_TOTAL = 22222.0;
    private const FOREIGN_BILL_TOTAL = 33333.0;

    private static bool $ready = false;
    private static ?string $originalDatabaseUrl = null;
    private static string $storedOwnOrgId = '';
    private static string $storedForeignOrgId = '';
    private static string $storedOwnTxId = '';
    private static string $storedForeignTxId = '';
    private static string $storedTaxTxId = '';
    private static string $storedBalanceTxId = '';

    private KernelBrowser $client;
    private \PDO $pdo;
    private string $ownOrgId;
    private string $foreignOrgId;
    private string $ownTxId;
    private string $foreignTxId;
    private string $debitKontoId;
    private string $incomeKontoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointAtIsolatedDatabase(!self::$ready);
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->pdo = $this->capturePdo();
        if (!self::$ready) {
            $this->createSchema();
            $this->seed();
            self::$ready = true;
        }
        $this->rememberIds();
        $this->login();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && self::$storedOwnTxId !== '') {
            $this->restoreLedger();
        }
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$originalDatabaseUrl !== null) {
            putenv('DATABASE_URL='.self::$originalDatabaseUrl);
            $_ENV['DATABASE_URL'] = self::$originalDatabaseUrl;
            $_SERVER['DATABASE_URL'] = self::$originalDatabaseUrl;
            self::ensureKernelShutdown();
        }
        parent::tearDownAfterClass();
    }

    public function testJournalListStaysInsideTheUsersCompanies(): void
    {
        foreach ($this->companyQueries() as $label => $query) {
            $this->client->request('GET', '/dashboard/transaction?'.$query);
            $this->assertJournalResponse($label.' html');

            $this->client->request('GET', '/dashboard/transaction?'.$query.'&partial=1');
            $this->assertJournalResponse($label.' partial');
        }
    }

    public function testJournalExportStaysInsideTheUsersCompanies(): void
    {
        foreach ($this->companyQueries() as $label => $query) {
            $this->client->request('GET', '/dashboard/transaction/export?'.$query);
            $status = $this->client->getResponse()->getStatusCode();
            if (in_array($status, [403, 404], true) && str_contains($label, 'foreign')) {
                $this->assertStringNotContainsString(self::FOREIGN_TX, (string) $this->client->getResponse()->getContent());
                continue;
            }
            $bytes = $this->responseBytes();
            if (!str_starts_with($bytes, 'PK')) {
                self::fail($label.' export was not a spreadsheet (HTTP '.$status.'): '.substr(preg_replace('/\s+/', ' ', strip_tags($bytes)) ?? '', 0, 300));
            }
            $sheet = $this->xlsxText($bytes);
            $this->assertStringContainsString(self::OWN_TX, $sheet, $label);
            $this->assertStringContainsString(self::OWN_ORG, $sheet, $label);
            $this->assertStringNotContainsString(self::FOREIGN_TX, $sheet, $label);
            $this->assertStringNotContainsString(self::FOREIGN_ORG, $sheet, $label);
            $this->assertStringNotContainsString($this->money(self::FOREIGN_INCOME), $sheet, $label);
            $this->assertStringNotContainsString($this->money(self::FOREIGN_TAX), $sheet, $label);
        }
    }

    public function testRequestingAnotherCompanyIdDoesNotReturnItsJournal(): void
    {
        foreach (['foreign company', 'empty company'] as $label) {
            $this->client->request('GET', '/dashboard/transaction?'.$this->companyQueries()[$label]);
            $body = (string) $this->client->getResponse()->getContent();
            $this->assertResponseIsSuccessful($label);
            $this->assertStringNotContainsString(self::FOREIGN_TX, $body, $label);
            $this->assertStringNotContainsString(self::FOREIGN_ORG, $body, $label);
        }
    }

    public function testOpeningAnotherCompanysJournalFormIsRefused(): void
    {
        foreach (['edit', 'clone'] as $action) {
            $this->client->request('GET', '/dashboard/transaction/'.$this->foreignTxId.'/'.$action);
            $body = (string) $this->client->getResponse()->getContent();
            $this->assertStringNotContainsString(self::FOREIGN_TX, $body, $action);
            $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404], $action);
        }
    }

    public function testShowingAnotherCompanysJournalLineIsRefused(): void
    {
        $this->client->request('GET', '/dashboard/transaction/'.$this->foreignTxId.'/show');
        $status = $this->client->getResponse()->getStatusCode();
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertContains($status, [403, 404], 'Show must refuse another company line (HTTP '.$status.', leaked='.(str_contains($body, self::FOREIGN_TX) ? 'yes' : 'no').').');
        $this->assertStringNotContainsString(self::FOREIGN_TX, $body);
    }

    public function testJournalFormDoesNotOfferAnotherCompany(): void
    {
        $crawler = $this->client->request('GET', '/dashboard/transaction/new');
        $this->assertResponseIsSuccessful();
        $options = $crawler->filter('select[name="transaction[organization]"] option')->extract(['value']);
        $this->assertContains($this->ownOrgId, $options);
        $this->assertNotContains($this->foreignOrgId, $options);

        $crawler = $this->client->request('GET', '/dashboard/transaction/'.$this->ownTxId.'/edit');
        $this->assertResponseIsSuccessful();
        $options = $crawler->filter('select[name="transaction[organization]"] option')->extract(['value']);
        $this->assertContains($this->ownOrgId, $options);
        $this->assertNotContains($this->foreignOrgId, $options);
    }

    public function testPostingAJournalLineIntoAnotherCompanyDoesNotPersistIt(): void
    {
        $before = $this->transactionCount();
        $this->client->request('POST', '/dashboard/transaction/new', $this->journalPayload(
            $this->foreignOrgId,
            self::POSTED_TX,
            '50.00',
        ));

        $this->assertSame(0, $this->countDescription(self::POSTED_TX));
        $this->assertSame($before, $this->transactionCount());
        $this->assertSame(self::FOREIGN_ORG, $this->organizationOf(self::FOREIGN_TX));
        $this->assertSame(self::OWN_ORG, $this->organizationOf(self::OWN_TX));
    }

    public function testEditingAnotherCompanysJournalLineDoesNotChangeIt(): void
    {
        $this->client->request('POST', '/dashboard/transaction/'.$this->foreignTxId.'/edit', $this->journalPayload(
            $this->foreignOrgId,
            self::TAMPERED_TX,
            '99999.00',
        ));

        $this->assertSame(0, $this->countDescription(self::TAMPERED_TX));
        $this->assertSame(self::FOREIGN_TX, $this->descriptionOf($this->foreignTxId));
        $this->assertSame($this->money(self::FOREIGN_INCOME), $this->money((float) $this->sumOf($this->foreignTxId)));
        $this->assertSame(self::FOREIGN_ORG, $this->organizationOf(self::FOREIGN_TX));
    }

    public function testMovingOwnJournalLineToAnotherCompanyDoesNotStick(): void
    {
        $before = $this->transactionCount();
        $foreignBefore = $this->countForOrganization(self::FOREIGN_ORG);
        $this->client->request('POST', '/dashboard/transaction/'.$this->ownTxId.'/edit', $this->journalPayload(
            $this->foreignOrgId,
            self::OWN_TX,
            $this->moneyNumber(self::OWN_SUM),
        ));

        $this->assertSame(self::OWN_ORG, $this->organizationOf(self::OWN_TX));
        $this->assertSame(self::OWN_TX, $this->descriptionOf($this->ownTxId));
        $this->assertSame($before, $this->transactionCount());
        $this->assertSame($foreignBefore, $this->countForOrganization(self::FOREIGN_ORG));
    }

    public function testCloningAnotherCompanysJournalLineDoesNotCopyIt(): void
    {
        $before = $this->transactionCount();
        $this->client->request('POST', '/dashboard/transaction/'.$this->foreignTxId.'/clone', $this->journalPayload(
            $this->foreignOrgId,
            self::CLONED_TX,
            '50.00',
        ));
        $this->assertSame(0, $this->countDescription(self::CLONED_TX));
        $this->assertSame($before, $this->transactionCount());
        $this->assertSame(self::FOREIGN_TX, $this->descriptionOf($this->foreignTxId));
    }

    public function testOverviewReportStaysInsideTheUsersCompanies(): void
    {
        $this->assertReportPathHidesOtherCompanies('/dashboard/report', true);
    }

    public function testTaxReportStaysInsideTheUsersCompanies(): void
    {
        $this->assertReportPathHidesOtherCompanies('/dashboard/report/dddddd', false);
    }

    public function testBalanceSheetStaysInsideTheUsersCompanies(): void
    {
        $this->assertReportPathHidesOtherCompanies('/dashboard/report/blianca', false);
    }

    public function testProfitReportStaysInsideTheUsersCompanies(): void
    {
        $this->assertReportPathHidesOtherCompanies('/dashboard/report/turnout', false);
    }

    public function testDashboardRecentJournalStaysInsideTheUsersCompanies(): void
    {
        foreach (['/dashboard', '/'] as $path) {
            $this->client->request('GET', $path);
            $this->assertResponseIsSuccessful($path);
            $body = (string) $this->client->getResponse()->getContent();
            $this->assertStringContainsString(self::OWN_TX, $body, $path);
            $this->assertStringNotContainsString(self::FOREIGN_TX, $body, $path);
            $this->assertStringNotContainsString(self::FOREIGN_ORG, $body, $path);
        }
    }

    /**
     * @return array<string, string>
     */
    private function companyQueries(): array
    {
        $period = http_build_query($this->period());

        return [
            'omitted company' => $period,
            'empty company' => $period.'&organization=',
            'foreign company' => $period.'&organization='.$this->foreignOrgId,
            'own company' => $period.'&organization='.$this->ownOrgId,
        ];
    }

    private function assertReportPathHidesOtherCompanies(string $path, bool $expectOwnKonto): void
    {
        foreach ($this->companyQueries() as $label => $query) {
            $this->client->request('GET', $path.'?'.$query);
            $status = $this->client->getResponse()->getStatusCode();
            $body = (string) $this->client->getResponse()->getContent();
            if (in_array($status, [403, 404], true) && str_contains($label, 'foreign')) {
                $this->assertReportDoesNotLeak($body, $path.' '.$label);
                continue;
            }
            $this->assertResponseIsSuccessful($path.' '.$label);
            if ($expectOwnKonto) {
                $this->assertStringContainsString(self::OWN_KONTO, $body, $path.' '.$label);
            }
            $this->assertReportDoesNotLeak($body, $path.' '.$label);
        }
    }

    private function assertJournalResponse(string $label): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $body = (string) $this->client->getResponse()->getContent();
        if (in_array($status, [403, 404], true) && str_contains($label, 'foreign')) {
            $this->assertStringNotContainsString(self::FOREIGN_TX, $body, $label);
            $this->assertStringNotContainsString(self::FOREIGN_ORG, $body, $label);

            return;
        }
        $this->assertResponseIsSuccessful($label);
        $this->assertStringContainsString(self::OWN_TX, $body, $label);
        $this->assertStringContainsString(self::OWN_ORG, $body, $label);
        $this->assertStringNotContainsString(self::FOREIGN_TX, $body, $label);
        $this->assertStringNotContainsString(self::FOREIGN_ORG, $body, $label);
    }

    private function assertReportDoesNotLeak(string $body, string $label): void
    {
        foreach ([
            self::FOREIGN_TX,
            self::FOREIGN_ORG,
            self::FOREIGN_INCOME_KONTO,
            self::FOREIGN_TAX_KONTO,
            self::FOREIGN_CLIENT,
            self::FOREIGN_SUPPLIER,
            self::FOREIGN_INVOICE,
            self::FOREIGN_BILL,
            $this->money(self::FOREIGN_INCOME),
            $this->money(self::FOREIGN_TAX),
            $this->money(self::FOREIGN_BALANCE),
            $this->money(self::FOREIGN_INVOICE_TOTAL),
            $this->money(self::FOREIGN_BILL_TOTAL),
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body, $label.' leaked '.$secret);
        }
    }

    /**
     * @return array<string, string>
     */
    /**
     * The journal form markup closes around the first row, so a browser parser
     * does not treat the later inputs as form fields. The posted request still
     * reaches TransactionType. The CSRF token is read from the page because
     * the same markup leaves it outside that parsed form.
     *
     * @return array<string, array<string, string>>
     */
    private function journalPayload(string $organizationId, string $description, string $sum): array
    {
        $crawler = $this->client->request('GET', '/dashboard/transaction/new');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="transaction[_token]"]')->attr('value');

        return [
            'transaction' => [
                '_token' => $token,
                'organization' => $organizationId,
                'date' => '2026-06-15',
                'debitKonto' => $this->debitKontoId,
                'creditKonto' => $this->incomeKontoId,
                'sum' => $sum,
                'description' => $description,
            ],
        ];
    }

    /**
     * @return array{dateFrom: string, dateTo: string}
     */
    private function period(): array
    {
        return [
            'dateFrom' => (string) (new \DateTimeImmutable('2026-06-01 00:00:00'))->getTimestamp(),
            'dateTo' => (string) (new \DateTimeImmutable('2026-06-30 23:59:59'))->getTimestamp(),
        ];
    }

    private function login(): void
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['username' => self::USERNAME]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
        $this->client->loginUser($user);
    }

    private function pointAtIsolatedDatabase(bool $recreate): void
    {
        if (self::$originalDatabaseUrl === null) {
            self::$originalDatabaseUrl = (string) (getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ''));
        }
        $env = (string) file_get_contents(dirname(__DIR__, 2).'/.env.test');
        if (!preg_match('/DATABASE_URL="([^"]+)"/', $env, $matches)) {
            self::fail('DATABASE_URL is missing from .env.test');
        }
        $url = $matches[1];
        // Prefer the process DATABASE_URL (CI) when present so host/credentials match the job.
        if (self::$originalDatabaseUrl !== '' && str_starts_with(self::$originalDatabaseUrl, 'mysql://')) {
            $url = self::$originalDatabaseUrl;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['user'])) {
            self::fail('DATABASE_URL in .env.test could not be parsed');
        }
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d', $parts['host'], $parts['port'] ?? 3306),
            rawurldecode($parts['user']),
            rawurldecode($parts['pass'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        // doctrine.yaml appends _test to the database name when APP_ENV=test.
        $actual = self::DATABASE.'_test';
        if ($recreate) {
            $pdo->exec('DROP DATABASE IF EXISTS `'.$actual.'`');
            $pdo->exec('CREATE DATABASE `'.$actual.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        $rewritten = preg_replace('#/[^/?]+(\?)#', '/'.self::DATABASE.'$1', $url, 1);
        $rewritten = preg_replace('#serverVersion=[^&]+#', 'serverVersion=8.0.46', (string) $rewritten);
        putenv('DATABASE_URL='.$rewritten);
        $_ENV['DATABASE_URL'] = $rewritten;
        $_SERVER['DATABASE_URL'] = $rewritten;
    }

    private function capturePdo(): \PDO
    {
        $params = static::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams();

        return new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $params['host'], $params['port'] ?? 3306, $params['dbname']),
            (string) $params['user'],
            (string) ($params['password'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }

    private function createSchema(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->bootstrapUser('ledger-admin', ['ROLE_ADMIN']);
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($admin);
        $em->flush();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $reader = $this->bootstrapUser(self::USERNAME, ['ROLE_USER']);
        $this->set($reader, 'createdBy', $admin);
        $this->set($reader, 'firstName', 'Ledger');
        $this->set($reader, 'lastName', 'Reader');
        $this->set($reader, 'position', 'Bookkeeper');
        $em->persist($reader);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $admin->createCountry($countryCommand);
        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $admin);
        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $admin);

        $own = $this->organization($admin, '1', 'Own Company d.o.o.', self::OWN_ORG, $address);
        $foreign = $this->organization($admin, '2', 'Foreign Company d.o.o.', self::FOREIGN_ORG, $address);
        $reader->addOrganization($own, $admin);

        $class7 = $this->kontoClass($admin, 7, 'Class 7');
        $class8 = $this->kontoClass($admin, 8, 'Class 8');
        $class1 = $this->kontoClass($admin, 1, 'Class 1');
        $incomeCategory = $this->kontoCategory($admin, $class7, 76, 'Income');
        $taxCategory = $this->kontoCategory($admin, $class8, 81, 'Tax');
        $bankCategory = $this->kontoCategory($admin, $class1, 11, 'Bank');
        $debit = $this->konto($admin, $bankCategory, 110, 'Bank', KontoTypes::active);
        $ownIncome = $this->konto($admin, $incomeCategory, 761, self::OWN_KONTO, KontoTypes::passive);
        $foreignIncome = $this->konto($admin, $incomeCategory, 760, self::FOREIGN_INCOME_KONTO, KontoTypes::passive);
        $tax = $this->konto($admin, $taxCategory, 810, self::FOREIGN_TAX_KONTO, KontoTypes::active);
        $balanceCategory = $this->kontoCategory($admin, $class1, 99, 'Balance');
        $balance = $this->konto($admin, $balanceCategory, 990, 'foreign-balance-konto', KontoTypes::active);

        $when = new \DateTime('2026-06-15 12:00:00');
        $ownLine = $this->transaction($reader, $own, $debit, $ownIncome, self::OWN_SUM, self::OWN_TX, $when);
        $foreignLine = $this->transaction($admin, $foreign, $debit, $foreignIncome, self::FOREIGN_INCOME, self::FOREIGN_TX, $when);
        $taxLine = $this->transaction($admin, $foreign, $tax, $debit, self::FOREIGN_TAX, 'foreign-tax-line', $when);
        $balanceLine = $this->transaction($admin, $foreign, $balance, $debit, self::FOREIGN_BALANCE, 'foreign-balance-line', $when);

        $client = $this->partner($admin, 'C1', self::FOREIGN_CLIENT, true, false, $address);
        $supplier = $this->partner($admin, 'S1', self::FOREIGN_SUPPLIER, false, true, $address);
        $invoice = $this->invoice($admin, $foreign, $client, self::FOREIGN_INVOICE, self::FOREIGN_INVOICE_TOTAL, $when);
        $bill = $this->bill($admin, $supplier, $foreign, self::FOREIGN_BILL, self::FOREIGN_BILL_TOTAL, $when);

        foreach ([$country, $post, $address, $own, $own->getOrganizationSettings(), $foreign, $foreign->getOrganizationSettings(), $class7, $class8, $class1, $incomeCategory, $taxCategory, $bankCategory, $balanceCategory, $debit, $ownIncome, $foreignIncome, $tax, $balance, $ownLine, $foreignLine, $taxLine, $balanceLine, $client, $supplier, $invoice, $bill] as $entity) {
            $em->persist($entity);
        }
        $invoiceItem = $invoice->getInvoiceItems()->first();
        if ($invoiceItem) {
            $em->persist($invoiceItem);
        }
        $em->flush();

        $invoiceState = (int) $this->pdo->query('SELECT state FROM invoice')->fetchColumn();
        $billState = (int) $this->pdo->query('SELECT state FROM incoming_invoice')->fetchColumn();
        if ($invoiceState !== 20 || $billState !== 20) {
            self::fail('Fixture documents were not stored as issued/paid, so report totals would not be visible.');
        }
    }

    private function rememberIds(): void
    {
        if (self::$storedOwnTxId === '') {
            self::$storedOwnOrgId = $this->idOfOrganization(self::OWN_ORG);
            self::$storedForeignOrgId = $this->idOfOrganization(self::FOREIGN_ORG);
            self::$storedOwnTxId = $this->idOfTransaction(self::OWN_TX);
            self::$storedForeignTxId = $this->idOfTransaction(self::FOREIGN_TX);
            self::$storedTaxTxId = $this->idOfTransaction('foreign-tax-line');
            self::$storedBalanceTxId = $this->idOfTransaction('foreign-balance-line');
        }
        $this->ownOrgId = self::$storedOwnOrgId;
        $this->foreignOrgId = self::$storedForeignOrgId;
        $this->ownTxId = self::$storedOwnTxId;
        $this->foreignTxId = self::$storedForeignTxId;
        $this->debitKontoId = $this->idOfKonto(110);
        $this->incomeKontoId = $this->idOfKonto(760);
    }

    private function restoreLedger(): void
    {
        $keep = [self::$storedOwnTxId, self::$storedForeignTxId, self::$storedTaxTxId, self::$storedBalanceTxId];
        $delete = $this->pdo->prepare('DELETE FROM transaction WHERE id NOT IN (?, ?, ?, ?)');
        $delete->execute($keep);
        $debit = $this->idOfKonto(110);
        $ownIncome = $this->idOfKonto(761);
        $foreignIncome = $this->idOfKonto(760);
        $tax = $this->idOfKonto(810);
        $balance = $this->idOfKonto(990);
        $update = $this->pdo->prepare('UPDATE transaction SET description = ?, sum = ?, organization_id = ?, hidden = 0, debit_konto_id = ?, credit_konto_id = ? WHERE id = ?');
        $update->execute([self::OWN_TX, $this->moneyNumber(self::OWN_SUM), self::$storedOwnOrgId, $debit, $ownIncome, self::$storedOwnTxId]);
        $update->execute([self::FOREIGN_TX, $this->moneyNumber(self::FOREIGN_INCOME), self::$storedForeignOrgId, $debit, $foreignIncome, self::$storedForeignTxId]);
        $update->execute(['foreign-tax-line', $this->moneyNumber(self::FOREIGN_TAX), self::$storedForeignOrgId, $tax, $debit, self::$storedTaxTxId]);
        $update->execute(['foreign-balance-line', $this->moneyNumber(self::FOREIGN_BALANCE), self::$storedForeignOrgId, $balance, $debit, self::$storedBalanceTxId]);
    }

    private function responseBytes(): string
    {
        $response = $this->client->getResponse();
        if ($response instanceof BinaryFileResponse) {
            return (string) file_get_contents($response->getFile()->getPathname());
        }

        return (string) $response->getContent();
    }

    private function organization(User $admin, string $code, string $name, string $short, object $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $short;
        $command->taxNumber = '1000000'.$code;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI5603100100100123'.$code;
        $command->bic = 'LJBASI2X';

        return $admin->createOrganization($command);
    }

    private function partner(User $admin, string $code, string $name, bool $client, bool $supplier, object $address): object
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = '2000000'.$code;
        $command->taxable = true;
        $command->address = $address;
        $command->isClient = $client;
        $command->isSupplier = $supplier;

        return $admin->createPartner($command);
    }

    private function invoice(User $admin, Organization $issuer, object $client, string $number, float $total, \DateTime $when): Invoice
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = $when;
        $command->dateServiceRenderedFrom = $when;
        $command->dateServiceRenderedTo = $when;
        $command->dueDate = $when;
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $issuer;
        $command->recepient = $client;
        $invoice = $admin->createInvoice($command);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = $number;
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = $total;
        $item->discount = 0;
        $invoice->createInvoiceItem($item);
        $state = new ReflectionProperty(Invoice::class, 'state');
        $state->setValue($invoice, 20);

        return $invoice;
    }

    private function bill(User $admin, object $supplier, Organization $recepient, string $number, float $total, \DateTime $when): IncomingInvoice
    {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = $when;
        $command->dueDate = $when;
        $command->issuer = $supplier;
        $command->recepient = $recepient;
        $command->number = $number;
        $command->reference = $number;
        $command->price = $total;
        $bill = $admin->createIncomingInvoice($command);
        $state = new ReflectionProperty(IncomingInvoice::class, 'state');
        $state->setValue($bill, 20);

        return $bill;
    }

    private function transaction(User $user, Organization $organization, Konto $debit, Konto $credit, float $sum, string $description, \DateTime $when): Transaction
    {
        $command = new CreateTransactionCommand();
        $command->organization = $organization;
        $command->debitKonto = $debit;
        $command->creditKonto = $credit;
        $command->sum = $sum;
        $command->date = $when;
        $command->description = $description;
        $command->hidden = false;

        return $user->createTransactionWithDescription($command);
    }

    private function kontoClass(User $user, int $number, string $name): KontoClass
    {
        $command = new class extends CreateKontoClassCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;

        return new KontoClass($command, $user);
    }

    private function kontoCategory(User $user, KontoClass $class, int $number, string $name): KontoCategory
    {
        $command = new class extends CreateKontoCategoryCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;

        return new KontoCategory($command, $class, $user);
    }

    private function konto(User $user, KontoCategory $category, int $number, string $name, int $type): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = $type;

        return new Konto($command, $category, $user);
    }

    private function bootstrapUser(string $username, array $roles): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Ledger');
        $this->set($user, 'lastName', 'Admin');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Administrator');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', $roles);
        $this->set($user, 'password', 'placeholder-not-a-password');

        return $user;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }

    private function transactionCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM transaction')->fetchColumn();
    }

    private function countDescription(string $description): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM transaction WHERE description = ?');
        $statement->execute([$description]);

        return (int) $statement->fetchColumn();
    }

    private function countForOrganization(string $shortName): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM transaction t INNER JOIN organization o ON o.id = t.organization_id WHERE o.short_name = ?');
        $statement->execute([$shortName]);

        return (int) $statement->fetchColumn();
    }

    private function organizationOf(string $description): string
    {
        $statement = $this->pdo->prepare('SELECT o.short_name FROM transaction t INNER JOIN organization o ON o.id = t.organization_id WHERE t.description = ?');
        $statement->execute([$description]);
        $name = $statement->fetchColumn();
        $this->assertIsString($name);

        return $name;
    }

    private function descriptionOf(string $id): string
    {
        $statement = $this->pdo->prepare('SELECT description FROM transaction WHERE id = ?');
        $statement->execute([$id]);
        $description = $statement->fetchColumn();
        $this->assertIsString($description);

        return $description;
    }

    private function sumOf(string $id): string
    {
        $statement = $this->pdo->prepare('SELECT sum FROM transaction WHERE id = ?');
        $statement->execute([$id]);

        return (string) $statement->fetchColumn();
    }

    private function idOfOrganization(string $shortName): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM organization WHERE short_name = ?');
        $statement->execute([$shortName]);

        return $this->uuidString((string) $statement->fetchColumn());
    }

    private function idOfTransaction(string $description): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM transaction WHERE description = ?');
        $statement->execute([$description]);

        return $this->uuidString((string) $statement->fetchColumn());
    }

    private function idOfKonto(int $number): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM konto WHERE number = ?');
        $statement->execute([$number]);

        return $this->uuidString((string) $statement->fetchColumn());
    }

    private function uuidString(string $stored): string
    {
        if (strlen($stored) === 36) {
            return $stored;
        }

        return Uuid::fromBytes($stored)->toString();
    }

    private function money(float $amount): string
    {
        return MoneyFormatter::amount($amount);
    }

    private function moneyNumber(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function xlsxText(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ledger');
        if ($path === false) {
            self::fail('Could not create a temporary spreadsheet file.');
        }
        file_put_contents($path, $bytes);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);
        $text = '';
        foreach ($sheet->getActiveSheet()->toArray() as $row) {
            foreach ($row as $cell) {
                $text .= ' '.$cell;
            }
        }

        return $text;
    }
}
