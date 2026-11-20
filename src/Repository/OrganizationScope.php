<?php

namespace App\Repository;

use App\Entity\Organization\Organization;
use Doctrine\ORM\QueryBuilder;

trait OrganizationScope
{
    /**
     * An empty set matches nothing, so a user without a company link sees no company data.
     *
     * @param iterable<Organization> $organizations
     */
    private function restrictToOrganizations(QueryBuilder $qb, string $field, iterable $organizations): QueryBuilder
    {
        $scope = [];
        foreach ($organizations as $organization) {
            if (!$organization instanceof Organization) {
                throw new \InvalidArgumentException('Organization scope accepts only Organization entities.');
            }
            $scope[] = $organization;
        }

        if ($scope === []) {
            return $qb->andWhere('1 = 0');
        }

        return $qb->andWhere($field.' IN (:scopeOrganizations)')->setParameter('scopeOrganizations', $scope);
    }
}
