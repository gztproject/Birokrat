<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;

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
	
	public function initWithInvoice(\DateTimeInterface $date, Konto $konto, float $sum, Invoice $invoice)
	{
		$this->setDate($date);
		$this->setSum($sum);
		$this->setKonto($konto);
		$this->setInvoice($invoice);
	}
	
	public function initWithTravelExpenseBundle(\DateTimeInterface $date, Konto $konto, float $sum, TravelExpenseBundle $travelExpenseBundle)
	{
		$this->setDate($date);
		$this->setSum(-$sum);
		$this->setKonto($konto);
		$this->setTravelExpenseBundle($travelExpenseBundle);
	}
	
	public function getKonto(): ?Konto
	{
		return $this->konto;
	}
	
	public function setKonto(?Konto $konto): self
	{
		$this->konto = $konto;
		
		return $this;
	}
	
	public function getCounterKonto(): ?Konto
	{
		return $this->konto;
	}
	
	public function setCounterKonto(?Konto $counterKonto): self
	{
		$this->counterKonto = $counterKonto;
		
		return $this;
	}
	
	public function getSum()
	{
		return $this->sum;
	}
	
	public function setSum($sum): self
	{
		$this->sum = $sum;
		
		return $this;
	}
	
	public function getDate(): ?\DateTimeInterface
	{
		return $this->date;
	}
	
	public function getDateString(): ?string
	{
		return $this->date->format('d. m. Y');
	}
	
	public function setDate(\DateTimeInterface $date): self
	{
		$this->date = $date;
		
		return $this;
	}
	
	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}
	
	public function setInvoice(?Invoice $Invoice): self
	{
		$this->invoice = $Invoice;
		
		return $this;
	}
	
	public function getTravelExpense(): ?TravelExpense
	{
		return $this->travelExpense;
	}
	
	public function setTravelExpense(?TravelExpense $travelExpense): self
	{
		$this->travelExpense = $travelExpense;
		
		return $this;
	}
	
	public function getTravelExpenseBundle(): ?TravelExpenseBundle
	{
		return $this->travelExpenseBundle;
	}
	
	public function setTravelExpenseBundle(?TravelExpenseBundle $travelExpenseBundle): self
	{
		$this->travelExpenseBundle = $travelExpenseBundle;
		
		return $this;
	}
}
