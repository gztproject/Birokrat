<?php

namespace App\Entity\TravelExpense;

use App\Entity\Base\State;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpenseStateRepository")
 */
class TravelExpenseState extends State
{
    
}
