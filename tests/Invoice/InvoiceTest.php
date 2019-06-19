<?php
namespace App\Tests\TravelExpense;

use PHPUnit\Framework\TestCase;
use App\Entity\Organization\Client;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;

class InvoiceTest extends TestCase
{
	public function testCreateInvoice()
    {    	
    	$user = $this->createMock(User::class);
    	$issuer = $this->createMock(Organization::class);
    	$recepient = $this->createMock(Client::class);
    				
    	
  		$inv = $this->createInvoice($user, $issuer, $recepient);  		
    	
    	$this->assertEquals(10, $inv->getDueInDays());
    	$this->assertEquals(0, $inv->getTotalValue());
    	$this->assertEquals(10, $inv->getState());
    }
    
    private function createInvoice(User $user = null, Organization $issuer = null, Client $recepient = null): Invoice
    {
    	if($user==null)
    		$user = $this->createMock(User::class);
    	if($issuer==null)
    		$issuer = $this->createMock(Organization::class);
    	if($recepient==null)
    		$recepient = $this->createMock(Client::class);
    	
    	$c = new CreateInvoiceCommand();
    	$c->dateOfIssue = new \DateTime("today");
    	$c->dateServiceRenderedFrom = new \DateTime("-7 days");
    	$c->dateServiceRenderedTo = new \DateTime("-1 days");
    	$c->dueDate = new \DateTime("+10 days");
    	$c->discount = 0;
    	$c->issuer = $issuer;
    	$c->recepient = $recepient;
    	
    	return new Invoice($c, $user);
    	  
    }
}