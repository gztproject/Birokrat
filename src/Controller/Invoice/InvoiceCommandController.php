<?php 
namespace App\Controller\Invoice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Exception;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Form\Invoice\InvoiceType;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\CreateInvoiceCommand;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Invoice\InvoicePdfFactory;
use WhiteOctober\TCPDFBundle\Controller\TCPDFController;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Invoice\UpdateInvoiceCommand;
use App\Entity\Invoice\UpdateInvoiceItemCommand;


class InvoiceCommandController extends AbstractController
{    
       
    /**
     * @Route("/dashboard/invoice/new", methods={"GET", "POST"}, name="invoice_new")
     */
    public function new(Request $request): Response
    {
    	$createInvoiceCommand = new CreateInvoiceCommand();
    	
    	$form = $this->createForm(InvoiceType::class, $createInvoiceCommand);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		 		
    		$invoice = $this->getUser()->createInvoice($createInvoiceCommand);
    		    		
    		$em = $this->getDoctrine()->getManager();
    		foreach($createInvoiceCommand->invoiceItemCommands as $c)
    		{
    			$ii = $invoice->createInvoiceItem($c);
    			$em->persist($ii);
    		}
    		    		
    		$em->persist($invoice);
    		$em->flush();
    		
    		return $this->redirectToRoute('invoice_show', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    }
    
    
    /**
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="invoice_edit")
     */
    public function edit(Request $request, Invoice $invoice): Response
    {
    	$updateInvoiceCommand = new UpdateInvoiceCommand();
    	$invoice->mapTo($updateInvoiceCommand);    	
    	    	
    	foreach($invoice->getInvoiceItems() as $ii)
    	{
    		$uiic = new UpdateInvoiceItemCommand();
    		$ii->mapTo($uiic);
    		array_push($updateInvoiceCommand->invoiceItemCommands, $uiic);
    	}
    	
    	$form = $this->createForm(InvoiceType::class, $updateInvoiceCommand);
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$invoice->update($updateInvoiceCommand, $this->getUser());
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($invoice->getInvoiceItems() as $ii)
    		{
    			$em->persist($ii);
    		}
    		
    		$em->persist($invoice);
    		$em->flush();
    		
    		return $this->redirectToRoute('invoice_show', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/edit.html.twig', [
    			'invoice' => $invoice,
    			'form' => $form->createView(),
    	]);
    }
    
    /**
     * Displays a form to edit an existing invoice entity.
     *
     * @Route("/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone",methods={"GET", "POST"}, name="invoice_clone")
     */
    public function clone(Request $request, Invoice $invoice): Response
    {
    	$clone = $invoice->clone($this->getUser());
    	
    	$updateInvoiceCommand = new UpdateInvoiceCommand();
    	$clone->mapTo($updateInvoiceCommand);
    	
    	foreach($clone->getInvoiceItems() as $ii)
    	{
    		$uiic = new UpdateInvoiceItemCommand();
    		$ii->mapTo($uiic);
    		array_push($updateInvoiceCommand->invoiceItemCommands, $uiic);
    	}
    	
    	$form = $this->createForm(InvoiceType::class, $updateInvoiceCommand);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		$clone->update($updateInvoiceCommand, $this->getUser());
    		$em = $this->getDoctrine()->getManager();
    		
    		foreach($clone->getInvoiceItems() as $ii)
    		{
    			$em->persist($ii);
    		}
    		
    		$em->persist($clone);
    		$em->flush();
    		
    		return $this->redirectToRoute('invoice_show', array('id'=> $clone->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/edit.html.twig', [
    			'invoice' => $clone,
    			'form' => $form->createView(),
    	]);
    }
    
    
    /**
     * @Route("/dashboard/invoice/issue", methods={"POST"}, name="invoice_issue")
     */
    public function issue(Request $request): Response
    {
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$id]);
    	if($invoice == null)
    		throw new \Exception("Can't find an invoice with id ".$id);
    	$date = new \DateTime($request->request->get('date', null));
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$number = InvoiceNumberFactory::factory($invoice->getIssuer(), 10, $this->getDoctrine())->generate();
    	$transaction = $invoice->setIssued($date, $number, $this->getUser());
    	
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
    	    	
    	$transaction = $invoice->setPaid($date, $this->getUser());
    	    	
    	$entityManager->persist($invoice);  
    	$entityManager->persist($transaction);
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
    
    /**
     * @Route("/dashboard/invoice/send", methods={"POST"}, name="invoice_send")
     */
    public function send(Request $request, MailerInterface $mailer, TCPDFController $tcpdf, TranslatorInterface $translator): JsonResponse
    {
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$id]);    	
    	$email = $request->request->get('email', null);
    	$subject = $request->request->get('subject', null);
    	$body = $request->request->get('body', null);
    	try {
    		if($email == null || $email == "")
    		{    					
    			throw new \Exception("Client has no e-mail addres.");
    		}
    		$path = __DIR__."/../../../tmp/";
    		//Check if the directory already exists.
    		if(!is_dir($path)){
    			//Directory does not exist, so lets create it.
    			mkdir($path, 0755);
    		}
    		
    		InvoicePdfFactory::factory($invoice, $translator, $tcpdf, 'F', $path)->generate();
    		$title = $translator->trans('title.invoice').' '.$invoice->getNumber().'.pdf';
    		
    		$emailObject = (new Email())
    		->from('birokrat@gzt.si') //$this->getUser()->getEmail()?:
    			->to($email)
    			->subject($subject)
    			->replyTo($this->getUser()->getEmail())
    			->html('<p>'.$body.'</p>')  
    			->attachFromPath($path.$title);    		
    		
    		$mailer->send($emailObject);
    		
    		unlink($path.$title);
    		
    		$data = "Invoice sent to ".$email;
    		$status = "ok";
    	}
    	catch (Exception $e)
    	{
    		$status = "error";
    		$data = $e->getMessage();
    	}
    	
    	return new JsonResponse(array(array('status'=>$status,'data'=>array($data))));
    }
}