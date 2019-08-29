<?php

namespace App\Entity\User;

use App\Entity\Settings\CreateUserSettingsCommand;
use App\Entity\Settings\UpdateUserSettingsCommand;
use App\Entity\Settings\UserSettings;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Base\AggregateBase;
use App\Entity\Organization\Client;
use App\Entity\Organization\CreateClientCommand;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\Organization;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\Geography\Country;
use App\Entity\Geography\CreateCountryCommand;


/**
 * @ORM\Table(name="app_users")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User extends AggregateBase implements UserInterface, \Serializable
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
     * @ORM\Column(type="string", length=20, nullable=true)
     * @ORM\JoinColumn(nullable=true)
     */
    private $mobile;
    
    /**
     * @ORM\Column(type="string", length=20, nullable=true)
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
    
    /**
     * @ORM\Column(type="string")
     */
    private $signatureFilename;
    
    /**
     * 
     * @param CreateUserCommand $c
     * @param User $user
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return \App\Entity\User\User
     */
    public function __construct(CreateUserCommand $c, User $user, UserPasswordEncoderInterface $passwordEncoder)
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
        $this->signatureFilename = $c->signatureFilename;
        
        $this->checkPasswordRequirements($c->password);
        $this->password = $passwordEncoder->encodePassword($this, $c->password);
        
        $this->roles = array($c->isRoleAdmin?'ROLE_ADMIN':'ROLE_USER');
                
        return $this;
    }  
    
    public function update(UpdateUserCommand $c, User $user, UserPasswordEncoderInterface $passwordEncoder)
    {
    	parent::updateBase($user);
    	if($c->username != null && $c->username != $this->username) $this->username = $c->username;
    	if($c->email != null && $c->email != $this->email) $this->email = $c->email;
    	if($c->firstName != null && $c->firstName != $this->firstName) $this->firstName = $c->firstName;
    	if($c->lastName != null && $c->lastName != $this->lastName) $this->lastName = $c->lastName;
    	if($c->mobile != null && $c->mobile != $this->mobile) $this->mobile = $c->mobile;
    	if($c->phone != null && $c->phone != $this->phone) $this->phone = $c->phone;
    	if($c->signatureFilename != null && $c->signatureFilename != $this->signatureFilename) $this->signatureFilename = $c->signatureFilename;
    	
    	if(strlen($c->password) != 0) 
    	{
    		$this->checkPasswordRequirements($c->password);
    		if($passwordEncoder->isPasswordValid($this, $c->oldPassword))
    			$this->password = $passwordEncoder->encodePassword($this, $c->password);
    		else throw new \Exception("Old password is incorrect.");
    	}
    	
    }
    
    private function checkPasswordRequirements(string $password)
    {
    	//ToDo: Read this from application settings?
    	$minPasswordLength = 4;
    	$passwordMustHaveNumbers = true;
    	$passwordMustHaveSpecials = true;
    	
    	if(strlen($password) < $minPasswordLength)
    		throw new \Exception("The password is too short.");
    	
    	if($passwordMustHaveNumbers && preg_match('/\\d/', $password) === 0)
    		throw new \Exception("The password must contain at least one number.");
    	    	
    	if($passwordMustHaveSpecials && preg_match('/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/', $password) === 0)
    		throw new \Exception("The password must contain at least one special character.");
    }
    
    /**
     * 
     * @param CreateUserSettingsCommand $c
     * @param User $user
     * @return UserSettings
     */
    public function CreateUserSettings(CreateUserSettingsCommand $c, User $user): UserSettings
    {
    	parent::updateBase($user);
    	$this->userSettings = new UserSettings($c, $this, $user);
    	return $this->userSettings;
    }
    
    /**
     * 
     * @param UpdateUserSettingsCommand $c
     * @param UserSettings $settings
     * @param User $user
     * @throws \Exception
     * @return UserSettings
     */
    public function updateUserSettings(UpdateUserSettingsCommand $c, UserSettings $settings, User $user): UserSettings
    {
    	if($this->userSettings != $settings)
    		throw new \Exception("Can't update settings that are not mine.");
    		parent::updateBase($user);
    		$this->userSettings->update($c, $user);
    		return $this->userSettings;
    }
    
    /**
     * 
     * @param Organization $organization
     * @param User $user
     * @return self
     */
    public function addOrganization(Organization $organization, User $user): self
    {
    	parent::updateBase($user);
    	if (!$this->organizations->contains($organization)) {
    		$this->organizations[] = $organization;
    		$organization->addUser($this, $user);
    	}
    	
    	return $this;
    }
    
    /**
     * 
     * @param Organization $organization
     * @param User $user
     * @return self
     */
    public function removeOrganization(Organization $organization, User $user): self
    {
    	parent::updateBase($user);
    	if ($this->organizations->contains($organization)) {
    		$this->organizations->removeElement($organization);
    		$organization->removeUser($this, $user);
    	}
    	
    	return $this;
    }    
    
    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo(object $to): object
    {
    	if ($to instanceof UpdateUserCommand)
    	{
    		$reflect = new \ReflectionClass($this);
    		$props  = $reflect->getProperties();
    		foreach($props as $prop)
    		{
    			$name = $prop->getName();
    			if(property_exists($to, $name))
    			{
    				$to->$name = $this->$name;
    			}
    		}
    		$to->isRoleAdmin = $this->getIsRoleAdmin();
    		$to->password = "";
    	}
    	else
    	{
    		throw(new \Exception('cant map ' . get_class($this) . ' to ' . get_class($to)));    		
    	}
    	return $to;
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
    
    /**
     * 
     * @param CreateCountryCommand $c
     * @return Country
     */
    public function createCountry(CreateCountryCommand $c): Country
    {
    	return new Country($c, $this);
    }
    
    /**
     * 
     * @param CreateOrganizationCommand $c
     * @return Organization
     */
    public function createOrganization(CreateOrganizationCommand $c): Organization
    {
    	return new Organization($c, $this);
    }
    
    /**
     * 
     * @param CreateClientCommand $c
     * @return Client
     */
    public function createClient(CreateClientCommand $c): Client
    {
    	return new Client($c, $this);
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
        
    public function getIsRoleAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles());
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
    
    public function getSignatureFilename(): ?string
    {
    	return $this->signatureFilename;
    }
    
    public function __toString(): string
    {
    	return $this->username.": ".$this->getFullname();
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
