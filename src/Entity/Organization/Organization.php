<?php

namespace App\Entity\Organization;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\User\User;
use App\Entity\Geography\Address;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrganizationRepository")
 */
class Organization extends Base
{
    /**
     * @ORM\Column(type="string", length=50)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $shortName;

    /**
     * @ORM\Column(type="integer")
     */
    private $taxNumber;

    /**
     * @ORM\Column(type="boolean")
     */
    private $taxable;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User\User", inversedBy="organizations")
     */
    private $users;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Address")
     * @ORM\JoinColumn(nullable=false)
     */
    private $address;
    
    //Move to OrganizationSettings at some point...
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $invoicePrefix;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): self
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getTaxNumber(): ?int
    {
        return $this->taxNumber;
    }
    
    public function getFullTaxNumber(): string
    {
    	if($this->taxable)
    		return "SI".$this->taxNumber;
    	return $this->taxNumber;
    }

    public function setTaxNumber(int $taxNumber): self
    {
        $this->taxNumber = $taxNumber;

        return $this;
    }

    public function getTaxable(): ?bool
    {
        return $this->taxable;
    }

    public function setTaxable(bool $taxable): self
    {
        $this->taxable = $taxable;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
        }

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): self
    {        
        $this->address = $address;   
        return $this;
    }
        
    //Move to OrganizationSettings sometime
    public function getInvoicePrefix(): ?string
    {
    	return $this->invoicePrefix;
    }
    
    public function setInvoicePrefix(?string $invoicePrefix): self
    {
    	$this->invoicePrefix = $invoicePrefix;
    	
    	return $this;
    }
}
