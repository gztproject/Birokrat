<?php

namespace App\Entity\Konto;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\User\User;

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
    
    /**
     * 
     * @param CreateKontoCommand $c
     * @param KontoCategory $category
     * @param User $user
     */
    public function __construct(CreateKontoCommand $c, KontoCategory $category, User $user)
    {
    	parent::__construct($user);
    	$this->name = $c->name;
    	$this->number = $c->number;
    	$this->description = $c->description;
    	$this->isActive = $c->isActive;
    	$this->category = $category;
    }
    
    /**
     * 
     * @param UpdateKontoCommand $c
     * @param User $user
     * @return Konto
     * @throws \Exception
     */
    public function update(UpdateKontoCommand $c, User $user): Konto
    {
    	if(!$this->isActive)
    		throw new \Exception("Can't update an inactive konto.");
    	parent::updateBase($user);
    	$this->name = $c->name;
    	$this->number = $c->number;
    	$this->description = $c->description;
    	return $this;
    }
    
    /**
     * 
     * @param User $user
     * @throws \Exception
     */
    public function activate(User $user)
    {
    	if($this->isActive)
    		throw new \Exception("Can't activate a konto that's already active.");
    	parent::updateBase($user);
    	$this->isActive = true;
    }
    
    /**
     * 
     * @param User $user
     * @throws \Exception
     */
    public function deactivate(User $user)
    {
    	if(!$this->isActive)
    		throw new \Exception("Can't deactivate a konto that's already deactivated.");
    	parent::updateBase($user);
    	$this->isActive = false;
    }
    
    /**
     * 
     * @param KontoCategory $category
     * @param User $user
     * @throws \Exception
     * @return Konto
     */
    public function removeCategory(KontoCategory $category, User $user): Konto
    {
    	if($this->category != $category)
    		throw new \Exception("Can't remove category other than itself.");
    		parent::updateBase($user);
    		$this->category = null;
    		return $this;
    }
    
    

    public function getCategory(): ?KontoCategory
    {
        return $this->category;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
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
