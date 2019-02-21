<?php

namespace App\Entity\Konto;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoCategoryRepository")
 */
class KontoCategory extends KontoBase
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\KontoClass", inversedBy="categories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $class;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Konto\Konto", mappedBy="category")
     */
    private $kontos;

    public function __construct()
    {
        $this->kontos = new ArrayCollection();
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
