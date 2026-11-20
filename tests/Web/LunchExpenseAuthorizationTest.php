<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoClass;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A signed-in user may record a meal expense only for a company they belong to.
 * The submitted company is the company that must be authorized: a later check
 * that accepts the user and then stores a different company still fails these tests.
 */
class LunchExpenseAuthorizationTest extends WebTestCase
{
    private const PLACEHOLDER_PASSWORD = 'placeholder-1!';

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private User $member;

    private Organization $ownCompany;

    private Organization $secondCompany;

    private Organization $foreignCompany;

    private \App\Entity\Geography\Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $this->em = static::getContainer()->get(EntityManagerInterface::class);
            $this->em->getConnection()->executeQuery('SELECT 1');
            $this->loadCompanies();
        } catch (DbalException|\PDOException $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testMealExpenseFormOffersOnlyCompaniesLinkedToTheSignedInUser(): void
    {
        $this->client->loginUser($this->member);
        $crawler = $this->client->request('GET', '/dashboard/lunchExpense/new');

        $this->assertResponseIsSuccessful();
        $offered = $this->optionValues($crawler, 'lunch_expense[organization]');

        $this->assertContains($this->id($this->ownCompany), $offered);
        $this->assertContains($this->id($this->secondCompany), $offered);
        $this->assertNotContains(
            $this->id($this->foreignCompany),
            $offered,
            'The meal-expense company field listed a company the signed-in user does not belong to.'
        );
    }

    public function testSignedInUserCannotSaveAMealExpenseOnAnotherCompanysBooks(): void
    {
        $this->client->loginUser($this->member);
        $before = $this->ledgerCounts();

        $this->submitMealExpense($this->id($this->foreignCompany), '2026-11-20', '19.41');

        $this->assertSame(
            $before,
            $this->ledgerCounts(),
            'Submitting another company created or removed a meal expense or journal entry before the request was refused.'
        );
        $this->assertMealExpenseWasRefused();
    }

    public function testSignedInUserCanSaveAMealExpenseOnEachLinkedCompany(): void
    {
        $this->client->loginUser($this->member);

        $this->submitMealExpense($this->id($this->ownCompany), '2026-11-21', '6.10');
        $this->assertResponseRedirects('/dashboard/lunchExpense');
        $this->assertSame(1, $this->countFor(LunchExpense::class, $this->ownCompany));
        $this->assertSame(1, $this->countFor(Transaction::class, $this->ownCompany));
        $this->assertSame(0, $this->countFor(LunchExpense::class, $this->foreignCompany));

        $this->submitMealExpense($this->id($this->secondCompany), '2026-11-22', '7.20');
        $this->assertResponseRedirects('/dashboard/lunchExpense');
        $this->assertSame(1, $this->countFor(LunchExpense::class, $this->secondCompany));
        $this->assertSame(1, $this->countFor(Transaction::class, $this->secondCompany));
        $this->assertSame(0, $this->countFor(Transaction::class, $this->foreignCompany));
    }

    public function testTravelExpenseFormCannotPostAMealOntoAnotherCompanysBooks(): void
    {
        $this->client->loginUser($this->member);
        $before = $this->ledgerCounts();

        $crawler = $this->client->request('GET', '/dashboard/travelExpense/new');
        $this->assertResponseIsSuccessful();
        $offered = $this->optionValues($crawler, 'travel_expense[organization]');

        $token = $crawler->filter('input[name="travel_expense[_token]"]')->attr('value');
        $this->client->request('POST', '/dashboard/travelExpense/new', [
            'travel_expense' => [
                'organization' => $this->id($this->foreignCompany),
                'date' => $this->isolatedDay(),
                'reason' => 'site visit',
                'advance' => '0',
                'travelStopCommands' => [
                    ['stopOrder' => '0', 'post' => $this->id($this->post), 'distanceFromPrevious' => '0'],
                    ['stopOrder' => '1', 'post' => $this->id($this->post), 'distanceFromPrevious' => '12'],
                ],
                '_token' => $token,
            ],
        ]);

        $this->assertSame(
            $before,
            $this->ledgerCounts(),
            'Submitting a travel expense for another company created or removed a meal expense or journal entry.'
        );
        $this->assertNotContains(
            $this->id($this->foreignCompany),
            $offered,
            'The travel-expense company field listed a company the signed-in user does not belong to.'
        );
        $response = $this->client->getResponse();
        if ($response->isRedirect()) {
            $path = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
            $this->assertNotSame('/dashboard/travelExpense', $path);
        }
    }

    public function testClonedTravelExpenseCannotPostAMealOntoAnotherCompanysBooks(): void
    {
        $this->client->loginUser($this->member);
        $source = $this->seedTravelExpense($this->ownCompany, $this->isolatedDay());
        $before = $this->ledgerCounts();
        $day = $this->isolatedDay();

        $crawler = $this->client->request('GET', '/dashboard/travelExpense/'.$this->id($source).'/clone');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="travel_expense[_token]"]')->attr('value');
        $this->client->request('POST', '/dashboard/travelExpense/'.$this->id($source).'/clone', [
            'travel_expense' => [
                'organization' => $this->id($this->foreignCompany),
                'date' => $day,
                'reason' => 'cloned visit',
                'advance' => '0',
                'travelStopCommands' => [
                    ['stopOrder' => '0', 'post' => $this->id($this->post), 'distanceFromPrevious' => '0'],
                    ['stopOrder' => '1', 'post' => $this->id($this->post), 'distanceFromPrevious' => '8'],
                ],
                '_token' => $token,
            ],
        ]);

        $this->assertSame(
            $before,
            $this->ledgerCounts(),
            'Cloning a travel expense onto another company created or removed a meal expense or journal entry.'
        );
    }

    public function testBookingMealsCannotPostAnotherCompanysJournal(): void
    {
        $this->client->loginUser($this->member);
        $day = $this->isolatedDay();
        $this->seedMealExpense($this->foreignCompany, $day);
        $before = $this->ledgerCounts();
        $from = (new \DateTimeImmutable($day.' 00:00:00'))->getTimestamp();
        $to = (new \DateTimeImmutable($day.' 23:59:59'))->getTimestamp();

        $this->client->request('POST', '/dashboard/lunchExpense/bookInBundle/withFilter', [
            'dateFrom' => (string) $from,
            'dateTo' => (string) $to,
            'date' => $day,
        ]);

        $this->assertSame(
            $before,
            $this->ledgerCounts(),
            'Booking meal expenses posted a journal entry for a company the signed-in user does not belong to.'
        );
    }

    private function submitMealExpense(string $organizationId, string $date, string $sum): void
    {
        $crawler = $this->client->request('GET', '/dashboard/lunchExpense/new');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="lunch_expense[_token]"]')->attr('value');

        $this->client->request('POST', '/dashboard/lunchExpense/new', [
            'lunch_expense' => [
                'organization' => $organizationId,
                'date' => $date,
                'sum' => $sum,
                '_token' => $token,
            ],
        ]);
    }

    private function assertMealExpenseWasRefused(): void
    {
        $response = $this->client->getResponse();
        if (!$response->isRedirect()) {
            $this->assertContains($response->getStatusCode(), [200, 403, 422]);

            return;
        }

        $path = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
        $this->assertNotSame('/dashboard/lunchExpense', $path);
    }

    /**
     * @return array<string, int>
     */
    private function ledgerCounts(): array
    {
        return [
            'own-meals' => $this->countFor(LunchExpense::class, $this->ownCompany),
            'own-journals' => $this->countFor(Transaction::class, $this->ownCompany),
            'second-meals' => $this->countFor(LunchExpense::class, $this->secondCompany),
            'second-journals' => $this->countFor(Transaction::class, $this->secondCompany),
            'foreign-meals' => $this->countFor(LunchExpense::class, $this->foreignCompany),
            'foreign-journals' => $this->countFor(Transaction::class, $this->foreignCompany),
        ];
    }

    private function countFor(string $class, Organization $organization): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return (int) $em->createQuery(sprintf('SELECT COUNT(e.id) FROM %s e WHERE IDENTITY(e.organization) = :org', $class))
            ->setParameter('org', $this->id($organization))
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    private function optionValues(\Symfony\Component\DomCrawler\Crawler $crawler, string $field): array
    {
        return $crawler->filter('select[name="'.$field.'"] option')->each(
            static fn (\Symfony\Component\DomCrawler\Crawler $node): string => (string) $node->attr('value')
        );
    }

    private function loadCompanies(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = $this->bootstrapUser('lunch-admin-'.$suffix, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $memberCommand = new CreateUserCommand();
        $memberCommand->username = 'lunch-member-'.$suffix;
        $memberCommand->firstName = 'Meal';
        $memberCommand->lastName = 'Member';
        $memberCommand->email = 'lunch-member-'.$suffix.'@example.test';
        $memberCommand->password = self::PLACEHOLDER_PASSWORD;
        $memberCommand->isRoleAdmin = false;
        $memberCommand->signatureFilename = '';
        $this->member = $admin->createUser($memberCommand, $hasher);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Testland '.$suffix;
        $countryCommand->nameInt = 'Testland';
        $countryCommand->A2 = 'T';
        $countryCommand->A3 = 'TST';
        $countryCommand->N3 = random_int(100, 999);
        $country = $admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = substr($suffix, 0, 4);
        $postCommand->codeInternational = 'TL-'.$suffix;
        $postCommand->name = 'Test City';
        $this->post = $country->createPost($postCommand, $admin);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = '1 Test Street';
        $addressCommand->line2 = '';
        $address = $this->post->createAddress($addressCommand, $admin);

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = random_int(1, 9);
        $classCommand->name = 'Class '.$suffix;
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = random_int(10, 99);
        $categoryCommand->name = 'Category '.$suffix;
        $categoryCommand->description = '';
        $category = $class->createCategory($categoryCommand, $admin);

        $debit = $this->konto($category, $admin, 'Meal debit '.$suffix, KontoTypes::active, 1);
        $credit = $this->konto($category, $admin, 'Meal credit '.$suffix, KontoTypes::passive, 2);

        $this->ownCompany = $this->company($admin, $address, 'own-'.$suffix, 'Own Company '.$suffix);
        $this->secondCompany = $this->company($admin, $address, 'second-'.$suffix, 'Second Company '.$suffix);
        $this->foreignCompany = $this->company($admin, $address, 'foreign-'.$suffix, 'Foreign Company '.$suffix);

        foreach ([$this->ownCompany, $this->secondCompany, $this->foreignCompany] as $company) {
            $this->enableMealAccounts($company, $admin, $debit, $credit);
        }

        $this->member->addOrganization($this->ownCompany, $admin);
        $this->member->addOrganization($this->secondCompany, $admin);

        $this->em->persist($this->member);
        $this->em->persist($country);
        $this->em->persist($this->post);
        $this->em->persist($address);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);
        $this->em->persist($this->ownCompany);
        $this->em->persist($this->secondCompany);
        $this->em->persist($this->foreignCompany);
        $this->em->flush();
    }

    private function company(User $admin, \App\Entity\Geography\Address $address, string $code, string $name): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $code;
        $command->taxNumber = substr(md5($code), 0, 8);
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56000000000000000';
        $command->bic = 'LJBASI2X';

        return $admin->createOrganization($command);
    }

    private function enableMealAccounts(Organization $company, User $admin, Konto $debit, Konto $credit): void
    {
        $settings = new UpdateOrganizationSettingsCommand();
        $settings->autoCreateLunch = true;
        $settings->lunchValue = 6.12;
        $settings->travelExpenseRate = 0.21;
        $settings->autoCreatePerDiem = false;
        $settings->IncurredTravelExpenseDebit = $debit;
        $settings->IncurredTravelExpenseCredit = $credit;
        $settings->PaidTravelExpenseDebit = $debit;
        $settings->PaidTravelExpenseCredit = $credit;
        $company->updateOrganizationSettings($settings, $admin);
    }

    private function konto(\App\Entity\Konto\KontoCategory $category, User $admin, string $name, int $type, int $number): Konto
    {
        $command = new class extends CreateKontoCommand {
            public $type;
        };
        $command->name = $name;
        $command->number = $number;
        $command->description = '';
        $command->isActive = true;
        $command->type = $type;

        return $category->createKonto($command, $admin);
    }

    private function bootstrapUser(string $username, bool $admin): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Admin');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Administrator');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->set($user, 'password', $hasher->hashPassword($user, self::PLACEHOLDER_PASSWORD));

        return $user;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }

    private function id(object $entity): string
    {
        return $entity->getId()->toString();
    }

    private function isolatedDay(): string
    {
        return (new \DateTimeImmutable('2094-01-01'))->modify('+'.random_int(0, 3600).' days')->format('Y-m-d');
    }

    private function seedMealExpense(Organization $company, string $day): LunchExpense
    {
        $command = new CreateLunchExpenseCommand();
        $command->organization = $company;
        $command->date = new \DateTime($day);
        $command->sum = '4.00';
        $expense = $this->member->createLunchExpense($command);
        $transaction = $expense->setNew($this->member);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($expense);
        $em->persist($transaction);
        $em->flush();

        return $expense;
    }

    private function seedTravelExpense(Organization $company, string $day): TravelExpense
    {
        $command = new CreateTravelExpenseCommand();
        $command->organization = $company;
        $command->employee = $this->member;
        $command->date = new \DateTime($day);
        $command->reason = 'seed';
        $command->advance = 0;
        $expense = $this->member->createTravelExpense($command);
        foreach ([0, 1] as $order) {
            $stop = new CreateTravelStopCommand();
            $stop->post = $this->post;
            $stop->stopOrder = $order;
            $stop->distanceFromPrevious = $order === 0 ? 0 : 5;
            $expense->createTravelStop($stop);
        }
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($expense);
        foreach ($expense->getTravelStops() as $stop) {
            $em->persist($stop);
        }
        $em->flush();

        return $expense;
    }
}
