<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoRepository")
 */
class Konto
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $number;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\KontoCategory", inversedBy="kontos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $category;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

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
