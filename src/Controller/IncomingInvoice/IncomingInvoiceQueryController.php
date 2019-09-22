<?php 
namespace App\Controller\IncomingInvoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Repository\IncomingInvoice\IncomingInvoiceRepository;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;

class IncomingInvoiceQueryController extends AbstractController
{    
    /**
     * @Route("/dashboard/incomingInvoice", methods={"GET"}, name="incomingInvoice_index")
     */
	public function index(IncomingInvoiceRepository $incomingInvoices, Request $request, PaginatorInterface $paginator): Response
    {   		
    	$queryBuilder = $incomingInvoices->getQuery();
    	
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	//$myInvoices = $invoices->findBy([], ['number' => 'DESC']);
    	return $this->render('dashboard/incomingInvoice/index.html.twig', [
    			'pagination' => $pagination,    			
    	]);
    }
    
    /**
     * @Route("/dashboard/incomingInvoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods={"GET"}, name="incomingInvoice_show")
     */
    public function show(IncomingInvoice $invoice): Response
    {
    	return $this->render('dashboard/incomingInvoice/show.html.twig', [
    			'invoice' => $invoice
    	]);    	    	
    }
}