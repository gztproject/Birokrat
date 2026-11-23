<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\Address;
use App\Entity\Geography\Country;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Geography\Post;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoicePdfFactory;
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
use App\Entity\Organization\UpdateOrganizationCommand;
use App\Entity\Organization\UpdatePartnerCommand;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\User\User;
use PHPUnit\Framework\TestCase;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Symfony\Contracts\Translation\TranslatorInterface;
use TCPDF;

/**
 * Sales-invoice PDFs must encode the account, name, and address copied onto the
 * invoice. Download, print, and email all go through InvoicePdfFactory.
 */
class InvoicePaymentQrTest extends TestCase
{
    private const ORIGINAL_ACCOUNT = "SI56 0310\r\n0100 1001 234";
    private const ATTACKER_ACCOUNT = 'SI56 9999 8888 7777 666';
    private const ORIGINAL_ISSUER_NAME = "Podjetje\r\nŠola";
    private const ATTACKER_ISSUER_NAME = 'Napadalec d.o.o.';
    private const ORIGINAL_PAYER_NAME = "Kupec\nŠola";
    private const ATTACKER_PAYER_NAME = 'Druga oseba';

    protected function tearDown(): void
    {
        RecordingTcpdf::reset();
    }

    public function testDownloadedInvoicePdfQrPaysTheAccountCopiedOntoTheInvoice(): void
    {
        $invoice = $this->invoiceAfterCompanyAccountWasChanged();
        $pdf = (string) InvoicePdfFactory::factory($invoice, $this->translator(), $this->tcpdf(), 'S', false)->generate();

        self::assertStringStartsWith('%PDF', $pdf);
        $this->assertQrMatchesInvoiceCopy($invoice);
    }

    public function testPrintedInvoicePdfQrPaysTheAccountCopiedOntoTheInvoice(): void
    {
        $invoice = $this->invoiceAfterCompanyAccountWasChanged();
        $pdf = (string) InvoicePdfFactory::factory($invoice, $this->translator(), $this->tcpdf(), 'S', true)->generate();

        self::assertStringStartsWith('%PDF', $pdf);
        $this->assertQrMatchesInvoiceCopy($invoice);
    }

    public function testEmailedInvoicePdfQrPaysTheAccountCopiedOntoTheInvoice(): void
    {
        // Email uses the same InvoicePdfFactory string destination as download.
        $invoice = $this->invoiceAfterCompanyAccountWasChanged();
        $pdf = (string) InvoicePdfFactory::factory($invoice, $this->translator(), $this->tcpdf(), 'S', false)->generate();

        self::assertStringStartsWith('%PDF', $pdf);
        $this->assertQrMatchesInvoiceCopy($invoice);
    }

    private function assertQrMatchesInvoiceCopy(Invoice $invoice): void
    {
        self::assertCount(1, RecordingTcpdf::$barcodes, 'The invoice PDF must contain one UPN payment QR code.');

        $printed = implode("\n", RecordingTcpdf::$multiCells);
        self::assertStringContainsString(self::ORIGINAL_ACCOUNT, $printed);
        self::assertStringNotContainsString(self::ATTACKER_ACCOUNT, $printed);

        $actual = RecordingTcpdf::$barcodes[0]['code'];
        self::assertSame('QRCODE,M', RecordingTcpdf::$barcodes[0]['type']);

        $expected = $this->expectedUpnPayload($invoice);
        self::assertSame($expected, $actual);
        self::assertStringNotContainsString($this->compactIban(self::ATTACKER_ACCOUNT), $actual);
        self::assertStringContainsString($this->compactIban(self::ORIGINAL_ACCOUNT), $actual);
    }

    private function invoiceAfterCompanyAccountWasChanged(): Invoice
    {
        $user = $this->createMock(User::class);
        $user->method('getFullname')->willReturn('Ada Clerk');
        $user->method('getSignatureFilename')->willReturn('');
        $user->method('getEmail')->willReturn('clerk@example.test');

        $country = $this->country($user, 'Slovenija');
        $issuer = $this->organization(
            $user,
            self::ORIGINAL_ISSUER_NAME,
            self::ORIGINAL_ACCOUNT,
            $this->address($user, $country, '1000', 'Ljubljana', 'Cesta 1', 'Nadstropje 2'),
        );
        $payer = $this->partner(
            $user,
            self::ORIGINAL_PAYER_NAME,
            $this->address($user, $country, '3000', 'Celje', 'Kupec 3', null),
        );

        $invoice = $this->issuedInvoice($user, $issuer, $payer);
        $copiedAccount = $invoice->getIssuerAccountNumber();
        $copiedIssuerName = $invoice->getIssuerName();
        $copiedPayerName = $invoice->getRecepientName();
        $copiedIssuerAddress = $invoice->getIssuerAddress();
        $copiedPayerAddress = $invoice->getRecepientAddress();

        $issuerUpdate = new UpdateOrganizationCommand();
        $issuerUpdate->name = self::ATTACKER_ISSUER_NAME;
        $issuerUpdate->accountNumber = self::ATTACKER_ACCOUNT;
        $issuerUpdate->address = $this->address($user, $country, '2000', 'Maribor', 'Napadalec 9', null);
        $issuer->update($issuerUpdate, $user);

        $payerUpdate = new UpdatePartnerCommand();
        $payerUpdate->name = self::ATTACKER_PAYER_NAME;
        $payerUpdate->address = $this->address($user, $country, '4000', 'Koper', 'Tuja 5', null);
        $payer->update($payerUpdate, $user);

        self::assertSame($copiedAccount, $invoice->getIssuerAccountNumber());
        self::assertSame($copiedIssuerName, $invoice->getIssuerName());
        self::assertSame($copiedPayerName, $invoice->getRecepientName());
        self::assertSame($copiedIssuerAddress, $invoice->getIssuerAddress());
        self::assertSame($copiedPayerAddress, $invoice->getRecepientAddress());
        self::assertSame(self::ATTACKER_ACCOUNT, $invoice->getIssuer()->getAccountNumber());
        self::assertSame(self::ATTACKER_ISSUER_NAME, $invoice->getIssuer()->getName());
        self::assertSame(self::ATTACKER_PAYER_NAME, $invoice->getRecepient()->getName());
        self::assertNotSame($invoice->getIssuerAccountNumber(), $invoice->getIssuer()->getAccountNumber());

        return $invoice;
    }

    private function issuedInvoice(User $user, Organization $issuer, Partner $payer): Invoice
    {
        $credit = $this->konto($user, 760, 'Revenue');
        $debit = $this->konto($user, 120, 'Receivables');
        $settings = new UpdateOrganizationSettingsCommand();
        $settings->IssueInvoiceCredit = $credit;
        $settings->IssueInvoiceDebit = $debit;
        $issuer->updateOrganizationSettings($settings, $user);

        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('2024-06-01');
        $command->dateServiceRenderedFrom = new \DateTime('2024-05-01');
        $command->dateServiceRenderedTo = new \DateTime('2024-05-31');
        $command->dueDate = new \DateTime('2024-06-15');
        $command->discount = 0;
        $command->number = '2024-0001';
        $command->issuer = $issuer;
        $command->recepient = $payer;

        $invoice = new Invoice($command, $user);
        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = 'Work';
        $item->quantity = 1;
        $item->unit = 'h';
        $item->price = 10;
        $item->discount = 0;
        $invoice->createInvoiceItem($item);
        $invoice->setIssued(new \DateTime('2024-06-01'), '2024-0001', $user);

        return $invoice;
    }

    private function expectedUpnPayload(Invoice $invoice): string
    {
        $payerAddress = $invoice->getRecepientFormattedAddress();
        $issuerAddress = $invoice->getIssuerFormattedAddress();
        $fields = [
            'UPNQR',
            '',
            '',
            '',
            '',
            $this->qrField($invoice->getRecepientName()),
            $this->qrField($this->streetFromFormattedAddress($payerAddress)),
            $this->qrField($this->postFromFormattedAddress($payerAddress)),
            sprintf('%011d', (int) round($invoice->getTotalPrice() * 100)),
            '',
            '',
            'IVPT',
            $this->qrField(sprintf('Plačilo računa %s', $invoice->getNumber())),
            $invoice->getDueDate()->format('d.m.Y'),
            $this->qrField($this->compactIban($invoice->getIssuerAccountNumber())),
            $this->qrField(str_replace(' ', '', $invoice->getReferenceNumber())),
            $this->qrField($invoice->getIssuerName()),
            $this->qrField($this->streetFromFormattedAddress($issuerAddress)),
            $this->qrField($this->postFromFormattedAddress($issuerAddress)),
        ];

        $body = implode("\n", $fields)."\n";
        $body .= sprintf("%03d\n", strlen($body));
        return str_pad($body, 411, ' ');
    }

    private function qrField(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return str_replace(['Š', 'š', 'Č', 'č', 'Ž', 'ž'], ['S', 's', 'C', 'c', 'Z', 'z'], $value);
    }

    private function compactIban(string $account): string
    {
        return str_replace([' ', "\r", "\n"], '', $account);
    }

    /**
     * @param list<string> $parts
     */
    private function streetFromFormattedAddress(array $parts): string
    {
        return implode(', ', array_slice($parts, 0, -2));
    }

    /**
     * @param list<string> $parts
     */
    private function postFromFormattedAddress(array $parts): string
    {
        return $parts[count($parts) - 2];
    }

    private function organization(User $user, string $name, string $account, Address $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = 'ORG';
        $command->name = $name;
        $command->taxNumber = '12345678';
        $command->taxable = true;
        $command->address = $address;
        $command->www = 'https://issuer.example';
        $command->email = 'issuer@example.test';
        $command->mobile = '040000000';
        $command->accountNumber = $account;
        $command->bic = 'LJBASI2X';

        return new Organization($command, $user);
    }

    private function partner(User $user, string $name, Address $address): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = 'PAY';
        $command->name = $name;
        $command->taxNumber = '87654321';
        $command->taxable = true;
        $command->address = $address;
        $command->email = 'payer@example.test';
        $command->isClient = true;
        $command->isSupplier = false;

        return new Partner($command, $user);
    }

    private function country(User $user, string $name): Country
    {
        $command = new CreateCountryCommand();
        $command->name = $name;
        $command->nameInt = $name;
        $command->A2 = 'SI';
        $command->A3 = 'SVN';
        $command->N3 = 705;

        return new Country($command, $user);
    }

    private function address(User $user, Country $country, string $code, string $city, string $line1, ?string $line2): Address
    {
        $postCommand = new CreatePostCommand();
        $postCommand->code = $code;
        $postCommand->codeInternational = 'SI-'.$code;
        $postCommand->name = $city;
        $post = new Post($postCommand, $user, $country);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = $line1;
        $addressCommand->line2 = $line2;

        return new Address($addressCommand, $user, $post);
    }

    private function konto(User $user, int $number, string $name): Konto
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 1;
        $classCommand->name = 'Class';
        $classCommand->description = '';
        $class = new KontoClass($classCommand, $user);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = 12;
        $categoryCommand->name = 'Category';
        $categoryCommand->description = '';
        $category = new KontoCategory($categoryCommand, $class, $user);

        $kontoCommand = new class extends CreateKontoCommand {
            public $type;
        };
        $kontoCommand->number = $number;
        $kontoCommand->name = $name;
        $kontoCommand->description = '';
        $kontoCommand->isActive = true;
        $kontoCommand->type = KontoTypes::active;

        return new Konto($kontoCommand, $category, $user);
    }

    private function tcpdf(): TCPDFController
    {
        RecordingTcpdf::reset();

        return new TCPDFController(RecordingTcpdf::class);
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return $translator;
    }

}

class RecordingTcpdf extends TCPDF
{
    /** @var list<string> */
    public static array $multiCells = [];

    /** @var list<array{code: string, type: string}> */
    public static array $barcodes = [];

    public static function reset(): void
    {
        self::$multiCells = [];
        self::$barcodes = [];
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false, $ln = 1, $x = null, $y = null, $reseth = true, $stretch = 0, $ishtml = false, $autopadding = true, $maxh = 0, $valign = 'T', $fitcell = false)
    {
        self::$multiCells[] = (string) $txt;

        return parent::MultiCell($w, $h, $txt, $border, $align, $fill, $ln, $x, $y, $reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);
    }

    public function write2DBarcode($code, $type, $x = null, $y = null, $w = null, $h = null, $style = [], $align = '', $distort = false)
    {
        self::$barcodes[] = ['code' => (string) $code, 'type' => (string) $type];

        return parent::write2DBarcode($code, $type, $x, $y, $w, $h, $style, $align, $distort);
    }
}
