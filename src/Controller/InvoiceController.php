<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoiceState;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use Symfony\Component\Validator\Constraints\DateTime;

class InvoiceController extends AbstractController
{    
    /**
     * @Route("/dashboard/invoice", methods={"GET"}, name="invoice_index")
     */
    public function index(InvoiceRepository $invoices): Response
    {                      
        $myInvoices = $invoices->findAll();
        return $this->render('dashboard/invoice/index.html.twig', ['invoices' => $myInvoices]);
    } 
    
    /**
     * @Route("/dashboard/invoice/new", methods={"GET", "POST"}, name="invoice_new")
     */
    public function new(Request $request): Response
    {
    	$invoice = new Invoice();
    	$state = $this->getDoctrine()->getRepository(InvoiceState::class)->findOneBy(['name'=>'new']);
    	$invoice->setState($state);
    	
    	$invoice->setIssuedBy($this->get('security.token_storage')->getToken()->getUser());
    	
    	$invoice->setIssuer($this->get('security.token_storage')->getToken()->getUser()->getOrganizations()[0]);
    	
    	$invoice->setNumber($invoice->getNewInvoiceNumber($invoice->getIssuer(), $this->getDoctrine()));
    	
    	$invoice->setDateOfIssue(\DateTime::createFromFormat('U', date("U")));
    	$invoice->setDueInDays($invoice->getIssuer()->getOrganizationSettings()->getDefaultPaymentDueIn());
    	
    	$form = $this->createForm(InvoiceType::class, $invoice)
    	->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$state = $this->getDoctrine()->getRepository(InvoiceState::class)->findOneBy(['name'=>'draft']);
    		$invoice->setState($state);   
    		$invoice->calculateReference();
    		$invoice->calculateTotals();    		
    		
    		$entityManager = $this->getDoctrine()->getManager();
    		foreach($invoice->getInvoiceItems() as $ii)
    		{
    			$entityManager->persist($ii);
    		}
    		    		
    		$entityManager->persist($invoice);
    		$entityManager->flush();
    		
    		return $this->redirectToRoute('invoice_pdf_debug', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    } 
       
    /**
     * @Route("/dashboard/invoice/pay/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="invoice_set_paid")
     */
    public function setPaid(Invoice $invoice): Response
    {
    	//ToDo: check if not already paid etc...
    	$entityManager = $this->getDoctrine()->getManager();
    	$invoice->setDatePaid(\DateTime::createFromFormat('U', date("U")));
    	$state = $this->getDoctrine()->getRepository(InvoiceState::class)->findOneBy(['name'=>'paid']);
    	$invoice->setState($state);
    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }
    
    /**
     * @Route("/dashboard/invoice/pdf-debug/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="invoice_pdf_debug")
     */
    public function generatePdfDebug(Invoice $invoice): Response
    {
    	// Retrieve the HTML generated in our twig file
    	return $this->render('dashboard/invoice/pdf.html.twig', [
    			'invoice' => $invoice
    	]);    	    	
    }
}