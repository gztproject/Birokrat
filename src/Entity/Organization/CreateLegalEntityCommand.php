<?php

namespace App\Entity\Organization;

class CreateLegalEntityCommand
{
    public $code;
    public $name;
    public $shortName;
    public $taxNumber;
    public $taxable;
    public $address;
    public $www;
    public $email;
    public $phone;
    public $mobile;
    public $accountNumber;
    public $bic;

    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    } 
}
