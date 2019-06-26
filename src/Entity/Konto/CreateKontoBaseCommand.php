<?php

namespace App\Entity\Konto;

abstract class CreateKontoBaseCommand
{
    public $number;
    public $name; 
    public $description;   
    
    public function __get($name) {
    	return $this->$name;
    }    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
