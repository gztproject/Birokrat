<?php

namespace App\Entity\Invoice;

class CreateInvoiceCommand
{
    public $dateOfIssue;
    
    public $issuer;

    public $recepient;
    
    public $number;

    public $discount;
    
    /**
     * Need this for getting them from the form to controller...
     * @var Array[CreateInvoiceItemCommand]
     */
    public $invoiceItemCommands;
    
    public $dateServiceRenderedFrom;
    
    public $dateServiceRenderedTo;

    public $dueDate;
    
    public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}
   
}
