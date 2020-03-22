<?php

namespace App\Entity\Invoice;

use Symfony\Contracts\Translation\TranslatorInterface;
use Qipsius\TCPDFBundle\Controller\TCPDFController;

class InvoicePdfFactory
{
	private $__invoice = null;
	private $__translator = null;
	private $__tcpdf = null;
	private $__dest = null;
	private $__path = null;
	private $__print = false;

	public function __construct(Invoice $invoice, TranslatorInterface $translator, TCPDFController $tcpdf, string $dest, ?string $path, bool $print = false)
   	{
   		$this->__invoice = $invoice;
   		$this->__translator = $translator;
   		$this->__tcpdf = $tcpdf;
   		$this->__dest = $dest;
   		$this->__path = $path;
   		$this->__print = $print;
   	}

   	/**
   	 * 
   	 * @param Invoice $invoice
   	 * @param TranslatorInterface $translator
   	 * @param TCPDFController $tcpdf
   	 * @param string $dest (Inline, Download, File, String, F + I, F + D, Email)
   	 * @return InvoicePdfFactory
   	 */
   	public static function factory(Invoice $invoice, TranslatorInterface $translator, TCPDFController $tcpdf, string $dest = "I", ?string $path = null, bool $print = false): InvoicePdfFactory
   	{
   		$validDests = array('I', 'D', 'F', 'S', 'FI', 'FD', 'E');  
   		if($dest == 'F' && $path == null)
   			throw new \Exception('No path provided for the file.');
   		return new InvoicePdfFactory($invoice, $translator, $tcpdf, in_array($dest, $validDests) ? $dest : 'I', $dest == 'F' ? $path : null, $print);
   	}

   	public function generate()
   	{
   		$title = "";
   		if($this->__path != null)
   			$title .= $this->__path;
   		$title .= $this->__translator->trans('title.invoice').' '.$this->__invoice->getNumber();
   		
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
   		
   		$name = $this->__invoice->getIssuerName();
   		$address = "";
   		for($i = 0; $i<count($this->__invoice->getIssuerFormattedAddress())-1; $i++)
   		{
   			if($address != "")	$address.= '<br>';
   			$address .= $this->__invoice->getIssuerFormattedAddress()[$i];
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
   		$orgData .= $this->__invoice->getIssuerTaxNumber(). "\n";
   		$orgData .= $this->__invoice->getIssuerAccountNumber(). "\n";
   		$orgData .= $this->__invoice->getIssuerBic(). "\n";
   		$pdf->MultiCell(60, '', $orgData, 0, 'L', 0, 1);
   		
   		$pdf->Ln(15);
   		
   		$pdf->Cell(80, '', $this->__translator->trans('label.recepient').':', 0, 0, '', 0, '', 0, false, 'T', 'M' );
   		
   		$pdf->SetFont('dejavusans', 'B', 12);
   		$pdf->Cell(40, '', $this->__translator->trans('title.invoice').' '.$this->__translator->trans('label.no.').':', 0, 0, 'R', 0, '', 0, false, 'T', 'M' );
   		$pdf->Cell(60, '', $this->__invoice->getNumber(), 0, 0, 'L', 0, '', 0, false, 'T', 'M' );
   		
   		$pdf->Ln(10);
   		
   		$pdf->SetFontSize(10);
   		$name = $this->__invoice->getRecepientName();
   		$address = "";
   		for($i = 0; $i<count($this->__invoice->getRecepientFormattedAddress())-1; $i++)
   		{
   			if($address != "")	$address.= "\n";
   			$address .= $this->__invoice->getRecepientFormattedAddress()[$i];
   		}
   		$pdf->MultiCell(80, '', $name."\n \n".$address, 0, 'L', 0, 0);
   		$pdf->SetFont('dejavusans', '', 8);
   		$pdf->MultiCell(40, '', $this->__invoice->getIssuerPostName().
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
   		$pdf->Cell( 80, 0, $this->__translator->trans('label.taxNumber').": ".$this->__invoice->getRecepientTaxNumber(), 0, 0, '', 0, '', 0, false, 'T', 'M' );   		
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
   				//$pdf->Cell( $tableWidths[0], 0, $ii->getCode(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   				$pdf->MultiCell($tableWidths[0], 0, $ii->getCode(), 0, '', $fill, 0, '', '', true, 0, false, true, 0);
   				//$pdf->Cell( $tableWidths[1], 0, $ii->getName(), 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   				$pdf->MultiCell($tableWidths[1], 0, $ii->getName(), 0, '', $fill, 0, '', '', false, 0, false, true, 0);
   				//$pdf->Cell( $tableWidths[2], 0, number_format($ii->getQuantity(), 2, ',', '.'), 0, 0, 'R', $fill, '', 0, false, 'T', 'M' );
   				$pdf->MultiCell($tableWidths[2], 0, number_format($ii->getQuantity(), 2, ',', '.'), 0, '', $fill, 0, '', '', false, 0, false, true, 0);
   				//$pdf->Cell( $tableWidths[3], 0, $ii->getUnit(), 0, 0, '', $fill, 'C', 0, false, 'T', 'M' );
   				$pdf->MultiCell($tableWidths[3], 0, $ii->getUnit(), 0, '', $fill, 0, '', '', false, 0, false, true, 0);
   				//$pdf->Cell( $tableWidths[4], 0, number_format($ii->getPrice(), 2, ',', '.').' €', 0, 0, 'R', $fill, '', 0, false, 'T', 'M' );
   				$pdf->MultiCell($tableWidths[4], 0, number_format($ii->getPrice(), 2, ',', '.').' €', 0, '', $fill, 0, '', '', false, 0, false, true, 0);
   				if($discount)   						
   					//$pdf->Cell( $tableWidths[5], 0, number_format($ii->getDiscount()*100, 2, ',', '.').' %', 0, 0, '', $fill, '', 0, false, 'T', 'M' );
   					$pdf->MultiCell($tableWidths[5], 0, number_format($ii->getDiscount()*100, 2, ',', '.').' %', 0, '', $fill, 0, '', '', false, 0, false, true, 0);
   				//$pdf->Cell( $tableWidths[6], 0, number_format($ii->getPrice()*$ii->getQuantity()*(1-$ii->getDiscount()), 2, ',', '.').' €', 0, 1, 'R', $fill, '', 0, false, 'T', 'M' );
   					$pdf->MultiCell($tableWidths[6], 0, number_format($ii->getPrice()*$ii->getQuantity()*(1-$ii->getDiscount()), 2, ',', '.').' €', 0, '', $fill, 1, '', '', false, 0, false, true, 0);
   				}
   				
   				//--------- End Items
   				$pdf->SetFillColor(200, 200, 200);
   				$pdf->SetXY(5, -75);
   				$pdf->Ln();
   				if($this->__invoice->getDiscount()>0 || true)
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
   				   				
   				//ToDo: Get this from organizaion settings
   				$pdf->Cell( 120, 0, 'V skladu s prvim odstavkom 94. člena ZDDV-1 DDV ni obračunan.', 0, 1, '', 0, '', 0, false, 'T', 'B' );   				
   				$pdf->Ln(7);
   				$pdf->Cell( 120, 0, $this->__translator->trans('label.preparedBy').':', 0, 1, '', 0, '', 0, false, 'T', 'B' );
   				$pdf->Cell( 120, 0, $this->__invoice->getCreatedBy()->getFullname(), 0, 0, '', 0, '', 0, false, 'T', 'B' );
   				if($this->__invoice->getState() === 20 || $this->__invoice->getState() === 30)
   				{
   					if(substr($this->__invoice->getCreatedBy()->getSignatureFilename(), -4) === "jpeg")
   						$pdf->Image('uploads/signatures/'.$this->__invoice->getCreatedBy()->getSignatureFilename(), 40, 250, 0, 22, 'JPG', 'signature', '', true, 150, '', false, false, 0, false, false, false);
   					elseif(substr($this->__invoice->getCreatedBy()->getSignatureFilename(), -3) === "png")
   						$pdf->Image('uploads/signatures/'.$this->__invoice->getCreatedBy()->getSignatureFilename(), 40, 250, 0, 22, 'PNG', 'signature', '', true, 150, '', false, false, 0, false, false, false);
   				}
   				// set style for barcode
   				$style = array(
   						'border' => false,
   						'padding' => 0,
   						'fgcolor' => array(0,0,0),
   						'bgcolor' => false,
   				);
   				//ToDo: Move this somewhere else, it shouldn't really be here. 
   				//https://www.upn-qr.si/uploads/files/NavodilaZaProgramerjeUPNQR.pdf
   				$qrString = "";
   				$qrString .= "UPNQR\n"; //1
   				$qrString .= "\n";		//2
   				$qrString .= "\n";		//3
   				$qrString .= "\n";		//4
   				$qrString .= "\n";		//5
   				$qrString .= $this->__invoice->getRecepient()->getName()."\n";		//6 - recip. name
   				$qrString .= $this->__invoice->getRecepient()->getAddress()->getStreetAddress()."\n";		//7 - recip. address
   				$qrString .= $this->__invoice->getRecepient()->getAddress()->getPost()->getNameAndCode()."\n";		//8 - recip. ZIP + post
   				$qrString .= sprintf("%011d\n", $this->__invoice->getTotalPrice()*100);			//9 - price*100; 11 chars, front zero padded
   				$qrString .= "\n";		//10
   				$qrString .= "\n";		//11
   				$qrString .= "IVPT\n";															//12 - purpouse code (IVPT - invoice payment)
   				$qrString .= sprintf("Plačilo računa %s\n", $this->__invoice->getNumber());		//13 - Description
   				$qrString .= sprintf("%s\n",$this->__invoice->getDueDate()->format('d.m.Y'));		//14 - DueDate (DD.MM.YYYY)
   				$qrString .= str_replace(' ', '',$this->__invoice->getIssuer()->getAccountNumber())."\n";		//15 - IBAN - no spaces
   				$qrString .= str_replace([' '],'',$this->__invoice->getReferenceNumber())."\n";		//16 - reference
   				$qrString .= $this->__invoice->getIssuer()->getName()."\n";		//17 - issuer name
   				$qrString .= $this->__invoice->getIssuer()->getAddress()->getStreetAddress()."\n";		//18 - address
   				$qrString .= $this->__invoice->getIssuer()->getAddress()->getPost()->getNameAndCode()."\n";		//19 - ZIP + post
   				$qrString = str_replace(['Š', 'š', 'Č', 'č', 'Ž', 'ž'], ['S', 's', 'C', 'c', 'Z', 'z'], $qrString);
   				$qrString .= sprintf("%03d\n",strlen($qrString));		//20 - checksum (num of characters without this field)
   				while(strlen($qrString)<411)
   				{
   					$qrString.=" ";
   				}   				

				$pdf->write2DBarcode(utf8_encode($qrString), 'QRCODE,M', 135, 242, 30, 30, $style, 'N');
   				// ---------------------------------------------------------
   				
   				//Close and output PDF document
				if($this->__print) $pdf->IncludeJS("print();");
   				$pdf->Output($title.'.pdf', $this->__dest);
   				
   				//============================================================+
   				// END OF FILE
   				//============================================================+      		
   	}
   
}