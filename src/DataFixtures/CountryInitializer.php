<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Country;

class CountryInitializer
{      
    public function generate(ObjectManager $manager): array
    {
        $path = __DIR__ . "/InitData/countries.csv";
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($path);
        $countries = array();
        
        foreach ($rows as $row) {
            $country = new Country();
            $country->setName($row["Name"]);
            $country->setNameInt($row["NameInternational"]);
            $country->setA2($row["A2"]);
            $country->setA3($row["A3"]);
            $country->setN3($row["N3"]);
                
        
            $manager->persist($country);
            array_push($countries, $country);
            $manager->flush();
        }
        
        
        return $countries;
    }
}