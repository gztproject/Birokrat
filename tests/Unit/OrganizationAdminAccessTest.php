<?php

namespace App\Tests\Unit;

use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Security\OrganizationMemberVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

/**
 * Administrators may act on any company even when they are not listed as members.
 */
class OrganizationAdminAccessTest extends TestCase
{
    public function testAdministratorBelongsToOrganizationsWithoutMembership(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);
        $organization = $this->organization();

        self::assertTrue($admin->belongsTo($organization));
        self::assertTrue(OrganizationMemberVoter::isMember($admin, $organization));
    }

    public function testOrdinaryUserDoesNotBelongWithoutMembership(): void
    {
        $user = $this->user(['ROLE_USER']);
        $organization = $this->organization();

        self::assertFalse($user->belongsTo($organization));
        self::assertFalse(OrganizationMemberVoter::isMember($user, $organization));
    }

    public function testAdministratorCanFilterToAnyRequestedOrganizationId(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);
        $foreignId = Uuid::uuid6()->toString();

        self::assertSame([strtolower($foreignId)], $admin->getOrganizationIdsFilteredBy($foreignId));
    }

    private function user(array $roles): User
    {
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'roles', $roles);

        return $user;
    }

    private function organization(): Organization
    {
        $organization = (new ReflectionClass(Organization::class))->newInstanceWithoutConstructor();
        $this->set($organization, 'id', Uuid::uuid6());

        return $organization;
    }

    private function set(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
            self::assertNotFalse($reflection);
        }
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
