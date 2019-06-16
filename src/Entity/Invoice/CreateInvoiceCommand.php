<?php

namespace App\Entity\Invoice;

class CreateInvoiceCommand
{
    public $dateOfIssue;
    
    public $issuer;

    public $recepient;

    public $number;

    public $discount;

    public $totalValue;

    public $totalPrice;

    public $invoiceItems;

    public $issuedBy;
    
    public $dateServiceRenderedFrom;
    
    public $dateServiceRenderedTo;

    public $dueDate;
    
    public function __get($name) {
		
		//echo "Get:$name";
		return $this->$name;
	}
	
	public function __set($name, $value) {
		
		//echo "Set:$name to $value";
		$this->$name = $value;
	}
   
}
