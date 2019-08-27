<?php

namespace App\Entity\Invoice;

class UpdateInvoiceItemCommand extends CreateInvoiceItemCommand
{
	public function getDiscount()
	{
		return $this->discount * 100;
	}
}
