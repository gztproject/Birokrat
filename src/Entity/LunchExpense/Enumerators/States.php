<?php 
namespace App\Entity\LunchExpense\Enumerators;

abstract class States
{
	const draft = 00;
	const new = 10;
	const booked = 20;
	const rejected = 100;
}