<?php

namespace App\Entity\Invoice;

use App\Entity\Organization\Organization;
use Doctrine\Common\Persistence\ManagerRegistry;

class InvoiceNumberFactory
{
   	private $__issuer = null;
   	private $__em = null;
   	private $__state = null;
	
   	public function __construct(Organization $issuer, int $state, ManagerRegistry $em)
   	{
   		$this->__issuer = $issuer;
   		$this->__em = $em;
   		$this->__state = $state;
   	}
	
   	public static function factory(Organization $issuer, int $state, ManagerRegistry $em)
   	{
   		return new InvoiceNumberFactory($issuer, $state, $em);
   	}
	
   	public function generate(): String
   	{   	
   		$sql = "SELECT i.* FROM invoice AS i WHERE i.issuer_id = '".$this->__issuer->getId()."' ";   		
   		if($this->__state == 00)
   		{
   			$sql .= "AND i.state IN(10,20,30,50)";
   		}
   		if($this->__state == 10)
   		{
   			$sql .= "AND i.state IN(20,30,50)";
   		}
   		else if ($this->__state > 10)
   			throw new \Exception("Invoice numbers can only be assigned to draft and new invoices.");
   		$sql .= 'ORDER BY i.date_of_issue DESC, i.number DESC LIMIT 1';
   		$stmt = $this->__em->getConnection()->prepare($sql);
   		$stmt->execute();   	 
   		
   		$lastInvoice =  $stmt->fetchAll();
   		
	   	$lastNumber = "";
	   	if($lastInvoice[0])
   			$lastNumber = $lastInvoice[0]['number'];
   		else
   		{
   			$prefix = $this->__issuer->getOrganizationSettings()->getInvoicePrefix();
   			$lastNumber = $prefix ? $prefix.'-' : '';
   			$lastNumber .= date("Y") . '-' . '0000';
   		}
   		
   		$parts = explode('-', $lastNumber);
   		$parts[array_key_last ($parts)] = sprintf('%04d', $parts[array_key_last ($parts)]+1);
   		
   		return implode('-', $parts);
   	}
   
}