<?php

namespace App\Entity\IncomingInvoice;

class CreateIncomingInvoiceCommand
{
    public $dateOfIssue;
    
    public $issuer;

    public $recepient;
    
    public $number;

    public $dueDate;
    
    public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}
   
}
