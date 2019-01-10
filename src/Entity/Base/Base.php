<?php

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

abstract class Base
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }    
}
