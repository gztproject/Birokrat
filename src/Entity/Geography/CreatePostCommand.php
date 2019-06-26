<?php

namespace App\Entity\Geography;


class CreatePostCommand
{
    public $code;
    public $codeInternational;
    public $name;
	
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
