<?php

namespace App\Entity\Invoice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Entity\Organization\Partner;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InvoiceRepository")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Partner")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $issuedBy;
    
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
     * Creates new invoice
     *
     * @param User $issuedBy User that created the invoice
     * @param Organization $issuer Issuing Organization
     * @param String $number Invoice number (generate using InvoiceNumberFactory)
     */
    public function __construct(User $issuedBy, Organization $issuer, String $number)
    {
        $this->invoiceItems = new ArrayCollection();
        
        //We have to initialize the state (see checkState()).
        $this->state = 00;      
        $this->setIssuedBy($issuedBy);        
        $this->setIssuer($issuer);
        
        $this->setNumber($number);
        
        $this->setDateOfIssue(\DateTime::createFromFormat('U', date("U")));
        $this->setDueInDays($this->issuer->getOrganizationSettings()->getDefaultPaymentDueIn());
    }
    
    public function setNew()    
    {  	
    	$this->calculateReference();
    	$this->calculateTotals();
    	$this->setState(10);    	  
    }
    
    public function setIssued()
    {
    	//$this->setDateIssued(\DateTime::createFromFormat('U', date("U")));
    	$this->setState(20);
    }
    
    public function setPaid()
    {
    	$this->setDatePaid(\DateTime::createFromFormat('U', date("U")));
    	$this->setState(30);
    }
    
    /**
     * Cancel the invoice
     * 
     * @param string $reason A reason for the cancellation
     */
    public function cancel(String $reason)
    {
    	$this->setDateCancelled(\DateTime::createFromFormat('U', date("U")));
    	$this->setCancelReason($reason);
    	$this->setState(40);
    }
    
    /**
     * Sets invoice state
     * 
     * @param integer $state 00-new, 10-draft, 20-issued, 30-paid, 40-cancelled. Draft state is kinda useless ATM but I'm keeping it for the time being.
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
    			if ($newState != 30 && $newState != 40) //Do we really want to be able to cancel issued invoices?
    				throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 30: //paid
    			throw new \Exception("Can't transition to state $newState from $currState");
    			break;
    		case 40: //cancelled
    			throw new \Exception("Can't do anything with cancelled invoice.");
    			break;
    		default:
    			throw new \Exception('This InvoiceState is unknown!');
    			break;
    	}
    	
    }
    
    private function calculateTotals(): self
    {
    	$price = 0;
    	foreach($this->getInvoiceItems() as $ii)
    	{
    		$price += ($ii->getPrice() * (1 - $ii->getDiscount()));
    	}
    	$this->totalValue = $price;
    	$this->totalPrice = $price * (1 - $this->discount);
    	
    	return $this;
    }
    
    // http://www.eclectica.ca/howto/modulus-11-self-check.php
    // http://www.zbs-giz.si/system/file.asp?FileId=3707
    private function calculateReference(): self
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
    			return $this;
    }

    public function getIssuedBy(): ?User
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(?User $issuedBy): self
    {
        $this->issuedBy = $issuedBy;

        return $this;
    }  
    
    public function getDateServiceRenderedFrom(): ?\DateTimeInterface
    {
    	return $this->dateServiceRenderedFrom;
    }
    
    public function getDateServiceRenderedFromString(): ?string
    {
    	return $this->dateServiceRenderedFrom->format('d. m. Y');
    }
    
    public function setDateServiceRenderedFrom(\DateTimeInterface $dateServiceRenderedFrom): self
    {
    	$this->dateServiceRenderedFrom = $dateServiceRenderedFrom;
    	
    	return $this;
    }
    
    public function getDateServiceRenderedTo(): ?\DateTimeInterface
    {
    	return $this->dateServiceRenderedTo;
    }
    
    public function getDateServiceRenderedToString(): ?string
    {
    	return $this->dateServiceRenderedTo->format('d. m. Y');
    }
    
    public function setDateServiceRenderedTo(\DateTimeInterface $dateServiceRenderedTo): self
    {
    	$this->dateServiceRenderedTo = $dateServiceRenderedTo;
    	
    	return $this;
    }
    
    public function getDateOfIssue(): ?\DateTimeInterface
    {
    	return $this->dateOfIssue;
    }
    
    public function getDateOfIssueString(): ?string
    {
    	return $this->dateOfIssue->format('d. m. Y');
    }
    
    public function setDateOfIssue(\DateTimeInterface $dateOfIssue): self
    {
    	$this->dateOfIssue = $dateOfIssue;
    	
    	return $this;
    }
    
    public function getIssuer(): ?Organization
    {
    	return $this->issuer;
    }
    
    public function setIssuer(?Organization $issuer): self
    {
    	$this->issuer = $issuer;
    	
    	return $this;
    }
    
    public function getRecepient(): ?Partner
    {
    	return $this->recepient;
    }
    
    public function setRecepient(?Partner $recepient): self
    {
    	$this->recepient = $recepient;
    	
    	return $this;
    }
    
    public function getNumber(): ?string
    {
    	return $this->number;
    }
    
    private function setNumber(string $number): self
    {
    	$this->number = $number;
    	
    	return $this;
    }
    
    public function getDiscount()
    {
    	return $this->discount;
    }
    
    public function setDiscount($discount): self
    {
    	$this->discount = $discount;
    	
    	return $this;
    }
    
    public function getTotalValue()
    {
    	return $this->totalValue;
    }
    
    private function setTotalValue($totalValue): self
    {
    	$this->totalValue = $totalValue;
    	
    	return $this;
    }
    
    public function getTotalPrice()
    {
    	return $this->totalPrice;
    }
    
    private function setTotalPrice($totalPrice): self
    {
    	$this->totalPrice = $totalPrice;
    	
    	return $this;
    }
    
    public function getReferenceNumber(): ?string
    {
    	return $this->referenceNumber;
    }
    
    private function setReferenceNumber(string $referenceNumber): self
    {
    	$this->referenceNumber = $referenceNumber;
    	
    	return $this;
    }
    
    /**
     * @return Collection|InvoiceItem[]
     */
    public function getInvoiceItems(): Collection
    {
    	return $this->invoiceItems;
    }
    
    public function addInvoiceItem(InvoiceItem $invoiceItem): self
    {
    	if (!$this->invoiceItems->contains($invoiceItem)) {
    		$this->invoiceItems[] = $invoiceItem;
    		$invoiceItem->setInvoice($this);
    	}
    	
    	return $this;
    }
    
    public function removeInvoiceItem(InvoiceItem $invoiceItem): self
    {
    	if ($this->invoiceItems->contains($invoiceItem)) {
    		$this->invoiceItems->removeElement($invoiceItem);
    		// set the owning side to null (unless already changed)
    		if ($invoiceItem->getInvoice() === $this) {
    			$invoiceItem->setInvoice(null);
    		}
    	}
    	
    	return $this;
    }
    
    public function getState(): ?int
    {
    	return $this->state;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }
    
    public function getDueDateString(): ?string
    {
    	return $this->dueDate->format('d. m. Y');
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }
    
    public function setDueInDays (int $days): self    
    {
    	//ToDo: Must be a better way to do this...
    	$date = \DateTime::createFromFormat("U", $this->getDateOfIssue()->format('U'));
    	$this->dueDate= $date->modify('+'.$days.' day');;
    	
    	return $this;
    }
    
    public function getDueInDays(): int
    {	
    	return date_diff($this->dueDate, $this->dateOfIssue, true)->format("%d");;
    }
    
    public function getDatePaid(): ?\DateTimeInterface
    {
    	return $this->datePaid;
    }
    
    public function getDatePaidString(): ?string
    {
    	return $this->datePaid->format('d. m. Y');
    }
    
    private function setDatePaid(\DateTimeInterface $datePaid): self
    {
    	$this->datePaid = $datePaid;
    	
    	return $this;
    }    
   
}
