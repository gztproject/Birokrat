<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
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
     * @ORM\Column(type="float", nullable=true)
     */
    private $travelExpenseRate;
    
    /**
	 * @ORM\Column(type="boolean")
	 */
    private $autoCreatePerDiem;
    
    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $perDiemValue;
    
    /**
     * @ORM\Column(type="boolean")
     */
    private $autoCreateLunch;
    
    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $lunchValue;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $IssueInvoiceDebit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $IssueInvoiceCredit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $InvoicePaidDebit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $InvoicePaidCredit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $IncurredTravelExpenseDebit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $IncurredTravelExpenseCredit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $PaidTravelExpenseDebit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $PaidTravelExpenseCredit;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $ReceivedHomeIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $ReceivedForeignIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $ReceivedIncomingInvoiceDebit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $PaidCashIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $PaidTransactionIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $PaidIncomingInvoiceDebit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $RefundedIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $RefundedIncomingInvoiceDebit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $RejectedIncomingInvoiceCredit;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $RejectedIncomingInvoiceDebit;
    
    
    
    /**
     * @ORM\Id()
     * @ORM\OneToOne(targetEntity="App\Entity\Organization\Organization", inversedBy="organizationSettings", cascade={"persist", "remove"})
     */
    private $organization;

    /**
     * 
     * @param CreateOrganizationSettingsCommand $c
     * @param Organization $organization
     * @param User $user
     */
    public function __construct(CreateOrganizationSettingsCommand $c, Organization $organization, User $user)
    {
    	parent::__construct($user);
    	$this->organization = $organization;
    	$this->defaultPaymentDueIn = $c->defaultPaymentDueIn;
    	$this->invoicePrefix = $c->invoicePrefix;
    	$this->referenceModel = $c->referenceModel;
    	$this->IssueInvoiceCredit = $c->IssueInvoiceCredit;
    	$this->IssueInvoiceDebit = $c->IssueInvoiceDebit;
    	$this->InvoicePaidCredit = $c->InvoicePaidCredit;
    	$this->InvoicePaidDebit = $c->InvoicePaidDebit;
    	$this->IncurredTravelExpenseCredit = $c->IncurredTravelExpenseCredit;
    	$this->IncurredTravelExpenseDebit = $c->IncurredTravelExpenseDebit;
    	$this->PaidTravelExpenseCredit = $c->PaidTravelExpenseCredit;
    	$this->PaidTravelExpenseDebit = $c->PaidTravelExpenseDebit;
    	$this->ReceivedHomeIncomingInvoiceCredit = $c->ReceivedHomeIncomingInvoiceCredit;
    	$this->ReceivedForeignIncomingInvoiceCredit = $c->ReceivedForeignIncomingInvoiceCredit;
    	$this->ReceivedIncomingInvoiceDebit = $c->ReceivedIncomingInvoiceDebit;
    	$this->PaidIncomingInvoiceCredit = $c->PaidIncomingInvoiceCredit;
    	$this->PaidIncomingInvoiceDebit = $c->PaidIncomingInvoiceDebit;
    	$this->RefundedIncomingInvoiceCredit = $c->RefundedIncomingInvoiceCredit;
    	$this->RefundedIncomingInvoiceDebit = $c->RefundedIncomingInvoiceDebit;
    	$this->RejectedIncomingInvoiceCredit = $c->RejectedIncomingInvoiceCredit;
    	$this->RejectedIncomingInvoiceDebit = $c->RejectedIncomingInvoiceDebit;
    }
    
    /**
     * 
     * @param UpdateOrganizationSettingsCommand $c
     * @param ?UpdateKontoPreferenceCommand $ck Pass null if you don't need to update it.
     * @param User $user
     * @throws \Exception
     */     
    public function update(UpdateOrganizationSettingsCommand $c, User $user): OrganizationSettings
    {    	
    	//ToDo: do some checks first...
    	parent::updateBase($user);
    	
    	$this->defaultPaymentDueIn = $c->defaultPaymentDueIn;
    	$this->invoicePrefix = $c->invoicePrefix;
    	$this->referenceModel = $c->referenceModel;    	
    	
    	//ToDo: check for nulls...
    	$this->IssueInvoiceCredit = $c->IssueInvoiceCredit;
    	$this->IssueInvoiceDebit = $c->IssueInvoiceDebit;
    	$this->InvoicePaidCredit = $c->InvoicePaidCredit;
    	$this->InvoicePaidDebit = $c->InvoicePaidDebit;
    	$this->IncurredTravelExpenseCredit = $c->IncurredTravelExpenseCredit;
    	$this->IncurredTravelExpenseDebit = $c->IncurredTravelExpenseDebit;
    	$this->PaidTravelExpenseCredit = $c->PaidTravelExpenseCredit;
    	$this->PaidTravelExpenseDebit = $c->PaidTravelExpenseDebit;
    	$this->ReceivedHomeIncomingInvoiceCredit = $c->ReceivedHomeIncomingInvoiceCredit;
    	$this->ReceivedForeignIncomingInvoiceCredit = $c->ReceivedForeignIncomingInvoiceCredit;
    	$this->ReceivedIncomingInvoiceDebit = $c->ReceivedIncomingInvoiceDebit;
    	$this->PaidIncomingInvoiceCredit = $c->PaidIncomingInvoiceCredit;
    	$this->PaidIncomingInvoiceDebit = $c->PaidIncomingInvoiceDebit;
    	$this->RefundedIncomingInvoiceCredit = $c->RefundedIncomingInvoiceCredit;
    	$this->RefundedIncomingInvoiceDebit = $c->RefundedIncomingInvoiceDebit;
    	$this->RejectedIncomingInvoiceCredit = $c->RejectedIncomingInvoiceCredit;
    	$this->RejectedIncomingInvoiceDebit = $c->RejectedIncomingInvoiceDebit;
    	
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
    
    public function getTravelExpenseRate(): ?float
    {
    	return $this->travelExpenseRate;
    }
    
    public function getAutoCreatePerDiem(): ?bool
    {
    	return $this->autoCreatePerDiem;
    }
   
    public function getPerDiemValue(): ?float
    {
    	return $this->perDiemValue;
    }
    
    public function getAutoCreateLunch(): ?bool
    {
    	return $this->autoCreateLunch;
    }
    
    public function getLunchValue(): ?float
    {
    	return $this->lunchValue;
    }
    
    public function getIssueInvoiceDebit(): ?Konto
    {
    	return $this->IssueInvoiceDebit;
    }
    
    public function getIssueInvoiceCredit(): ?Konto
    {
    	return $this->IssueInvoiceCredit;
    }
    
    public function getInvoicePaidDebit(): ?Konto
    {
    	return $this->InvoicePaidDebit;
    }
    
    public function getInvoicePaidCredit(): ?Konto
    {
    	return $this->InvoicePaidCredit;
    }
    
    public function getIncurredTravelExpenseDebit(): ?Konto
    {
    	return $this->IncurredTravelExpenseDebit;
    }
    
    public function getIncurredTravelExpenseCredit(): ?Konto
    {
    	return $this->IncurredTravelExpenseCredit;
    }
    
    public function getPaidTravelExpenseDebit(): ?Konto
    {
    	return $this->PaidTravelExpenseDebit;
    }
    
    public function getPaidTravelExpenseCredit(): ?Konto
    {
    	return $this->PaidTravelExpenseCredit;
    }
    
    public function getReceivedHomeIncomingInvoiceCredit(): ?Konto
    {
    	return $this->ReceivedHomeIncomingInvoiceCredit;
    }
    
    public function getReceivedForeignIncomingInvoiceCredit(): ?Konto
    {
    	return $this->ReceivedForeignIncomingInvoiceCredit;
    }
    
    public function getReceivedIncomingInvoiceDebit(): ?Konto
    {
    	return $this->ReceivedIncomingInvoiceDebit;
    }
    
    public function getPaidCashIncomingInvoiceCredit(): ?Konto
    {
    	return $this->PaidCashIncomingInvoiceCredit;
    }
    
    public function getPaidTransactionIncomingInvoiceCredit(): ?Konto
    {
    	return $this->PaidTransactionIncomingInvoiceCredit;
    }
    
    public function getPaidIncomingInvoiceDebit(): ?Konto
    {
    	return $this->PaidIncomingInvoiceDebit;
    }

    public function getRefundedIncomingInvoiceCredit(): ?Konto
    {
    	return $this->RefundedIncomingInvoiceCredit;
    }
    
    public function getRefundedIncomingInvoiceDebit(): ?Konto
    {
    	return $this->RefundedIncomingInvoiceDebit;
    }
    
    public function getRejectedIncomingInvoiceCredit(): ?Konto
    {
    	return $this->RejectedIncomingInvoiceCredit;
    }
    
    public function getRejectedIncomingInvoiceDebit(): ?Konto
    {
    	return $this->RejectedIncomingInvoiceDebit;
    }  
    
}
