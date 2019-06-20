<?php

namespace App\Entity\Invoice;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Invoice\InvoiceItemRepository")
 */
class InvoiceItem extends Base
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $quantity;

    /**
     * @ORM\Column(type="string", length=5)
     */
    private $unit;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $price;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2, nullable=true)
     */
    private $discount;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Invoice\Invoice", inversedBy="invoiceItems")
     * @ORM\JoinColumn(nullable=false)
     */
    private $invoice;

    public function __construct(CreateInvoiceItemCommand $c, Invoice $invoice)
    {
    	parent::__construct($invoice->getCreatedBy());
    	$this->invoice = $invoice;
    	
    	$this->code = $c->code;
    	$this->discount = $c->discount/100;
    	$this->name = $c->name;
    	$this->price = $c->price;
    	$this->quantity = $c->quantity;
    	$this->unit = $c->unit;    	
    }
    
    /*
     * Getters...
     */
    
    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getDiscount()
    {
        return $this->discount;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }    
}
