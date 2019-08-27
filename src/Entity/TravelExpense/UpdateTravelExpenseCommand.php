<?php

namespace App\Entity\TravelExpense;

class UpdateTravelExpenseCommand extends CreateTravelExpenseCommand
{
	public function __construct()
	{
		$this->travelStopCommands = array();
	}

}