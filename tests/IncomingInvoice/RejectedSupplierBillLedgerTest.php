<?php

namespace App\Tests\IncomingInvoice;

use App\Controller\Report\ReportController;
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
use App\Entity\Report\Bilanca;
use App\Entity\Report\DDDDDD;
use App\Entity\Report\Turnout;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Service\Ledger\BankFeeFactory;
use App\Tests\Support\SmokeFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Spec for rejecting a received supplier bill.
 *
 * Recording the bill posts its receipt (and any bank-fee line booked on that
 * document). Rejecting it must leave the bill rejected and the journal balanced
 * in the same flush: delete those lines or post matching reversals before the
 * rejected status is saved. Pay and refund stay refused afterwards.
 *
 * These tests fail while reject only changes the bill status.
 *
 * Customer-invoice cancel is not this path: an issued invoice cannot move to
 * cancelled. Refund of an already paid supplier bill is a separate transition
 * and is not covered here. A bill received and paid in one step is already
 * paid, so reject is refused before the status changes.
 */
final class RejectedSupplierBillLedgerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    /** @var array{admin: User, user: User, organization: Organization, partner: Partner} */
    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeQuery('SELECT 1');
        $loaded = (new SmokeFixtures(
            $this->em,
            $container->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class),
        ))->load();
        $this->fixtures = $loaded;
    }

    public function testRejectingReceivedBillRemovesReceiptAndBankFeeFromJournalAndReports(): void
    {
        $expense = $this->konto(4, 40, 400, 'Supplier expenses', KontoTypes::active);
        $liability = $this->konto(2, 22, 220, 'Home supplier liability', KontoTypes::passive);
        $feeDebit = $this->konto(4, 41, 415, 'Bank fees', KontoTypes::active);
        $feeCredit = $this->konto(1, 11, 110, 'Bank', KontoTypes::active);
        $org = $this->organization($expense, $liability, $liability, $feeDebit, $feeCredit);
        $invoice = $this->receive($org, $this->fixtures['partner'], 'BILL-100', '100.00', $expense, [], 1.50);

        $this->assertReceiptPosted($invoice, $liability, 100.00);
        $before = $this->reports($org);
        $this->assertEqualsWithDelta(101.50, $before['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(101.50, $before['overview']['expenses'], 0.001);
        $this->assertEqualsWithDelta(-1.50, $before['overview']['bank'], 0.001);
        $this->assertEqualsWithDelta(100.00, $before['bilanca']->p091, 0.001);
        $this->assertEqualsWithDelta(100.00, $before['turnout']->p130, 0.001);
        $this->assertEqualsWithDelta(101.50, (float) $before['ddd']->e, 0.001);
        $this->assertKontoBalance($before['kontos'], 400, 100.00);
        $this->assertKontoBalance($before['kontos'], 220, 100.00);
        $this->assertKontoBalance($before['kontos'], 415, 1.50);
        $this->assertKontoBalance($before['kontos'], 110, -1.50);

        $this->rejectViaHttp($invoice, 'duplicate delivery');

        $this->em->clear();
        $reloaded = $this->em->find(IncomingInvoice::class, $invoice->getId());
        $this->assertInstanceOf(IncomingInvoice::class, $reloaded);
        $this->assertSame($invoice->getId()->toString(), $reloaded->getId()->toString());
        $this->assertSame(States::rejected, $reloaded->getState());
        $this->assertSame('duplicate delivery', $this->rejectedReason($reloaded));
        $this->assertJournalNetsToZero($reloaded);

        $after = $this->reports($org);
        $this->assertEqualsWithDelta(0.0, $after['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['overview']['expenses'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['overview']['bank'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['overview']['outcome'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['bilanca']->p091, 0.001);
        $this->assertEqualsWithDelta(0.0, $after['turnout']->p130, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) ($after['ddd']->e ?? 0), 0.001);
        $this->assertKontoBalance($after['kontos'], 400, 0.0);
        $this->assertKontoBalance($after['kontos'], 220, 0.0);
        $this->assertKontoBalance($after['kontos'], 415, 0.0);
        $this->assertKontoBalance($after['kontos'], 110, 0.0);

        $this->assertPayAndRefundStayRefused($reloaded);
    }

    public function testRejectingSplitReceiptClearsEveryAllocationLine(): void
    {
        $goods = $this->konto(4, 40, 401, 'Goods', KontoTypes::active);
        $services = $this->konto(4, 40, 402, 'Services', KontoTypes::active);
        $liability = $this->konto(2, 22, 220, 'Home supplier liability', KontoTypes::passive);
        $org = $this->organization($goods, $liability, $liability, null, null);
        $invoice = $this->receive($org, $this->fixtures['partner'], 'BILL-SPLIT', '100.00', $goods, [
            ['konto' => $goods, 'amount' => 60.10, 'note' => 'goods'],
            ['konto' => $services, 'amount' => 39.90, 'note' => 'services'],
        ], 0.0);

        $lines = $this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]);
        $this->assertCount(2, $lines);
        $before = $this->reports($org);
        $this->assertEqualsWithDelta(100.00, $before['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(100.00, $before['bilanca']->p091, 0.001);
        $this->assertKontoBalance($before['kontos'], 401, 60.10);
        $this->assertKontoBalance($before['kontos'], 402, 39.90);
        $this->assertKontoBalance($before['kontos'], 220, 100.00);

        $this->rejectViaHttp($invoice, 'split bill rejected');

        $this->em->clear();
        $reloaded = $this->em->find(IncomingInvoice::class, $invoice->getId());
        $this->assertSame(States::rejected, $reloaded->getState());
        $this->assertJournalNetsToZero($reloaded);
        $after = $this->reports($org);
        $this->assertEqualsWithDelta(0.0, $after['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['overview']['expenses'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['bilanca']->p091, 0.001);
        $this->assertEqualsWithDelta(0.0, $after['turnout']->p130, 0.001);
        $this->assertKontoBalance($after['kontos'], 401, 0.0);
        $this->assertKontoBalance($after['kontos'], 402, 0.0);
        $this->assertKontoBalance($after['kontos'], 220, 0.0);
        $this->assertPayAndRefundStayRefused($reloaded);
    }

    public function testRejectingForeignSupplierBillClearsForeignLiabilityKonto(): void
    {
        $expense = $this->konto(4, 40, 400, 'Supplier expenses', KontoTypes::active);
        $home = $this->konto(2, 22, 220, 'Home supplier liability', KontoTypes::passive);
        $foreign = $this->konto(2, 22, 221, 'Foreign supplier liability', KontoTypes::passive);
        $org = $this->organization($expense, $home, $foreign, null, null);
        $supplier = $this->foreignSupplier();
        $invoice = $this->receive($org, $supplier, 'BILL-FOREIGN', '80.00', $expense, [], 0.0);

        $lines = $this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]);
        $this->assertCount(1, $lines);
        $this->assertSame($foreign->getId()->toString(), $lines[0]->getCreditKonto()->getId()->toString());
        $before = $this->reports($org);
        $this->assertEqualsWithDelta(80.00, $before['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(80.00, $before['bilanca']->p091, 0.001);
        $this->assertKontoBalance($before['kontos'], 221, 80.00);
        $this->assertKontoBalance($before['kontos'], 220, 0.0);

        $this->rejectViaHttp($invoice, 'foreign bill rejected');

        $this->em->clear();
        $reloaded = $this->em->find(IncomingInvoice::class, $invoice->getId());
        $this->assertSame(States::rejected, $reloaded->getState());
        $this->assertJournalNetsToZero($reloaded);
        $after = $this->reports($org);
        $this->assertEqualsWithDelta(0.0, $after['overview']['incomingInvoices'], 0.001);
        $this->assertEqualsWithDelta(0.0, $after['bilanca']->p091, 0.001);
        $this->assertKontoBalance($after['kontos'], 400, 0.0);
        $this->assertKontoBalance($after['kontos'], 221, 0.0);
        $this->assertKontoBalance($after['kontos'], 220, 0.0);
        $this->assertPayAndRefundStayRefused($reloaded);
    }

    private function rejectViaHttp(IncomingInvoice $invoice, string $reason): void
    {
        $this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]);
        $sawFlush = false;
        $openLedger = false;
        $listener = function (OnFlushEventArgs $args) use (&$sawFlush, &$openLedger, $invoice): void {
            $sawFlush = true;
            $uow = $args->getObjectManager()->getUnitOfWork();
            foreach (array_merge($uow->getScheduledEntityUpdates(), $uow->getScheduledEntityInsertions()) as $entity) {
                if (!$entity instanceof IncomingInvoice) {
                    continue;
                }
                if ($entity->getId()->toString() !== $invoice->getId()->toString()) {
                    continue;
                }
                if ($entity->getState() !== States::rejected) {
                    continue;
                }
                if (!$this->netsToZero($invoice, $uow)) {
                    $openLedger = true;
                }
            }
        };
        $this->em->getEventManager()->addEventListener(Events::onFlush, $listener);
        try {
            $this->client->loginUser($this->fixtures['user']);
            $this->client->request('POST', '/dashboard/incomingInvoice/reject', [
                'id' => $invoice->getId()->toString(),
                'reason' => $reason,
            ]);
        } finally {
            $this->em->getEventManager()->removeEventListener(Events::onFlush, $listener);
        }

        $this->assertResponseRedirects('/dashboard/incomingInvoice');
        $this->assertTrue($sawFlush, 'Reject must flush the rejected bill.');
        $this->assertFalse($openLedger, 'Reject saved the rejected status while the receipt journal was still open.');
    }

    private function assertPayAndRefundStayRefused(IncomingInvoice $invoice): void
    {
        $before = count($this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]));
        $this->client->loginUser($this->fixtures['user']);
        $this->client->request('POST', '/dashboard/incomingInvoice/pay', [
            'id' => $invoice->getId()->toString(),
            'date' => '2026-06-20',
            'mode' => (string) PaymentMethods::transaction,
            'bankCost' => '0',
        ]);
        $this->assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());

        $this->em->clear();
        $reloaded = $this->em->find(IncomingInvoice::class, $invoice->getId());
        $this->assertSame(States::rejected, $reloaded->getState());
        $this->assertCount($before, $this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $reloaded]));
        $this->assertJournalNetsToZero($reloaded);

        try {
            $reloaded->refund('supplier refund');
            $this->fail('Refund of a rejected supplier bill must be refused.');
        } catch (\Throwable) {
            $this->assertSame(States::rejected, $reloaded->getState());
        }
    }

    private function receive(
        Organization $org,
        Partner $supplier,
        string $number,
        string $price,
        Konto $debit,
        array $allocations,
        float $bankCost,
    ): IncomingInvoice {
        $command = new CreateIncomingInvoiceCommand();
        $command->dateOfIssue = new \DateTime('2026-06-15');
        $command->dueDate = new \DateTime('2026-07-15');
        $command->issuer = $supplier;
        $command->recepient = $org;
        $command->number = $number;
        $command->price = $price;
        $command->reference = 'REF-'.$number;
        $command->note = 'received for ledger test';

        $user = $this->fixtures['admin'];
        $invoice = $user->createIncomingInvoice($command);
        $transactions = $invoice->setReceived(new \DateTime('2026-06-15'), $user, $debit, $allocations);
        $this->em->persist($invoice);
        BankFeeFactory::persist($this->em, $transactions);
        if ($bankCost > 0) {
            $fee = static::getContainer()->get(BankFeeFactory::class)->createIfNeeded(
                $org,
                new \DateTime('2026-06-15'),
                $bankCost,
                $user,
                $invoice,
                $transactions[0],
            );
            $this->assertInstanceOf(Transaction::class, $fee);
            $this->em->persist($fee);
        }
        $this->em->flush();

        return $invoice;
    }

    private function assertReceiptPosted(IncomingInvoice $invoice, Konto $credit, float $amount): void
    {
        $lines = $this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]);
        $this->assertNotEmpty($lines);
        $receipt = null;
        foreach ($lines as $line) {
            if ($line->getCreditKonto()->getId()->toString() === $credit->getId()->toString()
                && abs((float) $line->getSum() - $amount) < 0.001) {
                $receipt = $line;
            }
        }
        $this->assertInstanceOf(Transaction::class, $receipt);
        $this->assertSame(States::received, $invoice->getState());
    }

    /**
     * @param list<array<string, mixed>> $kontos
     */
    private function assertKontoBalance(array $kontos, int $number, float $expected): void
    {
        $found = null;
        foreach ($kontos as $konto) {
            if ((int) $konto['number'] === $number) {
                $found = (float) $konto['sum'];
            }
        }
        if (abs($expected) < 0.001) {
            $this->assertTrue($found === null || abs($found) < 0.001, 'Konto '.$number.' balance is '.$found);
            return;
        }
        $this->assertNotNull($found, 'Konto '.$number.' is missing from the konto report.');
        $this->assertEqualsWithDelta($expected, $found, 0.001);
    }

    private function assertJournalNetsToZero(IncomingInvoice $invoice): void
    {
        $this->assertTrue($this->netsToZero($invoice, null), 'Rejected bill journal lines must net to zero on every konto.');
    }

    private function netsToZero(IncomingInvoice $invoice, ?UnitOfWork $uow): bool
    {
        $nets = [];
        foreach ($this->linesFor($invoice, $uow) as $line) {
            $sum = (float) $line->getSum();
            $debit = $line->getDebitKonto()->getId()->toString();
            $credit = $line->getCreditKonto()->getId()->toString();
            $nets[$debit] = ($nets[$debit] ?? 0.0) + $sum;
            $nets[$credit] = ($nets[$credit] ?? 0.0) - $sum;
        }
        foreach ($nets as $net) {
            if (abs($net) >= 0.001) {
                return false;
            }
        }

        // Scheduled deletions are still stored until this flush completes.
        if ($uow instanceof UnitOfWork) {
            return true;
        }

        return $nets !== [] || $this->storedLineCount($invoice) === 0;
    }

    /**
     * @return list<Transaction>
     */
    private function linesFor(IncomingInvoice $invoice, ?UnitOfWork $uow): array
    {
        $deleted = [];
        $managed = [];
        if ($uow instanceof UnitOfWork) {
            foreach ($uow->getScheduledEntityDeletions() as $entity) {
                if ($entity instanceof Transaction) {
                    $deleted[spl_object_id($entity)] = true;
                }
            }
            foreach ($uow->getIdentityMap()[Transaction::class] ?? [] as $entity) {
                $managed[spl_object_id($entity)] = $entity;
            }
            foreach ($uow->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof Transaction) {
                    $managed[spl_object_id($entity)] = $entity;
                }
            }
        } else {
            foreach ($this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]) as $entity) {
                $managed[spl_object_id($entity)] = $entity;
            }
        }

        $lines = [];
        foreach ($managed as $id => $entity) {
            if (isset($deleted[$id]) || !$this->belongsTo($entity, $invoice)) {
                continue;
            }
            $lines[] = $entity;
        }

        return $lines;
    }

    private function belongsTo(Transaction $transaction, IncomingInvoice $invoice): bool
    {
        $document = $transaction->getIncomingInvoice();

        return $document !== null && $document->getId()->toString() === $invoice->getId()->toString();
    }

    private function storedLineCount(IncomingInvoice $invoice): int
    {
        return count($this->em->getRepository(Transaction::class)->findBy(['incomingInvoice' => $invoice]));
    }

    /**
     * @return array{overview: array<string, mixed>, bilanca: Bilanca, turnout: Turnout, ddd: DDDDDD, kontos: list<array<string, mixed>>}
     */
    private function reports(Organization $org): array
    {
        $orgId = $org->getId()->toString();
        $from = (string) strtotime('2026-01-01 00:00:00');
        $to = (string) strtotime('2026-12-31 23:59:59');
        $controller = (new ReflectionClass(ReportController::class))->newInstanceWithoutConstructor();
        $call = function (string $method, mixed ...$args) use ($controller): mixed {
            $reflection = new ReflectionClass(ReportController::class);
            $reflected = $reflection->getMethod($method);

            return $reflected->invoke($controller, ...$args);
        };

        return [
            'overview' => $call('getOverviewReport', $orgId, $from, $to, $this->em),
            'bilanca' => $call('getBilancaReport', $orgId, $to, $this->em),
            'turnout' => $call('getTurnoutReport', $orgId, $from, $to, $this->em),
            'ddd' => $call('getDDDReport', $orgId, $from, $to, $this->em),
            'kontos' => $call('getKontoReport', $orgId, $from, $to, $this->em)['kontos'],
        ];
    }

    private function organization(
        Konto $expense,
        Konto $homeLiability,
        Konto $foreignLiability,
        ?Konto $feeDebit,
        ?Konto $feeCredit,
    ): Organization {
        $admin = $this->fixtures['admin'];
        $command = new CreateOrganizationCommand();
        $command->code = substr(uniqid('org', true), 0, 20);
        $command->name = 'Ledger Org';
        $command->shortName = 'Ledger';
        $command->taxNumber = '12345678';
        $command->taxable = true;
        $command->address = $this->fixtures['organization']->getAddress();
        $command->accountNumber = 'SI56031001001001234';
        $command->bic = 'LJBASI2X';
        $org = $admin->createOrganization($command);
        $settings = $org->getOrganizationSettings();
        $this->setSetting($settings, 'ReceivedIncomingInvoiceDebit', $expense);
        $this->setSetting($settings, 'ReceivedHomeIncomingInvoiceCredit', $homeLiability);
        $this->setSetting($settings, 'ReceivedForeignIncomingInvoiceCredit', $foreignLiability);
        $this->setSetting($settings, 'PaidCashIncomingInvoiceCredit', $homeLiability);
        $this->setSetting($settings, 'PaidTransactionIncomingInvoiceCredit', $homeLiability);
        if ($feeDebit instanceof Konto) {
            $this->setSetting($settings, 'BankFeeDebit', $feeDebit);
        }
        if ($feeCredit instanceof Konto) {
            $this->setSetting($settings, 'BankFeeCredit', $feeCredit);
        }
        $this->em->persist($org);
        $this->em->flush();

        return $org;
    }

    private function foreignSupplier(): Partner
    {
        $admin = $this->fixtures['admin'];
        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Austria';
        $countryCommand->nameInt = 'Austria';
        $countryCommand->A2 = 'AT';
        $countryCommand->A3 = 'AUT';
        $countryCommand->N3 = 40;
        $country = $admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1010';
        $postCommand->codeInternational = 'AT-1010';
        $postCommand->name = 'Wien';
        $post = $country->createPost($postCommand, $admin);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Ring 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $admin);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = substr(uniqid('sup', true), 0, 20);
        $partnerCommand->name = 'Foreign Supplier GmbH';
        $partnerCommand->shortName = 'Foreign';
        $partnerCommand->taxNumber = 'ATU12345678';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = false;
        $partnerCommand->isSupplier = true;
        $partner = $admin->createPartner($partnerCommand);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($partner);
        $this->em->flush();

        return $partner;
    }

    private function konto(int $classNumber, int $categoryNumber, int $number, string $name, int $type): Konto
    {
        $admin = $this->fixtures['admin'];
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = $classNumber;
        $classCommand->name = 'Class '.$classNumber;
        $classCommand->description = null;
        $class = new KontoClass($classCommand, $admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = $categoryNumber;
        $categoryCommand->name = 'Category '.$categoryNumber;
        $categoryCommand->description = null;
        $category = new KontoCategory($categoryCommand, $class, $admin);

        $kontoCommand = new class extends CreateKontoCommand {};
        $kontoCommand->number = $number;
        $kontoCommand->name = $name;
        $kontoCommand->description = null;
        $kontoCommand->isActive = true;
        $kontoCommand->type = $type;
        $konto = new Konto($kontoCommand, $category, $admin);

        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($konto);
        $this->em->flush();

        return $konto;
    }

    private function setSetting(OrganizationSettings $settings, string $name, mixed $value): void
    {
        $property = new ReflectionProperty(OrganizationSettings::class, $name);
        $property->setValue($settings, $value);
    }

    private function rejectedReason(IncomingInvoice $invoice): ?string
    {
        $property = new ReflectionProperty(IncomingInvoice::class, 'rejectedReason');

        return $property->getValue($invoice);
    }
}
