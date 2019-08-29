<?php

namespace App\Entity\Konto;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

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

    /**
     * 
     * @param CreateKontoCategoryCommand $c
     * @param KontoClass $class
     * @param User $user
     */
    public function __construct(CreateKontoCategoryCommand $c, KontoClass $class, User $user)
    {
    	parent::__construct($user);
        $this->kontos = new ArrayCollection();
        $this->name = $c->name;
        $this->number = $c->number;
        $this->description = $c->description;
        $this->class = $class;
    }
    
    /**
     * 
     * @param UpdateKontoCategoryCommand $c
     * @param User $user
     * @return KontoCategory
     */
    public function update(UpdateKontoCategoryCommand $c, User $user): KontoCategory
    {
    	parent::updateBase($user);
    	$this->name = $c->name;
    	$this->number = $c->number;
    	$this->description = $c->description;
    	return $this;
    }
    
    /**
     * 
     * @param UpdateKontoCommand $c
     * @param Konto $konto
     * @param User $user
     * @throws \Exception
     * @return Konto
     */
    public function updateKonto(UpdateKontoCommand $c, Konto $konto, User $user): Konto
    {
    	if(!$this->kontos->contains($konto))
    		throw new \Exception("Can't update konto that's not mine.");
    		return $konto->update($c, $user);
    }
    
    /**
     * 
     * @param CreateKontoCommand $c
     * @param User $user
     * @throws \Exception
     * @return Konto
     */
    public function createKonto(CreateKontoCommand $c, User $user): Konto
    {
    	$konto = new Konto($c, $this, $user);
    	
    	foreach($this->getKontos() as $k)
    	{
    		if ($k->getFullNumber() == $konto->getFullNumber())
    			throw new \Exception("Konto with this number already exists");
    	}
    	$this->kontos[] = $konto;
    	
    	return $konto;
    }
    
    /**
     * 
     * @param Konto $konto
     * @param User $user
     * @return Konto|NULL
     */
    public function removeKonto(Konto $konto, User $user): ?Konto
    {
    	if ($this->kontos->contains($konto)) {
    		$this->kontos->removeElement($konto);
    		// set the owning side to null (unless already changed)
    		if ($konto->getCategory() === $this) {
    			return $konto->removeCategory($this, $user);
    		}
    	}
    	return null;
    }
    
    /**
     * 
     * @param KontoClass $class
     * @param User $user
     * @throws \Exception
     * @return KontoCategory
     */
    public function removeClass(KontoClass $class, User $user): KontoCategory
    {
    	if($this->class != $class)
    		throw new \Exception("Can't remove class other than itself.");
    		parent::updateBase($user);
    		$this->class = null;
    		return $this;
    }
    
    
    

    public function getClass(): ?KontoClass
    {
        return $this->class;
    }
    
    /**
     * @return Collection|Konto[]
     */
    public function getKontos(): Collection
    {
        return $this->kontos;
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
