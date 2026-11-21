<?php

declare(strict_types=1);

namespace App\Tests\Expense;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Process\Process;

/**
 * Overlapping meal-claim and travel-claim bookings must insert one journal
 * entry for the claims they load.
 *
 * Run:
 *   php vendor/bin/phpunit tests/Expense/ConcurrentClaimBookingTest.php
 *
 * These assertions fail while both requests can commit a bundle journal for
 * the same claim.
 */
final class ConcurrentClaimBookingTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private string $lastDetail = '';

    protected function setUp(): void
    {
        parent::setUp();
        self::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            self::fail('Entity manager is not available.');
        }
        $this->em = $em;
    }

    public function testOverlappingLunchBookingsInsertOneJournalEntry(): void
    {
        $seed = $this->seedBookkeeper();
        $claim = $this->createLunchClaim($seed['user'], $seed['organization'], $seed['day'], '15.50');
        $this->em->flush();

        $sums = $this->bookTwice(
            'lunch',
            LunchExpense::class,
            '/dashboard/lunchExpense/bookInBundle/withFilter',
            'lunchExpenseBundle',
            $seed,
        );

        self::assertCount(1, $sums, $this->failureDetail($seed));
        self::assertEqualsWithDelta((float) $claim->getSum(), $sums[0], 0.001, $this->failureDetail($seed));
    }

    public function testOverlappingTravelBookingsInsertOneJournalEntry(): void
    {
        $seed = $this->seedBookkeeper();
        $claim = $this->createTravelClaim($seed['user'], $seed['organization'], $seed['post'], $seed['day']);
        $this->em->flush();
        $expected = (float) $claim->getTotalCost();

        $sums = $this->bookTwice(
            'travel',
            TravelExpense::class,
            '/dashboard/travelExpense/bookInBundle/withFilter',
            'travelExpenseBundle',
            $seed,
        );

        self::assertCount(1, $sums, $this->failureDetail($seed));
        self::assertEqualsWithDelta($expected, $sums[0], 0.001, $this->failureDetail($seed));
    }

    /**
     * @param array{user: User, organization: Organization, day: \DateTimeImmutable, from: int, to: int, detail: string} $seed
     * @return list<float>
     */
    private function bookTwice(string $kind, string $claimClass, string $path, string $bundleField, array $seed): array
    {
        $repository = $this->em->getRepository($claimClass);
        $visible = $repository->getFilteredQuery((string) $seed['from'], (string) $seed['to'], true, false)->getQuery()->getResult();
        self::assertCount(1, $visible, 'The unbooked claim must be visible to the booking query.');

        $directory = sys_get_temp_dir().'/birokrat-booking-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $worker = dirname(__DIR__).'/Expense/BookingRaceWorker.php';
        $projectDir = dirname(__DIR__, 2);
        $processes = [];
        foreach (['a', 'b'] as $slot) {
            $peer = $slot === 'a' ? 'b' : 'a';
            $configPath = $directory.'/'.$slot.'.json';
            file_put_contents($configPath, json_encode([
                'username' => $seed['user']->getUsername(),
                'claimClass' => $claimClass,
                'path' => $path,
                'dateFrom' => (string) $seed['from'],
                'dateTo' => (string) $seed['to'],
                'date' => $seed['day']->format('Y-m-d'),
                'readyFile' => $directory.'/'.$slot.'.ready',
                'peerReadyFile' => $directory.'/'.$peer.'.ready',
                'resultFile' => $directory.'/'.$slot.'.result',
                'timeoutSeconds' => 30,
                'kind' => $kind,
            ], JSON_THROW_ON_ERROR));
            $process = new Process([PHP_BINARY, $worker, $configPath], $projectDir);
            $process->setTimeout(120);
            $processes[$slot] = $process;
        }

        foreach ($processes as $process) {
            $process->start();
        }
        $details = [];
        foreach ($processes as $slot => $process) {
            $process->wait();
            $resultPath = $directory.'/'.$slot.'.result';
            $result = is_file($resultPath) ? (string) file_get_contents($resultPath) : '';
            $details[] = $slot.' exit='.$process->getExitCode().' result='.$result.' stderr='.trim($process->getErrorOutput()).' stdout='.trim($process->getOutput());
        }
        $this->lastDetail = implode("\n", $details);

        $this->em->clear();
        $organization = $this->em->find(Organization::class, $seed['organization']->getId());
        $rows = $this->em->createQuery(
            'SELECT t.sum AS journalSum FROM App\Entity\Transaction\Transaction t JOIN t.'.$bundleField.' b WHERE b.organization = :organization'
        )->setParameter('organization', $organization)->getScalarResult();

        return array_map(static fn (array $row): float => (float) $row['journalSum'], $rows);
    }

    /**
     * @return array{user: User, organization: Organization, post: \App\Entity\Geography\Post, day: \DateTimeImmutable, from: int, to: int, detail: string}
     */
    private function seedBookkeeper(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->newUser('booker-'.$suffix, 'booker-'.$suffix.'@example.test');
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Race '.$suffix;
        $countryCommand->nameInt = 'Race '.$suffix;
        $countryCommand->A2 = strtoupper(substr($suffix, 0, 2));
        $countryCommand->A3 = strtoupper(substr($suffix, 0, 3));
        $countryCommand->N3 = 900 + (hexdec(substr($suffix, 0, 2)) % 50);
        $country = $user->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = substr($suffix, 0, 4);
        $postCommand->codeInternational = 'R-'.$suffix;
        $postCommand->name = 'Race';
        $post = $country->createPost($postCommand, $user);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = '1 Race Street';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $user);

        $organizationCommand = new CreateOrganizationCommand();
        $organizationCommand->code = 'R'.$suffix;
        $organizationCommand->name = 'Race '.$suffix;
        $organizationCommand->shortName = 'Race';
        $organizationCommand->taxNumber = (string) random_int(10000000, 99999999);
        $organizationCommand->taxable = true;
        $organizationCommand->address = $address;
        $organization = $user->createOrganization($organizationCommand);
        $user->addOrganization($organization, $user);

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 8;
        $classCommand->name = 'Race class '.$suffix;
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $user);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 80;
        $categoryCommand->name = 'Race category '.$suffix;
        $categoryCommand->description = '';
        $category = new KontoCategory($categoryCommand, $class, $user);

        $debit = $this->newKonto($category, $user, 1, 'Race debit '.$suffix);
        $credit = $this->newKonto($category, $user, 2, 'Race credit '.$suffix);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($organization);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);
        $this->em->flush();

        $settings = new UpdateOrganizationSettingsCommand();
        $settings->invoicePrefix = 'R';
        $settings->defaultPaymentDueIn = 8;
        $settings->referenceModel = '00';
        $settings->travelExpenseRate = 0.5;
        $settings->autoCreatePerDiem = false;
        $settings->perDiemValue = 0;
        $settings->autoCreateLunch = false;
        $settings->lunchValue = 15.5;
        $settings->IncurredTravelExpenseDebit = $debit;
        $settings->IncurredTravelExpenseCredit = $credit;
        $settings->PaidTravelExpenseDebit = $debit;
        $settings->PaidTravelExpenseCredit = $credit;
        $organization->updateOrganizationSettings($settings, $user);
        $this->em->flush();

        $day = (new \DateTimeImmutable('2032-01-01 12:00:00'))->modify('+'.random_int(0, 2000).' days');

        return [
            'user' => $user,
            'organization' => $organization,
            'post' => $post,
            'day' => $day,
            'from' => $day->setTime(0, 0, 0)->getTimestamp(),
            'to' => $day->setTime(23, 59, 59)->getTimestamp(),
            'detail' => '',
        ];
    }

    private function createLunchClaim(User $user, Organization $organization, \DateTimeImmutable $day, string $sum): LunchExpense
    {
        $command = new CreateLunchExpenseCommand();
        $command->date = \DateTime::createFromImmutable($day);
        $command->organization = $organization;
        $command->sum = $sum;
        $claim = $user->createLunchExpense($command);
        $opening = $claim->setNew($user);
        $this->em->persist($claim);
        $this->em->persist($opening);

        return $claim;
    }

    private function createTravelClaim(User $user, Organization $organization, \App\Entity\Geography\Post $post, \DateTimeImmutable $day): TravelExpense
    {
        $command = new CreateTravelExpenseCommand();
        $command->date = \DateTime::createFromImmutable($day);
        $command->employee = $user;
        $command->organization = $organization;
        $command->reason = 'Customer visit';
        $command->advance = 0;
        $command->number = 'PN-'.$day->format('Ymd').'-'.bin2hex(random_bytes(2));
        $claim = $user->createTravelExpense($command);

        $stop = new CreateTravelStopCommand();
        $stop->stopOrder = 1;
        $stop->distanceFromPrevious = 10;
        $stop->post = $post;
        ob_start();
        try {
            $createdStop = $claim->createTravelStop($stop);
        } finally {
            ob_end_clean();
        }
        $opening = $claim->setNew($user);
        $this->em->persist($claim);
        $this->em->persist($createdStop);
        $this->em->persist($opening);

        return $claim;
    }

    private function newKonto(KontoCategory $category, User $user, int $number, string $name): Konto
    {
        $command = new class extends CreateKontoCommand {
            public $type;
        };
        $command->number = $number;
        $command->name = $name;
        $command->description = '';
        $command->isActive = true;
        $command->type = KontoTypes::active;
        $konto = new Konto($command, $category, $user);
        $category->getKontos()->add($konto);

        return $konto;
    }

    private function newUser(string $username, string $email): User
    {
        $reflection = new ReflectionClass(User::class);
        $user = $reflection->newInstanceWithoutConstructor();
        $this->writeProperty($user, 'id', Uuid::uuid6());
        $this->writeProperty($user, 'createdOn', new \DateTime());
        $this->writeProperty($user, 'createdBy', $user);
        $this->writeProperty($user, 'isActive', true);
        $this->writeProperty($user, 'organizations', new ArrayCollection());
        $this->writeProperty($user, 'username', $username);
        $this->writeProperty($user, 'firstName', 'Book');
        $this->writeProperty($user, 'lastName', 'Keeper');
        $this->writeProperty($user, 'email', $email);
        $this->writeProperty($user, 'mobile', null);
        $this->writeProperty($user, 'phone', null);
        $this->writeProperty($user, 'signatureFilename', '');
        $this->writeProperty($user, 'position', 'Bookkeeper');
        $this->writeProperty($user, 'totpEnabled', false);
        $this->writeProperty($user, 'totpSecret', null);
        $this->writeProperty($user, 'roles', ['ROLE_USER']);
        $this->writeProperty($user, 'password', 'placeholder');

        return $user;
    }

    private function writeProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @param array{detail?: string} $seed
     */
    private function failureDetail(array $seed): string
    {
        return "Overlapping bookings of the same unbooked claim must leave exactly one bundle journal entry.\n".$this->lastDetail;
    }
}
