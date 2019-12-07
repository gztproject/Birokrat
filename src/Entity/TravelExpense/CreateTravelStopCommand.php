<?php

namespace App\Entity\TravelExpense;

class CreateTravelStopCommand  implements iTravelStopCommand
{    
	public $id;
    public $post;
    public $address;
    public $organization;
    public $stopOrder;
    public $distanceFromPrevious;

    public function __get($name) {
    	return $this->$name;
    }
    
    public function __set($name, $value) {
    	$this->$name = $value;
    }
}
