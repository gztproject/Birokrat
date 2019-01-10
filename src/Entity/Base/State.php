<?php

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;

abstract class State extends Base
{    

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected $name;   

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }     
}
