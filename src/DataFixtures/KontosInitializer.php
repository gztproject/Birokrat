<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Konto\KontoClass;
use App\Entity\Konto\KontoCategory;
use App\Entity\Konto\Konto;

class KontosInitializer
{      
    public function generate(ObjectManager $manager)
    {
        $path = __DIR__ . "/InitData/kontos.csv";
        $fileReader = new ImportFileReader();
        $rows = $fileReader->GetRows($path);
        
        
        foreach ($rows as $row) {
            $number = $row["Number"];
            $name = $row["Name"];
            $lastClass = null;
            $lastCategory = null;
            switch (strlen((string)$number)){
                case 1:
                    $kontoClass = new KontoClass();
                    $kontoClass->setNumber((int)$number);
                    $kontoClass->setName($name); 
                    $lastClass = $kontoClass;
                    $manager->persist($kontoClass);
                    $manager->flush();
                    break;
                case 2:
                    $kontoCategory = new KontoCategory();
                    $kontoCategory->setNumber((int)$number);
                    $kontoCategory->setName($name);
                    $kontoCategory->setClass($lastClass);
                    $lastCategory = $kontoCategory;
                    $manager->persist($kontoCategory);
                    $manager->flush();
                    break;
                case 3:
                    $konto = new Konto();
                    $konto->setNumber((int)$number);
                    $konto->setName($name);
                    $konto->setCategory($lastCategory);
                    $konto->setIsActive(trim($row["IsActive"])==='Da');
                    $manager->persist($konto);
                    $manager->flush();
                    break;               
            }                          
        }    
    }
}