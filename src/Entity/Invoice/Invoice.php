<?php

namespace App\Entity\Invoice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InvoiceRepository")
 */
class Invoice extends Base
{
    /**
     * @ORM\Column(type="datetime")
     */
    private $dateOfIssue;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $issuer;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $recepient;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2, nullable=true)
     */
    private $discount;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     */
    private $totalValue;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=2)
     */
    private $totalPrice;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $referenceNumber;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Invoice\InvoiceItem", mappedBy="invoice", orphanRemoval=true)
     */
    private $invoiceItems;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Invoice\InvoiceState")
     * @ORM\JoinColumn(nullable=false)
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $issuedBy;
    
    /**
     * @ORM\Column(type="date")
     */
    private $dateServiceRenderedFrom;
    
	/**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateServiceRenderedTo;
    
    public function __construct()
    {
        $this->invoiceItems = new ArrayCollection();
    }

    public function getDateOfIssue(): ?\DateTimeInterface
    {
        return $this->dateOfIssue;
    }

    public function setDateOfIssue(\DateTimeInterface $dateOfIssue): self
    {
        $this->dateOfIssue = $dateOfIssue;

        return $this;
    }

    public function getIssuer(): ?Organization
    {
        return $this->issuer;
    }

    public function setIssuer(?Organization $issuer): self
    {
        $this->issuer = $issuer;

        return $this;
    }

    public function getRecepient(): ?Organization
    {
        return $this->recepient;
    }

    public function setRecepient(?Organization $recepient): self
    {
        $this->recepient = $recepient;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getDiscount()
    {
        return $this->discount;
    }

    public function setDiscount($discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getTotalValue()
    {
        return $this->totalValue;
    }

    public function setTotalValue($totalValue): self
    {
        $this->totalValue = $totalValue;

        return $this;
    }

    public function getTotalPrice()
    {
        return $this->totalPrice;
    }

    public function setTotalPrice($totalPrice): self
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    /**
     * @return Collection|InvoiceItem[]
     */
    public function getInvoiceItems(): Collection
    {
        return $this->invoiceItems;
    }

    public function addInvoiceItem(InvoiceItem $invoiceItem): self
    {
        if (!$this->invoiceItems->contains($invoiceItem)) {
            $this->invoiceItems[] = $invoiceItem;
            $invoiceItem->setInvoice($this);
        }

        return $this;
    }

    public function removeInvoiceItem(InvoiceItem $invoiceItem): self
    {
        if ($this->invoiceItems->contains($invoiceItem)) {
            $this->invoiceItems->removeElement($invoiceItem);
            // set the owning side to null (unless already changed)
            if ($invoiceItem->getInvoice() === $this) {
                $invoiceItem->setInvoice(null);
            }
        }

        return $this;
    }

    public function getState(): ?InvoiceState
    {
        return $this->state;
    }

    public function setState(?InvoiceState $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getIssuedBy(): ?User
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(?User $issuedBy): self
    {
        $this->issuedBy = $issuedBy;

        return $this;
    }  
    
    public function getDateServiceRenderedFrom(): ?\DateTimeInterface
    {
    	return $this->dateServiceRenderedFrom;
    }
    
    public function setDateServiceRenderedFrom(\DateTimeInterface $dateServiceRenderedFrom): self
    {
    	$this->dateServiceRenderedFrom = $dateServiceRenderedFrom;
    	
    	return $this;
    }
    
    public function getDateServiceRenderedTo(): ?\DateTimeInterface
    {
    	return $this->dateServiceRenderedTo;
    }
    
    public function setDateServiceRenderedTo(\DateTimeInterface $dateServiceRenderedTo): self
    {
    	$this->dateServiceRenderedTo = $dateServiceRenderedTo;
    	
    	return $this;
    }
    
    public function getNewInvoiceNumber(Organization $organization, ManagerRegistry $doctrine): ?string
    {
    	$lastInvoice = $doctrine->getRepository(Invoice::class)->findOneBy(['issuer'=>$organization], ['dateOfIssue'=>'DESC']);
    	$lastNumber = "";
    	if($lastInvoice)
    		$lastNumber = $lastInvoice->getNumber();
    	else 
    	{
    		$prefix = $organization->getInvoicePrefix();
    		$lastNumber = $prefix ? $prefix.'-' : '';
    		$lastNumber .= date("Y") . '-' . '0000';
    	}
    	
    	$parts = explode('-', $lastNumber);
    	$parts[array_key_last ($parts)] = sprintf('%04d', $parts[array_key_last ($parts)]+1);
    	
    	return implode('-', $parts);    	
    }
}
