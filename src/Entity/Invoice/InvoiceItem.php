<?php

namespace App\Entity\Invoice;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Invoice\InvoiceItemRepository")
 */
class InvoiceItem extends AggregateBase
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
    
    public function update(UpdateInvoiceItemCommand $c, Invoice $invoice) : InvoiceItem
    {
    	parent::updateBase($invoice->getUpdatedBy());
    	if($c->code != null && $c->code != $this->code)
    		$this->code = $c->code;
    	if($c->discount != null && $c->discount/100 != $this->discount)
    		$this->discount = $c->discount/100;
    	if($c->name != null && $c->name != $this->name)
    		$this->name = $c->name;
    	if($c->price != null && $c->price != $this->price)
    		$this->price = $c->price;
    	if($c->quantity != null && $c->quantity != $this->quantity)
    		$this->quantity = $c->quantity;
    	if($c->unit != null && $c->unit != $this->unit)
    		$this->unit = $c->unit;
    	
    	return $this;
    }
        
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo($to)
    {
    	if ($to instanceof UpdateInvoiceItemCommand || $to instanceof CreateInvoiceItemCommand)
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
