<?php

namespace App\Security;

use App\Entity\User\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Checks a code against a user's enabled TOTP secret. Every check holds a row
 * lock on the user, so concurrent requests cannot share one failure budget or
 * accept the same code twice.
 */
final class TotpVerifier
{
    /** Failed codes allowed on one password sign-in before it has to be repeated. */
    public const MAX_FAILURES_PER_SIGN_IN = 5;

    public const FAILED_ATTEMPTS_SESSION_KEY = '2fa_failed_attempts';

    /** Consecutive failed codes across all sign-ins before checks are delayed. */
    private const FREE_ACCOUNT_FAILURES = 10;

    private const BASE_DELAY_SECONDS = 60;

    private const MAX_DELAY_SECONDS = 86400;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param (callable(User): void)|null $onAccepted runs in the same locked transaction as the check
     */
    public function verify(User $user, string $code, ?callable $onAccepted = null): TotpVerdict
    {
        return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($user, $code, $onAccepted): TotpVerdict {
            $em->refresh($user, LockMode::PESSIMISTIC_WRITE);
            if (!$user->isTotpEnabled()) {
                return TotpVerdict::Rejected;
            }

            $now = new \DateTimeImmutable();
            if ($this->isThrottled($user, $now)) {
                return TotpVerdict::Throttled;
            }

            $slice = Totp::matchingSlice((string) $user->getTotpSecret(), $code, 1, $now->getTimestamp());
            if ($slice === null || !$user->consumeTotpSlice($slice)) {
                $user->recordTotpFailure($now);

                return TotpVerdict::Rejected;
            }

            if ($onAccepted !== null) {
                $onAccepted($user);
            }

            return TotpVerdict::Accepted;
        });
    }

    /**
     * Counts a rejected code against the current sign-in and returns true once
     * the sign-in has used up its budget.
     */
    public function recordSignInFailure(SessionInterface $session): bool
    {
        $failures = (int) $session->get(self::FAILED_ATTEMPTS_SESSION_KEY, 0) + 1;
        $session->set(self::FAILED_ATTEMPTS_SESSION_KEY, $failures);

        return $failures >= self::MAX_FAILURES_PER_SIGN_IN;
    }

    private function isThrottled(User $user, \DateTimeImmutable $now): bool
    {
        $failures = $user->getTotpFailedAttempts();
        $lastFailure = $user->getTotpLastFailureAt();
        if ($failures < self::FREE_ACCOUNT_FAILURES || $lastFailure === null) {
            return false;
        }

        $exponent = min($failures - self::FREE_ACCOUNT_FAILURES, 20);
        $delay = min(self::MAX_DELAY_SECONDS, self::BASE_DELAY_SECONDS * (2 ** $exponent));

        return $now->getTimestamp() < $lastFailure->getTimestamp() + $delay;
    }
}
