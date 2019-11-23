<?php

namespace App\Entity\LunchExpense;

use App\Entity\Base\AggregateBase;
use App\Entity\Konto\Konto;
use App\Entity\Transaction\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Transaction\iTransactionDocument;
use App\Entity\User\User;
use App\Entity\Transaction\CreateTransactionCommand;
use Symfony\Component\Validator\Constraints\Date;
use App\Entity\Organization\Organization;
use PhpParser\Node\Expr\Cast\Int_;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TravelExpense\TravelExpenseBundleRepository")
 */
class LunchExpense extends AggregateBase implements iTransactionDocument
{    
	/**
	 * @ORM\Column(type="date")
	*/
   	private $date;
   	/**
   	 * @ORM\Column(type="decimal", precision=15, scale=2)
   	 */
   	private $sum;
   	/**
   	 *  @ORM\Column(type="integer")
   	 */
   	private $state;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Organization\Organization")
     * @ORM\JoinColumn(nullable=false)
     */
    private $organization;
    
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\LunchExpense\LunchExpenseBundle", inversedBy="LunchExpenses")
     */
    private $lunchExpenseBundle;
    

    public function __construct(CreateLunchExpenseCommand $c, User $user)
    {
    	parent::__construct($user);    	
        
        $this->organization = $c->organization;
        $this->state = States::draft;
        $this->date = $c->date;
        $this->sum = $c->sum;
    }
    
    public function setNew(User $user): Transaction
    {
    	$this->setState(States::new);
    	parent::updateBase($user);
    	$c = new CreateTransactionCommand();
    	$c->organization = $this->organization;
    	$c->date = $this->date;
    	
    	$dc = $this->organization->getOrganizationSettings()->getIncurredTravelExpenseDebit();
    	$cc = $this->organization->getOrganizationSettings()->getIncurredTravelExpenseCredit();
    	if($cc == null || $dc == null)
    		throw new \LogicException("Please set konto preferences for this organization before issuing invoices.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	if($this->sum === null)
    		throw new \InvalidArgumentException("No price is set.");
    	$c->sum = $this->sum;
    			
    	$transaction = new Transaction($c, $user, $this);
    			
    	return $transaction;    	
    }
    
    public function setLunchExpenseBundle(LunchExpenseBundle $leb, User $user): LunchExpense
    {
    	if($user == null)
    		throw new \Exception("Updating user must be set.");
    		
    		if($this->state != 10)
    			throw new \Exception("I can only bundle new expenses.");
    			
    			if ($this->lunchExpenseBundle != null)
    				throw new \Exception("This LunchExpense is already bundled.");
    				
    				parent::updateBase($user);
    				$this->lunchExpenseBundle = $leb;
    				
    				return $this;
    }
    
    public function setBooked(\DateTime $date, User $user): Transaction
    {    	    	
    	parent::updateBase($user);
    	$this->setState(States::booked);
    	$c = new CreateTransactionCommand();
    	$c->date = $date;
    	$cc = $this->organization->getOrganizationSettings()->getPaidTravelExpenseCredit();
    	$dc = $this->organization->getOrganizationSettings()->getPaidTravelExpenseDebit();
    	if($cc == null || $dc == null)
    		throw new \Exception("Please set konto preferences for this organization before booking expenses.");
    	$c->creditKonto = $cc;
    	$c->debitKonto = $dc;
    	$c->organization = $this->organization;
    	
    	$c->sum = $this->sum;
    	
    	$transaction = new Transaction($c, $user, $this);    	
    	
    	return $transaction;
    }
    
    /**
     * Sets LE state
     *
     * @param integer $state 00-draft, 10-new, 20-booked, 100-rejected.
     */
    private function setState(?int $state): int
    {
    	$this->checkState($this->state, $state);
    	$this->state = $state;
    	
    	return $state;
    }
    
    /**
     * Checks if transition of states is allowed and everything is properly set.
     *
     * @param int $currState Current LE state
     * @param int $newState New LE state
     */
    private function checkState(int $currState, int $newState)
    {
    	switch ($currState) {
    		case States::draft:
    			if ($newState != States::new && $newState != States::rejected)
    				throw new \LogicException("Can't transition to state $newState from $currState");
    				break;
    		case States::new:
    			if ($newState != States::booked && $newState != States::rejected)
    				throw new \LogicException("Can't transition to state $newState from $currState");
    				break;
    		case States::booked:
    			if ($newState != States::rejected) //Do we really want to be able to cancel booked LEs?
    				throw new \LogicException("Can't transition to state $newState from $currState");
    				break;
    				
    		case States::rejected:
    			throw new \LogicException("Can't do anything with Rejected LE.");
    			break;
    		default:
    			throw new \LogicException('This LE State is unknown!');
    			break;
    	}
    	
    }
    
    public function getDate()
    {
    	return $this->date;
    }
    
    public function getDateString(): ?string
    {
    	return $this->date->format('d. m. Y');
    }    
    
    public function getSum(): float
    {
    	return $this->sum;
    }
    
    public function getState(): Int
    {
    	return $this->state;
    }
    
    public function getOrganization(): Organization
    {
    	return $this->organization;
    }
    
    public function __toString()
    {	
    	$string = "";    	
    	$string .= "LunchExpense " . $this->getDateString();
    	return $string;
    }
}

abstract class States
{
	const draft = 00;
	const new = 10;
	const booked = 20;
	const rejected = 100;
}
