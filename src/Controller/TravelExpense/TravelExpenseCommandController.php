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

class TravelExpenseCommandController extends AbstractController
{    
	/**
     * @Route("/dashboard/travelExpense/new", methods={"GET", "POST"}, name="travelExpense_new")
     */
    public function new(Request $request): Response
    {
    	$c = new CreateTravelExpenseCommand();    	
    	    	
    	$form = $this->createForm(TravelExpenseType::class, $c)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$c->employee = $this->getUser();
    		//ToDo: Get rate from organizationSettings
    		$c->rate = 0.37;
    		
    		$te = $this->getUser()->createTravelExpense($c);    		
    		
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($c->createTravelStopCommands as $tsc)
    		{
    			$ts = $te->createTravelStop($tsc);
    			$em->persist($ts);
    		}
    		
    		$em->persist($te);
    		$em->flush();
    		    		
    		return $this->redirectToRoute('travelExpense_index');
    	}
    	
    	return $this->render('dashboard/travelExpense/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }    
    
    /**
     * @Route("/dashboard/travelExpense/bookInBundle/withFilter", methods={"POST"}, name="travelExpense_bookinBundle_withFilter")
     */
    public function issue(TravelExpenseRepository $repo, Request $request): JsonResponse
    {
    	$dateFrom = $request->request->get('dateFrom', 0);
    	$dateTo = $request->request->get('dateTo', 0);
    	$booked = $request->request->get('booked', 'false') == 'true';
    	$unbooked = $request->request->get('unbooked', 'true') == 'true';
    	$queryBuilder = $repo->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
    	
    	$travelExpenses = $queryBuilder->getQuery()->getResult();
    	    	
    	$bundle = new TravelExpenseBundle();
    	foreach($travelExpenses as $te)
    	{
    		$bundle->addTravelExpense($te);
    	}
    	
    	$konto = $this->getDoctrine()->getRepository(Konto::class)->findOneBy(['number'=>486]); //486 	POVRAČILA STROŠKOV S.P. POSAMEZNIKOM
    	$date = new \DateTime($request->request->get('date', null));
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$transaction = $bundle->setBooked($konto, $date);
    	
    	$entityManager->persist($bundle);
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return new JsonResponse($travelExpenses);
    }
    
}
