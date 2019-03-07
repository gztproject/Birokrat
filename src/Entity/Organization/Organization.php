<?php

namespace App\Entity\Organization;

use App\Entity\Settings\OrganizationSettings;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\User\User;
use App\Entity\Geography\Address;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Organization\OrganizationRepository")
 */
class Organization extends LegalEntityBase
{
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User\User", inversedBy="organizations")
     */
    private $users;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Settings\OrganizationSettings", mappedBy="organization", cascade={"persist", "remove"})
     */
    private $organizationSettings;

    public function __construct()
    {
    	$this->users = new ArrayCollection();
    }
    
    public function initOrganization(string $code, string $name, int $taxNumber, bool $taxable, Address $address, OrganizationSettings $organizationSettings,
    		string $shortName = null, string $www = null, string $email = null, string $phone = null, string $mobile = null, string $accountNumber = null, string $bic = null)
    {
    	parent::init($code, $name, $taxNumber, $taxable, $address, $shortName, $www, $email, $phone, $mobile, $accountNumber, $bic);    	
    	
    	$this->setOrganizationSettings($organizationSettings);    	
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    private function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
        }

        return $this;
    }

    private function removeUser(User $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
        }

        return $this;
    }    

    public function getOrganizationSettings(): ?OrganizationSettings
    {
        return $this->organizationSettings;
    }

    private function setOrganizationSettings(?OrganizationSettings $organizationSettings): self
    {
        $this->organizationSettings = $organizationSettings;

        //ToDo: Remove orphans.
        // set (or unset) the owning side of the relation if necessary
        $newOrganization = $organizationSettings === null ? null : $this;
        if ($newOrganization !== $organizationSettings->getOrganization()) {
            $organizationSettings->setOrganization($newOrganization);
        }

        return $this;
    }    
}
