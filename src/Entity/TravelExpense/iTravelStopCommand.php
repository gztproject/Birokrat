<?php

namespace App\Entity\TravelExpense;

interface iTravelStopCommand
{    
    public function __get($name);
    
    public function __set($name, $value);
}
