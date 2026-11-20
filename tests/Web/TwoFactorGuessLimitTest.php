<?php

namespace App\Tests\Web;

use App\Entity\User\User;
use App\Security\Totp;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Spec for the two-factor challenge. These tests fail while a pending sign-in
 * accepts unlimited code guesses, and they pass once a small failure budget
 * locks that challenge until a fresh password login.
 *
 * The budget is five failed codes on one pending sign-in. A matching code
 * after that must not clear the pending flag. A code that already cleared it
 * must not clear it again inside the same acceptance window. The same budget
 * applies to disabling two-factor authentication, which is the other request
 * that checks a code against the account secret.
 */
final class TwoFactorGuessLimitTest extends WebTestCase
{
    private const FAILED_CODE_LIMIT = 5;

    /** Placeholder that only exists to satisfy the local password rules. */
    private const PLACEHOLDER_PASSWORD = 'placeholder-1!';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeQuery('SELECT 1');
            static::getContainer()->get('cache.global_clearer')->clearPool('cache.rate_limiter');
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testChallengeLocksUntilAFreshPasswordLogin(): void
    {
        $this->holdWindow();
        $secret = Totp::generateSecret();
        $username = $this->persistTotpUser($secret);
        $slice = (int) floor(time() / 30);
        $rejected = Totp::at($secret, $slice);
        $afterFreshLogin = $this->otherWindowCode($secret, $slice, $rejected);

        $this->signInToChallenge($username);
        for ($i = 0; $i < self::FAILED_CODE_LIMIT; ++$i) {
            $this->submitChallenge($this->wrongCode($secret, $i));
            $this->assertSecondFactorStillRequired();
        }

        $this->assertTrue(Totp::verify($secret, $rejected));
        $this->submitChallenge($rejected);
        $this->assertSecondFactorStillRequired();

        $this->client->request('GET', '/logout');
        $this->signInToChallenge($username);
        $this->assertTrue(Totp::verify($secret, $afterFreshLogin));
        $this->submitChallenge($afterFreshLogin);
        $this->assertDashboardOpened();
    }

    public function testAcceptedCodeIsRejectedOnTheNextPasswordLogin(): void
    {
        $this->holdWindow();
        $secret = Totp::generateSecret();
        $username = $this->persistTotpUser($secret);
        $code = Totp::at($secret, (int) floor(time() / 30));

        $this->signInToChallenge($username);
        $this->assertTrue(Totp::verify($secret, $code));
        $this->submitChallenge($code);
        $this->assertDashboardOpened();

        $this->client->request('GET', '/logout');
        $this->signInToChallenge($username);
        $this->assertTrue(
            Totp::verify($secret, $code),
            'The reused code has to still be inside the verifier window.'
        );
        $this->submitChallenge($code);
        $this->assertSecondFactorStillRequired();
    }

    public function testDisablingTotpLocksAfterRepeatedWrongCodes(): void
    {
        $this->holdWindow();
        $secret = Totp::generateSecret();
        $username = $this->persistTotpUser($secret);
        $started = (int) floor(time() / 30);
        $loginCode = Totp::at($secret, $started);

        $this->signInToChallenge($username);
        $this->submitChallenge($loginCode);
        $this->assertDashboardOpened();

        $disableSlice = $started + 2;
        $this->sleepUntilSlice($disableSlice);
        $disableCode = Totp::at($secret, $disableSlice);
        if ($disableCode === $loginCode || $disableCode === Totp::at($secret, $started - 1) || $disableCode === Totp::at($secret, $started + 1)) {
            $disableSlice = $started + 3;
            $this->sleepUntilSlice($disableSlice);
            $disableCode = Totp::at($secret, $disableSlice);
        }
        $this->assertTrue(Totp::verify($secret, $disableCode));

        for ($i = 0; $i < self::FAILED_CODE_LIMIT; ++$i) {
            $this->client->request('POST', '/user/2fa', [
                'code' => $this->wrongCode($secret, 100 + $i),
                'disable' => '1',
            ]);
            $this->assertTrue($this->reloadUser($username)->isTotpEnabled());
        }

        $this->assertTrue(Totp::verify($secret, $disableCode));
        $this->client->request('POST', '/user/2fa', [
            'code' => $disableCode,
            'disable' => '1',
        ]);

        $this->assertTrue(
            $this->reloadUser($username)->isTotpEnabled(),
            'A matching code after the failure limit must not disable two-factor authentication.'
        );
    }

    private function signInToChallenge(string $username): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => $username,
            '_password' => self::PLACEHOLDER_PASSWORD,
            '_csrf_token' => $token,
        ]);

        for ($i = 0; $i < 4 && $this->client->getResponse()->isRedirect(); ++$i) {
            $this->client->followRedirect();
        }

        $this->assertResponseIsSuccessful();
        $this->assertSame('/2fa', parse_url($this->client->getRequest()->getUri(), PHP_URL_PATH));
        $this->assertTrue((bool) $this->client->getRequest()->getSession()->get('2fa_pending'));
    }

    private function submitChallenge(string $code): void
    {
        $this->client->request('POST', '/2fa', ['code' => $code]);
    }

    private function assertSecondFactorStillRequired(): void
    {
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('~/(2fa|login)(?:$|[?#])~', $location, $location);
        $this->assertDoesNotMatchRegularExpression('~/dashboard(?:$|[?#])~', $location, $location);
    }

    private function assertDashboardOpened(): void
    {
        $this->assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('~/dashboard(?:$|[?#])~', $location, $location);
        $this->assertFalse((bool) $this->client->getRequest()->getSession()->get('2fa_pending'));

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('/dashboard', parse_url($this->client->getRequest()->getUri(), PHP_URL_PATH));
    }

    private function persistTotpUser(string $secret): string
    {
        $username = 'totp-'.bin2hex(random_bytes(6));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setProperty($user, 'id', Uuid::uuid6());
        $this->setProperty($user, 'createdOn', new \DateTime());
        $this->setProperty($user, 'createdBy', $user);
        $this->setProperty($user, 'isActive', true);
        $this->setProperty($user, 'organizations', new ArrayCollection());
        $this->setProperty($user, 'username', $username);
        $this->setProperty($user, 'firstName', 'Totp');
        $this->setProperty($user, 'lastName', 'Subject');
        $this->setProperty($user, 'email', $username.'@example.test');
        $this->setProperty($user, 'mobile', null);
        $this->setProperty($user, 'phone', null);
        $this->setProperty($user, 'signatureFilename', '');
        $this->setProperty($user, 'position', null);
        $this->setProperty($user, 'totpEnabled', true);
        $this->setProperty($user, 'totpSecret', $secret);
        $this->setProperty($user, 'roles', ['ROLE_USER']);
        $this->setProperty($user, 'password', $hasher->hashPassword($user, self::PLACEHOLDER_PASSWORD));

        $connection = $em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($user);
        $em->flush();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        return $username;
    }

    private function reloadUser(string $username): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function wrongCode(string $secret, int $salt): string
    {
        $slice = (int) floor(time() / 30);
        $valid = [];
        for ($i = -1; $i <= 1; ++$i) {
            $valid[] = Totp::at($secret, $slice + $i);
        }

        for ($n = 0; $n < 20; ++$n) {
            $candidate = str_pad((string) (($salt * 97 + $n * 13) % 1000000), 6, '0', STR_PAD_LEFT);
            if (!in_array($candidate, $valid, true)) {
                return $candidate;
            }
        }

        $this->fail('Could not choose a six-digit code outside the current window.');
    }

    private function otherWindowCode(string $secret, int $slice, string $excluded): string
    {
        foreach ([1, -1] as $offset) {
            $code = Totp::at($secret, $slice + $offset);
            if ($code !== $excluded) {
                return $code;
            }
        }

        $this->fail('The acceptance window did not yield a second distinct code.');
    }

    private function holdWindow(): void
    {
        $into = time() % 30;
        if ($into > 1) {
            sleep(30 - $into + 1);
        }
    }

    private function sleepUntilSlice(int $targetSlice): void
    {
        $deadline = time() + 120;
        while ((int) floor(time() / 30) < $targetSlice) {
            if (time() > $deadline) {
                $this->fail('Timed out waiting for authenticator time slice '.$targetSlice.'.');
            }
            sleep(1);
        }
    }

    private function setProperty(object $object, string $name, mixed $value): void
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
