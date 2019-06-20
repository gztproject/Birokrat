<?php

namespace App\Entity\Settings;

class CreateUserSettingsCommand
{    
        
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    } 
}
