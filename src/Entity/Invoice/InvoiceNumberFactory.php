<?php

namespace App\Entity\Invoice;

use App\Entity\Organization\Organization;
use Doctrine\Common\Persistence\ManagerRegistry;

class InvoiceNumberFactory
{
   private $__issuer = null;
   private $__doctrine = null;

   public function __construct(Organization $issuer, ManagerRegistry $doctrine)
   {
   	$this->__issuer = $issuer;
   	$this->__doctrine = $doctrine;
   }

   public static function factory(Organization $issuer, ManagerRegistry $doctrine)
   {
   	return new InvoiceNumberFactory($issuer, $doctrine);
   }

   public function generate(): String
   {
   	$lastInvoice = $this->__doctrine->getRepository(Invoice::class)->findOneBy(['issuer'=>$this->__issuer], ['dateOfIssue'=>'DESC']);
   	$lastNumber = "";
   	if($lastInvoice)
   		$lastNumber = $lastInvoice->getNumber();
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