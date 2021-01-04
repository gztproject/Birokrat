<?php 
namespace App\Entity\Konto\Enumerators;

/**
 * This is a konto type enum, telling us wether it's an active or passive konto
 * 0-active (debit-credit), 1-passive (credit-debit).
 *
 * @author gapi
 */
abstract class KontoTypes {
	/**
	 * Active konto (debit-credit)
	 */
	const active = 0;
	/**
	 * Passive konto (credit-debit)
	 */
	const passive = 1;
}