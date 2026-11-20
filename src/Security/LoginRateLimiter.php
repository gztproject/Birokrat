<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Login throttling keyed by client address and by username plus client address.
 *
 * Unlike Symfony's DefaultLoginRateLimiter this does not implement the peekable
 * interface: every attempt takes its token before the password is checked, so
 * concurrent guesses cannot all pass the check before the first failure is counted.
 * A successful login only resets the username bucket; the address bucket keeps
 * counting so username variants cannot be used to reopen it.
 */
final class LoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $loginGlobalLimiter,
        private readonly RateLimiterFactoryInterface $loginLocalLimiter,
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function consume(Request $request): RateLimit
    {
        $global = $this->globalLimiter($request)->consume(1);
        $local = $this->localLimiter($request)->consume(1);

        if (!$global->isAccepted()) {
            return $global;
        }
        if (!$local->isAccepted()) {
            return $local;
        }

        return $global->getRemainingTokens() < $local->getRemainingTokens() ? $global : $local;
    }

    public function reset(Request $request): void
    {
        $this->localLimiter($request)->reset();
    }

    private function globalLimiter(Request $request): LimiterInterface
    {
        return $this->loginGlobalLimiter->create($this->hash((string) $request->getClientIp()));
    }

    private function localLimiter(Request $request): LimiterInterface
    {
        $username = (string) $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $username = preg_match('//u', $username) ? mb_strtolower($username, 'UTF-8') : strtolower($username);

        return $this->loginLocalLimiter->create($this->hash($username.'-'.$request->getClientIp()));
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
