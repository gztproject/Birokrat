<?php 
namespace App\Controller\Invoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Form\Invoice\InvoiceType;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\CreateInvoiceCommand;

class InvoiceCommandController extends AbstractController
{    
       
    /**
     * @Route("/dashboard/invoice/new", methods={"GET", "POST"}, name="invoice_new")
     */
    public function new(Request $request): Response
    {
    	$createInvoiceCommand = new CreateInvoiceCommand();
    	
    	$form = $this->createForm(InvoiceType::class, $createInvoiceCommand)
    	->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		 		
    		$invoice = $this->getUser()->createInvoice($createInvoiceCommand);
    		    		
    		$em = $this->getDoctrine()->getManager();
    		foreach($createInvoiceCommand->createInvoiceItemCommands as $c)
    		{
    			$ii = $invoice->createInvoiceItem($c);
    			$em->persist($ii);
    		}
    		    		
    		$em->persist($invoice);
    		$em->flush();
    		
    		return $this->redirectToRoute('invoice_pdf_debug', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }
    
    /**
     * @Route("/dashboard/invoice/issue", methods={"POST"}, name="invoice_issue")
     */
    public function issue(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$konto = $this->getDoctrine()->getRepository(Konto::class)->findOneBy(['number'=>760]); //760 for services or 762 for goods
    	$date = new \DateTime($request->request->get('date', null));
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$number = InvoiceNumberFactory::factory($invoice->getIssuer, 10, $entityManager)->generate();
    	$transaction = $invoice->setIssued($konto, $date, $number, $this->getUser());
    	
    	$entityManager->persist($invoice);
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return $this->render('dashboard/invoice/pdf.html.twig', [
    			'invoice' => $invoice
    	]);
    }
       
    /**
     * @Route("/dashboard/invoice/pay", methods={"POST"}, name="invoice_set_paid")
     */
    public function setPaid(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$date = new \DateTime($request->request->get('date', null));    	
    	$entityManager = $this->getDoctrine()->getManager();
    	    	
    	$invoice->setPaid($date, $this->getUser());
    	    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }    
    
    /**
     * @Route("/dashboard/invoice/cancel", methods={"POST"}, name="invoice_cancel")
     */
    public function cancel(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$invoice->cancel($request->request->get('reason', ""));
    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }
}