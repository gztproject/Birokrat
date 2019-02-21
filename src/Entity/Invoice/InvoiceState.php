<?php

namespace App\Entity\Invoice;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\State;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InvoiceStateRepository")
 */
class InvoiceState extends State
{
    
}
