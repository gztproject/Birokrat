<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\UpdateInvoiceCommand;
use App\Entity\Invoice\UpdateInvoiceItemCommand;
use App\Entity\Konto\Konto;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\User\User;
use App\Form\Invoice\InvoiceType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Saving an edit or clone must keep a discount that was entered as a percent.
 * The form fields are percents. The invoice stores a fraction. One unchanged
 * save has to leave that fraction, the payable total, and the posted ledger
 * amount alone.
 */
class InvoiceDiscountRoundTripTest extends KernelTestCase
{
    private const PRICE = 100.0;

    private FormFactoryInterface $forms;

    private EntityManagerInterface $em;

    private User $user;

    private Organization $issuer;

    private Partner $recipient;

    protected function setUp(): void
    {
        $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
        putenv('DATABASE_URL=sqlite:///:memory:');

        self::bootKernel();
        $container = static::getContainer();
        $this->forms = $container->get('form.factory');
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('SELECT 1');
        if (!str_contains($this->em->getConnection()->getDatabasePlatform()::class, 'SQLite')) {
            self::fail('Discount round-trip tests must run on the in-memory SQLite connection.');
        }

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->user = $this->persistUser();
        [$this->issuer, $this->recipient] = $this->persistIssuerAndRecipient($this->user);
        $this->attachLedgerAccounts($this->issuer);
    }

    #[DataProvider('discountCases')]
    public function testUnchangedEditKeepsPercentDiscountAndTotal(float $headerPercent, float $linePercent, float $expectedTotal): void
    {
        $invoice = $this->createDiscountedInvoice($headerPercent, $linePercent);
        $this->assertDiscounted($invoice, $headerPercent, $linePercent, $expectedTotal);

        $saved = $this->saveDiscounts($invoice, false, $headerPercent, $linePercent, null, null);

        $this->assertDiscounted($saved, $headerPercent, $linePercent, $expectedTotal);
        $this->assertLedgerPostsDiscountedTotal($saved, $expectedTotal);
    }

    #[DataProvider('discountCases')]
    public function testUnchangedCloneKeepsPercentDiscountAndTotal(float $headerPercent, float $linePercent, float $expectedTotal): void
    {
        $invoice = $this->createDiscountedInvoice($headerPercent, $linePercent);

        $saved = $this->saveDiscounts($invoice, true, $headerPercent, $linePercent, null, null);

        $this->assertDiscounted($saved, $headerPercent, $linePercent, $expectedTotal);
        $this->assertLedgerPostsDiscountedTotal($saved, $expectedTotal);
    }

    public function testEditingDiscountToTwentyPercentStoresThatPercent(): void
    {
        $invoice = $this->createDiscountedInvoice(10.0, 10.0);

        $saved = $this->saveDiscounts($invoice, false, 10.0, 10.0, 20.0, 20.0);

        $this->assertDiscounted($saved, 20.0, 20.0, 64.0);
    }

    public function testCloningAndSettingDiscountToTwentyPercentStoresThatPercent(): void
    {
        $invoice = $this->createDiscountedInvoice(10.0, 10.0);

        $saved = $this->saveDiscounts($invoice, true, 10.0, 10.0, 20.0, 20.0);

        $this->assertDiscounted($saved, 20.0, 20.0, 64.0);
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function discountCases(): iterable
    {
        yield 'header and line' => [10.0, 10.0, 81.0];
        yield 'header only' => [10.0, 0.0, 90.0];
        yield 'line only' => [0.0, 10.0, 90.0];
    }

    private function saveDiscounts(
        Invoice $invoice,
        bool $clone,
        float $shownHeaderPercent,
        float $shownLinePercent,
        ?float $headerOverride,
        ?float $lineOverride,
    ): Invoice {
        if ($clone) {
            $invoice = $invoice->clone($this->user);
        }

        $command = new UpdateInvoiceCommand();
        $invoice->mapTo($command);
        foreach ($invoice->getInvoiceItems() as $item) {
            $itemCommand = new UpdateInvoiceItemCommand();
            $item->mapTo($itemCommand);
            $command->invoiceItemCommands[] = $itemCommand;
        }

        $form = $this->forms->create(InvoiceType::class, $command, [
            'csrf_protection' => false,
        ]);
        $submitted = $this->viewDataWithoutEntities($form);
        if ($headerOverride === null && $lineOverride === null) {
            self::assertEqualsWithDelta(
                $shownHeaderPercent,
                $this->parseNumber($submitted['discount']),
                0.0001,
                'Header percent field showed '.$submitted['discount']
            );
            self::assertEqualsWithDelta(
                $shownLinePercent,
                $this->parseNumber($submitted['invoiceItemCommands'][0]['discount']),
                0.0001,
                'Line percent field showed '.$submitted['invoiceItemCommands'][0]['discount']
            );
        }
        if ($headerOverride !== null) {
            $submitted['discount'] = $this->formatPercent($headerOverride);
        }
        if ($lineOverride !== null) {
            $submitted['invoiceItemCommands'][0]['discount'] = $this->formatPercent($lineOverride);
        }
        $form->submit($submitted, false);

        self::assertTrue($form->isValid(), $this->formErrors($form));

        return $invoice->update($command, $this->user);
    }

    private function formatPercent(float $percent): string
    {
        $formatted = number_format($percent, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function assertDiscounted(Invoice $invoice, float $headerPercent, float $linePercent, float $expectedTotal): void
    {
        self::assertCount(1, $invoice->getInvoiceItems());
        self::assertEqualsWithDelta($headerPercent / 100, (float) $invoice->getDiscount(), 0.00001);
        self::assertEqualsWithDelta($linePercent / 100, (float) $invoice->getInvoiceItems()->first()->getDiscount(), 0.00001);
        self::assertEqualsWithDelta($expectedTotal, (float) $invoice->getTotalPrice(), 0.001);
    }

    private function assertLedgerPostsDiscountedTotal(Invoice $invoice, float $expectedTotal): void
    {
        $issued = $invoice->setIssued(new \DateTime('today'), 'TST-2026-0002', $this->user);
        self::assertEqualsWithDelta($expectedTotal, (float) $issued->getSum(), 0.001);
        self::assertEqualsWithDelta($expectedTotal, (float) $invoice->getTotalPrice(), 0.001);

        $paid = $invoice->setPaid(new \DateTime('today'), $this->user);
        self::assertEqualsWithDelta($expectedTotal, (float) $paid->getSum(), 0.001);
    }

    private function createDiscountedInvoice(float $headerPercent, float $linePercent): Invoice
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('today');
        $command->dateServiceRenderedFrom = new \DateTime('-7 days');
        $command->dateServiceRenderedTo = new \DateTime('-1 days');
        $command->dueDate = new \DateTime('+10 days');
        $command->discount = $headerPercent;
        $command->number = 'TST-2026-0001';
        $command->issuer = $this->issuer;
        $command->recepient = $this->recipient;

        $invoice = new Invoice($command, $this->user);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Consulting';
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = self::PRICE;
        $item->discount = $linePercent;
        $invoice->createInvoiceItem($item);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewDataWithoutEntities(FormInterface $form): array
    {
        $data = [];
        foreach ($form as $name => $child) {
            if ($child->getConfig()->getType()->getInnerType() instanceof EntityType) {
                continue;
            }
            if ($child->getConfig()->getCompound()) {
                $data[$name] = $this->viewDataWithoutEntities($child);
                continue;
            }
            $data[$name] = $child->getViewData();
        }

        return $data;
    }

    private function parseNumber(mixed $value): float
    {
        $normalized = str_replace(["\xc2\xa0", "\xe2\x80\xaf", ' '], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }

    private function formErrors(FormInterface $form): string
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $messages[] = ($origin ? $origin->getPropertyPath() : 'form').': '.$error->getMessage();
        }

        return implode("\n", $messages);
    }

    private function persistUser(): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->setProperty($user, 'id', Uuid::uuid6());
        $this->setProperty($user, 'createdOn', new \DateTime());
        $this->setProperty($user, 'createdBy', $user);
        $this->setProperty($user, 'isActive', true);
        $this->setProperty($user, 'organizations', new ArrayCollection());
        $this->setProperty($user, 'username', 'invoice-tester');
        $this->setProperty($user, 'firstName', 'Test');
        $this->setProperty($user, 'lastName', 'User');
        $this->setProperty($user, 'email', 'invoice-tester@example.test');
        $this->setProperty($user, 'mobile', null);
        $this->setProperty($user, 'phone', null);
        $this->setProperty($user, 'signatureFilename', '');
        $this->setProperty($user, 'position', 'Tester');
        $this->setProperty($user, 'totpEnabled', false);
        $this->setProperty($user, 'totpSecret', null);
        $this->setProperty($user, 'roles', ['ROLE_USER']);
        $this->setProperty($user, 'password', $hasher->hashPassword($user, 'password'));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @return array{Organization, Partner}
     */
    private function persistIssuerAndRecipient(User $user): array
    {
        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $user->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $user);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg 1';
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $user);

        $organizationCommand = new CreateOrganizationCommand();
        $organizationCommand->code = '1';
        $organizationCommand->name = 'Issuer d.o.o.';
        $organizationCommand->shortName = 'Issuer';
        $organizationCommand->taxNumber = '12345678';
        $organizationCommand->taxable = true;
        $organizationCommand->address = $address;
        $organizationCommand->accountNumber = 'SI56031001001001234';
        $organizationCommand->bic = 'LJBASI2X';
        $organization = $user->createOrganization($organizationCommand);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = '2';
        $partnerCommand->name = 'Partner d.o.o.';
        $partnerCommand->shortName = 'Partner';
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $address;
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = false;
        $partner = $user->createPartner($partnerCommand);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($organization);
        $this->em->persist($organization->getOrganizationSettings());
        $this->em->persist($partner);
        $this->em->flush();

        return [$organization, $partner];
    }

    private function attachLedgerAccounts(Organization $issuer): void
    {
        $settings = $issuer->getOrganizationSettings();
        $this->setProperty($settings, 'IssueInvoiceCredit', $this->konto());
        $this->setProperty($settings, 'IssueInvoiceDebit', $this->konto());
        $this->setProperty($settings, 'InvoicePaidCredit', $this->konto());
        $this->setProperty($settings, 'InvoicePaidDebit', $this->konto());
    }

    private function konto(): Konto
    {
        return $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
            if (!$class instanceof ReflectionClass) {
                throw new \RuntimeException('Missing property '.$name);
            }
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
