<?php

namespace App\Tests\Web;

use App\Entity\Geography\Country;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\CreateTravelExpenseBundleCommand;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\TravelExpense\Enumerators\States;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Authorization spec for travel-expense booking, listing, detail, PDF, edit, clone, and bundles.
 * These tests fail while any signed-in user can act on another company's claims.
 */
class TravelExpenseAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $this->em()->getConnection()->executeQuery('SELECT 1');
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testAnonymousBookingDoesNotPostJournals(): void
    {
        $world = $this->seed('2031-03-03');

        $this->client->request('POST', '/dashboard/travelExpense/bookInBundle/withFilter', $this->bookingBody($world, 'wide'));

        $this->assertResponseRedirects();
        $this->assertSame(States::unbooked, $this->reloadClaim($world['foreignId'])->getState());
        $this->assertSame(States::unbooked, $this->reloadClaim($world['ownId'])->getState());
        $this->assertSame(0, $this->bundleTransactionCount($world['foreignOrgId']));
        $this->assertSame(0, $this->bundleTransactionCount($world['ownOrgId']));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function bookingPayloads(): iterable
    {
        yield 'wide date range and no company id' => ['wide'];
        yield 'submitted company id the user does not belong to' => ['foreign-id'];
    }

    #[DataProvider('bookingPayloads')]
    public function testSignedInOutsiderCannotBookAnotherCompany(string $mode): void
    {
        $world = $this->seed('2500-06-15');
        $this->client->loginUser($this->user($world['attackerId']));

        $this->client->request('POST', '/dashboard/travelExpense/bookInBundle/withFilter', $this->bookingBody($world, $mode));

        $hint = $this->responseHint();
        $own = $this->reloadClaim($world['ownId']);
        $foreign = $this->reloadClaim($world['foreignId']);

        $this->assertSame(
            States::booked,
            $own->getState(),
            'A member must still be able to book their own company. '.$hint
        );
        $this->assertNotNull($own->getTravelExpenseBundle(), $hint);
        $ownJournal = $this->bundleTransaction($own->getTravelExpenseBundle());
        $this->assertNotNull($ownJournal, $hint);
        $this->assertSame($world['ownOrgId'], $ownJournal->getOrganization()->getId()->toString(), $hint);
        $this->assertEquals($world['ownCost'], (float) $ownJournal->getSum(), $hint);

        $this->assertSame(
            States::unbooked,
            $foreign->getState(),
            'Outsider posted a journal for a company they do not belong to. '.$hint
        );
        $this->assertNull($foreign->getTravelExpenseBundle(), $hint);
        $this->assertSame(0, $this->bundleTransactionCount($world['foreignOrgId']), $hint);
    }

    public function testListAndPartialStayInsideTheUsersCompanies(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $this->client->loginUser($this->user($world['attackerId']));

        $this->client->request('GET', '/dashboard/travelExpense', [
            'dateFrom' => $world['from'],
            'dateTo' => $world['to'],
            'unbooked' => 'true',
            'booked' => 'false',
        ]);
        $this->assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($world['ownMarker'], $html);
        $this->assertStringContainsString($world['ownAmount'], $html);
        $this->assertStringNotContainsString($world['foreignMarker'], $html);
        $this->assertStringNotContainsString($world['foreignAmount'], $html);

        $this->client->request('GET', '/dashboard/travelExpense', [
            'dateFrom' => $world['from'],
            'dateTo' => $world['to'],
            'unbooked' => 'true',
            'booked' => 'false',
            'partial' => '1',
        ]);
        $this->assertResponseIsSuccessful();
        $partial = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($world['ownMarker'], $partial);
        $this->assertStringContainsString($world['ownAmount'], $partial);
        $this->assertStringNotContainsString($world['foreignMarker'], $partial);
        $this->assertStringNotContainsString($world['foreignAmount'], $partial);

    }

    public function testDashboardHidesOtherCompanies(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $this->client->loginUser($this->user($world['attackerId']));

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $dashboard = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($world['ownMarker'], $dashboard);
        $this->assertStringContainsString($world['ownAmount'], $dashboard);
        $this->assertStringNotContainsString($world['foreignMarker'], $dashboard);
        $this->assertStringNotContainsString($world['foreignAmount'], $dashboard);
    }

    public function testDetailPageRequiresCompanyMembership(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $foreignUrl = '/dashboard/travelExpense/'.$world['foreignId'];

        $this->client->loginUser($this->user($world['colleagueId']));
        $this->client->request('GET', $foreignUrl);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($world['foreignMarker'], (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->user($world['attackerId']));
        $this->client->request('GET', $foreignUrl);
        $show = (string) $this->client->getResponse()->getContent();
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404], $show);
        $this->assertStringNotContainsString($world['foreignMarker'], $show);
    }

    public function testPdfRequiresCompanyMembership(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $foreignUrl = '/dashboard/travelExpense/'.$world['foreignId'].'/pdf';

        $this->client->loginUser($this->user($world['colleagueId']));
        $this->client->request('GET', $foreignUrl);
        $this->assertResponseIsSuccessful();
        $pdf = (string) $this->client->getResponse()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString($world['foreignOrgName'], $text);
        $this->assertStringContainsString($world['foreignAmount'], $text);
        $this->assertStringContainsString($world['foreignMarker'], $text);

        $this->client->loginUser($this->user($world['attackerId']));
        $this->client->request('GET', $foreignUrl);
        $stolen = (string) $this->client->getResponse()->getContent();
        $this->assertNotSame($pdf, $stolen, 'Outsider downloaded the same travel-expense PDF a member receives.');
        $this->assertStringStartsNotWith('%PDF', $stolen);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404], substr($stolen, 0, 200));
    }

    public function testEditRequiresCompanyMembership(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $foreignUrl = '/dashboard/travelExpense/'.$world['foreignId'].'/edit';
        $this->client->loginUser($this->user($world['attackerId']));

        $edit = $this->client->request('GET', $foreignUrl);
        $editStatus = $this->client->getResponse()->getStatusCode();
        if ($editStatus === 200 && $edit->filter('[name="travel_expense[reason]"]')->count() > 0) {
            $payload = $this->formPayload($edit);
            $payload['travel_expense']['reason'] = 'PWNED-'.$world['token'];
            $this->client->request('POST', $foreignUrl, $payload);
        } else {
            $this->client->request('POST', $foreignUrl, [
                'travel_expense' => ['reason' => 'PWNED-'.$world['token']],
            ]);
        }

        $this->assertNotSame('PWNED-'.$world['token'], $this->reloadClaim($world['foreignId'])->getReason());
        $this->assertContains($editStatus, [403, 404]);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
    }

    public function testCloneRequiresCompanyMembership(): void
    {
        $world = $this->seed($this->newestClaimDay());
        $foreignUrl = '/dashboard/travelExpense/'.$world['foreignId'].'/clone';
        $this->client->loginUser($this->user($world['attackerId']));
        $claimsBefore = $this->claimCount($world['foreignOrgId']);

        $clone = $this->client->request('GET', $foreignUrl);
        $cloneStatus = $this->client->getResponse()->getStatusCode();
        if ($cloneStatus === 200 && $clone->filter('form')->count() > 0) {
            $this->client->submit($clone->filter('form')->form());
        }

        $this->assertSame($claimsBefore, $this->claimCount($world['foreignOrgId']));
        $this->assertContains($cloneStatus, [403, 404], substr((string) $clone->html(), 0, 200));
    }

    public function testBundlePageRequiresCompanyMembership(): void
    {
        $world = $this->seed($this->newestClaimDay(), true);
        $url = '/dashboard/travelExpense/bundle/'.$world['bundleId'];

        $this->client->loginUser($this->user($world['colleagueId']));
        $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($world['bundleMarker'], (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->user($world['attackerId']));
        $this->client->request('GET', $url);
        $bundleBody = (string) $this->client->getResponse()->getContent();
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404], substr($bundleBody, 0, 200));
        $this->assertStringNotContainsString($world['bundleMarker'], $bundleBody);
    }

    /**
     * @param 'wide'|'foreign-id' $mode
     * @param array<string, mixed> $world
     * @return array<string, string>
     */
    private function bookingBody(array $world, string $mode): array
    {
        $body = [
            'dateFrom' => (string) $world['wideFrom'],
            'dateTo' => (string) $world['wideTo'],
            'booked' => '',
            'unbooked' => '',
            'date' => '2031-04-04',
        ];
        if ($mode === 'foreign-id') {
            $body['organization'] = $world['foreignOrgId'];
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function seed(string $day, bool $withBundle = false): array
    {
        $em = $this->em();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $token = bin2hex(random_bytes(4));
        $when = new \DateTime($day.' 12:00:00');
        $admin = $this->bootstrapAdmin($hasher);
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($admin);
        $em->flush();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $attacker = $this->makeUser($admin, $hasher, 'atk'.$token, 'Ada', 'Attacker');
        $traveler = $this->makeUser($admin, $hasher, 'trv'.$token, 'Vera', 'Traveler');
        $colleague = $this->makeUser($admin, $hasher, 'col'.$token, 'Cora', 'Colleague');
        $em->persist($attacker);
        $em->persist($traveler);
        $em->persist($colleague);
        $em->flush();

        $country = $admin->createCountry($this->countryCommand($token));
        $ownOrigin = $country->createPost($this->postCommand('OA'.$token, 'Own'.$token), $admin);
        $ownDestination = $country->createPost($this->postCommand('OB'.$token, 'OwnEnd'.$token), $admin);
        $foreignOrigin = $country->createPost($this->postCommand('FA'.$token, 'Frn'.$token), $admin);
        $foreignDestination = $country->createPost($this->postCommand('FB'.$token, 'FrnEnd'.$token), $admin);
        $bundleOrigin = $country->createPost($this->postCommand('BA'.$token, 'Bdl'.$token), $admin);
        $bundleDestination = $country->createPost($this->postCommand('BB'.$token, 'BdlEnd'.$token), $admin);
        $address = $ownOrigin->createAddress($this->addressCommand('Trg '.$token), $admin);

        $debit = $this->konto($admin, 4, 46, 460, KontoTypes::active, 'Debit '.$token);
        $credit = $debit->getCategory()->getClass()->createCategory(
            $this->filled(new class extends CreateKontoCategoryCommand {}, 29, 'Cat29 '.$token),
            $admin
        )->createKonto(
            $this->filledKonto(281, KontoTypes::passive, 'Credit '.$token),
            $admin
        );

        $ownOrg = $this->organization($admin, 'A'.$token, 'OwnCo'.$token, $address, $debit, $credit);
        $foreignOrg = $this->organization($admin, 'B'.$token, 'Firm'.$token, $address, $debit, $credit);
        $attacker->addOrganization($ownOrg, $admin);
        $traveler->addOrganization($foreignOrg, $admin);
        $colleague->addOrganization($foreignOrg, $admin);

        $ownMarker = 'Own'.$token;
        $foreignMarker = 'Frn'.$token;
        $bundleMarker = 'Bdl'.$token;
        $own = $this->claim($attacker, $ownOrg, $when, $ownOrigin, $ownDestination, '11.11', '1.000', 'Own '.$token);
        $foreign = $this->claim($traveler, $foreignOrg, $when, $foreignOrigin, $foreignDestination, '87.65', '3.000', 'Trip '.$token);

        ob_start();
        $ownTx = $own['expense']->setNew($attacker);
        $foreignTx = $foreign['expense']->setNew($traveler);
        ob_end_clean();

        $em->persist($country);
        foreach ([$ownOrigin, $ownDestination, $foreignOrigin, $foreignDestination, $bundleOrigin, $bundleDestination] as $post) {
            $em->persist($post);
        }
        $em->persist($address);
        $em->persist($debit->getCategory()->getClass());
        $em->persist($debit->getCategory());
        $em->persist($debit);
        $em->persist($credit->getCategory());
        $em->persist($credit);
        $em->persist($ownOrg);
        $em->persist($ownOrg->getOrganizationSettings());
        $em->persist($foreignOrg);
        $em->persist($foreignOrg->getOrganizationSettings());
        $em->persist($own['expense']);
        $em->persist($foreign['expense']);
        $em->persist($ownTx);
        $em->persist($foreignTx);
        foreach ([$own['expense'], $foreign['expense']] as $expense) {
            foreach ($expense->getTravelStops() as $stop) {
                $em->persist($stop);
            }
        }

        $bundleId = null;
        if ($withBundle) {
            $bundled = $this->claim($traveler, $foreignOrg, $when, $bundleOrigin, $bundleDestination, '10.00', '1.000', 'Bundle '.$token);
            ob_start();
            $bundledTx = $bundled['expense']->setNew($traveler);
            $command = new CreateTravelExpenseBundleCommand();
            $command->travelExpenses = [$bundled['expense']];
            $command->organization = $foreignOrg;
            $bundle = $traveler->createTravelExpenseBundle($command);
            $paid = $bundle->setBooked(new \DateTime($day.' 00:00:00'), $traveler);
            ob_end_clean();
            foreach ($bundled['expense']->getTravelStops() as $stop) {
                $em->persist($stop);
            }
            $em->persist($bundled['expense']);
            $em->persist($bundledTx);
            $em->persist($bundle);
            $em->persist($paid);
            $bundleId = $bundle->getId()->toString();
        }

        $em->flush();

        $start = (clone $when)->setTime(0, 0, 0)->getTimestamp();
        $end = (clone $when)->setTime(23, 59, 59)->getTimestamp();

        return [
            'token' => $token,
            'attackerId' => $attacker->getId()->toString(),
            'colleagueId' => $colleague->getId()->toString(),
            'ownId' => $own['expense']->getId()->toString(),
            'foreignId' => $foreign['expense']->getId()->toString(),
            'ownOrgId' => $ownOrg->getId()->toString(),
            'foreignOrgId' => $foreignOrg->getId()->toString(),
            'foreignOrgName' => $foreignOrg->getName(),
            'ownMarker' => $ownMarker,
            'foreignMarker' => $foreignMarker,
            'bundleMarker' => $bundleMarker,
            'bundleId' => $bundleId,
            'ownCost' => 11.11,
            'ownAmount' => '11,11 €',
            'foreignAmount' => '262,95 €',
            'from' => (string) $start,
            'to' => (string) $end,
            'wideFrom' => (new \DateTime($when->format('Y').'-01-01 00:00:00'))->getTimestamp(),
            'wideTo' => (new \DateTime($when->format('Y').'-12-31 23:59:59'))->getTimestamp(),
        ];
    }

    private function newestClaimDay(): string
    {
        $max = $this->em()->createQuery('SELECT MAX(te.date) FROM '.TravelExpense::class.' te')->getSingleScalarResult();
        $day = $max ? (new \DateTime((string) $max))->modify('+1 day') : new \DateTime('2035-01-01');

        return $day->format('Y-m-d');
    }

    private function claim(
        User $employee,
        Organization $organization,
        \DateTime $when,
        \App\Entity\Geography\Post $origin,
        \App\Entity\Geography\Post $destination,
        string $distance,
        string $rate,
        string $reason,
    ): array {
        $command = new CreateTravelExpenseCommand();
        $command->date = $when;
        $command->employee = $employee;
        $command->organization = $organization;
        $command->rate = $rate;
        $command->reason = $reason;
        $command->advance = '12.34';
        $command->travelStopCommands = new ArrayCollection();
        ob_start();
        $expense = $employee->createTravelExpense($command);
        $expense->createTravelStop($this->stop($origin, 1, '0'));
        $expense->createTravelStop($this->stop($destination, 2, $distance));
        ob_end_clean();

        return ['expense' => $expense];
    }

    private function stop(\App\Entity\Geography\Post $post, int $order, string $distance): CreateTravelStopCommand
    {
        $command = new CreateTravelStopCommand();
        $command->post = $post;
        $command->stopOrder = $order;
        $command->distanceFromPrevious = $distance;

        return $command;
    }

    private function organization(
        User $admin,
        string $code,
        string $name,
        \App\Entity\Geography\Address $address,
        Konto $debit,
        Konto $credit,
    ): Organization {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = 'SI'.$code;
        $command->taxable = true;
        $command->address = $address;
        $organization = $admin->createOrganization($command);
        $settings = new UpdateOrganizationSettingsCommand();
        $settings->travelExpenseRate = 1.0;
        $settings->autoCreatePerDiem = false;
        $settings->autoCreateLunch = false;
        $settings->perDiemValue = 0;
        $settings->IncurredTravelExpenseDebit = $debit;
        $settings->IncurredTravelExpenseCredit = $credit;
        $settings->PaidTravelExpenseDebit = $debit;
        $settings->PaidTravelExpenseCredit = $credit;
        $organization->updateOrganizationSettings($settings, $admin);

        return $organization;
    }

    private function konto(User $admin, int $classNumber, int $categoryNumber, int $number, int $type, string $name): Konto
    {
        $class = new \App\Entity\Konto\KontoClass(
            $this->filled(new class extends CreateKontoClassCommand {}, $classNumber, 'Class '.$classNumber),
            $admin
        );
        $category = $class->createCategory(
            $this->filled(new class extends CreateKontoCategoryCommand {}, $categoryNumber, 'Cat '.$categoryNumber),
            $admin
        );

        return $category->createKonto($this->filledKonto($number, $type, $name), $admin);
    }

    private function filledKonto(int $number, int $type, string $name): CreateKontoCommand
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = $type;

        return $command;
    }

    private function filled(object $command, int $number, string $name): object
    {
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;

        return $command;
    }

    private function countryCommand(string $token): CreateCountryCommand
    {
        $command = new CreateCountryCommand();
        $command->name = 'Slovenija '.$token;
        $command->nameInt = 'Slovenia '.$token;
        $command->A2 = 'SI';
        $command->A3 = 'SVN';
        $command->N3 = 705;

        return $command;
    }

    private function postCommand(string $code, string $name): CreatePostCommand
    {
        $command = new CreatePostCommand();
        $command->code = substr($code, 0, 10);
        $command->codeInternational = substr('SI-'.$code, 0, 15);
        $command->name = $name;

        return $command;
    }

    private function addressCommand(string $line): CreateAddressCommand
    {
        $command = new CreateAddressCommand();
        $command->line1 = $line;
        $command->line2 = '';

        return $command;
    }

    private function makeUser(User $admin, UserPasswordHasherInterface $hasher, string $username, string $first, string $last): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = $first;
        $command->lastName = $last;
        $command->email = $username.'@example.test';
        $command->password = 'Aa1!'.bin2hex(random_bytes(6));
        $command->isRoleAdmin = false;
        $command->signatureFilename = '';
        $command->position = 'Clerk';

        return $admin->createUser($command, $hasher);
    }

    private function bootstrapAdmin(UserPasswordHasherInterface $hasher): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', 'adm'.bin2hex(random_bytes(4)));
        $this->set($user, 'firstName', 'Admin');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', 'admin-'.bin2hex(random_bytes(3)).'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Administrator');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_ADMIN']);
        $this->set($user, 'password', $hasher->hashPassword($user, 'Aa1!'.bin2hex(random_bytes(6))));

        return $user;
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

    private function user(string $id): User
    {
        $user = $this->em()->find(User::class, $id);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function reloadClaim(string $id): TravelExpense
    {
        $this->em()->clear();
        $claim = $this->em()->find(TravelExpense::class, $id);
        $this->assertInstanceOf(TravelExpense::class, $claim);

        return $claim;
    }

    private function bundleTransactionCount(string $organizationId): int
    {
        return (int) $this->em()->createQuery(
            'SELECT COUNT(t.id) FROM '.Transaction::class.' t WHERE IDENTITY(t.organization) = :org AND t.travelExpenseBundle IS NOT NULL'
        )->setParameter('org', $organizationId)->getSingleScalarResult();
    }

    private function bundleTransaction(TravelExpenseBundle $bundle): ?Transaction
    {
        return $this->em()->getRepository(Transaction::class)->findOneBy(['travelExpenseBundle' => $bundle]);
    }

    private function claimCount(string $organizationId): int
    {
        return (int) $this->em()->createQuery(
            'SELECT COUNT(te.id) FROM '.TravelExpense::class.' te WHERE IDENTITY(te.organization) = :org'
        )->setParameter('org', $organizationId)->getSingleScalarResult();
    }

    /**
     * TCPDF writes page text as UTF-16BE literals inside compressed content streams.
     */
    private function pdfText(string $pdf): string
    {
        $text = '';
        if (!preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches)) {
            return $text;
        }
        foreach ($matches[1] as $stream) {
            $decoded = @zlib_decode($stream);
            $blob = is_string($decoded) ? $decoded : $stream;
            if (!preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/s', $blob, $literals)) {
                continue;
            }
            foreach ($literals[1] as $literal) {
                if (str_contains($literal, "\0")) {
                    $text .= mb_convert_encoding($literal, 'UTF-8', 'UTF-16BE');
                } else {
                    $text .= $literal;
                }
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(\Symfony\Component\DomCrawler\Crawler $page): array
    {
        $nested = [];
        foreach ($page->filter('input, select, textarea') as $node) {
            $name = $node->getAttribute('name');
            if ($name === '') {
                continue;
            }
            if (strtolower($node->nodeName) === 'select') {
                $value = '';
                foreach ($node->getElementsByTagName('option') as $option) {
                    if ($option->hasAttribute('selected')) {
                        $value = $option->getAttribute('value');
                        break;
                    }
                }
            } elseif (strtolower($node->nodeName) === 'textarea') {
                $value = $node->textContent ?? '';
            } else {
                $type = strtolower($node->getAttribute('type'));
                if (($type === 'checkbox' || $type === 'radio') && !$node->hasAttribute('checked')) {
                    continue;
                }
                $value = $node->getAttribute('value');
            }
            $piece = [];
            parse_str($name.'='.rawurlencode((string) $value), $piece);
            $nested = array_replace_recursive($nested, $piece);
        }

        return $nested;
    }

    private function responseHint(): string
    {
        return 'HTTP '.$this->client->getResponse()->getStatusCode().' '.substr((string) $this->client->getResponse()->getContent(), 0, 400);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
