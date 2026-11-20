<?php

namespace App\Tests\Web;

use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chart of accounts must not be readable without a signed-in user.
 * These tests fail while /codesheets/konto is outside the access rules
 * and the controller does not check the caller.
 */
class KontoAuthorizationTest extends WebTestCase
{
    private const CLASS_NAME = 'ProbeClassQj4m';
    private const CATEGORY_ALPHA = 'ProbeCategoryAlphaQj4m';
    private const CATEGORY_BETA = 'ProbeCategoryBetaQj4m';
    private const KONTO_ALPHA = 'ProbeKontoAlphaQj4m';
    private const KONTO_BETA = 'ProbeKontoBetaQj4m';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $reader;
    private KontoCategory $alphaCategory;
    private string $alphaFullNumber;
    private string $betaFullNumber;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $this->em = static::getContainer()->get(EntityManagerInterface::class);
            $this->em->getConnection()->executeQuery('SELECT 1');
            $this->seedChart();
        } catch (DbalException|\PDOException|\Throwable $e) {
            if (!isset($this->client)) {
                $this->markTestSkipped('Database is not available for chart-of-accounts tests: '.$e->getMessage());
            }
            throw $e;
        }
    }

    public function testAnonymousGetDoesNotRevealChartOfAccounts(): void
    {
        $this->assertAnonymousCannotReadChart('GET', '/codesheets/konto', []);
    }

    public function testAnonymousGetWithQueryStringDoesNotRevealChartOfAccounts(): void
    {
        $this->assertAnonymousCannotReadChart('GET', '/codesheets/konto?unused=1', []);
    }

    public function testAnonymousPostWithoutFilterDoesNotRevealChartOfAccounts(): void
    {
        $this->assertAnonymousCannotReadChart('POST', '/codesheets/konto', []);
    }

    public function testAnonymousPostCategoryFilterDoesNotRevealSelectedAccounts(): void
    {
        // The category branch reads the posted array through InputBag::get(), which
        // rejects non-scalar values before the table is rendered. It must stay that
        // way: a later change that reads the array must still not show the chart
        // to an anonymous caller.
        $this->assertAnonymousFilterDoesNotRevealChart([
            'konto_filter' => [
                'kontoCategories' => $this->alphaCategory->getId()->toString(),
                '_token' => 'invalid',
            ],
        ]);
    }

    public function testAnonymousPostClassFilterDoesNotRevealChartStructure(): void
    {
        $this->assertAnonymousFilterDoesNotRevealChart([
            'konto_filter' => [
                'kontoClasses' => $this->alphaCategory->getClass()->getId()->toString(),
                '_token' => 'invalid',
            ],
        ]);
    }

    public function testSignedInUserStillReceivesChartOfAccounts(): void
    {
        $this->client->loginUser($this->reader);

        $this->client->request('GET', '/codesheets/konto');
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::KONTO_ALPHA, $content);
        $this->assertStringContainsString(self::KONTO_BETA, $content);
        $this->assertStringContainsString($this->alphaFullNumber, $content);
        $this->assertStringContainsString($this->betaFullNumber, $content);
    }

    private function assertAnonymousFilterDoesNotRevealChart(array $parameters): void
    {
        $this->client->request('POST', '/codesheets/konto', $parameters);
        $this->assertChartIsNotInResponse('POST', '/codesheets/konto');
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = (string) $this->client->getResponse()->headers->get('Location');
            $this->assertStringNotContainsString('/codesheets/konto', $location);
            $this->client->followRedirect();
            $this->assertChartIsNotInResponse('POST', '/codesheets/konto (after redirect)');

            return;
        }

        $this->assertFalse(
            $this->client->getResponse()->isSuccessful(),
            'Anonymous filtered chart request must not succeed. Status: '.$status
        );
    }

    private function assertAnonymousCannotReadChart(string $method, string $path, array $parameters): void
    {
        $this->client->request($method, $path, $parameters);
        $this->assertChartIsNotInResponse($method, $path);

        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = (string) $this->client->getResponse()->headers->get('Location');
            $this->assertStringNotContainsString('/codesheets/konto', $location, 'Refusal must not send the caller back to the chart.');
            $this->client->followRedirect();
            $this->assertChartIsNotInResponse($method, $path.' (after redirect)');

            return;
        }

        $this->assertContains(
            $status,
            [401, 403],
            sprintf(
                'Anonymous %s %s was not refused (status %d). Body starts: %s',
                $method,
                $path,
                $status,
                substr((string) $this->client->getResponse()->getContent(), 0, 400)
            )
        );
    }

    private function assertChartIsNotInResponse(string $method, string $path): void
    {
        $content = (string) $this->client->getResponse()->getContent();
        foreach ($this->markers() as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $content,
                sprintf('Anonymous %s %s disclosed chart marker "%s".', $method, $path, $marker)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function markers(): array
    {
        return [
            self::CLASS_NAME,
            self::CATEGORY_ALPHA,
            self::CATEGORY_BETA,
            self::KONTO_ALPHA,
            self::KONTO_BETA,
            $this->alphaFullNumber,
            $this->betaFullNumber,
        ];
    }

    private function seedChart(): void
    {
        $existing = $this->em->getRepository(Konto::class)->findOneBy(['name' => self::KONTO_ALPHA]);
        if ($existing instanceof Konto) {
            $this->alphaCategory = $existing->getCategory();
            $this->alphaFullNumber = $existing->getFullNumber();
            $beta = $this->em->getRepository(Konto::class)->findOneBy(['name' => self::KONTO_BETA]);
            $this->betaFullNumber = $beta->getFullNumber();
            $this->reader = $existing->getCreatedBy();

            return;
        }

        $this->reader = $this->makeUser();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($this->reader);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 48173;
        $classCommand->name = self::CLASS_NAME;
        $classCommand->description = 'probe';
        $class = new KontoClass($classCommand, $this->reader);

        $alphaCategory = $this->addCategory($class, 90, self::CATEGORY_ALPHA);
        $betaCategory = $this->addCategory($class, 91, self::CATEGORY_BETA);
        $alpha = $this->addKonto($alphaCategory, 96, self::KONTO_ALPHA);
        $beta = $this->addKonto($betaCategory, 97, self::KONTO_BETA);

        $this->em->persist($class);
        $this->em->persist($alphaCategory);
        $this->em->persist($betaCategory);
        $this->em->persist($alpha);
        $this->em->persist($beta);
        $this->em->flush();

        $this->alphaCategory = $alphaCategory;
        $this->alphaFullNumber = $alpha->getFullNumber();
        $this->betaFullNumber = $beta->getFullNumber();
    }

    private function addCategory(KontoClass $class, int $number, string $name): KontoCategory
    {
        $command = new class extends CreateKontoCategoryCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = 'probe';

        return $class->createCategory($command, $this->reader);
    }

    private function addKonto(KontoCategory $category, int $number, string $name): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = 'probe';
        $command->isActive = true;
        $command->type = KontoTypes::active;

        return $category->createKonto($command, $this->reader);
    }

    private function makeUser(): User
    {
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', 'chart-reader');
        $this->set($user, 'firstName', 'Chart');
        $this->set($user, 'lastName', 'Reader');
        $this->set($user, 'email', 'chart-reader@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Reader');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', 'not-used');

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
}
