<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Settings\OrganizationSettingsRepository::class)]
class OrganizationSettings extends Base
{
        
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private $invoicePrefix;

    #[ORM\Column(type: "integer", nullable: true)]
    private $defaultPaymentDueIn;

    #[ORM\Column(type: "string", length: 10, nullable: true)]
    private $referenceModel;
    
    #[ORM\Column(type: "float", nullable: true)]
    private $travelExpenseRate;
    
    #[ORM\Column(type: "boolean")]
    private $autoCreatePerDiem;
    
    #[ORM\Column(type: "float", nullable: true)]
    private $perDiemValue;
    
    #[ORM\Column(type: "boolean")]
    private $autoCreateLunch;
    
    #[ORM\Column(type: "float", nullable: true)]
    private $lunchValue;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $IssueInvoiceDebit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $IssueInvoiceCredit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $InvoicePaidDebit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $InvoicePaidCredit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $IncurredTravelExpenseDebit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $IncurredTravelExpenseCredit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $PaidTravelExpenseDebit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $PaidTravelExpenseCredit;
    
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $ReceivedHomeIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $ReceivedForeignIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $ReceivedIncomingInvoiceDebit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $PaidCashIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $PaidTransactionIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $PaidIncomingInvoiceDebit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $RefundedIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $RefundedIncomingInvoiceDebit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $RejectedIncomingInvoiceCredit;
    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $RejectedIncomingInvoiceDebit;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $BankFeeDebit;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Konto\Konto::class)]
    private $BankFeeCredit;
    
    
    
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: \App\Entity\Organization\Organization::class, inversedBy: "organizationSettings", cascade: ["persist", "remove"])]
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
    	$this->autoCreatePerDiem = (bool) $c->autoCreatePerDiem;
    	$this->autoCreateLunch = (bool) $c->autoCreateLunch;
    	$this->travelExpenseRate = $c->travelExpenseRate;
    	$this->perDiemValue = $c->perDiemValue;
    	$this->lunchValue = $c->lunchValue;
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
    	$this->PaidCashIncomingInvoiceCredit = $c->PaidCashIncomingInvoiceCredit ?? null;
    	$this->PaidTransactionIncomingInvoiceCredit = $c->PaidTransactionIncomingInvoiceCredit ?? null;
    	$this->RefundedIncomingInvoiceCredit = $c->RefundedIncomingInvoiceCredit;
    	$this->RefundedIncomingInvoiceDebit = $c->RefundedIncomingInvoiceDebit;
    	$this->RejectedIncomingInvoiceCredit = $c->RejectedIncomingInvoiceCredit;
    	$this->RejectedIncomingInvoiceDebit = $c->RejectedIncomingInvoiceDebit;
    	$this->BankFeeDebit = $c->BankFeeDebit ?? null;
    	$this->BankFeeCredit = $c->BankFeeCredit ?? null;
    	
    	//Obsolete...
    	$this->PaidIncomingInvoiceDebit = $c->PaidIncomingInvoiceDebit;
    	$this->PaidIncomingInvoiceCredit = $c->PaidIncomingInvoiceCredit;
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
    	$this->travelExpenseRate = $c->travelExpenseRate;
    	$this->autoCreatePerDiem = (bool) $c->autoCreatePerDiem;
    	$this->perDiemValue = $c->perDiemValue;
    	$this->autoCreateLunch = (bool) $c->autoCreateLunch;
    	$this->lunchValue = $c->lunchValue;
    	
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
    	$this->PaidCashIncomingInvoiceCredit = $c->PaidCashIncomingInvoiceCredit;
    	$this->PaidTransactionIncomingInvoiceCredit = $c->PaidTransactionIncomingInvoiceCredit;
    	$this->RefundedIncomingInvoiceCredit = $c->RefundedIncomingInvoiceCredit;
    	$this->RefundedIncomingInvoiceDebit = $c->RefundedIncomingInvoiceDebit;
    	$this->RejectedIncomingInvoiceCredit = $c->RejectedIncomingInvoiceCredit;
    	$this->RejectedIncomingInvoiceDebit = $c->RejectedIncomingInvoiceDebit;
    	$this->BankFeeDebit = $c->BankFeeDebit ?? $this->BankFeeDebit;
    	$this->BankFeeCredit = $c->BankFeeCredit ?? $this->BankFeeCredit;
    	 //Obsolete
    	$this->PaidIncomingInvoiceDebit = $c->PaidIncomingInvoiceDebit;
    	$this->PaidIncomingInvoiceCredit = $c->PaidIncomingInvoiceCredit;
    	
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
    
    /**
     * @deprecated
     * @return Konto|NULL
     */
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

    public function getBankFeeDebit(): ?Konto
    {
    	return $this->BankFeeDebit;
    }

    public function getBankFeeCredit(): ?Konto
    {
        return $this->BankFeeCredit;
    }

    public function mapTo(object $to): object
    {
    	if ($to instanceof UpdateOrganizationSettingsCommand || $to instanceof CreateOrganizationSettingsCommand)
    	{
    		$reflect = new \ReflectionClass($this);
    		$props = $reflect->getProperties();
    		foreach ($props as $prop)
    		{
    			$name = $prop->getName();
    			if (property_exists($to, $name))
    			{
    				$to->$name = $this->$name;
    			}
    		}
    	}
    	else
    	{
    		throw new \InvalidArgumentException('cant map ' . get_class($this) . ' to ' . get_class($to));
    	}

    	return $to;
    }
    
}
