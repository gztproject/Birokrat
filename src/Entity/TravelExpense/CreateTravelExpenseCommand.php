<?php

namespace App\Entity\TravelExpense;

class CreateTravelExpenseCommand 
{    
	/**
	 * Need this for getting them from the form to controller...
	 * @var Array[CreateTravelStopCommand]
	 */
	public $travelStopCommands;
    public $date;    
    public $employee;
    public $rate;
    
    public function __construct()
    {
    	$this->travelStopCommands = array();
    }

    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
