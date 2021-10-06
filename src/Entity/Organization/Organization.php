<?php

namespace App\Entity\Organization;

use App\Entity\Settings\OrganizationSettings;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;
use App\Entity\Geography\Address;
use App\Entity\Settings\CreateOrganizationSettingsCommand;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\Settings\KontoPreference;
use App\Entity\Settings\CreateKontoPreferenceCommand;

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
    
    /**
     * 
     * @param CreateOrganizationCommand $c
     * @param User $user
     */
    public function __construct(CreateOrganizationCommand $c, User $user)
    {
    	parent::__construct($user);
    	$this->code = $c->code;
    	$this->name = $c->name;
    	$this->taxNumber = $c->taxNumber;
    	$this->taxable = $c->taxable;
    	$this->address = $c->address;
    	if($c->shortName)
    		$this->shortName = $c->shortName;
    	if($c->www)
    		$this->www = $c->www;
    	if($c->email)
    		$this->email = $c->email;
    	if($c->phone)
    		$this->phone = $c->phone;
    	if($c->mobile)
    		$this->mobile = $c->mobile;
    	if($c->accountNumber)
    		$this->accountNumber = $c->accountNumber;
    	if($c->bic)
    		$this->bic = $c->bic;    
    	
    	$this->organizationSettings = $this->CreateOrganizationSettings(new CreateOrganizationSettingsCommand(), $user);
    }
    
    /**
     * 
     * @param UpdateOrganizationCommand $c
     * @param User $user
     * @return Organization
     */
    public function update (UpdateOrganizationCommand $c, User $user): Organization
    {
    	//Should we make a copy and deactivate old one not to mix up old stuff?
    	parent::updateBase($user);
    	if($c->name != null && $c->name != $this->name)
    		$this->name = $c->name;
    	if($c->taxNumber != null && $c->taxNumber != $this->taxNumber)
    		$this->taxNumber = $c->taxNumber;
    	if($c->taxable != null && $c->taxable != $this->taxable)
    		$this->taxable = $c->taxable;
    	if($c->address != null && $c->address != $this->address)
    		$this->address = $c->address;
    	if($c->shortName != null && $c->shortName != $this->shortName)
    		$this->shortName = $c->shortName;
    	if($c->www != null && $c->www != $this->www)
    		$this->www = $c->www;
    	if($c->email != null && $c->email != $this->email)
    		$this->email = $c->email;
    	if($c->phone != null && $c->phone != $this->phone)
    		$this->phone = $c->phone;
    	if($c->mobile != null && $c->mobile != $this->mobile)
    		$this->mobile = $c->mobile;
    	if($c->accountNumber != null && $c->accountNumber != $this->accountNumber)
    		$this->accountNumber = $c->accountNumber;
    	if($c->bic != null && $c->bic != $this->bic)
    		$this->bic = $c->bic;
    	
    	//ToDo: Updating OrganizationSettings
    	$ck = null;
    	if($ck != null)
    	{
    		$this->updateOrganizationSettings($ck, $user);
    	}
    	
    	return $this;
    }
     
    /**
     * 
     * @param CreateOrganizationSettingsCommand $c
     * @param User $user
     * @return OrganizationSettings
     */
    public function CreateOrganizationSettings(CreateOrganizationSettingsCommand $c, User $user): OrganizationSettings
    {
    	parent::updateBase($user);
    	$this->organizationSettings = new OrganizationSettings($c, $this, $user);
    	return $this->organizationSettings;
    }
    
    /**
     * 
     * @param UpdateOrganizationSettingsCommand $c
     * @param OrganizationSettings $settings
     * @param User $user
     * @throws \Exception
     * @return OrganizationSettings
     */
    public function updateOrganizationSettings(UpdateOrganizationSettingsCommand $c, User $user): OrganizationSettings
    {
    	if($this->organizationSettings->getOrganization() != $this)
    		throw new \Exception("Can't update settings that are not mine.");
    	parent::updateBase($user);
    	$this->organizationSettings->update($c, $user);
    	return $this->organizationSettings;    	
    }
    
    /**
     * 
     * @param User $user
     * @param User $editor
     * @return self
     */
    public function addUser(User $user, User $editor): self
    {    	
    	if (!$this->users->contains($user)) {
    		parent::updateBase($editor);
    		$this->users[] = $user;
    	}
    	
    	return $this;
    }
    
    /**
     * 
     * @param User $user
     * @param User $editor
     * @return self
     */
    public function removeUser(User $user, User $editor): self
    {
    	if ($this->users->contains($user)) {
    		parent::updateBase($editor);
    		$this->users->removeElement($user);
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
    	if ($to instanceof UpdateOrganizationCommand) 
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
    	} 
    	else 
    	{
    		throw(new \Exception('cant map ' . get_class($this) . ' to ' . get_class($to)));    		
    	}
    	return $to;
    }
    

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }     

    public function getOrganizationSettings(): ?OrganizationSettings
    {
        return $this->organizationSettings;
    }
        
    public function __toString() : string
    {
        $sb = "";
        $sb .= $this->code . ": " . $this->name;
        return $sb;
    }
}
