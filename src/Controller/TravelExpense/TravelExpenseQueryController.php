<?php 

namespace App\Controller\TravelExpense;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TravelExpense\TravelExpenseRepository;
use App\Entity\TravelExpense\TravelExpense;
use App\Entity\TravelExpense\TravelExpenseBundle;
use Knp\Component\Pager\PaginatorInterface;
use App\Http\InfiniteListResponder;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\TravelExpense\TravelExpensePdfFactory;
use Qipsius\TCPDFBundle\Controller\TCPDFController;

class TravelExpenseQueryController extends AbstractController
{    
	#[Route(path: "/dashboard/travelExpense", methods: ["GET"], name: "travelExpense_index")]
	public function index(TravelExpenseRepository $travelExpenses, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
	{   		
		$dateFrom = $request->query->get('dateFrom', 0);
		$dateTo = $request->query->get('dateTo', 0);
		$booked = $request->query->get('booked', 'false') == 'true';
		$unbooked = $request->query->get('unbooked', 'true') == 'true';
		$queryBuilder = $travelExpenses->getFilteredQuery($dateFrom, $dateTo, $unbooked, $booked);
		$pagination = $list->paginate($paginator, $queryBuilder, $request);

		if ($list->isPartial($request)) {
			return $list->json($pagination, 'dashboard/travelExpense/_rows.html.twig', 'dashboard/travelExpense/_cards.html.twig', 'travelExpenses', [
				'ShowCBs' => true,
			]);
		}

    	return $this->render('dashboard/travelExpense/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    } 
    
    #[Route(path: "/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods: ["GET"], name: "travelExpense_show")]
    public function show(TravelExpense $travelExpense): Response
    {           
        return $this->render('dashboard/travelExpense/show.html.twig', [
        		'travelExpense' => $travelExpense,
        ]);
    } 
    
    #[Route(path: "/dashboard/travelExpense/bundle/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods: ["GET"], name: "travelExpenseBundle_show")]
    public function showBundle(TravelExpenseBundle $travelExpenseBundle, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
    {
    	$pagination = $list->paginate($paginator, $travelExpenseBundle->getTravelExpenses(), $request);

    	if ($list->isPartial($request)) {
    		return $list->json($pagination, 'dashboard/travelExpense/_rows.html.twig', 'dashboard/travelExpense/_cards.html.twig', 'travelExpenses', [
    			'ShowCBs' => true,
    		]);
    	}

    	return $this->render('dashboard/travelExpense/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    } 
    
    #[Route(path: "/dashboard/travelExpense/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/pdf", methods: ["GET"], name: "travelExpense_pdf")]
    public function getPdf(TravelExpense $travelExpense, TCPDFController $tcpdf, TranslatorInterface $translator): Response
    {
    	$content = TravelExpensePdfFactory::factory($travelExpense, $translator, $tcpdf, 'S')->generate();

    	return new Response((string) $content, 200, [
    		'Content-Type' => 'application/pdf',
    		'Content-Disposition' => 'inline; filename="travel-expense.pdf"',
    	]);
    }
    
}
