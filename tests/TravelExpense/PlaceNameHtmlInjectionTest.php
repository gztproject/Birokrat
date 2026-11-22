<?php

namespace App\Tests\TravelExpense;

use App\Entity\Geography\Country;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Geography\Post;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Place names are attacker-controlled text. Travel-stop create and remove print that
 * text into the response that the front controller sends as an HTML document.
 * These assertions fail while that text is emitted raw, and pass once it is escaped,
 * omitted, or no longer stored as markup.
 */
class PlaceNameHtmlInjectionTest extends WebTestCase
{
    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $attacker;
    private User $victim;
    private Organization $organization;
    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema();
        $this->seedAccounts();
    }

    public function testClonePageDoesNotSendThePlaceNameAsHtml(): void
    {
        $post = $this->storePlaceName(self::PAYLOAD, '1002', 'SI-1002');
        if (!$post instanceof Post || !str_contains((string) $post->getName(), '<')) {
            $this->assertPlaceMarkupWasRejected();

            return;
        }

        $claim = $this->persistClaim([$post]);
        $this->client->loginUser($this->victim);

        [$response, $body] = $this->requestDocument('GET', '/dashboard/travelExpense/'.$claim->getId()->toString().'/clone');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertHtmlDocument($response, $body);
        $this->assertStringContainsString('dashboard_travelExpense_edit', $body);
        $this->assertStringContainsString('/user/'.$this->victim->getId()->toString(), $body);
        $this->assertStringNotContainsString(self::PAYLOAD, $body);
    }

    public function testSavingANewClaimDoesNotSendThePlaceNameAsHtml(): void
    {
        $post = $this->storePlaceName(self::PAYLOAD, '1002', 'SI-1002');
        if (!$post instanceof Post || !str_contains((string) $post->getName(), '<')) {
            $this->assertPlaceMarkupWasRejected();

            return;
        }

        $this->client->loginUser($this->attacker);
        [$response, $body] = $this->submitTravelClaim([
            $this->stopFields($post, 1, '5'),
        ]);

        $this->assertActionRan($response, $body);
        $this->assertStringNotContainsString(self::PAYLOAD, $body);
    }

    public function testAddingAStopDoesNotSendThePlaceNameAsHtml(): void
    {
        $benign = $this->storePlaceName('Ljubljana', '1000', 'SI-1000');
        $post = $this->storePlaceName(self::PAYLOAD, '1002', 'SI-1002');
        if (!$benign instanceof Post || !$post instanceof Post || !str_contains((string) $post->getName(), '<')) {
            $this->assertPlaceMarkupWasRejected();

            return;
        }

        $claim = $this->persistClaim([$benign, $benign]);
        $this->client->loginUser($this->attacker);
        [$response, $body] = $this->submitTravelClaim([
            $this->stopFields($benign, 1, '0'),
            $this->stopFields($benign, 2, '10'),
            $this->stopFields($post, 3, '15'),
        ], '/dashboard/travelExpense/'.$claim->getId()->toString().'/edit');

        $this->assertActionRan($response, $body);
        $this->assertStringNotContainsString(self::PAYLOAD, $body);
    }

    public function testRemovingAStopDoesNotSendThePlaceNameAsHtml(): void
    {
        $benign = $this->storePlaceName('Ljubljana', '1000', 'SI-1000');
        $post = $this->storePlaceName(self::PAYLOAD, '1002', 'SI-1002');
        if (!$benign instanceof Post || !$post instanceof Post || !str_contains((string) $post->getName(), '<')) {
            $this->assertPlaceMarkupWasRejected();

            return;
        }

        $claim = $this->persistClaim([$benign, $benign, $post]);
        $this->client->loginUser($this->attacker);
        [$response, $body] = $this->submitTravelClaim([
            $this->stopFields($benign, 1, '0'),
            $this->stopFields($benign, 2, '10'),
        ], '/dashboard/travelExpense/'.$claim->getId()->toString().'/edit');

        $this->assertActionRan($response, $body);
        $this->assertStringNotContainsString(self::PAYLOAD, $body);
    }

    private function assertPlaceMarkupWasRejected(): void
    {
        $plain = $this->storePlaceName('PlainTown', '1003', 'SI-1003');
        $this->assertInstanceOf(Post::class, $plain, 'The place form could not be submitted, so the travel-claim path was never reached.');
        $this->assertSame('PlainTown', $plain->getName());

        $names = array_map(
            static fn (Post $post): string => (string) $post->getName(),
            $this->em->getRepository(Post::class)->findAll(),
        );
        $this->assertNotContains(self::PAYLOAD, $names);
    }

    private function assertHtmlDocument(Response $response, string $body): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [200, 500],
            'Expected the clone response to be an HTML document. Status '.$response->getStatusCode().' body starts: '.substr($body, 0, 400),
        );
        $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('<html', strtolower($body));
    }

    private function assertActionRan(Response $response, string $body): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [302, 500],
            'The travel-claim form was not accepted, so create/remove did not run. Status '
            .$response->getStatusCode().' body starts: '.substr($body, 0, 500),
        );
        $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
    }

    /**
     * Bytes the Symfony front controller sends: output emitted while the kernel
     * handles the request, then the response body. HttpKernelBrowser keeps only
     * the response body, so the two are joined here in that order.
     *
     * @param array<string, mixed> $parameters
     * @return array{0: Response, 1: string}
     */
    private function requestDocument(string $method, string $uri, array $parameters = []): array
    {
        $level = ob_get_level();
        $emitted = '';
        ob_start(static function (string $chunk) use (&$emitted): string {
            $emitted .= $chunk;

            return '';
        });

        try {
            $this->client->request($method, $uri, $parameters);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_flush();
            }
        }

        $response = $this->client->getResponse();

        return [$response, $emitted.$response->getContent()];
    }

    private function storePlaceName(string $name, string $code, string $codeInternational): ?Post
    {
        $this->client->loginUser($this->attacker);
        $crawler = $this->client->request('GET', '/dashboard/codesheets/country/'.$this->country->getId()->toString().'/show');
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('#post__token')->attr('value');
        $country = $crawler->filter('#post_country option[selected], #post_country option')->first()->attr('value');
        if ($crawler->filter('#post_country option[value="'.$this->country->getId()->toString().'"]')->count() > 0) {
            $country = $this->country->getId()->toString();
        }

        $this->client->request('POST', '/dashboard/codesheets/post/new', [
            'post' => [
                'name' => $name,
                'code' => $code,
                'codeInternational' => $codeInternational,
                'country' => $country,
                '_token' => $token,
            ],
        ]);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        if (!is_array($payload) || ($payload[0]['status'] ?? null) !== 'ok') {
            return null;
        }

        $id = $payload[0]['data']['post']['id'] ?? null;
        $post = is_string($id) ? $this->em->getRepository(Post::class)->find($id) : null;

        return $post instanceof Post ? $post : null;
    }

    /**
     * @param array<int, array<string, string>> $stops
     * @return array{0: Response, 1: string}
     */
    private function submitTravelClaim(array $stops, string $uri = '/dashboard/travelExpense/new'): array
    {
        $crawler = $this->client->request('GET', $uri);
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('#travel_expense__token')->attr('value');

        $fields = [
            'organization' => $this->organization->getId()->toString(),
            'date' => '2026-11-22',
            'reason' => 'site visit',
            'advance' => '0',
            '_token' => $token,
            'travelStopCommands' => $stops,
        ];
        if ($crawler->filter('#travel_expense_employee')->count() > 0) {
            $fields['employee'] = $this->attacker->getId()->toString();
        }
        if ($crawler->filter('#travel_expense_rate')->count() > 0) {
            $fields['rate'] = '0.370';
        }

        return $this->requestDocument('POST', $uri, ['travel_expense' => $fields]);
    }

    /**
     * @return array<string, string>
     */
    private function stopFields(Post $post, int $order, string $distance): array
    {
        return [
            'stopOrder' => (string) $order,
            'post' => $post->getId()->toString(),
            'distanceFromPrevious' => $distance,
        ];
    }

    /**
     * @param list<Post> $posts
     */
    private function persistClaim(array $posts): TravelExpense
    {
        $this->reloadFixtures();
        $posts = array_map(function (Post $post): Post {
            $managed = $this->em->find(Post::class, $post->getId());
            $this->assertInstanceOf(Post::class, $managed);

            return $managed;
        }, $posts);

        $command = new CreateTravelExpenseCommand();
        $command->date = new \DateTime('2026-11-22');
        $command->employee = $this->attacker;
        $command->organization = $this->organization;
        $command->rate = 0.37;
        $command->reason = 'site visit';
        $command->advance = 0;
        $command->number = 'PN-XSS-1';

        $claim = $this->attacker->createTravelExpense($command);
        $order = 1;
        // Creating stops prints the place name. That output belongs to this
        // fixture, not to the HTTP response under test, so it is discarded here.
        $level = ob_get_level();
        ob_start(static function (string $chunk): string {
            return '';
        });
        try {
            foreach ($posts as $post) {
                $stop = new CreateTravelStopCommand();
                $stop->post = $post;
                $stop->stopOrder = $order;
                $stop->distanceFromPrevious = 10 * $order;
                $claim->createTravelStop($stop);
                ++$order;
            }
        } finally {
            while (ob_get_level() > $level) {
                ob_end_flush();
            }
        }

        $this->em->persist($claim);
        foreach ($claim->getTravelStops() as $stop) {
            $this->em->persist($stop);
        }
        $this->em->flush();

        return $claim;
    }

    private function seedAccounts(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->attacker = $this->bootstrapUser('attacker', $hasher);
        $this->victim = $this->bootstrapUser('victim', $hasher);
        $this->em->persist($this->attacker);
        $this->em->persist($this->victim);
        $this->em->flush();

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $this->country = $this->attacker->createCountry($countryCommand);

        $safeCommand = new CreatePostCommand();
        $safeCommand->code = '1001';
        $safeCommand->codeInternational = 'SI-1001';
        $safeCommand->name = 'SafeTown';
        $safePost = $this->country->createPost($safeCommand, $this->attacker);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $safePost->createAddress($addressCommand, $this->attacker);

        $orgCommand = new CreateOrganizationCommand();
        $orgCommand->code = '1';
        $orgCommand->name = 'Issuer d.o.o.';
        $orgCommand->shortName = 'Issuer';
        $orgCommand->taxNumber = '12345678';
        $orgCommand->taxable = true;
        $orgCommand->address = $address;
        $orgCommand->accountNumber = 'SI56000000000000000';
        $orgCommand->bic = 'LJBASI2X';
        $this->organization = $this->attacker->createOrganization($orgCommand);

        $settings = new UpdateOrganizationSettingsCommand();
        $settings->travelExpenseRate = 0.37;
        $settings->autoCreateLunch = false;
        $settings->autoCreatePerDiem = false;
        $this->organization->updateOrganizationSettings($settings, $this->attacker);

        $this->attacker->addOrganization($this->organization, $this->attacker);
        $this->victim->addOrganization($this->organization, $this->attacker);

        $this->em->persist($this->country);
        $this->em->persist($safePost);
        $this->em->persist($address);
        $this->em->persist($this->organization);
        $this->em->persist($this->organization->getOrganizationSettings());
        $this->em->flush();
    }

    private function bootstrapUser(string $username, UserPasswordHasherInterface $hasher): User
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
        $this->set($user, 'position', 'Tester');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', $hasher->hashPassword($user, 'placeholder-not-a-secret'));

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

    private function reloadFixtures(): void
    {
        $this->em->clear();
        $attacker = $this->em->getRepository(User::class)->findOneBy(['username' => 'attacker']);
        $victim = $this->em->getRepository(User::class)->findOneBy(['username' => 'victim']);
        $organization = $this->em->getRepository(Organization::class)->findOneBy([]);
        $country = $this->em->getRepository(Country::class)->findOneBy([]);
        $this->assertInstanceOf(User::class, $attacker);
        $this->assertInstanceOf(User::class, $victim);
        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertInstanceOf(Country::class, $country);
        $this->attacker = $attacker;
        $this->victim = $victim;
        $this->organization = $organization;
        $this->country = $country;
    }

    private function resetSchema(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }
}
