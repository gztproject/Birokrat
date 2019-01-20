<?php 

namespace App\Controller;

use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\TravelExpenseRepository;

class DashboardController extends AbstractController
{    
    /**     
     * @Route("/dashboard", methods={"GET"}, name="dashboard_index")
     */
	public function index(InvoiceRepository $invoices, TravelExpenseRepository $travelExpenses): Response
    {     
    	$myInvoices = $invoices->findBy([], ['dateOfIssue' => 'DESC'], 5);
    	$myTEs = $travelExpenses->findBy([], ['date' => 'DESC'], 5);
    	return $this->render('dashboard/index.html.twig', ['invoices' => $myInvoices, 'travelExpenses' => $myTEs]);
    }    
    
}
