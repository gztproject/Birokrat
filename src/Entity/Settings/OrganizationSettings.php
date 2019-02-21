<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\Organization\Organization;
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
     * @ORM\OneToOne(targetEntity="App\Entity\Organization\Organization", inversedBy="organizationSettings", cascade={"persist", "remove"})
     */
    private $organization;

    
    
    public function getInvoicePrefix(): ?string
    {
    	return $this->invoicePrefix;
    }
    
    public function setInvoicePrefix(?string $invoicePrefix): self
    {
    	$this->invoicePrefix = $invoicePrefix;
    	
    	return $this;
    }

    public function getDefaultPaymentDueIn(): ?int
    {
        return $this->defaultPaymentDueIn;
    }

    public function setDefaultPaymentDueIn(?int $defaultPaymentDueIn): self
    {
        $this->defaultPaymentDueIn = $defaultPaymentDueIn;

        return $this;
    }

    public function getReferenceModel(): ?string
    {
        return $this->referenceModel;
    }

    public function setReferenceModel(?string $referenceModel): self
    {
        $this->referenceModel = $referenceModel;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }
}
