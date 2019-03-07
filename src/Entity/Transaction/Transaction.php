<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\Base;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\Invoice;
use App\Entity\TravelExpense\TravelExpense;

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
	
	public function initWithInvoice(\DateTimeInterface $date, Konto $konto, float $sum, Invoice $invoice)
	{
		$this->setDate($date);
		$this->setSum($sum);
		$this->setKonto($konto);
		$this->setInvoice($invoice);
	}
	
	public function initWithTravelExpense(\DateTimeInterface $date, Konto $konto, float $sum, TravelExpense $travelExpense)
	{
		$this->setDate($date);
		$this->setSum($sum);
		$this->setKonto($konto);
		$this->setTravelExpense($travelExpense);
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
}
