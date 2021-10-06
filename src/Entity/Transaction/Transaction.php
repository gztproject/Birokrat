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
use Psr\Log\LoggerInterface;

/**
 * izdan račun fizični ali pravni osebi osebi (plačilo na TRR): 120/760.
 * Plačilo izdanega računa s strani kupca, na TRR: 110/120
 * Prejet račun dobavitelja (plačilo na TRR): 4xx/220
 * Prejeti računi na sp, ki jih sp plača z gotovino: 4xx/919
 * dvig gotovine sp iz TRR: 919/110
 * polog gotovine na sp TRR: 110/919
 * plačilo prispevkov sp 484/266 -> 266/110,
 * če jih plača iz osebnega TRR: 266/919
 * povračilo za prehrano sp: 486/285 -> 285/919
 * izplačilo potnih nalogov sp: 486/285 -> 285/919
 * Plačilo prejetega računa dobavitelju na TRR: 220/110
 * Plačilo računa iz TRR sp, ki se ne glasi na sp (osebna raba, račun ni knjižen): 919/110
 */

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\Transaction\TransactionRepository")
 */
class Transaction extends AggregateBase
{

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $organization;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     * @ORM\JoinColumn(nullable=false)
     */
    private $creditKonto;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Konto\Konto")
     * @ORM\JoinColumn(nullable=false)
     */
    private $debitKonto;

    /**
     *
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $sum;

    /**
     *
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * Should this transaction be hidden from regular transactions (for example we don't want to show the konto closing at the end of the year among refular transactions)
     *
     * @ORM\Column(type="boolean")
     */
    private $hidden;

    /**
     *
     * @ORM\Column(type="string", length=511, nullable=true)
     */
    private $description;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Invoice\Invoice")
     */
    private $invoice;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\IncomingInvoice\IncomingInvoice")
     */
    private $incomingInvoice;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TravelExpense\TravelExpense")
     */
    private $travelExpense;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\TravelExpense\TravelExpenseBundle", cascade={"persist", "remove"})
     */
    private $travelExpenseBundle;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\LunchExpense\LunchExpense", cascade={"persist", "remove"})
     */
    private $lunchExpense;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Entity\LunchExpense\LunchExpenseBundle", cascade={"persist", "remove"})
     */
    private $lunchExpenseBundle;

    /**
     * Creates a new transaction
     *
     * @param CreateTransactionCommand $c
     * @param User $user
     * @param iTransactionDocument $document
     * @throws \Exception
     */
    public function __construct(CreateTransactionCommand $c, User $user, ?iTransactionDocument $document)
    {
        if (isset($document)) {
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
        } else {
            if (! isset($c->description) || trim($c->description) === '')
                throw new \InvalidArgumentException("If creating a transaction with no document, a description must be provided.");
        }
        parent::__construct($user);
        $this->organization = $c->organization;
        $this->date = $c->date;
        $this->sum = $c->sum;
        $this->creditKonto = $c->creditKonto;
        $this->debitKonto = $c->debitKonto;
        $this->description = $c->description;
        $this->hidden = $c->hidden ?? false;
    }

    /**
     * Updates a transaction.
     * Not sure how much we should be using this though...
     *
     * @param UpdateTransactionCommand $c
     * @param User $user
     * @param ?iTransactionDocument $document
     * @throws \Exception
     * @return Transaction
     */
    public function update(UpdateTransactionCommand $c, User $user, ?iTransactionDocument $document, LoggerInterface $logger): Transaction
    {        
        $sbi = "User " . $user->getFullname() . " updating transaction " . $this->getId().": ";
        $sb = "";
        if (isset($document)) {
            if ($this->getDocument()->getId() != $document->getId())
                throw new \InvalidArgumentException("Transaction document cannot be changed!");
            if ($c->organization != null && $this->organization != $c->organization)
                throw new \InvalidArgumentException("Transaction organization cannot be changed directly if it has a document!");
            if ($c->date != null && $this->date != $c->date)
                throw new \InvalidArgumentException("Transaction date cannot be changed directly if it has a document!");
            if ($c->sum != null && $this->sum != $c->sum)
                throw new \InvalidArgumentException("Transaction sum cannot be changed directly if it has a document!");

            $this->updateCommon($c, $user, $sb);
        } else {
            if (! isset($c->description) || trim($c->description) === '')
                throw new \InvalidArgumentException("If updating a transaction with no document, a description must be provided.");
            $this->updateWithDescription($c, $user, $sb);
        } 
        if (trim($sb) == "")
            $sb .= "; No changes.";
        $sb = substr($sb, 2);
        $logger->info($sbi.$sb);
        return $this;
    }

    /**
     *
     * @param object $to
     * @return object
     */
    public function mapTo($to)
    {
        if ($to instanceof UpdateTransactionCommand || $to instanceof CreateTransactionCommand) {
            $reflect = new \ReflectionClass($this);
            $props = $reflect->getProperties();
            foreach ($props as $prop) {
                $name = $prop->getName();
                if (property_exists($to, $name)) {
                    $to->$name = $this->$name;
                }
            }
        } else {
            throw (new \Exception('Can\'t map ' . get_class($this) . ' to ' . get_class($to)));
            return $to;
        }
    }

    /**
     * Makes a copy of itself
     *
     * @param User $user
     *            The cloning user
     * @return Transaction Cloned transaction
     */
    public function clone(User $user): Transaction
    {
        if ($this->hasDocument())
            throw new \Exception('Can\'t clone a transaction with document.');
        $c = new CreateTransactionCommand();
        $this->mapTo($c);
        $transaction = $user->createTransactionWithDescription($c);

        return $transaction;
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

    private function updateCommon(UpdateTransactionCommand $c, User $user, string &$sb)
    {
        parent::updateBase($user);
        if ($c->creditKonto != null && $c->creditKonto !== $this->creditKonto)
        {
            $sb .= "; Credit konto ".$this->creditKonto->getNumber() ." -> ".$c->creditKonto->getNumber();
            $this->creditKonto = $c->creditKonto;            
        }
        if ($c->debitKonto != null && $c->debitKonto !== $this->debitKonto)
        {
            $sb .= "; Debit konto ".$this->debitKonto->getNumber() ." -> ".$c->debitKonto->getNumber();
            $this->debitKonto = $c->debitKonto;
        }
        if ($c->description != null && $c->description !== $this->description)
        {
            $sb .= "; Description ".$this->description ." -> ".$c->description;
            $this->description = $c->description;
        }
    }

    private function updateWithDescription(UpdateTransactionCommand $c, User $user, string &$sb)
    {
        $this->updateCommon($c, $user, $sb);
        if ($c->organization != null && $c->organization !== $this->organization)
        {
            $sb .= "; Organization ".$this->organization ." -> ".$c->organization;
            $this->organization = $c->organization;
        }
        if ($c->date != null && $c->date !== $this->date)
        {
            $sb .= "; Date ".$this->date->format('d. m. Y') ." -> ".$c->date->format('d. m. Y');
            $this->date = $c->date;
        }
        if ($c->sum != null && $c->sum != $this->sum)
        {
            $sb .= "; Sum ".$this->sum ." -> ".$c->sum;
            $this->sum = $c->sum;
        }
    }

    /*
     * **************************************************************************
     * * Public getters *
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

    public function getLunchExpenseBundle(): ?LunchExpenseBundle
    {
        return $this->lunchExpenseBundle;
    }

    public function getDescription(): ?string
    {
        $sb = $this->description;
        $sb .= $this->invoice;
        $sb .= $this->incomingInvoice;
        $sb .= $this->travelExpense;
        $sb .= $this->travelExpenseBundle;
        $sb .= $this->lunchExpense;
        $sb .= $this->lunchExpenseBundle;
        return $sb;
    }

    public function __toString(): string
    {
        $sb = "Transaction: ";
        $sb .= $this->date->format('d. m. Y') . ": ";
        $sb .= "Organization: " . $this->organization->getName() . "; ";
        $sb .= "Kontos: " . $this->debitKonto->getNumber();
        $sb .= " <- " . $this->creditKonto->getNumber() . "; ";
        $sb .= "SUM: " . $this->sum . "; ";
        $sb .= "Document: " . $this->getDocument() . "; ";
        if ($this->description !== null)
            $sb .= $this->description . "; ";
        return $sb;
    }

    public function hasDocument(): bool
    {
        return ($this->invoice != null) || ($this->incomingInvoice != null) || ($this->travelExpense != null) || ($this->travelExpenseBundle != null) || ($this->lunchExpense != null) || ($this->lunchExpenseBundle != null);
    }

    public function getDocument(): ?iTransactionDocument
    {
        if ($this->invoice != null)
            return $this->invoice;
        if ($this->incomingInvoice != null)
            return $this->incomingInvoice;
        if ($this->travelExpense != null)
            return $this->travelExpense;
        if ($this->travelExpenseBundle != null)
            return $this->travelExpenseBundle;
        if ($this->lunchExpense != null)
            return $this->lunchExpense;
        if ($this->lunchExpenseBundle != null)
            return $this->lunchExpenseBundle;
        return null;
    }
}
