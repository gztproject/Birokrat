<?php 
namespace App\Controller\Invoice;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Exception;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Form\Invoice\InvoiceType;
use App\Entity\Konto\Konto;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\CreateInvoiceItemCommand;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Invoice\InvoicePdfFactory;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Invoice\UpdateInvoiceCommand;
use App\Entity\Invoice\UpdateInvoiceItemCommand;
use App\Entity\Invoice\Enumerators\States;
use App\Mailer\MailerSettings;


class InvoiceCommandController extends AbstractController
{    
       
    #[Route(path: "/dashboard/invoice/new", methods: ["GET", "POST"], name: "invoice_new")]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
    	$createInvoiceCommand = new CreateInvoiceCommand();
    	$createInvoiceCommand->invoiceItemCommands[] = new CreateInvoiceItemCommand();
    	$createInvoiceCommand->invoiceItemCommands[0]->code = '1';
    	
    	$form = $this->createForm(InvoiceType::class, $createInvoiceCommand);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		 		
    		$invoice = $this->getUser()->createInvoice($createInvoiceCommand);
    		    		
    		$em = $doctrine->getManager();
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
    
    
    #[Route(path: "/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit", methods: ["GET", "POST"], name: "invoice_edit")]
    public function edit(Request $request, Invoice $invoice, ManagerRegistry $doctrine): Response
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
    		$em = $doctrine->getManager();
    		
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
    
    #[Route(path: "/dashboard/invoice/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone", methods: ["GET", "POST"], name: "invoice_clone")]
    public function clone(Request $request, Invoice $invoice, ManagerRegistry $doctrine): Response
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
    		$em = $doctrine->getManager();
    		
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
    
    
    #[Route(path: "/dashboard/invoice/issue", methods: ["POST"], name: "invoice_issue")]
    public function issue(Request $request, ManagerRegistry $doctrine): Response
    {
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $doctrine->getRepository(Invoice::class)->findOneBy(['id'=>$id]);
    	if($invoice == null)
    		throw new \Exception("Can't find an invoice with id ".$id);
    	$date = new \DateTime($request->request->get('date', null));
    	$entityManager = $doctrine->getManager();
    	$dateOfIssue = new \Datetime($request->request->get('dateOfIssue', 'now'));
    	$state = $request->request->get('state', States::new);
    	$number = InvoiceNumberFactory::factory($invoice->getIssuer(), $state, $dateOfIssue, $doctrine)->generate();
    	
    	$transaction = $invoice->setIssued($date, $number, $this->getUser());
    	
    	$entityManager->persist($invoice);
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return $this->render('dashboard/invoice/show.html.twig', [
    			'invoice' => $invoice
    	]);
    }
       
    #[Route(path: "/dashboard/invoice/pay", methods: ["POST"], name: "invoice_set_paid")]
    public function setPaid(Request $request, ManagerRegistry $doctrine): Response
    {
    	$invoice = $doctrine->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$date = new \DateTime($request->request->get('date', null));    	
    	$entityManager = $doctrine->getManager();
    	    	
    	$transaction = $invoice->setPaid($date, $this->getUser());
    	    	
    	$entityManager->persist($invoice);  
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }    
    
    #[Route(path: "/dashboard/invoice/cancel", methods: ["POST"], name: "invoice_cancel")]
    public function cancel(Request $request, ManagerRegistry $doctrine): Response
    {
    	$invoice = $doctrine->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$entityManager = $doctrine->getManager();
    	
    	$invoice->cancel($request->request->get('reason', ""));
    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }
    
    #[Route(path: "/dashboard/invoice/send", methods: ["POST"], name: "invoice_send")]
    public function send(Request $request, MailerInterface $mailer, TCPDFController $tcpdf, TranslatorInterface $translator, ManagerRegistry $doctrine, MailerSettings $mailerSettings): JsonResponse
    {
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $doctrine->getRepository(Invoice::class)->findOneBy(['id'=>$id]);    	
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
    		->from('birokrat@gzt.si')
    			->to($email)
    			->subject($subject)
    			->html('<p>'.$body.'</p>')
    			->attachFromPath($path.$title);
    		$userEmail = $this->getUser()?->getEmail();
    		if ($userEmail) {
    			$emailObject->replyTo($userEmail);
    		}    		
    		
    		$mailer->send($emailObject);
    		
    		unlink($path.$title);
    		
    		$data = $mailerSettings->describeDelivery($email);
    		$status = "ok";
    	}
    	catch (Exception $e)
    	{
    		//$this->addFlash('danger', 'invoice.not_sent');
    		$status = "error";
    		$data = $e->getMessage();
    	}
    	
    	return new JsonResponse(array(array('status'=>$status,'data'=>array($data))));
    }
}