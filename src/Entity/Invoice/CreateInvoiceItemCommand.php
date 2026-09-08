<?php

namespace App\Entity\Invoice;

class CreateInvoiceItemCommand
{
	public $id;

    public $code;

    public $name;

    public $quantity = 1;

    public $unit = 'x';

    public $price = 0;

    public $discount = 0;

    
    public function __get($name) 
    {    	
    	return $this->$name;
    }
    
    public function __set($name, $value) 
    {    	
    	$this->$name = $value;
    }
}
