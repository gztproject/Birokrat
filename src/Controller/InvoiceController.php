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
use Symfony\Component\Translation\TranslatorInterface;

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
    public function generatePdf(Invoice $invoice, TCPDFController $tcpdf, TranslatorInterface $translator): Response
    {
    	$title = $translator->trans('title.invoice').' '.$invoice->getNumber();
    	    	
    	$pdf = $tcpdf->create(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'utf-8', false);   	     	
    	    	
    	// set document information
    	$pdf->SetCreator(PDF_CREATOR);
    	$pdf->SetAuthor($invoice->getIssuedBy()->getFullname());
    	$pdf->SetTitle($title);
    	$pdf->SetSubject($invoice->getNumber());
    	$pdf->SetKeywords($translator->trans('title.invoice').','.$invoice->getNumber());
    	
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
    	
    	// ---------------------------------------------------------
    	
    	// set font
    	$pdf->SetFont('dejavusans', '', 15);
    	
    	// add a page
    	$pdf->AddPage();
    	
    	// set some text to print
    	$txt = <<<EOD
		$title \n
		
		EOD;
		
		// print a block of text using Write( $h, $txt, $link = '', $fill = false, $align = '', $ln = false, $stretch = 0, $firstline = false, $firstblock = false, $maxh = 0, $wadj = 0, $margin = '' )
    	$pdf->Write(0, $txt, '', 0, '', false, 0, false, false, 0);
    	
    	
    	$discount = false;
    	foreach ($invoice->getInvoiceItems() as $ii){
    		if($ii->getDiscount() != 0)
    			$discount = true;
    	} 
    	
    	$tableWidths = [20, 90, 15, 15, 20, 0, 20];
    	if($discount)
    		$tableWidths = [20, 70, 15, 15, 20, 20, 20];
    	
    	$pdf->SetFontSize(8);
    	$pdf->SetFillColor(200, 200, 200);
    	$fill = true;
    	//print a Cell( $w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M' )
    	$pdf->Cell( $tableWidths[0], 0, $translator->trans('label.code'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	$pdf->Cell( $tableWidths[1], 0, $translator->trans('label.name'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	$pdf->Cell( $tableWidths[2], 0, $translator->trans('label.quantity'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	$pdf->Cell( $tableWidths[3], 0, $translator->trans('label.unit'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	$pdf->Cell( $tableWidths[4], 0, $translator->trans('label.price'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	if($discount)
    		$pdf->Cell( $tableWidths[5], 0, $translator->trans('label.discount'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	$pdf->Cell( $tableWidths[6], 0, $translator->trans('label.value'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
    	
    	
    	$pdf->Ln();
    	$pdf->Ln();
    	
    	   	
    	
    	foreach ($invoice->getInvoiceItems() as $ii){
    		$fill = !$fill;
    		$pdf->Cell( $tableWidths[0], 0, $ii->getCode(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Cell( $tableWidths[1], 0, $ii->getName(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Cell( $tableWidths[2], 0, number_format($ii->getQuantity(), 2, ',', '.'), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Cell( $tableWidths[3], 0, $ii->getUnit(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Cell( $tableWidths[4], 0, number_format($ii->getPrice(), 2, ',', '.').' €', 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		if($discount)
    			$pdf->Cell( $tableWidths[5], 0, $ii->getDiscount().' %', 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Cell( $tableWidths[6], 0, number_format($ii->getPrice()*$ii->getQuantity()*(1-$ii->getDiscount()), 2, ',', '.').' €', 0, 0, '', $fill, '', 0, false, 'T', 'M' );
    		$pdf->Ln();
    	}
    	
    	
    	
    	// ---------------------------------------------------------
    	
    	//Close and output PDF document
    	
    	$pdf->Output($title.'.pdf', 'I');
    	
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