<?php

namespace App\Entity\TravelExpense;

use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Transaction\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\User\User;
use App\Entity\Transaction\CreateTransactionCommand;
use Symfony\Component\Validator\Constraints\Date;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpense\TravelExpenseBundleRepository")
 */
class TravelExpenseBundle extends AggregateBase implements iTransactionDocument
{    
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TravelExpense\TravelExpense", mappedBy="travelExpenseBundle")
     */
    private $travelExpenses;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $organization;
    

    public function __construct(CreateTravelExpenseBundleCommand $c, User $user)
    {
    	parent::__construct($user);
    	$this->travelExpenses = new ArrayCollection();
        foreach($c->travelExpenses as $te)
        {
        	$this->addTravelExpense($te, $user);
        }
        $this->organization = $c->organization;
    }
    
    /**
     * @return Collection|TravelExpense[]
     */
    public function getTravelExpenses(): Collection
    {
        return $this->travelExpenses;
    }

    private function addTravelExpense(TravelExpense $travelExpense, User $user): self
    {
        if (!$this->travelExpenses->contains($travelExpense)) {
            $this->travelExpenses[] = $travelExpense;
            $travelExpense->setTravelExpenseBundle($this, $user);
        }

        return $this;
    }

    /**
     * @deprecated
     * @param TravelExpense $travelExpense
     * @return self
     */
    private function removeTravelExpense(TravelExpense $travelExpense): self
    {
        if ($this->travelExpenses->contains($travelExpense)) {
            $this->travelExpenses->removeElement($travelExpense);
            // set the owning side to null (unless already changed)
            if ($travelExpense->getTravelExpenseBundle() === $this) {
                $travelExpense->setTravelExpenseBundle(null);
            }
        }

        return $this;
    }
    
    private function getTotalCost(): float
    {
    	$totalCost = 0.00;
    	foreach($this->travelExpenses as $te)
    	{
    		$totalCost += round($te->getTotalCost(), 2);
    	}
    	return round($totalCost, 2);
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
    	
    	foreach($this->travelExpenses as $te)
    	{
    		$te->setBooked($date, $user);
    	}
    	return $transaction;
    }
    
    public function getMinDate(): \DateTimeInterface
    {
    	$minDate = new \DateTime("now");
    	foreach($this->travelExpenses as $te)
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
    	foreach($this->travelExpenses as $te)
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
    	$string .= "TravelExpenses from " . $this->getMinDateString();
    	$string .= " to " . $this->getMaxDateString();
    	return $string;
    }
}
