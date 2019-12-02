<?php 

namespace App\Controller\LunchExpense;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\LunchExpense\LunchExpenseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use Psr\Log\LoggerInterface;
use App\Entity\LunchExpense\CreateLunchExpenseBundleCommand;
use App\Form\LunchExpense\LunchExpenseType;
use Doctrine\Common\Collections\ArrayCollection;

class LunchExpenseCommandController extends AbstractController
{    
	/**
     * @Route("/dashboard/lunchExpense/new", methods={"GET, POST"}, name="lunchExpense_new")
     */
	public function new(Request $request, LunchExpenseRepository $lunchExpenses, LoggerInterface $logger): Response
    {
    	$c = new CreateLunchExpenseCommand();    	
    	    	
    	$form = $this->createForm(LunchExpenseType::class, $c)
    	->add('saveAndCreateNew', SubmitType::class);
    	   	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$c->employee = $this->getUser();    		
    		
    		$te = $this->getUser()->createLunchExpense($c);  
    		
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($c->lunchStopCommands as $tsc)
    		{
    			$ts = $te->createLunchStop($tsc);
    			$em->persist($ts);
    		}
    		
    		$transaction = $te->setNew($this->getUser());
    		
    		
    		$em->persist($te);
    		$em->persist($transaction);    		
    		
    		$em->flush();
    		    		
    		return $this->redirectToRoute('lunchExpense_index');
    	}
    	
    	return $this->render('dashboard/lunchExpense/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }   
       
    
    /**
     * @Route("/dashboard/lunchExpense/bookInBundle/withFilter", methods={"POST"}, name="lunchExpense_bookinBundle_withFilter")
     */
    public function book(LunchExpenseRepository $repo, Request $request): JsonResponse
    {
    	$dateFrom = $request->request->get('dateFrom', 0);
    	$dateTo = $request->request->get('dateTo', 0);
    	$queryBuilder = $repo->getFilteredQuery($dateFrom, $dateTo, true, false);
    	
    	$lunchExpenses = $queryBuilder->getQuery()->getResult();
    	
    	$organizations = new ArrayCollection();
    	
    	foreach($lunchExpenses as $te)
    	{
    		if(!$organizations->contains($te->getOrganization()))
    			$organizations->add($te->getOrganization());    		
    	}
    	
    	foreach($organizations as $org)
    	{
    		$myTes = array_filter($lunchExpenses, function ($v) use ($org) {return $v->getOrganization() == $org;});
    		$c = new CreateLunchExpenseBundleCommand();
    		$c->lunchExpenses = $myTes;
    		$c->organization = $org;
    		
    		$bundle = $this->getUser()->createLunchExpenseBundle($c);      	
	    	
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
    	
    	return new JsonResponse($lunchExpenses);
    }
    
}
