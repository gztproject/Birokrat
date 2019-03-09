<?php

namespace App\Entity\Organization;

use Doctrine\Common\Persistence\ManagerRegistry;

class OrganizationCodeFactory
{
   private $__entity = null;
   private $__doctrine = null;

   public function __construct(string $entity, ManagerRegistry $doctrine)
   {
   	$this->__entity = $entity;
   	$this->__doctrine = $doctrine;
   }

   public static function factory(string $entity, ManagerRegistry $doctrine)
   {
   	return new OrganizationCodeFactory($entity, $doctrine);
   }

   public function generate(): String
   {   	
   	$lastOrganization = $this->__doctrine->getRepository($this->__entity)->findOneBy([], ['code'=>'DESC']);
   	$lastCode = "";
   	if($lastOrganization)
   		$lastCode = $lastOrganization->getCode();
   		else
   		{
   			$lastCode .= '0';
   		}
   		
   		   		
   		return $lastCode + 1;
   }
   
}