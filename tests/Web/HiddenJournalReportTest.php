<?php

namespace App\Tests\Web;

use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
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
 * An ordinary signed-in user must not be able to drop a journal amount out of
 * the overview, balance sheet, income statement, or tax summary by checking
 * the hidden box. The line stays on the journal list for other signed-in users.
 *
 * These assertions fail while that omission is possible.
 */
class HiddenJournalReportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $booker;
    private User $reader;
    private Organization $organization;
    private Konto $bankKonto;
    private Konto $incomeKonto;

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

        $this->booker = $fixtures['user'];
        $this->reader = $fixtures['admin'];
        self::assertNotContains('ROLE_ADMIN', $this->booker->getRoles());
        self::assertNotSame($this->booker->getUserIdentifier(), $this->reader->getUserIdentifier());

        $this->organization = $this->newOrganization($fixtures['admin'], $fixtures['organization']->getAddress());
        [$this->bankKonto, $this->incomeKonto] = $this->incomeAndBankKontos($fixtures['admin']);
        $this->booker->addOrganization($this->organization, $fixtures['admin']);
        // Reports and the journal list are scoped to the viewer's memberships.
        $this->reader->addOrganization($this->organization, $fixtures['admin']);
        $this->em->flush();
    }

    public function testOrdinaryUserCannotHideANewJournalLineFromFinancialReports(): void
    {
        $visible = 'visible-journal-'.bin2hex(random_bytes(4));
        $omitted = 'hidden-journal-'.bin2hex(random_bytes(4));

        $this->client->loginUser($this->booker);
        $this->postJournal('/dashboard/transaction/new', '10.00', $visible, false);
        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'A signed-in user can book a journal line. Response: '.$this->failureBody()
        );
        $this->postJournal('/dashboard/transaction/new', '20.00', $omitted, true);
        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Checking hidden must not reject an otherwise valid journal line. Response: '.$this->failureBody()
        );

        $this->em->clear();
        $hiddenLine = $this->findByDescription($omitted);
        self::assertInstanceOf(Transaction::class, $hiddenLine);

        $this->client->loginUser($this->reader);
        $this->client->request('GET', '/dashboard/transaction?organization='.$this->organization->getId()->toString());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($omitted, (string) $this->client->getResponse()->getContent());

        $this->assertReportsInclude('30,00');
        self::assertFalse(
            $this->isHidden($hiddenLine),
            'A user without ROLE_ADMIN must not persist the hidden flag.'
        );
    }

    public function testOrdinaryUserCannotOpenTheEditFormToHideAJournalLine(): void
    {
        $description = 'edit-journal-'.bin2hex(random_bytes(4));
        $this->client->loginUser($this->booker);
        $this->postJournal('/dashboard/transaction/new', '40.00', $description, false);
        self::assertTrue($this->client->getResponse()->isRedirect(), $this->failureBody());

        $this->em->clear();
        $line = $this->findByDescription($description);
        self::assertInstanceOf(Transaction::class, $line);

        // Edit is ROLE_ADMIN only; an ordinary user must not reach the form at all.
        $this->client->request('GET', '/dashboard/transaction/'.$line->getId()->toString().'/edit');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/dashboard/transaction/'.$line->getId()->toString().'/edit', [
            'transaction' => [
                'organization' => $this->organization->getId()->toString(),
                'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'debitKonto' => $this->bankKonto->getId()->toString(),
                'creditKonto' => $this->incomeKonto->getId()->toString(),
                'sum' => '40.00',
                'description' => $description,
                'bankCost' => '',
                'hidden' => '1',
                '_token' => 'not-checked-on-403',
            ],
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $line = $this->findByDescription($description);
        self::assertFalse($this->isHidden($line));
        $this->assertReportsInclude('40,00');
    }

    public function testCloneCannotHideAJournalLineFromFinancialReports(): void
    {
        $description = 'clone-journal-'.bin2hex(random_bytes(4));
        $this->client->loginUser($this->booker);
        $this->postJournal('/dashboard/transaction/new', '15.00', $description, false);
        self::assertTrue($this->client->getResponse()->isRedirect(), $this->failureBody());

        $this->em->clear();
        $line = $this->findByDescription($description);
        self::assertInstanceOf(Transaction::class, $line);

        $this->postJournal(
            '/dashboard/transaction/'.$line->getId()->toString().'/clone',
            '15.00',
            $description,
            true
        );
        self::assertTrue($this->client->getResponse()->isRedirect(), $this->failureBody());

        $this->em->clear();
        $lines = $this->em->getRepository(Transaction::class)->findBy(['description' => $description]);
        self::assertCount(2, $lines);
        foreach ($lines as $saved) {
            self::assertFalse($this->isHidden($saved));
        }
        $this->assertReportsInclude('30,00');
    }

    private function postJournal(string $path, string $sum, string $description, bool $hidden): void
    {
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful('GET '.$path.' failed: '.$this->failureBody());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertSame(1, preg_match('/name="transaction\[_token\]"[^>]*value="([^"]+)"/', $html, $token));
        self::assertStringContainsString('name="transaction[hidden]"', $html);

        $fields = [
            'organization' => $this->organization->getId()->toString(),
            'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'debitKonto' => $this->bankKonto->getId()->toString(),
            'creditKonto' => $this->incomeKonto->getId()->toString(),
            'sum' => $sum,
            'description' => $description,
            'bankCost' => '',
            '_token' => $token[1],
        ];
        if ($hidden) {
            $fields['hidden'] = '1';
        }

        $this->client->request('POST', $path, ['transaction' => $fields]);
    }

    private function assertReportsInclude(string $amount): void
    {
        $from = (new \DateTimeImmutable('today'))->getTimestamp();
        $to = (new \DateTimeImmutable('today 23:59:59'))->getTimestamp();
        $query = http_build_query([
            'organization' => $this->organization->getId()->toString(),
            'dateFrom' => $from,
            'dateTo' => $to,
        ]);

        $this->client->loginUser($this->reader);

        $actual = [];
        $this->client->request('GET', '/dashboard/report?'.$query);
        self::assertResponseIsSuccessful($this->failureBody());
        $overview = $this->client->getCrawler();
        $actual['overview income'] = $this->amountAfterLabel($overview, '//td[starts-with(normalize-space(.), "Izdani računi")]/following-sibling::td[1]');
        $actual['overview bank'] = $this->amountAfterLabel($overview, '//td[normalize-space()="TRR"]/following-sibling::td[1]');

        $this->client->request('GET', '/dashboard/report/blianca?'.$query);
        self::assertResponseIsSuccessful($this->failureBody());
        $actual['balance sheet'] = $this->amountAfterLabel($this->client->getCrawler(), '//td[span[normalize-space()="052"]]/following-sibling::td[contains(@class,"oddcolor")][1]');

        $this->client->request('GET', '/dashboard/report/turnout?'.$query);
        self::assertResponseIsSuccessful($this->failureBody());
        $actual['income statement'] = $this->amountAfterLabel($this->client->getCrawler(), '//td[span[normalize-space()="111"]]/following-sibling::td[contains(@class,"oddcolor")][1]');

        $this->client->request('GET', '/dashboard/report/dddddd?'.$query);
        self::assertResponseIsSuccessful($this->failureBody());
        $actual['tax summary'] = $this->amountAfterLabel($this->client->getCrawler(), '//td[normalize-space()="1."]/following-sibling::td[contains(@class,"number")][1]');

        $missing = array_filter($actual, static fn (string $value): bool => $value !== $amount);
        self::assertSame([], $missing, 'Reports must include the full journal amount '.$amount.'. Actual: '.json_encode($actual));
    }

    private function amountAfterLabel(Crawler $crawler, string $xpath): string
    {
        $node = $crawler->filterXPath($xpath);
        self::assertGreaterThan(0, $node->count(), 'Missing report cell for '.$xpath);
        $text = preg_replace('/\s+/', '', $node->text()) ?? '';
        if (!preg_match('/-?\d+,\d{2}/', $text, $match)) {
            self::fail('No amount in report cell: '.$node->text());
        }

        return $match[0];
    }

    private function findByDescription(string $description): ?Transaction
    {
        $found = $this->em->getRepository(Transaction::class)->findOneBy(['description' => $description]);

        return $found instanceof Transaction ? $found : null;
    }

    private function isHidden(Transaction $transaction): bool
    {
        $property = new \ReflectionProperty(Transaction::class, 'hidden');
        $property->setAccessible(true);

        return (bool) $property->getValue($transaction);
    }

    private function newOrganization(User $admin, mixed $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = substr(bin2hex(random_bytes(4)), 0, 8);
        $command->name = 'Report Org '.$command->code;
        $command->shortName = 'R'.$command->code;
        $command->taxNumber = '10000000';
        $command->taxable = true;
        $command->address = $address;
        $organization = $admin->createOrganization($command);
        $this->em->persist($organization);
        $this->em->flush();

        return $organization;
    }

    /**
     * @return array{0: Konto, 1: Konto}
     */
    private function incomeAndBankKontos(User $admin): array
    {
        $bank = $this->findKonto(110, 11);
        $income = $this->findKonto(760, 76);
        if ($bank instanceof Konto && $income instanceof Konto) {
            return [$bank, $income];
        }

        $class1 = $this->kontoClass($admin, 1, 'Financna sredstva');
        $category11 = $class1->createCategory($this->categoryCommand(11, 'Denarna sredstva'), $admin);
        $bank = $category11->createKonto($this->kontoCommand(110, 'Transakcijski racun', KontoTypes::active), $admin);

        $class7 = $this->kontoClass($admin, 7, 'Prihodki');
        $category76 = $class7->createCategory($this->categoryCommand(76, 'Poslovni prihodki'), $admin);
        $income = $category76->createKonto($this->kontoCommand(760, 'Prihodki od prodaje', KontoTypes::passive), $admin);

        $this->em->persist($class1);
        $this->em->persist($category11);
        $this->em->persist($bank);
        $this->em->persist($class7);
        $this->em->persist($category76);
        $this->em->persist($income);
        $this->em->flush();

        return [$bank, $income];
    }

    private function findKonto(int $number, int $categoryNumber): ?Konto
    {
        $found = $this->em->createQuery(
            'SELECT k FROM App\Entity\Konto\Konto k JOIN k.category c WHERE k.number = :number AND c.number = :category'
        )->setParameter('number', $number)->setParameter('category', $categoryNumber)->setMaxResults(1)->getOneOrNullResult();

        return $found instanceof Konto ? $found : null;
    }

    private function kontoClass(User $admin, int $number, string $name): KontoClass
    {
        $command = new class extends CreateKontoClassCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;

        return new KontoClass($command, $admin);
    }

    private function categoryCommand(int $number, string $name): CreateKontoCategoryCommand
    {
        $command = new class extends CreateKontoCategoryCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;

        return $command;
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

    private function failureBody(): string
    {
        return substr(preg_replace('/\s+/', ' ', (string) $this->client->getResponse()->getContent()) ?? '', 0, 500);
    }
}
