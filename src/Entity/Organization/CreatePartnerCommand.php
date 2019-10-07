<?php

namespace App\Entity\Organization;

class CreatePartnerCommand extends CreateLegalEntityCommand
{ 
	public $isClient;
	
	public $isSupplier;
	
}
