<?php
namespace App\Tests\TravelExpense;

use PHPUnit\Framework\TestCase;
use App\Entity\Geography\Post;
use App\Entity\Organization\Organization;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\User\User;
use App\Entity\TravelExpense\CreateTravelStopCommand;
use App\Entity\Geography\Address;

class TravelStopTest extends TestCase
{
	public function testAddRemoveStops()
    {    	
    	$user = $this->createMock(User::class);
            	
    	$rate = 0.37;
    	
    	$te = $this->createTravelExpense($user, $rate);
    	
        $distance = 0;
        for ($i = 0; $i < 5; $i++) {
        	$c = new CreateTravelStopCommand();
        	$c->post = $this->createMock(Post::class);
        	$c->distanceFromPrevious = 10*$i;
        	$distance += 10*$i;
        	$c->stopOrder = $i+1;
        	
        	$te -> createTravelStop($c);
        }       
        $j = 1;
        foreach ($te->getTravelStops() as $stop)
        {
        	$this->assertEquals($j, $stop->getStopOrder());
        	$this->assertNotNull($stop->getPost());
        	$j++;
        }
        
        $this->assertEquals(5, $te->getTravelStops()->count());
        $this->assertEquals($distance, $te->getTotalDistance());
        $this->assertEquals($distance*$rate, $te->getTotalCost());
        
        for ($i = 5; $i > 0; $i--) {
        	if($i<3)
        		$this->expectException(\Exception::class);
        	$te->removeTravelStop($te->getTravelStops()->last(), $user);
        	$this->assertEquals($i-1, $te->getTravelStops()->count());
        	$distance -= ($i-1)*10;
        	$this->assertEquals($distance, $te->getTotalDistance());
        	$this->assertEquals($distance*$rate, $te->getTotalCost());
        	$j=1;
        	foreach ($te->getTravelStops() as $stop)
        	{
        		$this->assertEquals($j, $stop->getStopOrder());
        		$j++;
        	}
        }
    }
    
    private function createTravelExpense(User $user=null, float $rate=0.37): TravelExpense
    {
    	if($user == null)
    		$user = $this->createMock(User::class);
    	$c = new CreateTravelExpenseCommand();
    	$c->date = new \DateTime();
    	$c->employee = $user;
    	$c->rate = $rate;
    	
    	return new TravelExpense($c, $user);
    }
    
    
}