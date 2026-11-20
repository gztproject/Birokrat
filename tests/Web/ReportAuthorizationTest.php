<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\Enumerators\PaymentMethods;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use App\Money\MoneyFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Financial reports must be limited to an organization the signed-in user belongs to.
 * These tests fail while an organization id from the query string is optional and unchecked.
 */
class ReportAuthorizationTest extends WebTestCase
{
    private const PLACEHOLDER_PASSWORD = 'placeholder-password-1';

    private const VICTIM_INCOME = 81011.50;

    private const VICTIM_PURCHASE = 54055.50;

    private const ATTACKER_INCOME = 42022.50;

    private const ROUTES = [
        '/dashboard/report',
        '/dashboard/report/dddddd',
        '/dashboard/report/blianca',
        '/dashboard/report/turnout',
    ];

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private User $attacker;

    private User $victimUser;

    private Organization $attackerOrg;

    private Organization $victimOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $books = $this->bookCompanies($hasher);
        $this->attacker = $books['attacker'];
        $this->victimUser = $books['victimUser'];
        $this->attackerOrg = $books['attackerOrg'];
        $this->victimOrg = $books['victimOrg'];
    }

    public function testAnonymousRequestDoesNotRenderReportFigures(): void
    {
        foreach (self::ROUTES as $route) {
            $this->client->request('GET', $route, ['organization' => $this->victimOrg->getId()->toString()]);
            $body = (string) $this->client->getResponse()->getContent();
            $problems = [];
            if (!$this->client->getResponse()->isRedirect()) {
                $problems[] = 'status '.$this->client->getResponse()->getStatusCode().' is not a login redirect';
            }
            foreach ($this->markers() as $marker) {
                if (str_contains($body, $marker)) {
                    $problems[] = 'body contains '.$marker;
                }
            }
            self::assertSame([], $problems, $route.' '.implode('; ', $problems));
        }
    }

    public function testAssignedUserSeesOnlyTheRequestedOrganization(): void
    {
        $this->client->loginUser($this->victimUser);
        foreach (self::ROUTES as $route) {
            $this->assertShowsOrganization(
                $route,
                ['organization' => $this->victimOrg->getId()->toString()],
                [$this->amount(self::VICTIM_INCOME), $this->amount(self::VICTIM_PURCHASE)],
                [$this->amount(self::ATTACKER_INCOME)],
            );
        }

        $this->client->loginUser($this->attacker);
        foreach (['/dashboard/report', '/dashboard/report/blianca', '/dashboard/report/turnout'] as $route) {
            $this->assertShowsOrganization(
                $route,
                ['organization' => $this->attackerOrg->getId()->toString()],
                [$this->amount(self::ATTACKER_INCOME)],
                [$this->amount(self::VICTIM_INCOME), $this->amount(self::VICTIM_PURCHASE)],
            );
        }

        // Category 79 is not a line on the tax summary, so the attacker's own income must not
        // pull the other company's category 76 and 40 totals onto that form.
        $this->assertShowsOrganization(
            '/dashboard/report/dddddd',
            ['organization' => $this->attackerOrg->getId()->toString()],
            [],
            [$this->amount(self::VICTIM_INCOME), $this->amount(self::VICTIM_PURCHASE), $this->amount(self::ATTACKER_INCOME)],
        );
    }

    public function testUserAssignedToBothOrganizationsSeesOnlyTheRequestedOne(): void
    {
        $this->attacker->addOrganization($this->victimOrg, $this->attacker);
        $this->em->flush();
        $this->client->loginUser($this->attacker);

        foreach (self::ROUTES as $route) {
            $this->assertShowsOrganization(
                $route,
                ['organization' => $this->victimOrg->getId()->toString()],
                [$this->amount(self::VICTIM_INCOME), $this->amount(self::VICTIM_PURCHASE)],
                [$this->amount(self::ATTACKER_INCOME)],
            );
            $present = $route === '/dashboard/report/dddddd' ? [] : [$this->amount(self::ATTACKER_INCOME)];
            $absent = [$this->amount(self::VICTIM_INCOME), $this->amount(self::VICTIM_PURCHASE)];
            if ($route === '/dashboard/report/dddddd') {
                $absent[] = $this->amount(self::ATTACKER_INCOME);
            }
            $this->assertShowsOrganization(
                $route,
                ['organization' => $this->attackerOrg->getId()->toString()],
                $present,
                $absent,
            );
        }
    }

    public function testAnotherCompanysOrganizationIdIsRejected(): void
    {
        $this->client->loginUser($this->attacker);
        $this->assertRoutesRefused(['organization' => $this->victimOrg->getId()->toString()]);
    }

    public function testMissingOrganizationIdIsRejected(): void
    {
        $this->client->loginUser($this->attacker);
        $this->assertRoutesRefused([]);
    }

    public function testEmptyOrganizationIdIsRejected(): void
    {
        $this->client->loginUser($this->attacker);
        $this->assertRoutesRefused(['organization' => '']);
    }

    public function testOrganizationIdTheUserDoesNotBelongToIsRejected(): void
    {
        $this->client->loginUser($this->attacker);
        $this->assertRoutesRefused(['organization' => Uuid::uuid4()->toString()]);
    }

    /**
     * @param list<string> $present
     * @param list<string> $absent
     */
    private function assertShowsOrganization(string $path, array $query, array $present, array $absent): void
    {
        $this->client->request('GET', $path, $query);
        $response = $this->client->getResponse();
        $body = (string) $response->getContent();
        $problems = [];
        if ($response->getStatusCode() !== 200) {
            $problems[] = 'status '.$response->getStatusCode();
            $problems[] = $this->excerpt($body);
        }
        foreach ($present as $marker) {
            if (!str_contains($body, $marker)) {
                $problems[] = 'missing '.$marker;
            }
        }
        foreach ($absent as $marker) {
            if (str_contains($body, $marker)) {
                $problems[] = 'contains '.$marker;
            }
        }
        self::assertSame([], $problems, $path.'?'.http_build_query($query).' '.implode('; ', $problems));
    }

    private function assertRoutesRefused(array $query): void
    {
        $problems = [];
        foreach (self::ROUTES as $route) {
            $problems = array_merge($problems, $this->refusalProblems($route, $query));
        }
        self::assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * @return list<string>
     */
    private function refusalProblems(string $path, array $query): array
    {
        $this->client->request('GET', $path, $query);
        $response = $this->client->getResponse();
        $body = (string) $response->getContent();
        $status = $response->getStatusCode();
        $problems = [];
        $prefix = $path.'?'.http_build_query($query);
        foreach ($this->markers() as $marker) {
            if (str_contains($body, $marker)) {
                $problems[] = $prefix.' contains '.$marker;
            }
        }
        if (!in_array($status, [400, 403, 404], true)) {
            $problems[] = $prefix.' status '.$status.' was not refused';
        }
        if ($status >= 500) {
            $problems[] = $prefix.' '.$this->excerpt($body);
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function markers(): array
    {
        return [
            $this->amount(self::VICTIM_INCOME),
            $this->amount(self::VICTIM_PURCHASE),
            $this->amount(self::ATTACKER_INCOME),
        ];
    }

    private function amount(float $value): string
    {
        return MoneyFormatter::amount($value);
    }

    private function excerpt(string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return substr($text, 0, 300);
    }

    /**
     * @return array{attacker: User, victimUser: User, attackerOrg: Organization, victimOrg: Organization}
     */
    private function bookCompanies(UserPasswordHasherInterface $hasher): array
    {
        $suffix = bin2hex(random_bytes(4));
        $admin = $this->bootstrapUser('report-admin-'.$suffix, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $attacker = $admin->createUser($this->userCommand('report-attacker-'.$suffix, 'attacker-'.$suffix.'@example.test'), $hasher);
        $victimUser = $admin->createUser($this->userCommand('report-victim-'.$suffix, 'victim-'.$suffix.'@example.test'), $hasher);
        $this->em->persist($attacker);
        $this->em->persist($victimUser);

        $address = $this->address($admin, $suffix);
        $attackerOrg = $this->organization($admin, $address, 'A'.$suffix, 'Attacker '.$suffix);
        $victimOrg = $this->organization($admin, $address, 'V'.$suffix, 'Victim '.$suffix);
        $attacker->addOrganization($attackerOrg, $admin);
        $victimUser->addOrganization($victimOrg, $admin);

        $attackerClient = $this->partner($admin, $address, 'AC'.$suffix, 'Attacker client '.$suffix, true, false);
        $victimClient = $this->partner($admin, $address, 'VC'.$suffix, 'Victim client '.$suffix, true, false);
        $victimSupplier = $this->partner($admin, $address, 'VS'.$suffix, 'Victim supplier '.$suffix, false, true);

        $income = $this->konto($admin, 7, 76, 760, KontoTypes::passive, 'Sales '.$suffix);
        $bank = $this->konto($admin, 1, 11, 110, KontoTypes::active, 'Bank '.$suffix);
        $expense = $this->konto($admin, 4, 40, 400, KontoTypes::active, 'Expense '.$suffix);
        $payable = $this->konto($admin, 2, 22, 220, KontoTypes::passive, 'Payable '.$suffix);
        $otherIncome = $this->konto($admin, 7, 79, 790, KontoTypes::passive, 'Other income '.$suffix);
        $receivable = $this->konto($admin, 1, 12, 120, KontoTypes::active, 'Receivable '.$suffix);

        $this->em->flush();

        $this->configureIssue($victimOrg, $admin, $bank, $income);
        $this->configurePurchase($victimOrg, $admin, $expense, $payable);
        $this->configureIssue($attackerOrg, $admin, $receivable, $otherIncome);

        $issuedOn = new \DateTime('2024-06-15');
        $victimInvoice = $this->issue($admin, $victimOrg, $victimClient, '8101150', self::VICTIM_INCOME, $issuedOn);
        $attackerInvoice = $this->issue($admin, $attackerOrg, $attackerClient, '4202250', self::ATTACKER_INCOME, $issuedOn);
        $purchase = $this->purchase($admin, $victimOrg, $victimSupplier, '5405550', self::VICTIM_PURCHASE, $issuedOn);

        $this->em->persist($victimInvoice['item']);
        $this->em->persist($victimInvoice['invoice']);
        $this->em->persist($victimInvoice['transaction']);
        $this->em->persist($attackerInvoice['item']);
        $this->em->persist($attackerInvoice['invoice']);
        $this->em->persist($attackerInvoice['transaction']);
        $this->em->persist($purchase['invoice']);
        foreach ($purchase['transactions'] as $transaction) {
            $this->em->persist($transaction);
        }
        $this->em->flush();

        return [
            'attacker' => $attacker,
            'victimUser' => $victimUser,
            'attackerOrg' => $attackerOrg,
            'victimOrg' => $victimOrg,
        ];
    }

    private function userCommand(string $username, string $email): CreateUserCommand
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Report';
        $command->lastName = 'User';
        $command->email = $email;
        $command->password = self::PLACEHOLDER_PASSWORD;
        $command->isRoleAdmin = false;
        $command->signatureFilename = '';

        return $command;
    }

    private function bootstrapUser(string $username, bool $admin): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setProperty($user, 'id', Uuid::uuid6());
        $this->setProperty($user, 'createdOn', new \DateTime());
        $this->setProperty($user, 'createdBy', $user);
        $this->setProperty($user, 'isActive', true);
        $this->setProperty($user, 'organizations', new ArrayCollection());
        $this->setProperty($user, 'username', $username);
        $this->setProperty($user, 'firstName', 'Report');
        $this->setProperty($user, 'lastName', 'Admin');
        $this->setProperty($user, 'email', $username.'@example.test');
        $this->setProperty($user, 'mobile', null);
        $this->setProperty($user, 'phone', null);
        $this->setProperty($user, 'signatureFilename', '');
        $this->setProperty($user, 'position', 'Administrator');
        $this->setProperty($user, 'totpEnabled', false);
        $this->setProperty($user, 'totpSecret', null);
        $this->setProperty($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->setProperty($user, 'password', $hasher->hashPassword($user, self::PLACEHOLDER_PASSWORD));

        return $user;
    }

    private function address(User $admin, string $suffix): \App\Entity\Geography\Address
    {
        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = substr($suffix, 0, 8);
        $postCommand->codeInternational = 'SI-'.$suffix;
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $admin);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $admin);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);

        return $address;
    }

    private function organization(User $admin, \App\Entity\Geography\Address $address, string $code, string $name): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $code;
        $command->taxNumber = $code;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56000000000000000';
        $command->bic = 'TESTSI2X';
        $organization = $admin->createOrganization($command);
        $this->em->persist($organization);

        return $organization;
    }

    private function partner(User $admin, \App\Entity\Geography\Address $address, string $code, string $name, bool $client, bool $supplier): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $code;
        $command->taxNumber = $code;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56000000000000001';
        $command->bic = 'TESTSI2X';
        $command->isClient = $client;
        $command->isSupplier = $supplier;
        $partner = $admin->createPartner($command);
        $this->em->persist($partner);

        return $partner;
    }

    private function konto(User $admin, int $classNumber, int $categoryNumber, int $number, int $type, string $name): Konto
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = $classNumber;
        $classCommand->name = 'Class '.$classNumber.' '.$name;
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = $categoryNumber;
        $categoryCommand->name = 'Category '.$categoryNumber.' '.$name;
        $categoryCommand->description = '';
        $category = $class->createCategory($categoryCommand, $admin);

        $kontoCommand = new class extends CreateKontoCommand {
            public int $type;
        };
        $kontoCommand->number = $number;
        $kontoCommand->name = $name;
        $kontoCommand->description = '';
        $kontoCommand->isActive = true;
        $kontoCommand->type = $type;
        $konto = $category->createKonto($kontoCommand, $admin);

        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($konto);

        return $konto;
    }

    private function configureIssue(Organization $organization, User $admin, Konto $debit, Konto $credit): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $command->autoCreatePerDiem = false;
        $command->autoCreateLunch = false;
        $command->IssueInvoiceDebit = $debit;
        $command->IssueInvoiceCredit = $credit;
        $organization->updateOrganizationSettings($command, $admin);
    }

    private function configurePurchase(Organization $organization, User $admin, Konto $debit, Konto $credit): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $command->autoCreatePerDiem = false;
        $command->autoCreateLunch = false;
        $command->IssueInvoiceDebit = $organization->getOrganizationSettings()->getIssueInvoiceDebit();
        $command->IssueInvoiceCredit = $organization->getOrganizationSettings()->getIssueInvoiceCredit();
        $command->ReceivedIncomingInvoiceDebit = $debit;
        $command->PaidTransactionIncomingInvoiceCredit = $credit;
        $organization->updateOrganizationSettings($command, $admin);
    }

    /**
     * @return array{invoice: \App\Entity\Invoice\Invoice, item: \App\Entity\Invoice\InvoiceItem, transaction: \App\Entity\Transaction\Transaction}
     */
    private function issue(User $admin, Organization $issuer, Partner $recipient, string $number, float $price, \DateTime $date): array
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = $date;
        $command->dateServiceRenderedFrom = $date;
        $command->dateServiceRenderedTo = $date;
        $command->dueDate = $date;
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $issuer;
        $command->recepient = $recipient;
        $invoice = $admin->createInvoice($command);
        $itemCommand = new CreateInvoiceItemCommand();
        $itemCommand->code = '1';
        $itemCommand->name = 'Service';
        $itemCommand->quantity = 1;
        $itemCommand->unit = 'x';
        $itemCommand->price = $price;
        $itemCommand->discount = 0;
        $item = $invoice->createInvoiceItem($itemCommand);
        $transaction = $invoice->setIssued($date, $number, $admin);

        return ['invoice' => $invoice, 'item' => $item, 'transaction' => $transaction];
    }

    /**
     * @return array{invoice: \App\Entity\IncomingInvoice\IncomingInvoice, transactions: list<\App\Entity\Transaction\Transaction>}
     */
    private function purchase(User $admin, Organization $recipient, Partner $supplier, string $number, float $price, \DateTime $date): array
    {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = $date;
        $command->dueDate = $date;
        $command->issuer = $supplier;
        $command->recepient = $recipient;
        $command->number = $number;
        $command->reference = $number;
        $command->price = $price;
        $invoice = $admin->createIncomingInvoice($command);
        $transactions = $invoice->setReceivedAndPaid($date, $admin, PaymentMethods::transaction);

        return ['invoice' => $invoice, 'transactions' => $transactions];
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
