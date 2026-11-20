<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\Enumerators\PaymentMethods;
use App\Entity\IncomingInvoice\Enumerators\States;
use App\Entity\IncomingInvoice\IncomingInvoice;
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
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Repository\IncomingInvoice\IncomingInvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Supplier bills are scoped to the recipient company.
 *
 * These tests fail while list, show, pay, reject, clone, and create accept a
 * bill or recipient company the signed-in user does not belong to.
 * There is no refund route, so refund is not covered.
 * Pay, reject, show, and clone must load the bill once and act on that same
 * object: a second load is handed back the caller's own bill, so a check that
 * reloads the id before the update fails closed.
 */
class IncomingInvoiceAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $outsider;
    private User $member;
    private Organization $outsiderOrg;
    private Organization $victimOrg;
    private IncomingInvoice $ownBill;
    private IncomingInvoice $victimPay;
    private IncomingInvoice $victimReject;
    private IncomingInvoice $victimClone;
    private Konto $debitKonto;
    private Partner $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema();
        $this->loadWorld();
    }

    public function testOutsiderListHidesOtherCompanyBills(): void
    {
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/dashboard/incomingInvoice');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBill->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimPay->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimReject->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimClone->getNumber(), $content);
        $this->assertSame(States::received, $this->reload($this->victimPay)->getState());
    }

    public function testOutsiderPartialListHidesOtherCompanyBills(): void
    {
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/dashboard/incomingInvoice?partial=1');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBill->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimPay->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimReject->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimClone->getNumber(), $content);
    }

    public function testMemberListHidesBillsOfOtherCompanies(): void
    {
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/dashboard/incomingInvoice');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->victimPay->getNumber(), $content);
        $this->assertStringNotContainsString($this->ownBill->getNumber(), $content);
    }

    public function testDashboardHidesOtherCompanyBills(): void
    {
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBill->getNumber(), $content);
        $this->assertStringNotContainsString($this->victimPay->getNumber(), $content);
        $this->assertStringNotContainsString('22,22', $content);
        $this->assertStringContainsString('1 odprtih', $content);
    }

    public function testOutsiderCannotShowOtherCompanyBill(): void
    {
        $gate = $this->installSingleLoadGate();
        $files = $this->scanFiles();
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/dashboard/incomingInvoice/'.$this->id($this->victimPay).'/show');

        $this->assertLessThanOrEqual(1, $gate->loads, 'Show loaded the bill more than once.');
        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $this->assertStringNotContainsString($this->victimPay->getNumber(), (string) $this->client->getResponse()->getContent());
        $this->assertSame(States::received, $this->reload($this->victimPay)->getState());
        $this->assertSame(States::received, $this->reload($this->ownBill)->getState());
    }

    public function testMemberCanShowOwnBill(): void
    {
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/dashboard/incomingInvoice/'.$this->id($this->victimPay).'/show');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->victimPay->getNumber(), (string) $this->client->getResponse()->getContent());
    }

    public function testOutsiderCannotPayOtherCompanyBill(): void
    {
        $gate = $this->installSingleLoadGate();
        $files = $this->scanFiles();
        $ledgerBefore = $this->ledgerCount($this->victimPay);
        $ownLedgerBefore = $this->ledgerCount($this->ownBill);
        $this->client->loginUser($this->outsider);
        $this->client->request('POST', '/dashboard/incomingInvoice/pay', [
            'id' => $this->id($this->victimPay),
            'date' => '2026-11-20',
            'mode' => (string) PaymentMethods::transaction,
            'bankCost' => '0',
        ]);

        $this->assertLessThanOrEqual(1, $gate->loads, 'Pay loaded the bill more than once.');
        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $this->assertSame(States::received, $this->reload($this->victimPay)->getState());
        $this->assertSame(States::received, $this->reload($this->ownBill)->getState());
        $this->assertSame($ledgerBefore, $this->ledgerCount($this->victimPay));
        $this->assertSame($ownLedgerBefore, $this->ledgerCount($this->ownBill));
        $this->assertNull($this->reload($this->victimPay)->getDatePaid());
    }

    public function testMemberCanPayOwnBill(): void
    {
        $ledgerBefore = $this->ledgerCount($this->victimClone);
        $this->client->loginUser($this->member);
        $this->client->request('POST', '/dashboard/incomingInvoice/pay', [
            'id' => $this->id($this->victimClone),
            'date' => '2026-11-20',
            'mode' => (string) PaymentMethods::transaction,
            'bankCost' => '0',
        ]);

        $this->assertResponseRedirects();
        $paid = $this->reload($this->victimClone);
        $this->assertSame(States::paid, $paid->getState());
        $this->assertNotNull($paid->getDatePaid());
        $this->assertSame($ledgerBefore + 1, $this->ledgerCount($this->victimClone));
        $this->assertSame(States::received, $this->reload($this->victimPay)->getState());
    }

    public function testOutsiderCannotRejectOtherCompanyBill(): void
    {
        $gate = $this->installSingleLoadGate();
        $files = $this->scanFiles();
        $this->client->loginUser($this->outsider);
        $this->client->request('POST', '/dashboard/incomingInvoice/reject', [
            'id' => $this->id($this->victimReject),
            'reason' => 'not our supplier bill',
        ]);

        $this->assertLessThanOrEqual(1, $gate->loads, 'Reject loaded the bill more than once.');
        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $rejected = $this->reload($this->victimReject);
        $this->assertSame(States::received, $rejected->getState());
        $this->assertSame(States::received, $this->reload($this->ownBill)->getState());
        $this->assertNotSame('not our supplier bill', $this->rejectedReason($rejected));
    }

    public function testOutsiderCannotOpenCloneOfOtherCompanyBill(): void
    {
        $gate = $this->installSingleLoadGate();
        $files = $this->scanFiles();
        $before = $this->billCount($this->victimOrg);
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/dashboard/incomingInvoice/'.$this->id($this->victimClone).'/clone');

        $this->assertLessThanOrEqual(1, $gate->loads, 'Clone loaded the bill more than once.');
        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $this->assertSame($before, $this->billCount($this->victimOrg));
        $this->assertStringNotContainsString($this->victimClone->getNumber(), (string) $this->client->getResponse()->getContent());
        $this->assertSame(States::received, $this->reload($this->victimClone)->getState());
    }

    public function testOutsiderPostCloneDoesNotPersistOtherCompanyBill(): void
    {
        $gate = $this->installSingleLoadGate();
        $files = $this->scanFiles();
        $before = $this->billCount($this->victimOrg);
        $this->client->loginUser($this->outsider);
        $token = $this->csrfToken();
        $this->client->request('POST', '/dashboard/incomingInvoice/'.$this->id($this->victimReject).'/clone', [
            'incoming_invoice' => [
                '_token' => $token,
                'issuer' => $this->id($this->supplier),
                'number' => $this->victimReject->getNumber().'-copy',
                'reference' => 'REF-FOREIGN',
                'price' => '22.22',
                'recepient' => $this->id($this->victimOrg),
                'dateOfIssue' => '2026-11-20',
                'dueDate' => '2026-12-20',
                'debitKonto' => $this->id($this->debitKonto),
                'paymentMethod' => (string) PaymentMethods::transaction,
                'bankCost' => '0',
                'note' => 'copied without membership',
            ],
        ]);

        $this->assertLessThanOrEqual(1, $gate->loads, 'Clone loaded the bill more than once.');
        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $this->assertSame($before, $this->billCount($this->victimOrg));
        $this->assertSame(0, $this->countByNumber($this->victimReject->getNumber().'-copy'));
        $this->assertSame(States::received, $this->reload($this->victimReject)->getState());
        $this->assertSame(States::received, $this->reload($this->ownBill)->getState());
    }

    public function testOutsiderCannotCreateBillForOtherCompany(): void
    {
        $files = $this->scanFiles();
        $before = $this->billCount($this->victimOrg);
        $number = 'FOREIGN-CREATE-'.$this->id($this->victimOrg);
        $this->client->loginUser($this->outsider);
        $crawler = $this->client->request('GET', '/dashboard/incomingInvoice/new');
        $this->assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="incoming_invoice[_token]"]')->attr('value');
        $this->client->request('POST', '/dashboard/incomingInvoice/new', [
            'incoming_invoice' => [
                '_token' => $token,
                'issuer' => $this->id($this->supplier),
                'number' => $number,
                'reference' => 'REF-CREATE',
                'price' => '44.44',
                'recepient' => $this->id($this->victimOrg),
                'dateOfIssue' => '2026-11-20',
                'dueDate' => '2026-12-20',
                'debitKonto' => $this->id($this->debitKonto),
                'paymentMethod' => (string) PaymentMethods::transaction,
                'bankCost' => '0',
                'note' => 'booked onto another company',
            ],
        ]);

        $this->assertDenied();
        $this->assertSame($files, $this->scanFiles());
        $this->assertSame($before, $this->billCount($this->victimOrg));
        $this->assertSame(0, $this->countByNumber($number));
    }

    private function assertDenied(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains(
            $status,
            [403, 404],
            'The signed-in user is not a member of the recipient company. HTTP '.$status
        );
    }

    private function installSingleLoadGate(): SingleLoadIncomingInvoiceRepository
    {
        $registry = static::getContainer()->get('doctrine');
        $gate = new SingleLoadIncomingInvoiceRepository($registry, $this->ownBill);
        $property = new ReflectionProperty($this->em, 'repositoryFactory');
        $inner = $property->getValue($this->em);
        $property->setValue($this->em, new SingleLoadRepositoryFactory($inner, $gate));

        return $gate;
    }

    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/incomingInvoice/new');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('input[name="incoming_invoice[_token]"]')->attr('value');
    }

    private function resetSchema(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $all = array_values(array_filter(
            $this->em->getMetadataFactory()->getAllMetadata(),
            static fn ($metadata) => !$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass
        ));
        $tool = new SchemaTool($this->em);
        $tables = $connection->createSchemaManager()->listTableNames();
        if (!in_array('app_users', $tables, true)) {
            $tool->createSchema($all);
        } else {
            foreach ($tables as $table) {
                $connection->executeStatement('DELETE FROM '.$connection->quoteIdentifier($table));
            }
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function loadWorld(): void
    {
        $this->outsider = $this->user('outsider');
        $this->member = $this->user('member');
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($this->outsider);
        $this->em->persist($this->member);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $this->member->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $this->member);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $this->member);

        $this->outsiderOrg = $this->organization('A1', 'Outsider d.o.o.', $address, $this->outsider);
        $this->victimOrg = $this->organization('B1', 'Victim d.o.o.', $address, $this->member);
        $this->outsider->addOrganization($this->outsiderOrg, $this->outsider);
        $this->member->addOrganization($this->victimOrg, $this->member);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'S1';
        $partnerCommand->name = 'Supplier d.o.o.';
        $partnerCommand->shortName = 'Supplier';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = false;
        $partnerCommand->isSupplier = true;
        $this->supplier = $this->member->createPartner($partnerCommand);

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 4;
        $classCommand->name = 'Costs';
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $this->member);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 40;
        $categoryCommand->name = 'Operating costs';
        $categoryCommand->description = '';
        $category = $class->createCategory($categoryCommand, $this->member);

        $this->debitKonto = $this->konto($category, 0, 'Expense');
        $home = $this->konto($category, 1, 'Suppliers');
        $paid = $this->konto($category, 2, 'Bank');
        $this->configureLedgers($this->outsiderOrg, $this->debitKonto, $home, $paid, $this->outsider);
        $this->configureLedgers($this->victimOrg, $this->debitKonto, $home, $paid, $this->member);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($this->outsiderOrg);
        $this->em->persist($this->victimOrg);
        $this->em->persist($this->supplier);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($this->debitKonto);
        $this->em->persist($home);
        $this->em->persist($paid);
        $this->em->flush();

        $this->ownBill = $this->receivedBill($this->outsider, $this->outsiderOrg, 'OWN-BILL-11', '11.11');
        $this->victimPay = $this->receivedBill($this->member, $this->victimOrg, 'VICTIM-PAY-22', '22.22');
        $this->victimReject = $this->receivedBill($this->member, $this->victimOrg, 'VICTIM-REJECT-23', '23.23');
        $this->victimClone = $this->receivedBill($this->member, $this->victimOrg, 'VICTIM-CLONE-24', '24.24');
        $this->em->flush();
    }

    private function user(string $username): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new \Doctrine\Common\Collections\ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', $username);
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Tester');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', 'placeholder-not-a-secret');

        return $user;
    }

    private function organization(string $code, string $name, object $address, User $owner): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = '12345678';
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56031001001001234';
        $command->bic = 'LJBASI2X';

        return $owner->createOrganization($command);
    }

    private function konto(KontoCategory $category, int $number, string $name): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = '';
        $command->isActive = true;
        $command->type = KontoTypes::active;

        return $category->createKonto($command, $this->member);
    }

    private function configureLedgers(Organization $organization, Konto $debit, Konto $home, Konto $paid, User $user): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $organization->getOrganizationSettings()->mapTo($command);
        $command->ReceivedIncomingInvoiceDebit = $debit;
        $command->ReceivedHomeIncomingInvoiceCredit = $home;
        $command->ReceivedForeignIncomingInvoiceCredit = $home;
        $command->PaidCashIncomingInvoiceCredit = $paid;
        $command->PaidTransactionIncomingInvoiceCredit = $paid;
        $organization->updateOrganizationSettings($command, $user);
    }

    private function receivedBill(User $owner, Organization $recipient, string $number, string $price): IncomingInvoice
    {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = new \DateTime('today');
        $command->dueDate = new \DateTime('+30 days');
        $command->issuer = $this->supplier;
        $command->recepient = $recipient;
        $command->number = $number;
        $command->reference = 'REF-'.$number;
        $command->price = $price;
        $command->note = 'note '.$number;
        $invoice = $owner->createIncomingInvoice($command);
        $transactions = $invoice->setReceived(new \DateTime('today'), $owner, $this->debitKonto);
        $this->em->persist($invoice);
        foreach ($transactions as $transaction) {
            $this->em->persist($transaction);
        }

        return $invoice;
    }

    private function reload(IncomingInvoice $invoice): IncomingInvoice
    {
        $this->em->clear();
        $fresh = $this->em->find(IncomingInvoice::class, $this->id($invoice));
        $this->assertInstanceOf(IncomingInvoice::class, $fresh);

        return $fresh;
    }

    private function ledgerCount(IncomingInvoice $invoice): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(t.id) FROM '.Transaction::class.' t WHERE t.incomingInvoice = :invoice'
        )->setParameter('invoice', $this->id($invoice))->getSingleScalarResult();
    }

    private function billCount(Organization $organization): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(i.id) FROM '.IncomingInvoice::class.' i WHERE i.recepient = :organization'
        )->setParameter('organization', $this->id($organization))->getSingleScalarResult();
    }

    private function countByNumber(string $number): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(i.id) FROM '.IncomingInvoice::class.' i WHERE i.number = :number'
        )->setParameter('number', $number)->getSingleScalarResult();
    }

    private function rejectedReason(IncomingInvoice $invoice): ?string
    {
        $property = new ReflectionProperty(IncomingInvoice::class, 'rejectedReason');

        return $property->getValue($invoice);
    }

    private function scanFiles(): array
    {
        $directory = (string) static::getContainer()->getParameter('incoming_scans_directory');
        if (!is_dir($directory)) {
            return [];
        }
        $files = scandir($directory);
        sort($files);

        return array_values(array_diff($files, ['.', '..']));
    }

    private function id(object $entity): string
    {
        $id = $entity->getId();

        return $id instanceof \Stringable ? (string) $id : (string) $id;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}

/**
 * Second and later loads return the caller's own bill instead of the row just read.
 * A fix that looks the id up again therefore updates a different bill and fails.
 */
final class SingleLoadIncomingInvoiceRepository extends IncomingInvoiceRepository
{
    public int $loads = 0;

    public function __construct(ManagerRegistry $registry, private readonly IncomingInvoice $decoy)
    {
        parent::__construct($registry);
    }

    public function find(mixed $id, \Doctrine\DBAL\LockMode|int|null $lockMode = null, int|null $lockVersion = null): object|null
    {
        return $this->gate(parent::find($id, $lockMode, $lockVersion));
    }

    public function findOneBy(array $criteria, array|null $orderBy = null): object|null
    {
        return $this->gate(parent::findOneBy($criteria, $orderBy));
    }

    public function createQueryBuilder(string $alias, string|null $indexBy = null): QueryBuilder
    {
        $this->loads++;

        return parent::createQueryBuilder($alias, $indexBy);
    }

    private function gate(object|null $loaded): object|null
    {
        $this->loads++;
        if ($this->loads > 1) {
            return $this->decoy;
        }

        return $loaded;
    }
}

final class SingleLoadRepositoryFactory implements \Doctrine\ORM\Repository\RepositoryFactory
{
    public function __construct(private readonly \Doctrine\ORM\Repository\RepositoryFactory $inner, private readonly IncomingInvoiceRepository $gate)
    {
    }

    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        if ($entityName === IncomingInvoice::class) {
            return $this->gate;
        }

        return $this->inner->getRepository($entityManager, $entityName);
    }
}
