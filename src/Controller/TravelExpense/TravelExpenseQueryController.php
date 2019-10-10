<?php 

namespace App\Controller\TravelExpense;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TravelExpense\TravelExpenseRepository;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use Knp\Component\Pager\PaginatorInterface;

class TravelExpenseQueryController extends AbstractController
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
     * @Route("/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="travelExpense_show")
     */
    public function show(TravelExpense $travelExpense): Response
    {           
        return $this->render('dashboard/travelExpense/show.html.twig', [
        		'travelExpense' => $travelExpense,
        ]);
    } 
    
    /**
     * @Route("/dashboard/travelExpense/bundle/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="travelExpenseBundle_show")
     */
    public function showBundle(TravelExpenseBundle $travelExpenseBundle): Response
    {
    	return $this->render('dashboard/travelExpense/index.html.twig', [
    			'pagination' => $travelExpenseBundle->getTravelExpenses(),
    	]);
    } 
    
}
