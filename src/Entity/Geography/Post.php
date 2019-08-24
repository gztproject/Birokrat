<?php

namespace App\Entity\Geography;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PostRepository")
 */
class Post extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=10)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=15)
     */
    private $codeInternational;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Country", inversedBy="posts")
     * @ORM\JoinColumn(nullable=false)
     */
    private $country;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Geography\Address", mappedBy="post")
     */
    private $addresses;

    public function __construct(CreatePostCommand $c, User $user, Country $country)
    {
    	if($user == null)
    		throw new \Exception("Can't create entity without a user.");
    	if($c == null)
    		throw new \Exception("Can't create entity without a command.");
    	if($country == null)
    		throw new \Exception("Can't create a child entity without parent.");
    			
    	parent::__construct($user);
        $this->addresses = new ArrayCollection();
        $this->country = $country;
        $this->code = $c->code;
        $this->codeInternational = $c->codeInternational;
        $this->name = $c->name;
    }

    public function update(UpdatePostCommand $c, User $user): Post
    {
    	throw new \Exception("Not implemented yet.");
    	parent::updateBase($user);
    	return $this;
    }
    
    public function removeCountry(Country $country, User $user): Post
    {
    	if($this->country != $country)
    		throw new \Exception("Can't remove contry other than itself.");
    	parent::updateBase($user);
    	$this->country = null;
    	return $this;
    }
    
    public function createAddress(CreateAddressCommand $c, User $user): Address
    {
    	$address = new Address($c, $user, $this);
    	
    	if (!$this->addresses->contains($address)) {
    		$this->addresses[] = $address;
    	}
    	
    	return $address;
    }
    
    
    
    public function removeAddress(Address $address, User $user): ?Address
    {
    	if ($this->addresses->contains($address)) {
    		$this->addresses->removeElement($address);
    		// set the owning side to null (unless already changed)
    		if ($address->getPost() === $this) {
    			return $address->removePost($this, $user);
    		}
    	}
    	return null;
    }
    
    public function updateAddress(Address $address, UpdateAddressCommand $c, User $user): ?Address
    {
    	if ($this->addresses->contains($address)) {
    		return $address->update($c, $user);
    	}
    	return null;
    }   	
    
    
    
    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getCodeInternational(): ?string
    {
        return $this->codeInternational;
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }    

    /**
     * @return Collection|Address[]
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }    
    
    public function getNameAndCode(): string
    {
    	return $this->code . " " . $this->name;
    }
}
