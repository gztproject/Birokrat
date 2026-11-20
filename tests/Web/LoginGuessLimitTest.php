<?php

namespace App\Tests\Web;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Uuid;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Spec for login throttling and the password minimum.
 * These tests fail while repeated guesses are still accepted and while a
 * four-character password is still stored.
 */
class LoginGuessLimitTest extends WebTestCase
{
    /**
     * Guesses that must be refused for one username from one client address.
     * Symfony's login throttling default is five accepted failures, then a block.
     * A stricter limit still satisfies this.
     */
    private const FAILURES_BEFORE_BLOCK = 5;

    private const MINIMUM_PASSWORD_LENGTH = 12;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $hasher;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/birokrat-login-guess-'.bin2hex(random_bytes(4)).'.db';
        $url = 'sqlite:///'.$this->databasePath;
        putenv('DATABASE_URL='.$url);
        $_ENV['DATABASE_URL'] = $url;
        $_SERVER['DATABASE_URL'] = $url;

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testMatchingPasswordSignsInBeforeTheFailureLimit(): void
    {
        $username = 'account-before-limit';
        $password = $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH);
        $this->storeAccount($username, $password);

        $this->submitLogin($username, $password);

        $this->assertTrue($this->sessionCanOpenAuthenticatedPage(), 'A password that meets the minimum did not sign in.');
    }

    public function testLoginStopsAcceptingGuessesForTheSameUsernameAndClientAddress(): void
    {
        $username = 'account-guess-limit';
        $password = $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH);
        $this->storeAccount($username, $password);
        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.10');

        for ($attempt = 1; $attempt <= self::FAILURES_BEFORE_BLOCK; ++$attempt) {
            $this->submitLogin($username, 'placeholder-guess-'.$attempt);
            $this->assertFalse(
                $this->sessionCanOpenAuthenticatedPage(),
                'A rejected guess created a signed-in session.'
            );
        }

        $this->submitLogin($username, $password);

        $this->assertFalse(
            $this->sessionCanOpenAuthenticatedPage(),
            'A matching password was accepted after repeated guesses from the same client address.'
        );
    }

    public function testFailuresForOneUsernameDoNotBlockAnotherUsernameFromTheSameClient(): void
    {
        $password = $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH);
        $this->storeAccount('account-blocked', $password);
        $this->storeAccount('account-other', $password);
        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.10');

        for ($attempt = 1; $attempt <= self::FAILURES_BEFORE_BLOCK; ++$attempt) {
            $this->submitLogin('account-blocked', 'placeholder-guess-'.$attempt);
        }

        $this->submitLogin('account-other', $password);

        $this->assertTrue(
            $this->sessionCanOpenAuthenticatedPage(),
            'Guesses for one username blocked a different username from the same client address.'
        );
    }

    public function testFailuresFromOneClientAddressDoNotBlockTheSameUsernameFromAnotherAddress(): void
    {
        $username = 'account-address-key';
        $password = $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH);
        $this->storeAccount($username, $password);

        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.10');
        for ($attempt = 1; $attempt <= self::FAILURES_BEFORE_BLOCK; ++$attempt) {
            $this->submitLogin($username, 'placeholder-guess-'.$attempt);
        }

        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.11');
        $this->submitLogin($username, $password);

        $this->assertTrue(
            $this->sessionCanOpenAuthenticatedPage(),
            'Guesses from one client address blocked the same username from a different address.'
        );
    }

    public function testFourCharacterPasswordIsRejectedWhenCreatingAUser(): void
    {
        $this->assertPasswordRejected(4, 'create');
    }

    public function testElevenCharacterPasswordIsRejectedWhenCreatingAUser(): void
    {
        $this->assertPasswordRejected(self::MINIMUM_PASSWORD_LENGTH - 1, 'create');
    }

    public function testMinimumLengthPasswordCanBeCreated(): void
    {
        $admin = $this->creator();
        $created = $admin->createUser($this->command('created-long', self::MINIMUM_PASSWORD_LENGTH), $this->hasher);

        $this->assertNotSame($this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH), $created->getPassword());
        $this->assertTrue($this->hasher->isPasswordValid($created, $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH)));
    }

    public function testFourCharacterPasswordIsRejectedWhenUpdatingAUser(): void
    {
        $this->assertPasswordRejected(4, 'update');
    }

    public function testElevenCharacterPasswordIsRejectedWhenUpdatingAUser(): void
    {
        $this->assertPasswordRejected(self::MINIMUM_PASSWORD_LENGTH - 1, 'update');
    }

    private function assertPasswordRejected(int $length, string $operation): void
    {
        $admin = $this->creator();
        $account = $admin->createUser($this->command('password-rule', self::MINIMUM_PASSWORD_LENGTH), $this->hasher);

        try {
            if ('create' === $operation) {
                $admin->createUser($this->command('password-rule-new', $length), $this->hasher);
            } else {
                $update = new UpdateUserCommand();
                $update->password = $this->placeholderPassword($length);
                $update->oldPassword = $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH);
                $account->update($update, $admin, $this->hasher);
            }
            $this->fail('A password shorter than '.self::MINIMUM_PASSWORD_LENGTH.' characters was stored.');
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertStringContainsString('short', strtolower($e->getMessage()));
        }
    }

    private function storeAccount(string $username, string $password): void
    {
        $admin = $this->creator();
        $account = $admin->createUser($this->command($username, null, $password), $this->hasher);

        $this->em->getConnection()->executeStatement('PRAGMA foreign_keys = OFF');
        $this->em->persist($admin);
        $this->em->persist($account);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
    }

    private function command(string $username, ?int $length = null, ?string $password = null): CreateUserCommand
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Test';
        $command->lastName = 'Account';
        $command->email = $username.'@example.test';
        $command->password = $password ?? $this->placeholderPassword($length ?? self::MINIMUM_PASSWORD_LENGTH);
        $command->isRoleAdmin = false;
        $command->signatureFilename = '';

        return $command;
    }

    private function creator(): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setProperty($user, 'id', Uuid::uuid6());
        $this->setProperty($user, 'createdOn', new \DateTime());
        $this->setProperty($user, 'createdBy', $user);
        $this->setProperty($user, 'isActive', true);
        $this->setProperty($user, 'organizations', new ArrayCollection());
        $this->setProperty($user, 'username', 'creator');
        $this->setProperty($user, 'firstName', 'Creator');
        $this->setProperty($user, 'lastName', 'Account');
        $this->setProperty($user, 'email', 'creator@example.test');
        $this->setProperty($user, 'mobile', null);
        $this->setProperty($user, 'phone', null);
        $this->setProperty($user, 'signatureFilename', '');
        $this->setProperty($user, 'position', null);
        $this->setProperty($user, 'totpEnabled', false);
        $this->setProperty($user, 'totpSecret', null);
        $this->setProperty($user, 'roles', ['ROLE_ADMIN']);
        $this->setProperty($user, 'password', $this->hasher->hashPassword($user, $this->placeholderPassword(self::MINIMUM_PASSWORD_LENGTH)));

        return $user;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }

    private function submitLogin(string $username, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $parameters = [
            '_username' => $username,
            '_password' => $password,
        ];
        $token = $crawler->filter('input[name="_csrf_token"]');
        if ($token->count() > 0) {
            $parameters['_csrf_token'] = $token->attr('value');
        }

        $this->client->request('POST', '/login', $parameters);
    }

    private function sessionCanOpenAuthenticatedPage(): bool
    {
        $this->client->request('GET', '/user/2fa');

        return $this->client->getResponse()->isSuccessful();
    }

    private function placeholderPassword(int $length): string
    {
        return str_repeat('p', $length - 2).'1!';
    }
}
