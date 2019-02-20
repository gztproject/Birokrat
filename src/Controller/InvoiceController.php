<?php 
namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
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
    		
    		return $this->redirectToRoute('invoice_pdf', array('id'=> $invoice->getId()));
    	}
    	
    	return $this->render('dashboard/invoice/new.html.twig', [
    			'form' => $form->createView(),
    	]);
    } 
    
    /**
     * @Route("/dashboard/invoice/pdf/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="invoice_pdf")
     */
    public function generatePdf(Invoice $invoice)
    {
    	// Configure Dompdf according to your needs
    	$pdfOptions = new Options();
    	$pdfOptions
    		->set('defaultFont', 'helvetica')
    	;
    	
    	// Instantiate Dompdf with our options
    	$dompdf = new Dompdf($pdfOptions);
    	
    	// Retrieve the HTML generated in our twig file
    	$html = $this->renderView('dashboard/invoice/pdf.html.twig', [
    			'invoice' => $invoice
    	]);
    	//return $html;
    	
    	$dompdf->basePath();
    	
    	// Load HTML to Dompdf
    	$dompdf->loadHtml($html);
    	
    	// (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
    	$dompdf->setPaper('A4', 'portrait');
    	
    	// Render the HTML as PDF
    	$dompdf->render();
    	
    	// Output the generated PDF to Browser (inline view)
    	$dompdf->stream("mypdf.pdf", [
    			"Attachment" => false
    	]);
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