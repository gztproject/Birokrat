<?php 

namespace App\Controller;

use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\TravelExpenseRepository;
use App\Entity\TravelExpense\TravelExpense;
use App\Form\TravelExpenseType;
use App\Entity\TravelExpense\TravelExpenseState;

class TravelExpenseController extends AbstractController
{    
	/**     
     * @Route("/dashboard/travelExpense", methods={"GET"}, name="travelExpense_index")
     */
	public function index(TravelExpenseRepository $travelExpenses): Response
    {     
    	$myTEs = $travelExpenses->findBy([], ['date' => 'DESC']);
    	return $this->render('dashboard/travelExpense/index.html.twig', ['travelExpenses' => $myTEs]);
    } 
    
    /**
     * @Route("/dashboard/travelExpense/new", methods={"GET", "POST"}, name="travelExpense_new")
     */
    public function new(Request $request)
    {
    	$te = new TravelExpense();
    	$state = $this->getDoctrine()->getRepository(TravelExpenseState::class)->findOneBy(['name'=>'new']);
    	$te->setState($state);
    	
    	$form = $this->createForm(TravelExpenseType::class, $te)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$state = $this->getDoctrine()->getRepository(TravelExpenseState::class)->findOneBy(['name'=>'submitted']);
    		$te->setState($state);
    		
    		$te->setEmployee($this->get('security.token_storage')->getToken()->getUser());
    		//ToDo: Get rate from organizationSettings
    		$te->setRate(0.37);
    		$te->calculateTotalDistance(); 
    		
    		
    		
    		$entityManager = $this->getDoctrine()->getManager();
    		foreach($te->getTravelStops() as $ts)
    		{
    			$entityManager->persist($ts);
    		}
    		
    		$entityManager->persist($te);
    		$entityManager->flush();
    		    		
    		return $this->redirectToRoute('travelExpense_index');
    	}
    	
    	return $this->render('dashboard/travelExpense/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }
    
}
