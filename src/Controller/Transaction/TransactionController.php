<?php 

namespace App\Controller\Transaction;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\Transaction\TransactionRepository;

class TransactionController extends AbstractController
{    
	/**     
     * @Route("/dashboard/transaction", methods={"GET"}, name="transaction_index")
     */
	public function index(TransactionRepository $transactions, Request $request, PaginatorInterface $paginator): Response
    {     
    	$queryBuilder = $transactions->getQuery();
    	
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	return $this->render('dashboard/transaction/index.html.twig', [
    			'pagination' => $pagination,
    	]);
    } 
    
    /**
     * @Route("/dashboard/transaction/new", methods={"GET"}, name="transaction_new")
     */
    public function new(TransactionRepository $transactions, Request $request, PaginatorInterface $paginator): Response
    {
    	$queryBuilder = $transactions->getQuery();
    	
    	$pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);
    	
    	return $this->render('dashboard/transaction/index.html.twig', [
    			'pagination' => $pagination,
    	]);
    }
    
}
