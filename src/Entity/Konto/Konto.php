<?php

namespace App\Entity\Konto;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoRepository")
 */
class Konto extends KontoBase
{    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\KontoCategory", inversedBy="kontos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $category;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive;    

    public function getCategory(): ?KontoCategory
    {
        return $this->category;
    }

    public function setCategory(?KontoCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }
    
    public function getFullNumber(): string
    {
        $category = $this->getCategory();
        $class = $category->getClass();
        
        return (string)$class->getNumber().substr((string)$category->getNumber(), -1).substr((string)$this->getNumber(),-1);
    }
    
    public function getNumberAndName(): string
    {
        return (string)$this->getFullNumber()." - ".$this->name;
    }    
}
