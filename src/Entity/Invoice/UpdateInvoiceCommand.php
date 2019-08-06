<?php

namespace App\Entity\Invoice;

class UpdateInvoiceCommand extends CreateInvoiceCommand
{	
	
	public function __construct()
	{
		$this->invoiceItemCommands = array();
	}
	
	public function getDiscount()
	{
		return $this->discount * 100;
	}

}
