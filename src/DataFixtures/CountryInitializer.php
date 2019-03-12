<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Geography\Country;

class CountryInitializer implements IEntityInitializer
{   
	private $path;
	private $manager;
	
	/**
	 * Country initializer
	 * @param ObjectManager $manager DB manager to use for storing entities
	 * @param string $path Relative path to .tsv file
	 */
	public function __construct(ObjectManager $manager, string $path)
	{
		$this->path = __DIR__ . $path;
		$this->manager = $manager;
	}
	
	/**
	 * Generates countries	 * 
	 * @return array Array of generated countries
	 */
    public function generate(): array
    {    	
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($this->path);
        $countries = array();
        
        foreach ($rows as $row) {
            $country = new Country();
            $country->setName($row["Name"]);
            $country->setNameInt($row["NameInternational"]);
            $country->setA2($row["A2"]);
            $country->setA3($row["A3"]);
            $country->setN3($row["N3"]);
                
        
            $this->manager->persist($country);
            array_push($countries, $country);
            $this->manager->flush();
        }
        
        
        return $countries;
    }
}