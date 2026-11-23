<?php

namespace App\Entity\Invoice;

use App\Entity\Organization\Organization;
use Doctrine\Persistence\ManagerRegistry;

class InvoiceNumberFactory
{
   	private $__issuer = null;
   	private $__em = null;
   	private $__state = null;
   	private $__dateOfIssue = null;
	
   	public function __construct(Organization $issuer, int $state, \DateTimeInterface $dateOfIssue, ManagerRegistry $em)
   	{
   		$this->__issuer = $issuer;
   		$this->__em = $em;
   		$this->__state = $state;
   		$this->__dateOfIssue = $dateOfIssue;
   	}
	
   	public static function factory(Organization $issuer, int $state, \DateTimeInterface $dateOfIssue, ManagerRegistry $em)
   	{
   		return new InvoiceNumberFactory($issuer, $state, $dateOfIssue, $em);
   	}
	
   	/**
   	 * Returns the next number without reserving it. Use it only for previews.
   	 */
   	public function generate(): String
   	{
   		return $this->next(false);
   	}
   	
   	/**
   	 * Returns the next number and keeps the issuer's sequence locked until the
   	 * surrounding transaction ends, so the number has to be stored in that
   	 * same transaction.
   	 */
   	public function allocate(): String
   	{
   		$connection = $this->__em->getConnection();
   		if(!$connection->isTransactionActive())
   			throw new \LogicException("Invoice numbers can only be allocated inside a transaction.");
   		
   		$locked = $connection->executeQuery(
   			'SELECT o.id FROM organization AS o WHERE o.id = ? FOR UPDATE',
   			[(string) $this->__issuer->getId()]
   		)->fetchOne();
   		if($locked === false)
   			throw new \Exception("Can't find the issuing organization.");
   		
   		return $this->next(true);
   	}
   	
   	private function next(bool $locking): String
   	{   	
   		$sql = "SELECT i.number FROM invoice AS i WHERE i.issuer_id = ? AND YEAR(i.date_of_issue) = ?";
   		if($this->__state == 00)
   		{
   			$sql .= " AND i.state IN(10,20,30,50)";
   		}
   		if($this->__state == 10)
   		{
   			$sql .= " AND i.state IN(20,30,50)";
   		}
   		else if ($this->__state > 10)
   			throw new \Exception("Invoice numbers can only be assigned to draft and new invoices.");
   		$sql .= " ORDER BY CAST(SUBSTRING_INDEX(i.number, '-', -1) AS UNSIGNED) DESC, i.date_of_issue DESC LIMIT 1";
   		if($locking)
   			$sql .= ' FOR UPDATE';
   		
   		$lastInvoice = $this->__em->getConnection()->executeQuery(
   			$sql,
   			[(string) $this->__issuer->getId(), (int) $this->__dateOfIssue->format("Y")]
   		)->fetchAssociative();
   		
	   	$lastNumber = "";
	   	$prefix = $this->__issuer->getOrganizationSettings()!=null?$this->__issuer->getOrganizationSettings()->getInvoicePrefix():"";
	   	
	   	if($lastInvoice)
   			$lastNumber = $lastInvoice['number'];
   		else
   		{   			
   			$lastNumber = $prefix ? $prefix.'-' : '';
   			$lastNumber .= $this->__dateOfIssue->format("Y") . '-' . '0000';
   		}
   		
   		$parts = explode('-', $lastNumber);
   		if(count($parts) == 3)
   			$parts[array_key_first($parts)] = $prefix;
   		$parts[array_key_last($parts)-1] = sprintf('%04d', $this->__dateOfIssue->format("Y"));
   		$parts[array_key_last($parts)] = sprintf('%04d', $parts[array_key_last ($parts)]+1);
   		
   		return implode('-', $parts);
   	}
   
}