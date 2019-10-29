<?php 

namespace App\Controller\TravelExpense;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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

class TravelExpenseCommandController extends AbstractController
{    
	/**
     * @Route("/dashboard/travelExpense/new", methods={"GET", "POST"}, name="travelExpense_new")
     */
	public function new(Request $request, TravelExpenseRepository $travelExpenses, LoggerInterface $logger): Response
    {
    	$c = new CreateTravelExpenseCommand();    	
    	    	
    	$form = $this->createForm(TravelExpenseType::class, $c)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$c->employee = $this->getUser();    		
    		
    		$te = $this->getUser()->createTravelExpense($c);  
    		
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($c->travelStopCommands as $tsc)
    		{
    			$ts = $te->createTravelStop($tsc);
    			$em->persist($ts);
    		}
    		
    		$transaction = $te->setNew($this->getUser());
    		
    		//If this is the first travelExpense of the day:
    		$query = $travelExpenses->getFilteredQuery($c->date->getTimestamp(), $c->date->getTimestamp(), true, true)->getQuery();
    		$query->execute();
    		$logger->debug("Found ".count($query->getArrayResult())." existing TravelExpenses on ".date("r",$c->date->getTimestamp()));
    		if(count($query->getArrayResult()) == 0)
    		{
    				$logger->debug("Auto renerating lunch transaction is set to ".$c->organization->getOrganizationSettings()->getAutoCreateLunch());
    				if($c->organization->getOrganizationSettings()->getAutoCreateLunch())
    				{
    					$clec = new CreateLunchExpenseCommand();
    					$clec->date = $c->date;
    					$clec->organization = $c->organization;
    					$clec->sum = $c->organization->getOrganizationSettings()->getLunchValue();
    					$lunchExpense = new LunchExpense($clec, $this->getUser());
    					
    					$lunchTransaction =  $lunchExpense->setNew($this->getUser());
    				}
    				else $logger->debug("Auto generating lunch transaction is off. Moving on.");
    				
    				
    			if(isset($lunchExpense))
    				$logger->debug("Created lunch expense ".$lunchExpense.". ");
    				else
    					$logger->debug("Lunch expense not created.");
    		}
    		$em->persist($te);
    		$em->persist($transaction);
    		if(isset($lunchExpense))
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
    
    /**
     * Displays a form to edit an existing TravelExpense.
     *
     * @Route("/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="travelExpense_edit")
     */
    public function edit(Request $request, TravelExpense $te, LoggerInterface $logger): Response
    {
    	$updateTECommand = new UpdateTravelExpenseCommand();
    	$te->mapTo($updateTECommand);
    	
    	foreach($te->getTravelStops() as $ts)
    	{
    		$utsc = new UpdateTravelStopCommand();
    		$ts->mapTo($utsc);
    		array_push($updateTECommand->travelStopCommands, $utsc);
    	}
    	
    	$form = $this->createForm(TravelExpenseType::class, $updateTECommand)
    		->add('saveAndCreateNew', SubmitType::class);
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$te->update($updateTECommand, $this->getUser());    		
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($te->getTravelStops() as $ts)
    		{
    			$logger->debug("Persisting TravelStop ".$ts.". ");
    			$em->persist($ts);
    		}
    		
    		$logger->debug("Persisting TravelExpense ".$te.". ");
    		//ToDo: Update Transaction
    		$em->persist($te);
    		$em->flush();
    		
    		return $this->redirectToRoute('travelExpense_show', array('id'=> $te->getId()));
    	}
    	
    	return $this->render('dashboard/travelExpense/edit.html.twig', [
    			'travelExpense' => $te,
    			'form' => $form->createView(),
    	]);
    }
    
    /**
     * Clones an existing invoice and opens it in editor.
     *
     * @Route("/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone",methods={"GET", "POST"}, name="travelExpense_clone")
     */
    public function clone(Request $request, TravelExpense $te, TravelExpenseRepository $travelExpenses, LoggerInterface $logger): Response
    {
    	$clone = $te->clone($this->getUser());
    	
    	$updateTECommand = new UpdateTravelExpenseCommand();
    	$clone->mapTo($updateTECommand);
    	
    	foreach($clone->getTravelStops() as $ts)
    	{
    		$utsc = new UpdateTravelStopCommand();
    		$ts->mapTo($utsc);
    		array_push($updateTECommand->travelStopCommands, $utsc);
    	}
    	
    	$form = $this->createForm(TravelExpenseType::class, $updateTECommand)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$clone->update($updateTECommand, $this->getUser());
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($clone->getTravelStops() as $ts)
    		{
    			$logger->debug("Persisting Cloned TravelStop ".$ts.". ");
    			$em->persist($ts);
    		}
    		    		
    		$transaction = $clone->setNew($this->getUser());
    		//If this is the first travelExpense of the day:
    		$query = $travelExpenses->getFilteredQuery($updateTECommand->date->getTimestamp(), $updateTECommand->date->getTimestamp(), true, true)->getQuery();
    		$query->execute();
    		$logger->debug("Found ".count($query->getArrayResult())." existing TravelExpenses on ".date("r",$updateTECommand->date->getTimestamp()));
    		if(count($query->getArrayResult()) == 0)
    		{
    			$logger->debug("Auto renerating lunch transaction is set to ".$updateTECommand->organization->getOrganizationSettings()->getAutoCreateLunch());
    			if($updateTECommand->organization->getOrganizationSettings()->getAutoCreateLunch())
    			{
    				$clec = new CreateLunchExpenseCommand();
    				$clec->date = $updateTECommand->date;
    				$clec->organization = $updateTECommand->organization;
    				$clec->sum = $updateTECommand->organization->getOrganizationSettings()->getLunchValue();
    				$lunchExpense = new LunchExpense($clec, $this->getUser());
    				
    				$lunchTransaction =  $lunchExpense->setNew($this->getUser());
    			}
    			else $logger->debug("Auto generating lunch transaction is off. Moving on.");
    			
    			
    			if(isset($lunchExpense))
    				$logger->debug("Created lunch expense ".$lunchExpense.". ");
    				else
    					$logger->debug("Lunch expense not created.");
    		}
    		$em->persist($clone);
    		$logger->debug("Persisting Cloned TravelExpense ".$clone.". ");
    		$em->persist($transaction);
    		if(isset($lunchExpense))
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
    
    /**
     * @Route("/dashboard/travelExpense/bookInBundle/withFilter", methods={"POST"}, name="travelExpense_bookinBundle_withFilter")
     */
    public function book(TravelExpenseRepository $repo, Request $request): JsonResponse
    {
    	$dateFrom = $request->request->get('dateFrom', 0);
    	$dateTo = $request->request->get('dateTo', 0);
    	$booked = $request->request->get('booked', 'false') == 'true';
    	$unbooked = $request->request->get('unbooked', 'true') == 'true';
    	$queryBuilder = $repo->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
    	
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
    		$entityManager = $this->getDoctrine()->getManager();
	    	
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
    
}
