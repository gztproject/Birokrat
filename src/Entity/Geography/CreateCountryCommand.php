<?php

namespace App\Entity\Geography;

class CreateCountryCommand
{
    public $name;
    public $nameInt;
    public $A2;
    public $A3;
    public $N3;

    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {    	
    	$this->$name = $value;
    }
}
