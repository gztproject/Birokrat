<?php 
namespace App\Entity\TravelExpense\Enumerators;

/**
 * 00-new, 10-unbooked, 20-booked, 100-cancelled.
 *
 * @author gapi
 */
abstract class States {
	/**
	 * A new TE
	 * @var integer
	 */
	const new = 00;
	/**
	 * An unbooked TE
	 * @var integer
	 */
	const unbooked = 10;
	/**
	 * A booked TE
	 * @var integer
	 */
	const booked = 20;
	/**
	 * A cancelled TE
	 * @var integer
	 */
	const cancelled = 100;
}