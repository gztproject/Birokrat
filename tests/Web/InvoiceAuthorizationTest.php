<?php

namespace App\Tests\Web;

use App\Entity\Invoice\Enumerators\States;
use App\Entity\Invoice\Invoice;
use App\Entity\Transaction\Transaction;
use App\Entity\User\User;
use App\Tests\Support\InvoiceAuthorizationWorld;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A signed-in user may read and change invoices only for companies they belong to.
 * These tests fail while invoice queries and id actions ignore that membership.
 */
class InvoiceAuthorizationTest extends WebTestCase
{
    private KernelBrowser $client;
    private InvoiceAuthorizationWorld $world;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $em = $container->get(EntityManagerInterface::class);
            $em->getConnection()->executeQuery('SELECT 1');
            $this->world = (new InvoiceAuthorizationWorld(
                $em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
        } catch (DbalException|\PDOException $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }

        $this->assertNotContains('ROLE_ADMIN', $this->world->attacker->getRoles());
        $this->assertContains('ROLE_USER', $this->world->attacker->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $this->world->owner->getRoles());
        $this->logIn($this->world->attacker);
    }

    public function testAnonymousInvoicePdfRedirectsToLogin(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimIssuedId.'/pdf');
        $this->assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/login', $location);
    }

    public function testMemberSeesOwnInvoiceAndNotOnlyTheirsWhenUnscoped(): void
    {
        $this->client->loginUser($this->world->owner);

        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimDraftId.'/show');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->world->victimAccount, (string) $this->client->getResponse()->getContent());
        $this->assertStringContainsString($this->world->victimShort, (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimIssuedId.'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('pdf', (string) $this->client->getResponse()->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', (string) $this->client->getResponse()->getContent());
    }

    public function testMemberCanIssueOwnInvoice(): void
    {
        $this->client->loginUser($this->world->owner);
        $this->client->request('POST', '/dashboard/invoice/issue', [
            'id' => $this->world->victimDraftId,
            'date' => '2999-12-31',
        ]);
        $this->assertResponseIsSuccessful();
        $invoice = $this->reload($this->world->victimDraftId);
        $this->assertSame(States::issued, $invoice->getState());
        $this->assertSame(1, $this->transactionCount($this->world->victimDraftId));
    }

    public function testStrangerInvoiceListIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/dashboard/invoice');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimDraftId, 'sales invoice list');
        $this->assertHides($this->world->victimIssuedNumber, 'sales invoice list');
        $this->assertHides($this->world->victimShort, 'sales invoice list');
        $this->assertStringContainsString($this->world->attackerShort, (string) $this->client->getResponse()->getContent());
    }

    public function testStrangerPartialInvoiceListIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/dashboard/invoice?partial=1');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimDraftId, 'sales invoice partial list');
        $this->assertHides($this->world->victimShort, 'sales invoice partial list');
    }

    public function testStrangerDashboardIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimIssuedNumber, 'dashboard');
        $this->assertHides($this->world->victimShort, 'dashboard');
        $this->assertHides($this->money($this->world->victimPrice), 'dashboard');
    }

    public function testStrangerHomeIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimIssuedNumber, 'home');
        $this->assertHides($this->world->victimShort, 'home');
        $this->assertHides($this->money($this->world->victimPrice), 'home');
    }

    public function testStrangerReportIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/dashboard/report');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimCustomer, 'client report');
    }

    public function testStrangerReportForAnotherCompanyIncludesItsCustomers(): void
    {
        $this->client->request('GET', '/dashboard/report?organization='.$this->world->victimOrg->getId()->toString());
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimCustomer, 'client report filtered to another company');
    }

    public function testStrangerTransactionListIncludesOtherCompanies(): void
    {
        $this->client->request('GET', '/dashboard/transaction');
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimIssuedNumber, 'ledger list');
        $this->assertHides($this->world->victimShort, 'ledger list');
    }

    public function testStrangerTransactionListForAnotherCompany(): void
    {
        $this->client->request('GET', '/dashboard/transaction?organization='.$this->world->victimOrg->getId()->toString());
        $this->assertResponseIsSuccessful();
        $this->assertHides($this->world->victimIssuedNumber, 'ledger list for another company');
    }

    public function testStrangerCannotOpenShow(): void
    {
        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimDraftId.'/show');
        $show = (string) $this->client->getResponse()->getContent();
        $this->assertRejected(
            'Invoice show',
            (str_contains($show, $this->world->victimAccount) ? 'response includes the issuer account. ' : '')
            .(str_contains($show, $this->world->victimShort) ? 'response includes the customer. ' : '')
        );
    }

    public function testStrangerCannotDownloadPdf(): void
    {
        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimIssuedId.'/pdf');
        $pdf = (string) $this->client->getResponse()->getContent();
        $this->assertRejected(
            'Invoice pdf',
            (str_starts_with($pdf, '%PDF') ? 'pdf bytes returned. ' : '')
            .(str_contains($pdf, $this->world->victimAccount) ? 'pdf includes the issuer account. ' : '')
            .'content-type='.$this->client->getResponse()->headers->get('content-type').'. '
        );
    }

    public function testStrangerCannotPrint(): void
    {
        $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimIssuedId.'/print');
        $print = (string) $this->client->getResponse()->getContent();
        $this->assertRejected(
            'Invoice print',
            (str_starts_with($print, '%PDF') ? 'pdf bytes returned. ' : '')
            .(str_contains($print, $this->world->victimAccount) ? 'pdf includes the issuer account. ' : '')
        );
    }

    public function testStrangerCannotEditAnotherCompanysInvoice(): void
    {
        $stolen = '9-2999-'.$this->tail();
        $crawler = $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimDraftId.'/edit');
        if ($this->client->getResponse()->getStatusCode() === 200 && $crawler->filter('form[name="invoice"]')->count() > 0) {
            $form = $crawler->filter('form[name="invoice"]')->form();
            $form['invoice[number]'] = $stolen;
            $this->client->submit($form);
        } else {
            $token = $this->invoiceFormToken();
            $this->client->request('POST', '/dashboard/invoice/'.$this->world->victimDraftId.'/edit', [
                'invoice' => $this->invoicePayload($stolen, $token, $this->world->victimOrg->getId()->toString()),
            ]);
        }

        $stored = $this->storedNumber($this->world->victimDraftId);
        $this->assertRejected(
            'Invoice edit',
            $stored === $this->world->victimDraftNumber ? '' : 'stored number changed to '.$stored.'. '
        );
        $this->assertSame($this->world->attackerDraftNumber, $this->storedNumber($this->world->attackerDraftId));
    }

    public function testStrangerCannotCloneAnotherCompanysInvoice(): void
    {
        $stolen = '8-2999-'.$this->tail();
        $before = $this->invoiceCount();
        $crawler = $this->client->request('GET', '/dashboard/invoice/'.$this->world->victimIssuedId.'/clone');
        $revealed = str_contains((string) $this->client->getResponse()->getContent(), $this->world->victimIssuedNumber);
        if ($this->client->getResponse()->getStatusCode() === 200 && $crawler->filter('form[name="invoice"]')->count() > 0) {
            $form = $crawler->filter('form[name="invoice"]')->form();
            $form['invoice[number]'] = $stolen;
            $this->client->submit($form);
        } else {
            $token = $this->invoiceFormToken();
            $this->client->request('POST', '/dashboard/invoice/'.$this->world->victimIssuedId.'/clone', [
                'invoice' => $this->invoicePayload($stolen, $token, $this->world->victimOrg->getId()->toString()),
            ]);
        }

        $created = $this->findByNumber($stolen);
        $this->assertRejected(
            'Invoice clone',
            ($revealed ? 'clone form included the other invoice number. ' : '')
            .($created instanceof Invoice ? 'a new invoice '.$stolen.' was stored. ' : '')
            .($this->invoiceCount() > $before ? 'invoice count increased. ' : '')
        );
        $this->assertNull($created);
    }

    public function testStrangerCannotIssueAnotherCompanysInvoice(): void
    {
        $this->client->request('POST', '/dashboard/invoice/issue', [
            'id' => $this->world->victimDraftId,
            'date' => '2999-12-31',
        ]);
        $invoice = $this->reload($this->world->victimDraftId);
        $count = $this->transactionCount($this->world->victimDraftId);
        $this->assertRejected(
            'Invoice issue',
            ($invoice->getState() === States::new ? '' : 'state='.$invoice->getState().'. ')
            .($invoice->getNumber() === $this->world->victimDraftNumber ? '' : 'number='.$invoice->getNumber().'. ')
            .($count === 0 ? '' : 'ledger entries='.$count.'. ')
        );
        $this->assertSame(States::new, $this->reload($this->world->attackerDraftId)->getState());
    }

    public function testStrangerCannotPayAnotherCompanysInvoice(): void
    {
        $this->client->request('POST', '/dashboard/invoice/pay', [
            'id' => $this->world->victimIssuedId,
            'date' => '2999-12-31',
            'bankCost' => '0',
        ]);
        $invoice = $this->reload($this->world->victimIssuedId);
        $count = $this->transactionCount($this->world->victimIssuedId);
        $this->assertRejected(
            'Invoice pay',
            ($invoice->getState() === States::issued ? '' : 'state='.$invoice->getState().'. ')
            .($invoice->getDatePaid() === null ? '' : 'datePaid was set. ')
            .($count === 1 ? '' : 'ledger entries='.$count.'. ')
        );
        $this->assertSame(States::issued, $this->reload($this->world->attackerIssuedId)->getState());
    }

    public function testMemberCanPayOwnInvoice(): void
    {
        $this->client->loginUser($this->world->owner);
        $this->client->request('POST', '/dashboard/invoice/pay', [
            'id' => $this->world->victimIssuedId,
            'date' => '2999-12-31',
            'bankCost' => '0',
        ]);
        $this->assertResponseRedirects();
        $invoice = $this->reload($this->world->victimIssuedId);
        $this->assertSame(States::paid, $invoice->getState());
        $this->assertNotNull($invoice->getDatePaid());
    }

    public function testStrangerCannotCancelAnotherCompanysInvoice(): void
    {
        $this->client->request('POST', '/dashboard/invoice/cancel', [
            'id' => $this->world->victimDraftId,
            'reason' => 'not my invoice',
        ]);
        $invoice = $this->reload($this->world->victimDraftId);
        $this->assertRejected(
            'Invoice cancel',
            ($invoice->getState() === States::new ? '' : 'state='.$invoice->getState().'. ')
            .($invoice->getCancelReason() === null ? '' : 'reason='.$invoice->getCancelReason().'. ')
        );
    }

    public function testStrangerCannotEmailAnotherCompanysInvoice(): void
    {
        $chosen = 'collector@example.test';
        $events = $this->watchTmp(function () use ($chosen): void {
            $this->client->request('POST', '/dashboard/invoice/send', [
                'id' => $this->world->victimIssuedId,
                'email' => $chosen,
                'cc' => '',
                'subject' => 'copy',
                'body' => 'copy',
            ]);
        });
        $mailed = $this->describeMail($chosen);
        $wrotePdf = $this->eventsMention($events, $this->world->victimIssuedNumber);
        $invoice = $this->reload($this->world->victimIssuedId);
        $this->assertRejected(
            'Invoice send',
            $mailed
            .($wrotePdf ? 'pdf file was created for '.$this->world->victimIssuedNumber.'. ' : '')
            .($invoice->getState() === States::issued ? '' : 'state='.$invoice->getState().'. ')
        );
        $this->assertSame('', $mailed);
        $this->assertFalse($wrotePdf);
    }

    public function testNewInvoiceFormListsEveryCompany(): void
    {
        $this->client->request('GET', '/dashboard/invoice/new');
        $this->assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($this->world->attackerOrgName, $html);
        $this->assertHides($this->world->victimOrgName, 'new invoice issuer list');
    }

    public function testStrangerCannotAskForAnotherCompanysInvoiceNumber(): void
    {
        $this->client->request('POST', '/dashboard/invoice/getNewNumber', [
            'issuerId' => $this->world->victimOrg->getId()->toString(),
            'dateOfIssue' => '2999-12-31',
            'state' => '10',
        ]);
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertRejected(
            'Next invoice number',
            str_contains($body, 'VQ-') ? 'response included a number from the other company: '.$body.' ' : ''
        );
    }

    public function testMemberCanAskForOwnInvoiceNumber(): void
    {
        $this->client->loginUser($this->world->owner);
        $this->client->request('POST', '/dashboard/invoice/getNewNumber', [
            'issuerId' => $this->world->victimOrg->getId()->toString(),
            'dateOfIssue' => '2999-12-31',
            'state' => '10',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('VQ-', (string) $this->client->getResponse()->getContent());
    }

    public function testStrangerCannotReadAnotherCompanysPaymentTerms(): void
    {
        $this->client->request('POST', '/dashboard/invoice/getDefaultDueInDays', [
            'issuerId' => $this->world->victimOrg->getId()->toString(),
        ]);
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertRejected(
            'Default due days',
            str_contains($body, (string) $this->world->victimDueDays) ? 'response included the other company terms: '.$body.' ' : ''
        );
    }

    private function logIn(User $user): void
    {
        $crawler = $this->client->request('GET', '/login');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->client->request('POST', '/login', [
            '_username' => $user->getUserIdentifier(),
            '_password' => $this->world->password,
            '_csrf_token' => $token,
        ]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('/login', $this->client->getRequest()->getPathInfo());
    }

    private function assertHides(string $secret, string $where): void
    {
        $this->assertFalse(
            str_contains((string) $this->client->getResponse()->getContent(), $secret),
            $where.' includes '.$secret
        );
    }

    private function assertRejected(string $what, string $outcome): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [403, 404], true) && $outcome === '',
            $what.' must be rejected for a user who is not a member of the issuer. HTTP '.$status.'. '.$outcome
        );
    }

    private function reload(string $id): Invoice
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $invoice = $em->find(Invoice::class, $id);
        $this->assertInstanceOf(Invoice::class, $invoice);

        return $invoice;
    }

    private function transactionCount(string $invoiceId): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return (int) $em->createQuery(
            'SELECT COUNT(t.id) FROM '.Transaction::class.' t WHERE t.invoice = :id'
        )->setParameter('id', $invoiceId)->getSingleScalarResult();
    }

    private function invoiceCount(): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return (int) $em->createQuery('SELECT COUNT(i.id) FROM '.Invoice::class.' i')->getSingleScalarResult();
    }

    private function storedNumber(string $id): string
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return (string) $em->createQuery(
            'SELECT i.number FROM '.Invoice::class.' i WHERE i.id = :id'
        )->setParameter('id', $id)->getSingleScalarResult();
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ');
    }

    private function findByNumber(string $number): ?Invoice
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['number' => $number]);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    private function invoiceFormToken(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/invoice/new');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('input[name="invoice[_token]"]')->attr('value');
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(string $number, string $token, string $issuerId): array
    {
        return [
            '_token' => $token,
            'issuer' => $issuerId,
            'number' => $number,
            'recepient' => '',
            'dateOfIssue' => '2999-12-30',
            'dueDate' => '2999-12-20',
            'dateServiceRenderedFrom' => '2999-12-01',
            'dateServiceRenderedTo' => '2999-12-15',
            'discount' => '0',
            'invoiceItemCommands' => [
                ['code' => '1', 'name' => 'Consulting', 'quantity' => '1', 'unit' => 'x', 'price' => '100', 'discount' => '0'],
            ],
        ];
    }

    private function tail(): string
    {
        return sprintf('%04d', random_int(1000, 9999));
    }

    /**
     * @return list<string>
     */
    private function watchTmp(callable $action): array
    {
        $tmp = dirname(__DIR__, 2).'/tmp';
        if (!is_dir($tmp) && !mkdir($tmp, 0755, true) && !is_dir($tmp)) {
            $this->fail('Could not create '.$tmp);
        }
        $log = tempnam(sys_get_temp_dir(), 'invpdf');
        $this->assertNotFalse($log);
        $process = proc_open(
            ['inotifywait', '-m', '-q', '-e', 'create,delete,close_write,moved_to', '--format', '%e %f', $tmp],
            [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes
        );
        $this->assertIsResource($process);
        $running = false;
        for ($i = 0; $i < 20; ++$i) {
            $status = proc_get_status($process);
            if ($status['running']) {
                $running = true;
                break;
            }
            usleep(50000);
        }
        $this->assertTrue($running, 'inotifywait did not start');
        usleep(100000);
        try {
            $action();
            usleep(200000);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
        $text = (string) file_get_contents($log);
        @unlink($log);

        if ($text === '') {
            return [];
        }
        $lines = preg_split('/\R/', trim($text));

        return $lines === false ? [] : $lines;
    }

    /**
     * @param list<string> $events
     */
    private function eventsMention(array $events, string $needle): bool
    {
        foreach ($events as $event) {
            if (str_contains($event, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function describeMail(string $chosen): string
    {
        $notes = '';
        foreach (self::getMailerMessages() as $message) {
            if (!$message instanceof Email) {
                $notes .= 'non-email message sent. ';
                continue;
            }
            $to = array_map(static fn ($address) => $address->getAddress(), $message->getTo());
            $original = $message->getHeaders()->has('X-Original-To')
                ? $message->getHeaders()->get('X-Original-To')->getBodyAsString()
                : '';
            $notes .= 'mail to '.implode(',', $to);
            if ($original !== '') {
                $notes .= ' original '.$original;
            }
            if ($message->getAttachments() !== []) {
                $notes .= ' with attachment';
            }
            $notes .= '. ';
            if (in_array($chosen, $to, true) || str_contains($original, $chosen)) {
                $notes .= 'recipient was the address from the request. ';
            }
        }

        return $notes;
    }
}
