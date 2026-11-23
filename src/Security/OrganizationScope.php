<?php

namespace App\Security;

use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Repository\Organization\OrganizationRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves which organization ids a signed-in user may query.
 * Administrators see every company unless they pick one in a filter.
 */
final class OrganizationScope
{
    public function __construct(
        private readonly Security $security,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @return string[]
     */
    public function ids(?string $requestedId): array
    {
        $requestedId = $requestedId === '' ? null : $requestedId;
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            if ($requestedId !== null) {
                return [strtolower($requestedId)];
            }

            return array_map(
                static fn (Organization $organization): string => $organization->getId()->toString(),
                $this->organizations->findAll(),
            );
        }

        return $user->getOrganizationIdsFilteredBy($requestedId);
    }

    /**
     * @return Organization[]
     */
    public function organizations(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->organizations->findAll();
        }

        return $user->getOrganizations()->toArray();
    }
}
