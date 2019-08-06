<?php

namespace App\Entity\Invoice;

class CreateInvoiceItemCommand
{
	public $id;

    public $code;

    public $name;

    public $quantity;

    public $unit;

    public $price;

    public $discount;

    
    public function __get($name) 
    {    	
    	return $this->$name;
    }
    
    public function __set($name, $value) 
    {    	
    	$this->$name = $value;
    }
}
