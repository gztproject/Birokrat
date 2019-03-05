<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Post;
use App\Entity\Geography\Address;
use App\Entity\Organization\Organization;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\Organization\Partner;

class OrganizationsInitializer
{
    private $posts;
    private $addresses;
    private $fileReader;

    public function generate(ObjectManager $manager, array $posts)
    {
    	
    	$this->posts = $posts;
    	$this->fileReader = new ImportFileReader();
    	$this->addresses = Array();
    	
    	// Let's set partners first
    	$rows = $this->fileReader->GetRows(__DIR__ . "/InitData/partners.csv");
    	$this->addresses = Array();
    	
    	foreach ($rows as $row) {
    		
    		$partner = new Partner();
    		$partner->setCode($row["Code"]);
    		$partner->setName($row["Name"]);
    		$partner->setShortName($row["ShortName"]);
    		$partner->setTaxNumber($row["TaxNumber"]);
    		$partner->setTaxable($row["Taxable"]==='TRUE');
    		$partner->setWww($row["www"]);
    		$partner->setMobile($row["mobile"]);
    		$partner->setPhone($row["phone"]);
    		$partner->setEmail($row["email"]);
    		$partner->setAccountNumber($row["accountNumber"]);
    		$partner->setBic($row["bic"]);
    		
    		//address...
    		$post = $this->getPost($row["postCodeInternational"]);
    		if($post == null)
    		{
    			throw new EntityNotFoundException('Post with postcode '.$row["postCodeInternational"].' doesn\'t exist.');
    		}
    		//try to find existing address:
    		$address = $this->getAddress($row["Address1"], $row["Address2"], $post);
    		if($address == null){
    			$address = new Address();
    			$address->setLine1($row["Address1"]);
    			$address->setLine2($row["Address2"]);
    			$address->setPost($post);
    			$manager->persist($address);
    			array_push($this->addresses, $address);
    		}
    		
    		$partner->setAddress($address);
    		
    		$manager->persist($partner);    		
    		$manager->flush();
    	}
    	
    	
    	// Set default organizationSettings
    	$defOrganizationSettings = new OrganizationSettings();
    	$defOrganizationSettings->setOrganization(null);
    	$defOrganizationSettings->setInvoicePrefix("");
    	$defOrganizationSettings->setReferenceModel("SI00");
    	$defOrganizationSettings->setDefaultPaymentDueIn(30);
    	
    	$manager->persist($defOrganizationSettings);
    	$manager->flush();
    	
    	// And now organizations
        $organizations = array(); 
        $rows = $this->fileReader->GetRows(__DIR__ . "/InitData/organizations.csv");
                
        foreach ($rows as $row) {
            
            $organization = new Organization();
            $organization->setCode($row["Code"]);
            $organization->setName($row["Name"]);
            $organization->setShortName($row["ShortName"]);
            $organization->setTaxNumber($row["TaxNumber"]);
            $organization->setTaxable($row["Taxable"]==='TRUE');
            $organization->setWww($row["www"]);
            $organization->setMobile($row["mobile"]);
            $organization->setPhone($row["phone"]);
            $organization->setEmail($row["email"]);
            $organization->setAccountNumber($row["accountNumber"]);
            $organization->setBic($row["bic"]);
            
            if($row["InvoicePrefix"] || $row["defaultPaymentDueIn"] || $row["referenceModel"]){
            	$organizationSettings = new OrganizationSettings();
            	$organizationSettings->setInvoicePrefix($row["InvoicePrefix"]);
            	$organizationSettings->setDefaultPaymentDueIn($row["defaultPaymentDueIn"]);
            	$organizationSettings->setReferenceModel($row["referenceModel"]);
            	$organization->setOrganizationSettings($organizationSettings);
            }
            
            //address...
            $post = $this->getPost($row["postCodeInternational"]);
            if($post == null)
            {
            	throw new EntityNotFoundException('Post with postcode '.$row["postCodeInternational"].' doesn\'t exist.');
            }
            //try to find existing address:
            $address = $this->getAddress($row["Address1"], $row["Address2"], $post);
            if($address == null){
            	$address = new Address();
            	$address->setLine1($row["Address1"]);
            	$address->setLine2($row["Address2"]);
            	$address->setPost($post);
            	$manager->persist($address);
            	array_push($this->addresses, $address);
            }
            
            $organization->setAddress($address);
                  
            $manager->persist($organization);
            array_push($organizations, $organization);
            $manager->flush();
        }
        return $organizations;
    }
    
    private function getPost($postCodeInternational): ?Post
    {
        foreach ($this->posts as $post) {
            if ($post->getCodeInternational() === $postCodeInternational) {
                return $post;
            }
        }
        return null;
    }
    
    private function getAddress(string $line1, string $line2, Post $post): ?Address
    {
    	foreach ($this->addresses as $address) {
    		if ($address->getLine1() === $line1 && $address->getLine2() === $line2 && $address->getPost() === $post)  {
    			return $address;
    		}
    	}
    	return null;
    }
}