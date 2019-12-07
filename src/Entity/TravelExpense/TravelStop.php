<?php

namespace App\Entity\TravelExpense;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Geography\Address;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelStopRepository")
 */
class TravelStop extends AggregateBase
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Post")
     */
    private $post;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Address")
     */
    private $address;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     */
    private $organization;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpense", inversedBy="travelStops")
     * @ORM\JoinColumn(nullable=false)
     */
    private $travelExpense;

    /**
     * @ORM\Column(type="integer")
     */
    private $stopOrder;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $distanceFromPrevious;
    
    
    public function __construct(CreateTravelStopCommand $c, TravelExpense $te) {
    	parent::__construct($te->getCreatedBy());
    	$this->travelExpense = $te;
    	
    	if($c->organization != null)
    	{
    		$this->organization = $c->organization;
    		$this->address = $this->organization->getAddress();
    		$this->post = $this->address->getPost();
    	}
    	elseif ($c->address != null)
    	{
    		$this->organization = null;
    		$this->address = $c->address;
    		$this->post = $this->address->getPost();
    	}
    	else 
    	{
    		$this->organization = null;
    		$this->address = null;
    		$this->post = $c->post;
    	}
    	
    	$this->distanceFromPrevious = $c->distanceFromPrevious;
    	$this->stopOrder = (int) $c->stopOrder;
    }  
    
    /**
     * Used to update stop order in case of removing/adding stops to TE.
     * @param int $order
     */
    public function setStopOrder(int $order)
    {
    	$this->stopOrder = $order;
    }
    
    /**
     * Used to remove association with TE
     */
    public function remove()
    {
    	$this->travelExpense = null;
    }
    
    public function update(iTravelStopCommand $c, TravelExpense $te): TravelStop
    {
    	parent::updateBase($te->getUpdatedBy());
    	
    	$this->travelExpense = $te;
    	if($c->organization != null && $c->organization !== $this->organization)
    	{
    		$this->organization = $c->organization;
    		$this->address = $this->organization->getAddress();
    		$this->post = $this->address->getPost();
    	}
    	elseif ($c->address != null && $c->address !== $this->address)
    	{
    		$this->organization = null;
    		$this->address = $c->address;
    		$this->post = $this->address->getPost();
    	}
    	elseif($c->post != null && $c->post !== $this->post)
    	{
    		$this->organization = null;
    		$this->address = null;
    		$this->post = $c->post;
    	}
    	
    	if($c->distanceFromPrevious != null && $c->distanceFromPrevious !== $this->distanceFromPrevious)
    		$this->distanceFromPrevious = $c->distanceFromPrevious;
    	
    	if($c->stopOrder != null && (int) $c->stopOrder !== $this->stopOrder)
    		$this->stopOrder = (int) $c->stopOrder;
    	
    	return $this;
    }
    
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo($to)
    {
    	if ($to instanceof UpdateTravelStopCommand || $to instanceof CreateTravelStopCommand)
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
     * *********************************************************
     * Getters
     * *********************************************************
     */
    public function getPost(): ?Post
    {
        return $this->post;
    }    

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getTravelExpense(): TravelExpense
    {
        return $this->travelExpense;
    }

    public function getStopOrder(): int
    {
        return $this->stopOrder;
    }

    public function getDistanceFromPrevious(): ?float
    {
        return $this->distanceFromPrevious;
    }
    
    public function __toString(): string
    {
    	$ret = "";
    	if($this->address != null)
    		$ret .= $this->address.", ";
    	$ret .= $this->post;    	
    	$ret .= ", stop order ".$this->stopOrder;	
    	return $ret;
    }    
}
