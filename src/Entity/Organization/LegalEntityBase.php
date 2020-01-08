<?php

namespace App\Entity\Organization;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;
use App\Entity\Geography\Address;

class LegalEntityBase extends AggregateBase
{
    /**
     * @ORM\Column(type="string", length=50)
     */
    protected $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $shortName;

    /**
     * @ORM\Column(type="string")
     */
    protected $taxNumber;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $taxable;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Geography\Address")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $address;
    
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $www;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $phone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $mobile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $accountNumber;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    protected $bic;
        
    public function getCode(): ?string
    {
        return $this->code;
    }    

    public function getName(): ?string
    {
        return $this->name;
    }   

    public function getShortName(): ?string
    {
    	if($this->shortName == "" || $this->shortName == null)
    		return $this->getName();
    	else 
    		return $this->shortName;
         
    }

    public function getTaxNumber(): ?int
    {
        return $this->taxNumber;
    }
    
    public function getFullTaxNumber(): string
    {
    	if($this->taxable)
    		return "SI ".$this->taxNumber;
    	return $this->taxNumber;
    }

    public function getTaxable(): ?bool
    {
        return $this->taxable;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getWww(): ?string
    {
        return $this->www;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }    
}
