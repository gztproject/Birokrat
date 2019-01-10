<?php

namespace App\Entity\Transaction;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Konto;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ExpenseRepository")
 */
class Expense extends Transaction
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
    private $dateTime;

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

    public function getDateTime(): ?\DateTimeInterface
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTimeInterface $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }
}
