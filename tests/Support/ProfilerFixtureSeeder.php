<?php

namespace App\Tests\Support;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfilerFixtureSeeder
{
    public const USERNAME = 'profiler-victim';
    public const PASSWORD = 'placeholder-password-1!';
    public const TAX_NUMBER = '44556677';
    public const ACCOUNT_NUMBER = 'SI99PROFILER0001';
    public const INVOICE_NUMBER = '20264455';

    public function seed(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): array
    {
        $admin = $this->bootstrapRootUser($hasher);
        $em->persist($admin);
        $em->flush();

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
        $orgCommand->accountNumber = 'SI56000000000000000';
        $orgCommand->bic = 'LJBASI2X';
        $organization = $admin->createOrganization($orgCommand);
        $admin->addOrganization($organization, $admin);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = '2';
        $partnerCommand->name = 'Profiler Partner d.o.o.';
        $partnerCommand->shortName = 'Profiler Partner';
        $partnerCommand->taxNumber = self::TAX_NUMBER;
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = true;
        $partnerCommand->accountNumber = self::ACCOUNT_NUMBER;
        $partnerCommand->bic = 'LJBASI2X';
        $partner = $admin->createPartner($partnerCommand);

        $invoiceCommand = new CreateInvoiceCommand();
        $invoiceCommand->dateOfIssue = new \DateTime('today');
        $invoiceCommand->dateServiceRenderedFrom = new \DateTime('-7 days');
        $invoiceCommand->dateServiceRenderedTo = new \DateTime('-1 days');
        $invoiceCommand->dueDate = new \DateTime('+10 days');
        $invoiceCommand->discount = 0;
        $invoiceCommand->number = self::INVOICE_NUMBER;
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

        $em->persist($country);
        $em->persist($post);
        $em->persist($address);
        $em->persist($organization);
        $em->persist($organization->getOrganizationSettings());
        $em->persist($partner);
        $em->persist($invoice);
        $em->persist($invoiceItem);
        $em->flush();

        return [
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'tax_number' => self::TAX_NUMBER,
            'account_number' => self::ACCOUNT_NUMBER,
            'invoice_number' => self::INVOICE_NUMBER,
            'partner_id' => $partner->getId()->toString(),
            'partner_name' => 'Profiler Partner d.o.o.',
            'partner_short_name' => 'Profiler Partner',
        ];
    }

    private function bootstrapRootUser(UserPasswordHasherInterface $hasher): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', self::USERNAME);
        $this->set($user, 'firstName', 'Profiler');
        $this->set($user, 'lastName', 'Victim');
        $this->set($user, 'email', 'profiler-victim@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Tester');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', ['ROLE_USER']);
        $this->set($user, 'password', $hasher->hashPassword($user, self::PASSWORD));

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
