<?php

namespace App\Tests\Web;

use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\Organization;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ordinary signed-in users must not change a stored ledger entry.
 * The edit form is offered only to administrators, and the same rule has to
 * hold on the server for the entry identified by the URL.
 *
 * These tests fail until that rule is enforced. A refusal that still writes
 * the entry, or that writes a different entry than the one in the URL, fails
 * as well.
 *
 * Not covered, because those actions are offered to every signed-in user
 * rather than hidden behind the administrator role: creating a transaction
 * (transaction_new) and cloning one (transaction_clone).
 */
class TransactionEditAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $this->em = $container->get(EntityManagerInterface::class);
            $this->em->getConnection()->executeQuery('SELECT 1');
            $this->fixtures = (new SmokeFixtures(
                $this->em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
        } catch (DbalException|\PDOException $e) {
            $this->fail('Database is not available for ledger authorization tests: '.$e->getMessage());
        }
    }

    public function testAnonymousRequestDoesNotChangeTheLedgerEntry(): void
    {
        $books = $this->postManualEntry('Anonymous ledger entry', '10.00');

        $this->client->request('POST', $this->editUrl($books['target']), $this->editPayload($books, '11.00', 'Forged by anonymous', $books['day']));

        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', $location);
        $this->assertSame($books['targetBefore'], $this->stored($books['target']));
        $this->assertSame($books['bystanderBefore'], $this->stored($books['bystander']));
    }

    public function testOrdinaryUserCannotChangeAManualLedgerEntry(): void
    {
        $mark = bin2hex(random_bytes(4));
        $books = $this->postManualEntry('Posted rent '.$mark, '10.00');
        $this->client->loginUser($this->user(SmokeFixtures::USER_USERNAME));

        $this->client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);

        $token = $this->transactionFormToken();
        $forgedDay = $books['day']->modify('+10 days');
        $forged = 'Falsified rent '.$mark;
        $this->client->request('POST', $this->editUrl($books['target']), $this->editPayload(
            $books,
            '1.00',
            $forged,
            $forgedDay,
            $books['replacement'],
            $books['bystanderDebit'],
            $token,
        ));

        $this->assertLedgerRejected($books, $forged);
        $this->assertProductStillShows($books, 'Posted rent '.$mark, '10,00', $forged, true);
    }

    public function testOrdinaryUserCannotChangeADocumentBackedLedgerEntry(): void
    {
        $mark = bin2hex(random_bytes(4));
        $books = $this->postIssuedInvoiceEntry('Issued invoice ledger '.$mark);
        $this->client->loginUser($this->user(SmokeFixtures::USER_USERNAME));
        $token = $this->transactionFormToken();
        $forged = 'Falsified invoice ledger '.$mark;

        $this->client->request('POST', $this->editUrl($books['target']), $this->editPayload(
            $books,
            $books['targetBefore']['sum'],
            $forged,
            $books['day'],
            $books['replacement'],
            $books['bystanderDebit'],
            $token,
        ));

        $this->assertLedgerRejected($books, $forged);
        $this->assertProductStillShows($books, 'Issued invoice ledger '.$mark, $this->money($books['targetBefore']['sum']), $forged, false);
    }

    public function testAdministratorChangesOnlyTheLedgerEntryInTheUrl(): void
    {
        $mark = bin2hex(random_bytes(4));
        $adjusted = 'Admin adjusted rent '.$mark;
        $books = $this->postManualEntry('Admin posted rent '.$mark, '10.00');
        $this->client->loginUser($this->user(SmokeFixtures::ADMIN_USERNAME));

        $list = $this->listHtml();
        $this->assertStringContainsString($this->editUrl($books['target']), $list);

        $token = $this->transactionFormToken();
        $forgedDay = $books['day']->modify('+3 days');
        $this->client->request('POST', $this->editUrl($books['target']), $this->editPayload(
            $books,
            '1.00',
            $adjusted,
            $forgedDay,
            $books['replacement'],
            $books['bystanderDebit'],
            $token,
        ));

        $status = $this->client->getResponse()->getStatusCode();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertTrue(
            in_array($status, [302, 303], true) && str_contains($location, $this->showUrl($books['target'])),
            'Administrator edit should save the URL entry and redirect to it. Status '.$status.' location '.$location.' '.$this->responseExcerpt(),
        );

        $stored = $this->stored($books['target']);
        $this->assertSame('1.00', $stored['sum']);
        $this->assertSame($forgedDay->format('Y-m-d'), $stored['date']);
        $this->assertSame($books['replacement'], $stored['debit']);
        $this->assertSame($books['bystanderDebit'], $stored['credit']);
        $this->assertSame($adjusted, $stored['description']);
        $this->assertSame($books['bystanderBefore'], $this->stored($books['bystander']));

        $this->assertStringContainsString($adjusted, $this->listHtml());
        $this->assertExportContains($adjusted);
        $this->assertStringContainsString($this->money('1.00'), $this->reportRow($books, $books['replacementName']));
    }

    /**
     * @return array{
     *     day: \DateTimeImmutable,
     *     organization: string,
     *     debit: string,
     *     credit: string,
     *     replacement: string,
     *     bystanderDebit: string,
     *     debitName: string,
     *     replacementName: string,
     *     target: string,
     *     bystander: string,
     *     targetBefore: array<string, string>,
     *     bystanderBefore: array<string, string>
     * }
     */
    private function postManualEntry(string $description, string $sum): array
    {
        $admin = $this->user(SmokeFixtures::ADMIN_USERNAME);
        $organization = $this->fixtures['organization'];
        $day = $this->freshDay();
        $chart = $this->chart($admin);

        $target = $this->persistManual($admin, $organization, $chart['debit'], $chart['credit'], $sum, $description, $day);
        $bystander = $this->persistManual($admin, $organization, $chart['otherDebit'], $chart['otherCredit'], '99.00', 'Untouched bystander fee', $day);
        $this->em->flush();

        return $this->books($organization, $day, $chart, $target, $bystander);
    }

    /**
     * @return array<string, mixed>
     */
    private function postIssuedInvoiceEntry(string $description): array
    {
        $admin = $this->user(SmokeFixtures::ADMIN_USERNAME);
        $organization = $this->fixtures['organization'];
        $partner = $this->fixtures['partner'];
        $day = $this->freshDay();
        $chart = $this->chart($admin);

        $settings = $organization->getOrganizationSettings();
        $this->assertInstanceOf(OrganizationSettings::class, $settings);
        (new \ReflectionProperty(OrganizationSettings::class, 'IssueInvoiceDebit'))->setValue($settings, $chart['debit']);
        (new \ReflectionProperty(OrganizationSettings::class, 'IssueInvoiceCredit'))->setValue($settings, $chart['credit']);

        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = \DateTime::createFromImmutable($day);
        $command->dateServiceRenderedFrom = \DateTime::createFromImmutable($day->modify('-7 days'));
        $command->dateServiceRenderedTo = \DateTime::createFromImmutable($day->modify('-1 days'));
        $command->dueDate = \DateTime::createFromImmutable($day->modify('+10 days'));
        $command->discount = 0;
        $command->number = (string) random_int(10000000, 99999999);
        $command->issuer = $organization;
        $command->recepient = $partner;
        $invoice = $admin->createInvoice($command);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Consulting';
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = 100;
        $item->discount = 0;
        $invoiceItem = $invoice->createInvoiceItem($item);
        $this->em->persist($invoice);
        $this->em->persist($invoiceItem);
        $this->em->flush();

        $issued = $invoice->setIssued(\DateTime::createFromImmutable($day), $command->number, $admin);
        (new \ReflectionProperty(Transaction::class, 'description'))->setValue($issued, $description);
        $bystander = $this->persistManual($admin, $organization, $chart['otherDebit'], $chart['otherCredit'], '99.00', 'Untouched invoice bystander', $day);
        $this->em->persist($issued);
        $this->em->flush();

        return $this->books($organization, $day, $chart, $issued, $bystander);
    }

    /**
     * @param array{debit: Konto, credit: Konto, otherDebit: Konto, otherCredit: Konto, replacement: Konto, debitName: string, replacementName: string} $chart
     * @return array<string, mixed>
     */
    private function books(Organization $organization, \DateTimeImmutable $day, array $chart, Transaction $target, Transaction $bystander): array
    {
        $books = [
            'day' => $day,
            'organization' => $organization->getId()->toString(),
            'debit' => $chart['debit']->getId()->toString(),
            'credit' => $chart['credit']->getId()->toString(),
            'replacement' => $chart['replacement']->getId()->toString(),
            'bystanderDebit' => $chart['otherDebit']->getId()->toString(),
            'debitName' => $chart['debitName'],
            'replacementName' => $chart['replacementName'],
            'target' => $target->getId()->toString(),
            'bystander' => $bystander->getId()->toString(),
        ];
        $books['targetBefore'] = $this->stored($books['target']);
        $books['bystanderBefore'] = $this->stored($books['bystander']);

        return $books;
    }

    /**
     * @return array{debit: Konto, credit: Konto, otherDebit: Konto, otherCredit: Konto, replacement: Konto, debitName: string, replacementName: string}
     */
    private function chart(User $admin): array
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = random_int(1000, 9999);
        $classCommand->name = 'Ledger class '.$classCommand->number;
        $classCommand->description = 'Ledger authorization test';
        $class = new KontoClass($classCommand, $admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = $classCommand->number;
        $categoryCommand->name = 'Ledger category '.$classCommand->number;
        $categoryCommand->description = 'Ledger authorization test';
        $category = new KontoCategory($categoryCommand, $class, $admin);

        $debitName = 'Ledger debit '.$classCommand->number;
        $replacementName = 'Ledger replacement '.$classCommand->number;
        $debit = $this->konto($category, $admin, 110, $debitName);
        $credit = $this->konto($category, $admin, 120, 'Ledger credit '.$classCommand->number);
        $otherDebit = $this->konto($category, $admin, 210, 'Ledger other debit '.$classCommand->number);
        $otherCredit = $this->konto($category, $admin, 220, 'Ledger other credit '.$classCommand->number);
        $replacement = $this->konto($category, $admin, 310, $replacementName);

        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);
        $this->em->persist($otherDebit);
        $this->em->persist($otherCredit);
        $this->em->persist($replacement);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'otherDebit' => $otherDebit,
            'otherCredit' => $otherCredit,
            'replacement' => $replacement,
            'debitName' => $debitName,
            'replacementName' => $replacementName,
        ];
    }

    private function konto(KontoCategory $category, User $admin, int $number, string $name): Konto
    {
        $command = new class extends CreateKontoCommand {
            public int $type;
        };
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = KontoTypes::active;

        return new Konto($command, $category, $admin);
    }

    private function persistManual(User $admin, Organization $organization, Konto $debit, Konto $credit, string $sum, string $description, \DateTimeImmutable $day): Transaction
    {
        $command = new CreateTransactionCommand();
        $command->organization = $organization;
        $command->debitKonto = $debit;
        $command->creditKonto = $credit;
        $command->sum = $sum;
        $command->date = \DateTime::createFromImmutable($day);
        $command->description = $description;
        $command->hidden = false;
        $transaction = $admin->createTransactionWithDescription($command);
        $this->em->persist($transaction);

        return $transaction;
    }

    /**
     * @param array<string, mixed> $books
     * @return array<string, mixed>
     */
    private function editPayload(
        array $books,
        string $sum,
        string $description,
        \DateTimeImmutable $day,
        ?string $debit = null,
        ?string $credit = null,
        ?string $token = null,
    ): array {
        $transaction = [
            'organization' => $books['organization'],
            'date' => $day->format('Y-m-d'),
            'debitKonto' => $debit ?? $books['debit'],
            'creditKonto' => $credit ?? $books['credit'],
            'sum' => $sum,
            'description' => $description,
            // A different entry's id in the body must not select what gets written.
            'id' => $books['bystander'],
        ];
        if ($token !== null) {
            $transaction['_token'] = $token;
        }

        return ['transaction' => $transaction];
    }

    private function transactionFormToken(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/transaction/new');
        $this->assertResponseIsSuccessful('The signed-in user can open the new-transaction form. '.$this->responseExcerpt());
        $token = $crawler->filter('input[name="transaction[_token]"]');
        $this->assertGreaterThan(0, $token->count(), 'Transaction form did not render a CSRF field. '.$this->responseExcerpt());

        return (string) $token->attr('value');
    }

    /**
     * @param array<string, mixed> $books
     */
    private function assertLedgerRejected(array $books, string $forgedDescription): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $target = $this->stored($books['target']);
        $bystander = $this->stored($books['bystander']);
        $problems = [];
        if ($status !== 403) {
            $problems[] = 'expected HTTP 403, got '.$status.' '.$this->responseExcerpt();
        }
        if ($target != $books['targetBefore']) {
            $problems[] = 'target entry changed from '.json_encode($books['targetBefore']).' to '.json_encode($target);
        }
        if ($bystander != $books['bystanderBefore']) {
            $problems[] = 'a different entry changed from '.json_encode($books['bystanderBefore']).' to '.json_encode($bystander);
        }
        if ($target['description'] === $forgedDescription || $bystander['description'] === $forgedDescription) {
            $problems[] = 'forged description was stored';
        }
        $this->assertSame([], $problems, "Ledger edit must be refused before anything is written:\n- ".implode("\n- ", $problems));
    }

    /**
     * @param array<string, mixed> $books
     */
    private function assertProductStillShows(array $books, string $description, string $amount, string $forged, bool $onLedgerList): void
    {
        if ($onLedgerList) {
            $page = $this->listHtml();
            $this->assertStringContainsString($description, $page);
            $this->assertStringNotContainsString($forged, $page);
            $this->assertStringContainsString($amount, $page);
            $this->assertStringNotContainsString($this->editUrl($books['target']), $page);
        }

        $this->assertExportContains($description);
        $this->assertExportDoesNotContain($forged);
        $this->assertStringContainsString($amount, $this->reportRow($books));
    }

    /**
     * @param array<string, mixed> $books
     */
    private function reportRow(array $books, ?string $kontoName = null): string
    {
        $name = $kontoName ?? $books['debitName'];
        $report = $this->client->request('GET', '/dashboard/report?organization='.$books['organization']);
        $this->assertResponseIsSuccessful($this->responseExcerpt());
        $row = $report->filter('tr')->reduce(function ($node) use ($name) {
            return str_contains($node->text(), $name);
        });
        $this->assertGreaterThan(0, $row->count(), 'Report no longer lists '.$name);

        return $row->text();
    }

    private function assertExportContains(string $needle): void
    {
        $this->assertStringContainsString($needle, $this->exportText(), 'Export did not contain '.$needle);
    }

    private function assertExportDoesNotContain(string $needle): void
    {
        $this->assertStringNotContainsString($needle, $this->exportText());
    }

    private function listHtml(): string
    {
        $this->client->request('GET', '/dashboard/transaction');
        $this->assertResponseIsSuccessful($this->responseExcerpt());

        return (string) $this->client->getResponse()->getContent();
    }

    private function exportText(): string
    {
        $text = '';
        foreach ($this->exportRows() as $row) {
            foreach ($row as $cell) {
                $text .= (string) $cell."\n";
            }
        }

        return $text;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function exportRows(): array
    {
        $this->client->request('GET', '/dashboard/transaction/export');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $path = $response->getFile()->getPathname();
        $this->assertFileExists($path);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);

        return $reader->load($path)->getActiveSheet()->toArray();
    }

    /**
     * @return array{sum: string, date: string, debit: string, credit: string, description: string}
     */
    private function stored(string $id): array
    {
        $this->em->clear();
        $transaction = $this->em->getRepository(Transaction::class)->find($id);
        $this->assertInstanceOf(Transaction::class, $transaction);
        $description = (new \ReflectionProperty(Transaction::class, 'description'))->getValue($transaction);

        return [
            'sum' => number_format((float) $transaction->getSum(), 2, '.', ''),
            'date' => $transaction->getDate()->format('Y-m-d'),
            'debit' => $transaction->getDebitKonto()->getId()->toString(),
            'credit' => $transaction->getCreditKonto()->getId()->toString(),
            'description' => (string) $description,
        ];
    }

    private function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function editUrl(string $id): string
    {
        return '/dashboard/transaction/'.$id.'/edit';
    }

    private function showUrl(string $id): string
    {
        return '/dashboard/transaction/'.$id.'/show';
    }

    private function freshDay(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('9999-01-01'))->modify('+'.random_int(0, 300).' days')->setTime(0, 0);
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ');
    }

    private function responseExcerpt(): string
    {
        $response = $this->client->getResponse();

        return 'status '.$response->getStatusCode().' location '.(string) $response->headers->get('Location');
    }
}
