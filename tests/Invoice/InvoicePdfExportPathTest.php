<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\Address;
use App\Entity\Geography\Post;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Enumerators\States;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\Settings\CreateOrganizationSettingsCommand;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\User\User;
use App\Controller\Invoice\InvoiceCommandController;
use App\Mailer\MailerSettings;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invoice PDF export must not create, replace, or delete a PDF outside the
 * temporary directory. These tests fail while the invoice number or the
 * invoice prefix can steer that path.
 */
class InvoicePdfExportPathTest extends TestCase
{
	private string $projectRoot;

	/** @var array<string, string> */
	private array $rootPdfs = [];

	/** @var array<string, string> */
	private array $parentPdfs = [];

	private TranslatorInterface $translator;

	protected function setUp(): void
	{
		$this->projectRoot = dirname(__DIR__, 2);
		$this->rootPdfs = $this->pdfContents($this->projectRoot);
		$this->parentPdfs = $this->pdfContents(dirname($this->projectRoot));
		$this->translator = $this->slovenianTranslator();
	}

	protected function tearDown(): void
	{
		$this->restorePdfs($this->projectRoot, $this->rootPdfs);
		$this->restorePdfs(dirname($this->projectRoot), $this->parentPdfs);
		$glued = $this->projectRoot.'/tmp/'.$this->translator->trans('title.invoice').' ';
		if (is_dir($glued)) {
			foreach (glob($glued.'/*') ?: [] as $child) {
				if (is_file($child)) {
					unlink($child);
				}
			}
			@rmdir($glued);
		}
	}

	public function testInvoicePrefixWithParentSegmentsDoesNotTouchPdfsOutsideTemporaryDirectory(): void
	{
		$invoice = $this->issuedInvoice('/../../zq7', null, States::new);
		$this->plantSentinel($invoice);
		$beforeRoot = $this->pdfContents($this->projectRoot);
		$beforeParent = $this->pdfContents(dirname($this->projectRoot));

		$this->send($invoice);

		$this->assertOutsidePdfsUnchanged($beforeRoot, $beforeParent);
	}

	public function testStoredInvoiceNumberWithParentSegmentsDoesNotTouchPdfsOutsideTemporaryDirectory(): void
	{
		$invoice = $this->issuedInvoice('', [
			'number' => '/../../1-2-3-4',
			'date_of_issue' => '2026-06-01',
			'state' => States::new,
		], States::draft);
		$this->plantSentinel($invoice);
		$beforeRoot = $this->pdfContents($this->projectRoot);
		$beforeParent = $this->pdfContents(dirname($this->projectRoot));

		$this->send($invoice);

		$this->assertOutsidePdfsUnchanged($beforeRoot, $beforeParent);
	}

	public function testSendDoesNotDeleteOutsidePdfWhenGluedDirectoryExists(): void
	{
		$invoice = $this->issuedInvoice('/../../zq9', null, States::new);
		$this->plantSentinel($invoice);
		$glued = $this->projectRoot.'/tmp/'.$this->translator->trans('title.invoice').' ';
		if (!is_dir($glued)) {
			mkdir($glued, 0777, true);
		}
		$beforeRoot = $this->pdfContents($this->projectRoot);
		$beforeParent = $this->pdfContents(dirname($this->projectRoot));

		$this->send($invoice);

		$this->assertOutsidePdfsUnchanged($beforeRoot, $beforeParent);
	}

	public function testOrdinaryInvoiceNumberDoesNotTouchPdfsOutsideTemporaryDirectory(): void
	{
		$invoice = $this->issuedInvoice('OK', null, States::new);
		$beforeRoot = $this->pdfContents($this->projectRoot);
		$beforeParent = $this->pdfContents(dirname($this->projectRoot));

		$response = $this->send($invoice);
		$payload = json_decode($response->getContent(), true);

		$this->assertSame('ok', $payload[0]['status'] ?? null);
		$this->assertOutsidePdfsUnchanged($beforeRoot, $beforeParent);
	}

	/**
	 * @param array<string, string> $beforeRoot
	 * @param array<string, string> $beforeParent
	 */
	private function assertOutsidePdfsUnchanged(array $beforeRoot, array $beforeParent): void
	{
		$this->assertPdfSetUnchanged($beforeRoot, $this->pdfContents($this->projectRoot));
		$this->assertPdfSetUnchanged($beforeParent, $this->pdfContents(dirname($this->projectRoot)));
	}

	/**
	 * @param array<string, string> $before
	 * @param array<string, string> $after
	 */
	private function assertPdfSetUnchanged(array $before, array $after): void
	{
		$this->assertSame(array_keys($before), array_keys($after), 'A PDF outside the temporary directory was created or deleted.');
		foreach ($before as $path => $contents) {
			$this->assertSame(hash('sha256', $contents), hash('sha256', $after[$path]), $path.' was overwritten.');
		}
	}

	private function issuedInvoice(string $prefix, ?array $previousInvoice, int $numberingState): Invoice
	{
		$user = $this->createMock(User::class);
		$user->method('getFullname')->willReturn('Test User');
		$user->method('getSignatureFilename')->willReturn('');
		$user->method('getEmail')->willReturn('user@example.com');
		$user->method('belongsTo')->willReturn(true);

		$credit = $this->createMock(Konto::class);
		$credit->method('getNumber')->willReturn(760);
		$debit = $this->createMock(Konto::class);
		$debit->method('getNumber')->willReturn(120);

		$settingsCommand = new CreateOrganizationSettingsCommand();
		$settingsCommand->invoicePrefix = $prefix;
		$settingsCommand->IssueInvoiceCredit = $credit;
		$settingsCommand->IssueInvoiceDebit = $debit;
		$issuer = $this->stubIssuer();
		$settings = new OrganizationSettings($settingsCommand, $issuer, $user);
		$issuer->method('getOrganizationSettings')->willReturn($settings);

		$draftNumber = $previousInvoice['number'] ?? '2026-0000';
		$invoice = new Invoice($this->invoiceCommand($issuer, $this->stubPartner(), $draftNumber), $user);

		$registry = $this->registryReturning($previousInvoice);
		$number = InvoiceNumberFactory::factory(
			$invoice->getIssuer(),
			$numberingState,
			new \DateTimeImmutable('2026-06-01'),
			$registry,
		)->generate();
		$invoice->setIssued(new \DateTime('2026-06-01'), $number, $user);

		return $invoice;
	}

	private function send(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
	{
		$repository = $this->createMock(ObjectRepository::class);
		$repository->method('findOneBy')->willReturn($invoice);
		$doctrine = $this->createMock(ManagerRegistry::class);
		$doctrine->method('getRepository')->willReturn($repository);

		$mailer = $this->createMock(MailerInterface::class);
		$mailer->method('send')->willReturnCallback(static function (RawMessage $message): void {
			$message->toString();
		});

		$controller = $this->getMockBuilder(InvoiceCommandController::class)
			->onlyMethods(['getUser', 'generateUrl'])
			->getMock();
		$controller->method('getUser')->willReturn($invoice->getCreatedBy());
		$controller->method('generateUrl')->willReturn('/partner');

		$request = Request::create('/dashboard/invoice/send', 'POST', [
			'id' => 'issued-invoice',
			'email' => 'client@example.com',
			'subject' => 'Invoice',
			'body' => 'Please find the invoice attached.',
		]);

		return $controller->send(
			$request,
			$mailer,
			new TCPDFController(\TCPDF::class),
			$this->translator,
			$doctrine,
			new MailerSettings('test', 'null://null', ''),
		);
	}

	private function plantSentinel(Invoice $invoice): void
	{
		$logical = $this->projectRoot.'/tmp/'.$this->translator->trans('title.invoice').' '.$invoice->getNumber().'.pdf';
		$resolved = $this->collapsePath($logical);
		$directory = dirname($resolved);
		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}
		file_put_contents($resolved, 'ORIGINAL PDF CONTENTS');
	}

	private function invoiceCommand(Organization $issuer, Partner $recepient, string $number): CreateInvoiceCommand
	{
		$command = new CreateInvoiceCommand();
		$command->dateOfIssue = new \DateTimeImmutable('2026-06-01');
		$command->dateServiceRenderedFrom = new \DateTimeImmutable('2026-05-01');
		$command->dateServiceRenderedTo = new \DateTimeImmutable('2026-05-31');
		$command->dueDate = new \DateTimeImmutable('2026-06-15');
		$command->discount = 0;
		$command->number = $number;
		$command->issuer = $issuer;
		$command->recepient = $recepient;

		return $command;
	}

	private function stubIssuer(): Organization
	{
		$issuer = $this->createMock(Organization::class);
		$issuer->method('getId')->willReturn(Uuid::uuid4());
		$issuer->method('getName')->willReturn('Issuer d.o.o.');
		$issuer->method('getAddress')->willReturn($this->stubAddress());
		$issuer->method('getFullTaxNumber')->willReturn('SI12345678');
		$issuer->method('getAccountNumber')->willReturn('SI56031001001001234');
		$issuer->method('getBic')->willReturn('LJBASI2X');
		$issuer->method('getWww')->willReturn('https://issuer.example');
		$issuer->method('getEmail')->willReturn('issuer@example.com');
		$issuer->method('getMobile')->willReturn('040000000');

		return $issuer;
	}

	private function stubPartner(): Partner
	{
		$partner = $this->createMock(Partner::class);
		$partner->method('getName')->willReturn('Partner d.o.o.');
		$partner->method('getAddress')->willReturn($this->stubAddress());
		$partner->method('getFullTaxNumber')->willReturn('SI87654321');
		$partner->method('getId')->willReturn(Uuid::uuid4());
		$partner->method('emailsDifferFrom')->willReturn(false);

		return $partner;
	}

	private function stubAddress(): Address
	{
		$post = $this->createMock(Post::class);
		$post->method('getName')->willReturn('Ljubljana');
		$post->method('getNameAndCode')->willReturn('1000 Ljubljana');

		$address = $this->createMock(Address::class);
		$address->method('getFullAddress')->willReturn('Trg 1, 1000 Ljubljana');
		$address->method('getStreetAddress')->willReturn('Trg 1');
		$address->method('getPost')->willReturn($post);

		return $address;
	}

	private function registryReturning(?array $row): ManagerRegistry
	{
		$result = $this->createMock(Result::class);
		$result->method('fetchAssociative')->willReturn($row === null ? false : $row);

		$statement = $this->createMock(Statement::class);
		$statement->method('executeQuery')->willReturn($result);

		$connection = $this->createMock(Connection::class);
		$connection->method('prepare')->willReturn($statement);

		$registry = $this->createMock(ManagerRegistry::class);
		$registry->method('getConnection')->willReturn($connection);

		return $registry;
	}

	private function slovenianTranslator(): TranslatorInterface
	{
		$translator = new Translator('sl');
		$translator->addLoader('xlf', new XliffFileLoader());
		$translator->addResource(
			'xlf',
			$this->projectRoot.'/translations/messages+intl-icu.sl.xlf',
			'sl',
			'messages',
		);

		return $translator;
	}

	/**
	 * @return array<string, string>
	 */
	private function pdfContents(string $directory): array
	{
		$contents = [];
		foreach (glob($directory.'/*.pdf') ?: [] as $path) {
			if (is_file($path)) {
				$contents[$path] = (string) file_get_contents($path);
			}
		}
		ksort($contents);

		return $contents;
	}

	/**
	 * @param array<string, string> $original
	 */
	private function restorePdfs(string $directory, array $original): void
	{
		foreach ($this->pdfContents($directory) as $path => $contents) {
			if (!array_key_exists($path, $original)) {
				unlink($path);
			} elseif ($original[$path] !== $contents) {
				file_put_contents($path, $original[$path]);
			}
		}
	}

	private function collapsePath(string $path): string
	{
		$absolute = str_starts_with($path, '/');
		$parts = [];
		foreach (explode('/', $path) as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}
			if ($part === '..') {
				array_pop($parts);
				continue;
			}
			$parts[] = $part;
		}

		return ($absolute ? '/' : '').implode('/', $parts);
	}
}
