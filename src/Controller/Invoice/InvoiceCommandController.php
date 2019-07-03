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
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$id]);
    	if($invoice == null)
    		throw new \Exception("Can't find an invoice with id ".$id);
    	$konto = $this->getDoctrine()->getRepository(Konto::class)->findOneBy(['number'=>760]); //760 for services or 762 for goods
    	$date = new \DateTime($request->request->get('date', null));
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$number = InvoiceNumberFactory::factory($invoice->getIssuer(), 10, $this->getDoctrine())->generate();
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
    
    /**
     * @Route("/dashboard/invoice/send", methods={"POST"}, name="invoice_send")
     */
    public function send(Request $request, MailerInterface $mailer): JsonResponse
    {
    	$id = $request->request->get('id', null);
    	if($id == null)
    		throw new \Exception("Bad request. I need an id.");
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$id]);    	
    	$email = $invoice->getRecepient()->getEmail() ?: $request->request->get('email', null);
    	try {
    		if($email == null || $email == "")
    		{    					
    			throw new \Exception("Client has no e-mail addres.");
    		}
    		
    		$emailObject = (new Email())
    			->from('birokrat@gzt.si')
    			->to($email)
    			->subject('Time for Symfony Mailer!')
    			->text('Sending emails is fun again!')
    			->html('<p>See Twig integration for better HTML integration!</p>')
    			->attach("/dashboard/invoice/pdf/$id", 'racun.pdf');    		
    		
    		$mailer->send($emailObject);
    		
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