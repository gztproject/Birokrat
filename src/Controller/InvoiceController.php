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
    	
    	$form = $this->createForm(InvoiceType::class, $invoice)
    	->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		
    		$state = $this->getDoctrine()->getRepository(InvoiceState::class)->findOneBy(['name'=>'submitted']);
    		$invoice->setState($state);    		
    		
    		$entityManager = $this->getDoctrine()->getManager();
    		foreach($invoice->getInvoiceItems() as $ii)
    		{
    			$entityManager->persist($ii);
    		}
    		    		
    		$entityManager->persist($invoice);
    		$entityManager->flush();
    		
    		return $this->redirectToRoute('invoice_index');
    	}
    	
    	return $this->render('dashboard/invoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    } 
}