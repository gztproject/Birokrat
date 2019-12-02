<?php

namespace App\Entity\LunchExpense;

use App\Entity\Base\AggregateBase;
use App\Entity\Transaction\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\User\User;
use App\Entity\Transaction\CreateTransactionCommand;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LunchExpense\LunchExpenseBundleRepository")
 */
class LunchExpenseBundle extends AggregateBase implements iTransactionDocument
{    
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LunchExpense\LunchExpense", mappedBy="lunchExpenseBundle")
     */
    private $lunchExpenses;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $organization;
    

    public function __construct(CreateLunchExpenseBundleCommand $c, User $user)
    {
    	parent::__construct($user);
    	$this->lunchExpenses = new ArrayCollection();
        foreach($c->lunchExpenses as $te)
        {
        	$this->addLunchExpense($te, $user);
        }
        $this->organization = $c->organization;
    }
    
    /**
     * @return Collection|LunchExpense[]
     */
    public function getLunchExpenses(): Collection
    {
        return $this->lunchExpenses;
    }

    private function addLunchExpense(LunchExpense $lunchExpense, User $user): self
    {
        if (!$this->lunchExpenses->contains($lunchExpense)) {
            $this->lunchExpenses[] = $lunchExpense;
            $lunchExpense->setLunchExpenseBundle($this, $user);
        }

        return $this;
    }
    
    private function getTotalCost(): float
    {
    	$totalCost = 0.00;
    	foreach($this->lunchExpenses as $le)
    	{
    		$totalCost += $le->getSum();
    	}
    	return $totalCost;
    }
    
    public function setBooked(\DateTime $date, User $user): Transaction
    {    	    	
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $date;
    	$cc = $this->organization->getOrganizationSettings()->getPaidTravelExpenseCredit();
    	$dc = $this->organization->getOrganizationSettings()->getPaidTravelExpenseDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->organization = $this->organization;
    	
    	$c->sum = $this->getTotalCost();
    	
    	$transaction = new Transaction($c, $user, $this);
    	
    	foreach($this->lunchExpenses as $te)
    	{
    		$te->setBooked($date, $user);
    	}
    	return $transaction;
    }
    
    public function getMinDate(): \DateTimeInterface
    {
    	$minDate = new \DateTime("now");
    	foreach($this->lunchExpenses as $te)
    	{
    		if($te->getDate()<$minDate)
    			$minDate = $te->getDate();
    	}
    	return $minDate;
    }
    
    public function getMinDateString():string
    {
    	return $this->getMinDate()->format('d. m. Y');
    }
    
    public function getMaxDate(): \DateTimeInterface
    {
    	$maxDate = new \DateTime("1.1.1970");
    	foreach($this->lunchExpenses as $te)
    	{
    		if($te->getDate()>$maxDate)
    			$maxDate = $te->getDate();
    	}
    	return $maxDate;
    }
    
    public function getMaxDateString():string
    {
    	return $this->getMaxDate()->format('d. m. Y');
    }
    
    public function getDateRange():string
    {    	
    	return $this->getMinDateString()." - ".$this->getMaxDateString();
    }
    
    
    public function __toString()
    {	
    	$string = "";    	
    	$string .= "LunchExpenses from " . $this->getMinDateString();
    	$string .= " to " . $this->getMaxDateString();
    	return $string;
    }
}
