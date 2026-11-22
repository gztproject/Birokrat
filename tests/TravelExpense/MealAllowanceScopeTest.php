<?php

namespace App\Tests\TravelExpense;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoClass;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\User\User;
use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Meal allowances posted while saving a travel claim must follow only that
 * claim's company and that calendar day.
 *
 * These assertions fail while the daily meal lookup ignores the company.
 */
final class MealAllowanceScopeTest extends WebTestCase
{
    private const LUNCH_A = 4.0;
    private const LUNCH_B = 9.5;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $this->client->followRedirects(false);
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

        $this->admin = $fixtures['admin'];
        $this->user = $fixtures['user'];
        self::assertNotContains('ROLE_ADMIN', $this->user->getRoles());
        $this->client->loginUser($this->user);
    }

    public function testNewClaimPostsAllowanceForCompanyThatHasNoneEvenWhenAnotherCompanyHasOne(): void
    {
        $day = $this->unusedDay();
        $books = $this->openBooks($day);
        $this->recordMealClaim($books['companyA'], $day, self::LUNCH_A);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyA'], $day, 1, self::LUNCH_A);
        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);
    }

    public function testClonedClaimPostsAllowanceForCompanyThatHasNoneEvenWhenAnotherCompanyHasOne(): void
    {
        $day = $this->unusedDay();
        $sourceDay = $this->unusedDay();
        $books = $this->openBooks($day);
        $this->recordMealClaim($books['companyA'], $day, self::LUNCH_A);
        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $sourceDay);
        $sourceId = $this->travelClaimId($books['companyB'], $sourceDay);

        $this->saveClonedTravelClaim($sourceId, $books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyA'], $day, 1, self::LUNCH_A);
        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);

        $this->saveClonedTravelClaim($sourceId, $books['companyB'], $books['postId'], $day);

        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);
    }

    public function testNewClaimDoesNotPostASecondAllowanceWhenThatCompanyAlreadyHasOne(): void
    {
        $day = $this->unusedDay();
        $books = $this->openBooks($day);
        $this->recordMealClaim($books['companyB'], $day, self::LUNCH_B);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);
        $this->assertMealAllowance($books['companyA'], $day, 0, self::LUNCH_A);
    }

    public function testNewClaimPostsAllowanceWhenNoCompanyHasAMealThatDay(): void
    {
        $day = $this->unusedDay();
        $books = $this->openBooks($day);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);
        $this->assertMealAllowance($books['companyA'], $day, 0, self::LUNCH_A);
    }

    public function testNewClaimOmitsAllowanceWhenAutomaticMealAllowancesAreOff(): void
    {
        $day = $this->unusedDay();
        $books = $this->openBooks($day, false);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyB'], $day, 0, self::LUNCH_B);
    }

    public function testNewClaimPostsAllowanceWhenThatCompanysOnlyMealIsOnAnotherDay(): void
    {
        $day = $this->unusedDay();
        $otherDay = $this->unusedDay();
        $books = $this->openBooks($day);
        $this->recordMealClaim($books['companyB'], $otherDay, self::LUNCH_B);

        $this->saveNewTravelClaim($books['companyB'], $books['postId'], $day);

        $this->assertTravelClaimSaved($books['companyB'], $day);
        $this->assertMealAllowance($books['companyB'], $day, 1, self::LUNCH_B);
        $this->assertMealAllowance($books['companyB'], $otherDay, 1, self::LUNCH_B);
    }

    /**
     * @return array{companyA: Organization, companyB: Organization, postId: string}
     */
    private function openBooks(string $day, bool $autoLunchForB = true): array
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $debit = $this->konto($suffix, 1, 'Travel debit');
        $credit = $this->konto($suffix, 2, 'Travel credit');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Allowance '.$suffix;
        $countryCommand->nameInt = 'Allowance '.$suffix;
        $countryCommand->A2 = 'QZ';
        $countryCommand->A3 = 'QZA';
        $countryCommand->N3 = 900;
        $country = $this->admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = substr($suffix, 0, 6);
        $postCommand->codeInternational = 'QZ-'.$suffix;
        $postCommand->name = 'Post '.$suffix;
        $post = $country->createPost($postCommand, $this->admin);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Street 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $this->admin);

        $companyA = $this->company('A'.$suffix, 'Company A '.$suffix, $address);
        $companyB = $this->company('B'.$suffix, 'Company B '.$suffix, $address);
        $this->enableMeals($companyA, $debit, $credit, true, self::LUNCH_A);
        $this->enableMeals($companyB, $debit, $credit, $autoLunchForB, self::LUNCH_B);

        $this->em->persist($debit->getCategory()->getClass());
        $this->em->persist($debit->getCategory());
        $this->em->persist($debit);
        $this->em->persist($credit->getCategory()->getClass());
        $this->em->persist($credit->getCategory());
        $this->em->persist($credit);
        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($companyA);
        $this->em->persist($companyA->getOrganizationSettings());
        $this->em->persist($companyB);
        $this->em->persist($companyB->getOrganizationSettings());
        $this->em->flush();

        return [
            'companyA' => $companyA,
            'companyB' => $companyB,
            'postId' => $post->getId()->toString(),
        ];
    }

    private function company(string $code, string $name, object $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = (string) random_int(10000000, 99999999);
        $command->taxable = true;
        $command->address = $address;

        return $this->admin->createOrganization($command);
    }

    private function enableMeals(Organization $organization, Konto $debit, Konto $credit, bool $auto, float $amount): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $command->travelExpenseRate = 0.43;
        $command->autoCreateLunch = $auto;
        $command->lunchValue = $amount;
        $command->IncurredTravelExpenseDebit = $debit;
        $command->IncurredTravelExpenseCredit = $credit;
        $organization->updateOrganizationSettings($command, $this->admin);
    }

    private function konto(string $suffix, int $number, string $name): Konto
    {
        $classCommand = new class extends CreateKontoClassCommand {
        };
        $classCommand->number = 4;
        $classCommand->name = 'Class '.$suffix;
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $this->admin);

        $categoryCommand = new class extends CreateKontoCategoryCommand {
        };
        $categoryCommand->number = 80 + $number;
        $categoryCommand->name = 'Category '.$suffix.' '.$number;
        $categoryCommand->description = '';
        $category = $class->createCategory($categoryCommand, $this->admin);

        $kontoCommand = new class extends CreateKontoCommand {
            public int $type;
        };
        $kontoCommand->number = $number;
        $kontoCommand->name = $name.' '.$suffix;
        $kontoCommand->description = '';
        $kontoCommand->isActive = true;
        $kontoCommand->type = KontoTypes::active;

        return $category->createKonto($kontoCommand, $this->admin);
    }

    private function recordMealClaim(Organization $organization, string $day, float $amount): void
    {
        $this->submit('GET', '/dashboard/lunchExpense/new', 'meal claim form');
        $token = $this->csrfToken('lunch_expense');
        $this->client->request('POST', '/dashboard/lunchExpense/new', [
            'lunch_expense' => [
                '_token' => $token,
                'organization' => $organization->getId()->toString(),
                'date' => $day,
                'sum' => (string) $amount,
            ],
        ]);
        $this->assertSaved('Recording a meal claim');
    }

    private function saveNewTravelClaim(Organization $organization, string $postId, string $day): void
    {
        $this->submit('GET', '/dashboard/travelExpense/new', 'new travel claim form');
        $token = $this->csrfToken('travel_expense');
        $this->client->request('POST', '/dashboard/travelExpense/new', [
            'travel_expense' => $this->travelPayload($token, $organization->getId()->toString(), $postId, $day),
        ]);
        $this->assertSaved('Saving a new travel claim');
    }

    private function saveClonedTravelClaim(string $sourceId, Organization $organization, string $postId, string $day): void
    {
        $this->client->request('GET', '/dashboard/travelExpense/'.$sourceId.'/clone');
        self::assertResponseIsSuccessful('Opening the clone form failed: '.$this->responseText());
        $token = $this->csrfToken('travel_expense');
        $this->client->request('POST', '/dashboard/travelExpense/'.$sourceId.'/clone', [
            'travel_expense' => $this->travelPayload($token, $organization->getId()->toString(), $postId, $day),
        ]);
        $this->assertSaved('Saving a cloned travel claim');
    }

    /**
     * @return array<string, mixed>
     */
    private function travelPayload(string $token, string $organizationId, string $postId, string $day): array
    {
        return [
            '_token' => $token,
            'organization' => $organizationId,
            'date' => $day,
            'reason' => 'Customer visit',
            'advance' => '0',
            'travelStopCommands' => [
                ['stopOrder' => '0', 'post' => $postId, 'distanceFromPrevious' => '0'],
                ['stopOrder' => '1', 'post' => $postId, 'distanceFromPrevious' => '10'],
            ],
        ];
    }

    private function submit(string $method, string $uri, string $what): void
    {
        $this->client->request($method, $uri);
        self::assertResponseIsSuccessful($what.' failed: '.$this->responseText());
    }

    private function csrfToken(string $formName): string
    {
        $token = $this->client->getCrawler()->filter('input[name="'.$formName.'[_token]"]');
        self::assertGreaterThan(0, $token->count(), 'Missing CSRF token for '.$formName);

        return (string) $token->attr('value');
    }

    private function assertSaved(string $action): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $errors = '';
        $crawler = $this->client->getCrawler();
        if ($crawler->count() > 0) {
            $nodes = $crawler->filter('.invalid-feedback, .form-error-message, .alert');
            if ($nodes->count() > 0) {
                $errors = ' Form errors: '.implode(' | ', $nodes->each(static fn ($node): string => trim($node->text())));
            }
        }
        self::assertContains($status, [301, 302, 303], $action.' returned HTTP '.$status.'.'.$errors.' '.$this->responseText());
    }

    private function assertTravelClaimSaved(Organization $organization, string $day): void
    {
        self::assertGreaterThan(
            0,
            count($this->claimsOn($organization->getId()->toString(), $day)),
            'The travel claim itself was not saved for '.$day.'.',
        );
    }

    private function assertMealAllowance(Organization $organization, string $day, int $expected, float $amount): void
    {
        $organizationId = $organization->getId()->toString();
        $meals = $this->mealsOn($organizationId, $day);
        $journals = $this->mealJournalsOn($organizationId, $day);
        self::assertCount(
            $expected,
            $meals,
            'Meal claims for '.$organization->getName().' on '.$day.' should be '.$expected.'.',
        );
        self::assertCount(
            $expected,
            $journals,
            'Meal-allowance journal lines for '.$organization->getName().' on '.$day.' should be '.$expected.'.',
        );
        if ($expected === 0) {
            return;
        }

        foreach ($meals as $meal) {
            self::assertEqualsWithDelta($amount, $meal->getSum(), 0.001);
            self::assertSame($organizationId, $meal->getOrganization()->getId()->toString());
        }
        foreach ($journals as $journal) {
            self::assertEqualsWithDelta($amount, (float) $journal->getSum(), 0.001);
            self::assertSame($organizationId, $journal->getOrganization()->getId()->toString());
            $meal = $journal->getLunchExpense();
            self::assertInstanceOf(LunchExpense::class, $meal);
            self::assertSame($organizationId, $meal->getOrganization()->getId()->toString());
            self::assertSame($day, $meal->getDate()->format('Y-m-d'));
            $settings = $journal->getOrganization()->getOrganizationSettings();
            self::assertSame(
                $settings->getIncurredTravelExpenseDebit()->getId()->toString(),
                $journal->getDebitKonto()->getId()->toString(),
            );
            self::assertSame(
                $settings->getIncurredTravelExpenseCredit()->getId()->toString(),
                $journal->getCreditKonto()->getId()->toString(),
            );
        }
    }

    private function travelClaimId(Organization $organization, string $day): string
    {
        $claims = $this->claimsOn($organization->getId()->toString(), $day);
        self::assertNotEmpty($claims, 'No travel claim was stored for '.$day.'.');

        return $claims[0]->getId()->toString();
    }

    /**
     * @return list<TravelExpense>
     */
    private function claimsOn(string $organizationId, string $day): array
    {
        $this->em->clear();

        return array_values(array_filter(
            $this->em->getRepository(TravelExpense::class)->findAll(),
            fn (TravelExpense $claim): bool => $claim->getOrganization()->getId()->toString() === $organizationId
                && $claim->getDate()->format('Y-m-d') === $day,
        ));
    }

    /**
     * @return list<LunchExpense>
     */
    private function mealsOn(string $organizationId, string $day): array
    {
        $this->em->clear();

        return array_values(array_filter(
            $this->em->getRepository(LunchExpense::class)->findAll(),
            fn (LunchExpense $meal): bool => $meal->getOrganization()->getId()->toString() === $organizationId
                && $meal->getDate()->format('Y-m-d') === $day,
        ));
    }

    /**
     * @return list<Transaction>
     */
    private function mealJournalsOn(string $organizationId, string $day): array
    {
        $this->em->clear();

        return array_values(array_filter(
            $this->em->getRepository(Transaction::class)->findAll(),
            function (Transaction $journal) use ($organizationId, $day): bool {
                $meal = $journal->getLunchExpense();

                return $meal instanceof LunchExpense
                    && $journal->getOrganization()->getId()->toString() === $organizationId
                    && $meal->getDate()->format('Y-m-d') === $day;
            },
        ));
    }

    private function unusedDay(): string
    {
        for ($attempt = 0; $attempt < 40; ++$attempt) {
            $day = (new \DateTimeImmutable('1992-01-01'))->modify('+'.random_int(0, 8000).' days')->format('Y-m-d');
            $count = (int) $this->em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM lunch_expense WHERE date = ?',
                [$day],
            );
            if ($count === 0) {
                return $day;
            }
        }

        self::fail('Could not find a calendar day with no meal claims.');
    }

    private function responseText(): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->client->getResponse()->getContent())) ?? '');

        return substr($text, 0, 1500);
    }
}
