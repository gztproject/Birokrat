<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\InvoiceState;

class InvoiceStatesInitializer
{
    public function generate(ObjectManager $manager)
    {
        $states = array("new", "draft", "issued", "paid", "cancelled");
        foreach($states as $name){
            $state = new InvoiceState();
            $state->setName($name);
            $manager->persist($state);        
            $manager->flush();
        }
    }
}