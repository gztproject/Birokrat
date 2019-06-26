<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use App\Entity\User\User;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Transaction\TransactionRepository")
 */
class Transaction extends Base
{
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $konto;
	
	/**
	 * @ORM\Column(type="decimal", precision=10, scale=2)
	 */
	private $sum;
	
	/**
	 * @ORM\Column(type="datetime")
	 */
	private $date;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\Invoice\Invoice", cascade={"persist", "remove"})
	 */
	private $invoice;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\TravelExpense\TravelExpense", cascade={"persist", "remove"})
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
				throw new \Exception('Not implemented yet.');
				break;
			case TravelExpenseBundle::class:
				parent::__construct($user);
				$this->initWithTravelExpenseBundle($c, $document);
				break;
			default:
				throw new \Exception('Not implemented yet.');
				break;
		}
	}
	
	public function update(UpdateTransactionCommand $c, User $user, iTransactionDocument $document): Transaction
	{
		switch (get_class($document)) {
			case Invoice::class:
				parent::updateBase($user);
				$this->updateWithInvoice($c, $document);
				break;
			case TravelExpense::class:
				throw new \Exception('Not implemented yet.');
				break;
			case TravelExpenseBundle::class:
				parent::updateBase($user);
				$this->iupdateWithTravelExpenseBundle($c, $document);
				break;
			default:
				throw new \Exception('Not implemented yet.');
				break;
		}
	}
	
	private function initWithInvoice(CreateTransactionCommand $c, Invoice $invoice)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->konto = $c->konto;
		$this->invoice = $invoice;
	}
	
	private function initWithTravelExpenseBundle(CreateTransactionCommand $c, TravelExpenseBundle $bundle)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->konto = $c->konto;
		$this->travelExpenseBundle = $bundle;
	}
	
	private function updateWithInvoice(UpdateTransactionCommand $c, Invoice $invoice)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->konto = $c->konto;
		$this->invoice = $invoice;
	}
	
	private function updateWithTravelExpenseBundle(UpdateTransactionCommand $c, TravelExpenseBundle $bundle)
	{
		$this->date = $c->date;
		$this->sum = $c->sum;
		$this->konto = $c->konto;
		$this->travelExpenseBundle = $bundle;
	}
	
	
	
	public function getKonto(): ?Konto
	{
		return $this->konto;
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
