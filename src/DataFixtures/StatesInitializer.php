<?php

namespace App\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\InvoiceState;
use App\Entity\TravelExpenseState;

class StatesInitializer
{
    public function generate(ObjectManager $manager)
    {
        $this->generateInvoiceStates($manager);
        $this->generateTravelExpenseStates($manager);
    }
    
    private function generateInvoiceStates($manager)
    {
        $states = array("new", "draft", "issued", "paid", "cancelled");
        foreach($states as $name){
            $state = new InvoiceState();
            $state->setName($name);
            $manager->persist($state);
            $manager->flush();
        }
    }
    
    private function generateTravelExpenseStates($manager)
    {
        $states = array("new", "draft", "submitted", "paid", "cancelled");
        foreach($states as $name){
            $state = new TravelExpenseState();
            $state->setName($name);
            $manager->persist($state);
            $manager->flush();
        }
    }
}