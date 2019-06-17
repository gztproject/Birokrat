<?php

namespace App\Entity\Invoice;

use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteOctober\TCPDFBundle\Controller\TCPDFController;
class InvoicePdfFactory
{
	private $__invoice = null;
	private $__translator = null;
	private $__tcpdf = null;

   	public function __construct(Invoice $invoice, TranslatorInterface $translator, TCPDFController $tcpdf)
   	{
   		$this->__invoice = $invoice;
   		$this->__translator = $translator;
   		$this->__tcpdf = $tcpdf;
   	}

   	public static function factory(Invoice $invoice, TranslatorInterface $translator, TCPDFController $tcpdf): InvoicePdfFactory
   	{
   		return new InvoicePdfFactory($invoice, $translator, $tcpdf);
   	}

   	public function generate(): String
   	{
   		$title = $this->__translator->trans('title.invoice').' '.$this->__invoice->getNumber();
   		
   		//ToDo: Move this to Invoice
   		$pdf = $this->__tcpdf->create(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'utf-8', false);
   		
   		// set document information
   		$pdf->SetCreator(PDF_CREATOR);
   		$pdf->SetAuthor($this->__invoice->getCreatedBy()->getFullname());
   		$pdf->SetTitle($title);
   		$pdf->SetSubject($this->__invoice->getNumber());
   		$pdf->SetKeywords($this->__translator->trans('title.invoice').','.$this->__invoice->getNumber());
   		
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
   		$pdf->SetFont('dejavusans', '', 10);
   		
   		// add a page
   		$pdf->AddPage();
   		//ToDo: Get from OrganizationSettings
   		$imgdata = base64_decode('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');
   		
   		//Image( $file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false,
   		//$border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = array() )
   		$pdf->Image('@'.$imgdata, '', '', 20, 20, 'png', '', 'T');
   		
   		$name = $this->__invoice->getIssuer()->getShortName();
   		$address = "";
   		for($i = 0; $i<count($this->__invoice->getIssuer()->getAddress()->getFullFormattedAddress())-1; $i++)
   		{
   			if($address != "")	$address.= '<br>';
   			$address .= $this->__invoice->getIssuer()->getAddress()->getFullFormattedAddress()[$i];
   		}
   		$html = "<h2>$name</h2></br><b>$address</b>";
   		$pdf->writeHTMLCell(80,'','','',$html, 0);
   		$pdf->SetFontSize(8);
   		//MultiCell( $w, $h, $txt, $border = 0, $align = 'J', $fill = false, $ln = 1, $x = '', $y = '', $reseth = true, $stretch = 0,
   		//	$ishtml = false, $autopadding = true, $maxh = 0, $valign = 'T', $fitcell = false )
   		$pdf->MultiCell(20, '', "WWW:\nE-mail:\nGSM:\n".$this->__translator->trans('label.taxNumber').":\nTRR:\nBIC:", 0, 'R', 0, 0);
   		
   		$orgData = $this->__invoice->getIssuer()->getWww() . "\n";
   		$orgData .= $this->__invoice->getIssuer()->getEmail(). "\n";
   		$orgData .= $this->__invoice->getIssuer()->getMobile(). "\n";
   		$orgData .= $this->__invoice->getIssuer()->getFullTaxNumber(). "\n";
   		$orgData .= $this->__invoice->getIssuer()->getAccountNumber(). "\n";
   		$orgData .= $this->__invoice->getIssuer()->getBic(). "\n";
   		$pdf->MultiCell(60, '', $orgData, 0, 'L', 0, 1);
   		
   		$pdf->Ln(15);
   		
   		$pdf->Cell(80, '', $this->__translator->trans('label.recepient').':', 0, 0, '', 0, '', 0, false, 'T', 'M' );
   		
   		$pdf->SetFont('dejavusans', 'B', 12);
   		$pdf->Cell(40, '', $this->__translator->trans('title.invoice').' '.$this->__translator->trans('label.no.').':', 0, 0, 'R', 0, '', 0, false, 'T', 'M' );
   		$pdf->Cell(60, '', $this->__invoice->getNumber(), 0, 0, 'L', 0, '', 0, false, 'T', 'M' );
   		
   		$pdf->Ln(10);
   		
   		$pdf->SetFontSize(10);
   		$name = $this->__invoice->getRecepient()->getShortName();
   		$address = "";
   		for($i = 0; $i<count($this->__invoice->getRecepient()->getAddress()->getFullFormattedAddress())-1; $i++)
   		{
   			if($address != "")	$address.= "\n";
   			$address .= $this->__invoice->getRecepient()->getAddress()->getFullFormattedAddress()[$i];
   		}
   		$pdf->MultiCell(80, '', $name."\n \n".$address, 0, 'L', 0, 0);
   		$pdf->SetFont('dejavusans', '', 8);
   		$pdf->MultiCell(40, '', $this->__invoice->getIssuer()->getAddress()->getPost()->getName().
   				",\n\n".$this->__translator->trans('label.dueInDays').
   				":\n".$this->__translator->trans('label.dueDate').
   				":\n".$this->__translator->trans('label.reference').
   				":\n".$this->__translator->trans('label.dateServiceRendered').":", 0, 'R', 0, 0);
   		
   		$invData = $this->__invoice->getDateOfIssueString() . "\n\n";
   		$invData .= $this->__invoice->getDueInDays()."\n";
   		$invData .= $this->__invoice->getDueDateString()."\n";
   		$invData .= $this->__invoice->getReferenceNumber()."\n";
   		$invData .= $this->__invoice->getDateServiceRenderedString();
   		$pdf->MultiCell(60, '', $invData, 0, 'L', 0, 1);
   		$pdf->Ln(7);
   		$pdf->Cell( 80, 0, $this->__translator->trans('label.taxNumber').": ".$this->__invoice->getRecepient()->getFullTaxNumber(), 0, 0, '', 0, '', 0, false, 'T', 'M' );
   		$pdf->Ln(7);
   		
   		// ---------ITEMS
   		$discount = false;
   		foreach ($this->__invoice->getInvoiceItems() as $ii){
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
   			$pdf->Cell( $tableWidths[0], 0, $this->__translator->trans('label.code'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   			$pdf->Cell( $tableWidths[1], 0, $this->__translator->trans('label.name'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   			$pdf->Cell( $tableWidths[2], 0, $this->__translator->trans('label.quantity'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   			$pdf->Cell( $tableWidths[3], 0, $this->__translator->trans('label.unit'), 1, 0, 'C', $fill, '', 0, false, 'T', 'M' );
   			$pdf->Cell( $tableWidths[4], 0, $this->__translator->trans('label.price'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   			if($discount)
   				$pdf->Cell( $tableWidths[5], 0, $this->__translator->trans('label.discount'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   				$pdf->Cell( $tableWidths[6], 0, $this->__translator->trans('label.value'), 1, 0, '', $fill, '', 0, false, 'T', 'M' );
   				
   				$pdf->Ln(7);
   				$pdf->SetFillColor(220, 220, 220);
   				foreach ($this->__invoice->getInvoiceItems() as $ii){
   					$fill = !$fill;
   					$pdf->Cell( $tableWidths[0], 0, $ii->getCode(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   					$pdf->Cell( $tableWidths[1], 0, $ii->getName(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   					$pdf->Cell( $tableWidths[2], 0, number_format($ii->getQuantity(), 2, ',', '.'), 0, 0, 'R', $fill, '', 0, false, 'T', 'M' );
   					$pdf->Cell( $tableWidths[3], 0, $ii->getUnit(), 0, 0, '', $fill, 'C', 0, false, 'T', 'M' );
   					$pdf->Cell( $tableWidths[4], 0, number_format($ii->getPrice(), 2, ',', '.').' €', 0, 0, 'R', $fill, '', 0, false, 'T', 'M' );
   					if($discount)
   						$pdf->Cell( $tableWidths[5], 0, number_format($ii->getDiscount()*100, 2, ',', '.').' %', 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   						$pdf->Cell( $tableWidths[6], 0, number_format($ii->getPrice()*$ii->getQuantity()*(1-$ii->getDiscount()), 2, ',', '.').' €', 0, 1, 'R', $fill, '', 0, false, 'T', 'M' );
   				}
   				
   				//--------- End Items
   				$pdf->SetFillColor(200, 200, 200);
   				$pdf->SetXY(5, -75);
   				$pdf->Ln();
   				if($this->__invoice->getDiscount()>0)
   				{
   					$pdf->Cell( 120, 0, '', 0, 0, '', 0, '', 0, false, 'T', 'B' );
   					$pdf->Cell( 30, 0, $this->__translator->trans('label.value').":", 0, 0, '', 0, '', 0, false, 'T', 'B' );
   					$pdf->Cell( 30, 0, number_format($this->__invoice->getTotalValue(), 2, ',', '.')." €", 0, 1, 'R', 0, '', 0, false, 'T', 'B' );
   					
   					
   					$pdf->Cell( 120, 0, '', 0, 0, '', 0, '', 0, false, 'T', 'B' );
   					$pdf->Cell( 20, 0, $this->__translator->trans('label.discount').":", 0, 0, '', 0, '', 0, false, 'T', 'B' );
   					$pdf->Cell( 20, 0, number_format($this->__invoice->getDiscount()*100, 2, ',', '.') . " %", 0, 0, 'C', 0, '', 0, false, 'T', 'B' );
   					$pdf->Cell( 20, 0, number_format($this->__invoice->getTotalValue()*$this->__invoice->getDiscount(), 2, ',', '.')." €", 0, 1, 'R', 0, '', 0, false, 'T', 'B' );
   				}
   				
   				$pdf->Cell( 120, 0, '', 0, 0, '', 0, '', 0, false, 'T', 'B' );
   				$pdf->Cell( 30, 0, $this->__translator->trans('label.toPay').":", 0, 0, '', 1, '', 0, false, 'T', 'B' );
   				$pdf->Cell( 30, 0, number_format($this->__invoice->getTotalPrice(), 2, ',', '.')." €", 0, 1, 'R', 1, '', 0, false, 'T', 'B' );
   				
   				$pdf->Ln(14);
   				//ToDo: Move this to organizaion settings
   				$pdf->Cell( 120, 0, 'V skladu s 1. točko 94. člena ZDDV-1 DDV ni obračunan.', 0, 1, '', 0, '', 0, false, 'T', 'B' );
   				$pdf->Ln(7);
   				$pdf->Cell( 120, 0, $this->__translator->trans('label.preparedBy').':', 0, 1, '', 0, '', 0, false, 'T', 'B' );
   				$pdf->Cell( 120, 0, $this->__invoice->getCreatedBy()->getFullname(), 0, 0, '', 0, '', 0, false, 'T', 'B' );
   				// ---------------------------------------------------------
   				
   				//Close and output PDF document
   				
   				$pdf->Output($title.'.pdf', 'I');
   				
   				//============================================================+
   				// END OF FILE
   				//============================================================+      		
   	}
   
}