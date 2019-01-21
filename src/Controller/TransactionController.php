<?php 

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ExpenseRepository;

class TransactionController extends AbstractController
{    
	/**     
     * @Route("/dashboard/transaction", methods={"GET"}, name="transaction_index")
     */
	public function index(ExpenseRepository $expenses): Response
    {     
    	$myExpenses = $expenses->findAll();
    	return $this->render('dashboard/transaction/index.html.twig', ['expenses' => $myExpenses]);
    } 
    
    /**
     * @Route("/dashboard/transaction/new", methods={"GET"}, name="transaction_new")
     */
    public function new(ExpenseRepository $expenses): Response
    {
    	$myExpenses = $expenses->findAll();
    	return $this->render('dashboard/transaction/index.html.twig', ['expenses' => $myExpenses]);
    } 
    
}
