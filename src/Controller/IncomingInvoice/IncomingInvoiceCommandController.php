<?php 
namespace App\Controller\IncomingInvoice;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Form\IncomingInvoice\IncomingInvoiceType;
use App\Entity\IncomingInvoice\CreateIncomingInvoiceCommand;
use App\Entity\IncomingInvoice\UpdateIncomingInvoiceCommand;
use App\Service\Ledger\BankFeeFactory;


class IncomingInvoiceCommandController extends AbstractController
{    
       
    #[Route(path: "/dashboard/incomingInvoice/new", methods: ["GET", "POST"], name: "incomingInvoice_new")]
    public function new(Request $request, ManagerRegistry $doctrine, BankFeeFactory $bankFees): Response
    {
    	$c = new CreateIncomingInvoiceCommand();
    	
    	$form = $this->createForm(IncomingInvoiceType::class, $c);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$invoice = $this->getUser()->createIncomingInvoice($c);
    		$allocations = $c->allocations ?? [];
    		if($c->paidOnSpot)
    			$transactions = $invoice->setReceivedAndPaid(new \DateTime('now'), $this->getUser(), $c->paymentMethod, $c->debitKonto, $allocations);
    		else
    			$transactions = $invoice->setReceived(new \DateTime('now'), $this->getUser(), $c->debitKonto, $allocations);
    		    		
    		$em = $doctrine->getManager();
    		$em->persist($invoice);
    		BankFeeFactory::persist($em, $transactions);
    		$fee = $bankFees->createIfNeeded($invoice->getRecepient(), new \DateTime('now'), $c->bankCost ?? 0, $this->getUser(), $invoice, $transactions[0]);
    		if ($fee) {
    			$em->persist($fee);
    		}
    		$em->flush();
    		
    		return $this->redirectToRoute('incomingInvoice_show', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/incomingInvoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }
    
    #[Route(path: "/dashboard/incomingInvoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone", methods: ["GET", "POST"], name: "incomingInvoice_clone")]
    public function clone(Request $request, IncomingInvoice $invoice, ManagerRegistry $doctrine): Response
    {
    	$clone = $invoice->clone($this->getUser());
    	
    	$c = new UpdateIncomingInvoiceCommand();
    	$clone->mapTo($c);
    	
    	
    	$form = $this->createForm(IncomingInvoiceType::class, $c);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$clone->update($c, $this->getUser());
    		$transactions = $clone->setReceived(new \DateTime('now'), $this->getUser(), $c->debitKonto ?? null, $c->allocations ?? []);
    		$em = $doctrine->getManager();
    		
    		$em->persist($clone);
    		BankFeeFactory::persist($em, $transactions);
    		$em->flush();
    		
    		return $this->redirectToRoute('incomingInvoice_show', array('id'=> $clone->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/edit.html.twig', [
    			'invoice' => $clone,
    			'form' => $form->createView(),
    	]);
    }
    
       
    #[Route(path: "/dashboard/incomingInvoice/pay", methods: ["POST"], name: "incomingInvoice_pay")]
    public function pay(Request $request, ManagerRegistry $doctrine, BankFeeFactory $bankFees): Response
    {
    	$invoice = $doctrine->getRepository(IncomingInvoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$date = new \DateTime($request->request->get('date', null));    	
    	$entityManager = $doctrine->getManager();
    	
    	    	
    	$transaction = $invoice->setPaid($date, $this->getUser(), $request->request->get('mode', null));
    	    	
    	$entityManager->persist($invoice);  
    	$entityManager->persist($transaction);
    	$fee = $bankFees->createIfNeeded($invoice->getRecepient(), $date, $request->request->get('bankCost', 0), $this->getUser(), $invoice, $transaction);
    	if ($fee) {
    		$entityManager->persist($fee);
    	}
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('incomingInvoice_index');
    }    
    	
    #[Route(path: "/dashboard/incomingInvoice/reject", methods: ["POST"], name: "incomingInvoice_reject")]
    public function reject(Request $request, ManagerRegistry $doctrine): Response
    {
    	$invoice = $doctrine->getRepository(IncomingInvoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$entityManager = $doctrine->getManager();
    	
    	$invoice->reject($request->request->get('reason', ""));
    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('incomingInvoice_index');
    }
    
    //ToDo: Add a refund handler...
}
