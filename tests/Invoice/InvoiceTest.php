<?php
namespace App\Tests\Invoice;

use PHPUnit\Framework\TestCase;
use App\Entity\Geography\Address;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\User\User;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Konto\Konto;
use App\Entity\Settings\OrganizationSettings;

class InvoiceTest extends TestCase
{
	public function testCreateInvoice()
	{
		$user = $this->createMock(User::class);
		$issuer = $this->stubIssuer();
		$recepient = $this->stubPartner();

		$inv = $this->createInvoice($user, $issuer, $recepient);

		$this->assertEquals(10, $inv->getDueInDays());
		$this->assertEquals(0, $inv->getTotalValue());
		$this->assertEquals(10, $inv->getState());
	}

	public function testIssueInvoice()
	{
		$user = $this->createMock(User::class);
		$recepient = $this->stubPartner();

		$cc = $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
		$cc->method('getNumber')->willReturn(760);

		$dc = $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
		$dc->method('getNumber')->willReturn(120);

		$os = $this->createMock(OrganizationSettings::class);
		$os->method('getIssueInvoiceCredit')->willReturn($cc);
		$os->method('getIssueInvoiceDebit')->willReturn($dc);

		$issuer = $this->stubIssuer($os);
		$inv = $this->createInvoice($user, $issuer, $recepient);

		$transaction = $inv->setIssued(new \DateTime(), 'TST-2019-0001', $user);

		$this->assertEquals($transaction->getSum(), $inv->getTotalValue());
		$this->assertEquals(760, $transaction->getCreditKonto()->getNumber());
		$this->assertEquals(120, $transaction->getDebitKonto()->getNumber());
		$this->assertEquals(20, $inv->getState());

		$this->expectException(\Exception::class);
		$inv->setIssued(new \DateTime(), 'TST-2019-0002', $user);
	}

	public function testPayInvoice()
	{
		$user = $this->createMock(User::class);
		$recepient = $this->stubPartner();

		$cc = $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
		$cc->method('getNumber')->willReturn(760);

		$dc = $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
		$dc->method('getNumber')->willReturn(120);

		$dcp = $this->getMockBuilder(Konto::class)->disableOriginalConstructor()->getMock();
		$dcp->method('getNumber')->willReturn(110);

		$os = $this->createMock(OrganizationSettings::class);
		$os->method('getIssueInvoiceCredit')->willReturn($cc);
		$os->method('getIssueInvoiceDebit')->willReturn($dc);
		$os->method('getInvoicePaidCredit')->willReturn($dc);
		$os->method('getInvoicePaidDebit')->willReturn($dcp);

		$issuer = $this->stubIssuer($os);
		$inv = $this->createInvoice($user, $issuer, $recepient);

		$inv->setIssued(new \DateTime(), 'TST-2019-0001', $user);
		$inv->setPaid(new \DateTime(), $user);

		$this->assertEquals(30, $inv->getState());

		$this->expectException(\Exception::class);
		$inv->setIssued(new \DateTime(), 'TST-2019-0002', $user);
	}

	private function createInvoice(User $user, Organization $issuer, Partner $recepient): Invoice
	{
		$c = new CreateInvoiceCommand();
		$c->dateOfIssue = new \DateTime('today');
		$c->dateServiceRenderedFrom = new \DateTime('-7 days');
		$c->dateServiceRenderedTo = new \DateTime('-1 days');
		$c->dueDate = new \DateTime('+10 days');
		$c->discount = 0;
		$c->number = 'TST-2019-0001';
		$c->issuer = $issuer;
		$c->recepient = $recepient;

		return new Invoice($c, $user);
	}

	private function stubIssuer(?OrganizationSettings $os = null): Organization
	{
		$issuer = $this->createMock(Organization::class);
		$issuer->method('getName')->willReturn('Issuer d.o.o.');
		$issuer->method('getAddress')->willReturn($this->stubAddress());
		$issuer->method('getFullTaxNumber')->willReturn('SI 12345678');
		$issuer->method('getAccountNumber')->willReturn('SI56031001001001234');
		$issuer->method('getBic')->willReturn('LJBASI2X');
		if ($os !== null) {
			$issuer->method('getOrganizationSettings')->willReturn($os);
		}

		return $issuer;
	}

	private function stubPartner(): Partner
	{
		$partner = $this->createMock(Partner::class);
		$partner->method('getName')->willReturn('Partner d.o.o.');
		$partner->method('getAddress')->willReturn($this->stubAddress());
		$partner->method('getFullTaxNumber')->willReturn('SI 87654321');

		return $partner;
	}

	private function stubAddress(): Address
	{
		$post = $this->createMock(Post::class);
		$post->method('getName')->willReturn('Ljubljana');

		$address = $this->createMock(Address::class);
		$address->method('getFullAddress')->willReturn('Trg 1, 1000 Ljubljana');
		$address->method('getPost')->willReturn($post);

		return $address;
	}
}
