<?php

namespace App\Entity\Geography;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AddressRepository")
 */
class Address extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $line1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $line2;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Post", inversedBy="addresses")
     * @ORM\JoinColumn(nullable=false)
     */
    private $post;
    
    public function __construct(CreateAddressCommand $c, User $user, Post $post)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	if($post == null)
    		throw new \Exception("Can't create a child entity without parent.");
    	
    	parent::__construct($user);    	
    	$this->line1 = $c->line1;
    	$this->line2 = $c->line2;
    	$this->post = $post;
    }
    
    public function update(UpdateAddressCommand $c, User $user)
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
    
    public function removePost(Post $post, User $user): Address
    {
    	if($this->post != $post)
    		throw new \Exception("Can't remove post other than itself.");
    	parent::updateBase($user);
    	$this->post = null;
    	return $this;
    }
    
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo(object $to): object
    {
    	if ($to instanceof UpdateAddressCommand || $to instanceof CreateAddressCommand)
    	{
    		$reflect = new \ReflectionClass($to);
    		$props  = $reflect->getProperties();
    		foreach($props as $prop)
    		{    			
    			$name = $prop->getName();
    			if(property_exists($this, $name))
    			{
    				$to->$name = $this->$name;
    			}
    		}
    	}
    	else
    	{
    		throw(new \Exception('cant map ' . get_class($this) . ' to ' . get_class($to)));
    	}
    	return $to;
    }

    public function getLine1(): ?string
    {
        return $this->line1;
    }

    public function getLine2(): ?string
    {
        return $this->line2;
    }
    
    public function getStreetAddress(): ?string
    {
    	$street = $this->line1;
    	$street .= $this->line2 ? ", ".$this->line2:"";
    	return $street;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function getFullAddress(): string
    {
    	$address = $this->line1;
    	if ($this->line2)
    		$address .= ", " . $this->line2;
    	$address .= ", " . $this->post->getNameAndCode() . ", " . $this->post->getCountry()->getName();
    	return $address;
    }
    
    public function getFullFormattedAddress(): array
    {
    	return explode(", ", $this->getFullAddress());    	
    }
    
    public function __toString(): string
    {
    	return $this->getFullAddress();
    }
}
