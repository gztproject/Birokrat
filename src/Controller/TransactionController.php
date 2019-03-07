<?php 

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\Transaction\TransactionRepository;

class TransactionController extends AbstractController
{    
	/**     
     * @Route("/dashboard/transaction", methods={"GET"}, name="transaction_index")
     */
	public function index(TransactionRepository $transactions): Response
    {     
    	$myTransactions = $transactions->findAll();
    	return $this->render('dashboard/transaction/index.html.twig', ['transactions' => $myTransactions]);
    } 
    
    /**
     * @Route("/dashboard/transaction/new", methods={"GET"}, name="transaction_new")
     */
    public function new(TransactionRepository $transactions): Response
    {
    	$myTransactions = $transactions->findAll();
    	return $this->render('dashboard/transaction/index.html.twig', ['transactions' => $myTransactions]);
    }
    
}
