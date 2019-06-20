<?php

namespace App\Entity\Settings;

class CreateOrganizationSettingsCommand
{
    public $invoicePrefix;
    public $defaultPaymentDueIn;
    public $referenceModel;
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    } 
}
