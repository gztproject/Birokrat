<?php

namespace App\Tests\Web;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Username uniqueness for profile editing and user creation.
 *
 * These tests fail while a signed-in user can store a username that already
 * belongs to someone else. Sign-in loads one matching row and checks the
 * password only against that row, so the other account can no longer sign in.
 * They pass once a duplicate name is rejected and the username column rejects
 * a second committed write of the same name.
 */
class DuplicateUsernameLockoutTest extends WebTestCase
{
    private const FIXTURE_PASSWORD = 'placeholder-1!';
    private const VICTIM_PASSWORD = 'placeholder-victim-1!';
    private const ATTACKER_PASSWORD = 'placeholder-attacker-1!';

    /** @var list<string> */
    private static array $signatureBaseline = [];

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $hasher;

    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->suffix = bin2hex(random_bytes(4));
        // Form logins share the IP bucket with the rest of the suite; clear it so
        // uniqueness checks are not confused with throttling.
        static::getContainer()->get('cache.global_clearer')->clearPool('cache.rate_limiter');
        if (self::$signatureBaseline === []) {
            $directory = (string) static::getContainer()->getParameter('signatures_directory');
            self::$signatureBaseline = is_dir($directory) ? $this->fileNames($directory) : [];
        }
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $connection = $client->getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement("DELETE FROM app_users WHERE email LIKE 'lockout-%@example.test'");
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $directory = (string) $client->getContainer()->getParameter('signatures_directory');
        if (is_dir($directory)) {
            foreach (scandir($directory) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $directory.'/'.$name;
                if (is_file($path) && !in_array($name, self::$signatureBaseline, true)) {
                    unlink($path);
                }
            }
        }
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testOrdinaryUserCanRenameToAnUnusedUsername(): void
    {
        $creator = $this->bootstrapCreator();
        $user = $this->createAccount($creator, 'keeper', false);
        $renamed = 'keeper-renamed-'.$this->suffix;

        $this->submitOwnProfile($user, $renamed, self::FIXTURE_PASSWORD);

        $reloaded = $this->reload($user);
        $this->assertSame($renamed, $reloaded->getUsername());
        $this->assertTrue($this->signIn($renamed, self::FIXTURE_PASSWORD));
        $this->assertFalse($this->signIn('keeper-'.$this->suffix, self::FIXTURE_PASSWORD));
    }

    public function testOrdinaryUserCannotTakeAnExistingUsername(): void
    {
        $creator = $this->bootstrapCreator();
        // Older row first. Sign-in has no ORDER BY, so which account remains
        // able to sign in depends on which row the database returns.
        $attacker = $this->createAccount($creator, 'attacker', false, self::ATTACKER_PASSWORD);
        $victim = $this->createAccount($creator, 'victim', false, self::VICTIM_PASSWORD);

        $this->submitOwnProfile($attacker, $victim->getUsername(), self::ATTACKER_PASSWORD);

        $this->assertNoDuplicateLockout($attacker, $victim, $victim->getUsername(), self::ATTACKER_PASSWORD, self::VICTIM_PASSWORD);
    }

    public function testOrdinaryUserCannotTakeACaseVariantOfAnExistingUsername(): void
    {
        $creator = $this->bootstrapCreator();
        $attacker = $this->createAccount($creator, 'attacker', false, self::ATTACKER_PASSWORD);
        $victim = $this->createAccount($creator, 'victim', false, self::VICTIM_PASSWORD);

        $this->submitOwnProfile($attacker, strtoupper($victim->getUsername()), self::ATTACKER_PASSWORD);

        $this->assertNoDuplicateLockout($attacker, $victim, $victim->getUsername(), self::ATTACKER_PASSWORD, self::VICTIM_PASSWORD);
    }

    public function testAdminCannotRenameAnotherUserOntoAnExistingUsername(): void
    {
        $admin = $this->bootstrapCreator();
        $victim = $this->createAccount($admin, 'victim', false, self::VICTIM_PASSWORD);
        $other = $this->createAccount($admin, 'other', false, self::ATTACKER_PASSWORD);

        $this->browserSignedIn($admin->getUsername(), self::FIXTURE_PASSWORD);
        $crawler = $this->client->request('GET', '/admin/user/'.$victim->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->submitUsername($crawler, $other->getUsername());

        $this->assertNoDuplicateLockout($victim, $other, $other->getUsername(), self::VICTIM_PASSWORD, self::ATTACKER_PASSWORD);
    }

    public function testAdminCannotCreateAUserWithAnExistingUsername(): void
    {
        $admin = $this->bootstrapCreator();
        $existing = $this->createAccount($admin, 'existing', false);
        $email = 'lockout-created-'.$this->suffix.'@example.test';

        $directory = $this->signatureDirectory();
        $before = $this->fileNames($directory);
        $upload = $this->temporaryPng();

        $this->browserSignedIn($admin->getUsername(), self::FIXTURE_PASSWORD);
        $crawler = $this->client->request('GET', '/admin/user/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[username]'] = $existing->getUsername();
        $form['user[email]'] = $email;
        $form['user[firstName]'] = 'Created';
        $form['user[lastName]'] = 'User';
        $form['user[password][first]'] = self::ATTACKER_PASSWORD;
        $form['user[password][second]'] = self::ATTACKER_PASSWORD;
        $form['user[signature]']->upload($upload);
        $this->client->submit($form);
        $written = array_values(array_diff($this->fileNames($directory), $before));

        static::ensureKernelShutdown();
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $created = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $matches = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM app_users WHERE username = ?',
            [$existing->getUsername()],
        );
        $problems = [];
        if ($created instanceof User) {
            $problems[] = 'created account '.$created->getId().' with the existing username';
        }
        if ($matches !== 1) {
            $problems[] = 'rows matching the username: '.$matches;
        }
        if ($written !== []) {
            $problems[] = 'signature files written: '.implode(', ', $written);
        }
        $this->assertSame([], $problems, implode("\n", $problems));
        $this->assertTrue($this->signIn($existing->getUsername(), self::FIXTURE_PASSWORD));
    }

    public function testAdminCanCreateAUserWithAnUnusedUsername(): void
    {
        $admin = $this->bootstrapCreator();
        $username = 'created-'.$this->suffix;
        $email = 'lockout-fresh-'.$this->suffix.'@example.test';

        $upload = $this->temporaryPng();

        $this->browserSignedIn($admin->getUsername(), self::FIXTURE_PASSWORD);
        $crawler = $this->client->request('GET', '/admin/user/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[username]'] = $username;
        $form['user[email]'] = $email;
        $form['user[firstName]'] = 'Created';
        $form['user[lastName]'] = 'User';
        $form['user[password][first]'] = self::FIXTURE_PASSWORD;
        $form['user[password][second]'] = self::FIXTURE_PASSWORD;
        $form['user[signature]']->upload($upload);
        $this->client->submit($form);

        static::ensureKernelShutdown();
        $client = static::createClient();
        $created = $client->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $created);
        $this->assertSame($username, $created->getUsername());
        $this->assertTrue($this->signIn($username, self::FIXTURE_PASSWORD));
    }

    public function testRejectedDuplicateUsernameDoesNotReplaceTheSignatureFile(): void
    {
        $creator = $this->bootstrapCreator();
        $attacker = $this->createAccount($creator, 'attacker', false, self::ATTACKER_PASSWORD);
        $victim = $this->createAccount($creator, 'victim', false, self::VICTIM_PASSWORD);
        $directory = $this->signatureDirectory();
        $original = 'lockout-original-'.$this->suffix.'.png';
        $this->writePng($directory.'/'.$original);
        $this->write($attacker, 'signatureFilename', $original);
        $this->em->flush();
        $before = $this->fileNames($directory);

        $this->browserSignedIn($attacker->getUsername(), self::ATTACKER_PASSWORD);
        $crawler = $this->client->request('GET', '/user/'.$attacker->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[username]'] = $victim->getUsername();
        $form['user[signature]']->upload($this->temporaryPng());
        $this->client->submit($form);
        $after = $this->fileNames($directory);

        $this->assertSame($before, $after, "Signature files changed before the duplicate username was refused.\nbefore=".implode(',', $before)."\nafter=".implode(',', $after));
        $this->assertFileExists($directory.'/'.$original);
        $this->assertNoDuplicateLockout($attacker, $victim, $victim->getUsername(), self::ATTACKER_PASSWORD, self::VICTIM_PASSWORD);
    }

    public function testSecondCommittedUsernameWriteIsRejected(): void
    {
        $creator = $this->bootstrapCreator();
        $first = $this->createAccount($creator, 'first', false);
        $second = $this->createAccount($creator, 'second', false);
        $shared = 'shared-'.$this->suffix;

        $params = $this->em->getConnection()->getParams();
        $extra = new EntityManager(DriverManager::getConnection($params), $this->em->getConfiguration());
        try {
            $left = $this->em->find(User::class, $first->getId());
            $right = $extra->find(User::class, $second->getId());
            $this->assertInstanceOf(User::class, $left);
            $this->assertInstanceOf(User::class, $right);

            // Both writes pass any lookup that runs before either one commits.
            $left->update($this->renameCommand($left, $shared), $left, $this->hasher);
            $right->update($this->renameCommand($right, $shared), $right, $this->hasher);
            $this->em->flush();

            $thrown = null;
            try {
                $extra->flush();
            } catch (\Throwable $e) {
                $thrown = $e;
            }

            $this->assertInstanceOf(
                UniqueConstraintViolationException::class,
                $thrown,
                $thrown === null
                    ? 'The second committed write stored a duplicate username.'
                    : 'The second write failed with '.get_class($thrown).' instead of a unique-constraint violation.',
            );
        } finally {
            $extra->close();
        }

        static::ensureKernelShutdown();
        $client = static::createClient();
        $matches = (int) $client->getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM app_users WHERE username = ?',
            [$shared],
        );
        $this->assertLessThan(2, $matches);
    }

    public function testUsernameColumnHasAUniqueIndex(): void
    {
        $indexes = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'app_users'
               AND column_name = 'username'
               AND non_unique = 0",
        );
        $this->assertGreaterThan(0, $indexes, 'app_users.username has no unique index.');
    }

    private function assertNoDuplicateLockout(User $changed, User $other, string $contestedUsername, string $changedPassword, string $otherPassword): void
    {
        $changedId = $changed->getId()->toString();
        $otherId = $other->getId()->toString();
        $changedOriginal = $changed->getUsername();
        $otherOriginal = $other->getUsername();

        static::ensureKernelShutdown();
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $changedNow = $em->find(User::class, $changed->getId());
        $otherNow = $em->find(User::class, $other->getId());
        $loaded = $em->getRepository(User::class)->findOneBy(['username' => $contestedUsername]);
        $matches = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM app_users WHERE username = ?',
            [$contestedUsername],
        );

        $changedLogin = $this->signIn($changedOriginal, $changedPassword);
        $otherLogin = $this->signIn($otherOriginal, $otherPassword);
        $changedPasswordOnContestedName = $this->signIn($contestedUsername, $changedPassword);
        $otherPasswordOnContestedName = $this->signIn($contestedUsername, $otherPassword);

        $problems = [];
        if (!$changedNow instanceof User || $changedNow->getUsername() !== $changedOriginal) {
            $problems[] = 'changed account username is now '.($changedNow?->getUsername() ?? 'missing');
        }
        if (!$otherNow instanceof User || $otherNow->getUsername() !== $otherOriginal) {
            $problems[] = 'other account username is now '.($otherNow?->getUsername() ?? 'missing');
        }
        if ($matches !== 1) {
            $problems[] = 'rows matching the contested username: '.$matches;
        }
        if (!$changedLogin) {
            $problems[] = 'changed account cannot sign in with its original username';
        }
        if (!$otherLogin) {
            $problems[] = 'other account cannot sign in with its username';
        }
        if ($changedPasswordOnContestedName && !$otherPasswordOnContestedName) {
            $problems[] = 'only the changed account password was accepted for the contested username';
        }
        if ($otherPasswordOnContestedName && !$changedPasswordOnContestedName && $matches > 1) {
            $problems[] = 'only the other account password was accepted for the contested username';
        }

        $loadedId = $loaded instanceof User ? $loaded->getId()->toString() : 'none';
        $this->assertSame(
            [],
            $problems,
            "Duplicate username lockout.\n".implode("\n", $problems)
            ."\nfindOneBy(".$contestedUsername.') loaded '.$loadedId
            .' changed='.$changedId.' other='.$otherId
            .' changedLogin='.($changedLogin ? 'yes' : 'no')
            .' otherLogin='.($otherLogin ? 'yes' : 'no')
            .' changedPasswordOnContestedName='.($changedPasswordOnContestedName ? 'yes' : 'no')
            .' otherPasswordOnContestedName='.($otherPasswordOnContestedName ? 'yes' : 'no'),
        );
    }

    private function signatureDirectory(): string
    {
        $directory = (string) static::getContainer()->getParameter('signatures_directory');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory;
    }

    /**
     * @return list<string>
     */
    private function fileNames(string $directory): array
    {
        $names = [];
        foreach (scandir($directory) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    private function temporaryPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lockout');
        $png = $path.'.png';
        rename($path, $png);
        $this->writePng($png);

        return $png;
    }

    private function writePng(string $path): void
    {
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        imagedestroy($image);
    }

    private function browserSignedIn(string $username, string $password): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);
        // loginUser avoids filling the shared IP login bucket; signIn() still
        // exercises the real form path when checking lockout behaviour.
        $this->client->loginUser($user);
        unset($password);
    }

    private function submitOwnProfile(User $user, string $username, string $password): void
    {
        $this->browserSignedIn($user->getUsername(), $password);
        $crawler = $this->client->request('GET', '/user/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('input[name="user[username]"]')->count());
        $this->submitUsername($crawler, $username);
    }

    private function submitUsername(Crawler $crawler, string $username): void
    {
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[username]'] = $username;
        $this->client->submit($form);
    }

    private function signIn(string $username, string $password): bool
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        static::getContainer()->get('cache.global_clearer')->clearPool('cache.rate_limiter');
        $crawler = $client->request('GET', '/login');
        if (!$client->getResponse()->isSuccessful()) {
            return false;
        }
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', [
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $token,
        ]);
        if (!$client->getResponse()->isRedirect()) {
            return false;
        }
        $location = $client->getResponse()->headers->get('Location') ?? '';

        return !str_contains($location, '/login');
    }

    private function renameCommand(User $user, string $username): UpdateUserCommand
    {
        $command = new UpdateUserCommand();
        $user->mapTo($command);
        $command->username = $username;
        $command->password = '';
        $command->oldPassword = '';

        return $command;
    }

    private function reload(User $user): User
    {
        $id = $user->getId();
        static::ensureKernelShutdown();
        $client = static::createClient();
        $reloaded = $client->getContainer()->get(EntityManagerInterface::class)->find(User::class, $id);
        $this->assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }

    private function createAccount(User $creator, string $role, bool $admin, string $password = self::FIXTURE_PASSWORD): User
    {
        $username = $role.'-'.$this->suffix;
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Lock';
        $command->lastName = 'Out';
        $command->email = 'lockout-'.$role.'-'.$this->suffix.'@example.test';
        $command->password = $password;
        $command->isRoleAdmin = $admin;
        $command->signatureFilename = '';
        $user = $creator->createUser($command, $this->hasher);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function bootstrapCreator(): User
    {
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->write($user, 'id', Uuid::uuid6());
        $this->write($user, 'createdOn', new \DateTime());
        $this->write($user, 'createdBy', $user);
        $this->write($user, 'isActive', true);
        $this->write($user, 'organizations', new ArrayCollection());
        $this->write($user, 'username', 'creator-'.$this->suffix);
        $this->write($user, 'firstName', 'Creator');
        $this->write($user, 'lastName', 'User');
        $this->write($user, 'email', 'lockout-creator-'.$this->suffix.'@example.test');
        $this->write($user, 'mobile', null);
        $this->write($user, 'phone', null);
        $this->write($user, 'signatureFilename', '');
        $this->write($user, 'position', null);
        $this->write($user, 'totpEnabled', false);
        $this->write($user, 'totpSecret', null);
        $this->write($user, 'roles', ['ROLE_ADMIN']);
        $this->write($user, 'password', $this->hasher->hashPassword($user, self::FIXTURE_PASSWORD));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function write(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
            $this->assertInstanceOf(ReflectionClass::class, $class);
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
