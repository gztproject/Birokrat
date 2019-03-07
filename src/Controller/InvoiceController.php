<?php 
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Invoice\Invoice;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use Symfony\Component\Validator\Constraints\DateTime;
use App\Entity\Invoice\InvoiceNumberFactory;
use App\Entity\Konto\Konto;
use WhiteOctober\TCPDFBundle\Controller\TCPDFController;

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
    	$doctrine = $this->getDoctrine();
    	$issuedBy = $this->get('security.token_storage')->getToken()->getUser();
    	$organizations = $this->get('security.token_storage')->getToken()->getUser()->getOrganizations();
    	$issuer = $organizations[0];
    	if (count($organizations)>1)
    	{
    		//show organization picker (modal I guess)    		
    	}
    	
    	$number = InvoiceNumberFactory::factory($issuer, $doctrine)->generate();
    	
    	$invoice = new Invoice($issuedBy, $issuer, $number);   	
    	
    	$form = $this->createForm(InvoiceType::class, $invoice)
    	->add('saveAndCreateNew', SubmitType::class);
    	
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted() && $form->isValid()) {
    		 		
    		$invoice->setNew();
    		
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
     * @Route("/dashboard/invoice/pay", methods={"POST"}, name="invoice_set_paid")
     */
    public function setPaid(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$entityManager = $this->getDoctrine()->getManager();
    	    	
    	$invoice->setPaid();
    	    	
    	$entityManager->persist($invoice);
    	$entityManager->flush();
    	
    	return $this->redirectToRoute('invoice_index');
    }
    
    /**
     * @Route("/dashboard/invoice/issue", methods={"POST"}, name="invoice_issue")
     */
    public function issue(Request $request): Response
    {
    	$invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy(['id'=>$request->request->get('id', null)]);
    	$konto = $this->getDoctrine()->getRepository(Konto::class)->findOneBy(['number'=>760]); //760 for services or 762 for goods
    	$entityManager = $this->getDoctrine()->getManager();
    	
    	$transaction = $invoice->setIssued($konto);
    	
    	$entityManager->persist($invoice);    	
    	$entityManager->persist($transaction);
    	$entityManager->flush();
    	
    	return $this->render('dashboard/invoice/pdf.html.twig', [
    			'invoice' => $invoice
    	]);   
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
     * @Route("/dashboard/invoice/pdf/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}", methods={"GET"}, name="invoice_pdf")
     */
    public function generatePdf(Invoice $invoice, TCPDFController $tcpdf): Response
    {
    	$pdf = $tcpdf->create();
    	/**
    	 * Creates an example PDF TEST document using TCPDF
    	 * @package com.tecnick.tcpdf
    	 * @abstract TCPDF - Example: Removing Header and Footer
    	 * @author Nicola Asuni
    	 * @since 2008-03-04
    	 */    	
    	    	
    	// set document information
    	$pdf->SetCreator(PDF_CREATOR);
    	$pdf->SetAuthor('Nicola Asuni');
    	$pdf->SetTitle('TCPDF Example 002');
    	$pdf->SetSubject('TCPDF Tutorial');
    	$pdf->SetKeywords('TCPDF, PDF, example, test, guide');
    	
    	// remove default header/footer
    	$pdf->setPrintHeader(false);
    	$pdf->setPrintFooter(false);
    	
    	// set default monospaced font
    	$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    	
    	// set margins
    	$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    	
    	// set auto page breaks
    	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    	
    	// set image scale factor
    	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    	
    	// set some language-dependent strings (optional)
    	if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    		require_once(dirname(__FILE__).'/lang/eng.php');
    		$pdf->setLanguageArray($l);
    	}
    	
    	// ---------------------------------------------------------
    	
    	// set font
    	$pdf->SetFont('times', 'BI', 20);
    	
    	// add a page
    	$pdf->AddPage();
    	
    	// set some text to print
    	$n = $invoice->getNumber();
    	$txt = <<<EOD
		Invoice $n

		Default page header and footer are disabled using setPrintHeader() and setPrintFooter() methods.
		EOD;
    	
    	// print a block of text using Write()
    	$pdf->Write(0, $txt, '', 0, 'C', true, 0, false, false, 0);
    	
    	// ---------------------------------------------------------
    	
    	//Close and output PDF document
    	$pdf->Output('example_002.pdf', 'I');
    	
    	//============================================================+
    	// END OF FILE
    	//============================================================+    	
    	
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