<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
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
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in user may read and book only supplier bills of companies they belong to.
 * These tests fail while list, show, pay, reject, and clone honor a posted bill id
 * from another company.
 */
class IncomingInvoiceAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    /** @var array<string, mixed> */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->world = $this->loadWorld();
    }

    public function testForeignBillIsHiddenFromListAndDashboard(): void
    {
        $this->client->loginUser($this->world['outsider']);

        $this->client->request('GET', '/dashboard/incomingInvoice');
        $this->assertResponseIsSuccessful();
        $list = (string) $this->client->getResponse()->getContent();

        $this->client->request('GET', '/dashboard/incomingInvoice?partial=1');
        $this->assertResponseIsSuccessful();
        $partial = (string) $this->client->getResponse()->getContent();

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $dashboard = (string) $this->client->getResponse()->getContent();

        $this->assertSameCompanyBillsOnly($list);
        $this->assertSameCompanyBillsOnly($partial);
        $this->assertSameCompanyBillsOnly($dashboard);
    }

    public function testForeignBillShowDoesNotRevealScanOrNote(): void
    {
        $this->client->loginUser($this->world['outsider']);

        $this->client->request('GET', '/dashboard/incomingInvoice/'.$this->world['foreignId'].'/show');
        $response = $this->client->getResponse();
        $this->assertRefused($response, 'Show of another company supplier bill');
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString($this->world['foreignNote'], $body);
        $this->assertStringNotContainsString($this->world['foreignScan'], $body);
        $this->assertStringNotContainsString($this->world['foreignNumber'], $body);
        $this->assertStringNotContainsString('uploads/incoming-invoices/'.$this->world['foreignScan'], $body);
    }

    public function testForeignBillPayDoesNotWriteJournal(): void
    {
        $foreignId = $this->world['foreignId'];
        $ownId = $this->world['ownId'];
        $journalsBefore = $this->journalCount($foreignId);
        $ownJournalsBefore = $this->journalCount($ownId);

        $this->client->loginUser($this->world['outsider']);
        $this->client->disableReboot();
        $this->em->clear();
        $this->em->getEventManager()->addEventListener(Events::postLoad, new class($foreignId, $this->world['ownOrgId']) {
            private bool $rewritten = false;

            public function __construct(private string $invoiceId, private string $organizationId)
            {
            }

            public function postLoad(PostLoadEventArgs $args): void
            {
                $entity = $args->getObject();
                if (!$entity instanceof IncomingInvoice || $this->rewritten) {
                    return;
                }
                if ($entity->getId()->toString() !== $this->invoiceId) {
                    return;
                }
                $this->rewritten = true;
                // The row changes after the first load. A later load of the same id must not
                // become the bill that is paid.
                $args->getObjectManager()->getConnection()->executeStatement(
                    'UPDATE incoming_invoice SET recepient_id = ? WHERE id = ?',
                    [$this->organizationId, $this->invoiceId]
                );
            }
        });

        $this->client->request('POST', '/dashboard/incomingInvoice/pay', [
            'id' => $foreignId,
            'date' => '2026-11-20',
            'mode' => '10',
            'bankCost' => '0',
        ]);

        $this->assertRefused($this->client->getResponse(), 'Pay of another company supplier bill');
        $this->assertSame(States::received, $this->invoiceState($foreignId));
        $this->assertSame($journalsBefore, $this->journalCount($foreignId));
        $this->assertNull($this->datePaid($foreignId));
        $this->assertSame(States::received, $this->invoiceState($ownId));
        $this->assertSame($ownJournalsBefore, $this->journalCount($ownId));
    }

    public function testForeignBillRejectDoesNotChangeState(): void
    {
        $foreignId = $this->world['foreignId'];
        $this->client->loginUser($this->world['outsider']);

        $this->client->request('POST', '/dashboard/incomingInvoice/reject', [
            'id' => $foreignId,
            'reason' => 'hide the liability',
        ]);

        $this->assertRefused($this->client->getResponse(), 'Reject of another company supplier bill');
        $this->assertSame(States::received, $this->invoiceState($foreignId));
        $this->assertNull($this->rejectedReason($foreignId));
    }

    public function testForeignBillCloneDoesNotCopyOrPersist(): void
    {
        $victimOrgId = $this->world['victimOrgId'];
        $before = $this->invoiceCount($victimOrgId);
        $journalsBefore = $this->journalCount($this->world['foreignId']);

        $this->client->loginUser($this->world['outsider']);
        $url = '/dashboard/incomingInvoice/'.$this->world['foreignId'].'/clone';
        $crawler = $this->client->request('GET', $url);
        $getStatus = $this->client->getResponse()->getStatusCode();
        $getBody = (string) $this->client->getResponse()->getContent();

        if ($crawler->filter('#incoming_invoice__token')->count() > 0) {
            $this->client->request('POST', $url, [
                'incoming_invoice' => [
                    '_token' => $crawler->filter('#incoming_invoice__token')->attr('value'),
                    'issuer' => $this->world['partnerId'],
                    'number' => $this->world['foreignNumber'],
                    'reference' => 'REF-'.$this->world['foreignNumber'],
                    'recepient' => $victimOrgId,
                    'dateOfIssue' => '2099-12-31',
                    'dueDate' => '1970-01-02',
                    'price' => '4242,42',
                    'debitKonto' => $this->world['debitKontoId'],
                    'note' => $this->world['foreignNote'],
                ],
            ]);
        }

        $this->assertContains($getStatus, [403, 404], 'Clone of another company supplier bill returned HTTP '.$getStatus.'.');
        $this->assertStringNotContainsString($this->world['foreignNote'], $getBody);
        $this->assertStringNotContainsString($this->world['foreignNumber'], $getBody);
        $this->assertRefused($this->client->getResponse(), 'Clone submit of another company supplier bill');
        $this->assertSame($before, $this->invoiceCount($victimOrgId));
        $this->assertSame($journalsBefore, $this->journalCount($this->world['foreignId']));
        $this->assertSame($this->world['victimJournals'], $this->journalCountForOrganization($victimOrgId));
    }

    public function testMemberStillReadsAndPaysOwnBill(): void
    {
        $this->client->loginUser($this->world['member']);

        $this->client->request('GET', '/dashboard/incomingInvoice');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->world['foreignNumber'], (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/dashboard/incomingInvoice/'.$this->world['foreignId'].'/show');
        $this->assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->world['foreignNote'], $body);
        $this->assertStringContainsString('uploads/incoming-invoices/'.$this->world['foreignScan'], $body);

        $journalsBefore = $this->journalCount($this->world['foreignId']);
        $this->client->request('POST', '/dashboard/incomingInvoice/pay', [
            'id' => $this->world['foreignId'],
            'date' => '2026-11-20',
            'mode' => '10',
            'bankCost' => '0',
        ]);
        $this->assertResponseRedirects('/dashboard/incomingInvoice');
        $this->assertSame(States::paid, $this->invoiceState($this->world['foreignId']));
        $this->assertSame($journalsBefore + 1, $this->journalCount($this->world['foreignId']));
        $this->assertNotNull($this->datePaid($this->world['foreignId']));
    }

    private function assertSameCompanyBillsOnly(string $body): void
    {
        $this->assertStringContainsString($this->world['ownNumber'], $body);
        $this->assertStringContainsString('17,53', $body);
        $this->assertStringNotContainsString($this->world['foreignNumber'], $body);
        $this->assertStringNotContainsString($this->world['foreignId'], $body);
        $this->assertStringNotContainsString($this->world['foreignNote'], $body);
        $this->assertStringNotContainsString('4 242,42', $body);
    }

    private function assertRefused(Response $response, string $what): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            $what.' returned HTTP '.$response->getStatusCode().'.'
        );
    }

    private function invoiceState(string $id): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT state FROM incoming_invoice WHERE id = ?',
            [$id]
        );
    }

    private function datePaid(string $id): ?string
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT date_paid FROM incoming_invoice WHERE id = ?',
            [$id]
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    private function rejectedReason(string $id): ?string
    {
        $value = $this->em->getConnection()->fetchOne(
            'SELECT rejected_reason FROM incoming_invoice WHERE id = ?',
            [$id]
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    private function journalCount(string $invoiceId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM transaction WHERE incoming_invoice_id = ?',
            [$invoiceId]
        );
    }

    private function journalCountForOrganization(string $organizationId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM transaction WHERE organization_id = ?',
            [$organizationId]
        );
    }

    private function invoiceCount(string $organizationId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM incoming_invoice WHERE recepient_id = ?',
            [$organizationId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadWorld(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $member = $this->user('member-'.$suffix);
        $outsider = $this->user('outsider-'.$suffix);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($member);
        $this->em->persist($outsider);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $member->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-'.$suffix;
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $member);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $member);

        $victimOrg = $this->organization($member, $address, 'V'.$suffix, 'Victim d.o.o.', 'Victim');
        $ownOrg = $this->organization($outsider, $address, 'O'.$suffix, 'Outsider d.o.o.', 'Outsider');
        $member->addOrganization($victimOrg, $member);
        $outsider->addOrganization($ownOrg, $outsider);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'P'.$suffix;
        $partnerCommand->name = 'Supplier '.$suffix;
        $partnerCommand->shortName = 'Supplier';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = false;
        $partnerCommand->isSupplier = true;
        $partner = $member->createPartner($partnerCommand);

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 4;
        $classCommand->name = 'Class '.$suffix;
        $classCommand->description = 'class';
        $class = new KontoClass($classCommand, $member);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 40;
        $categoryCommand->name = 'Category '.$suffix;
        $categoryCommand->description = 'category';
        $category = new KontoCategory($categoryCommand, $class, $member);

        $debit = $this->konto($category, $member, 1, 'Expense '.$suffix);
        $credit = $this->konto($category, $member, 2, 'Liability '.$suffix);
        $bank = $this->konto($category, $member, 3, 'Bank '.$suffix);
        $this->bookPreferences($victimOrg, $member, $debit, $credit, $bank);
        $this->bookPreferences($ownOrg, $outsider, $debit, $credit, $bank);

        $foreignNote = 'private-note-'.$suffix;
        $foreignScan = 'private-scan-'.$suffix.'.pdf';
        $foreignNumber = 'VICTIM-BILL-'.$suffix;
        $ownNumber = 'OWN-BILL-'.$suffix;
        $foreign = $this->receivedBill($member, $partner, $victimOrg, $debit, $foreignNumber, $foreignNote, $foreignScan, 4242.42);
        $own = $this->receivedBill($outsider, $partner, $ownOrg, $debit, $ownNumber, 'own-note-'.$suffix, null, 17.53);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($victimOrg);
        $this->em->persist($ownOrg);
        $this->em->persist($partner);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);
        $this->em->persist($bank);
        $this->em->persist($foreign['invoice']);
        foreach ($foreign['transactions'] as $transaction) {
            $this->em->persist($transaction);
        }
        $this->em->persist($own['invoice']);
        foreach ($own['transactions'] as $transaction) {
            $this->em->persist($transaction);
        }
        $this->em->flush();

        $victimOrgId = $victimOrg->getId()->toString();

        return [
            'member' => $member,
            'outsider' => $outsider,
            'foreignId' => $foreign['invoice']->getId()->toString(),
            'ownId' => $own['invoice']->getId()->toString(),
            'foreignNumber' => $foreignNumber,
            'ownNumber' => $ownNumber,
            'foreignNote' => $foreignNote,
            'foreignScan' => $foreignScan,
            'victimOrgId' => $victimOrgId,
            'ownOrgId' => $ownOrg->getId()->toString(),
            'partnerId' => $partner->getId()->toString(),
            'debitKontoId' => $debit->getId()->toString(),
            'victimJournals' => $this->journalCountForOrganization($victimOrgId),
        ];
    }

    private function user(string $username): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Test');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Clerk');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', 'not-a-login-secret');

        return $user;
    }

    private function organization(User $owner, object $address, string $code, string $name, string $shortName): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $shortName;
        $command->taxNumber = '12345678';
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56031001001001234';
        $command->bic = 'LJBASI2X';

        return $owner->createOrganization($command);
    }

    private function konto(KontoCategory $category, User $owner, int $number, string $name): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = KontoTypes::active;

        return $category->createKonto($command, $owner);
    }

    private function bookPreferences(Organization $organization, User $owner, Konto $debit, Konto $credit, Konto $bank): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $command->autoCreatePerDiem = false;
        $command->autoCreateLunch = false;
        $command->ReceivedIncomingInvoiceDebit = $debit;
        $command->ReceivedHomeIncomingInvoiceCredit = $credit;
        $command->ReceivedForeignIncomingInvoiceCredit = $credit;
        $command->PaidCashIncomingInvoiceCredit = $bank;
        $command->PaidTransactionIncomingInvoiceCredit = $bank;
        $organization->updateOrganizationSettings($command, $owner);
    }

    /**
     * @return array{invoice: IncomingInvoice, transactions: array<int, object>}
     */
    private function receivedBill(
        User $owner,
        Partner $supplier,
        Organization $recipient,
        Konto $debit,
        string $number,
        string $note,
        ?string $scan,
        float $price,
    ): array {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = new \DateTime('2099-12-31');
        $command->dueDate = new \DateTime('1970-01-02');
        $command->issuer = $supplier;
        $command->recepient = $recipient;
        $command->number = $number;
        $command->reference = 'REF-'.$number;
        $command->price = $price;
        $command->note = $note;
        $command->scanFilename = $scan;
        $invoice = $owner->createIncomingInvoice($command);
        $transactions = $invoice->setReceived(new \DateTime('2099-12-31'), $owner, $debit);

        return ['invoice' => $invoice, 'transactions' => $transactions];
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
