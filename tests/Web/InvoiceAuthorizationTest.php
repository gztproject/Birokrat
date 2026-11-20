<?php

namespace App\Tests\Web;

use App\Entity\Geography\Address;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use App\Entity\Invoice\Enumerators\States;
use App\Entity\Invoice\Invoice;
use App\Entity\Konto\CreateKontoCategoryCommand;
use App\Entity\Konto\CreateKontoClassCommand;
use App\Entity\Konto\CreateKontoCommand;
use App\Entity\Konto\Enumerators\KontoTypes;
use App\Entity\Konto\Konto;
use App\Entity\Konto\KontoClass;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in member of one company must not create, read, or mutate another company's sales invoices.
 *
 * These assertions describe the fixed behavior. They fail while issuer and invoice id are unchecked.
 */
class InvoiceAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $suffix;

    private string $attackerUsername;

    private string $ownOrgId;

    private string $foreignOrgId;

    private string $ownPartnerId;

    private string $foreignNewId;

    private string $foreignIssuedId;

    private string $ownNewId;

    private string $ownIssuedId;

    private string $secretBuyer;

    private string $secretItem;

    private string $ownBuyer;

    private string $ownItem;

    private string $foreignPrefix;

    private string $ownPrefix;

    private int $foreignDueDays = 73;

    private string $nonce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $this->nonce = (string) random_int(1000, 9999);
        $this->attackerUsername = 'member-'.$this->suffix;
        $this->secretBuyer = 'SecretBuyer'.$this->suffix;
        $this->secretItem = 'ConfidentialRate'.$this->suffix;
        $this->ownBuyer = 'OwnBuyer'.$this->suffix;
        $this->ownItem = 'OwnRate'.$this->suffix;
        $this->foreignPrefix = 'F'.substr($this->suffix, 0, 4);
        $this->ownPrefix = 'O'.substr($this->suffix, 0, 4);

        try {
            $this->client = static::createClient();
            $this->client->followRedirects(false);
            $em = $this->em();
            $em->getConnection()->executeQuery('SELECT 1');
            $this->seed($em);
        } catch (DbalException|\PDOException $e) {
            $this->markTestSkipped('Database is not available for invoice authorization tests: '.$e->getMessage());
        }
    }

    public function testSignedInMemberIsOnlyLinkedToTheirOwnCompany(): void
    {
        $em = $this->em();
        $attacker = $em->getRepository(User::class)->findOneBy(['username' => $this->attackerUsername]);
        $own = $em->find(Organization::class, $this->ownOrgId);
        $foreign = $em->find(Organization::class, $this->foreignOrgId);
        $this->assertInstanceOf(User::class, $attacker);

        $this->assertTrue($attacker->getOrganizations()->contains($own));
        $this->assertFalse($attacker->getOrganizations()->contains($foreign));
        $this->assertNotContains('ROLE_ADMIN', $attacker->getRoles());
    }

    public function testIssuerChoicesAreLimitedToTheMembersCompanies(): void
    {
        $this->loginAttacker();
        $crawler = $this->client->request('GET', '/dashboard/invoice/new');

        $this->assertResponseIsSuccessful();
        $values = $this->optionValues($crawler, 'select[name="invoice[issuer]"]');
        $this->assertContains($this->ownOrgId, $values, 'The member company is missing from the issuer list: '.implode(',', $values));
        $this->assertNotContains($this->foreignOrgId, $values, 'Another company is offered as the issuer: '.implode(',', $values));
    }

    public function testMemberCanCreateAnInvoiceForTheirOwnCompany(): void
    {
        $this->loginAttacker();
        $number = 'N1-9999-'.$this->nonce;
        $before = $this->invoiceCount($this->ownOrgId);

        $this->client->request('POST', '/dashboard/invoice/new', $this->invoiceForm($this->ownOrgId, $this->ownPartnerId, $number, $this->ownItem));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 302]);
        $created = $this->invoiceByNumber($number);
        $this->assertInstanceOf(Invoice::class, $created);
        $this->assertSame($this->ownOrgId, $created->getIssuer()->getId()->toString());
        $this->assertSame($before + 1, $this->invoiceCount($this->ownOrgId));
    }

    public function testCreateWithAnotherCompanysIssuerDoesNotPersist(): void
    {
        $this->loginAttacker();
        $number = 'N2-9999-'.$this->nonce;
        $beforeForeign = $this->invoiceCount($this->foreignOrgId);
        $beforeOwn = $this->invoiceCount($this->ownOrgId);

        $this->client->request('POST', '/dashboard/invoice/new', $this->invoiceForm($this->foreignOrgId, $this->ownPartnerId, $number, $this->secretItem));

        $this->assertNull($this->invoiceByNumber($number));
        $this->assertSame($beforeForeign, $this->invoiceCount($this->foreignOrgId));
        $this->assertSame($beforeOwn, $this->invoiceCount($this->ownOrgId));
        $this->assertNotSame(302, $this->client->getResponse()->getStatusCode(), $this->responseHint('create'));
    }

    public function testMemberCanEditTheirOwnInvoice(): void
    {
        $this->loginAttacker();
        $number = 'ON-2099-7008';

        $this->client->request('POST', '/dashboard/invoice/'.$this->ownNewId.'/edit', $this->invoiceForm($this->ownOrgId, $this->ownPartnerId, $number, $this->ownItem));

        $invoice = $this->reloadInvoice($this->ownNewId);
        $this->assertSame($number, $this->storedNumber($this->ownNewId));
        $this->assertSame($this->ownOrgId, $invoice->getIssuer()->getId()->toString());
    }

    public function testEditOfAnotherCompanysInvoiceDoesNotChangeIt(): void
    {
        $this->loginAttacker();
        $beforeNumber = $this->storedNumber($this->foreignNewId);
        $beforeIssuer = $this->reloadInvoice($this->foreignNewId)->getIssuer()->getId()->toString();

        $getStatus = $this->client->request('GET', '/dashboard/invoice/'.$this->foreignNewId.'/edit')->filter('body')->count() >= 0
            ? $this->client->getResponse()->getStatusCode()
            : 0;
        $this->client->request('POST', '/dashboard/invoice/'.$this->foreignNewId.'/edit', $this->invoiceForm($this->foreignOrgId, $this->ownPartnerId, 'HK-2099-0002', 'Changed'.$this->suffix));

        $invoice = $this->reloadInvoice($this->foreignNewId);
        $this->assertSame($beforeNumber, $this->storedNumber($this->foreignNewId));
        $this->assertSame($beforeIssuer, $invoice->getIssuer()->getId()->toString());
        $this->assertSame('ON-9999-9004', $this->storedNumber($this->ownNewId));
        $this->assertSame($this->ownOrgId, $this->reloadInvoice($this->ownNewId)->getIssuer()->getId()->toString());
        $this->assertRefusedCode($getStatus, 'edit show');
        $this->assertRefused($this->client->getResponse(), 'edit');
        $this->assertStringNotContainsString($this->secretItem, (string) $this->client->getInternalResponse()->getContent());
    }

    public function testMemberCannotMoveTheirInvoiceToAnotherCompany(): void
    {
        $this->loginAttacker();
        $beforeForeign = $this->invoiceCount($this->foreignOrgId);

        $this->client->request('POST', '/dashboard/invoice/'.$this->ownNewId.'/edit', $this->invoiceForm($this->foreignOrgId, $this->ownPartnerId, 'ON-9999-9004', $this->ownItem));

        $invoice = $this->reloadInvoice($this->ownNewId);
        $this->assertSame($this->ownOrgId, $invoice->getIssuer()->getId()->toString());
        $this->assertSame('ON-9999-9004', $this->storedNumber($this->ownNewId));
        $this->assertSame($beforeForeign, $this->invoiceCount($this->foreignOrgId));
    }

    public function testMemberCanIssueTheirOwnInvoice(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/issue', [
            'id' => $this->ownNewId,
            'date' => '9999-12-31',
        ]);

        $invoice = $this->reloadInvoice($this->ownNewId);
        $this->assertSame(States::issued, $invoice->getState());
        $this->assertSame(1, $this->transactionCount($this->ownNewId));
        $this->assertSame($this->ownOrgId, $invoice->getIssuer()->getId()->toString());
    }

    public function testIssueOfAnotherCompanysInvoiceDoesNotBookIt(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/issue', [
            'id' => $this->foreignNewId,
            'date' => '9999-12-31',
        ]);

        $foreign = $this->reloadInvoice($this->foreignNewId);
        $own = $this->reloadInvoice($this->ownNewId);
        $this->assertSame(States::new, $foreign->getState());
        $this->assertSame('ZN-9999-9002', $this->storedNumber($this->foreignNewId));
        $this->assertSame(0, $this->transactionCount($this->foreignNewId));
        $this->assertSame(States::new, $own->getState());
        $this->assertSame(0, $this->transactionCount($this->ownNewId));
        $this->assertRefused($this->client->getResponse(), 'issue');
    }

    public function testMemberCanMarkTheirOwnInvoicePaid(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/pay', [
            'id' => $this->ownIssuedId,
            'date' => '2099-06-02',
            'bankCost' => '0',
        ]);

        $invoice = $this->reloadInvoice($this->ownIssuedId);
        $this->assertSame(States::paid, $invoice->getState());
        $this->assertNotNull($invoice->getDatePaid());
        $this->assertSame(2, $this->transactionCount($this->ownIssuedId));
    }

    public function testPayOfAnotherCompanysInvoiceDoesNotBookIt(): void
    {
        $this->loginAttacker();
        $beforeForeign = $this->transactionCount($this->foreignIssuedId);
        $beforeOwn = $this->transactionCount($this->ownIssuedId);

        $this->client->request('POST', '/dashboard/invoice/pay', [
            'id' => $this->foreignIssuedId,
            'date' => '2099-06-02',
            'bankCost' => '0',
        ]);

        $foreign = $this->reloadInvoice($this->foreignIssuedId);
        $own = $this->reloadInvoice($this->ownIssuedId);
        $this->assertSame(States::issued, $foreign->getState());
        $this->assertNull($foreign->getDatePaid());
        $this->assertSame($beforeForeign, $this->transactionCount($this->foreignIssuedId));
        $this->assertSame(States::issued, $own->getState());
        $this->assertNull($own->getDatePaid());
        $this->assertSame($beforeOwn, $this->transactionCount($this->ownIssuedId));
        $this->assertRefused($this->client->getResponse(), 'pay');
    }

    public function testMemberCanCancelTheirOwnInvoice(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/cancel', [
            'id' => $this->ownNewId,
            'reason' => 'duplicate',
        ]);

        $invoice = $this->reloadInvoice($this->ownNewId);
        $this->assertSame(States::cancelled, $invoice->getState());
        $this->assertSame('duplicate', $invoice->getCancelReason());
    }

    public function testCancelOfAnotherCompanysInvoiceDoesNotChangeIt(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/cancel', [
            'id' => $this->foreignNewId,
            'reason' => 'forged',
        ]);

        $foreign = $this->reloadInvoice($this->foreignNewId);
        $own = $this->reloadInvoice($this->ownNewId);
        $this->assertSame(States::new, $foreign->getState());
        $this->assertNull($foreign->getCancelReason());
        $this->assertSame(States::new, $own->getState());
        $this->assertNull($own->getCancelReason());
        $this->assertRefused($this->client->getResponse(), 'cancel');
    }

    public function testMemberCanOpenTheirOwnInvoiceAndPdf(): void
    {
        $this->loginAttacker();

        $this->client->request('GET', '/dashboard/invoice/'.$this->ownIssuedId.'/show');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->ownItem, (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/dashboard/invoice/'.$this->ownIssuedId.'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/dashboard/invoice/'.$this->ownIssuedId.'/print');
        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF', (string) $this->client->getResponse()->getContent());
    }

    public function testShowPdfAndPrintOfAnotherCompanysInvoiceAreRefused(): void
    {
        $this->loginAttacker();

        $this->client->request('GET', '/dashboard/invoice/'.$this->foreignIssuedId.'/show');
        $show = $this->client->getResponse();
        $this->assertRefused($show, 'show');
        $this->assertStringNotContainsString($this->secretItem, (string) $show->getContent());
        $this->assertStringNotContainsString($this->secretBuyer, (string) $show->getContent());

        $this->client->request('GET', '/dashboard/invoice/'.$this->foreignIssuedId.'/pdf');
        $pdf = $this->client->getResponse();
        $this->assertRefused($pdf, 'pdf');
        $this->assertStringStartsNotWith('%PDF', (string) $pdf->getContent());

        $this->client->request('GET', '/dashboard/invoice/'.$this->foreignIssuedId.'/print');
        $print = $this->client->getResponse();
        $this->assertRefused($print, 'print');
        $this->assertStringStartsNotWith('%PDF', (string) $print->getContent());
    }

    public function testInvoiceIndexAndDashboardHideAnotherCompanysInvoices(): void
    {
        $this->loginAttacker();

        $this->client->request('GET', '/dashboard/invoice');
        $this->assertResponseIsSuccessful();
        $index = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBuyer, $index);
        $this->assertStringContainsString('OA-9999-9003', $index);
        $this->assertStringNotContainsString($this->secretBuyer, $index);
        $this->assertStringNotContainsString('ZZ-9999-9001', $index);

        $this->client->request('GET', '/dashboard/invoice?partial=1');
        $this->assertResponseIsSuccessful();
        $partial = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBuyer, $partial);
        $this->assertStringNotContainsString($this->secretBuyer, $partial);
        $this->assertStringNotContainsString('ZZ-9999-9001', $partial);

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $dashboard = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->ownBuyer, $dashboard);
        $this->assertStringNotContainsString($this->secretBuyer, $dashboard);
        $this->assertStringNotContainsString('ZZ-9999-9001', $dashboard);
    }

    public function testCloneOfAnotherCompanysInvoiceDoesNotCopyOrPersistIt(): void
    {
        $this->loginAttacker();
        $before = $this->invoiceCount($this->foreignOrgId) + $this->invoiceCount($this->ownOrgId);
        $filesBefore = $this->tmpFiles();

        $this->client->request('GET', '/dashboard/invoice/'.$this->foreignIssuedId.'/clone');
        $show = $this->client->getResponse();
        $this->assertRefused($show, 'clone');
        $this->assertStringNotContainsString($this->secretItem, (string) $show->getContent());

        $this->client->request('POST', '/dashboard/invoice/'.$this->foreignIssuedId.'/clone', $this->invoiceForm($this->ownOrgId, $this->ownPartnerId, 'CL-2099-0001', $this->secretItem));

        $this->assertNull($this->invoiceByNumber('CL-2099-0001'));
        $this->assertSame($before, $this->invoiceCount($this->foreignOrgId) + $this->invoiceCount($this->ownOrgId));
        $this->assertRefused($this->client->getResponse(), 'clone submit');
        $this->assertSame($filesBefore, $this->tmpFiles());
    }

    public function testSendOfAnotherCompanysInvoiceDoesNotRenderItsPdf(): void
    {
        $this->loginAttacker();
        $filesBefore = $this->tmpFiles();
        $beforeState = $this->reloadInvoice($this->foreignIssuedId)->getState();

        $this->client->request('POST', '/dashboard/invoice/send', [
            'id' => $this->foreignIssuedId,
            'email' => 'customer@example.test',
            'cc' => '',
            'subject' => 'Invoice',
            'body' => 'Please pay',
        ]);

        $response = $this->client->getResponse();
        $this->assertRefused($response, 'send');
        $payload = json_decode((string) $response->getContent(), true);
        $status = is_array($payload) ? ($payload[0]['status'] ?? null) : null;
        $this->assertNotSame('ok', $status);
        $this->assertSame($filesBefore, $this->tmpFiles());
        $this->assertSame($beforeState, $this->reloadInvoice($this->foreignIssuedId)->getState());
    }

    public function testMemberCanReadTheirOwnIssuerNumberAndDueDays(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/getNewNumber', [
            'issuerId' => $this->ownOrgId,
            'dateOfIssue' => '2099-06-01',
            'state' => '10',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->ownPrefix, (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', '/dashboard/invoice/getDefaultDueInDays', [
            'issuerId' => $this->ownOrgId,
        ]);
        $this->assertResponseIsSuccessful();
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('ok', $decoded[0]['status'] ?? null);
        $this->assertContains(15, $decoded[0]['data'] ?? []);
    }

    public function testIssuerLookupForAnotherCompanyIsRefused(): void
    {
        $this->loginAttacker();

        $this->client->request('POST', '/dashboard/invoice/getNewNumber', [
            'issuerId' => $this->foreignOrgId,
            'dateOfIssue' => '9999-12-31',
            'state' => '10',
        ]);
        $numberResponse = $this->client->getResponse();
        $this->assertRefused($numberResponse, 'getNewNumber');
        $this->assertStringNotContainsString($this->foreignPrefix, (string) $numberResponse->getContent());

        $this->client->request('POST', '/dashboard/invoice/getDefaultDueInDays', [
            'issuerId' => $this->foreignOrgId,
        ]);
        $dueResponse = $this->client->getResponse();
        $this->assertRefused($dueResponse, 'getDefaultDueInDays');
        $decoded = json_decode((string) $dueResponse->getContent(), true);
        $revealed = is_array($decoded) && ($decoded[0]['status'] ?? null) === 'ok' && in_array($this->foreignDueDays, $decoded[0]['data'] ?? [], true);
        $this->assertFalse($revealed);
    }

    private function seed(EntityManagerInterface $em): void
    {
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->getConnection()->executeStatement("DELETE FROM transaction WHERE invoice_id IN (SELECT id FROM invoice WHERE date_of_issue = '9999-12-31')");
        $em->getConnection()->executeStatement("DELETE FROM invoice_item WHERE invoice_id IN (SELECT id FROM invoice WHERE date_of_issue = '9999-12-31')");
        $em->getConnection()->executeStatement("DELETE FROM invoice WHERE date_of_issue = '9999-12-31'");
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $bootstrap = $this->newUser('bootstrap-'.$this->suffix, 'Bootstrap', 'User');
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $em->persist($bootstrap);
        $em->flush();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $attacker = $this->newUser($this->attackerUsername, 'Ada', 'Member', $bootstrap);
        $victim = $this->newUser('victim-'.$this->suffix, 'Bea', 'Owner', $bootstrap);
        $em->persist($attacker);
        $em->persist($victim);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $bootstrap->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $bootstrap);
        $address = $post->createAddress($this->addressCommand('Trg 1'), $bootstrap);

        $own = $this->organization($attacker, 'Own '.$this->suffix, 'O'.$this->suffix, $address);
        $foreign = $this->organization($victim, 'Foreign '.$this->suffix, 'F'.$this->suffix, $address);
        $attacker->addOrganization($own, $attacker);
        $victim->addOrganization($foreign, $victim);

        $debit = $this->konto($bootstrap, 120, 'Receivables');
        $credit = $this->konto($bootstrap, 761, 'Revenue');
        $cash = $this->konto($bootstrap, 112, 'Bank');
        $this->wireKontos($own, $debit, $credit, $cash, $this->ownPrefix, 15);
        $this->wireKontos($foreign, $debit, $credit, $cash, $this->foreignPrefix, $this->foreignDueDays);

        $ownPartner = $this->partner($attacker, $this->ownBuyer, 'P'.$this->suffix, $address);
        $foreignPartner = $this->partner($victim, $this->secretBuyer, 'Q'.$this->suffix, $address);

        $ownNew = $this->invoice($attacker, $own, $ownPartner, 'ON-9999-9004', $this->ownItem);
        $ownIssued = $this->invoice($attacker, $own, $ownPartner, 'OA-9999-9003', $this->ownItem);
        $foreignNew = $this->invoice($victim, $foreign, $foreignPartner, 'ZN-9999-9002', $this->secretItem);
        $foreignIssued = $this->invoice($victim, $foreign, $foreignPartner, 'ZZ-9999-9001', $this->secretItem);
        $ownIssueTx = $ownIssued->setIssued(new \DateTime('9999-12-31'), 'OA-9999-9003', $attacker);
        $foreignIssueTx = $foreignIssued->setIssued(new \DateTime('9999-12-31'), 'ZZ-9999-9001', $victim);

        $entities = [$country, $post, $address, $own, $foreign, $own->getOrganizationSettings(), $foreign->getOrganizationSettings(), $ownPartner, $foreignPartner, $ownNew, $ownIssued, $foreignNew, $foreignIssued, $ownNew->getInvoiceItems()->first(), $ownIssued->getInvoiceItems()->first(), $foreignNew->getInvoiceItems()->first(), $foreignIssued->getInvoiceItems()->first(), $ownIssueTx, $foreignIssueTx];
        foreach ([$debit, $credit, $cash] as $konto) {
            $entities[] = $konto->getClass();
            $entities[] = $konto->getCategory();
            $entities[] = $konto;
        }
        foreach ($entities as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $this->ownOrgId = $own->getId()->toString();
        $this->foreignOrgId = $foreign->getId()->toString();
        $this->ownPartnerId = $ownPartner->getId()->toString();
        $this->ownNewId = $ownNew->getId()->toString();
        $this->ownIssuedId = $ownIssued->getId()->toString();
        $this->foreignNewId = $foreignNew->getId()->toString();
        $this->foreignIssuedId = $foreignIssued->getId()->toString();
    }

    private function loginAttacker(): void
    {
        $this->client->loginUser($this->user($this->attackerUsername));
    }

    private function invoiceForm(string $issuerId, string $partnerId, string $number, string $itemName): array
    {
        $token = $this->csrfToken();

        return [
            'invoice' => [
                'issuer' => $issuerId,
                'number' => $number,
                'recepient' => $partnerId,
                'dateOfIssue' => '2099-06-01',
                'dueDate' => '2099-06-15',
                'dateServiceRenderedFrom' => '2099-05-01',
                'dateServiceRenderedTo' => '2099-05-31',
                'discount' => '0',
                'invoiceItemCommands' => [
                    0 => [
                        'code' => '1',
                        'name' => $itemName,
                        'quantity' => '1',
                        'unit' => 'x',
                        'price' => '4242.42',
                        'discount' => '0',
                    ],
                ],
                '_token' => $token,
            ],
        ];
    }

    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/invoice/new');
        $this->assertResponseIsSuccessful('The signed-in member could not open the invoice form: '.$this->responseHint('new'));
        $field = $crawler->filter('input[name="invoice[_token]"]');
        $this->assertGreaterThan(0, $field->count(), 'Invoice form did not render a CSRF field.');

        return (string) $field->attr('value');
    }

    private function optionValues(\Symfony\Component\DomCrawler\Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector.' option')->each(static fn ($option) => (string) $option->attr('value'));
    }

    private function assertRefused(Response $response, string $action): void
    {
        $this->assertRefusedCode($response->getStatusCode(), $action.' '.$this->responseHint($action));
    }

    private function assertRefusedCode(int $status, string $action): void
    {
        $this->assertContains($status, [403, 404], $action.' was not refused (HTTP '.$status.').');
    }

    private function responseHint(string $action): string
    {
        $content = (string) $this->client->getResponse()->getContent();

        return $action.' HTTP '.$this->client->getResponse()->getStatusCode().' '.substr($content, 0, 400);
    }

    private function invoiceCount(string $organizationId): int
    {
        return (int) $this->em()->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->andWhere('i.issuer = :issuer')
            ->setParameter('issuer', $organizationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function transactionCount(string $invoiceId): int
    {
        return (int) $this->em()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->andWhere('t.invoice = :invoice')
            ->setParameter('invoice', $invoiceId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function storedNumber(string $id): string
    {
        return (string) $this->em()->createQueryBuilder()
            ->select('i.number')
            ->from(Invoice::class, 'i')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function invoiceByNumber(string $number): ?Invoice
    {
        $invoice = $this->em()->getRepository(Invoice::class)->findOneBy(['number' => $number]);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    private function reloadInvoice(string $id): Invoice
    {
        $invoice = $this->em()->find(Invoice::class, $id);
        $this->assertInstanceOf(Invoice::class, $invoice);

        return $invoice;
    }

    private function user(string $username): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        if (!$em->isOpen()) {
            static::ensureKernelShutdown();
            $this->client = static::createClient();
            $em = static::getContainer()->get(EntityManagerInterface::class);
        }
        $em->clear();

        return $em;
    }

    /**
     * @return list<string>
     */
    private function tmpFiles(): array
    {
        $paths = glob(dirname(__DIR__, 2).'/tmp/*') ?: [];
        sort($paths);

        return $paths;
    }

    private function newUser(string $username, string $firstName, string $lastName, ?User $creator = null): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->setProp($user, 'id', Uuid::uuid6());
        $this->setProp($user, 'createdOn', new \DateTime());
        $this->setProp($user, 'createdBy', $creator ?? $user);
        $this->setProp($user, 'isActive', true);
        $this->setProp($user, 'organizations', new ArrayCollection());
        $this->setProp($user, 'username', $username);
        $this->setProp($user, 'firstName', $firstName);
        $this->setProp($user, 'lastName', $lastName);
        $this->setProp($user, 'email', $username.'@example.test');
        $this->setProp($user, 'mobile', null);
        $this->setProp($user, 'phone', null);
        $this->setProp($user, 'signatureFilename', '');
        $this->setProp($user, 'position', 'Clerk');
        $this->setProp($user, 'totpEnabled', false);
        $this->setProp($user, 'totpSecret', null);
        $this->setProp($user, 'roles', ['ROLE_USER']);
        $this->setProp($user, 'password', 'placeholder-hash');

        return $user;
    }

    private function organization(User $owner, string $name, string $code, Address $address): Organization
    {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = '12345678';
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = 'SI56031001001001234';
        $command->bic = 'LJBASI2X';

        return $owner->createOrganization($command);
    }

    private function partner(User $owner, string $name, string $code, Address $address): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = '87654321';
        $command->taxable = true;
        $command->address = $address;
        $command->isClient = true;
        $command->isSupplier = false;
        $command->email = 'buyer@example.test';

        return $owner->createPartner($command);
    }

    private function addressCommand(string $line1): CreateAddressCommand
    {
        $command = new CreateAddressCommand();
        $command->line1 = $line1;
        $command->line2 = '';

        return $command;
    }

    private function konto(User $owner, int $number, string $name): Konto
    {
        $classCommand = new class extends CreateKontoClassCommand {};
        $classCommand->number = 1;
        $classCommand->name = 'Class '.$number;
        $classCommand->description = 'class';
        $class = new KontoClass($classCommand, $owner);

        $categoryCommand = new class extends CreateKontoCategoryCommand {};
        $categoryCommand->number = $number;
        $categoryCommand->name = 'Category '.$number;
        $categoryCommand->description = 'category';
        $category = $class->createCategory($categoryCommand, $owner);

        $kontoCommand = new class extends CreateKontoCommand {
            public int $type = KontoTypes::active;
        };
        $kontoCommand->number = $number;
        $kontoCommand->name = $name;
        $kontoCommand->description = $name;
        $kontoCommand->isActive = true;
        $kontoCommand->type = KontoTypes::active;

        return $category->createKonto($kontoCommand, $owner);
    }

    private function wireKontos(Organization $organization, Konto $debit, Konto $credit, Konto $cash, string $prefix, int $dueDays): void
    {
        $settings = $organization->getOrganizationSettings();
        $this->assertInstanceOf(OrganizationSettings::class, $settings);
        $this->setProp($settings, 'IssueInvoiceDebit', $debit);
        $this->setProp($settings, 'IssueInvoiceCredit', $credit);
        $this->setProp($settings, 'InvoicePaidDebit', $cash);
        $this->setProp($settings, 'InvoicePaidCredit', $debit);
        $this->setProp($settings, 'invoicePrefix', $prefix);
        $this->setProp($settings, 'defaultPaymentDueIn', $dueDays);
    }

    private function invoice(User $owner, Organization $issuer, Partner $recepient, string $number, string $itemName): Invoice
    {
        $command = new CreateInvoiceCommand();
        $command->dateOfIssue = new \DateTime('9999-12-31');
        $command->dateServiceRenderedFrom = new \DateTime('2099-05-01');
        $command->dateServiceRenderedTo = new \DateTime('2099-05-31');
        $command->dueDate = new \DateTime('2099-06-15');
        $command->discount = 0;
        $command->number = $number;
        $command->issuer = $issuer;
        $command->recepient = $recepient;
        $invoice = $owner->createInvoice($command);

        $item = new CreateInvoiceItemCommand();
        $item->code = '1';
        $item->name = $itemName;
        $item->quantity = 1;
        $item->unit = 'x';
        $item->price = 4242.42;
        $item->discount = 0;
        $invoice->createInvoiceItem($item);

        return $invoice;
    }

    private function setProp(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $parent = $class->getParentClass();
            if (!$parent instanceof ReflectionClass) {
                throw new \RuntimeException('Missing property '.$name);
            }
            $class = $parent;
        }
        $class->getProperty($name)->setValue($object, $value);
    }
}
