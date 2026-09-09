<?php 
namespace App\Controller\IncomingInvoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Repository\IncomingInvoice\IncomingInvoiceRepository;
use Knp\Component\Pager\PaginatorInterface;
use App\Http\InfiniteListResponder;

class IncomingInvoiceQueryController extends AbstractController
{    
    #[Route(path: "/dashboard/incomingInvoice", methods: ["GET"], name: "incomingInvoice_index")]
	public function index(IncomingInvoiceRepository $incomingInvoices, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
    {   		
    	$queryBuilder = $incomingInvoices->getQuery();
    	$pagination = $list->paginate($paginator, $queryBuilder, $request);

    	if ($list->isPartial($request)) {
    		return $list->json($pagination, 'dashboard/incomingInvoice/_rows.html.twig', 'dashboard/incomingInvoice/_cards.html.twig', 'invoices');
    	}

    	return $this->render('dashboard/incomingInvoice/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    }
    
    #[Route(path: "/dashboard/incomingInvoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods: ["GET"], name: "incomingInvoice_show")]
    public function show(IncomingInvoice $invoice): Response
    {
    	return $this->render('dashboard/incomingInvoice/show.html.twig', [
    			'invoice' => $invoice
    	]);    	    	
    }
}