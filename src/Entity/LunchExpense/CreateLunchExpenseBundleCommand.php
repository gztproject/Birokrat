<?php

namespace App\Entity\LunchExpense;

class CreateLunchExpenseBundleCommand 
{    
	/**
	 * Need this for getting them from the form to controller...
	 * @var Array[LunchExpense]
	 */
	public $lunchExpenses;
    public $date;    
    public $organization;
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
