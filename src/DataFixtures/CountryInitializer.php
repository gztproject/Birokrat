<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Country;

class CountryInitializer
{      
    public function generate(ObjectManager $manager): array
    {
        $countries = array();
        
        //for...
        $country = new Country();
        $country->setName("Slovenija");
        $country->setNameInt("Slovenia");
        $country->setA2("SI");
        $country->setA3("SVN");
        $country->setN3(705);
                
        
        $manager->persist($country);
        array_push($countries, $country);
        $manager->flush();
        //end for...
        
        
        return $countries;
    }
}