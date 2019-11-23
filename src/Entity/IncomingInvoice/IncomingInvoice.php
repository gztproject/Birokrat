<?php

namespace App\Entity\IncomingInvoice;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Entity\Organization\Partner;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\Transaction\CreateTransactionCommand;

/**
 * @ORM\Entity(repositoryClass="App\Repository\IncomingInvoice\IncomingInvoiceRepository")
 */
class IncomingInvoice extends AggregateBase implements iTransactionDocument
{
    /**
     * @ORM\Column(type="date")
     */
    private $dateOfIssue;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Partner")
     * @ORM\JoinColumn(nullable=false)
     */
    private $issuer;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
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
     * @ORM\Column(type="string", length=50, nullable=true)
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
     * @ORM\Column(type="string", length=512, nullable=true)
     */
    private $note;
    
    /**
     * @ORM\Column(type="string", nullable=true)
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
        $this->state = States::draft;         
        
        $this->dateOfIssue = $c->dateOfIssue;
        $this->dueDate = $c->dueDate;
        $this->issuer = $c->issuer;        
        $this->number = $c->number;
        $this->recepient = $c->recepient;  
        $this->price = $c->price;
    }
    
    public function update(UpdateIncomingInvoiceCommand $c, User $user) : IncomingInvoice
    {
    	//We can only update invoices in state 10.
    	if($this->state != States::received)
    		throw new \LogicException("Only new invoices can be updated.");
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
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @return Transaction
     */
    public function setReceived(\DateTime $date, User $user, Konto $cdc = null): Transaction
    {
    	$this->setState(States::received);
    	parent::updateBase($user);
    	    	    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->dateOfIssue;
    	$c->organization = $this->recepient;    	
    	$dc = $cdc != null ? $cdc : $this->recepient->getOrganizationSettings()->getReceivedIncomingInvoiceDebit();
    	$cc = $this->recepient->getOrganizationSettings()->getReceivedIncomingInvoiceCredit();
    	if($cc == null || $dc == null)
    		throw new \LogicException("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	if($this->price === null)
    		throw new \InvalidArgumentException("No price is set.");
    	$c->sum = $this->price;
    		
    	$transaction = new Transaction($c, $user, $this);
    		
    	return $transaction;
    }
    
    /**
     * Sets the invoice recieved and paid directly on the spot.
     * @param \DateTime $date
     * @param User $user
     * @param int $mode
     * @param Konto $cdc
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @return Transaction
     */
    public function setReceivedAndPaid(\DateTime $date, User $user, int $mode, Konto $cdc = null): Transaction
    {
    	$this->setState(States::received);
    	parent::updateBase($user);
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->dateOfIssue;
    	$c->organization = $this->recepient;
    	$dc = $cdc != null ? $cdc : $this->recepient->getOrganizationSettings()->getReceivedIncomingInvoiceDebit();
    	$cc = null;
    	switch ($mode){
    		case PaymentMethods::cash:
    			$cc = $this->recepient->getOrganizationSettings()->getPaidCashIncomingInvoiceCredit();
    			break;
    		case PaymentMethods::transaction:
    			$cc = $this->recepient->getOrganizationSettings()->getPaidTransactionIncomingInvoiceCredit();
    			break;
    		default:
    			throw new \InvalidArgumentException("Unknown mode of payment.");
    	}    	
    	if($cc == null || $dc == null)
    		throw new \LogicException("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	if($this->price === null)
    		throw new \InvalidArgumentException("No price is set.");
    	$c->sum = $this->price;
    	
    	$this->setState(States::paid);
    		
    	$transaction = new Transaction($c, $user, $this);
    	
    	return $transaction;
    }
    
    /**
     * Marks the invoice paid.
     * @param \DateTime $date
     * @param User $user
     * @param int $mode Mode of payment (00-cash, 10-transaction)
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @return Transaction
     */
    public function setPaid(\DateTime $date, User $user, int $mode): Transaction
    {    	
    	$this->setState(States::paid);
    	parent::updateBase($user);
    	$this->datePaid = $date;
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->datePaid; 
    	$c->organization = $this->recepient;
    	$dc = $this->recepient->getOrganizationSettings()->getPaidIncomingInvoiceDebit();
    	$cc = null;
    	switch ($mode){
    		case PaymentMethods::cash:
    			$cc = $this->recepient->getOrganizationSettings()->getPaidCashIncomingInvoiceCredit();
    			break;
    		case PaymentMethods::transaction:
    			$cc = $this->recepient->getOrganizationSettings()->getPaidTransactionIncomingInvoiceCredit();
	    		break;
    		default:
    			throw new \InvalidArgumentException("Unknown mode of payment.");
    	}    	
    	if($cc == null || $dc == null)
    		throw new \LogicException("Please set konto preferences for this organization before boking incoming invoices.");
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
    	$this->setState(States::refunded);
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
    	$this->setState(States::rejected);
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
     * @throws \LogicException
     */
    private function checkState(int $currState, int $newState)
    {
    	switch ($currState) {
    		case States::draft:
    			if ($newState != States::received)    				 
    				throw new \LogicException("Can't transition to state $newState from $currState");
    			break;
    		case States::received:
    			if ($newState != States::paid && $newState != States::rejected)
    				throw new \LogicException("Can't transition to state $newState from $currState");
    			break;
    		case States::paid:
    			if ($newState != States::refunded)
    				throw new \LogicException("Can't transition to state $newState from $currState");
    			break;
    		case States::refounded:
    			throw new \LogicException("Can't transition to state $newState from $currState");
    			break;
    		case States::rejected:
    			throw new \LogicException("Can't transition to state $newState from $currState");
    			break;    		
    		default:
    			throw new \LogicException('This InvoiceState is unknown!');
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
    		throw(new \InvalidArgumentException('cant map ' . get_class($this) . ' to ' . get_class($to)));
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
    
    public function getIssuer(): Partner
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

    public function getprice(): float
    {
    	return $this->price;
    }
    
    public function getReferenceNumber(): string
    {
    	return $this->referenceNumber!=null?$this->referenceNumber:"";
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

/**
 * 00-draft, 10-received, 20-paid, 100-refunded, 110-rejected.
 * @author gapi
 */
abstract class States
{
	const draft = 00;
	const received = 10;
	const paid = 20;
	const refunded = 100;
	const rejected = 110;
}

abstract class PaymentMethods
{
	const cash = 00;
	const transaction = 10;
}
