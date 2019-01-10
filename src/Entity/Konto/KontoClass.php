<?php

namespace App\Entity\Konto;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\KontoClassRepository")
 */
class KontoClass extends KontoBase
{
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Konto\KontoCategory", mappedBy="class")
     */
    private $categories;

    public function __construct()
    {
        $this->kontoCategories = new ArrayCollection();
    }    

    /**
     * @return Collection|KontoCategory[]
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(KontoCategory $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories[] = $category;
            $category->setClass($this);
        }

        return $this;
    }

    public function removeCategory(KontoCategory $category): self
    {
        if ($this->categories->contains($category)) {
            $this->categories->removeElement($category);
            // set the owning side to null (unless already changed)
            if ($category->getClass() === $this) {
                $category->setClass(null);
            }
        }

        return $this;
    }   
    
}
