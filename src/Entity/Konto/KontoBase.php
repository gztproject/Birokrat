<?php

namespace App\Entity\Konto;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;

abstract class KontoBase extends AggregateBase
{
    /**
     * @ORM\Column(type="integer")
     */
    protected $number;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;  
    
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $description;
        
    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }    
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        
        return $this;
    }
    
    public function getNumberAndName(): string
    {
        return (string)$this->number." - ".$this->name;
    }
}
