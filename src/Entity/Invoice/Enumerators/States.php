<?php
namespace App\Entity\Invoice\Enumerators;
/**
 * 00-draft, 10-new, 20-issued, 30-paid, 40-cancelled, 50-rejected.
 *
 * @author gapi
 */
abstract class States {
	const draft = 00;
	const new = 10;
	const issued = 20;
	const paid = 30;
	const cancelled = 40;
	const rejected = 50;
}