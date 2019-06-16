<?php

namespace App\Entity\TravelExpense;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\User\User;
use App\Entity\Transaction\Transaction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpenseRepository")
 */
class TravelExpense extends Base
{
    /**
     * @ORM\Column(type="datetime")
     */
    private $date;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $employee;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $totalDistance;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=3)
     */
    private $rate;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TravelExpense\TravelStop", mappedBy="travelExpense", orphanRemoval=true)
     */
    private $travelStops;

    /**
     *  @ORM\Column(type="integer")
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpenseBundle", inversedBy="TravelExpenses")
     */
    private $travelExpenseBundle;

    public function __construct()
    {
    	$this->state = 00;
        $this->travelStops = new ArrayCollection();
    }    

    public function getDate(): ?\DateTimeInterface
    {
    	return $this->date;
    }
   	
    public function getDateString(): ?string
    {
    	return $this->date->format('d. m. Y');
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
    
    public function getEmployee(): ?User
    {
    	return $this->employee;
    }
    
    public function setEmployee(?User $user): self
    {    	
    	$this->employee = $user;
    	
    	return $this;
    }

    public function getTotalDistance()
    {
        return $this->totalDistance;
    }

//     public function setTotalDistance($totalDistance): self
//     {
//         $this->totalDistance = $totalDistance;

//         return $this;
//     }

    public function getRate()
    {
        return $this->rate;
    }

    public function setRate($rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    /**
     * @return Collection|TravelStop[]
     */
    public function getTravelStops(): Collection
    {
        return $this->travelStops;
    }

    public function addTravelStop(TravelStop $travelStop): self
    {
        if (!$this->travelStops->contains($travelStop)) {
            $this->travelStops[] = $travelStop;
            $travelStop->setTravelExpense($this);
        }

        return $this;
    }

    public function removeTravelStop(TravelStop $travelStop): self
    {
        if ($this->travelStops->contains($travelStop)) {
            $this->travelStops->removeElement($travelStop);
            // set the owning side to null (unless already changed)
            if ($travelStop->getTravelExpense() === $this) {
                $travelStop->setTravelExpense(null);
            }
        }

        return $this;
    }

    public function getState(): ?int
    {
        return $this->state;
    }
    
    public function setUnbooked(): self
    {
    	$this->setState(10);
    	return $this;
    }
    
    public function setBooked(): self
    {
    	$this->setState(20);
    	return $this;
    }

    /**
     * Sets TE state
     *
     * @param integer $state 00-new, 10-unbooked, 20-booked, 100-cancelled.
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
     * @param int $currState Current TE state
     * @param int $newState New TE state
     */
    private function checkState(int $currState, int $newState)
    {
    	switch ($currState) {
    		case 00: //new
    			if ($newState != 10 && $newState != 100)
    				throw new \Exception("Can't transition to state $newState from $currState");
    				break;
    		case 10: //unbooked
    			if ($newState != 20 && $newState != 100)
    				throw new \Exception("Can't transition to state $newState from $currState");
    				break;
    		case 20: //booked
    			if ($newState != 100) //Do we really want to be able to cancel booked TEs?
    				throw new \Exception("Can't transition to state $newState from $currState");
    				break;
    		
    		case 100: //cancelled
    			throw new \Exception("Can't do anything with cancelled TE.");
    			break;
    		default:
    			throw new \Exception('This TE State is unknown!');
    			break;
    	}
    	
    }
    
    public function getTotalCost(): ?float
    {
    	return round($this->totalDistance * $this->rate, 2);
    }
    
    public function calculateTotalDistance(): ?float
    {
    	$this->totalDistance = 0;
    	foreach($this->travelStops as $ts)
    	{
    		if($ts->getStopOrder() > 0)
    			$this->totalDistance += $ts->getDistanceFromPrevious();
    	}    	
    	return $this->totalDistance;
    }
    
    public function getTravelDescription(): ?string
    {
    	$desc = null;
    	foreach($this->travelStops as $ts)
    	{
    		if($ts->getStopOrder() > 1)
    			$desc .= " - ";
    		$desc .= $ts->getPost()->getName();
    	}
    	return $desc;
    }

    public function getTravelExpenseBundle(): ?TravelExpenseBundle
    {
        return $this->travelExpenseBundle;
    }

    public function setTravelExpenseBundle(?TravelExpenseBundle $travelExpenseBundle): self
    {
        $this->travelExpenseBundle = $travelExpenseBundle;

        return $this;
    }
    
}
