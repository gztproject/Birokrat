<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Post;
use App\Entity\Geography\Address;
use App\Entity\Organization\Organization;
use Doctrine\ORM\EntityNotFoundException;
use App\Entity\Settings\OrganizationSettings;
use App\Entity\Organization\Client;

class OrganizationsInitializer implements IEntityInitializer
{
	private $manager;
    private $posts;
    private $addresses;
    private $fileReader;
    private $partnersPath;
    private $organizationsPath;
    
    /**
     * Partner & organization initializer
     * @param ObjectManager $manager DB manager to use for storing entities
     * @param string $partnersPath Relative path to .tsv file with partners
     * @param string $organizationsPath Relative path to .tsv file with organizations
     * @param array $posts Array of posts
     */
    public function __construct(ObjectManager $manager, string $partnersPath, string $organizationsPath, array $posts)
    {
    	$this->manager = $manager;
    	$this->partnersPath = __DIR__ . $partnersPath;
    	$this->organizationsPath = __DIR__ . $organizationsPath;
    	$this->posts = $posts;
    }

    /**
     * Generates Client and Organization entities
     * @throws EntityNotFoundException Thrown when trying to create an address with nonexisting post 
     * @return array Array of generated organizations (doesn't return clients)
     */
    public function generate(): array
    {	
    	$this->fileReader = new ImportFileReader();
    	$this->addresses = Array();
    	
    	// Let's set partners first
    	$rows = $this->fileReader->GetRows($this->partnersPath);
    	$this->addresses = Array();
    	
    	foreach ($rows as $row) {
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
    			$this->manager->persist($address);
    			array_push($this->addresses, $address);
    		}
    		$client = new Client();
    		$client->init($row["Code"], $row["Name"], $row["TaxNumber"], $row["Taxable"]==='TRUE', $address, $row["ShortName"], 
    				$row["www"], $row["email"], $row["phone"], $row["mobile"], $row["accountNumber"], $row["bic"]);
    		    		
    		$this->manager->persist($client);    		
    		$this->manager->flush();
    	}
    	
    	
    	// Set default organizationSettings
    	$defOrganizationSettings = new OrganizationSettings();
    	$defOrganizationSettings->setOrganization(null);
    	$defOrganizationSettings->setInvoicePrefix("");
    	$defOrganizationSettings->setReferenceModel("SI00");
    	$defOrganizationSettings->setDefaultPaymentDueIn(30);
    	
    	$this->manager->persist($defOrganizationSettings);
    	$this->manager->flush();
    	
    	// And now organizations
        $organizations = array(); 
        $rows = $this->fileReader->GetRows($this->organizationsPath);
                
        foreach ($rows as $row) {
        	$organizationSettings = new OrganizationSettings();
            if($row["InvoicePrefix"] || $row["defaultPaymentDueIn"] || $row["referenceModel"]){            	
            	$organizationSettings->setInvoicePrefix($row["InvoicePrefix"]);
            	$organizationSettings->setDefaultPaymentDueIn($row["defaultPaymentDueIn"]);
            	$organizationSettings->setReferenceModel($row["referenceModel"]);            	
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
            	$this->manager->persist($address);
            	array_push($this->addresses, $address);
            }
            $organization = new Organization();
            $organization->initOrganization($row["Code"], $row["Name"], $row["TaxNumber"], $row["Taxable"]==='TRUE', $address, $organizationSettings,
            		$row["ShortName"], $row["www"], $row["email"], $row["phone"], $row["mobile"], $row["accountNumber"], $row["bic"]);
                  
            $this->manager->persist($organization);
            array_push($organizations, $organization);
            $this->manager->flush();
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