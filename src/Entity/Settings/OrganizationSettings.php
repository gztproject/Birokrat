<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Settings\OrganizationSettingsRepository")
 */
class OrganizationSettings extends Base
{
        
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $invoicePrefix;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $defaultPaymentDueIn;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $referenceModel;
    
    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Settings\KontoPreference", cascade={"persist", "remove"})
     */
    private $kontoPreference;
    
    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Organization\Organization", inversedBy="organizationSettings", cascade={"persist", "remove"})
     */
    private $organization;

    /**
     * 
     * @param CreateOrganizationSettingsCommand $c
     * @param CreateKontoPreferenceCommand $ck
     * @param Organization $organization
     * @param User $user
     */
    public function __construct(CreateOrganizationSettingsCommand $c, CreateKontoPreferenceCommand $ck, Organization $organization, User $user)
    {
    	parent::__construct($user);
    	$this->organization = $organization;
    	$this->defaultPaymentDueIn = $c->defaultPaymentDueIn;
    	$this->invoicePrefix = $c->invoicePrefix;
    	$this->referenceModel = $c->referenceModel;
    	$this->kontoPreference = new KontoPreference($ck, $user);
    }
    
    /**
     * 
     * @param UpdateOrganizationSettingsCommand $c
     * @param ?UpdateKontoPreferenceCommand $ck Pass null if you don't need to update it.
     * @param User $user
     * @throws \Exception
     */     
    public function update(UpdateOrganizationSettingsCommand $c, ?UpdateKontoPreferenceCommand $ck, User $user): OrganizationSettings
    {    	
    	parent::updateBase($user);
    	
    	$this->defaultPaymentDueIn = $c->defaultPaymentDueIn;
    	$this->invoicePrefix = $c->invoicePrefix;
    	$this->referenceModel = $c->referenceModel;
    	if($ck != null)
    	{
    		if($ck->id != $this->kontoPreference->getId())
	    		throw new \Exception("Can't update someone else's preferences");
	    		$this->kontoPreference->update($ck, $user);
    	}
    	return $this;
    }
    
    public function getInvoicePrefix(): ?string
    {
    	return $this->invoicePrefix;
    }
    
    
    public function getDefaultPaymentDueIn(): ?int
    {
        return $this->defaultPaymentDueIn;
    }

    
    public function getReferenceModel(): ?string
    {
        return $this->referenceModel;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    } 
    
    public function getKontoPreference(): KontoPreference
    {
    	return $this->kontoPreference;
    }
    
    
}
