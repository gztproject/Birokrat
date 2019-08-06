<?php

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use App\Entity\User\User;
use DateTime;

abstract class Base
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $id;
    
    /**
     * @ORM\Column(type="datetime")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $createdOn;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $createdBy;
    
    /**
     * @ORM\Column(type="datetime")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $updatedOn;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User\User")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $updatedBy;
    
    /**
     * Sets the creating user and datetime for the new entity. 
     * @param User $user User that is creating the entity. (@see methods in User->create...)
     * @return Uuid Returns the Uuid of created entity.
     */
    public function __construct(User $user)
    {
    	$this->id = Uuid::uuid1();
    	$this->createdOn = new Datetime('now');
    	//ToDo: Do some checks?
    	$this->createdBy = $user;
    	return $this->id;
    }
    
    /**
     * Sets the updating user and datetime for the entity being updated.
     * @param User $user User that is updating the entity. (@see entity's update method)
     * @return Uuid Returns the Uuid of updated entity. - we have to support null for PHPUnit tests...
     */
    protected function updateBase(User $user): ?Uuid
    {
    	$this->updatedOn = new Datetime('now');
    	$this->updatedBy = $user;
    	return $this->id;
    }

    
    /**
     * Returns the entity UUID
     * @return Uuid
     */
    public function getId(): Uuid
    {
        return $this->id;
    }    
    
    /**
     * Returns the datetime of entity's creation.
     * @return \DateTimeInterface Datetime of entity's creation
     */
    public function getCreatedOn(): \DateTimeInterface
    {
    	return $this->createdOn;
    }
    
    /**
     * Returns the datetime string of entity's creation.
     * @return string Datetime formatted as string ('j. n. Y')
     */
    public function getCreatedOnString(): string
    {
    	return $this->createdOn->format('j. n. Y');
    }
    
    /**
     * Returns the user that created the entity.
     * @return User User that created the entity.
     */
    public function getCreatedBy(): User
    {
    	return $this->createdBy;
    }
    
    /**
     * Returns the datetime of entity's last update.
     * @return \DateTimeInterface|NULL Datetime of last update or null if not updated yet.
     */
    public function getUpdatedOn(): ?\DateTimeInterface
    {
    	return $this->createdOn;
    }
    
    /**
     * Returns the datetime string of entity's last update.
     * @return string|NULL Datetime formatted as string ('j. n. Y') or null if not updated yet.
     */
    public function getUpdatedOnString(): ?string
    {
    	return $this->createdOn->format('j. n. Y');
    }
    
    /**
     * Returns the user that last updated the entity.
     * @return User|NULL User that last updated the entity or null if not updated yet.
     */
    public function getUpdatedBy(): ?User
    {
    	return $this->updatedBy;
    }
}
