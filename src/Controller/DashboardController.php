<?php 

namespace App\Controller;

use App\Repository\IncomingInvoice\IncomingInvoiceRepository;
use App\Repository\Invoice\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Dashboard\DashboardPeriod;
use App\Repository\TravelExpense\TravelExpenseRepository;
use App\Repository\Transaction\TransactionRepository;

class DashboardController extends AbstractController
{    
	#[Route(path: "/", methods: ["GET"], name: "homepage")]
	#[Route(path: "/dashboard", methods: ["GET"], name: "dashboard_index")]
	public function index(InvoiceRepository $invoices, IncomingInvoiceRepository $incomingInvoices, TravelExpenseRepository $travelExpenses, TransactionRepository $transactions): Response
    {     
        $from = DashboardPeriod::current()->from();
    	$myInvoices = $invoices->findRecent([10, 20, 30], 5, $from);
    	$unpaidIncoming = $incomingInvoices->findUnpaidReceived(5, $from);
    	$myTEs = $travelExpenses->findRecent(5, $from);
	$myTransactions = $transactions->getFilteredQuery($from->getTimestamp(), date('U'), null, 'DESC', 5)->getQuery()->getResult();
    	return $this->render('dashboard/index.html.twig', [
            'invoices' => $myInvoices,
            'incomingInvoices' => $unpaidIncoming,
            'travelExpenses' => $myTEs,
            'transactions'=>$myTransactions,
            'receivables' => $invoices->summarizeIssued($from),
            'overdue' => $invoices->summarizeOverdue($from),
            'unpaidBills' => $incomingInvoices->summarizeUnpaid($from),
        ]);
    }    
    
}
