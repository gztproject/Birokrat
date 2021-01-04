<?php
namespace App\Entity\IncomingInvoice\Enumerators;
/**
 * 00-draft, 10-received, 20-paid, 100-refunded, 110-rejected.
 *
 */
abstract class States {
	const draft = 00;
	const received = 10;
	const paid = 20;
	const refunded = 100;
	const rejected = 110;
}