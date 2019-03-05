<?php

namespace App\Entity\Organization;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\User\User;
use App\Entity\Geography\Address;

class LegalEntityBase extends Base
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
     * @ORM\Column(type="integer")
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

    public function init (string $code, string $name, int $taxNumber, bool $taxable, Address $address, string $shortName = null,
    	string $www = null, string $email = null, string $phone = null, string $mobile = null, string $accountNumber = null, string $bic = null)
    {
    	$this->setCode($code);
    	$this->setName($name);
    	$this->setTaxNumber($taxNumber);
    	$this->setTaxable($taxable);
    	$this->setAddress($address);
    	if($shortName)
    		$this->setShortName($shortName);
    	if($www)
    		$this->setWww($www);
    	if($email)
    		$this->setEmail($email);
    	if($phone)
    		$this->setPhone($phone);
    	if($mobile)
    		$this->setMobile($mobile);
    	if($accountNumber)
    		$this->setAccountNumber($accountNumber);
    	if($bic)
    		$this->setBic($bic);
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
    		return "SI ".$this->taxNumber;
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

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): self
    {        
        $this->address = $address;   
        return $this;
    }

    
    public function getWww(): ?string
    {
        return $this->www;
    }

    public function setWww(?string $wwwAddress): self
    {
        $this->www = $wwwAddress;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): self
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): self
    {
        $this->bic = $bic;

        return $this;
    }
}
