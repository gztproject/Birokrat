<?php 
namespace App\Controller\IncomingInvoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Exception;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Form\IncomingInvoice\IncomingInvoiceType;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\UpdateIncomingInvoiceCommand;


class IncomingInvoiceCommandController extends AbstractController
{    
       
    /**
     * @Route("/dashboard/incomingInvoice/new", methods={"GET", "POST"}, name="incomingInvoice_new")
     */
    public function new(Request $request): Response
    {
    	$c = new CreateIncomingInvoiceCommand();
    	
    	$form = $this->createForm(IncomingInvoiceType::class, $c);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		 		
    		$invoice = $this->getUser()->createIncomingInvoice($c);
    		if($c->paidOnSpot)
    			$transaction = $invoice->setReceivedAndPaid(new \DateTime('now'), $this->getUser(), $c->paymentMethod, $c->debitKonto);
    		else
    			$transaction = $invoice->setReceived(new \DateTime('now'), $this->getUser(), $c->debitKonto);
    		    		
    		$em = $this->getDoctrine()->getManager();
    		    		    		
    		$em->persist($invoice);
    		$em->persist($transaction);
    		$em->flush();
    		
    		return $this->redirectToRoute('incomingInvoice_show', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/incomingInvoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }
    
    /**
     * Clones the IncomingInvoice and displays a form to edit an existing incomingInvoice entity.
     *
     * @Route("/dashboard/incomingInvoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone",methods={"GET", "POST"}, name="incomingInvoice_clone")
     */
    public function clone(Request $request, IncomingInvoice $invoice): Response
    {
    	$clone = $invoice->clone($this->getUser());
    	
    	$c = new UpdateIncomingInvoiceCommand();
    	$clone->mapTo($c);
    	
    	
    	$form = $this->createForm(IncomingInvoiceType::class, $c);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$clone->update($c, $this->getUser());
    		$transaction = $clone->setReceived(new \DateTime('now'), $this->getUser());
    		$em = $this->getDoctrine()->getManager();
    		
    		$em->persist($clone);
    		$em->persist($transaction);
    		$em->flush();
    		
    		return $this->redirectToRoute('incomingInvoice_show', array('id'=> $clone->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/edit.html.twig', [
    			'invoice' => $clone,
    			'form' => $form->createView(),
    	]);
    }
    
       
    /**
     * @Route("/dashboard/incomingInvoice/pay", methods={"POST"}, name="incomingInvoice_pay")
     */
    public function pay(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(IncomingInvoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$date = new \DateTime($request->request->get('date', null));    	
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	    	
    	$transaction = $invoice->setPaid($date, $this->getUser(), $request->request->get('mode', null));
    	    	
    	$entityManager->persist($invoice);  
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }    
    
    /**
     * @Route("/dashboard/incomingInvoice/reject", methods={"POST"}, name="incomingInvoice_reject")
     */
    public function reject(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(IncomingInvoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$invoice->reject($request->request->get('reason', ""));
    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }
    
    //ToDo: Add a refund handler...
}