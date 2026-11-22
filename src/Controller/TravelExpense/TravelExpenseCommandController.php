<?php 

namespace App\Controller\TravelExpense;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LunchExpense\LunchExpenseRepository;
use App\Repository\TravelExpense\TravelExpenseRepository;
use App\Entity\Konto\Konto;
use App\Form\TravelExpense\TravelExpenseType;
use App\Entity\TravelExpense\TravelExpenseBundle;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\TravelExpense\CreateTravelExpenseCommand;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\UpdateTravelExpenseCommand;
use App\Entity\TravelExpense\UpdateTravelStopCommand;
use Psr\Log\LoggerInterface;
use App\Entity\TravelExpense\CreateTravelExpenseBundleCommand;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Repository\Transaction\TransactionRepository;
use App\Entity\Transaction\UpdateTransactionCommand;

class TravelExpenseCommandController extends AbstractController
{    
	#[Route(path: "/dashboard/travelExpense/new", methods: ["GET", "POST"], name: "travelExpense_new")]
    public function new(Request $request, TravelExpenseRepository $travelExpenses, LunchExpenseRepository $lunchExpenses, LoggerInterface $logger, ManagerRegistry $doctrine): Response
    {
    	$c = new CreateTravelExpenseCommand();
    	$c->employee = $this->getUser();
    	    	
    	$form = $this->createForm(TravelExpenseType::class, $c, [
    			'is_admin' => $this->isGranted('ROLE_ADMIN'),
    		])
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		if (!$this->isGranted('ROLE_ADMIN') || $c->employee === null) {
    			$c->employee = $this->getUser();
    		}
    		
    		$te = $this->getUser()->createTravelExpense($c);  
    		
    		$em = $doctrine->getManager();
    		
    		foreach($c->travelStopCommands as $tsc)
    		{
    			$ts = $te->createTravelStop($tsc);
    			$em->persist($ts);
    		}
    		
    		$transaction = $te->setNew($this->getUser());
    		
    		$lunchExpense = $this->createAutomaticLunchExpense($te, $lunchExpenses, $logger);
    		if($lunchExpense !== null)
    			$lunchTransaction = $lunchExpense->setNew($this->getUser());
    		
    		$em->persist($te);
    		$em->persist($transaction);
    		if($lunchExpense !== null)
    		{
    			$em->persist($lunchTransaction);
    			$em->persist($lunchExpense);
    		}
    		
    		$em->flush();
    		    		
    		return $this->redirectToRoute('travelExpense_index');
    	}
    	
    	return $this->render('dashboard/travelExpense/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }   
    
    #[Route(path: "/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit", methods: ["GET", "POST"], name: "travelExpense_edit")]
    public function edit(Request $request, TravelExpense $te, LoggerInterface $logger, TransactionRepository $transactions, ManagerRegistry $doctrine): Response
    {
    	$updateTECommand = new UpdateTravelExpenseCommand();
    	$te->mapTo($updateTECommand);
    	
    	foreach($te->getTravelStops() as $ts)
    	{
    		$utsc = new UpdateTravelStopCommand();
    		$ts->mapTo($utsc);
    		$updateTECommand->travelStopCommands->add($utsc);
    	}
    	
    	$form = $this->createForm(TravelExpenseType::class, $updateTECommand, [
    			'is_admin' => $this->isGranted('ROLE_ADMIN'),
    		])
    		->add('saveAndCreateNew', SubmitType::class);
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$te->update($updateTECommand, $this->getUser(), $logger); 
    		
    		$transaction = $transactions->findOneByTravelExpense($te);
    		$utc = new UpdateTransactionCommand();
    		
    		$utc->date = $te->getDate();
    		$utc->organization = $te->getOrganization();
    		$utc->sum = $te->getTotalCost();
    		
    		$transaction->update($utc, $this->getUser(), $te, $logger);
    		
    		$em = $doctrine->getManager();
    		
    		foreach($te->getTravelStops() as $ts)
    		{
    			$logger->debug("Persisting TravelStop ".$ts.". ");
    			$em->persist($ts);
    		}  
    		$logger->debug("Persisting TravelExpense ".$te.". ");    	
    		$em->persist($te);
    		
    		$logger->debug("Persisting Transaction ".$transaction.". ");
    		$em->persist($transaction);
    		$em->flush();
    		
    		return $this->redirectToRoute('travelExpense_show', array('id'=> $te->getId()));
    	}
    	
    	return $this->render('dashboard/travelExpense/edit.html.twig', [
    			'travelExpense' => $te,
    			'form' => $form->createView(),
    	]);
    }
    
    #[Route(path: "/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone", methods: ["GET", "POST"], name: "travelExpense_clone")]
    public function clone(Request $request, TravelExpense $te, TravelExpenseRepository $travelExpenses, LunchExpenseRepository $lunchExpenses, 
        LoggerInterface $logger, ManagerRegistry $doctrine): Response
    {
    	$clone = $te->clone($this->getUser());
    	
    	$updateTECommand = new UpdateTravelExpenseCommand();
    	$clone->mapTo($updateTECommand);
    	
    	foreach($clone->getTravelStops() as $ts)
    	{
    		$utsc = new UpdateTravelStopCommand();
    		$ts->mapTo($utsc);
    		$updateTECommand->travelStopCommands->add($utsc);
    	}
    	
    	$form = $this->createForm(TravelExpenseType::class, $updateTECommand, [
    			'is_admin' => $this->isGranted('ROLE_ADMIN'),
    		])
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$clone->update($updateTECommand, $this->getUser(), $logger);
    		$em = $doctrine->getManager();
    		
    		foreach($clone->getTravelStops() as $ts)
    		{
    			$logger->debug("Persisting Cloned TravelStop ".$ts.". ");
    			$em->persist($ts);
    		}
    		    		
    		$transaction = $clone->setNew($this->getUser());
    		
    		$lunchExpense = $this->createAutomaticLunchExpense($clone, $lunchExpenses, $logger);
    		if($lunchExpense !== null)
    			$lunchTransaction = $lunchExpense->setNew($this->getUser());
    		
    		$em->persist($clone);
    		$logger->debug("Persisting Cloned TravelExpense ".$clone.". ");
    		$em->persist($transaction);
    		if($lunchExpense !== null)
    		{
    			$em->persist($lunchTransaction);
    			$em->persist($lunchExpense);
    		}
    		$em->flush();
    		
    		return $this->redirectToRoute('travelExpense_show', array('id'=> $clone->getId()));
    	}
    	
    	return $this->render('dashboard/travelExpense/edit.html.twig', [
    			'travelExpense' => $clone,
    			'form' => $form->createView(),
    	]);
    }
    
    #[Route(path: "/dashboard/travelExpense/bookInBundle/withFilter", methods: ["POST"], name: "travelExpense_bookinBundle_withFilter")]
    public function book(TravelExpenseRepository $repo, Request $request, ManagerRegistry $doctrine): JsonResponse
    {
    	$dateFrom = $request->request->get('dateFrom', 0);
    	$dateTo = $request->request->get('dateTo', 0);
    	$queryBuilder = $repo->getFilteredQuery($dateFrom, $dateTo, true, false);
    	
    	$travelExpenses = $queryBuilder->getQuery()->getResult();
    	
    	$organizations = new ArrayCollection();
    	
    	foreach($travelExpenses as $te)
    	{
    		if(!$organizations->contains($te->getOrganization()))
    			$organizations->add($te->getOrganization());    		
    	}
    	
    	foreach($organizations as $org)
    	{
    		$myTes = array_filter($travelExpenses, function ($v) use ($org) {return $v->getOrganization() == $org;});
    		$c = new CreateTravelExpenseBundleCommand();
    		$c->travelExpenses = $myTes;
    		$c->organization = $org;
    		
    		$bundle = $this->getUser()->createTravelExpenseBundle($c);      	
	    	
    		$date = new \DateTime($request->request->get('date', null));
    		$entityManager = $doctrine->getManager();
	    	
    		$transaction = $bundle->setBooked($date, $this->getUser());
	    	
    		foreach($myTes as $te)
    		{
	    		$entityManager->persist($te);
    		}
    		$entityManager->persist($bundle);
    		$entityManager->persist($transaction);
    		$entityManager->flush();
    	}
    	
    	return new JsonResponse($travelExpenses);
    }
    
    /**
     * The allowance belongs to the travel expense's own organization and day, so both the
     * "already has a lunch" check and the created LunchExpense must use that same organization.
     */
    private function createAutomaticLunchExpense(TravelExpense $te, LunchExpenseRepository $lunchExpenses, LoggerInterface $logger): ?LunchExpense
    {
    	$organization = $te->getOrganization();
    	$date = $te->getDate();
    	$settings = $organization->getOrganizationSettings();
    	
    	if(!$settings->getAutoCreateLunch())
    	{
    		$logger->debug("Auto generating lunch transaction is off for ".$organization.". Moving on.");
    		return null;
    	}
    	
    	$existing = $lunchExpenses->countForOrganizationOnDay($organization, $date);
    	$logger->debug("Found ".$existing." existing LunchExpenses for ".$organization." on ".$date->format('Y-m-d'));
    	if($existing > 0)
    		return null;
    	
    	$clec = new CreateLunchExpenseCommand();
    	$clec->date = $date;
    	$clec->organization = $organization;
    	$clec->sum = $settings->getLunchValue();
    	$lunchExpense = $this->getUser()->createLunchExpense($clec);
    	$logger->debug("Created lunch expense ".$lunchExpense.". ");
    	
    	return $lunchExpense;
    }
}
