<?php

namespace App\Entity\Invoice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Entity\Organization\Client;
use App\Entity\Transaction\Transaction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Invoice\InvoiceRepository")
 */
class Invoice extends Base
{
    /**
     * @ORM\Column(type="datetime")
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
        $this->state = 00;         
        
        $this->dateOfIssue = $c->dateOfIssue;
        $this->dateServiceRenderedFrom = $c->dateServiceRenderedFrom;
        $this->dateServiceRenderedTo = $c->dateServiceRenderedTo;
        $this->discount = $c->discount/100;
        $this->dueDate = $c->dueDate;
        $this->issuer = $c->issuer;        
        $this->number = $c->number;
        $this->recepient = $c->recepient;
                
        //Do we really need this here or can we set it when we actually issue it?
        $this->calculateReference();
        $this->calculateTotals();
        $this->setState(10);
    }
    
    /**
     * Creates a new InvoiceItem on this invoice
     * @param CreateInvoiceItemCommand $c
     * @return InvoiceItem
     */
    public function createInvoiceItem(CreateInvoiceItemCommand $c): InvoiceItem
    {
    	$ii = new InvoiceItem($c, $this);
    	$this->invoiceItems[] = $ii;
    	$this->calculateTotals();
    	    	
    	return $ii;
    }
    
    /**
     * Sets the invoice issued and creates the transaction.
     * @param Konto $konto Konto for the transaction
     * @param \DateTime $date 
     * @param string $number Invoice number (in case of other new invoices being issued later)
     * @return Transaction
     */
    public function setIssued(Konto $konto, \DateTime $date, string $number): Transaction
    {
    	$this->number = $number;
    	$this->calculateReference();
    	$this->setDateOfIssue($date);
    	$this->calculateTotals();
    	
    	$transaction = new Transaction();
    	$transaction->initWithInvoice($this->getDateOfIssue(), $konto, $this->getTotalPrice(), $this);
    	
    	$this->setState(20);
    	return $transaction;
    }
    
    public function setPaid(\DateTime $date)
    {    	
    	$this->setDatePaid($date);
    	$this->setState(30);
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
    	$this->setState(40);
    }
    
    /**
     * Sets invoice state
     * 
     * @param integer $state 00-draft, 10-new, 20-issued, 30-paid, 40-cancelled, 50-rejected.
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
    		case 00: //new
    			if ($newState != 10 && $newState != 40)    				 
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 10: //draft
    			if ($newState != 20 && $newState != 40)
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 20: //issued
    			if ($newState != 30 && $newState != 50) 
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 30: //paid
    			throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 40: //cancelled
    			throw new \Exception("Can't do anything with cancelled invoice.");
    			break;
    		case 50: //rejected
    			throw new \Exception("Can't do anything with rejected invoice.");
    			break;
    		default:
    			throw new \Exception('This InvoiceState is unknown!');
    			break;
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
    	return $this->issuer;
    }
    
    public function getRecepient(): Client
    {
    	return $this->recepient;
    }
    
    public function getNumber(): string
    {
    	return $this->number;
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
    	return $this->referenceNumber;
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
    	$this->dueDate= $date->modify('+'.$days.' day');;
    	
    	return $this;
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
}
