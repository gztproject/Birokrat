<?php

namespace App\Tests\Web;

use App\Entity\User\CreateUserCommand;
use App\Entity\User\UpdateUserCommand;
use App\Entity\User\User;
use App\Form\User\UserType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Administrator checkbox on the user edit form.
 *
 * These tests fail while saving an existing user ignores isRoleAdmin.
 */
class UserAdministratorFlagTest extends WebTestCase
{
    private const PLACEHOLDER_PASSWORD = 'placeholder-pass-1!';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testAdminEditClearsAdministratorFlagAndClosesAdminPages(): void
    {
        $actor = $this->persistUser('actor-clear-admin', true);
        $target = $this->persistUser('target-clear-admin', true, $actor);
        $actorId = $actor->getId();
        $targetId = $target->getId();

        $this->submitProfile($actor, '/admin/user/'.$targetId->toString().'/edit', false, 'Cleared');

        $this->assertSame('Cleared', $this->reload($targetId)->getFirstName());
        $this->assertTrue($this->reload($actorId)->getIsRoleAdmin());
        $this->assertAdministratorRemoved($targetId);
    }

    public function testProfileEditRouteClearsAdministratorFlagAndClosesAdminPages(): void
    {
        $actor = $this->persistUser('actor-clear-profile', true);
        $target = $this->persistUser('target-clear-profile', true, $actor);
        $targetId = $target->getId();

        $this->submitProfile($actor, '/user/'.$targetId->toString().'/edit', false, 'ClearedProfile');

        $this->assertSame('ClearedProfile', $this->reload($targetId)->getFirstName());
        $this->assertAdministratorRemoved($targetId);
    }

    public function testAdminEditGrantsAdministratorFlagAndOpensAdminPages(): void
    {
        $actor = $this->persistUser('actor-grant-admin', true);
        $target = $this->persistUser('target-grant-admin', false, $actor);
        $targetId = $target->getId();

        $this->submitProfile($actor, '/admin/user/'.$targetId->toString().'/edit', true, 'Promoted');

        $this->assertSame('Promoted', $this->reload($targetId)->getFirstName());
        $this->assertAdministratorGranted($targetId);
    }

    public function testProfileEditRouteGrantsAdministratorFlagAndOpensAdminPages(): void
    {
        $actor = $this->persistUser('actor-grant-profile', true);
        $target = $this->persistUser('target-grant-profile', false, $actor);
        $targetId = $target->getId();

        $this->submitProfile($actor, '/user/'.$targetId->toString().'/edit', true, 'PromotedProfile');

        $this->assertSame('PromotedProfile', $this->reload($targetId)->getFirstName());
        $this->assertAdministratorGranted($targetId);
    }

    public function testSelfEditIgnoresAdministratorCheckbox(): void
    {
        $user = $this->persistUser('self-edit-user', false);
        $userId = $user->getId();

        $this->submitProfile($user, '/user/'.$userId->toString().'/edit', true, 'SelfEdit');

        $reloaded = $this->reload($userId);
        $this->assertSame('SelfEdit', $reloaded->getFirstName());
        $this->assertFalse($reloaded->getIsRoleAdmin());
        $this->assertAdminPagesDenied($reloaded);
    }

    public function testAdminSelfEditIgnoresClearingAdministratorCheckbox(): void
    {
        $admin = $this->persistUser('self-edit-admin', true);
        $adminId = $admin->getId();

        $this->submitProfile($admin, '/admin/user/'.$adminId->toString().'/edit', false, 'SelfAdmin');

        $reloaded = $this->reload($adminId);
        $this->assertSame('SelfAdmin', $reloaded->getFirstName());
        $this->assertTrue($reloaded->getIsRoleAdmin());
        $this->assertAdminPagesOpen($reloaded);
    }

    public function testSavingClearedCheckboxStoresTheBoundAdministratorValue(): void
    {
        $actor = $this->persistUser('actor-bind-admin', true);
        $target = $this->persistUser('target-bind-admin', true, $actor);
        $targetId = $target->getId();

        $this->client->loginUser($actor);
        $crawler = $this->client->request('GET', '/admin/user/'.$targetId->toString().'/edit');
        $this->assertResponseIsSuccessful();

        $form = $this->profileForm($crawler);
        $checkboxName = $this->checkboxName($crawler);
        $this->assertTrue($form[$checkboxName]->hasValue());
        $form[$checkboxName]->untick();
        $this->client->submit($form);

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect(), 'Saving the cleared administrator checkbox did not redirect.');

        $posted = $this->client->getRequest()->request->all('user');
        $this->assertArrayNotHasKey('isRoleAdmin', $posted);

        // A missing checkbox must bind to false, and the edit action must persist that.
        $bound = new UpdateUserCommand();
        $bound->isRoleAdmin = true;
        $factory = static::getContainer()->get('form.factory');
        $boundForm = $factory->create(UserType::class, $bound, ['csrf_protection' => false]);
        $boundForm->submit($posted);
        $this->assertFalse($bound->isRoleAdmin);
        $this->assertFalse($this->reload($targetId)->getIsRoleAdmin());
    }

    private function submitProfile(User $actor, string $path, bool $administrator, string $firstName): void
    {
        $this->client->loginUser($actor);
        $crawler = $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful('Edit page did not render: '.$this->client->getResponse()->getStatusCode());

        $form = $this->profileForm($crawler);
        $checkboxName = $this->checkboxName($crawler);
        if ($administrator) {
            $this->assertFalse($form[$checkboxName]->hasValue());
            $form[$checkboxName]->tick();
        } else {
            $this->assertTrue($form[$checkboxName]->hasValue());
            $form[$checkboxName]->untick();
        }

        $firstNameField = $crawler->filter('input[name$="[firstName]"]')->attr('name');
        $form[$firstNameField]->setValue($firstName);
        $this->client->submit($form);

        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isRedirect(),
            'Saving the user edit form did not redirect. Status '.$response->getStatusCode().' body '.substr((string) $response->getContent(), 0, 1500)
        );
    }

    private function profileForm(\Symfony\Component\DomCrawler\Crawler $crawler): \Symfony\Component\DomCrawler\Form
    {
        $formNode = $crawler->filter('form')->reduce(
            static fn (\Symfony\Component\DomCrawler\Crawler $node): bool => $node->filter('input[name$="[isRoleAdmin]"]')->count() > 0
        );

        return $formNode->form();
    }

    private function checkboxName(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $name = $crawler->filter('input[name$="[isRoleAdmin]"]')->attr('name');
        $this->assertNotNull($name);

        return $name;
    }

    private function assertAdministratorRemoved(UuidInterface $id): void
    {
        $user = $this->reload($id);
        $this->assertAdminPagesDenied($user);
        $user = $this->reload($id);
        $this->assertFalse($user->getIsRoleAdmin());
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    private function assertAdministratorGranted(UuidInterface $id): void
    {
        $user = $this->reload($id);
        $this->assertAdminPagesOpen($user);
        $user = $this->reload($id);
        $this->assertTrue($user->getIsRoleAdmin());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    private function assertAdminPagesDenied(User $user): void
    {
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/user');
        $this->assertResponseStatusCodeSame(403);
    }

    private function assertAdminPagesOpen(User $user): void
    {
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/user');
        $this->assertResponseIsSuccessful();
    }

    private function reload(UuidInterface $id): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->find(User::class, $id);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function persistUser(string $username, bool $administrator, ?User $creator = null): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $username .= '-'.$suffix;

        if (!$creator instanceof User) {
            $creator = $this->newUser($username, $administrator, $hasher);
            $em->persist($creator);
            $em->flush();

            return $creator;
        }

        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Target';
        $command->lastName = 'Account';
        $command->email = $username.'@example.test';
        $command->password = self::PLACEHOLDER_PASSWORD;
        $command->isRoleAdmin = $administrator;
        $command->signatureFilename = '';
        $user = $creator->createUser($command, $hasher);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function newUser(string $username, bool $administrator, UserPasswordHasherInterface $hasher): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Ada');
        $this->set($user, 'lastName', 'Admin');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Clerk');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', [$administrator ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $this->set($user, 'password', $hasher->hashPassword($user, self::PLACEHOLDER_PASSWORD));

        return $user;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
