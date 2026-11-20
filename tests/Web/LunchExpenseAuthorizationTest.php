<?php

namespace App\Tests\Web;

use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoClass;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\LunchExpense\Enumerators\States;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Meal-expense booking and listing must not cross company membership.
 *
 * These assertions fail while any signed-in user can post
 * /dashboard/lunchExpense/bookInBundle/withFilter for a date range and have
 * the action load every unbooked claim in that range, then save a booked
 * bundle and a ledger transaction for companies the user does not belong to.
 * The list at /dashboard/lunchExpense uses the same unscoped query.
 *
 * Not covered here, because they do not go through that date filter:
 * - LunchExpenseQueryController::showBundle loads one bundle by id.
 * - LunchExpenseCommandController::new accepts any organization on the form.
 * - TravelExpenseCommandController::new and ::clone call the same query only
 *   to decide whether to auto-create a lunch claim.
 * - TravelExpenseCommandController::book is the same pattern for travel claims.
 */
class LunchExpenseAuthorizationTest extends WebTestCase
{
    private const BOOK_PATH = '/dashboard/lunchExpense/bookInBundle/withFilter';

    private const LIST_PATH = '/dashboard/lunchExpense';

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $client = static::createClient();
            $client->getContainer()->get(EntityManagerInterface::class)->getConnection()->executeQuery('SELECT 1');
            static::ensureKernelShutdown();
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testSignedInOutsiderDoesNotBookOtherCompaniesInADateRange(): void
    {
        $client = static::createClient();
        $world = $this->seedWorld($client);
        $foreignIds = array_map(static fn (Organization $org): string => $org->getId()->toString(), $world['foreign']);

        $client->loginUser($world['attacker']);
        $client->request('POST', self::BOOK_PATH, [
            'dateFrom' => $world['from'],
            'dateTo' => $world['to'],
            'booked' => '',
            'unbooked' => '',
            'date' => $world['bookDate'],
        ]);

        $em = $this->reloadManager();
        $status = $client->getResponse()->getStatusCode();

        foreach ($world['foreignClaims'] as $claimId) {
            $claim = $this->claimRow($em, $claimId);
            $this->assertSame(
                States::new,
                $claim['state'],
                'A meal claim in another company must stay unbooked. HTTP '.$status
            );
            $this->assertNull(
                $claim['bundleId'],
                'A meal claim in another company must not be attached to a bundle. HTTP '.$status
            );
        }

        foreach ($foreignIds as $orgId) {
            $org = $em->find(Organization::class, $orgId);
            $posted = (int) $em->createQuery(
                'SELECT COUNT(t.id) FROM '.Transaction::class.' t WHERE t.organization = :org AND t.lunchExpenseBundle IS NOT NULL'
            )->setParameter('org', $org)->getSingleScalarResult();
            $this->assertSame(0, $posted, 'No ledger bundle may be posted for a company the user does not belong to.');
        }

        $own = $this->claimRow($em, $world['ownClaim']);
        $this->assertSame(
            States::booked,
            $own['state'],
            'The same request still books a claim of a company the user belongs to. HTTP '.$status
        );
        $this->assertNotNull($own['bundleId'], 'The member company claim must be bundled when it is booked.');
    }

    public function testSignedInOutsiderDoesNotBookWhenTheDateFilterIsLeftEmpty(): void
    {
        $client = static::createClient();
        $world = $this->seedWorld($client);

        $client->loginUser($world['attacker']);
        // The book button posts the current query string. With no filter applied
        // those fields are empty, and an empty bound is not added to the query.
        $client->request('POST', self::BOOK_PATH, [
            'dateFrom' => '',
            'dateTo' => '',
            'booked' => '',
            'unbooked' => '',
            'date' => $world['bookDate'],
        ]);

        $em = $this->reloadManager();
        $status = $client->getResponse()->getStatusCode();
        foreach ($world['foreignClaims'] as $claimId) {
            $claim = $this->claimRow($em, $claimId);
            $this->assertSame(States::new, $claim['state'], 'Empty date bounds must not book another company. HTTP '.$status);
            $this->assertNull($claim['bundleId'], 'Empty date bounds must not bundle another company. HTTP '.$status);
        }

        $own = $this->claimRow($em, $world['ownClaim']);
        $this->assertSame(States::booked, $own['state'], 'Empty date bounds still book the member company. HTTP '.$status);
    }

    public function testMealExpenseListHidesClaimsFromOtherCompanies(): void
    {
        $client = static::createClient();
        $world = $this->seedWorld($client);
        $client->loginUser($world['attacker']);

        $queries = [
            [
                'dateFrom' => $world['from'],
                'dateTo' => $world['to'],
                'unbooked' => 'true',
                'booked' => 'false',
            ],
            [
                'dateFrom' => $world['from'],
                'dateTo' => $world['to'],
                'unbooked' => 'true',
                'booked' => 'true',
            ],
            [
                'dateFrom' => $world['from'],
                'dateTo' => $world['to'],
                'partial' => '1',
                'unbooked' => 'true',
                'booked' => 'false',
            ],
        ];

        foreach ($queries as $query) {
            $client->request('GET', self::LIST_PATH, $query);
            $this->assertResponseIsSuccessful();
            $body = (string) $client->getResponse()->getContent();
            $this->assertStringContainsString($world['ownClaim'], $body, 'The member company claim is listed for '.json_encode($query));
            foreach ($world['foreignClaims'] as $claimId) {
                $this->assertStringNotContainsString($claimId, $body, 'Another company claim is listed for '.json_encode($query));
            }
        }
    }

    public function testAnonymousPostDoesNotBook(): void
    {
        $client = static::createClient();
        $world = $this->seedWorld($client);

        $client->request('POST', self::BOOK_PATH, [
            'dateFrom' => $world['from'],
            'dateTo' => $world['to'],
            'date' => $world['bookDate'],
        ]);

        $this->assertResponseRedirects();
        $em = $this->reloadManager();
        foreach (array_merge([$world['ownClaim']], $world['foreignClaims']) as $claimId) {
            $claim = $this->claimRow($em, $claimId);
            $this->assertSame(States::new, $claim['state']);
            $this->assertNull($claim['bundleId']);
        }
    }

    /**
     * @return array{
     *     attacker: User,
     *     foreign: list<Organization>,
     *     from: string,
     *     to: string,
     *     bookDate: string,
     *     ownClaim: string,
     *     foreignClaims: list<string>
     * }
     */
    private function seedWorld(KernelBrowser $client): array
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $fixtures = (new SmokeFixtures($em, $hasher))->load();
        $admin = $fixtures['admin'];
        $address = $fixtures['organization']->getAddress();

        $suffix = bin2hex(random_bytes(4));
        $attackerCommand = new CreateUserCommand();
        $attackerCommand->username = 'meal-outsider-'.$suffix;
        $attackerCommand->firstName = 'Meal';
        $attackerCommand->lastName = 'Outsider';
        $attackerCommand->email = 'meal-outsider-'.$suffix.'@example.test';
        $attackerCommand->password = SmokeFixtures::USER_PASSWORD;
        $attackerCommand->isRoleAdmin = false;
        $attackerCommand->signatureFilename = '';
        $attacker = $admin->createUser($attackerCommand, $hasher);
        $em->persist($attacker);

        $home = $this->makeOrganization($admin, $address, 'H'.$suffix, 'Home '.$suffix, '1000'.$suffix);
        $foreignA = $this->makeOrganization($admin, $address, 'A'.$suffix, 'Foreign A '.$suffix, '2000'.$suffix);
        $foreignB = $this->makeOrganization($admin, $address, 'B'.$suffix, 'Foreign B '.$suffix, '3000'.$suffix);
        $attacker->addOrganization($home, $admin);

        $debit = $this->makeKonto($em, $admin, 4, 48, 486, KontoTypes::active, 'Meal debit '.$suffix);
        $credit = $this->makeKonto($em, $admin, 2, 28, 285, KontoTypes::passive, 'Meal credit '.$suffix);
        foreach ([$home, $foreignA, $foreignB] as $org) {
            $this->enableExpenseAccounts($org, $admin, $debit, $credit);
            $em->persist($org);
            $em->persist($org->getOrganizationSettings());
        }

        $day = (new \DateTimeImmutable('2091-06-01 12:00:00'))->modify('+'.random_int(0, 1200).' days');
        $own = $this->makeNewClaim($em, $admin, $home, $day, '11.11');
        $claimA = $this->makeNewClaim($em, $admin, $foreignA, $day, '22.22');
        $claimB = $this->makeNewClaim($em, $admin, $foreignB, $day->modify('+1 day'), '33.33');
        $em->flush();

        $this->assertFalse($attacker->getOrganizations()->contains($foreignA));
        $this->assertFalse($attacker->getOrganizations()->contains($foreignB));
        $this->assertTrue($attacker->getOrganizations()->contains($home));
        $this->assertNotContains('ROLE_ADMIN', $attacker->getRoles());

        $from = (string) $day->modify('-40 days')->setTime(0, 0, 0)->getTimestamp();
        $to = (string) $day->modify('+40 days')->setTime(23, 59, 59)->getTimestamp();

        return [
            'attacker' => $attacker,
            'foreign' => [$foreignA, $foreignB],
            'from' => $from,
            'to' => $to,
            'bookDate' => $day->format('Y-m-d'),
            'ownClaim' => $own->getId()->toString(),
            'foreignClaims' => [$claimA->getId()->toString(), $claimB->getId()->toString()],
        ];
    }

    private function makeOrganization(User $admin, object $address, string $code, string $name, string $taxNumber): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = $taxNumber;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56000000000000000';
        $command->bic = 'LJBASI2X';

        return $admin->createOrganization($command);
    }

    private function makeKonto(
        EntityManagerInterface $em,
        User $admin,
        int $classNumber,
        int $categoryNumber,
        int $kontoNumber,
        int $type,
        string $name,
    ): Konto {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = $classNumber;
        $classCommand->name = $name.' class';
        $classCommand->description = $name;
        $class = new KontoClass($classCommand, $admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = $categoryNumber;
        $categoryCommand->name = $name.' category';
        $categoryCommand->description = $name;
        $category = $class->createCategory($categoryCommand, $admin);

        $kontoCommand = new class extends CreateKontoCommand {};
        $kontoCommand->number = $kontoNumber;
        $kontoCommand->name = $name;
        $kontoCommand->description = $name;
        $kontoCommand->isActive = true;
        $kontoCommand->type = $type;
        $konto = $category->createKonto($kontoCommand, $admin);

        $em->persist($class);
        $em->persist($category);
        $em->persist($konto);

        return $konto;
    }

    private function enableExpenseAccounts(Organization $org, User $admin, Konto $debit, Konto $credit): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $org->getOrganizationSettings()->mapTo($command);
        $command->IncurredTravelExpenseDebit = $debit;
        $command->IncurredTravelExpenseCredit = $credit;
        $command->PaidTravelExpenseDebit = $debit;
        $command->PaidTravelExpenseCredit = $credit;
        $org->updateOrganizationSettings($command, $admin);
    }

    private function makeNewClaim(
        EntityManagerInterface $em,
        User $author,
        Organization $organization,
        \DateTimeImmutable $day,
        string $sum,
    ): LunchExpense {
        $command = new CreateLunchExpenseCommand();
        $command->date = \DateTime::createFromImmutable($day);
        $command->organization = $organization;
        $command->sum = $sum;
        $claim = $author->createLunchExpense($command);
        $incurred = $claim->setNew($author);
        $em->persist($claim);
        $em->persist($incurred);

        return $claim;
    }

    private function reloadManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em;
    }

    /**
     * @return array{state: int, bundleId: ?string}
     */
    private function claimRow(EntityManagerInterface $em, string $id): array
    {
        $claim = $em->find(LunchExpense::class, $id);
        $this->assertInstanceOf(LunchExpense::class, $claim);
        $bundleId = $em->createQuery(
            'SELECT b.id FROM '.LunchExpense::class.' le JOIN le.lunchExpenseBundle b WHERE le.id = :id'
        )->setParameter('id', $claim->getId())->getOneOrNullResult();

        return [
            'state' => $claim->getState(),
            'bundleId' => $bundleId === null ? null : (string) $bundleId['id'],
        ];
    }
}
