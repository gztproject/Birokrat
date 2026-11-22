<?php

namespace App\Tests\Web;

use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A journal amount that is stored must move the income statement and the balance
 * sheet by the same amount. Rejecting a negative amount before it is stored is
 * the other acceptable outcome. These tests fail while a negative journal line
 * is saved and then omitted from the income statement only.
 *
 * Not covered here: invoice, incoming-invoice, travel, and lunch flows can also
 * copy an amount onto a transaction. Bank cost is dropped when it is not positive.
 */
class NegativeJournalAmountReportTest extends WebTestCase
{
    private const AMOUNT = -80.25;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $organizationId;

    private string $debitKontoId;

    private string $creditKontoId;

    private int $periodFrom;

    private int $periodTo;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $this->em = $container->get(EntityManagerInterface::class);
            $this->em->getConnection()->executeQuery('SELECT 1');
            $fixtures = (new SmokeFixtures(
                $this->em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }

        $actor = $fixtures['user'];
        $this->assertFalse($actor->getIsRoleAdmin());
        $kontos = $this->createReportKontos($actor);
        $this->organizationId = $fixtures['organization']->getId()->toString();
        $this->debitKontoId = $kontos['debit']->getId()->toString();
        $this->creditKontoId = $kontos['credit']->getId()->toString();
        $this->periodFrom = (new \DateTimeImmutable('2024-06-15 00:00:00'))->getTimestamp();
        $this->periodTo = (new \DateTimeImmutable('2024-06-15 23:59:59'))->getTimestamp();

        $this->client->loginUser($actor);
    }

    public function testPositiveJournalAmountMovesBothReportsTogether(): void
    {
        $before = $this->reportSnapshot();
        $description = 'positive-journal-'.bin2hex(random_bytes(4));

        $this->submitNewJournal('80.25', $description);

        $saved = $this->transactionsByDescription($description);
        $this->assertCount(1, $saved);
        $this->assertEqualsWithDelta(80.25, (float) $saved[0]->getSum(), 0.001);
        $this->assertJournalPageShows($saved[0], '80,25');

        $this->assertSameMovement($before, $this->reportSnapshot(), 80.25);
    }

    public function testNewJournalWithNegativeAmountCannotSplitTheReports(): void
    {
        $before = $this->reportSnapshot();
        $description = 'negative-journal-'.bin2hex(random_bytes(4));

        $this->submitNewJournal((string) self::AMOUNT, $description);

        $this->assertNegativeLineIsRejectedOrReported($before, $description, self::AMOUNT);
    }

    public function testEditingJournalToNegativeAmountCannotSplitTheReports(): void
    {
        $description = 'edit-journal-'.bin2hex(random_bytes(4));
        $this->submitNewJournal('40.00', $description);
        $saved = $this->transactionsByDescription($description);
        $this->assertCount(1, $saved);
        $this->assertEqualsWithDelta(40.0, (float) $saved[0]->getSum(), 0.001);

        $before = $this->reportSnapshot();
        $id = $saved[0]->getId()->toString();
        $crawler = $this->client->request('GET', '/dashboard/transaction/'.$id.'/edit');
        $this->assertResponseIsSuccessful();
        $this->submitJournalForm('/dashboard/transaction/'.$id.'/edit', $crawler, (string) self::AMOUNT, $description);

        $this->assertNegativeLineIsRejectedOrReported($before, $description, self::AMOUNT, 40.0);
    }

    public function testCloningJournalWithNegativeAmountCannotSplitTheReports(): void
    {
        $original = 'clone-source-'.bin2hex(random_bytes(4));
        $this->submitNewJournal('25.00', $original);
        $saved = $this->transactionsByDescription($original);
        $this->assertCount(1, $saved);

        $cloneDescription = 'clone-negative-'.bin2hex(random_bytes(4));
        $id = $saved[0]->getId()->toString();
        $crawler = $this->client->request('GET', '/dashboard/transaction/'.$id.'/clone');
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $this->transactionsByDescription($cloneDescription));

        $before = $this->reportSnapshot();
        $this->submitJournalForm('/dashboard/transaction/'.$id.'/clone', $crawler, (string) self::AMOUNT, $cloneDescription);

        $this->assertEqualsWithDelta(25.0, (float) $this->transactionsByDescription($original)[0]->getSum(), 0.001);
        $this->assertNegativeLineIsRejectedOrReported($before, $cloneDescription, self::AMOUNT);
    }

    /**
     * @param array{balance: float, income: float} $before
     */
    private function assertNegativeLineIsRejectedOrReported(array $before, string $description, float $submitted, ?float $previousSum = null): void
    {
        $rows = $this->transactionsByDescription($description);
        $after = $this->reportSnapshot();
        $balanceDelta = round($after['balance'] - $before['balance'], 2);
        $incomeDelta = round($after['income'] - $before['income'], 2);

        if ($previousSum === null) {
            $this->assertLessThanOrEqual(1, count($rows));
            if ($rows === []) {
                $this->assertEqualsWithDelta(0.0, $balanceDelta, 0.001);
                $this->assertEqualsWithDelta(0.0, $incomeDelta, 0.001);

                return;
            }

            $this->assertEqualsWithDelta($submitted, (float) $rows[0]->getSum(), 0.001);
            $this->assertJournalPageShows($rows[0], '-80,25');
            $this->assertSameMovement($before, $after, $submitted);

            return;
        }

        $this->assertCount(1, $rows);
        $actual = round((float) $rows[0]->getSum(), 2);
        if (abs($actual - $previousSum) < 0.001) {
            $this->assertEqualsWithDelta(0.0, $balanceDelta, 0.001);
            $this->assertEqualsWithDelta(0.0, $incomeDelta, 0.001);

            return;
        }

        $this->assertEqualsWithDelta($submitted, $actual, 0.001);
        $this->assertJournalPageShows($rows[0], '-80,25');
        $this->assertSameMovement($before, $after, round($submitted - $previousSum, 2));
    }

    /**
     * @param array{balance: float, income: float} $before
     * @param array{balance: float, income: float} $after
     */
    private function assertSameMovement(array $before, array $after, float $expected): void
    {
        $balanceDelta = round($after['balance'] - $before['balance'], 2);
        $incomeDelta = round($after['income'] - $before['income'], 2);

        $this->assertEqualsWithDelta($expected, $balanceDelta, 0.001, 'Balance sheet did not count the stored journal amount.');
        $this->assertEqualsWithDelta(
            $balanceDelta,
            $incomeDelta,
            0.001,
            'Income statement omitted a journal amount that the balance sheet counted.'
        );
    }

    private function submitNewJournal(string $amount, string $description): void
    {
        $crawler = $this->client->request('GET', '/dashboard/transaction/new');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('#transaction_sum')->count());
        $this->submitJournalForm('/dashboard/transaction/new', $crawler, $amount, $description);
    }

    /**
     * The journal template's extra closing tags put every field after the date
     * outside the form element, so the Save button does not send them. The save
     * action still accepts a request that posts those rendered fields.
     */
    private function submitJournalForm(string $url, Crawler $crawler, string $amount, string $description): void
    {
        $this->client->request('POST', $url, [
            'transaction' => [
                'organization' => $this->organizationId,
                'date' => '2024-06-15',
                'debitKonto' => $this->debitKontoId,
                'creditKonto' => $this->creditKontoId,
                'sum' => $amount,
                'description' => $description,
                '_token' => $crawler->filter('#transaction__token')->attr('value'),
            ],
        ]);
    }

    private function assertJournalPageShows(Transaction $transaction, string $formattedAmount): void
    {
        $page = 1;
        $last = 1;
        do {
            $this->client->request('GET', '/dashboard/transaction?'.http_build_query([
                'partial' => 1,
                'page' => $page,
                'dateFrom' => $this->periodFrom,
                'dateTo' => $this->periodTo,
                'organization' => $this->organizationId,
            ]));
            $this->assertResponseIsSuccessful();
            $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
            $this->assertIsArray($payload);
            $html = ($payload['rows'] ?? '').($payload['cards'] ?? '');
            if (str_contains($html, (string) $transaction->getDescription()) && str_contains($html, $formattedAmount)) {
                return;
            }
            $last = (int) ($payload['lastPage'] ?? 1);
            ++$page;
        } while ($page <= $last);

        $this->fail('Journal list did not show the stored line.');
    }

    /**
     * @return array{balance: float, income: float}
     */
    private function reportSnapshot(): array
    {
        $query = http_build_query([
            'organization' => $this->organizationId,
            'dateFrom' => $this->periodFrom,
            'dateTo' => $this->periodTo,
        ]);

        $this->client->request('GET', '/dashboard/report/blianca?'.$query);
        $this->assertResponseIsSuccessful();
        $balance = $this->amountForRow('036');

        $this->client->request('GET', '/dashboard/report/turnout?'.$query);
        $this->assertResponseIsSuccessful();
        $income = round($this->amountForRow('121') + $this->amountForRow('122'), 2);

        return ['balance' => $balance, 'income' => $income];
    }

    private function amountForRow(string $code): float
    {
        $span = $this->client->getCrawler()->filter('td.lbl span')->reduce(
            static fn (Crawler $node): bool => trim($node->text()) === $code
        );
        $this->assertGreaterThan(0, $span->count(), 'Report row '.$code.' was not rendered.');
        $text = $span->closest('tr')->filter('td.input.oddcolor')->eq(0)->text();
        $normalized = str_replace([',', ' ', "\xc2\xa0", '€', "\n", "\r", "\t"], ['.', '', '', '', '', '', ''], $text);

        $this->assertTrue(is_numeric($normalized), 'Could not read amount from "'.$text.'".');

        return (float) $normalized;
    }

    /**
     * @return list<Transaction>
     */
    private function transactionsByDescription(string $description): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return array_values($em->getRepository(Transaction::class)->findBy(['description' => $description]));
    }

    /**
     * @return array{debit: Konto, credit: Konto}
     */
    private function createReportKontos(User $actor): array
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 6;
        $classCommand->name = 'Report class';
        $classCommand->description = 'Report class';
        $class = new KontoClass($classCommand, $actor);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 60;
        $categoryCommand->name = 'Inventory change';
        $categoryCommand->description = 'Inventory change';
        $category = $class->createCategory($categoryCommand, $actor);

        $credit = $category->createKonto($this->kontoCommand(6000, 'Inventory', KontoTypes::passive), $actor);

        $debitClassCommand = new class extends CreateKontoClassCommand {};
        $debitClassCommand->number = 1;
        $debitClassCommand->name = 'Assets';
        $debitClassCommand->description = 'Assets';
        $debitClass = new KontoClass($debitClassCommand, $actor);

        $debitCategoryCommand = new class extends CreateKontoCategoryCommand {};
        $debitCategoryCommand->number = 1;
        $debitCategoryCommand->name = 'Cash';
        $debitCategoryCommand->description = 'Cash';
        $debitCategory = $debitClass->createCategory($debitCategoryCommand, $actor);
        $debit = $debitCategory->createKonto($this->kontoCommand(1000, 'Cash', KontoTypes::active), $actor);

        foreach ([$class, $category, $credit, $debitClass, $debitCategory, $debit] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return ['debit' => $debit, 'credit' => $credit];
    }

    private function kontoCommand(int $number, string $name, int $type): CreateKontoCommand
    {
        $command = new class extends CreateKontoCommand {
            public int $type;
        };
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = $type;

        return $command;
    }
}
