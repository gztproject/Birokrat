<?php 

namespace App\Controller\TravelExpense;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\TravelExpenseRepository;
use App\Entity\Konto\Konto;
use App\Entity\TravelExpense\TravelExpense;
use App\Form\TravelExpenseType;
use Knp\Component\Pager\PaginatorInterface;
use App\Entity\TravelExpense\TravelExpenseBundle;
use Symfony\Component\HttpFoundation\JsonResponse;

class TravelExpenseController extends AbstractController
{    
	/**     
     * @Route("/dashboard/travelExpense", methods={"GET"}, name="travelExpense_index")
     */
	public function index(TravelExpenseRepository $travelExpenses, Request $request, PaginatorInterface $paginator): Response
	{   		
		$dateFrom = $request->query->get('dateFrom', 0);
		$dateTo = $request->query->get('dateTo', 0);
		$booked = $request->query->get('booked', 'false') == 'true';
		$unbooked = $request->query->get('unbooked', 'true') == 'true';
		$queryBuilder = $travelExpenses->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
		
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
    	    	
    	$form = $this->createForm(TravelExpenseType::class, $te)
    		->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$te->setUnbooked();
    		
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
