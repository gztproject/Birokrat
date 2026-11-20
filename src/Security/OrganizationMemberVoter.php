<?php

namespace App\Security;

use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to an organization, or to a supplier bill or ledger transaction
 * through the organization it belongs to, only to users linked to that organization.
 */
class OrganizationMemberVoter extends Voter
{
    public const MEMBER = 'ORGANIZATION_MEMBER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MEMBER
            && ($subject instanceof Organization || $subject instanceof IncomingInvoice || $subject instanceof Transaction);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $organization = match (true) {
            $subject instanceof IncomingInvoice => $subject->getRecepient(),
            $subject instanceof Transaction => $subject->getOrganization(),
            default => $subject,
        };
        if (!$organization instanceof Organization) {
            return false;
        }

        return $user->getOrganizations()->contains($organization);
    }
}
