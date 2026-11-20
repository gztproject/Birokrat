<?php

namespace App\Tests\Web;

use App\Entity\Geography\Address;
use App\Entity\Geography\Country;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Geography\Post;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\TravelExpense\Enumerators\States;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ordinary members may write travel claims and journal entries only for companies they belong to.
 * These tests fail while create, edit, clone, and bundle-booking accept another company's id.
 */
class TravelExpenseAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $ownOrganizationId;

    private string $foreignOrganizationId;

    private string $actorId;

    private string $postId;

    private string $marker;

    private static int $dayOffset = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $this->marker = $suffix;
        $actor = $this->bootstrapUser($hasher, 'member-'.$suffix);
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($actor);
        $em->flush();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $address = $this->address($actor);
        $em->persist($address->getPost()->getCountry());
        $em->persist($address->getPost());
        $em->persist($address);

        $debit = $this->konto($actor, 486, 'Travel debit '.$suffix);
        $credit = $this->konto($actor, 285, 'Travel credit '.$suffix);
        $em->persist($debit->getCategory()->getClass());
        $em->persist($debit->getCategory());
        $em->persist($debit);
        $em->persist($credit->getCategory()->getClass());
        $em->persist($credit->getCategory());
        $em->persist($credit);

        $own = $this->organization($actor, $address, 'Own '.$suffix, 'own-'.$suffix);
        $foreign = $this->organization($actor, $address, 'Foreign '.$suffix, 'foreign-'.$suffix);
        $this->configureTravelAccounts($own, $actor, $debit, $credit);
        $this->configureTravelAccounts($foreign, $actor, $debit, $credit);
        $actor->addOrganization($own, $actor);

        $em->persist($own);
        $em->persist($own->getOrganizationSettings());
        $em->persist($foreign);
        $em->persist($foreign->getOrganizationSettings());
        $em->flush();

        $this->actorId = $actor->getId()->toString();
        $this->ownOrganizationId = $own->getId()->toString();
        $this->foreignOrganizationId = $foreign->getId()->toString();
        $this->postId = $address->getPost()->getId()->toString();
        $this->client->loginUser($actor);
    }

    public function testCompanyFieldListsOnlyTheMemberCompany(): void
    {
        $crawler = $this->client->request('GET', '/dashboard/travelExpense/new');
        $this->assertResponseIsSuccessful();

        $values = $this->optionValues($crawler->filter('select[name="travel_expense[organization]"] option'));
        $this->assertContains(strtolower($this->ownOrganizationId), $values);
        $this->assertNotContains(strtolower($this->foreignOrganizationId), $values);
    }

    public function testMemberCanCreateAClaimForTheirOwnCompany(): void
    {
        $marker = 'own-'.$this->marker;
        $before = $this->transactionCount($this->ownOrganizationId);

        $this->postClaim('/dashboard/travelExpense/new', $this->tokenFromNewForm(), [
            'organization' => $this->ownOrganizationId,
            'date' => $this->uniqueDay()->format('Y-m-d'),
            'reason' => $marker,
            'advance' => '0',
        ]);

        $this->assertResponseRedirects('/dashboard/travelExpense');
        $claim = $this->claimByReason($marker);
        $this->assertInstanceOf(TravelExpense::class, $claim);
        $this->assertSame($this->ownOrganizationId, $claim->getOrganization()->getId()->toString());
        $this->assertSame($before + 1, $this->transactionCount($this->ownOrganizationId));
        $this->assertSame(0, $this->transactionCount($this->foreignOrganizationId));
    }

    public function testMemberCannotCreateAClaimForAnotherCompany(): void
    {
        $marker = 'foreign-create-'.$this->marker;
        $beforeOwn = $this->transactionCount($this->ownOrganizationId);
        $beforeForeign = $this->transactionCount($this->foreignOrganizationId);

        $this->postClaim('/dashboard/travelExpense/new', $this->tokenFromNewForm(), [
            'organization' => $this->foreignOrganizationId,
            'date' => $this->uniqueDay()->format('Y-m-d'),
            'reason' => $marker,
            'advance' => '12.50',
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertLessThan(500, $status, $this->responseSnippet());
        $this->assertTrue(null === $this->claimByReason($marker), 'Creating a claim stored another company. '.$this->responseSnippet());
        $this->assertSame($beforeForeign, $this->transactionCount($this->foreignOrganizationId));
        $this->assertSame($beforeOwn, $this->transactionCount($this->ownOrganizationId));
        $this->assertSame(0, $this->claimCount($this->foreignOrganizationId));
    }

    public function testMemberCannotEditAnotherCompanysClaim(): void
    {
        $day = $this->uniqueDay();
        $claim = $this->seedClaim($this->foreignOrganizationId, $day, 'seed-edit-'.$this->marker);
        $token = $this->tokenFromNewForm();

        $this->postClaim('/dashboard/travelExpense/'.$claim.'/edit', $token, [
            'organization' => $this->foreignOrganizationId,
            'date' => $day->format('Y-m-d'),
            'reason' => 'edited-by-outsider-'.$this->marker,
            'advance' => '9.00',
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [403, 404], $this->responseSnippet());
        $reloaded = $this->reloadClaim($claim);
        $this->assertSame('seed-edit-'.$this->marker, $reloaded->getReason());
        $this->assertSame(States::unbooked, $reloaded->getState());
        $this->assertSame($this->foreignOrganizationId, $reloaded->getOrganization()->getId()->toString());
        $journal = $this->journalFor($claim);
        $this->assertSame($this->foreignOrganizationId, $journal->getOrganization()->getId()->toString());
        $this->assertEquals(round(10 * 0.43, 2), $journal->getSum());
    }

    public function testMemberCannotCloneAnotherCompanysClaim(): void
    {
        $day = $this->uniqueDay();
        $claim = $this->seedClaim($this->foreignOrganizationId, $day, 'seed-clone-'.$this->marker);
        $claimsBefore = $this->claimCount($this->foreignOrganizationId);
        $transactionsBefore = $this->transactionCount($this->foreignOrganizationId);

        $this->postClaim('/dashboard/travelExpense/'.$claim.'/clone', $this->tokenFromNewForm(), [
            'organization' => $this->foreignOrganizationId,
            'date' => $day->format('Y-m-d'),
            'reason' => 'cloned-by-outsider-'.$this->marker,
            'advance' => '0',
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [403, 404], $this->responseSnippet());
        $this->assertSame($claimsBefore, $this->claimCount($this->foreignOrganizationId));
        $this->assertSame($transactionsBefore, $this->transactionCount($this->foreignOrganizationId));
        $this->assertTrue(null === $this->claimByReason('cloned-by-outsider-'.$this->marker));
        $this->assertSame('seed-clone-'.$this->marker, $this->reloadClaim($claim)->getReason());
    }

    public function testPostedCompanyOnAnOwnedClaimStaysTheAuthorizedCompany(): void
    {
        $day = $this->uniqueDay();
        $claim = $this->seedClaim($this->ownOrganizationId, $day, 'seed-owned-'.$this->marker);
        $foreignTransactions = $this->transactionCount($this->foreignOrganizationId);

        $this->postClaim('/dashboard/travelExpense/'.$claim.'/edit', $this->tokenFromNewForm(), [
            'organization' => $this->foreignOrganizationId,
            'date' => $day->format('Y-m-d'),
            'reason' => 'moved-company-'.$this->marker,
            'advance' => '4.00',
        ]);

        $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $this->responseSnippet());
        $reloaded = $this->reloadClaim($claim);
        $this->assertSame($this->ownOrganizationId, $reloaded->getOrganization()->getId()->toString());
        $journal = $this->journalFor($claim);
        $this->assertSame($this->ownOrganizationId, $journal->getOrganization()->getId()->toString());
        $this->assertSame($foreignTransactions, $this->transactionCount($this->foreignOrganizationId));
        $this->assertSame(0, $this->claimCount($this->foreignOrganizationId));
    }

    public function testCloneOfAnOwnedClaimIgnoresAForeignCompany(): void
    {
        $day = $this->uniqueDay();
        $claim = $this->seedClaim($this->ownOrganizationId, $day, 'seed-owned-clone-'.$this->marker);
        $ownClaims = $this->claimCount($this->ownOrganizationId);
        $foreignClaims = $this->claimCount($this->foreignOrganizationId);
        $foreignTransactions = $this->transactionCount($this->foreignOrganizationId);

        $this->postClaim('/dashboard/travelExpense/'.$claim.'/clone', $this->tokenFromNewForm(), [
            'organization' => $this->foreignOrganizationId,
            'date' => $day->format('Y-m-d'),
            'reason' => 'clone-retargeted-'.$this->marker,
            'advance' => '0',
        ]);

        $this->assertLessThan(500, $this->client->getResponse()->getStatusCode(), $this->responseSnippet());
        $this->assertSame($foreignClaims, $this->claimCount($this->foreignOrganizationId));
        $this->assertSame($foreignTransactions, $this->transactionCount($this->foreignOrganizationId));
        $clone = $this->claimByReason('clone-retargeted-'.$this->marker);
        if ($clone instanceof TravelExpense) {
            $this->assertSame($this->ownOrganizationId, $clone->getOrganization()->getId()->toString());
            $this->assertSame($ownClaims + 1, $this->claimCount($this->ownOrganizationId));
        }
    }

    public function testMemberCannotBookAnotherCompanysClaim(): void
    {
        $day = $this->uniqueDay();
        $claim = $this->seedClaim($this->foreignOrganizationId, $day, 'seed-book-'.$this->marker);
        $transactionsBefore = $this->transactionCount($this->foreignOrganizationId);
        $from = $day->getTimestamp();
        $to = $day->modify('+1 day')->getTimestamp() - 1;

        $this->client->request('POST', '/dashboard/travelExpense/bookInBundle/withFilter', [
            'dateFrom' => (string) $from,
            'dateTo' => (string) $to,
            'date' => $day->format('Y-m-d'),
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertLessThan(500, $status, $this->responseSnippet());
        $reloaded = $this->reloadClaim($claim);
        $this->assertSame(States::unbooked, $reloaded->getState(), $this->responseSnippet());
        $this->assertNull($reloaded->getTravelExpenseBundle());
        $this->assertSame($transactionsBefore, $this->transactionCount($this->foreignOrganizationId));
    }

    private function postClaim(string $path, string $token, array $fields): void
    {
        $fields['_token'] = $token;
        $fields['travelStopCommands'] = [
            [
                'stopOrder' => '0',
                'post' => $this->postId,
                'distanceFromPrevious' => '0',
            ],
            [
                'stopOrder' => '1',
                'post' => $this->postId,
                'distanceFromPrevious' => '10',
            ],
        ];
        $this->client->request('POST', $path, [
            'travel_expense' => $fields,
        ]);
    }

    private function tokenFromNewForm(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/travelExpense/new');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="travel_expense[_token]"]');
        $this->assertGreaterThan(0, $token->count(), 'The new-claim form did not render a CSRF token.');

        return (string) $token->attr('value');
    }

    private function seedClaim(string $organizationId, \DateTimeImmutable $day, string $reason): string
    {
        $em = $this->em();
        $actor = $em->find(User::class, $this->actorId);
        $organization = $em->find(Organization::class, $organizationId);
        $this->assertInstanceOf(User::class, $actor);
        $this->assertInstanceOf(Organization::class, $organization);

        $command = new CreateTravelExpenseCommand();
        $command->date = \DateTime::createFromImmutable($day);
        $command->organization = $organization;
        $command->employee = $actor;
        $command->reason = $reason;
        $command->advance = 0;
        $claim = $actor->createTravelExpense($command);
        $post = $em->find(Post::class, $this->postId);
        $this->assertInstanceOf(Post::class, $post);
        foreach ([[0, 0], [1, 10]] as [$order, $distance]) {
            $stop = new CreateTravelStopCommand();
            $stop->post = $post;
            $stop->stopOrder = $order;
            $stop->distanceFromPrevious = $distance;
            $em->persist($claim->createTravelStop($stop));
        }
        $journal = $claim->setNew($actor);
        $em->persist($claim);
        $em->persist($journal);
        $em->flush();

        return $claim->getId()->toString();
    }

    private function reloadClaim(string $id): TravelExpense
    {
        $claim = $this->em()->find(TravelExpense::class, $id);
        $this->assertInstanceOf(TravelExpense::class, $claim);

        return $claim;
    }

    private function journalFor(string $claimId): Transaction
    {
        $claim = $this->reloadClaim($claimId);
        $journal = $this->em()->getRepository(Transaction::class)->findOneBy(['travelExpense' => $claim]);
        $this->assertInstanceOf(Transaction::class, $journal);

        return $journal;
    }

    private function claimByReason(string $reason): ?TravelExpense
    {
        return $this->em()->getRepository(TravelExpense::class)->findOneBy(['reason' => $reason]);
    }

    private function claimCount(string $organizationId): int
    {
        return (int) $this->em()->createQuery(
            'SELECT COUNT(claim.id) FROM '.TravelExpense::class.' claim WHERE claim.organization = :organization'
        )->setParameter('organization', $this->em()->find(Organization::class, $organizationId))->getSingleScalarResult();
    }

    private function transactionCount(string $organizationId): int
    {
        return (int) $this->em()->createQuery(
            'SELECT COUNT(journal.id) FROM '.Transaction::class.' journal WHERE journal.organization = :organization'
        )->setParameter('organization', $this->em()->find(Organization::class, $organizationId))->getSingleScalarResult();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function responseSnippet(): string
    {
        $response = $this->client->getResponse();

        return 'HTTP '.$response->getStatusCode().' '.substr((string) $response->getContent(), 0, 500);
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler $options
     * @return list<string>
     */
    private function optionValues($options): array
    {
        $values = [];
        foreach ($options as $option) {
            $values[] = strtolower(trim($option->getAttribute('value')));
        }

        return $values;
    }

    private function uniqueDay(): \DateTimeImmutable
    {
        $day = (new \DateTimeImmutable('2040-01-01'))->modify('+'.self::$dayOffset.' days');
        ++self::$dayOffset;

        return $day->setTime(0, 0);
    }

    private function bootstrapUser(UserPasswordHasherInterface $hasher, string $username): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Member');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Clerk');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', '');
        $this->set($user, 'password', $hasher->hashPassword($user, 'a1!'.bin2hex(random_bytes(6))));

        return $user;
    }

    private function address(User $user): Address
    {
        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = new Country($countryCommand, $user);

        $postCommand = new CreatePostCommand();
        $postCommand->code = (string) random_int(1000, 9999);
        $postCommand->codeInternational = 'SI-'.$postCommand->code;
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $user);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';

        return $post->createAddress($addressCommand, $user);
    }

    private function organization(User $user, Address $address, string $name, string $code): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = (string) random_int(10000000, 99999999);
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56000000000000000';
        $command->bic = 'LJBASI2X';

        return $user->createOrganization($command);
    }

    private function configureTravelAccounts(Organization $organization, User $user, Konto $debit, Konto $credit): void
    {
        $settings = new UpdateOrganizationSettingsCommand();
        $settings->travelExpenseRate = 0.43;
        $settings->autoCreateLunch = false;
        $settings->autoCreatePerDiem = false;
        $settings->perDiemValue = 0;
        $settings->lunchValue = 0;
        $settings->defaultPaymentDueIn = 15;
        $settings->invoicePrefix = 'T';
        $settings->referenceModel = '00';
        $settings->IncurredTravelExpenseDebit = $debit;
        $settings->IncurredTravelExpenseCredit = $credit;
        $settings->PaidTravelExpenseDebit = $debit;
        $settings->PaidTravelExpenseCredit = $credit;
        $organization->updateOrganizationSettings($settings, $user);
    }

    private function konto(User $user, int $number, string $name): Konto
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = intdiv($number, 100);
        $classCommand->name = 'Class '.$name;
        $classCommand->description = $name;
        $class = new KontoClass($classCommand, $user);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = intdiv($number, 10);
        $categoryCommand->name = 'Category '.$name;
        $categoryCommand->description = $name;
        $category = new KontoCategory($categoryCommand, $class, $user);

        $kontoCommand = new class extends CreateKontoCommand {
            public $type;
        };
        $kontoCommand->number = $number;
        $kontoCommand->name = $name;
        $kontoCommand->description = $name;
        $kontoCommand->isActive = true;
        $kontoCommand->type = 0;

        return new Konto($kontoCommand, $category, $user);
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
}
