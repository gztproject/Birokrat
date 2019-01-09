<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoCategoryRepository")
 */
class KontoCategory
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
     * @ORM\ManyToOne(targetEntity="App\Entity\KontoClass", inversedBy="categories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $class;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Konto", mappedBy="category")
     */
    private $kontos;

    public function __construct()
    {
        $this->kontos = new ArrayCollection();
    }

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

    public function getClass(): ?KontoClass
    {
        return $this->class;
    }

    public function setClass(?KontoClass $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return Collection|Konto[]
     */
    public function getKontos(): Collection
    {
        return $this->kontos;
    }

    public function addKonto(Konto $konto): self
    {
        if (!$this->kontos->contains($konto)) {
            $this->kontos[] = $konto;
            $konto->setKontoCategory($this);
        }

        return $this;
    }

    public function removeKonto(Konto $konto): self
    {
        if ($this->kontos->contains($konto)) {
            $this->kontos->removeElement($konto);
            // set the owning side to null (unless already changed)
            if ($konto->getKontoCategory() === $this) {
                $konto->setKontoCategory(null);
            }
        }

        return $this;
    }
    
    public function getFullNumber(): string
    {
        $class = $this->getClass();
        
        return (string)$class->getNumber().substr((string)$this->getNumber(), -1);
    }
    
    public function getNumberAndName(): string
    {
        return (string)$this->getFullNumber()." - ".$this->name;
    }
}
