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
use Knp\Component\Pager\PaginatorInterface;

class TravelExpenseController extends AbstractController
{    
	/**     
     * @Route("/dashboard/travelExpense", methods={"GET"}, name="travelExpense_index")
     */
	public function index(TravelExpenseRepository $travelExpenses, Request $request, PaginatorInterface $paginator): Response
	{   		
		$queryBuilder = $travelExpenses->getQuery();
		
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	//$myTEs = $travelExpenses->findBy([], ['date' => 'DESC']);
    	return $this->render('dashboard/travelExpense/index.html.twig', [
    			'pagination' => $pagination,    			
    	]);
    } 
    
    /**
     * @Route("/dashboard/travelExpense/new", methods={"GET", "POST"}, name="travelExpense_new")
     */
    public function new(Request $request)
    {
    	$te = new TravelExpense();    	
    	$te->setState(00);
    	
    	$form = $this->createForm(TravelExpenseType::class, $te)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$te->setState(10);
    		
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
    
    /**
     * @Route("/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="travelExpense_show")
     */
    public function show(TravelExpense $travelExpense): Response
    {           
        return $this->render('dashboard/travelExpense/show.html.twig', [
        		'travelExpense' => $travelExpense,
        ]);
    }
    
}
