<?php

namespace App\Security;

use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\LunchExpenseBundle;
use App\Entity\Organization\Organization;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to a company-owned document only to users who belong to that company.
 */
class OrganizationMemberVoter extends Voter
{
    public const MEMBER = 'organization_member';

    private const SUBJECTS = [
        Organization::class,
        TravelExpense::class,
        TravelExpenseBundle::class,
        LunchExpense::class,
        LunchExpenseBundle::class,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute !== self::MEMBER || !\is_object($subject)) {
            return false;
        }
        foreach (self::SUBJECTS as $class) {
            if ($subject instanceof $class) {
                return true;
            }
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $organization = $subject instanceof Organization ? $subject : $subject->getOrganization();

        return self::isMember($user, $organization);
    }

    public static function isMember(User $user, ?Organization $organization): bool
    {
        if ($organization === null) {
            return false;
        }

        foreach ($user->getOrganizations() as $membership) {
            if ($membership->getId()->equals($organization->getId())) {
                return true;
            }
        }

        return false;
    }
}
