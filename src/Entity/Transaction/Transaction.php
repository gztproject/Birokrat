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
 * 1.   s.p. ni dolžan voditi blagajniškega poslovanja, prosto razpolaga z gotovino
 * 2.   izdan račun fizični ali pravni osebi osebi, plačan  z gotovino:  919/760
 * 3.   izdan račun fizični ali pravni osebi osebi (plačilo na TRR):  120/760.
 * 4.   Prejet račun dobavitelja (plačilo na TRR): 4…/220
 * 5.   Prejeti računi na sp, ki jih sp plača  z gotovino:  4…/919
 * 6.   dvig gotovine sp iz TRR:  919/110
 * 7.   polog gotovine na sp TRR:  110/919
 * 8.   plačilo prispevkov sp, če jih plača iz osebnega TRR:  283../919
 * 9.   povračilo za prehrano sp:  486./919
 * 10.  izplačilo potnih nalogov sp:  41.../919
 * 11.  dnevni iztržek (gotovinska prodaja), zbirno:  919/760.
 * 12.  Plačilo prejetega računa dobavitelju na TRR:  220/110
 * 13.  Plačilo izdanega računa s strani kupca, na TRR: 110/120
 * 14.  Plačilo računa iz TRR sp, ki se ne glasi na sp (osebna raba, račun ni knjižen):  919../110
 * 15.  Knjiženje prejetega računa, ki ni na sp (osebna raba): 4(A)…./919.., plačilo iz TRR: 919/110
 */

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
	 * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $counterKonto;
	
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
