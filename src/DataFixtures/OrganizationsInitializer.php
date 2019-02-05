<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Post;
use App\Entity\Geography\Address;
use App\Entity\Organization\Organization;

class OrganizationsInitializer
{
    private $posts;

    public function generate(ObjectManager $manager, array $posts)
    {
        $organizations = array();
        $this->posts = $posts;
        $path = __DIR__ . "/InitData/organizations.csv";
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($path);
                
        foreach ($rows as $row) {
            
            $organization = new Organization();
            $organization->setCode($row["Code"]);
            $organization->setName($row["Name"]);
            $organization->setShortName($row["ShortName"]);
            $organization->setTaxNumber($row["TaxNumber"]);
            $organization->setTaxable($row["Taxable"]==='TRUE');
            $organization->setInvoicePrefix($row["InvoicePrefix"]);
            
            //address...
            $address = new Address();
            $address->setLine1($row["Address1"]);
            $address->setLine2($row["Address2"]);
            $address->setPost($this->getPost($row["postCodeInternational"]));
            $manager->persist($address);
            
            $organization->addAddress($address);
                  
            $manager->persist($organization);
            array_push($organizations, $organization);
            $manager->flush();
        }
        return $organizations;
    }
    
    private function getPost($postCodeInternational): Post
    {
        foreach ($this->posts as $post) {
            if ($post->getCodeInternational() === $postCodeInternational) {
                return $post;
            }
        }
        return null;
    }
}