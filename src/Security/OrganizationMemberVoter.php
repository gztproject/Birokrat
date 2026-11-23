<?php

namespace App\Security;

use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Invoice\Invoice;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\LunchExpenseBundle;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to a company-owned document to members of that company and to administrators.
 */
class OrganizationMemberVoter extends Voter
{
    public const MEMBER = 'ORGANIZATION_MEMBER';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MEMBER
            && (
                $subject instanceof Organization
                || $subject instanceof Invoice
                || $subject instanceof IncomingInvoice
                || $subject instanceof Transaction
                || $subject instanceof TravelExpense
                || $subject instanceof TravelExpenseBundle
                || $subject instanceof LunchExpense
                || $subject instanceof LunchExpenseBundle
            );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $organization = match (true) {
            $subject instanceof Invoice => $subject->getIssuer(),
            $subject instanceof IncomingInvoice => $subject->getRecepient(),
            $subject instanceof Transaction,
            $subject instanceof TravelExpense,
            $subject instanceof TravelExpenseBundle,
            $subject instanceof LunchExpense,
            $subject instanceof LunchExpenseBundle => $subject->getOrganization(),
            default => $subject,
        };

        return self::isMember($user, $organization instanceof Organization ? $organization : null);
    }

    public static function isMember(User $user, ?Organization $organization): bool
    {
        if ($organization === null) {
            return false;
        }

        if ($user->getIsRoleAdmin()) {
            return true;
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
