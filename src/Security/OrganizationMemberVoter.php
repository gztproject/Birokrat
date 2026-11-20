<?php

namespace App\Security;

use App\Entity\Invoice\Invoice;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to a company's documents only to users who are members of that company.
 * Invoices belong to their issuer, ledger transactions to their organization.
 */
class OrganizationMemberVoter extends Voter
{
    public const MEMBER = 'ORGANIZATION_MEMBER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MEMBER
            && ($subject instanceof Organization || $subject instanceof Invoice || $subject instanceof Transaction);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $organization = match (true) {
            $subject instanceof Invoice => $subject->getIssuer(),
            $subject instanceof Transaction => $subject->getOrganization(),
            default => $subject,
        };

        return self::isMember($user, $organization);
    }

    private static function isMember(User $user, ?Organization $organization): bool
    {
        if ($organization === null) {
            return false;
        }

        $id = $organization->getId()->toString();
        foreach ($user->getOrganizations() as $membership) {
            if ($membership->getId()->toString() === $id) {
                return true;
            }
        }

        return false;
    }
}
