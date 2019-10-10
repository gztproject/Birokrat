<?php

namespace App\Entity\Konto;

use App\Entity\User\User;
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

    /**
     * 
     * @param CreateKontoClassCommand $c
     * @param User $user
     */
    public function __construct(CreateKontoClassCommand $c, User $user)
    {
    	parent::__construct($user);
    	$this->categories = new ArrayCollection();
    	$this->name = $c->name;
    	$this->number = $c->number;
    	$this->description = $c->description;
    }
    
    /**
     * 
     * @param UpdateKontoClassCommand $c
     * @param User $user
     * @return KontoClass
     */
    public function update(UpdateKontoClassCommand $c, User $user): KontoClass
    {
    	parent::updateBase($user);
    	$this->name = $c->name;
    	$this->number = $c->number;
    	$this->description = $c->description;
    	return $this;
    }
    
    public function updateDebit(float $sum, User $user)
    {
    	parent::updateBase($user);
    	$this->debit += $sum;
    }
    
    public function updateCredit(float $sum, User $user)
    {
    	parent::updateBase($user);
    	$this->credit += $sum;
    }
    
    /**
     * 
     * @param UpdateKontoCategoryCommand $c
     * @param KontoCategory $category
     * @param User $user
     * @throws \Exception
     * @return KontoCategory
     */
    public function updateCategory(UpdateKontoCategoryCommand $c, KontoCategory $category, User $user): KontoCategory
    {
    	if(!$this->categories->contains($category))
    		throw new \Exception("Can't update category that's not mine.");
    	return $category->update($c, $user);
    }
    
    /**
     * 
     * @param CreateKontoCategoryCommand $c
     * @param User $user
     * @throws \Exception
     * @return KontoCategory
     */
    public function createCategory(CreateKontoCategoryCommand $c, User $user): KontoCategory
    {
    	$kontoCategory = new KontoCategory($c, $this, $user);
    	
    	foreach($this->getCategories() as $k)
    	{
    		if ($k->getFullNumber() == $kontoCategory->getFullNumber())
    			throw new \Exception("KontoCategory with this number already exists");
    	}
    	$this->categories[] = $kontoCategory;
    	
    	return $kontoCategory;
    }
    
    /**
     * 
     * @param KontoCategory $category
     * @param User $user
     * @return KontoCategory|NULL
     */
    public function removeCategory(KontoCategory $category, User $user): ?KontoCategory
    {
    	if ($this->categories->contains($category)) {
    		$this->categories->removeElement($category);
    		// set the owning side to null (unless already changed)
    		if ($category->getClass() === $this) {
    			return $category->removeClass($this, $user);
    		}
    	}
    	return null;
    }

    /**
     * @return Collection|KontoCategory[]
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }    
}
