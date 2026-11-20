<?php

namespace App\Security;

use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to an organization only to its members and to administrators.
 */
class OrganizationVoter extends Voter
{
    public const SHOW = 'organization_show';
    public const EDIT = 'organization_edit';
    public const DELETE = 'organization_delete';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Organization && \in_array($attribute, [self::SHOW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $currentUserId = $currentUser->getId();

        return $subject->getUsers()->exists(
            static fn ($key, User $member): bool => $member->getId()->equals($currentUserId)
        );
    }
}
