<?php

namespace App\Entity\Transaction;

class CreateTransactionCommand
{
	public $organization;
	public $creditKonto;
	public $debitKonto;
	public $sum;
	public $date;
	public $description;
	
	public function __get($name) {
		return $this->$name;
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}
	
	public function __toString(): string {
		$sb = "";
		if($this->date !== null) $sb .= $this->date->format('d. m. Y').": ";
		if($this->organization !== null) $sb .= "Organization: ". $this->organization->getName()."; ";
		if($this->debitKonto !== null) $sb .= "Kontos: ".$this->debitKonto->getNumber();
		if($this->creditKonto !== null) $sb .= " <- ".$this->creditKonto->getNumber()."; ";
		if($this->sum !== null) $sb .= "SUM: ". $this->sum."; ";		
		return $sb;
	}
}
