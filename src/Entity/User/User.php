<?php

namespace App\Entity\User;

use App\Entity\Settings\UserSettings;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Base\Base;
use App\Entity\Organization\Organization;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;


/**
 * @ORM\Table(name="app_users")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User extends Base implements UserInterface, \Serializable
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $username;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $firstName;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $lastName;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $password;
    
    /**
     * @ORM\Column(type="array")
     */
    private $roles;
    
    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     * @Assert\Email()
     */
    private $email;
    
    /**
     * @ORM\Column(type="string", length=20)
     * @ORM\JoinColumn(nullable=true)
     */
    private $mobile;
    
    /**
     * @ORM\Column(type="string", length=20)
     * @ORM\JoinColumn(nullable=true)
     */
    private $phone;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive;
    
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Organization\Organization", mappedBy="users")
     */
    private $organizations;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Settings\UserSettings", mappedBy="user" , cascade={"persist", "remove"})
     */
    private $userSettings;
    
    private function __construct(CreateUserCommand $c, User $user, UserPasswordEncoderInterface $passwordEncoder)
    {
    	parent::__construct($user);
        $this->isActive = true;
        $this->organizations = new ArrayCollection();
        
        $this->username = $c->username;
        $this->email = $c->email;
        $this->firstName = $c->firstName;
        $this->lastName = $c->lastName;
        $this->mobile = $c->mobile;
        $this->phone = $c->phone;
        $this->password = $passwordEncoder->encodePassword($this, $c->password);
        $this->roles = array($c->isRoleAdmin?'ROLE_ADMIN':'ROLE_USER');
                
        return $this;
    }    

    /*
     * Private stuff, mostly helper functions
     */
        
    private function addOrganization(Organization $organization): self
    {
    	if (!$this->organizations->contains($organization)) {
    		$this->organizations[] = $organization;
    		$organization->addUser($this);
    	}
    	
    	return $this;
    }
    
    private function removeOrganization(Organization $organization): self
    {
    	if ($this->organizations->contains($organization)) {
    		$this->organizations->removeElement($organization);
    		$organization->removeUser($this);
    	}
    	
    	return $this;
    }
    
    private function setUserSettings(?UserSettings $userSettings): self
    {
    	$this->userSettings = $userSettings;
    	
    	// set (or unset) the owning side of the relation if necessary
    	$newUser = $userSettings === null ? null : $this;
    	if ($newUser !== $userSettings->getUser()) {
    		$userSettings->setOrganization($newUser);
    	}
    	
    	return $this;
    }
    
    /*
     * *****************************************************************
     * Entity creators (everything should be created by a user)
     * *****************************************************************
     */
    
    /**
     * Creates a new User.
     * @param CreateUserCommand $c
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return User New user
     */
    public function createUser(CreateUserCommand $c, UserPasswordEncoderInterface $passwordEncoder): User
    {
    	return new User($c, $this, $passwordEncoder);
    }
    
    /**
     * Creates a new invoice.
     * @param CreateInvoiceCommand $c
     * @return Invoice
     */
    public function createInvoice(CreateInvoiceCommand $c): Invoice
    {
    	return new Invoice($c, $this);
    }
    
    /**
     * Creates a new travelExpense.
     * @param CreateTravelExpenseCommand $c
     * @return TravelExpense
     */
    public function createTravelExpense(CreateTravelExpenseCommand $c): TravelExpense
    {
    	return new TravelExpense($c, $this);
    }
    
    
    /*
     * ***************************************************************
     * Getters (Needed by Symfony)
     * ***************************************************************
     */
    
    public function getUsername(): ?string
    {
        return $this->username;        
    }
        
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }   
    
    public function getLastName(): ?string
    {
        return $this->lastName;
    }    
        
    public function getFullname(): ?string
    {
        return $this->firstName . " " . $this->lastName;
    }
    
    public function getPlainPassword(): ?string
    {
        return null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    
    public function getEmail(): ?string
    {
        return $this->email;
    }
    
    public function getMobile(): ?string
    {
        return $this->mobile;
    }
    
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }
    
    public function getRoles(): array
    {
        if($this->roles == null)            
            return array('ROLE_USER');
        return $this->roles;
    }
    
    public function isEnabled()
    {
        return $this->isActive;
    }
        
    public function getIsRoleAdmin(): ?bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles()) || $this->isRoleAdmin;
    }
    
    /**
     * @return Collection|Organization[]
     */
    public function getOrganizations(): Collection
    {
        return $this->organizations;
    }
    

    public function getUserSettings(): ?UserSettings
    {
        return $this->userSettings;
    }

    
    
    /*
     * ********************************************************************
     * Stuff needed by UserInterface and Serializable
     * ********************************************************************
     */
    
     /** @see \Serializable::serialize() */
    public function serialize()
    {
    	return serialize(array(
    			$this->id,
    			$this->username,
    			$this->password,
    			$this->isActive,
    	));
    }
    
    /** @see \Serializable::unserialize() */
    public function unserialize($serialized)
    {
    	list (
    			$this->id,
    			$this->username,
    			$this->password,
    			$this->isActive,
    			) = unserialize($serialized);
    }
        
    public function eraseCredentials()
    {
    	return;
    }
    
    public function getSalt()
    {
    	return;
    }
}
