<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Post;
use App\Entity\Geography\Address;
use App\Entity\Organization\Organization;
use Doctrine\ORM\EntityNotFoundException;

class OrganizationsInitializer
{
    private $posts;
    private $addresses;

    public function generate(ObjectManager $manager, array $posts)
    {
        $organizations = array();
        $this->posts = $posts;
        $path = __DIR__ . "/InitData/organizations.csv";
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($path);
        $this->addresses = Array();
                
        foreach ($rows as $row) {
            
            $organization = new Organization();
            $organization->setCode($row["Code"]);
            $organization->setName($row["Name"]);
            $organization->setShortName($row["ShortName"]);
            $organization->setTaxNumber($row["TaxNumber"]);
            $organization->setTaxable($row["Taxable"]==='TRUE');
            $organization->setInvoicePrefix($row["InvoicePrefix"]);
            
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