<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\Enumerators\States as IncomingStates;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Enumerators\States as InvoiceStates;
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
use App\Entity\Organization\Partner;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Home dashboard queries must be limited to organizations linked to the signed-in user.
 *
 * Sales invoices are the company's issued invoices. Supplier bills are incoming invoices
 * received by the company. Travel claims and journal lines belong to the company on
 * their organization association. Totals use the same sets: issued receivables (state 20),
 * overdue issued invoices, and unpaid received bills (state 10).
 *
 * These assertions fail while those queries are unscoped.
 */
class DashboardCompanyScopeTest extends WebTestCase
{
    private const OWN_INVOICE = '10101991';
    private const FOREIGN_INVOICE = '90909931';
    private const FOREIGN_PAID_INVOICE = '30303030';
    private const OWN_BILL = 'BILL-OWN-44110';
    private const FOREIGN_BILL = 'BILL-FOREIGN-99331';
    private const OWN_PLACE = 'Ownhaven';
    private const FOREIGN_PLACE = 'Foreignhaven';
    private const OWN_JOURNAL = 'OWN-JRN-44110';
    private const FOREIGN_JOURNAL = 'FOREIGN-JRN-99331';
    private const OWN_COMPANY = 'OwnCo';
    private const FOREIGN_COMPANY = 'ForeignCo';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $author;

    /** @var array<string, User> */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema();
        $this->users = $this->loadBooks();
    }

    public function testMemberOfOneCompanyDoesNotSeeTheOtherCompanyOnEitherHomeRoute(): void
    {
        $this->client->loginUser($this->users['member']);

        foreach (['/', '/dashboard'] as $path) {
            $this->client->request('GET', $path);
            $this->assertSuccessfulDashboard($path);
            $this->assertSame([1, 1, 1], $this->kpiCounts($this->client->getCrawler()), $path.' totals must count only the member company');
            $this->assertDashboardContains($this->client->getCrawler(), $this->ownMarkers(), $path);
            $this->assertDashboardOmits($this->client->getCrawler(), $this->foreignMarkers(), $path);
        }
    }

    public function testUserWithNoCompanyLinkSeesNoCompanyActivity(): void
    {
        $this->client->loginUser($this->users['unlinked']);

        foreach (['/', '/dashboard'] as $path) {
            $this->client->request('GET', $path);
            $this->assertSuccessfulDashboard($path);
            $this->assertSame([0, 0, 0], $this->kpiCounts($this->client->getCrawler()), $path.' totals must be empty without a company link');
            $this->assertDashboardOmits($this->client->getCrawler(), array_merge($this->ownMarkers(), $this->foreignMarkers()), $path);
        }
    }

    public function testUserLinkedToBothCompaniesSeesBoth(): void
    {
        $this->client->loginUser($this->users['both']);

        foreach (['/', '/dashboard'] as $path) {
            $this->client->request('GET', $path);
            $this->assertSuccessfulDashboard($path);
            $this->assertSame([2, 2, 2], $this->kpiCounts($this->client->getCrawler()), $path.' totals must include every linked company');
            $this->assertDashboardContains($this->client->getCrawler(), array_merge($this->ownMarkers(), $this->foreignMarkers()), $path);
        }
    }

    /**
     * @return list<string>
     */
    private function ownMarkers(): array
    {
        return [self::OWN_INVOICE, self::OWN_BILL, self::OWN_PLACE, self::OWN_JOURNAL, self::OWN_COMPANY];
    }

    /**
     * @return list<string>
     */
    private function foreignMarkers(): array
    {
        return [self::FOREIGN_INVOICE, self::FOREIGN_PAID_INVOICE, self::FOREIGN_BILL, self::FOREIGN_PLACE, self::FOREIGN_JOURNAL, self::FOREIGN_COMPANY];
    }

    /**
     * Receivables, overdue invoices, unpaid bills — in that order.
     *
     * @return list<int>
     */
    private function kpiCounts(Crawler $crawler): array
    {
        $counts = $crawler->filter('.kpi-meta')->each(function (Crawler $node): int {
            if (!preg_match('/(\d+)/', $node->text(), $match)) {
                $this->fail('KPI meta did not contain a count: '.$node->text());
            }

            return (int) $match[1];
        });
        $this->assertCount(3, $counts, 'Dashboard should render receivables, overdue, and unpaid-bill KPIs');

        return $counts;
    }

    /**
     * @param list<string> $markers
     */
    private function assertDashboardContains(Crawler $crawler, array $markers, string $path): void
    {
        $html = $crawler->html();
        foreach ($markers as $marker) {
            $this->assertStringContainsString($marker, $html, $path.' should show '.$marker);
        }
    }

    /**
     * @param list<string> $markers
     */
    private function assertDashboardOmits(Crawler $crawler, array $markers, string $path): void
    {
        $html = $crawler->html();
        foreach ($markers as $marker) {
            $this->assertStringNotContainsString($marker, $html, $path.' should not show '.$marker);
        }
    }

    private function assertSuccessfulDashboard(string $path): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertSame(200, $status, $path.' returned '.$status."\n".substr((string) $this->client->getResponse()->getContent(), 0, 1500));
    }

    private function resetSchema(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * @return array{member: User, unlinked: User, both: User}
     */
    private function loadBooks(): array
    {
        $this->author = $this->user('author');
        $member = $this->user('member');
        $unlinked = $this->user('unlinked');
        $both = $this->user('both');
        $this->flushUsers($this->author, $member, $unlinked, $both);

        $country = $this->author->createCountry($this->countryCommand());
        $addressPost = $country->createPost($this->postCommand('1000', 'Ljubljana'), $this->author);
        $ownPost = $country->createPost($this->postCommand('2000', self::OWN_PLACE), $this->author);
        $foreignPost = $country->createPost($this->postCommand('3000', self::FOREIGN_PLACE), $this->author);
        $address = $addressPost->createAddress($this->addressCommand(), $this->author);

        $ownOrg = $this->organization('1', 'Own Company d.o.o.', self::OWN_COMPANY, '11111111', $address);
        $foreignOrg = $this->organization('2', 'Foreign Company d.o.o.', self::FOREIGN_COMPANY, '22222222', $address);
        $ownCustomer = $this->partner('3', 'Own Customer d.o.o.', 'OwnCustomer', '33333333', true, false, $address);
        $foreignCustomer = $this->partner('4', 'Foreign Customer d.o.o.', 'ForeignCustomer', '44444444', true, false, $address);
        $ownSupplier = $this->partner('5', 'Own Supplier d.o.o.', 'OwnSupplier', '55555555', false, true, $address);
        $foreignSupplier = $this->partner('6', 'Foreign Supplier d.o.o.', 'ForeignSupplier', '66666666', false, true, $address);

        $member->addOrganization($ownOrg, $this->author);
        $both->addOrganization($ownOrg, $this->author);
        $both->addOrganization($foreignOrg, $this->author);

        [$debit, $credit] = $this->kontos();

        $ownInvoice = $this->salesInvoice($ownOrg, $ownCustomer, self::OWN_INVOICE, 100, InvoiceStates::issued, new \DateTime('-5 days'));
        $foreignInvoice = $this->salesInvoice($foreignOrg, $foreignCustomer, self::FOREIGN_INVOICE, 900, InvoiceStates::issued, new \DateTime('-5 days'));
        $foreignPaid = $this->salesInvoice($foreignOrg, $foreignCustomer, self::FOREIGN_PAID_INVOICE, 50, InvoiceStates::paid, new \DateTime('+10 days'));

        $ownBill = $this->supplierBill($ownSupplier, $ownOrg, self::OWN_BILL, 200);
        $foreignBill = $this->supplierBill($foreignSupplier, $foreignOrg, self::FOREIGN_BILL, 800);

        $ownTravel = $this->travelClaim($ownOrg, $member, $ownPost, self::OWN_PLACE);
        $foreignTravel = $this->travelClaim($foreignOrg, $this->author, $foreignPost, self::FOREIGN_PLACE);

        $ownJournal = $this->journalLine($ownOrg, $debit, $credit, self::OWN_JOURNAL, 300);
        $foreignJournal = $this->journalLine($foreignOrg, $debit, $credit, self::FOREIGN_JOURNAL, 700);

        foreach ([
            $country, $addressPost, $ownPost, $foreignPost, $address,
            $ownOrg, $ownOrg->getOrganizationSettings(), $foreignOrg, $foreignOrg->getOrganizationSettings(),
            $ownCustomer, $foreignCustomer, $ownSupplier, $foreignSupplier,
            $debit->getClass(), $debit->getCategory(), $debit, $credit,
            $ownInvoice, $ownInvoice->getInvoiceItems()->first(),
            $foreignInvoice, $foreignInvoice->getInvoiceItems()->first(),
            $foreignPaid, $foreignPaid->getInvoiceItems()->first(),
            $ownBill, $foreignBill,
            $ownTravel, $ownTravel->getTravelStops()->first(),
            $foreignTravel, $foreignTravel->getTravelStops()->first(),
            $ownJournal, $foreignJournal,
        ] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return ['member' => $member, 'unlinked' => $unlinked, 'both' => $both];
    }

    private function user(string $username): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setPrivate($user, 'id', Uuid::uuid6());
        $this->setPrivate($user, 'createdOn', new \DateTime());
        $this->setPrivate($user, 'createdBy', $user);
        $this->setPrivate($user, 'isActive', true);
        $this->setPrivate($user, 'organizations', new ArrayCollection());
        $this->setPrivate($user, 'username', $username);
        $this->setPrivate($user, 'firstName', 'Test');
        $this->setPrivate($user, 'lastName', $username);
        $this->setPrivate($user, 'email', $username.'@example.test');
        $this->setPrivate($user, 'mobile', null);
        $this->setPrivate($user, 'phone', null);
        $this->setPrivate($user, 'signatureFilename', '');
        $this->setPrivate($user, 'position', 'Clerk');
        $this->setPrivate($user, 'totpEnabled', false);
        $this->setPrivate($user, 'totpSecret', null);
        $this->setPrivate($user, 'roles', ['ROLE_USER']);
        $this->setPrivate($user, 'password', 'not-a-secret');

        return $user;
    }

    private function flushUsers(User ...$users): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($users as $user) {
            $this->em->persist($user);
        }
        $this->em->flush();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function countryCommand(): CreateCountryCommand
    {
        $command = new CreateCountryCommand();
        $command->name = 'Slovenija';
        $command->nameInt = 'Slovenia';
        $command->A2 = 'SI';
        $command->A3 = 'SVN';
        $command->N3 = 705;

        return $command;
    }

    private function postCommand(string $code, string $name): CreatePostCommand
    {
        $command = new CreatePostCommand();
        $command->code = $code;
        $command->codeInternational = 'SI-'.$code;
        $command->name = $name;

        return $command;
    }

    private function addressCommand(): CreateAddressCommand
    {
        $command = new CreateAddressCommand();
        $command->line1 = 'Trg 1';
        $command->line2 = '';

        return $command;
    }

    private function organization(string $code, string $name, string $shortName, string $taxNumber, object $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $shortName;
        $command->taxNumber = $taxNumber;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56031001001001234';
        $command->bic = 'LJBASI2X';
        $organization = $this->author->createOrganization($command);
        $this->setPrivate($organization->getOrganizationSettings(), 'travelExpenseRate', 0.43);

        return $organization;
    }

    private function partner(string $code, string $name, string $shortName, string $taxNumber, bool $client, bool $supplier, object $address): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $shortName;
        $command->taxNumber = $taxNumber;
        $command->taxable = true;
        $command->address = $address;
        $command->isClient = $client;
        $command->isSupplier = $supplier;

        return $this->author->createPartner($command);
    }

    /**
     * @return array{0: Konto, 1: Konto}
     */
    private function kontos(): array
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 1;
        $classCommand->name = 'Class';
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $this->author);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 10;
        $categoryCommand->name = 'Category';
        $categoryCommand->description = '';
        $category = new KontoCategory($categoryCommand, $class, $this->author);

        return [$this->konto(110, $category), $this->konto(760, $category)];
    }

    private function konto(int $number, KontoCategory $category): Konto
    {
        $command = new class extends CreateKontoCommand {
            public $type;
        };
        $command->number = $number;
        $command->name = 'Konto '.$number;
        $command->description = '';
        $command->isActive = true;
        $command->type = KontoTypes::active;

        return new Konto($command, $category, $this->author);
    }

    private function salesInvoice(Organization $issuer, Partner $recipient, string $number, int $price, int $state, \DateTime $dueDate): object
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('today');
        $command->dateServiceRenderedFrom = new \DateTime('-7 days');
        $command->dateServiceRenderedTo = new \DateTime('-1 days');
        $command->dueDate = $dueDate;
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $issuer;
        $command->recepient = $recipient;
        $invoice = $this->author->createInvoice($command);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Work';
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = $price;
        $item->discount = 0;
        $invoice->createInvoiceItem($item);
        $this->setPrivate($invoice, 'state', $state);

        return $invoice;
    }

    private function supplierBill(Partner $issuer, Organization $recipient, string $number, int $price): object
    {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = new \DateTime('today');
        $command->dueDate = new \DateTime('-5 days');
        $command->issuer = $issuer;
        $command->recepient = $recipient;
        $command->number = $number;
        $command->price = $price;
        $command->reference = 'SI00 '.$number;
        $bill = $this->author->createIncomingInvoice($command);
        $this->setPrivate($bill, 'state', IncomingStates::received);

        return $bill;
    }

    private function travelClaim(Organization $organization, User $employee, object $post, string $place): object
    {
        $command = new CreateTravelExpenseCommand();
        $command->date = new \DateTime('today');
        $command->organization = $organization;
        $command->employee = $employee;
        $command->rate = 0.43;
        $command->reason = $place;
        $command->number = 'PN-'.$place;
        $claim = $this->author->createTravelExpense($command);
        $stop = new CreateTravelStopCommand();
        $stop->post = $post;
        $stop->stopOrder = 1;
        $stop->distanceFromPrevious = 10;
        $claim->createTravelStop($stop);

        return $claim;
    }

    private function journalLine(Organization $organization, Konto $debit, Konto $credit, string $description, int $sum): object
    {
        $command = new CreateTransactionCommand();
        $command->organization = $organization;
        $command->debitKonto = $debit;
        $command->creditKonto = $credit;
        $command->date = new \DateTime('today');
        $command->sum = $sum;
        $command->description = $description;
        $command->hidden = false;

        return $this->author->createTransactionWithDescription($command);
    }

    private function setPrivate(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
            if ($class === false) {
                throw new \RuntimeException('Missing property '.$name);
            }
        }
        $class->getProperty($name)->setValue($object, $value);
    }
}
