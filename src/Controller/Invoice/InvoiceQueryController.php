<?php 
namespace App\Controller\Invoice;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Invoice\Invoice;
use App\Repository\Invoice\InvoiceRepository;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Entity\Organization\Organization;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Entity\Invoice\InvoicePdfFactory;
use App\Http\InfiniteListResponder;

class InvoiceQueryController extends AbstractController
{    
    #[Route(path: "/dashboard/invoice", methods: ["GET"], name: "invoice_index")]
	public function index(InvoiceRepository $invoices, Request $request, PaginatorInterface $paginator, InfiniteListResponder $list): Response
    {   		
    	$queryBuilder = $invoices->getQuery();
    	$pagination = $list->paginate($paginator, $queryBuilder, $request);

    	if ($list->isPartial($request)) {
    		return $list->json($pagination, 'dashboard/invoice/_rows.html.twig', 'dashboard/invoice/_cards.html.twig', 'invoices');
    	}

    	return $this->render('dashboard/invoice/index.html.twig', [
    			'pagination' => $pagination,
    			'last_page' => $list->lastPage($pagination),
    	]);
    } 
    
    #[Route(path: "/dashboard/invoice/getNewNumber", methods: ["POST"], name: "invoice_getNewNumber")]
    public function getNewNumber(Request $request, ManagerRegistry $doctrine): JsonResponse
    {   
    	$issuer = $doctrine->getRepository(Organization::class)->findOneBy(['id'=>$request->request->get('issuerId', null)]);
    	$dateOfIssue = new \Datetime($request->request->get('dateOfIssue', 'now'));
    	$state = $request->request->get('state', 10);
    	try {
    		$data = InvoiceNumberFactory::factory($issuer, $state, $dateOfIssue, $doctrine)->generate();
    		$status = "ok";
    	} 
    	catch (Exception $e) 
    	{
    		$status = "error";
    		$data = $e->getMessage();    				
    	}
    	
    	return new JsonResponse(
    			array(
    					array(
    							'status'=>$status,
    							'data'=>array(
    									$data
    									)
    							)
    					)
    			);
    }
    
    #[Route(path: "/dashboard/invoice/getDefaultDueInDays", methods: ["POST"], name: "invoice_getDefaultDueInDays")]
    public function getDefaultDueInDays(Request $request, ManagerRegistry $doctrine): JsonResponse
    {    	
    	$issuer = $doctrine->getRepository(Organization::class)->findOneBy(['id'=>$request->request->get('issuerId', null)]);
    	try {
    		$data = $issuer->getOrganizationSettings()!=null?$issuer->getOrganizationSettings()->getDefaultPaymentDueIn():15;
    		$status = "ok";
    	}
    	catch (Exception $e)
    	{
    		$status = "error";
    		$data = $e->getMessage();
    	}
    	
    	return new JsonResponse(
    			array(
    					array(
    							'status'=>$status,
    							'data'=>array(
    									$data
    							)
    					)
    			)
    			);
    }
    
    #[Route(path: "/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/pdf", methods: ["GET"], name: "invoice_pdf")]
    public function getPdf(Invoice $invoice, TCPDFController $tcpdf, TranslatorInterface $translator): Response
    {
    	$content = InvoicePdfFactory::factory($invoice, $translator, $tcpdf, 'S')->generate();

    	return new Response((string) $content, 200, [
    		'Content-Type' => 'application/pdf',
    		'Content-Disposition' => 'inline; filename="invoice.pdf"',
    	]);
    }
    
    #[Route(path: "/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/print", methods: ["GET"], name: "invoice_print")]
    public function print(Invoice $invoice, TCPDFController $tcpdf, TranslatorInterface $translator): Response
    {
    	$content = InvoicePdfFactory::factory($invoice, $translator, $tcpdf, 'S', null, true)->generate();

    	return new Response((string) $content, 200, [
    		'Content-Type' => 'application/pdf',
    		'Content-Disposition' => 'inline; filename="invoice.pdf"',
    	]);
    }
    
    #[Route(path: "/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods: ["GET"], name: "invoice_show")]
    public function show(Invoice $invoice): Response
    {
    	return $this->render('dashboard/invoice/show.html.twig', [
    			'invoice' => $invoice
    	]);    	    	
    }
}