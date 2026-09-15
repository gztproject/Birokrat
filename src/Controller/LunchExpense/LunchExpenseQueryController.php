<?php 

namespace App\Controller\LunchExpense;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\LunchExpense\LunchExpenseRepository;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\LunchExpense\LunchExpenseBundle;
use Knp\Component\Pager\PaginatorInterface;
use App\Http\InfiniteListResponder;

class LunchExpenseQueryController extends AbstractController
{    
	#[Route(path: "/dashboard/lunchExpense", methods: ["GET"], name: "lunchExpense_index")]
	public function index(LunchExpenseRepository $lunchExpenses, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
	{   		
		$dateFrom = $request->query->get('dateFrom', 0);
		$dateTo = $request->query->get('dateTo', 0);
		$booked = $request->query->get('booked', 'false') == 'true';
		$unbooked = $request->query->get('unbooked', 'true') == 'true';
		$queryBuilder = $lunchExpenses->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
		$pagination = $list->paginate($paginator, $queryBuilder, $request);

		if ($list->isPartial($request)) {
			return $list->json($pagination, 'dashboard/lunchExpense/_rows.html.twig', 'dashboard/lunchExpense/_cards.html.twig', 'lunchExpenses', [
				'ShowCBs' => true,
			]);
		}

    	return $this->render('dashboard/lunchExpense/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    }     
    
    #[Route(path: "/dashboard/lunchExpense/bundle/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods: ["GET"], name: "lunchExpenseBundle_show")]
    public function showBundle(LunchExpenseBundle $lunchExpenseBundle, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
    {
    	$pagination = $list->paginate($paginator, $lunchExpenseBundle->getLunchExpenses(), $request);

    	if ($list->isPartial($request)) {
    		return $list->json($pagination, 'dashboard/lunchExpense/_rows.html.twig', 'dashboard/lunchExpense/_cards.html.twig', 'lunchExpenses', [
    			'ShowCBs' => true,
    		]);
    	}

    	return $this->render('dashboard/lunchExpense/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    } 
    
}
