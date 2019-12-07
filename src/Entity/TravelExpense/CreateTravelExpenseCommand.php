<?php

namespace App\Entity\TravelExpense;

use Doctrine\Common\Collections\ArrayCollection;

class CreateTravelExpenseCommand 
{    
	/**
	 * Need this for getting them from the form to controller...
	 * @var Array[CreateTravelStopCommand]
	 */
	public $travelStopCommands;
    public $date;    
    public $employee;
    public $organization;
    public $rate;
    
    public function __construct()
    {
    	$this->travelStopCommands = new ArrayCollection();
    }

    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
