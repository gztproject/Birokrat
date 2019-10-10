<?php

namespace App\Entity\Transaction;

class CreateTransactionCommand
{
	public $organization;
	public $creditKonto;
	public $debitKonto;
	public $sum;
	public $date;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}
}
