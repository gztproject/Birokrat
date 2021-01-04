<?php
namespace App\Entity\IncomingInvoice\Enumerators;
/**
 * Payment methods
 *
 */
abstract class PaymentMethods {
	const cash = 00;
	const transaction = 10;
}