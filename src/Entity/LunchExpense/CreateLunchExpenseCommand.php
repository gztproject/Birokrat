<?php

namespace App\Entity\LunchExpense;

class CreateLunchExpenseCommand 
{    
    public $date;    
    public $organization;
    public $sum;
    
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
