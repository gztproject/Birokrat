<?php

namespace App\Entity\TravelExpense;

use Symfony\Contracts\Translation\TranslatorInterface;
use Qipsius\TCPDFBundle\Controller\TCPDFController;

class TravelExpensePdfFactory
{
	private $__travelExpense = null;
	private $__translator = null;
	private $__tcpdf = null;
	private $__dest = null;
	private $__path = null;
	private $__print = false;

	public function __construct(TravelExpense $travelExpense, TranslatorInterface $translator, TCPDFController $tcpdf, string $dest, ?string $path, bool $print = false)
   	{
   		$this->__travelExpense = $travelExpense;
   		$this->__translator = $translator;
   		$this->__tcpdf = $tcpdf;
   		$this->__dest = $dest;
   		$this->__path = $path;
   		$this->__print = $print;
   	}

   	/**
   	 * 
   	 * @param TravelExpense $travelExpense
   	 * @param TranslatorInterface $translator
   	 * @param TCPDFController $tcpdf
   	 * @param string $dest (Inline, Download, File, String, F + I, F + D, Email)
   	 * @return TravelExpensePdfFactory
   	 */
   	public static function factory(TravelExpense $travelExpense, TranslatorInterface $translator, TCPDFController $tcpdf, string $dest = "I", ?string $path = null, bool $print = false): TravelExpensePdfFactory
   	{
   		$validDests = array('I', 'D', 'F', 'S', 'FI', 'FD', 'E');  
   		if($dest == 'F' && $path == null)
   			throw new \Exception('No path provided for the file.');
   			return new TravelExpensePdfFactory($travelExpense, $translator, $tcpdf, in_array($dest, $validDests) ? $dest : 'I', $dest == 'F' ? $path : null, $print);
   	}

   	public function generate()
   	{
   		$title = "";
   		if($this->__path != null)
   			$title .= $this->__path;
   		$title .= $this->__translator->trans('title.travelExpense').' '.$this->__travelExpense->getNumber();
   		
   		$pdf = $this->__tcpdf->create('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'utf-8', false);
   		
   		// set document information
   		$pdf->SetCreator(PDF_CREATOR);
   		$pdf->SetAuthor($this->__travelExpense->getCreatedBy()->getFullname());
   		$pdf->SetTitle($title);
   		$pdf->SetSubject($this->__travelExpense->getNumber());
   		$pdf->SetKeywords($this->__translator->trans('title.travelExpense').','.$this->__travelExpense->getNumber());
   		
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
   		
   		$purple = [120,  80, 252];
   		$white 	= [255, 255, 255];
   		$black  = [  0,   0,   0];
   		
   		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)
   		// Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
   		
   		//First half of the page
   		
   		$border = array(
   				'T' => array('width' => 0.5, 'color' => $purple),
   				'R' => array('width' => 0.5, 'color' => $purple),
   				'B' => array('width' => 0.5, 'color' => $purple),
   				'L' => array('width' => 0.5, 'color' => $purple),
   		);
   		
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		
   		$pdf->MultiCell(120, 20, "Nalog za službeno potovanje ", $border, 'R', 0, 0, 14, 20, true, 0, false, true, 40, 'T');
   		
   		//ToDo: Get from OrganizationSettings
   		$imgdata = base64_decode('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');
   		
   		
   		$pdf->Image('@'.$imgdata, 27, 20.5, 20, 19, 'png', '', 'T', true, 300, '', false, false, 0, false, false, false);
   		
   		$border = 0;
   		/*array(
   				'T' => array('width' => 0.5, 'color' => $purple),
   				'R' => array('width' => 0.5, 'color' => $purple),
   				'B' => array('width' => 0.5, 'color' => $purple),
   				'L' => array('width' => 0.5, 'color' => $purple),
   		);*/
   		
   		$start = 50;
   		$height = 7.5;
   		
   		$pdf->MultiCell(40, $height, "Odrejam da odpotuje", 									$border, 'L', 0, 0, 14, 	$start + (0*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-40, $height, $this->__travelExpense->getEmployee()->getFullname(),	$border, 'R', 0, 0, 14+40, 	$start + (0*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(40, $height, "na delovnem mestu", 										$border, 'L', 0, 0, 14, 	$start + (1*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-40, $height, $this->__travelExpense->getEmployee()->getPosition(),	$border, 'R', 0, 0, 14+40, 	$start + (1*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(20, $height, "stanujoč", 												$border, 'L', 0, 0, 14, 	$start + (2*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-20, $height, $this->__travelExpense->getOrganization()->getAddress()->getFullAddress(),	 $border, 'R', 0, 0, 14+20, 	$start + (2*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(10, $height, "dne", 													$border, 'L', 0, 0, 14, 	$start + (3*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-10,$height, $this->__travelExpense->getDateString(),	 			$border, 'R', 0, 0, 14+10, 	$start + (3*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(50, $height, "po nalogu (odločba - spis)", 								$border, 'L', 0, 0, 14, 	$start + (4*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-50, $height, "direktorja",	 										$border, 'R', 0, 0, 14+50, 	$start + (4*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(20, $height, "v (na)", 													$border, 'L', 0, 0, 14, 	$start + (5*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-20, $height, $this->__travelExpense->getTravelDescription(),	 	$border, 'R', 0, 0, 14+20, 	$start + (5*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(20, $height, "z nalogo", 												$border, 'L', 0, 0, 14, 	$start + (7*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-20, $height, $this->__travelExpense->getReason(),	 				$border, 'R', 0, 0, 14+20, 	$start + (7*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(50, $height, "Potovanje bo trajalo do", 								$border, 'L', 0, 0, 14, 	$start + (8*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-50, $height, $this->__travelExpense->getDateString(),	 			$border, 'R', 0, 0, 14+50, 	$start + (8*$height), true, 0, false, true, 40, 'T');
   		
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(40, $height, "Odobravam uporabo",										$border, 'L', 0, 0, 14, 	$start + (10*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-40, $height, "osebnega avtomobila",	 								$border, 'R', 0, 0, 14+40,	$start + (10*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(40, $height, "Potne stroške plača",										$border, 'L', 0, 0, 14, 	$start + (11*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-40, $height, $this->__travelExpense->getOrganization()->getName(),	$border, 'R', 0, 0, 14+40, 	$start + (11*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(40, $height, "Višina dnevnice",											$border, 'L', 0, 0, 14, 	$start + (12*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-40, $height, "0,00€",	 											$border, 'R', 0, 0, 14+40, 	$start + (12*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(80, $height, "Odobravam izplačilo predujma v znesku",					$border, 'L', 0, 0, 14, 	$start + (13*$height), true, 0, false, true, 40, 'T');
   		$pdf->SetTextColor($black[0], $black[1], $black[2]);
   		$pdf->MultiCell(120-80, $height, "0,00€",	 											$border, 'R', 0, 0, 14+80, 	$start + (13*$height), true, 0, false, true, 40, 'T');   
   		$pdf->SetTextColor($purple[0], $purple[1], $purple[2]);
   		$pdf->MultiCell(50, $height, "Podpis nalogodajalca",									$border, 'L', 0, 0, 14, 	$start + (15*$height), true, 0, false, true, 40, 'T');
   		
   		if(substr($this->__travelExpense->getCreatedBy()->getSignatureFilename(), -4) === "jpeg")
   			$pdf->Image('uploads/signatures/'.$this->__travelExpense->getCreatedBy()->getSignatureFilename(), 14+50, $start + (15*$height), 0, 18, 'JPG', 'signature', '', true, 150, '', false, false, $border, false, false, false);
   		elseif(substr($this->__travelExpense->getCreatedBy()->getSignatureFilename(), -3) === "png")
   			$pdf->Image('uploads/signatures/'.$this->__travelExpense->getCreatedBy()->getSignatureFilename(), 14+50, $start + (15*$height), 0, 18, 'PNG', 'signature', '', true, 150, '', false, false, $border, false, false, false);
   		
   		
   		
   		
   		
   		//Second half of the page
   		$border = array(
   				'T' => array('width' => 0.5, 'color' => $purple),
   				'R' => array('width' => 0.5, 'color' => $purple),
   				'B' => array('width' => 0.5, 'color' => $purple),
   				'L' => array('width' => 0.5, 'color' => $purple),
   		);
   		$pdf->MultiCell(120, 20, "Obračun potnih stroškov ", $border, 'R', 0, 0, 160, 20, true, 0, false, true, 40, 'T');
   		
   		
   		   		
   				
   		//Close and output PDF document
		if($this->__print) $pdf->IncludeJS("print();");
   		$pdf->Output($title.'.pdf', $this->__dest);
   		
   		//============================================================+
   		// END OF FILE
   		//============================================================+      		
   	}
   
}