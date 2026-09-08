<?php 

namespace App\Controller\LunchExpense;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LunchExpense\LunchExpenseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\LunchExpense\CreateLunchExpenseCommand;
use App\Entity\LunchExpense\CreateLunchExpenseBundleCommand;
use App\Form\LunchExpense\LunchExpenseType;
use Doctrine\Common\Collections\ArrayCollection;

class LunchExpenseCommandController extends AbstractController
{    
	#[Route(path: "/dashboard/lunchExpense/new", methods: ["GET", "POST"], name: "lunchExpense_new")]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
    	$c = new CreateLunchExpenseCommand();
    	$c->date = new \DateTime();
    	$organization = $this->getUser()?->getOrganizations()->first();
    	if ($organization) {
    		$c->organization = $organization;
    		$c->sum = $organization->getOrganizationSettings()->getLunchValue();
    	}

    	$form = $this->createForm(LunchExpenseType::class, $c)
    	->add('saveAndCreateNew', SubmitType::class);

    	$form->handleRequest($request);
    	   	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$te = $this->getUser()->createLunchExpense($c);
    		$transaction = $te->setNew($this->getUser());

    		$em = $doctrine->getManager();
    		$em->persist($te);
    		$em->persist($transaction);
    		$em->flush();
    		    		
    		return $this->redirectToRoute('lunchExpense_index');
    	}
    	
    	return $this->render('dashboard/lunchExpense/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }   
       
    
    #[Route(path: "/dashboard/lunchExpense/bookInBundle/withFilter", methods: ["POST"], name: "lunchExpense_bookinBundle_withFilter")]
    public function book(LunchExpenseRepository $repo, Request $request, ManagerRegistry $doctrine): JsonResponse
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
    	
    	return new JsonResponse($lunchExpenses);
    }
    
}
