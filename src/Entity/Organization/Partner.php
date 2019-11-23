<?php

namespace App\Entity\Organization;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Invoice\Invoice;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Organization\PartnerRepository")
 */
class Partner extends LegalEntityBase
{    
	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isSupplier;
	
	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isClient;
	
	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\Invoice\Invoice", mappedBy="recepient", orphanRemoval=false)
	 */
	private $invoices;
	
	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\IncomingInvoice\IncomingInvoice", mappedBy="issuer", orphanRemoval=false)
	 */
	private $incomingInvoices;
	
	public function __construct(CreatePartnerCommand $c, User $user)
	{
		parent::__construct($user);
		$this->code = $c->code;
		$this->name = $c->name;
		$this->taxNumber = $c->taxNumber;
		$this->taxable = $c->taxable;
		$this->address = $c->address;
		if($c->shortName)
			$this->shortName = $c->shortName;
		if($c->www)
			$this->www = $c->www;
		if($c->email)
			$this->email = $c->email;
		if($c->phone)
			$this->phone = $c->phone;
		if($c->mobile)
			$this->mobile = $c->mobile;
		if($c->accountNumber)
			$this->accountNumber = $c->accountNumber;
		if($c->bic)
			$this->bic = $c->bic;
		$this->isClient = $c->isClient;
		$this->isSupplier = $c->isSupplier;
	}
	
	public function update (UpdatePartnerCommand $c, User $user): Partner
	{
		//Should we make a copy and deactivate old one not to mix up old stuff?
		parent::updateBase($user);
		if($c->name != null && $c->name != $this->name)
			$this->name = $c->name;
		if($c->taxNumber != null && $c->taxNumber != $this->taxNumber)
			$this->taxNumber = $c->taxNumber;
		if($c->taxable != null && $c->taxable != $this->taxable)
			$this->taxable = $c->taxable;
		if($c->address != null && $c->address != $this->address)
			$this->address = $c->address;
		if($c->shortName != null && $c->shortName != $this->shortName)
			$this->shortName = $c->shortName;
		if($c->www != null && $c->www != $this->www)
			$this->www = $c->www;
		if($c->email != null && $c->email != $this->email)
			$this->email = $c->email;
		if($c->phone != null && $c->phone != $this->phone)
			$this->phone = $c->phone;
		if($c->mobile != null && $c->mobile != $this->mobile)
			$this->mobile = $c->mobile;
		if($c->accountNumber != null && $c->accountNumber != $this->accountNumber)
			$this->accountNumber = $c->accountNumber;
		if($c->bic != null && $c->bic != $this->bic)
			$this->bic = $c->bic;
		if($c->isClient != null && $c->isClient != $this->isClient)
			$this->isClient = $c->isClient;
		if($c->isSupplier != null && $c->isSupplier != $this->isSupplier)
			$this->isSupplier = $c->isSupplier;
									
		return $this;
	}
	
	/**
	 *
	 * @param object $to
	 * @return object
	 */
	public function mapTo($to)
	{
		if ($to instanceof UpdatePartnerCommand)
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
	
	public function isClient(): bool
	{
		return $this->isClient;
	}
	
	public function isSupplier(): bool
	{
		return $this->isSupplier;
	}
	
	/**
	 * @return Collection|Invoice[]
	 */
	public function getInvoices(): Collection
	{
		return $this->invoices;
	}
	
	/**
	 * @return Collection|IncomingInvoice[]
	 */
	public function getIncomingInvoices(): Collection
	{
		return $this->incomingInvoices;
	}
    
}
