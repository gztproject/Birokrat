<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoClassRepository")
 */
class KontoClass
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
     * @ORM\OneToMany(targetEntity="App\Entity\KontoCategory", mappedBy="Class")
     */
    private $kontoCategories;

    public function __construct()
    {
        $this->kontoCategories = new ArrayCollection();
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

    /**
     * @return Collection|KontoCategory[]
     */
    public function getKontoCategories(): Collection
    {
        return $this->kontoCategories;
    }

    public function addKontoCategory(KontoCategory $kontoCategory): self
    {
        if (!$this->kontoCategories->contains($kontoCategory)) {
            $this->kontoCategories[] = $kontoCategory;
            $kontoCategory->setClass($this);
        }

        return $this;
    }

    public function removeKontoCategory(KontoCategory $kontoCategory): self
    {
        if ($this->kontoCategories->contains($kontoCategory)) {
            $this->kontoCategories->removeElement($kontoCategory);
            // set the owning side to null (unless already changed)
            if ($kontoCategory->getClass() === $this) {
                $kontoCategory->setClass(null);
            }
        }

        return $this;
    }
}
