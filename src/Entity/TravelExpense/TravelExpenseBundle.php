<?php

namespace App\Entity\TravelExpense;

use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Transaction\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Transaction\iTransactionDocument;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpense\TravelExpenseBundleRepository")
 */
class TravelExpenseBundle extends AggregateBase implements iTransactionDocument
{    
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TravelExpense\TravelExpense", mappedBy="travelExpenseBundle")
     */
    private $TravelExpenses;

    public function __construct()
    {
        $this->TravelExpenses = new ArrayCollection();
    }
    
    /**
     * @return Collection|TravelExpense[]
     */
    public function getTravelExpenses(): Collection
    {
        return $this->TravelExpenses;
    }

    public function addTravelExpense(TravelExpense $travelExpense): self
    {
        if (!$this->TravelExpenses->contains($travelExpense)) {
            $this->TravelExpenses[] = $travelExpense;
            $travelExpense->setTravelExpenseBundle($this);
        }

        return $this;
    }

    public function removeTravelExpense(TravelExpense $travelExpense): self
    {
        if ($this->TravelExpenses->contains($travelExpense)) {
            $this->TravelExpenses->removeElement($travelExpense);
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
    	foreach($this->TravelExpenses as $te)
    	{
    		$totalCost += round($te->getTotalCost(), 2);
    	}
    	return round($totalCost, 2);
    }
    
    public function setBooked(Konto $konto, \DateTime $date): Transaction
    {    	    	
    	$transaction = new Transaction();
    	$transaction->initWithTravelExpenseBundle($date, $konto, $this->getTotalCost(), $this);
    	
    	foreach($this->TravelExpenses as $te)
    	{
    		$te->setBooked();
    	}
    	return $transaction;
    }
}
