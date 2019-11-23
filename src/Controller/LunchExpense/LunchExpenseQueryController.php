<?php 

namespace App\Controller\LunchExpense;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\LunchExpense\LunchExpenseRepository;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\LunchExpenseBundle;
use Knp\Component\Pager\PaginatorInterface;

class LunchExpenseQueryController extends AbstractController
{    
	/**     
     * @Route("/dashboard/lunchExpense", methods={"GET"}, name="lunchExpense_index")
     */
	public function index(LunchExpenseRepository $lunchExpenses, Request $request, PaginatorInterface $paginator): Response
	{   		
		$dateFrom = $request->query->get('dateFrom', 0);
		$dateTo = $request->query->get('dateTo', 0);
		$booked = $request->query->get('booked', 'false') == 'true';
		$unbooked = $request->query->get('unbooked', 'true') == 'true';
		$queryBuilder = $lunchExpenses->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
		
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	//$myTEs = $lunchExpenses->findBy([], ['date' => 'DESC']);
    	return $this->render('dashboard/lunchExpense/index.html.twig', [
    			'pagination' => $pagination,  
    			
    	]);
    }     
    
    /**
     * @Route("/dashboard/lunchExpense/bundle/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="lunchExpenseBundle_show")
     */
    public function showBundle(LunchExpenseBundle $lunchExpenseBundle): Response
    {
    	return $this->render('dashboard/lunchExpense/index.html.twig', [
    			'pagination' => $lunchExpenseBundle->getLunchExpenses(),
    	]);
    } 
    
}
