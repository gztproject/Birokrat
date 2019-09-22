<?php

namespace App\Entity\IncomingInvoice;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Entity\Organization\Client;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\Transaction\CreateTransactionCommand;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Invoice\InvoiceRepository")
 */
class IncomingInvoice extends AggregateBase implements iTransactionDocument
{
    /**
     * @ORM\Column(type="date")
     */
    private $dateOfIssue;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $issuer;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Client")
     * @ORM\JoinColumn(nullable=false)
     */
    private $recepient;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     */
    private $price;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $referenceNumber;

    /**
     *  @ORM\Column(type="integer")
     */
    private $state;

    /**
     * @ORM\Column(type="date")
     */
    private $dueDate;
    
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $datePaid;
    
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateRejected;
    
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $rejectedReason;
    
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateRefunded;
    
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $refundReason;
    
    /**
     * @ORM\Column(type="string", length=512)
     */
    private $note;
    
    /**
     * @ORM\Column(type="string")
     */
    private $scanFilename;
    
    
    /**
     * Creates new invoice
     * @param CreateIncomingInvoiceCommand $c
     * @param User $issuedBy User that created the invoice
     */
    public function __construct(CreateIncomingInvoiceCommand $c, User $user)
    {
    	parent::__construct($user);    
        $this->rejectReason = null;
        $this->dateRejected = null;
        $this->refundReason = null;
        $this->dateRefunded = null;
        $this->datePaid = null;
        //We have to initialize the state (see checkState()).
        $this->state = 00;         
        
        $this->dateOfIssue = $c->dateOfIssue;
        $this->dueDate = $c->dueDate;
        $this->issuer = $c->issuer;        
        $this->number = $c->number;
        $this->recepient = $c->recepient;  
    }
    
    public function update(UpdateIncomingInvoiceCommand $c, User $user) : IncomingInvoice
    {
    	//We can only update invoices in state 10.
    	if($this->state != 10)
    		throw new \Exception("Only new invoices can be updated.");
    	parent::updateBase($user);
    	if($c->dateOfIssue != null && $c->dateOfIssue != $this->dateOfIssue)
    		$this->dateOfIssue = $c->dateOfIssue;
    	if($c->dueDate != null && $c->dueDate != $this->dueDate)
    		$this->dueDate = $c->dueDate;
    	if($c->issuer != null && $c->issuer != $this->issuer)
    		$this->issuer = $c->issuer;
    	if($c->number != null && $c->number != $this->number)
    		$this->number = $c->number;
    	if($c->recepient != null && $c->recepient != $this->recepient)
    		$this->recepient = $c->recepient;
    	
    	
    	return $this;
    }
    
   
    /**
     * Sets the invoice received and creates the transaction.
     * @param \DateTime $date
     * @param User $user Issuing user
     * @return Transaction
     */
    public function setReceived(\DateTime $date, User $user): Transaction
    {
    	$this->setState(10);
    	parent::updateBase($user);
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->dateOfIssue;
    	$cc = $this->issuer->getOrganizationSettings()->getReceivedIncomingInvoiceCredit();
    	$dc = $this->issuer->getOrganizationSettings()->getReceivedIncomingInvoiceDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->sum = $this->price;
    		
    	$transaction = new Transaction($c, $user, $this);
    		
    	return $transaction;
    }
   
    
    /**
     * Makes a copy of itself
     * @param User $user The cloning user
     * @return IncomingInvoice Cloned incomingInvoice
     */
    public function clone(User $user) : IncomingInvoice
    {
    	$c = new CreateIncomingInvoiceCommand();
    	$this->mapTo($c);
    	$invoice = $user->createIncomingInvoice($c);
    	
    	return $invoice;
    }
    
    public function setPaid(\DateTime $date, User $user): Transaction
    {    	
    	$this->setState(10);
    	parent::updateBase($user);
    	$this->datePaid = $date;
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->datePaid;
    	$cc = $this->issuer->getOrganizationSettings()->getIncomingInvoicePaidCredit();
    	$dc = $this->issuer->getOrganizationSettings()->getIncomingInvoicePaidDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before boking incoming invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->sum = $this->price;
    		
    	$transaction = new Transaction($c, $user, $this);
    		
    	return $transaction;
    }
    
    /**
     * Set IncomingInvoice refunded
     *
     * @param string $reason A reason for the refund
     */
    public function refund(String $reason)
    {
    	$this->dateRefunded = \DateTime::createFromFormat('U', date("U"));
    	$this->refundReason = $reason;
    	$this->setState(100);
    }
    
    /**
     * Reject the IncomingInvoice
     * 
     * @param string $reason A reason for the rejection
     */
    public function reject(String $reason)
    {
    	$this->dateRejected = \DateTime::createFromFormat('U', date("U"));
    	$this->rejectedReason = $reason;
    	$this->setState(110);
    }
    
    /**
     * Sets invoice state
     * 
     * @param integer $state 00-draft, 10-received, 20-paid, 100-refunded, 110-rejected.
     */
    private function setState(?int $state): self
    {
    	$this->checkState($this->state, $state);
        $this->state = $state;

        return $this;
    }
    
    /**
     * Checks if transition of states is allowed and everything is properly set. 
     * 
     * @param int $currState Current invoice state
     * @param int $newState New InvoiceState
     */
    private function checkState(int $currState, int $newState)
    {
    	switch ($currState) {
    		case 00: //draft
    			if ($newState != 10)    				 
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 10: //received
    			if ($newState != 20 && $newState != 110)
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 20: //paid
    			if ($newState != 100)
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 100: //refunded
    			throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 110: //rejected
    			throw new \Exception("Can't transition to state $newState from $currState");
    			break;    		
    		default:
    			throw new \Exception('This InvoiceState is unknown!');
    			break;
    	}
    	
    }
    
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo($to)
    {
    	if ($to instanceof UpdateIncomingInvoiceCommand || $to instanceof CreateIncomingInvoiceCommand)
    	{
    		$reflect = new \ReflectionClass($this);
    		$props  = $reflect->getProperties();
    		foreach($props as $prop)
    		{
    			$name = $prop->getName();
    			if(property_exists($to, $name))
    			{
    				$to->$name = $this->$name;
    			}
    		}
    	}
    	else
    	{
    		throw(new \Exception('cant map ' . get_class($this) . ' to ' . get_class($to)));
    		return $to;
    	}
    }
    
    public function getDateOfIssue(): \DateTimeInterface
    {
    	return $this->dateOfIssue;
    }
    
    public function getDateOfIssueString(): string
    {
    	return $this->dateOfIssue->format('j. n. Y');
    }
    
    public function getIssuer(): Client
    {
    	return $this->issuer;
    }
    
    public function getRecepient(): Organization
    {
    	return $this->recepient;
    }
    
    public function getNumber(): string
    {
    	return $this->number;
    }

    public function getprice()
    {
    	return $this->price;
    }
    
    public function getReferenceNumber(): string
    {
    	return $this->referenceNumber;
    }

    public function getState(): int
    {
    	return $this->state;
    }

    public function getDueDate(): \DateTimeInterface
    {
        return $this->dueDate;
    }
    
    public function getDueDateString(): string
    {
    	return $this->dueDate->format('j. n. Y');
    }
    
    public function getDueInDays(): int
    {	
    	return date_diff($this->dueDate, $this->dateOfIssue, true)->format("%d");
    }
    
    public function getDatePaid(): ?\DateTimeInterface
    {
    	return $this->datePaid;
    }
    
    public function getDatePaidString(): ?string
    {
    	return $this->datePaid->format('j. n. Y');
    }
}
