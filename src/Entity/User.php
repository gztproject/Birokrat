<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Ramsey\Uuid\Uuid;


/**
 * @ORM\Table(name="app_users")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements AdvancedUserInterface, \Serializable
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="uuid")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $username;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $surname;
    
    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=4096)
     */
    private $plainPassword;

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
     * @ORM\Column(type="boolean")
     */
    private $isActive;
    
    /**
     * @Assert\NotNull()
     */
    private $isRoleAdmin;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Organization", mappedBy="users")
     */
    private $organizations;
    
    public function __construct()
    {
        $this->isActive = true;
        $this->isRoleAdmin = false;
        $this->organizations = new ArrayCollection();
        
        // not needed with bcrypt
        // $this->salt = md5(uniqid('', true));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

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
    
    public function getSurname(): ?string
    {
        return $this->surname;
    }
    
    public function setSurname(string $name): self
    {
        $this->surname = $name;
        
        return $this;
    }
    
    public function getFullname(): ?string
    {
        return $this->name . " " . $this->surname;
    }
    
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }
    
    public function setPlainPassword($password): self
    {
        $this->plainPassword = $password;
        
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }
    
    public function getSalt()
    {
        //not needed with bcrypt
        return null;
    }
    
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        
        return $this;
    }
    
    public function getRoles(): array
    {
        if($this->roles == null)            
            return array('ROLE_USER');
        return $this->roles;
    }
    
    public function eraseCredentials()
    {
        $this->plainPassword = null;
    }
    
    public function isAccountNonExpired()
    {
        return true;
    }
    
    public function isAccountNonLocked()
    {
        return true;
    }
    
    public function isCredentialsNonExpired()
    {
        return true;
    }
    
    public function isEnabled()
    {
        return $this->isActive;
    }
        
    public function getIsRoleAdmin(): ?bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles()) || $this->isRoleAdmin;
    }
    
    public function setIsRoleAdmin(bool $isRoleAdmin): self
    {
        $this->isRoleAdmin = $isRoleAdmin;
        $this->roles = array($isRoleAdmin?'ROLE_ADMIN':'ROLE_USER');
        
        return $this;
    }
       
    /** @see \Serializable::serialize() */
    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
            $this->isActive,
            //not needed with bcrypt
            // $this->salt,
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
            //not needed with bcrypt
            // $this->salt
            ) = unserialize($serialized);
    }

    /**
     * @return Collection|Organization[]
     */
    public function getOrganizations(): Collection
    {
        return $this->organizations;
    }

    public function addOrganization(Organization $organization): self
    {
        if (!$this->organizations->contains($organization)) {
            $this->organizations[] = $organization;
            $organization->addUser($this);
        }

        return $this;
    }

    public function removeOrganization(Organization $organization): self
    {
        if ($this->organizations->contains($organization)) {
            $this->organizations->removeElement($organization);
            $organization->removeUser($this);
        }

        return $this;
    }
}
