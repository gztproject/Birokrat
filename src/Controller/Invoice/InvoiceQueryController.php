<?php 
namespace App\Controller\Invoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice\Invoice;
use App\Repository\Invoice\InvoiceRepository;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Entity\Organization\Organization;
use WhiteOctober\TCPDFBundle\Controller\TCPDFController;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Entity\Invoice\InvoicePdfFactory;

class InvoiceQueryController extends AbstractController
{    
    /**
     * @Route("/dashboard/invoice", methods={"GET"}, name="invoice_index")
     */
	public function index(InvoiceRepository $invoices, Request $request, PaginatorInterface $paginator): Response
    {   		
    	$queryBuilder = $invoices->getQuery();
    	
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	//$myInvoices = $invoices->findBy([], ['number' => 'DESC']);
    	return $this->render('dashboard/invoice/index.html.twig', [
    			'pagination' => $pagination,    			
    	]);
    } 
    
    /**
     * @Route("/dashboard/invoice/getNewNumber", methods={"POST"}, name="invoice_getNewNumber")
     */
    public function getNewNumber(Request $request): JsonResponse
    {    	
    	$doctrine = $this->getDoctrine();
    	$issuer = $doctrine->getRepository(Organization::class)->findOneBy(['id'=>$request->request->get('issuerId', null)]);
    	try {
    		$data = InvoiceNumberFactory::factory($issuer, 00, $doctrine)->generate();
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
    
    /**
     * @Route("/dashboard/invoice/getDefaultDueInDays", methods={"POST"}, name="invoice_getDefaultDueInDays")
     */
    public function getDefaultDueInDays(Request $request): JsonResponse
    {
    	$doctrine = $this->getDoctrine();
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
    
    /**
     * @Route("/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/pdf", methods={"GET"}, name="invoice_pdf")
     */
    public function getPdf(Invoice $invoice, TCPDFController $tcpdf, TranslatorInterface $translator): Response
    {
    	return InvoicePdfFactory::factory($invoice, $translator, $tcpdf, 'I')->generate();    	
    }
    
    /**
     * @Route("/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods={"GET"}, name="invoice_show")
     */
    public function show(Invoice $invoice): Response
    {
    	return $this->render('dashboard/invoice/show.html.twig', [
    			'invoice' => $invoice
    	]);    	    	
    }
}