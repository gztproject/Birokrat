<?php

namespace App\Tests\Support;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
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

/**
 * Two ordinary users, each a member of a different company, and invoices issued by the victim company.
 */
final class InvoiceAuthorizationWorld
{
    private static int $clock = 0;

    public string $password;
    public string $suffix;
    public User $attacker;
    public User $owner;
    public Organization $victimOrg;
    public Organization $attackerOrg;
    public string $victimOrgName;
    public string $attackerOrgName;
    public string $victimCustomer;
    public string $victimShort;
    public string $attackerShort;
    public string $victimAccount;
    public string $victimDraftId;
    public string $victimDraftNumber;
    public string $victimIssuedId;
    public string $victimIssuedNumber;
    public string $attackerDraftId;
    public string $attackerDraftNumber;
    public string $attackerIssuedId;
    public string $attackerIssuedNumber;
    public string $victimPrice;
    public int $victimDueDays = 47;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(): self
    {
        $this->suffix = bin2hex(random_bytes(4));
        // Digit + special + enough hex to clear User::MIN_PASSWORD_LENGTH (12).
        $this->password = 'p'.random_int(0, 9).'!'.bin2hex(random_bytes(5));
        $serial = sprintf('%04d', random_int(1000, 9999));

        $maker = $this->bootstrap('maker'.$this->suffix, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($maker);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $this->attacker = $this->user($maker, 'attacker'.$this->suffix, false);
        $this->owner = $this->user($maker, 'owner'.$this->suffix, false);
        $this->em->persist($this->attacker);
        $this->em->persist($this->owner);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $maker->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = substr($serial, 0, 4);
        $postCommand->codeInternational = 'SI-'.$postCommand->code;
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $maker);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $maker);

        $this->victimAccount = 'SI56'.str_pad((string) random_int(0, 999999999999999), 15, '0', STR_PAD_LEFT);
        $this->victimOrgName = 'Victim Issuer '.$this->suffix;
        $this->attackerOrgName = 'Attacker Issuer '.$this->suffix;
        $this->victimOrg = $this->organization($maker, 'V'.$serial, $this->victimOrgName, 'Vorg'.$this->suffix, $this->victimAccount, $address);
        $this->attackerOrg = $this->organization($maker, 'A'.$serial, $this->attackerOrgName, 'Aorg'.$this->suffix, 'SI56000000000000000', $address);
        $this->owner->addOrganization($this->victimOrg, $maker);
        $this->attacker->addOrganization($this->attackerOrg, $maker);

        $this->victimCustomer = 'Victim Customer '.$this->suffix;
        $this->victimShort = 'Vic'.$this->suffix;
        $this->attackerShort = 'Atk'.$this->suffix;
        $victimPartner = $this->partner($maker, 'P'.$serial, $this->victimCustomer, $this->victimShort, $address);
        $attackerPartner = $this->partner($maker, 'Q'.$serial, 'Attacker Customer '.$this->suffix, $this->attackerShort, $address);

        [$debit, $credit] = $this->kontos($maker);
        $this->configure($this->victimOrg, $debit, $credit, 'VQ', $this->victimDueDays);
        $this->configure($this->attackerOrg, $debit, $credit, 'AQ', 11);

        $this->victimDraftNumber = 'D-2999-'.$serial;
        $this->victimIssuedNumber = 'I-2999-'.$serial;
        $this->attackerDraftNumber = 'E-2999-'.$serial;
        $this->attackerIssuedNumber = 'F-2999-'.$serial;
        $this->victimPrice = '8'.$serial.'.65';

        $victimDraft = $this->invoice($this->owner, $this->victimOrg, $victimPartner, $this->victimDraftNumber, '100', null);
        self::$clock++;
        $victimIssuedOn = (new \DateTime('5000-01-01'))->modify('+'.self::$clock.' days');
        $victimIssued = $this->invoice($this->owner, $this->victimOrg, $victimPartner, $this->victimIssuedNumber, $this->victimPrice, $victimIssuedOn);
        $attackerDraft = $this->invoice($this->attacker, $this->attackerOrg, $attackerPartner, $this->attackerDraftNumber, '50', null);
        $attackerIssued = $this->invoice($this->attacker, $this->attackerOrg, $attackerPartner, $this->attackerIssuedNumber, '60', new \DateTime('2999-12-31'));

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($this->victimOrg);
        $this->em->persist($this->victimOrg->getOrganizationSettings());
        $this->em->persist($this->attackerOrg);
        $this->em->persist($this->attackerOrg->getOrganizationSettings());
        $this->em->persist($victimPartner);
        $this->em->persist($attackerPartner);
        foreach ([$victimDraft, $victimIssued, $attackerDraft, $attackerIssued] as $invoice) {
            $this->em->persist($invoice);
            foreach ($invoice->getInvoiceItems() as $item) {
                $this->em->persist($item);
            }
        }
        $this->em->flush();

        $this->victimDraftId = $victimDraft->getId()->toString();
        $this->victimIssuedId = $victimIssued->getId()->toString();
        $this->attackerDraftId = $attackerDraft->getId()->toString();
        $this->attackerIssuedId = $attackerIssued->getId()->toString();
        $this->victimIssuedNumber = $victimIssued->getNumber();
        $this->attackerIssuedNumber = $attackerIssued->getNumber();

        return $this;
    }

    private function user(User $maker, string $username, bool $admin): User
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = $admin ? 'Admin' : 'Ordinary';
        $command->lastName = 'User';
        $command->email = $username.'@example.test';
        $command->password = $this->password;
        $command->isRoleAdmin = $admin;
        $command->signatureFilename = '';

        return $maker->createUser($command, $this->hasher);
    }

    private function organization(User $maker, string $code, string $name, string $short, string $account, object $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $short;
        $command->taxNumber = substr($code, 0, 8);
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = $account;
        $command->bic = 'TESTSI2X';

        return $maker->createOrganization($command);
    }

    private function partner(User $maker, string $code, string $name, string $short, object $address): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $short;
        $command->taxNumber = substr($code, 0, 8);
        $command->taxable = true;
        $command->address = $address;
        $command->isClient = true;
        $command->isSupplier = false;

        return $maker->createPartner($command);
    }

    /**
     * @return array{0: Konto, 1: Konto}
     */
    private function kontos(User $maker): array
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 1;
        $classCommand->name = 'Class '.$this->suffix;
        $classCommand->description = 'class';
        $class = new KontoClass($classCommand, $maker);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 10;
        $categoryCommand->name = 'Category '.$this->suffix;
        $categoryCommand->description = 'category';
        $category = new KontoCategory($categoryCommand, $class, $maker);

        $debit = $this->konto($category, $maker, 100, 'Debit '.$this->suffix, KontoTypes::active);
        $credit = $this->konto($category, $maker, 101, 'Credit '.$this->suffix, KontoTypes::passive);
        $this->em->persist($class);
        $this->em->persist($category);
        $this->em->persist($debit);
        $this->em->persist($credit);

        return [$debit, $credit];
    }

    private function konto(KontoCategory $category, User $maker, int $number, string $name, int $type): Konto
    {
        $command = new class extends CreateKontoCommand {};
        $command->number = $number;
        $command->name = $name;
        $command->description = $name;
        $command->isActive = true;
        $command->type = $type;

        return $category->createKonto($command, $maker);
    }

    private function configure(Organization $organization, Konto $debit, Konto $credit, string $prefix, int $dueDays): void
    {
        $settings = $organization->getOrganizationSettings();
        $this->set($settings, 'invoicePrefix', $prefix);
        $this->set($settings, 'defaultPaymentDueIn', $dueDays);
        $this->set($settings, 'IssueInvoiceDebit', $debit);
        $this->set($settings, 'IssueInvoiceCredit', $credit);
        $this->set($settings, 'InvoicePaidDebit', $debit);
        $this->set($settings, 'InvoicePaidCredit', $credit);
    }

    private function invoice(User $author, Organization $issuer, Partner $partner, string $number, string $price, ?\DateTime $issueOn): Invoice
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('2999-12-30');
        $command->dateServiceRenderedFrom = new \DateTime('2999-12-01');
        $command->dateServiceRenderedTo = new \DateTime('2999-12-15');
        $command->dueDate = new \DateTime('2999-12-20');
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $issuer;
        $command->recepient = $partner;
        $invoice = $author->createInvoice($command);

        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Consulting';
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = $price;
        $item->discount = 0;
        $invoice->createInvoiceItem($item);

        if ($issueOn !== null) {
            $transaction = $invoice->setIssued($issueOn, $number, $author);
            $this->em->persist($transaction);
        }

        return $invoice;
    }

    private function bootstrap(string $username, bool $admin): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Maker');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Maker');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $this->set($user, 'password', $this->hasher->hashPassword($user, $this->password));

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
