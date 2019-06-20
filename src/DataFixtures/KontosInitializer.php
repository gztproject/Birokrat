<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Konto\KontoClass;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\Konto;

class KontosInitializer implements IEntityInitializer
{    
	private $path;
	private $manager;
	
	/**
	 * Konto Initializer
	 * @param ObjectManager $manager DB manager to use for storing entities	
	 * @param string $path Relative path to .tsv file
	 */
	public function __construct(ObjectManager $manager, string $path)
	{
		$this->path = __DIR__ . $path;
		$this->manager = $manager;
	}
	
	/**
	 * Generates kontos.	 
	 * @return array Array of generated kontos 
	 */	
    public function generate(): array
    {        
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($this->path);        
        
        $kontos = array();
        foreach ($rows as $row) {
            $number = $row["Number"];
            $name = $row["Name"];
            $lastClass = null;
            $lastCategory = null;
            switch (strlen(trim((string)$number))){
                case 1:
                    $kontoClass = new KontoClass();
                    $kontoClass->setNumber((int)$number);
                    $kontoClass->setName($name); 
                    $lastClass = $kontoClass;
                    $this->manager->persist($kontoClass);
                    $this->manager->flush();
                    break;
                case 2:
                	if($lastClass == null)
                		$lastClass = $kontoClass;
                    $kontoCategory = new KontoCategory();
                    $kontoCategory->setNumber((int)$number);
                    $kontoCategory->setName($name);
                    $kontoCategory->setClass($lastClass);
                    $lastCategory = $kontoCategory;
                    $this->manager->persist($kontoCategory);
                    $this->manager->flush();
                    break;
                case 3:
                	if($lastCategory == null)
                		$lastCategory = $kontoCategory;
                    $konto = new Konto();
                    $konto->setNumber((int)$number);
                    $konto->setName($name);
                    $konto->setCategory($lastCategory);
                    $konto->setIsActive(trim($row["IsActive"])==='Da');
                    $this->manager->persist($konto);
                    array_push($kontos, $konto);
                    $this->manager->flush();
                    break;               
            }                          
        } 
        return $kontos;
    }
}