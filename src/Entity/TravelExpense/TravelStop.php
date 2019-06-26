<?php

namespace App\Entity\TravelExpense;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Geography\Address;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelStopRepository")
 */
class TravelStop extends Base
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
    	$this->stopOrder = $c->stopOrder;
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
    
    protected function update(UpdateTravelStopCommand $c, User $user)
    {
    	parent::updateBase($user);
    	
    	if($c->organization != null && $c->organization != $this->organization)
    	{
    		$this->organization = $c->organization;
    		$this->address = $this->organization->getAddress();
    		$this->post = $this->address->getPost();
    	}
    	elseif ($c->address != null && $c->address != $this->address)
    	{
    		$this->organization = null;
    		$this->address = $c->address;
    		$this->post = $this->address->getPost();
    	}
    	elseif($c->post != null && $c->post != $this->post)
    	{
    		$this->organization = null;
    		$this->address = null;
    		$this->post = $c->post;
    	}
    	
    	if($c->distanceFromPrevious != null && $c->distanceFromPrevious != $this->distanceFromPrevious)
    		$this->distanceFromPrevious = $c->distanceFromPrevious;
    	
    	if($c->stopOrder != null && $c->stopOrder != $this->stopOrder)
    		$this->stopOrder = $c->stopOrder;
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
}
