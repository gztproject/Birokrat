<?php

namespace App\Tests\Web;

use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Sign-in must require the token rendered on the login form.
 *
 * These tests fail while form login accepts a password without that token.
 */
class LoginCsrfTest extends WebTestCase
{
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';
    private const SESSION_TOKEN_KEY = '_security_main';
    private const OTHER_INTENTION = 'delete';
    private const OTHER_INTENTION_SECRET = 'other-intention-secret';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $em = $container->get(EntityManagerInterface::class);
            $em->getConnection()->executeQuery('SELECT 1');
            (new SmokeFixtures(
                $em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
            // Earlier suites may have exhausted the shared IP login bucket.
            $container->get('cache.global_clearer')->clearPool('cache.rate_limiter');
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testSignInWithTheTokenFromTheSameSessionSucceeds(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->assertNotSame('', $token);

        $this->postSignIn(SmokeFixtures::USER_USERNAME, SmokeFixtures::USER_PASSWORD, $token);

        $this->assertSame(SmokeFixtures::USER_USERNAME, $this->sessionAccount());
        $this->assertSame(SmokeFixtures::USER_USERNAME, $this->accountOnNextVisit('/dashboard'));
        $this->assertSame(SmokeFixtures::USER_USERNAME, $this->accountOnNextVisit('/'));
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function tokensAnAttackerCanSupply(): iterable
    {
        yield 'omitted' => [null];
        yield 'empty' => [''];
        yield 'chosen by the attacker' => ['attacker-chosen-token'];
    }

    #[DataProvider('tokensAnAttackerCanSupply')]
    public function testCrossSiteSignInWithoutTheSessionTokenDoesNotSignIn(?string $token): void
    {
        $this->postSignIn(
            SmokeFixtures::USER_USERNAME,
            SmokeFixtures::USER_PASSWORD,
            $token,
            rememberMe: true,
            server: $this->crossSiteServer(),
        );

        $this->assertSubmittedAccountWasNotSignedIn(SmokeFixtures::USER_USERNAME);
    }

    public function testTokenCopiedFromAnotherSessionDoesNotSignIn(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $copiedToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->assertNotSame('', $copiedToken);
        $this->dropCookies($this->client->getCookieJar());
        $this->assertSame([], $this->client->getCookieJar()->all());

        $this->postSignIn(
            SmokeFixtures::USER_USERNAME,
            SmokeFixtures::USER_PASSWORD,
            $copiedToken,
            rememberMe: true,
            server: $this->crossSiteServer(),
        );

        $this->assertSubmittedAccountWasNotSignedIn(SmokeFixtures::USER_USERNAME);
    }

    public function testTokenForADifferentIntentionDoesNotSignIn(): void
    {
        $this->client->request('GET', '/login');
        $session = $this->client->getRequest()->getSession();
        $session->set('_csrf/'.self::OTHER_INTENTION, self::OTHER_INTENTION_SECRET);
        $session->save();

        $this->postSignIn(
            SmokeFixtures::USER_USERNAME,
            SmokeFixtures::USER_PASSWORD,
            self::OTHER_INTENTION_SECRET,
            rememberMe: true,
        );

        $this->assertSubmittedAccountWasNotSignedIn(SmokeFixtures::USER_USERNAME);
    }

    public function testSignInWithoutTheSessionTokenDoesNotReplaceAnExistingAccount(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->postSignIn(SmokeFixtures::ADMIN_USERNAME, SmokeFixtures::ADMIN_PASSWORD, $token);
        $this->assertSame(SmokeFixtures::ADMIN_USERNAME, $this->accountOnNextVisit('/dashboard'));

        $this->postSignIn(
            SmokeFixtures::USER_USERNAME,
            SmokeFixtures::USER_PASSWORD,
            'attacker-chosen-token',
            rememberMe: true,
            server: $this->crossSiteServer(),
        );

        $this->assertSubmittedAccountWasNotSignedIn(SmokeFixtures::USER_USERNAME);
        $this->assertNotSame(
            SmokeFixtures::USER_USERNAME,
            $this->accountOnNextVisit('/dashboard/invoice'),
            'The invoice list was opened as the account submitted without its sign-in token.'
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function postSignIn(string $username, string $password, ?string $csrfToken, bool $rememberMe = false, array $server = []): void
    {
        $parameters = [
            '_username' => $username,
            '_password' => $password,
        ];
        if (null !== $csrfToken) {
            $parameters['_csrf_token'] = $csrfToken;
        }
        if ($rememberMe) {
            $parameters['_remember_me'] = 'on';
        }

        $this->client->request('POST', '/login', $parameters, [], $server);
    }

    /**
     * @return array<string, string>
     */
    private function crossSiteServer(): array
    {
        return [
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_REFERER' => 'https://attacker.example/trap',
        ];
    }

    private function assertSubmittedAccountWasNotSignedIn(string $username): void
    {
        $rememberMe = $this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE);
        $sessionAccount = $this->sessionAccount();
        $nextVisit = $this->accountOnNextVisit('/dashboard');
        $homepage = $this->accountOnNextVisit('/');

        $this->assertFalse(
            null !== $rememberMe
            || $sessionAccount === $username
            || $nextVisit === $username
            || $homepage === $username,
            sprintf(
                'Sign-in without the sign-in token was accepted. Session account: %s. Dashboard account: %s. Homepage account: %s. Remember-me cookie: %s.',
                $sessionAccount ?? 'none',
                $nextVisit ?? 'none',
                $homepage ?? 'none',
                null !== $rememberMe ? 'set' : 'absent'
            )
        );
    }

    private function sessionAccount(): ?string
    {
        $request = $this->client->getRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $raw = $request->getSession()->get(self::SESSION_TOKEN_KEY);
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $token = unserialize($raw, ['allowed_classes' => true]);
        if (!$token instanceof TokenInterface) {
            return null;
        }

        $user = $token->getUser();
        if (!\is_object($user) || !method_exists($user, 'getUserIdentifier')) {
            return null;
        }

        return $user->getUserIdentifier();
    }

    private function accountOnNextVisit(string $path): ?string
    {
        $this->client->request('GET', $path);
        $content = (string) $this->client->getResponse()->getContent();
        if (!preg_match('/data-dashboard-layout-user-value="([^"]*)"/', $content, $match)) {
            return null;
        }

        return $match[1];
    }

    private function dropCookies(CookieJar $jar): void
    {
        foreach ($jar->all() as $cookie) {
            $jar->expire($cookie->getName(), $cookie->getPath() ?: '/', $cookie->getDomain() ?: null);
        }
    }
}
