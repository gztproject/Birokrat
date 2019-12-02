<?php

namespace App\Entity\TravelExpense;

class CreateTravelExpenseBundleCommand 
{    
	/**
	 * Need this for getting them from the form to controller...
	 * @var Array[TravelExpense]
	 */
	public $travelExpenses;
    public $organization;
    
    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
