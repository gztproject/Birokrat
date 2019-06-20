<?php

namespace App\Entity\Geography;

class CreateAddressCommand
{
    public $line1;    
    public $line2;
    /**
     * Only used in controller
     * @var Post
     */
    public $post;
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}