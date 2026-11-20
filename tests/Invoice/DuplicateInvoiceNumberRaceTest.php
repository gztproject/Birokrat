<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
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
use App\Entity\Organization\Partner;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

/**
 * Two signed-in issue requests for the same company and year must not store
 * one invoice number on both documents. The UI calls state 10 a draft; that
 * is the state the issue action accepts.
 *
 * InvoiceQueryController::getNewNumber reads this same counter and returns it
 * to the browser, but it does not save a document or a journal. The only path
 * that stores the generated number and posts the revenue journal is
 * POST /dashboard/invoice/issue. OrganizationCodeFactory is a separate counter
 * and is not part of this race.
 */
#[Group('database')]
class DuplicateInvoiceNumberRaceTest extends KernelTestCase
{
    private static bool $schemaReady = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $kernel = new RaceDelayKernel('test', true);
        $kernel->boot();
        $kernel->shutdown();
    }

    public function testOverlappingIssueKeepsTheExistingSequenceUnique(): void
    {
        $year = (int) date('Y');
        $prefix = 'TST';
        $fixtures = $this->seedCompany($prefix, $year, true);

        $dir = $this->issueTogether(
            $fixtures['user'],
            $fixtures['drafts'][0],
            $fixtures['drafts'][1],
        );

        $this->assertIssuedPair(
            $fixtures['drafts'][0],
            $fixtures['drafts'][1],
            [sprintf('%s-%d-0006', $prefix, $year), sprintf('%s-%d-0007', $prefix, $year)],
            $dir,
        );
    }

    public function testOverlappingIssueAssignsDistinctNumbersWhenNoneExistYet(): void
    {
        $year = (int) date('Y');
        $prefix = 'NEW';
        $fixtures = $this->seedCompany($prefix, $year, false);

        $dir = $this->issueTogether(
            $fixtures['user'],
            $fixtures['drafts'][0],
            $fixtures['drafts'][1],
        );

        $this->assertIssuedPair(
            $fixtures['drafts'][0],
            $fixtures['drafts'][1],
            [sprintf('%s-%d-0001', $prefix, $year), sprintf('%s-%d-0002', $prefix, $year)],
            $dir,
        );
    }

    /**
     * @return array{user: string, drafts: array{0: string, 1: string}}
     */
    private function seedCompany(string $prefix, int $year, bool $withIssuedInvoice): array
    {
        $em = $this->entityManager();
        $this->ensureSchema($em);

        $user = $this->newUser('issuer-'.bin2hex(random_bytes(4)));
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($user);
        $em->flush();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $user->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $user);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $user);

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 7;
        $classCommand->name = 'Class';
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $user);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 76;
        $categoryCommand->name = 'Category';
        $categoryCommand->description = '';
        $category = new KontoCategory($categoryCommand, $class, $user);

        $debit = $this->newKonto(120, 'Receivables', KontoTypes::active, $category, $user);
        $credit = $this->newKonto(760, 'Revenue', KontoTypes::passive, $category, $user);

        $orgCommand = new CreateOrganizationCommand();
        $orgCommand->code = substr($prefix, 0, 8);
        $orgCommand->name = 'Issuer '.$prefix;
        $orgCommand->shortName = $prefix;
        $orgCommand->taxNumber = '12345678';
        $orgCommand->taxable = true;
        $orgCommand->address = $address;
        $orgCommand->accountNumber = 'SI56031001001001234';
        $orgCommand->bic = 'LJBASI2X';
        $organization = $user->createOrganization($orgCommand);
        $user->addOrganization($organization, $user);
        $this->setProperty($organization->getOrganizationSettings(), 'invoicePrefix', $prefix);
        $this->setProperty($organization->getOrganizationSettings(), 'IssueInvoiceDebit', $debit);
        $this->setProperty($organization->getOrganizationSettings(), 'IssueInvoiceCredit', $credit);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'P'.$prefix;
        $partnerCommand->name = 'Partner '.$prefix;
        $partnerCommand->shortName = 'Partner';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = false;
        $partner = $user->createPartner($partnerCommand);

        $em->persist($country);
        $em->persist($post);
        $em->persist($address);
        $em->persist($class);
        $em->persist($category);
        $em->persist($debit);
        $em->persist($credit);
        $em->persist($organization);
        $em->persist($organization->getOrganizationSettings());
        $em->persist($partner);

        $drafts = [];
        if ($withIssuedInvoice) {
            $issued = $this->newDraft($user, $organization, $partner, sprintf('%s-%d-0099', $prefix, $year));
            $item = $issued->createInvoiceItem($this->itemCommand());
            $transaction = $issued->setIssued(
                new \DateTime(sprintf('%d-06-01', $year)),
                sprintf('%s-%d-0005', $prefix, $year),
                $user,
            );
            $em->persist($issued);
            $em->persist($item);
            $em->persist($transaction);
        }

        foreach (['0091', '0092'] as $label) {
            $draft = $this->newDraft($user, $organization, $partner, sprintf('%d-%s', $year, $label));
            $item = $draft->createInvoiceItem($this->itemCommand());
            $em->persist($draft);
            $em->persist($item);
            $drafts[] = (string) $draft->getId();
        }

        $em->flush();
        $username = $user->getUsername();
        $em->clear();
        $em->getConnection()->close();

        return ['user' => $username, 'drafts' => [$drafts[0], $drafts[1]]];
    }

    private function issueTogether(string $username, string $invoiceA, string $invoiceB): string
    {
        $dir = sys_get_temp_dir().'/invoice-race-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        $issueDate = date('Y-m-d');

        $workerA = $this->worker('A', $dir, $invoiceA, $username, $issueDate);
        $workerB = $this->worker('B', $dir, $invoiceB, $username, $issueDate);
        $workerA->start();
        $workerB->start();
        $workerA->wait();
        $workerB->wait();

        return $dir;
    }

    private function worker(string $role, string $dir, string $invoiceId, string $username, string $issueDate): Process
    {
        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__).'/Invoice/issue_race_worker.php',
            $role,
            $dir,
            $invoiceId,
            $username,
            $issueDate,
        ]);
        $process->setTimeout(90);
        $process->setWorkingDirectory(dirname(__DIR__, 2));

        return $process;
    }

    /**
     * @param array{0: string, 1: string} $expectedNumbers
     */
    private function assertIssuedPair(string $idA, string $idB, array $expectedNumbers, string $dir): void
    {
        $hold = $this->timestamp($dir.'/a-holding-write');
        $released = $this->timestamp($dir.'/a-released');
        $started = $this->timestamp($dir.'/B-started');
        $context = sprintf(
            "A held the write at %.6f, B started the issue request at %.6f, A sent the write at %.6f.\nA: %s\nB: %s",
            $hold,
            $started,
            $released,
            $this->workerResult($dir, 'A'),
            $this->workerResult($dir, 'B'),
        );

        self::assertGreaterThan(0, $hold, $context);
        self::assertGreaterThan($hold, $started, "B must start while A is still between the number lookup and the save.\n".$context);
        self::assertLessThan($released, $started, "B must start before A sends its invoice write.\n".$context);

        $em = $this->entityManager();
        $em->clear();
        $rows = $em->getConnection()->fetchAllAssociative(
            'SELECT id, number, state FROM invoice WHERE id IN (?, ?)',
            [$idA, $idB],
        );
        $numbers = [];
        $states = [];
        foreach ($rows as $row) {
            $numbers[] = (string) $row['number'];
            $states[(string) $row['id']] = (int) $row['state'];
        }
        sort($numbers);
        $expected = $expectedNumbers;
        sort($expected);

        self::assertSame(20, $states[$idA] ?? 0, $context);
        self::assertSame(20, $states[$idB] ?? 0, $context);

        $journals = $em->getConnection()->fetchAllAssociative(
            'SELECT invoice_id, COUNT(*) AS journal_count FROM transaction WHERE invoice_id IN (?, ?) GROUP BY invoice_id',
            [$idA, $idB],
        );
        self::assertCount(2, $journals, "Each issued invoice must have its own revenue journal.\n".$context);
        foreach ($journals as $journal) {
            self::assertSame(1, (int) $journal['journal_count'], $context);
        }

        self::assertSame(
            $expected,
            $numbers,
            "Overlapping issue requests must store two consecutive numbers, one per journal.\n".$context,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        try {
            self::bootKernel();
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->getConnection()->executeQuery('SELECT 1');

            return $em;
        } catch (DbalException|\PDOException $e) {
            self::markTestSkipped('Database is not available for invoice race tests: '.$e->getMessage());
        }
    }

    private function ensureSchema(EntityManagerInterface $em): void
    {
        if (self::$schemaReady) {
            return;
        }
        $schema = $em->getConnection()->createSchemaManager();
        if (!$schema->tablesExist(['invoice'])) {
            $tool = new SchemaTool($em);
            $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        }
        self::$schemaReady = true;
    }

    private function newUser(string $username): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setProperty($user, 'id', Uuid::uuid6());
        $this->setProperty($user, 'createdOn', new \DateTime());
        $this->setProperty($user, 'createdBy', $user);
        $this->setProperty($user, 'isActive', true);
        $this->setProperty($user, 'organizations', new ArrayCollection());
        $this->setProperty($user, 'username', $username);
        $this->setProperty($user, 'firstName', 'Issue');
        $this->setProperty($user, 'lastName', 'User');
        $this->setProperty($user, 'email', $username.'@example.test');
        $this->setProperty($user, 'mobile', null);
        $this->setProperty($user, 'phone', null);
        $this->setProperty($user, 'signatureFilename', '');
        $this->setProperty($user, 'position', 'Clerk');
        $this->setProperty($user, 'totpEnabled', false);
        $this->setProperty($user, 'totpSecret', null);
        $this->setProperty($user, 'roles', ['ROLE_USER']);
        $this->setProperty($user, 'password', 'not-a-password');

        return $user;
    }

    private function newKonto(int $number, string $name, int $type, KontoCategory $category, User $user): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = '';
        $command->isActive = true;
        $command->type = $type;

        return new Konto($command, $category, $user);
    }

    private function newDraft(User $user, Organization $organization, Partner $partner, string $number): Invoice
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('today');
        $command->dateServiceRenderedFrom = new \DateTime('-7 days');
        $command->dateServiceRenderedTo = new \DateTime('-1 days');
        $command->dueDate = new \DateTime('+10 days');
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $organization;
        $command->recepient = $partner;

        return $user->createInvoice($command);
    }

    private function itemCommand(): CreateInvoiceItemCommand
    {
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Work';
        $item->quantity = 1;
        $item->unit = 'h';
        $item->price = 100;
        $item->discount = 0;

        return $item;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $parent = $class->getParentClass();
            if ($parent === false) {
                throw new \RuntimeException('Missing property '.$name);
            }
            $class = $parent;
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }

    private function timestamp(string $path): float
    {
        if (!is_file($path)) {
            return 0.0;
        }

        return (float) file_get_contents($path);
    }

    private function workerResult(string $dir, string $role): string
    {
        $path = $dir.'/result-'.$role.'.json';
        if (!is_file($path)) {
            return 'no result';
        }

        return (string) file_get_contents($path);
    }
}
