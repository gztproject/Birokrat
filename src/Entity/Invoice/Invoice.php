<?php

namespace App\Entity\Invoice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Invoice\Enumerators\States;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Entity\Organization\Partner;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\Transaction\CreateTransactionCommand;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Invoice\InvoiceRepository")
 */
class Invoice extends AggregateBase implements iTransactionDocument
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Partner", inversedBy="invoices")
     * @ORM\JoinColumn(nullable=false)
     */
    private $recepient;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2, nullable=true)
     */
    private $discount;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     */
    private $totalValue;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     */
    private $totalPrice;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $referenceNumber;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Invoice\InvoiceItem", mappedBy="invoice", orphanRemoval=true)
     * @ORM\OrderBy({"code" = "ASC"})
     */
    private $invoiceItems;

    /**
     *  @ORM\Column(type="integer")
     */
    private $state;
        
    /**
     * @ORM\Column(type="date")
     */
    private $dateServiceRenderedFrom;
    
	/**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateServiceRenderedTo;

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
    private $dateCancelled;
    
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $cancelReason;
    
    /*
     * We're saving some denormalized issuer and client data in case of changes (eg. address, name, etc.)
     */
    
    /**
    * @ORM\Column(type="string", length=255)
    */
    private $issuerName;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $issuerAddress;
    /**
     * @ORM\Column(type="string", length=100)
     */
    private $issuerPostName;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $issuerTaxNumber;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $issuerAccountNumber;    
    /**
     * @ORM\Column(type="string", length=10)
     */
    private $issuerBic;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $recepientName;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $recepientAddress;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $recepientTaxNumber;   
    
    
    /**
     * Creates new invoice
     * @param CreateInvoiceCommand $c
     * @param User $issuedBy User that created the invoice
     */
    public function __construct(CreateInvoiceCommand $c, User $user)
    {
    	parent::__construct($user);
        $this->invoiceItems = new ArrayCollection();        
        $this->cancelReason = null;
        $this->dateCancelled = null;
        $this->datePaid = null;
        //We have to initialize the state (see checkState()).
        $this->state = States::draft;         
        
        $this->dateOfIssue = $c->dateOfIssue;
        $this->dateServiceRenderedFrom = $c->dateServiceRenderedFrom;
        $this->dateServiceRenderedTo = $c->dateServiceRenderedTo;
        $this->discount = $c->discount/100;
        $this->dueDate = $c->dueDate;
        $this->issuer = $c->issuer;        
        $this->number = $c->number;
        $this->recepient = $c->recepient;
        $this->setRedundantData();
                
        //Do we really need this here or can we set it when we actually issue it?
        $this->calculateReference();
        $this->calculateTotals();
        $this->setState(States::new);
    }
    
    public function update(UpdateInvoiceCommand $c, User $user) : Invoice
    {
    	//We can only update invoices in state new(10).
    	if($this->state != States::new)
    		throw new \Exception("Only new invoices can be updated.");
    	parent::updateBase($user);
    	if($c->dateOfIssue !== null && $c->dateOfIssue !== $this->dateOfIssue)
    		$this->dateOfIssue = $c->dateOfIssue;
    	if($c->dateServiceRenderedFrom !== null && $c->dateServiceRenderedFrom !== $this->dateServiceRenderedFrom)
    		$this->dateServiceRenderedFrom = $c->dateServiceRenderedFrom;
    	if($c->dateServiceRenderedTo !== null && $c->dateServiceRenderedTo !== $this->dateServiceRenderedTo)
    		$this->dateServiceRenderedTo = $c->dateServiceRenderedTo;
    	if($c->discount !== null && $c->discount/100 !== $this->discount)
    		$this->discount = $c->discount/100;
    	if($c->dueDate !== null && $c->dueDate !== $this->dueDate)
    		$this->dueDate = $c->dueDate;
    	if($c->issuer !== null && $c->issuer !== $this->issuer)
    		$this->issuer = $c->issuer;
    	if($c->number !== null && $c->number !== $this->number)
    		$this->number = $c->number;
    	if($c->recepient !== null && $c->recepient !== $this->recepient)
    		$this->recepient = $c->recepient;
    	
    	    
    	$itemsToKeep = new ArrayCollection();
    	foreach($c->invoiceItemCommands as $uiic)
    	{    		
    		$ii = array_filter($this->invoiceItems->toArray(), function ($v) use ($uiic) {return $v->getId() == $uiic->id;})[0]??null;
    		if($ii == null)
    		{
    			$itemsToKeep->add($this->createInvoiceItem($uiic));
    		}
    		else
    		{
    			$itemsToKeep->add($ii->update($uiic, $this));
    		}
    	}
    	
    	foreach($this->invoiceItems as $ii)
    	{
    		if(!$itemsToKeep->contains($ii))
    		{    			
    			$this->removeInvoiceItem($ii);
    		}
    	}
    	$this->calculateReference();
    	$this->calculateTotals();
    	
    	return $this;
    }
    
    /**
     * Creates a new InvoiceItem on this invoice
     * @param CreateInvoiceItemCommand $c
     * @return InvoiceItem
     */
    public function createInvoiceItem(CreateInvoiceItemCommand $c): InvoiceItem
    {
    	$ii = new InvoiceItem($c, $this);
    	$this->invoiceItems->add($ii);
    	$this->calculateTotals();
    	    	
    	return $ii;
    }
    
    private function removeInvoiceItem(InvoiceItem $ii)
    {
    	if($this->invoiceItems->contains($ii))
    		$this->invoiceItems->removeElement($ii);
    	else 
    		throw new \Exception("Can't remove item that's not there!");
    }
    
    /**
     * Makes a copy of itself
     * @param User $user The cloning user
     * @return Invoice Cloned invoice (sub-clones all InvoiceItems too.)
     */
    public function clone(User $user) : Invoice
    {
    	$c = new CreateInvoiceCommand();
    	$this->mapTo($c);
    	$invoice = $user->createInvoice($c);
    	foreach($this->invoiceItems as $ii)
    	{
    		$cii = new CreateInvoiceItemCommand();
    		$ii->mapTo($cii);
    		$invoice->createInvoiceItem($cii);
    	}
    	return $invoice;
    }
    
    /**
     * Sets the invoice issued and creates the transaction.
     * @param \DateTime $date 
     * @param string $number Invoice number (in case of other new invoices being issued later)
     * @param User $user Issuing user
     * @return Transaction
     */
    public function setIssued(\DateTime $date, string $number, User $user): Transaction
    {
    	$this->setState(States::issued);
    	parent::updateBase($user);
    	
    	$this->number = $number;
    	$this->calculateReference();
    	$this->dateOfIssue = $date;
    	$this->calculateTotals();
    	
    	$c = new CreateTransactionCommand();
    	$c->organization = $this->issuer;
    	$c->date = $this->dateOfIssue;
    	$cc = $this->issuer->getOrganizationSettings()->getIssueInvoiceCredit();
    	$dc = $this->issuer->getOrganizationSettings()->getIssueInvoiceDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->sum = $this->totalPrice;
    	
    	$transaction = new Transaction($c, $user, $this);
    	    	    	
    	return $transaction;
    }
    
    public function setPaid(\DateTime $date, User $user): Transaction
    {    	
    	$this->setState(States::paid);
    	parent::updateBase($user);
    	$this->datePaid = $date;
    	
    	$c = new CreateTransactionCommand();
    	$c->organization = $this->issuer;
    	$c->date = $this->datePaid;
    	$cc = $this->issuer->getOrganizationSettings()->getInvoicePaidCredit();
    	$dc = $this->issuer->getOrganizationSettings()->getInvoicePaidDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->sum = $this->totalPrice;
    		
    	$transaction = new Transaction($c, $user, $this);
    		
    	return $transaction;
    }
    
    /**
     * Cancel the invoice
     * 
     * @param string $reason A reason for the cancellation
     */
    public function cancel(String $reason)
    {
    	$this->dateCancelled = \DateTime::createFromFormat('U', date("U"));
    	$this->cancelReason = $reason;
    	$this->setState(States::cancelled);
    }
    
    /**
     * Sets invoice state
     * @param int $state
     * @return self
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
    		case States::draft:
    			if ($newState != States::new && $newState != States::cancelled)    				 
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case States::new:
    			if ($newState != States::issued && $newState != States::cancelled)
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case States::issued:
    			if ($newState != States::paid && $newState != States::rejected) 
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case States::paid:
    			throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case States::cancelled:
    			throw new \Exception("Can't do anything with cancelled invoice.");
    			break;
    		case States::rejected:
    			throw new \Exception("Can't do anything with rejected invoice.");
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
    	if ($to instanceof UpdateInvoiceCommand || $to instanceof CreateInvoiceCommand)
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
    
    /**
     * Calculates and sets totalValue and totalPrice.
     */
    private function calculateTotals(): void
    {
    	$price = 0;
    	foreach($this->getInvoiceItems() as $ii)
    	{
    		$price += ($ii->getPrice() * $ii->getQuantity() * (1 - $ii->getDiscount()));
    	}
    	$this->totalValue = $price;
    	$this->totalPrice = $price * (1 - $this->discount);
    }
    
    // http://www.eclectica.ca/howto/modulus-11-self-check.php
    // http://www.zbs-giz.si/system/file.asp?FileId=3707
    /**
     * Calculates and sets ReferenceNumber. Make sure the invoice number is set beforehand.
     * @throws Exception
     */
    private function calculateReference()
    {
    	if (strlen($this->number) > 20)
    		throw new Exception("Input string must be shorter than 20 characters.");
    		
    	//Remove prefix (if there is one)
    	$number = explode('-', $this->number);
    	if(count($number) > 2)
    		array_shift($number);
    			
    		$result = "SI01 ";
    		$base = array(implode('-', $number));
    			
    		foreach ($base as $base_val) {
    				
    			if (strlen($base_val) > 12)
    				throw new Exception("Input string must be shorter than 12 characters.");
    					
    			$weight = array(2,3,4,5,6,7,8,9,10,11,12,13);
    					
    			/* For convenience, reverse the string and work left to right. */
    			$reversed_base_val = strrev(str_replace("-","", $base_val));
    					
    			for ($i = 0, $sum = 0; $i < strlen($reversed_base_val); $i ++) {
    				/* Calculate product and accumulate. */
    				$sum += substr($reversed_base_val, $i, 1) * $weight[$i];
    			}
    					
    			$remainder = $sum % 11;
    					
    			$check = 11 - $remainder;
    			if ($remainder == 1 || $remainder == 0)
    				$check = 0;
    						
    			if ($base_val != $base[0])
    				$result .= "-";
    							
    			$result .= $base_val . $check;
    		}
    	$this->referenceNumber = $result;    			
    }   
    
    private function setRedundantData()
    {
    	$this->issuerName = $this->issuer->getName();
    	$this->issuerAddress = $this->issuer->getAddress()->getFullAddress();
    	$this->issuerPostName = $this->issuer->getAddress()->getPost()->getName();
    	$this->issuerTaxNumber = $this->issuer->getFullTaxNumber();
    	$this->issuerAccountNumber = $this->issuer->getAccountNumber();
    	$this->issuerBic = $this->issuer->getBic();
    	$this->recepientName = $this->recepient->getName();
    	$this->recepientAddress = $this->recepient->getAddress()->getFullAddress();
    	$this->recepientTaxNumber = $this->recepient->getFullTaxNumber();
    }
    
    public function getDateServiceRenderedFrom(): \DateTimeInterface
    {
    	return $this->dateServiceRenderedFrom;
    }
    
    public function getDateServiceRenderedFromString(): string
    {
    	return $this->dateServiceRenderedFrom->format('j. n. Y');
    }
    
    public function getDateServiceRenderedTo(): \DateTimeInterface
    {
    	return $this->dateServiceRenderedTo;
    }
    
    public function getDateServiceRenderedToString(): string
    {
    	return $this->dateServiceRenderedTo->format('j. n. Y');
    }
    
    public function getDateServiceRenderedString(): string
    {
    	 $string = $this->dateServiceRenderedFrom->format('j. n. Y');
    	 if ($this->dateServiceRenderedTo > $this->dateServiceRenderedFrom)
    	 	$string .= " - ".$this->dateServiceRenderedTo->format('j. n. Y');
    	 return $string;
    }
    
    public function getDateOfIssue(): \DateTimeInterface
    {
    	return $this->dateOfIssue;
    }
    
    public function getDateOfIssueString(): string
    {
    	return $this->dateOfIssue->format('j. n. Y');
    }
    
    public function getIssuer(): Organization
    {
    	$issuer = $this->issuer;
    	return $issuer;
    }
    
    public function getIssuerName(): String
    {
    	return $this->issuerName;
    }
    public function getIssuerAddress(): String
    {
    	return $this->issuerAddress;
    }
    public function getIssuerPostName(): String
    {
    	return $this->issuerPostName;
    }
    public function getIssuerFormattedAddress(): array
    {    	
    	return explode(", ", $this->issuerAddress);
    }
    public function getIssuerTaxNumber(): String
    {
    	return $this->issuerTaxNumber;
    }
    public function getIssuerAccountNumber(): String
    {
    	return $this->issuerAccountNumber;
    }
    public function getIssuerBic(): String
    {
    	return $this->issuerBic;
    }
    
    public function getRecepient(): Partner
    {
    	$recepient = $this->recepient;
    	return $recepient;
    }
    
    public function getRecepientName(): String
    {
    	return $this->recepientName;
    }
    public function getRecepientAddress(): String
    {
    	return $this->recepientAddress;
    }
    public function getRecepientFormattedAddress(): array
    {
    	return explode(", ", $this->recepientAddress);
    }
    public function getRecepientTaxNumber(): String
    {
    	return $this->recepientTaxNumber;
    }    
    
    public function getNumber(): string
    {
    	if($this->state === States::issued || $this->state === States::paid)
    	{
    		return $this->number;
    	}
    	else
    	{
    		return "";
    	}
    }
    
    public function getDiscount():float
    {
    	return $this->discount;
    }
    
    public function getTotalValue()
    {
    	return $this->totalValue;
    }

    public function getTotalPrice()
    {
    	return $this->totalPrice;
    }
    
    public function getReferenceNumber(): string
    {
    	if($this->state === States::issued || $this->state === States::paid)
    	{
    		return $this->referenceNumber;
    	}
    	else 
    	{
    		return "-";
    	}
    }

    /**
     * @return Collection|InvoiceItem[]
     */
    public function getInvoiceItems(): Collection
    {
    	return $this->invoiceItems;
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
    
    /**
     * @deprecated Shouldn't set this from outside.
     * @param int $days
     * @return self
     */
    public function setDueInDays (int $days): self    
    {
    	//ToDo: Must be a better way to do this...
    	$date = \DateTime::createFromFormat("U", $this->getDateOfIssue()->format('U'));
    	$this->dueDate = $date->modify('+'.$days.' day');;
    	
    	return $this;
    }
    
    public function getDueInDays(): int
    {	
    	return date_diff($this->dueDate, $this->dateOfIssue, true)->days;
    }
    
    public function getDatePaid(): ?\DateTimeInterface
    {
    	return $this->datePaid;
    }
    
    public function getDatePaidString(): ?string
    {
    	return $this->datePaid->format('j. n. Y');
    }

    public function getDateCancelled(): ?\DateTimeInterface
    {
    	return $this->dateCancelled;
    }
    
    public function getDateCancelledString(): ?string
    {
    	return $this->dateCancelled->format('j. n. Y');
    }
    
    public function getCancelReason(): ?string
    {
    	return $this->cancelReason;
    }
    
     /**
      * @deprecated Use createdBy instead.
      * Returns the creating user
      * @return User
      */
    public function getIssuedBy(): User
    {
    	return $this->createdBy;
    }
    
    public function __toString()
    {
    	return "Invoice: ".$this->number;
    }
}
