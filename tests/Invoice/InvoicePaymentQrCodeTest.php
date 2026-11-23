<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\Country;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Geography\Post;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoicePdfFactory;
use App\Entity\Konto\Konto;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\Organization\UpdateOrganizationCommand;
use App\Entity\Organization\UpdatePartnerCommand;
use App\Entity\User\User;
use App\Form\Geography\AddressType;
use App\Form\Geography\PostType;
use App\Form\Organization\OrganizationType;
use App\Form\Organization\PartnerType;
use PHPUnit\Framework\TestCase;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoicePaymentQrCodeTest extends TestCase
{
	private const ATTACKER_ACCOUNT = 'SI56000000000000099';
	private const COMPANY_ACCOUNT = 'SI56 0310 0100 1001 234';
	private const CUSTOMER_NAME = 'ACME CUSTOMER';

	private FormFactoryInterface $forms;
	private User $user;
	private TCPDFController $tcpdf;
	private TranslatorInterface $translator;

	protected function setUp(): void
	{
		$this->forms = Forms::createFormFactoryBuilder()
			->addExtension(new ValidatorExtension(Validation::createValidator()))
			->getFormFactory();
		$this->user = $this->createMock(User::class);
		$this->user->method('getFullname')->willReturn('Clerk User');
		$this->user->method('getSignatureFilename')->willReturn('none.txt');
		$this->tcpdf = new TCPDFController('TCPDF');
		$this->translator = new class implements TranslatorInterface {
			public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
			{
				return $id;
			}

			public function getLocale(): string
			{
				return 'en';
			}
		};
	}

	public function testCustomerNameLineBreaksRedirectThePaymentCode(): void
	{
		$invoice = $this->issuedInvoice();
		$partner = $invoice->getRecepient();
		$submitted = $this->submitPartnerName($this->linesOccupying(6, self::ATTACKER_ACCOUNT));
		$this->assertTrue($submitted->isValid());

		$update = $submitted->getData();
		$update->address = $partner->getAddress();
		$partner->update($update, $this->user);

		$this->assertSame(self::CUSTOMER_NAME, $invoice->getRecepientName());
		$this->assertSame(self::COMPANY_ACCOUNT, $invoice->getIssuerAccountNumber());
		$this->assertNotSame(self::CUSTOMER_NAME, $partner->getName());

		$failures = [];
		foreach ($this->renderedInvoices($invoice) as $label => $pdf) {
			$text = $this->pdfText($pdf);
			$this->assertStringContainsString(self::CUSTOMER_NAME, $text, $label);
			$this->assertStringContainsString(self::COMPANY_ACCOUNT, $text, $label);
			$this->assertStringNotContainsString(self::ATTACKER_ACCOUNT, $text, $label);
			try {
				$this->assertPaymentCodePaysStoredAccount($pdf, $invoice, $label);
			} catch (\PHPUnit\Framework\AssertionFailedError $e) {
				$failures[] = $label.': '.$e->getMessage();
			}
		}
		$this->assertSame([], $failures, implode("\n", $failures));
	}

	public function testCustomerNameCarriageReturnsRedirectThePaymentCode(): void
	{
		$invoice = $this->issuedInvoice();
		$partner = $invoice->getRecepient();
		$submitted = $this->submitPartnerName(implode("\r\n", $this->leadIn(6, self::ATTACKER_ACCOUNT)));
		$this->assertTrue($submitted->isValid());
		$this->assertStringContainsString("\r", $submitted->get('name')->getData());

		$update = $submitted->getData();
		$update->address = $partner->getAddress();
		$partner->update($update, $this->user);

		$this->assertPaymentCodePaysStoredAccount($this->pdfString($invoice, 'S'), $invoice, 'download');
	}

	public function testStreetLineBreaksRedirectThePaymentCode(): void
	{
		$invoice = $this->issuedInvoice();
		$partner = $invoice->getRecepient();
		$address = $this->submitStreet($this->linesOccupying(7, self::ATTACKER_ACCOUNT), '');
		$update = new UpdatePartnerCommand();
		$update->address = $address;
		$partner->update($update, $this->user);

		$text = $this->pdfText($this->pdfString($invoice, 'S'));
		$this->assertStringContainsString(self::CUSTOMER_NAME, $text);
		$this->assertStringContainsString(self::COMPANY_ACCOUNT, $text);
		$this->assertStringNotContainsString(self::ATTACKER_ACCOUNT, $text);
		$this->assertPaymentCodePaysStoredAccount($this->pdfString($invoice, 'S'), $invoice, 'street');
	}

	public function testStreetSecondLineBreaksRedirectThePaymentCode(): void
	{
		$invoice = $this->issuedInvoice();
		$partner = $invoice->getRecepient();
		$address = $this->submitStreet('Slovenska 1', implode("\n", $this->leadIn(7, self::ATTACKER_ACCOUNT)));
		$update = new UpdatePartnerCommand();
		$update->address = $address;
		$partner->update($update, $this->user);

		$this->assertPaymentCodePaysStoredAccount($this->pdfString($invoice, 'S'), $invoice, 'street line 2');
	}

	public function testPostNameLineBreaksRedirectThePaymentCode(): void
	{
		$invoice = $this->issuedInvoice();
		$partner = $invoice->getRecepient();
		$post = $this->submitPostName($this->linesOccupying(8, self::ATTACKER_ACCOUNT));
		$command = new CreateAddressCommand();
		$command->line1 = 'Slovenska 1';
		$command->line2 = '';
		$command->post = $post;
		$update = new UpdatePartnerCommand();
		$update->address = $post->createAddress($command, $this->user);
		$partner->update($update, $this->user);

		$text = $this->pdfText($this->pdfString($invoice, 'S'));
		$this->assertStringContainsString(self::COMPANY_ACCOUNT, $text);
		$this->assertStringNotContainsString(self::ATTACKER_ACCOUNT, $text);
		$this->assertPaymentCodePaysStoredAccount($this->pdfString($invoice, 'S'), $invoice, 'post');
	}

	public function testStoredAccountStaysThePaymentTargetAfterTheOrganizationAccountChanges(): void
	{
		$invoice = $this->issuedInvoice();
		$issuer = $invoice->getIssuer();
		$command = new UpdateOrganizationCommand();
		$form = $this->forms->create(OrganizationType::class, $command);
		$form->submit([
			'name' => $issuer->getName(),
			'shortName' => '',
			'taxNumber' => '12345678',
			'taxable' => '1',
			'www' => 'https://example.test',
			'email' => 'accounts@example.test',
			'phone' => '',
			'mobile' => '040000000',
			'accountNumber' => self::ATTACKER_ACCOUNT,
			'bic' => 'LJBASI2X',
		]);
		$this->assertTrue($form->isValid());
		$issuer->update($form->getData(), $this->user);

		$this->assertSame(self::ATTACKER_ACCOUNT, $issuer->getAccountNumber());
		$this->assertSame(self::COMPANY_ACCOUNT, $invoice->getIssuerAccountNumber());
		$text = $this->pdfText($this->pdfString($invoice, 'S'));
		$this->assertStringContainsString(self::COMPANY_ACCOUNT, $text);
		$this->assertPaymentCodePaysStoredAccount($this->pdfString($invoice, 'S'), $invoice, 'organization account');
	}

	private function assertPaymentCodePaysStoredAccount(string $pdf, Invoice $invoice, string $label): void
	{
		$payload = $this->decodePaymentCode($pdf);
		if ($payload === null) {
			$this->assertStringNotContainsString(self::ATTACKER_ACCOUNT, $this->pdfText($pdf), $label);

			return;
		}

		$lines = $this->paymentLines($payload);
		$stored = str_replace(' ', '', $invoice->getIssuerAccountNumber());
		$this->assertSame($stored, $lines[14] ?? null, $label.' line 15 was '.json_encode($lines[14] ?? null));
		$this->assertStringNotContainsString("\r", $payload, $label);
		$this->assertStringNotContainsString(self::ATTACKER_ACCOUNT, $payload, $label);
	}

	/**
	 * @return array<string, string>
	 */
	private function renderedInvoices(Invoice $invoice): array
	{
		$dir = sys_get_temp_dir().'/invoice-qr-'.bin2hex(random_bytes(4));
		mkdir($dir);
		$download = $this->pdfString($invoice, 'S');
		$print = $this->pdfString($invoice, 'S', true);
		$this->pdfString($invoice, 'F', false, $dir.'/');
		$files = glob($dir.'/*.pdf');
		$this->assertNotEmpty($files);
		$email = (string) file_get_contents($files[0]);
		unlink($files[0]);
		rmdir($dir);

		return [
			'download' => $download,
			'print' => $print,
			'email' => $email,
		];
	}

	private function pdfString(Invoice $invoice, string $dest, bool $print = false, ?string $path = null): string
	{
		return (string) InvoicePdfFactory::factory($invoice, $this->translator, $this->tcpdf, $dest, $path, $print)->generate();
	}

	private function issuedInvoice(): Invoice
	{
		$post = $this->post('Ljubljana');
		$issuerAddress = $this->address($post, 'Trubarjeva 1', '');
		$customerAddress = $this->address($post, 'Slovenska 1', '');

		$organization = new CreateOrganizationCommand();
		$organization->code = 'ORG1';
		$organization->name = 'COMPANY D.O.O.';
		$organization->taxNumber = '12345678';
		$organization->taxable = true;
		$organization->address = $issuerAddress;
		$organization->www = 'https://example.test';
		$organization->email = 'accounts@example.test';
		$organization->mobile = '040000000';
		$organization->accountNumber = self::COMPANY_ACCOUNT;
		$organization->bic = 'LJBASI2X';
		$issuer = new Organization($organization, $this->user);

		$credit = $this->createMock(Konto::class);
		$debit = $this->createMock(Konto::class);
		$settings = $issuer->getOrganizationSettings();
		$creditProperty = new \ReflectionProperty($settings, 'IssueInvoiceCredit');
		$debitProperty = new \ReflectionProperty($settings, 'IssueInvoiceDebit');
		$creditProperty->setValue($settings, $credit);
		$debitProperty->setValue($settings, $debit);

		$partnerCommand = new CreatePartnerCommand();
		$partnerCommand->code = 'P1';
		$partnerCommand->name = self::CUSTOMER_NAME;
		$partnerCommand->taxNumber = '87654321';
		$partnerCommand->taxable = true;
		$partnerCommand->isClient = true;
		$partnerCommand->isSupplier = false;
		$partnerCommand->address = $customerAddress;
		$partner = new Partner($partnerCommand, $this->user);

		$invoiceCommand = new CreateInvoiceCommand();
		$invoiceCommand->dateOfIssue = new \DateTime('2026-01-15');
		$invoiceCommand->dateServiceRenderedFrom = new \DateTime('2026-01-01');
		$invoiceCommand->dateServiceRenderedTo = new \DateTime('2026-01-10');
		$invoiceCommand->dueDate = new \DateTime('2026-01-30');
		$invoiceCommand->discount = 0;
		$invoiceCommand->number = '2026-0001';
		$invoiceCommand->issuer = $issuer;
		$invoiceCommand->recepient = $partner;
		$invoice = new Invoice($invoiceCommand, $this->user);
		$invoice->setIssued(new \DateTime('2026-01-15'), '2026-0001', $this->user);

		return $invoice;
	}

	private function submitPartnerName(string $name): FormInterface
	{
		$form = $this->forms->create(PartnerType::class, new UpdatePartnerCommand());
		$form->submit([
			'name' => $name,
			'shortName' => '',
			'taxNumber' => '87654321',
			'isClient' => '1',
			'isSupplier' => null,
			'taxable' => '1',
			'www' => '',
			'email' => '',
			'phone' => '',
			'mobile' => '',
			'accountNumber' => '',
			'bic' => '',
			'extraEmailCommands' => [],
		]);

		return $form;
	}

	private function submitStreet(string $line1, string $line2): \App\Entity\Geography\Address
	{
		$command = new CreateAddressCommand();
		$builder = $this->forms->createNamedBuilder('address', FormType::class, $command, [
			'data_class' => CreateAddressCommand::class,
		]);
		(new AddressType())->buildForm($builder, []);
		$builder->remove('post');
		$form = $builder->getForm();
		$form->submit([
			'line1' => $line1,
			'line2' => $line2,
		]);
		$this->assertTrue($form->isValid(), (string) $form->getErrors(true));
		$submitted = $form->getData();
		$post = $this->post('Ljubljana');
		$create = new CreateAddressCommand();
		$create->line1 = $submitted->line1;
		$create->line2 = $submitted->line2;
		$create->post = $post;

		return $post->createAddress($create, $this->user);
	}

	private function submitPostName(string $name): Post
	{
		$command = new CreatePostCommand();
		$builder = $this->forms->createNamedBuilder('post', FormType::class, $command, [
			'data_class' => CreatePostCommand::class,
		]);
		(new PostType())->buildForm($builder, []);
		$builder->remove('country');
		$form = $builder->getForm();
		$form->submit([
			'code' => '1000',
			'codeInternational' => 'SI-1000',
			'name' => $name,
		]);
		$this->assertTrue($form->isValid(), (string) $form->getErrors(true));
		$submitted = $form->getData();
		$country = $this->country();
		$create = new CreatePostCommand();
		$create->code = $submitted->code;
		$create->codeInternational = $submitted->codeInternational;
		$create->name = $submitted->name;

		return $country->createPost($create, $this->user);
	}

	private function country(): Country
	{
		$command = new CreateCountryCommand();
		$command->name = 'Slovenija';
		$command->nameInt = 'Slovenia';
		$command->A2 = 'SI';
		$command->A3 = 'SVN';
		$command->N3 = 705;

		return new Country($command, $this->user);
	}

	private function post(string $name): Post
	{
		$command = new CreatePostCommand();
		$command->code = '1000';
		$command->codeInternational = 'SI-1000';
		$command->name = $name;

		return $this->country()->createPost($command, $this->user);
	}

	private function address(Post $post, string $line1, string $line2): \App\Entity\Geography\Address
	{
		$command = new CreateAddressCommand();
		$command->line1 = $line1;
		$command->line2 = $line2;
		$command->post = $post;

		return $post->createAddress($command, $this->user);
	}

	/**
	 * Lines inserted at $firstLine (1-based) so that line 15 is $account.
	 */
	private function linesOccupying(int $firstLine, string $account): string
	{
		return implode("\n", $this->leadIn($firstLine, $account));
	}

	/**
	 * @return list<string>
	 */
	private function leadIn(int $firstLine, string $account): array
	{
		$count = 15 - $firstLine + 1;
		$lines = array_fill(0, $count, 'X');
		$lines[$count - 1] = $account;

		return $lines;
	}

	private function decodePaymentCode(string $pdf): ?string
	{
		$dir = sys_get_temp_dir().'/invoice-qr-'.bin2hex(random_bytes(4));
		mkdir($dir);
		$pdfPath = $dir.'/invoice.pdf';
		file_put_contents($pdfPath, $pdf);
		$prefix = $dir.'/page';
		$this->runCommand(['pdftoppm', '-png', '-r', '400', $pdfPath, $prefix]);
		$png = $prefix.'-1.png';
		$this->assertFileExists($png);
		$result = $this->runCommand(['zbarimg', '--raw', '-q', $png]);
		$this->removeTree($dir);
		if ($result['exit'] === 4) {
			return null;
		}
		$this->assertSame(0, $result['exit'], $result['stderr']);

		return $result['stdout'];
	}

	/**
	 * @return list<string>
	 */
	private function paymentLines(string $payload): array
	{
		$payload = rtrim($payload, "\n");
		$lines = explode("\n", rtrim($payload, ' '));
		if ($lines !== [] && end($lines) === '') {
			array_pop($lines);
		}

		return $lines;
	}

	private function pdfText(string $pdf): string
	{
		$dir = sys_get_temp_dir().'/invoice-text-'.bin2hex(random_bytes(4));
		mkdir($dir);
		$path = $dir.'/invoice.pdf';
		file_put_contents($path, $pdf);
		$result = $this->runCommand(['pdftotext', '-layout', $path, '-']);
		$this->removeTree($dir);
		$this->assertSame(0, $result['exit'], $result['stderr']);

		return $result['stdout'];
	}

	/**
	 * @param list<string> $command
	 * @return array{exit: int, stdout: string, stderr: string}
	 */
	private function runCommand(array $command): array
	{
		$pipes = [];
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		return [
			'exit' => proc_close($process),
			'stdout' => $stdout === false ? '' : $stdout,
			'stderr' => $stderr === false ? '' : $stderr,
		];
	}

	private function removeTree(string $dir): void
	{
		foreach (glob($dir.'/*') ?: [] as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		rmdir($dir);
	}
}
