<?php

namespace App\Tests\Support;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UploadAuthorizationFixtures
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return array{admin: User, other: User, organization: Organization, partner: Partner, debit: Konto}
     */
    public function load(string $adminUsername, string $otherUsername): array
    {
        $admin = $this->bootstrapUser($adminUsername, SmokeFixtures::ADMIN_PASSWORD, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $otherCommand = new CreateUserCommand();
        $otherCommand->username = $otherUsername;
        $otherCommand->firstName = 'Other';
        $otherCommand->lastName = 'User';
        $otherCommand->email = $otherUsername.'@example.test';
        $otherCommand->password = SmokeFixtures::USER_PASSWORD;
        $otherCommand->isRoleAdmin = false;
        $otherCommand->signatureFilename = '';
        $other = $admin->createUser($otherCommand, $this->hasher);
        $this->em->persist($other);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $admin);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $admin);

        $suffix = substr($adminUsername, -6);
        $orgCommand = new CreateOrganizationCommand();
        $orgCommand->code = 'U'.$suffix;
        $orgCommand->name = 'Upload Issuer '.$suffix;
        $orgCommand->shortName = 'Upload';
        $orgCommand->taxNumber = '12345678';
        $orgCommand->taxable = true;
        $orgCommand->address = $address;
        $orgCommand->accountNumber = 'SI56031001001001234';
        $orgCommand->bic = 'LJBASI2X';
        $organization = $admin->createOrganization($orgCommand);
        $admin->addOrganization($organization, $admin);
        $other->addOrganization($organization, $admin);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'P'.$suffix;
        $partnerCommand->name = 'Upload Supplier '.$suffix;
        $partnerCommand->shortName = 'Supplier';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = false;
        $partnerCommand->isSupplier = true;
        $partner = $admin->createPartner($partnerCommand);

        $class = $this->kontoClass($admin, 4, 'Costs');
        $category = $this->kontoCategory($admin, $class, 40, 'Material');
        $debit = $this->konto($admin, $category, 400, 'Material cost');
        $credit = $this->konto($admin, $category, 220, 'Suppliers');

        $settings = $organization->getOrganizationSettings();
        $this->set($settings, 'ReceivedIncomingInvoiceDebit', $debit);
        $this->set($settings, 'ReceivedHomeIncomingInvoiceCredit', $credit);
        $this->set($settings, 'ReceivedForeignIncomingInvoiceCredit', $credit);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($organization);
        $this->em->persist($settings);
        $this->em->persist($partner);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);
        $this->em->flush();

        return [
            'admin' => $admin,
            'other' => $other,
            'organization' => $organization,
            'partner' => $partner,
            'debit' => $debit,
        ];
    }

    private function bootstrapUser(string $username, string $password, bool $admin): User
    {
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', $admin ? 'Admin' : 'Test');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', $admin ? 'Administrator' : 'Tester');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $this->set($user, 'password', $this->hasher->hashPassword($user, $password));

        return $user;
    }

    private function kontoClass(User $user, int $number, string $name): KontoClass
    {
        $class = (new ReflectionClass(KontoClass::class))->newInstanceWithoutConstructor();
        $this->set($class, 'id', Uuid::uuid6());
        $this->set($class, 'createdOn', new \DateTime());
        $this->set($class, 'createdBy', $user);
        $this->set($class, 'categories', new ArrayCollection());
        $this->set($class, 'number', $number);
        $this->set($class, 'name', $name);

        return $class;
    }

    private function kontoCategory(User $user, KontoClass $class, int $number, string $name): KontoCategory
    {
        $category = (new ReflectionClass(KontoCategory::class))->newInstanceWithoutConstructor();
        $this->set($category, 'id', Uuid::uuid6());
        $this->set($category, 'createdOn', new \DateTime());
        $this->set($category, 'createdBy', $user);
        $this->set($category, 'kontos', new ArrayCollection());
        $this->set($category, 'class', $class);
        $this->set($category, 'number', $number);
        $this->set($category, 'name', $name);

        return $category;
    }

    private function konto(User $user, KontoCategory $category, int $number, string $name): Konto
    {
        $konto = (new ReflectionClass(Konto::class))->newInstanceWithoutConstructor();
        $this->set($konto, 'id', Uuid::uuid6());
        $this->set($konto, 'createdOn', new \DateTime());
        $this->set($konto, 'createdBy', $user);
        $this->set($konto, 'category', $category);
        $this->set($konto, 'isActive', true);
        $this->set($konto, 'type', 0);
        $this->set($konto, 'number', $number);
        $this->set($konto, 'name', $name);

        return $konto;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
