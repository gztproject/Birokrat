<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\User;

/**
 * izdan račun fizični ali pravni osebi osebi (plačilo na TRR):  120/760.
 * Plačilo izdanega računa s strani kupca, na TRR: 110/120
 * Prejet račun dobavitelja (plačilo na TRR): 4xx/220
 * Prejeti računi na sp, ki jih sp plača  z gotovino:  4xx/919
 * dvig gotovine sp iz TRR:  919/110
 * polog gotovine na sp TRR:  110/919
 * plačilo prispevkov sp 484/266 -> 266/110, 
 * 		če jih plača iz osebnega TRR:  266/919
 * povračilo za prehrano sp:  486/285 -> 285/919
 * izplačilo potnih nalogov sp:  486/285 -> 285/919
 * Plačilo prejetega računa dobavitelju na TRR:  220/110
 * Plačilo računa iz TRR sp, ki se ne glasi na sp (osebna raba, račun ni knjižen):  919/110
 */

/**
 * @ORM\Entity(repositoryClass="App\Repository\Transaction\TransactionRepository")
 */
class Transaction extends Base
{
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $organization;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $creditKonto;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $debitKonto;
	
	/**
	 * @ORM\Column(type="decimal", precision=10, scale=2)
	 */
	private $sum;
	
	/**
	 * @ORM\Column(type="datetime")
	 */
	private $date;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Invoice\Invoice")
	 */
	private $invoice;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpense")
	 */
	private $travelExpense;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\TravelExpense\TravelExpenseBundle", cascade={"persist", "remove"})
	 */
	private $travelExpenseBundle;
	
	public function __construct(CreateTransactionCommand $c, User $user, iTransactionDocument $document)
	{		
		switch (get_class($document)) {
			case Invoice::class:
				parent::__construct($user);
				$this->initWithInvoice($c, $document);
				break;
			case TravelExpense::class:
				parent::__construct($user);
				$this->initWithTravelExpense($c, $document);
				break;
			case TravelExpenseBundle::class:
				parent::__construct($user);
				$this->initWithTravelExpenseBundle($c, $document);
				break;
			default:
				throw new \Exception('Not implemented yet.');
				break;
		}
				
		$this->organization = $c->organization;
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->creditKonto = $c->creditKonto;
		$this->debitKonto = $c->debitKonto;
		$this->creditKonto->updateCredit($this->sum, $user);
		$this->debitKonto->updateDebit($this->sum, $user);			
		
	}
	
	public function update(UpdateTransactionCommand $c, User $user, iTransactionDocument $document): Transaction
	{
		switch (get_class($document)) {
			case Invoice::class:
				parent::updateBase($user);
				$this->updateWithInvoice($c, $document);
				break;
			case TravelExpense::class:
				parent::updateBase($user);
				$this->updateWithTravelExpense($c, $document);
				break;
			case TravelExpenseBundle::class:
				parent::updateBase($user);
				$this->iupdateWithTravelExpenseBundle($c, $document);
				break;
			default:
				throw new \Exception('Not implemented yet.');
				break;
			return $this;
		}
	}
	
	private function initWithInvoice(CreateTransactionCommand $c, Invoice $invoice)	
	{		
		$this->invoice = $invoice;
	}
	
	private function initWithTravelExpenseBundle(CreateTransactionCommand $c, TravelExpenseBundle $bundle)
	{		
		$this->travelExpenseBundle = $bundle;
	}
	
	private function initWithTravelExpense(CreateTransactionCommand $c, TravelExpense $travelExpense)
	{
		$this->travelExpense = $travelExpense;
	}
	
	private function updateWithInvoice(UpdateTransactionCommand $c, Invoice $invoice)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->creditKonto = $c->creditKonto;
		$this->debitKonto = $c->debitKonto;
		$this->invoice = $invoice;
	}
	
	private function updateWithTravelExpense(UpdateTransactionCommand $c, TravelExpense $travelExpense)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->creditKonto = $c->creditKonto;
		$this->debitKonto = $c->debitKonto;
		$this->travelExpense = $travelExpense;
	}
	
	private function updateWithTravelExpenseBundle(UpdateTransactionCommand $c, TravelExpenseBundle $bundle)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->creditKonto = $c->creditKonto;
		$this->debitKonto = $c->debitKonto;
		$this->travelExpenseBundle = $bundle;
	}
	
	/*
	 * **************************************************************************
	 * *    Public getters														*
	 * **************************************************************************
	 */
	
	public function getOrganization(): Organization
	{
		return $this->organization;
	}
	
	public function getCreditKonto(): Konto
	{
		return $this->creditKonto;
	}
	
	public function getDebitKonto(): Konto
	{
		return $this->debitKonto;
	}

	public function getSum()
	{
		return $this->sum;
	}
	
	public function getDate(): ?\DateTimeInterface
	{
		return $this->date;
	}
	
	public function getDateString(): ?string
	{
		return $this->date->format('d. m. Y');
	}
	
	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}
	
	public function getTravelExpense(): ?TravelExpense
	{
		return $this->travelExpense;
	}
	
	public function getTravelExpenseBundle(): ?TravelExpenseBundle
	{
		return $this->travelExpenseBundle;
	}
}
