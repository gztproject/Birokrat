<?php

namespace App\Entity\TravelExpense;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\User\User;

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
     * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpenseState")
     * @ORM\JoinColumn(nullable=false)
     */
    private $state;

    public function __construct()
    {
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

    public function getState(): ?TravelExpenseState
    {
        return $this->state;
    }

    public function setState(?TravelExpenseState $state): self
    {
        $this->state = $state;

        return $this;
    }
    
    public function getTotalRefund(): ?float
    {
    	return $this->totalDistance * $this->rate;
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
    		if($ts->getStopOrder() > 0)
    			$desc .= " - ";
    		$desc .= $ts->getPost()->getName();
    	}
    	return $desc;
    }
    
    
}
