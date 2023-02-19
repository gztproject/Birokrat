<?php

namespace App\Controller\Transaction;

use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Konto\Konto;
use App\Entity\Transaction\Transaction;
use App\Repository\Transaction\TransactionRepository;
use App\Entity\Transaction\UpdateTransactionCommand;
use App\Entity\Transaction\CreateTransactionCommand;
use App\Form\Transaction\TransactionType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;

class TransactionController extends AbstractController {
	/**
	 *
	 * @Route("/dashboard/transaction", methods={"GET"}, name="transaction_index")
	 */
	public function index(TransactionRepository $transactions, Request $request, PaginatorInterface $paginator): Response {
		$dateFrom = $request->query->get ( 'dateFrom', null );
		$dateTo = $request->query->get ( 'dateTo', null );
		$orgId = $request->query->get ( 'organization', null );
		$orgId = $orgId === "" ? null : $orgId;

		$queryBuilder = $transactions->getFilteredQuery ( $dateFrom, $dateTo, $orgId );

		$pagination = $paginator->paginate ( $queryBuilder, $request->query->getInt ( 'page', 1 ), $request->query->getInt ( 'results', 100 ) );

		return $this->render ( 'dashboard/transaction/index.html.twig', [ 
				'pagination' => $pagination
		] );
	}

	/**
	 *
	 * @Route("/dashboard/transaction/export", methods={"GET"}, name="transaction_export")
	 */
	public function export(TransactionRepository $transactions, Request $request, PaginatorInterface $paginator): Response {
		$dateFrom = $request->query->get ( 'dateFrom', null );
		$dateTo = $request->query->get ( 'dateTo', null );
		$orgId = $request->query->get ( 'organization', null );
		$orgId = $orgId === "" ? null : $orgId;

		$queryBuilder = $transactions->getFilteredQuery ( $dateFrom, $dateTo, $orgId, "ASC" );

		$result = $queryBuilder->getQuery ()->getResult ();

		$spreadsheet = new Spreadsheet ();

		$sheet = $spreadsheet->getActiveSheet ();
		$sheet->setTitle ( "Transactions double-sided" );

		$row = 1;

		$sheet->setCellValue ( 'A' . $row, 'Organization' );
		$sheet->setCellValue ( 'B' . $row, 'Date' );
		$sheet->setCellValue ( 'C' . $row, 'Sum' );
		$sheet->setCellValue ( 'D' . $row, 'Credit' );
		$sheet->setCellValue ( 'E' . $row, 'Debit' );
		$sheet->setCellValue ( 'F' . $row, 'Description' );

		$row ++;

		foreach ( $result as $res ) {
			$sheet->setCellValue ( 'A' . $row, $res->getOrganization ()->getShortName () );
			$sheet->setCellValue ( 'B' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel ( $res->getDate () ) );
			$sheet->setCellValue ( 'C' . $row, $res->getSum () );
			$sheet->setCellValue ( 'D' . $row, $res->getCreditKonto ()->getNumber () );
			$sheet->setCellValue ( 'E' . $row, $res->getDebitKonto ()->getNumber () );
			$sheet->setCellValue ( 'F' . $row, $res->getDescription () );

			$row ++;
		}

		$sheet->getStyle ( 'B' )->getNumberFormat ()->setFormatCode ( 'dd. mm. yyyy' );
		$sheet->getStyle ( 'C' )->getNumberFormat ()->setFormatCode ( \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE );

		$sheet->getColumnDimension ( 'A' )->setAutoSize ( true );
		$sheet->getColumnDimension ( 'B' )->setAutoSize ( true );
		$sheet->getColumnDimension ( 'C' )->setAutoSize ( true );
		$sheet->getColumnDimension ( 'D' )->setAutoSize ( true );
		$sheet->getColumnDimension ( 'E' )->setAutoSize ( true );
		$sheet->getColumnDimension ( 'F' )->setAutoSize ( true );

		/*
		 * $sheet = $spreadsheet->createSheet ();
		 * $sheet->setTitle ( "Transactions simple" );
		 *
		 * $row = 1;
		 *
		 * $sheet->setCellValue ( 'A' . $row, 'Organization' );
		 * $sheet->setCellValue ( 'B' . $row, 'Date' );
		 * $sheet->setCellValue ( 'C' . $row, 'Sum' );
		 * $sheet->setCellValue ( 'D' . $row, 'Description' );
		 *
		 * $row ++;
		 *
		 * foreach ( $result as $res ) {
		 * $sum = $res->getSum () * $this->isPositive ( $res->getCreditKonto (), $res->getDebitKonto () );
		 *
		 * if ($sum == 0)
		 * continue;
		 *
		 * $sheet->setCellValue ( 'A' . $row, $res->getOrganization ()->getShortName () );
		 * $sheet->setCellValue ( 'B' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel ( $res->getDate () ) );
		 * $sheet->setCellValue ( 'C' . $row, $sum );
		 * $sheet->setCellValue ( 'D' . $row, $res->getDescription () );
		 *
		 * $row ++;
		 * }
		 *
		 * $sheet->getStyle ( 'B' )->getNumberFormat ()->setFormatCode ( 'dd. mm. yyyy' );
		 * $sheet->getStyle ( 'C' )->getNumberFormat ()->setFormatCode ( \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE );
		 *
		 * $sheet->getColumnDimension ( 'A' )->setAutoSize ( true );
		 * $sheet->getColumnDimension ( 'B' )->setAutoSize ( true );
		 * $sheet->getColumnDimension ( 'C' )->setAutoSize ( true );
		 * $sheet->getColumnDimension ( 'D' )->setAutoSize ( true );
		 */
		// Create your Office 2007 Excel (XLSX Format)
		$writer = new Xlsx ( $spreadsheet );

		// Create a Temporary file in the system
		$fileName = "TransactionReport_" . date ( 'Y-m-d', $dateFrom ) . "_" . date ( 'Y-m-d', $dateTo );
		$temp_file = tempnam ( sys_get_temp_dir (), $fileName );

		// Create the excel file in the tmp directory of the system
		$writer->save ( $temp_file );

		// Return the excel file as an attachment
		return $this->file ( $temp_file, $fileName, ResponseHeaderBag::DISPOSITION_INLINE );
	}
	
	private function isPositive(Konto $credit, Konto $debit) {
		if ($credit->getCategory ()->getNumber () == 76)
			return 1;

		if (($credit->getCategory ()->getNumber () == 11 || $credit->getCategory ()->getNumber () == 91) && ! ($debit->getCategory ()->getNumber () == 11 || $debit->getCategory ()->getNumber () == 91))
			return - 1;

		if (($debit->getCategory ()->getNumber () == 11 || $debit->getCategory ()->getNumber () == 91) && ! ($credit->getCategory ()->getNumber () == 11 || $credit->getCategory ()->getNumber () == 91 || $credit->getCategory ()->getNumber () == 12))
			return 1;

		// if ($debit->getCategory ()->getNumber () == 28)
		// return - 1;

		// if ($debit->getCategory ()->getClass ()->getNumber () == 4 || $debit->getCategory ()->getNumber () == 81)
		// if ($credit->getCategory ()->getNumber () == 11 || $credit->getCategory ()->getNumber () == 91)
		// return - 1;

		return 0;
	}

	/**
	 *
	 * @Route("/dashboard/transaction/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/show", methods={"GET"}, name="transaction_show")
	 */
	public function show(Transaction $transaction, PaginatorInterface $paginator): Response {
		return $this->render ( 'dashboard/transaction/index.html.twig', [ 
				'pagination' => $paginator->paginate ( [ 
						$transaction
				] )
		] );
	}

	/**
	 *
	 * @Route("/dashboard/transaction/new", methods={"GET", "POST"}, name="transaction_new")
	 */
	public function new(TransactionRepository $transactions, Request $request, PaginatorInterface $paginator): Response {
		$createTransactionCommand = new CreateTransactionCommand ();
		$createTransactionCommand->hidden = false;

		$form = $this->createForm ( TransactionType::class, $createTransactionCommand )->add ( 'saveAndCreateNew', SubmitType::class );

		$form->handleRequest ( $request );

		if ($form->isSubmitted () && $form->isValid ()) {

			$transaction = $this->getUser ()->createTransactionWithDescription ( $createTransactionCommand );

			$em = $this->getDoctrine ()->getManager ();

			$em->persist ( $transaction );
			$em->flush ();

			return $this->redirectToRoute ( 'transaction_show', array (
					'id' => $transaction->getId ()
			) );
		}

		return $this->render ( 'dashboard/transaction/new.html.twig', [ 
				'form' => $form->createView ()
		] );
	}
	
	/**
	 * Displays a form to edit an existing transaction entity.
	 *
	 * @Route("/dashboard/transaction/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/edit",methods={"GET", "POST"}, name="transaction_edit")
	 */
	public function edit(Request $request, Transaction $transaction, LoggerInterface $logger, ManagerRegistry $doctrine): Response
	{
	    $updateTransactionCommand = new UpdateTransactionCommand();
	    $transaction->mapTo($updateTransactionCommand);
	    $doc = $transaction->getDocument();
	    
	    $form = $this->createForm(TransactionType::class, $updateTransactionCommand);
	    $form->handleRequest($request);
	    
	    if ($form->isSubmitted() && $form->isValid()) {
	        $transaction->update($updateTransactionCommand, $this->getUser(), $doc, $logger);
	        $em = $doctrine->getManager();
	        
	        $em->persist($transaction);
	        $em->flush();
	        
	        return $this->redirectToRoute('transaction_show', array('id'=> $transaction->getId()));
	    }
	    
	    return $this->render('dashboard/transaction/edit.html.twig', [
	        'transaction' => $transaction,
	        'form' => $form->createView(),
	    ]);
	}
	
	/**
	 * Clones the transaction and displays a form to edit the new transaction entity.
	 *
	 * @Route("/dashboard/transaction/{id<[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}>}/clone",methods={"GET", "POST"}, name="transaction_clone")
	 */
	public function clone(Request $request, Transaction $transaction, LoggerInterface $logger, ManagerRegistry $doctrine): Response
	{
	    $clone = $transaction->clone($this->getUser());
	    
	    $updateTransactionCommand = new UpdateTransactionCommand();
	    $clone->mapTo($updateTransactionCommand);
	    	    	    
	    $form = $this->createForm(TransactionType::class, $updateTransactionCommand);
	    
	    $form->handleRequest($request);
	    
	    if ($form->isSubmitted() && $form->isValid()) {
	        $clone->update($updateTransactionCommand, $this->getUser(), null, $logger);
	        $em = $doctrine->getManager();
	        	        
	        
	        $em->persist($clone);
	        $em->flush();
	        
	        return $this->redirectToRoute('transaction_show', array('id'=> $clone->getId()));
	    }
	    
	    return $this->render('dashboard/transaction/edit.html.twig', [
	        'transaction' => $clone,
	        'form' => $form->createView(),
	    ]);
	}
}
