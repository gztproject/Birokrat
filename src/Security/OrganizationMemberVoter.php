<?php

namespace App\Security;

use App\Entity\Invoice\Invoice;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to an organization, or to a sales invoice through its issuer,
 * only to users linked to that organization.
 */
class OrganizationMemberVoter extends Voter
{
    public const MEMBER = 'ORGANIZATION_MEMBER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MEMBER && ($subject instanceof Organization || $subject instanceof Invoice);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $organization = $subject instanceof Invoice ? $subject->getIssuer() : $subject;
        if (!$organization instanceof Organization) {
            return false;
        }

        $id = $organization->getId();
        foreach ($user->getOrganizations() as $membership) {
            if ($membership->getId()->equals($id)) {
                return true;
            }
        }

        return false;
    }
}
