<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Organization\Organization;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\User;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\LunchExpenseBundle;
use App\Entity\LunchExpense\UpdateLunchExpenseCommand;

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
class Transaction extends AggregateBase
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
	 * @ORM\Column(type="string", length=511, nullable=true)
	 */
	private $description;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Invoice\Invoice")
	 */
	private $invoice;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\IncomingInvoice\IncomingInvoice")
	 */
	private $incomingInvoice;
	
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpense")
	 */
	private $travelExpense;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\TravelExpense\TravelExpenseBundle", cascade={"persist", "remove"})
	 */
	private $travelExpenseBundle;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\LunchExpense\LunchExpense", cascade={"persist", "remove"})
	 */
	private $lunchExpense;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\LunchExpense\LunchExpenseBundle", cascade={"persist", "remove"})
	 */
	private $lunchExpenseBundle;
	
	/**
	 * Creates a new transaction
	 * @param CreateTransactionCommand $c
	 * @param User $user
	 * @param iTransactionDocument $document
	 * @throws \Exception
	 */
	public function __construct(CreateTransactionCommand $c, User $user, ?iTransactionDocument $document)
	{	
		if(isset($document))
		{
			switch (get_class($document)) { 			
				case Invoice::class:
					$this->initWithInvoice($c, $document);
					break;
				case IncomingInvoice::class:
					$this->initWithIncomingInvoice($c, $document);
				break;
				case TravelExpense::class:
					$this->initWithTravelExpense($c, $document);
					break;
				case TravelExpenseBundle::class:
					$this->initWithTravelExpenseBundle($c, $document);
					break;
				case LunchExpense::class:
					$this->initWithLunchExpense($c, $document);
					break;
				case LunchExpenseBundle::class:
					$this->initWithLunchExpenseBundle($c, $document);
					break;
				default:
					throw new \Exception('Not implemented yet.');
					break;				
			}
		}
		else 
		{
			if(!isset($c->description) || trim($c->description) === '')
				throw new \InvalidArgumentException("If creating a transaction with no document, a description must be provided.");
		}
		parent::__construct($user);
		$this->organization = $c->organization;
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->creditKonto = $c->creditKonto;
		$this->debitKonto = $c->debitKonto;
		$this->description = $c->description;		
		
	}
	
	/**
	 * Updates a transaction. Not sure how much we should be using this though...
	 * @param UpdateTransactionCommand $c
	 * @param User $user
	 * @param iTransactionDocument $document
	 * @throws \Exception
	 * @return Transaction
	 */
	public function update(UpdateTransactionCommand $c, User $user, ?iTransactionDocument $document): Transaction
	{
		if(isset($document))
		{
			switch (get_class($document)) {
				case Invoice::class:
					parent::updateBase($user);
					$this->updateWithInvoice($c, $document);
					break;
				case IncomingInvoice::class:
					parent::updateBase($user);
					$this->updateWithIncomingInvoice($c, $document);
					break;
				case TravelExpense::class:
					parent::updateBase($user);
					$this->updateWithTravelExpense($c, $document);
					break;
				case TravelExpenseBundle::class:
					parent::updateBase($user);
					$this->updateWithTravelExpenseBundle($c, $document);
					break;
				case LunchExpense::class:
					parent::updateBase($user);
					$this->updateWithLunchExpense($c, $document);
					break;
				default:
					throw new \Exception('Not implemented yet.');
					break;				
			}
		}
		else
			$this->updateWithDescription();
		
		//ToDo: Check theese methods, we should probably amend old data... 
		$this->creditKonto->updateCredit($this->sum, $user);
		$this->debitKonto->updateDebit($this->sum, $user);
		return $this;
	}
	
	private function initWithInvoice(CreateTransactionCommand $c, Invoice $invoice)	
	{		
		$this->invoice = $invoice;
	}
	
	private function initWithIncomingInvoice(CreateTransactionCommand $c, IncomingInvoice $incomingInvoice)
	{
		$this->incomingInvoice = $incomingInvoice;
	}
	
	private function initWithTravelExpenseBundle(CreateTransactionCommand $c, TravelExpenseBundle $bundle)
	{		
		$this->travelExpenseBundle = $bundle;
	}
	
	private function initWithTravelExpense(CreateTransactionCommand $c, TravelExpense $travelExpense)
	{
		$this->travelExpense = $travelExpense;
	}
	
	private function initWithLunchExpense(CreateTransactionCommand $c, LunchExpense $le)
	{
		$this->lunchExpense = $le;
	}
	
	private function initWithLunchExpenseBundle(CreateTransactionCommand $c, LunchExpenseBundle $bundle)
	{
		$this->lunchExpenseBundle = $bundle;
	}
	
	private function updateCommon(UpdateTransactionCommand $c)
	{
		if($c->organization!==null && $c->organization !== $this->organization)
			$this->organization = $c->organization;
		if($c->date!==null && $c->date !== $this->date)
			$this->date = $c->date;
		if($c->sum!==null && $c->sum !== $this->sum)
			$this->sum = $c->sum;
		if($c->creditKonto!==null && $c->creditKonto !== $this->creditKonto)
			$this->creditKonto = $c->creditKonto;
		if($c->debitKonto!==null && $c->debitKonto !== $this->debitKonto)
			$this->debitKonto = $c->debitKonto;
		if($c->description!==null && $c->description !== $this->description)
			$this->description = $c->description;		
	}
	
	private function updateWithDescription(UpdateTransactionCommand $c)
	{
		$this->updateCommon($c);
		
	}
	
	private function updateWithInvoice(UpdateTransactionCommand $c, Invoice $invoice)
	{
		$this->updateCommon($c);
		$this->invoice = $invoice;
	}
	
	private function updateWithIncomingInvoice(UpdateTransactionCommand $c, IncomingInvoice $incomingInvoice)
	{
		$this->updateCommon($c);
	}
	
	private function updateWithTravelExpense(UpdateTransactionCommand $c, TravelExpense $travelExpense)
	{
		$this->updateCommon($c);
		$this->travelExpense = $travelExpense;
	}
	
	private function updateWithTravelExpenseBundle(UpdateTransactionCommand $c, TravelExpenseBundle $bundle)
	{
		$this->updateCommon($c);
		$this->travelExpenseBundle = $bundle;
	}
	
	private function updateWithLunchExpense(UpdateLunchExpenseCommand $c, LunchExpense $le)
	{
		$this->updateCommon($c);
		$this->lunchExpense = $le;
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
	
	public function getIncomingInvoice(): ?IncomingInvoice
	{
		return $this->incomingInvoice;
	}
	
	public function getTravelExpense(): ?TravelExpense
	{
		return $this->travelExpense;
	}
	
	public function getTravelExpenseBundle(): ?TravelExpenseBundle
	{
		return $this->travelExpenseBundle;
	}
	
	public function getLunchExpense(): ?LunchExpense
	{
		return $this->lunchExpense;
	}
	
	public function getDescription(): ?string
	{
		return $this->description;
	}
	
	public function __toString(): string
	{
		return "Transaction: ".$this->getDateString()." ".$this->getSum();
	}
}
