<?php

namespace App\Tests\Support;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Invoice;
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

final class SmokeFixtures
{
    public const ADMIN_USERNAME = 'admin';
    public const ADMIN_PASSWORD = 'Admin1!';
    public const USER_USERNAME = 'tester';
    public const USER_PASSWORD = 'User1!a';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return array{admin: User, user: User, organization: Organization, partner: Partner, invoice: Invoice}
     */
    public function load(): array
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['username' => self::ADMIN_USERNAME]);
        if ($existing instanceof User) {
            $org = $existing->getOrganizations()->first() ?: $this->em->getRepository(Organization::class)->findOneBy([]);
            $partner = $this->em->getRepository(Partner::class)->findOneBy([]);
            $invoice = $this->em->getRepository(Invoice::class)->findOneBy([]);
            $user = $this->em->getRepository(User::class)->findOneBy(['username' => self::USER_USERNAME]);

            return [
                'admin' => $existing,
                'user' => $user ?? $existing,
                'organization' => $org,
                'partner' => $partner,
                'invoice' => $invoice,
            ];
        }

        $admin = $this->bootstrapUser(self::ADMIN_USERNAME, self::ADMIN_PASSWORD, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $userCommand = new CreateUserCommand();
        $userCommand->username = self::USER_USERNAME;
        $userCommand->firstName = 'Test';
        $userCommand->lastName = 'User';
        $userCommand->email = 'tester@example.test';
        $userCommand->password = self::USER_PASSWORD;
        $userCommand->isRoleAdmin = false;
        $userCommand->signatureFilename = '';
        $user = $admin->createUser($userCommand, $this->hasher);
        $this->em->persist($user);

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

        $orgCommand = new CreateOrganizationCommand();
        $orgCommand->code = '1';
        $orgCommand->name = 'Issuer d.o.o.';
        $orgCommand->shortName = 'Issuer';
        $orgCommand->taxNumber = '12345678';
        $orgCommand->taxable = true;
        $orgCommand->address = $address;
        $orgCommand->accountNumber = 'SI56031001001001234';
        $orgCommand->bic = 'LJBASI2X';
        $organization = $admin->createOrganization($orgCommand);
        $admin->addOrganization($organization, $admin);
        $user->addOrganization($organization, $admin);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = '2';
        $partnerCommand->name = 'Partner d.o.o.';
        $partnerCommand->shortName = 'Partner';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = true;
        $partner = $admin->createPartner($partnerCommand);

        $invoiceCommand = new CreateInvoiceCommand();
        $invoiceCommand->dateOfIssue = new \DateTime('today');
        $invoiceCommand->dateServiceRenderedFrom = new \DateTime('-7 days');
        $invoiceCommand->dateServiceRenderedTo = new \DateTime('-1 days');
        $invoiceCommand->dueDate = new \DateTime('+10 days');
        $invoiceCommand->discount = 0;
        $invoiceCommand->number = 'TST-2026-0001';
        $invoiceCommand->issuer = $organization;
        $invoiceCommand->recepient = $partner;
        $invoice = $admin->createInvoice($invoiceCommand);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Consulting';
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = 100;
        $item->discount = 0;
        $invoiceItem = $invoice->createInvoiceItem($item);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($organization);
        $this->em->persist($organization->getOrganizationSettings());
        $this->em->persist($partner);
        $this->em->persist($invoice);
        $this->em->persist($invoiceItem);
        $this->em->flush();

        return [
            'admin' => $admin,
            'user' => $user,
            'organization' => $organization,
            'partner' => $partner,
            'invoice' => $invoice,
        ];
    }

    private function bootstrapUser(string $username, string $password, bool $admin): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
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
        $this->set($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $this->set($user, 'password', $this->hasher->hashPassword($user, $password));

        return $user;
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
