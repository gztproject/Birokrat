<?php

namespace App\Entity\User;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


class CreateUserCommand
{
	public $username;
	public $firstName;
	public $lastName;
	public $plainPassword;
	public $password;
	public $roles;
	public $email;
	public $mobile;
	public $phone;
	public $isRoleAdmin;
	
	
	public function __get($name) {
		
		//echo "Get:$name";
		return $this->$name;
	}
	
	public function __set($name, $value) {
		
		//echo "Set:$name to $value";
		$this->$name = $value;
	}
	
	/*//Getters and setters needed by forms.
	
	public function __construct()
	{
		$this->organizations = new ArrayCollection();
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
	
	public function getFirstName(): ?string
	{
		return $this->firstName;
	}
	
	public function setFirstName(string $firstName): self
	{
		$this->firstName = $firstName;
		
		return $this;
	}
	
	public function getLastName(): ?string
	{
		return $this->lastName;
	}
	
	public function setLastName(string $lastName): self
	{
		$this->lastName = $lastName;
		
		return $this;
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
	
	public function getMobile(): ?string
	{
		return $this->mobile;
	}
	
	public function setMobile(string $mobile): self
	{
		$this->mobile = $mobile;
		
		return $this;
	}
	
	public function getPhone(): ?string
	{
		return $this->phone;
	}
	
	public function setPhone(string $phone): self
	{
		$this->phone = $phone;
		
		return $this;
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

	public function getOrganizations(): Collection
	{
		return $this->organizations;
	}	*/
}
