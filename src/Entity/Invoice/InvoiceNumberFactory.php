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
	
   	public function generate(): String
   	{   	
   		$sql = "SELECT i.* FROM invoice AS i WHERE i.issuer_id = '".$this->__issuer->getId()."' AND YEAR(i.date_of_issue) = ".$this->__dateOfIssue->format("Y");   		
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
   		$sql .= ' ORDER BY i.date_of_issue DESC, i.number DESC LIMIT 1';
   		$stmt = $this->__em->getConnection()->prepare($sql);   		  	 
   		
   		$lastInvoice =  $stmt->executeQuery()->fetchAssociative();
   		
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