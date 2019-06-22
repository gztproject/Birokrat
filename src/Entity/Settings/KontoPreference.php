<?php

namespace App\Entity\Settings;

use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Settings\KontoPreferenceRepository")
 */
class KontoPreference extends Base
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     */
    private $IssueInvoicedevit;

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
     * 
     * @param CreateKontoPreferenceCommand $c
     * @param User $user
     */
    public function __construct(CreateKontoPreferenceCommand $c, User $user)
    {
    	parent::__construct($user);
    	
    	$this->IssueInvoiceCredit = $c->IssueInvoiceCredit;
    	$this->IssueInvoiceDebit = $c->IssueInvoiceDebit;
    	$this->InvoicePaidCredit = $c->InvoicePaidCredit;
    	$this->InvoicePaidDebit = $c->InvoicePaidDebit;
    	$this->IncurredTravelExpenseCredit = $c->IncurredTravelExpenseCredit;
    	$this->IncurredTravelExpenseDebit = $c->IncurredTravelExpenseDebit;
    	$this->PaidTravelExpenseCredit = $c->PaidTravelExpenseCredit;
    	$this->PaidTravelExpenseDebit = $c->PaidTravelExpenseDebit;
    }
    
    /**
     * 
     * @param UpdateKontoPreferenceCommand $c
     * @param User $user
     * @return KontoPreference
     */
    public function update(UpdateKontoPreferenceCommand $c, User $user): KontoPreference
    {
    	//ToDo: do some checks first...
    	parent::updateBase($user);
    	
    	//ToDo: check for nulls...
    	$this->IssueInvoiceCredit = $c->IssueInvoiceCredit;
    	$this->IssueInvoiceDebit = $c->IssueInvoiceDebit;
    	$this->InvoicePaidCredit = $c->InvoicePaidCredit;
    	$this->InvoicePaidDebit = $c->InvoicePaidDebit;
    	$this->IncurredTravelExpenseCredit = $c->IncurredTravelExpenseCredit;
    	$this->IncurredTravelExpenseDebit = $c->IncurredTravelExpenseDebit;
    	$this->PaidTravelExpenseCredit = $c->PaidTravelExpenseCredit;
    	$this->PaidTravelExpenseDebit = $c->PaidTravelExpenseDebit;
    	
    	return $this;
    }

    public function getIssueInvoiceDebit(): ?Konto
    {
        return $this->IssueInvoicedevit;
    }

    public function getIssueInvoiceCredit(): ?Konto
    {
        return $this->IssueInvoiceCredit;
    }

    public function getInvoicePaidDebit(): ?Konto
    {
        return $this->IvnoicePaidDebit;
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

}
