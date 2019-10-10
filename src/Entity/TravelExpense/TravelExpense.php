<?php

namespace App\Entity\TravelExpense;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Entity\Transaction\Transaction;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Entity\Transaction\iTransactionDocument;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpense\TravelExpenseRepository")
 */
class TravelExpense extends AggregateBase implements iTransactionDocument
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $organization;

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
    
    public function __construct(CreateTravelExpenseCommand $c, User $user)
    {
    	parent::__construct($user);
    	$this->state = 00;
        $this->travelStops = new ArrayCollection();
        
        $this->date = $c->date;
        $this->organization = $c->organization;
        $this->employee = $c->employee;
        $rate = $this->organization->getOrganizationSettings()->getTravelExpenseRate();
        if($rate == null)
        	throw new \Exception("Please set default travel expense rate for your organization.");
        $this->rate = $c->rate ?: $rate;
    }    
    
    
    public function setNew(User $user): Transaction
    {
    	$this->setState(10);
    	
    	parent::updateBase($user);
    	
    	$c = new CreateTransactionCommand();
    	$c->date = $this->date;
    	$cc = $this->organization->getOrganizationSettings()->getIncurredTravelExpenseCredit();
    	$dc = $this->organization->getOrganizationSettings()->getIncurredTravelExpenseDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->organization = $this->organization;
    	$c->sum = $this->totalDistance * $this->rate;
    	return new Transaction($c, $user, $this);
    }
    
    /**
     * Updates the TravelExpense.
     * @param UpdateTravelExpenseCommand $c Only fill the fields that need updating.
     * @param User $user Updating user.
     * @throws \Exception TravelExpense is not editable or the updating user is not set.
     * @return TravelExpense Updated TravelExpense.
     */
    public function update(UpdateTravelExpenseCommand $c, User $user): TravelExpense
    {
    	if($user == null)
    		throw new \Exception("Updating user must be set.");
    	
    	if($this->state > 10)
    		throw new \Exception("Can't update booked or cancelled TravelExpenses.");
    	
    	parent::updateBase($user);
    	
    	if($c->date != null && $c->date != $this->date)
    		$this->date = $c->date;
    	
    	if($c->employee != null && $c->employee != $this->employee)
    		$this->employee = $c->employee;
    	
    	if($c->rate != null && $c->rate != $this->rate)
    		$this->rate = $c->rate;
    	
    	$stopsToKeep = new ArrayCollection();
    	foreach($c->travelStopCommands as $utsc)
    	{
    		$ts = array_filter($this->travelStops->toArray(), function ($v) use ($utsc) {return $v->getId() == $utsc->id;})[0]??null;
    		echo("UTSC: ".$utsc->id.";       ");
    		if($ts == null)
    		{
    			echo("New TS: ".$utsc->id.";       ");
    			$stopsToKeep->add($this->createTravelStop($utsc));
    		}
    		else
    		{
    			echo("Update TS: ".$ts.";       ");
    			$stopsToKeep->add($ts->update($utsc, $this));
    		}
    	}
    	
    	foreach($this->travelStops as $ts)
    	{
    		if(!$stopsToKeep->contains($ts))
    		{
    			$this->removeTravelStop($ts);
    		}
    	}
    	
    	return $this;
    }
    
    public function setTravelExpenseBundle(TravelExpenseBundle $teb, User $user): TravelExpense
    {
    	if($user == null)
    		throw new \Exception("Updating user must be set.");
    		
    	if($this->state != 10)
    		throw new \Exception("I can only bundle new expenses.");
    	
    	if ($this->travelExpenseBundle != null)
    		throw new \Exception("This TravelExpense is already bundled.");
    	
    	parent::updateBase($user);
    	$this->travelExpenseBundle = $teb;
    	
    	return $this;
    }
    
    public function setBooked(\DateTime $date, User $user): TravelExpense
    {
    	if($user == null)
    		throw new \Exception("Updating user must be set.");
    		
    	if($this->state != 10)
    		throw new \Exception("I can only book new expenses.");
    			
    	parent::updateBase($user);
    	$this->setState(20);
    	
    	return $this;
    	
    }
    
    
    /**
     * Createss a new TravelStop.
     * @param CreateTravelStopCommand $c
     * @throws \Exception If the TravelExpense is already booked or cancelled.
     * @return TravelStop
     */
    public function createTravelStop(CreateTravelStopCommand $c): TravelStop
    {
    	echo("Creating new TravelStop with post ".$c->post.". ");
    	if($this->state > 10)
    		throw new \Exception("Can't update booked or cancelled TravelExpenses.");
    	$ts = new TravelStop($c, $this);
    	echo("Created ".$ts);
    	$this->travelStops->add($ts);
    	$this->calculateTotalDistance();
    	return $ts;
    }
    
    /**
     * Updates the TravelStop.
     * @param UpdateTravelStopCommand $c Only fill the fields that need updating.
     * @param TravelStop $ts TravelStop to update.
     * @param User $user Updating user.
     * @throws \Exception If the TravelStop is not in this TravelExpense or the updating user is not set.
     * @return TravelStop Updated TravelStop.
     * @deprecated
     */
    private function updateTravelStop(UpdateTravelStopCommand $c, TravelStop $ts, User $user): TravelStop
    {
    	if($this->state > 10)
    		throw new \Exception("Can't update booked or cancelled TravelExpenses.");
    	if($user == null)
    		throw new \Exception("Updating user must be set.");
    	if(!$this->travelStops->contains($ts))
    		throw new \Exception("Can't update a travelStop that's not in this TravelExpense.");
    	$ts->update($c, $user);
    	return $ts;
    }
        
    private function removeTravelStop(TravelStop $ts): TravelExpense
    {
    	if($this->state > 10)
    		throw new \Exception("Can't update booked or cancelled TravelExpenses.");
    	if(!$this->travelStops->contains($ts))
    		throw new \Exception("Can't remove a travelStop that's not in this TravelExpense.");
    	if($this->travelStops->count() < 3) 
    		throw new \Exception("Can't remove last two TravelStops.");
    	
    	echo("Removing TravelStop ".$ts.". ");
    		    	
    	$this->travelStops->removeElement($ts);    		
    	if ($ts->getTravelExpense() === $this) {
    		$ts->remove();
    	}
    	
    	$index = $ts->getStopOrder();
    	foreach($this->getTravelStops() as $stop)
    	{
    		if($stop->getStopOrder()>=$index)
    			$stop->setStopOrder($stop->getStopOrder());
    	}
    	$this->calculateTotalDistance();
    	return $this;
    }
    
    /**
     * Makes a copy of itself
     * @param User $user The cloning user
     * @return TravelExpense Cloned invoice (sub-clones all InvoiceItems too.)
     */
    public function clone(User $user) : TravelExpense
    {
    	$c = new CreateTravelExpenseCommand();
    	$this->mapTo($c);
    	$te = $user->createTravelExpense($c);
    	foreach($this->travelStops as $ts)
    	{
    		$cts = new CreateTravelStopCommand();
    		$ts->mapTo($cts);
    		$te->createTravelStop($cts);
    	}
    	return $te;
    }
    
    /**
     * Sets TE state
     *
     * @param integer $state 00-new, 10-unbooked, 20-booked, 100-cancelled.
     */
    private function setState(?int $state): int
    {
    	$this->checkState($this->state, $state);
    	$this->state = $state;
    	
    	return $state;
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
    
    private function calculateTotalDistance(): ?float
    {
    	$this->totalDistance = 0;
    	foreach($this->travelStops as $ts)
    	{
    		if($ts->getStopOrder() > 0)
    			$this->totalDistance += $ts->getDistanceFromPrevious();
    	}
    	return $this->totalDistance;
    }
    
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo($to)
    {
    	if ($to instanceof UpdateTravelExpenseCommand || $to instanceof CreateTravelExpenseCommand)
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
    

    /*
     * ************************************************************
     * Getters
     * ************************************************************
     */
    public function getDate(): ?\DateTimeInterface
    {
    	return $this->date;
    }
   	
    public function getDateString(): ?string
    {
    	return $this->date->format('d. m. Y');
    }    
    
    public function getEmployee(): User
    {
    	return $this->employee;
    }
    
    public function getOrganization(): Organization
    {
    	return $this->organization;
    }
    
    public function getTotalDistance()
    {
        return $this->totalDistance;
    }

    public function getRate()
    {
        return $this->rate;
    }

    /**
     * @return Collection|TravelStop[]
     */
    public function getTravelStops(): Collection
    {
        return $this->travelStops;
    }

    public function getState(): ?int
    {
        return $this->state;
    }    
    
    public function getTotalCost(): ?float
    {
    	return round($this->totalDistance * $this->rate, 2);
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
    
    public function __toString(): string
    {
    	$ret = $this->getDateString().", ";    	
    	$ret .= $this->employee.", stops:";
    	foreach($this->travelStops as $ts)
    	{
    		$ret .= $ts.", ";
    	}
    	return $ret;
    }
    
}
