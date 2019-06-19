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
    public $createInvoiceItemCommands;
    
    /**
     * @deprecated Using CreatedBy instead of this.
     * @var User
     */
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
