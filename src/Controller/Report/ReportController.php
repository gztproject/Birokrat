<?php 

namespace App\Controller\Report;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\Transaction;
use App\Repository\Transaction\TransactionRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class ReportController extends AbstractController
{    
	/**     
     * @Route("/dashboard/report", methods={"GET"}, name="report_index")
     */
	public function index(EntityManagerInterface $em): Response
    {   
    	$organization = $this->getUser()->getOrganizations()[0];
		    	
    	return $this->render('dashboard/report/index.html.twig', [
    			'overviewReport' =>$this->getOverviewReport($organization, $em),
    			'kontoReport' => $this->getKontoReport($organization, $em),
    			'clientReport' => $this->getClientReport($organization, $em),
    			
    	]);
    } 
    
    private function getOverviewReport(Organization $organization, EntityManagerInterface $em): array
    {
    	$invoices = 0;
    	$incomingInvoices = 0;
    	$dailyExpenses = 0;
    	$socialSecurity = 0;
    	$otherExpenses = 0;
    	$bank = 0;
    	$debts = 0;
    	$cash = 0;
    	
    	$qb = $em->createQueryBuilder();
    	$qb->select(['k.id',
    			'kc.number AS categoryNumber',
    			'k.name',
    			'k.number AS kontoNumber',
    			'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
    			'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'])
    			->from('App\Entity\Konto\Konto','k', 'k.id')
    			->leftJoin('App\Entity\Konto\KontoCategory', 'kc', 'WITH', 'k.category = kc.id')
    			->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', 't.debitKonto = k.id OR t.creditKonto = k.id')   
    			->where('kc.number IN (76, 40, 41, 48, 11, 12, 91, 28)')
    			->andWhere('t.organization = :orgId')    
    			->groupBy('k.id')			
    			->setParameter('orgId', $organization->getId());
    	$query = $qb->getQuery();
    	$result = $query->getArrayResult();
    	
    	foreach($result as $res)
    	{
    		switch ($res['categoryNumber'])
    		{
    			case 76:
    				$invoices += $res['credit'] - $res['debit'];
    				break;
    			case 40:
    				$incomingInvoices += $res['debit'] - $res['credit'];
    				break;
    			case 41:
    				$incomingInvoices += $res['debit'] - $res['credit'];
    				break;
    			case 48:
    				if($res['kontoNumber'] == 486)
    					$dailyExpenses += $res['debit'] - $res['credit'];
    				elseif($res['kontoNumber'] == 484)
    					$socialSecurity += $res['debit'] - $res['credit'];
    				else 
    					$otherExpenses += $res['debit'] - $res['credit'];
    				break;
    			case 11:
    				$bank += $res['debit'] - $res['credit'];
    				break;
    			case 12:
    				$debts += $res['debit'] - $res['credit'];
    				break;
    			case 28:
    				$debts += $res['debit'] - $res['credit'];
    				break;
    			case 91:
    				$cash += $res['debit'] - $res['credit'];
    				break;
    			default:
    				break;
    		}
    	}
    	return [    			
    			'invoices'=> $invoices,
    			'income' => $invoices,
    		
    			'incomingInvoices' => $incomingInvoices,
    			'socialSecurity' => $socialSecurity,
    			'dailyExpenses' => $dailyExpenses,
    			'other' => $otherExpenses,
    			'expenses' => $incomingInvoices+$socialSecurity+$dailyExpenses+$otherExpenses,
    			
    			'bank' => $bank,
    			'debts' => $debts,
    			'cash' => $cash,
    			'outcome' => $bank+$cash+$debts
    	];
    }
    
    private function getKontoReport(Organization $organization, EntityManagerInterface $em): array
    {
    	$qb = $em->createQueryBuilder();
    	$qb->select(['k.id', 
    				'k.number', 
    				'k.name', 
    				'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit', 
    				'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'])
    		->from('App\Entity\Konto\Konto','k', 'k.id')
    		->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', 't.debitKonto = k.id OR t.creditKonto = k.id')
    		->where('t.id IS NOT NULL')
    		->andWhere('t.organization = :orgId')
    		->addGroupBy('k.id')
    		->setParameter('orgId', $organization->getId());
    	$query = $qb->getQuery();
    	$result = $query->getArrayResult();
    	$report = ['kontos' => []];
    	foreach($result as $konto)
    	{
    		$konto['sum'] = $konto['debit']-$konto['credit'];
    		array_push($report['kontos'], $konto);
    	}
    	return $report;
    }
    
    private function getClientReport(Organization $organization, EntityManagerInterface $em): array
    {
//     	$sql = 'SELECT c.id, c.name, COUNT(i.id), SUM(i.total_price) AS total, SUM(CASE WHEN i.state=30 THEN i.total_price ELSE 0 END) AS paid, 
// 					SUM(CASE WHEN i.state=20 THEN i.total_price ELSE 0 END) AS open 
// 				FROM partner AS c 
// 				LEFT OUTER JOIN invoice AS i ON i.recepient_id = c.id AND i.state IN (20, 30) 
// 				WHERE c.is_client = 1
// 					AND i.issuer_id IN ("2acaf66b-4103-11e9-ad9e-68f7280713e5", "2accfd44-4103-11e9-ad9e-68f7280713e5") 
// 				GROUP BY c.id 
// 				ORDER BY total DESC';
    	
    	$qb = $em->createQueryBuilder();
    	$qb->select(['c.id', 
    				'c.name', 
    				'COUNT(i.id) AS number', 
    				'SUM(i.totalPrice) AS total', 
    				'SUM(CASE WHEN i.state = 30 THEN i.totalPrice ELSE 0 END) AS paid', 
    				'SUM(CASE WHEN i.state = 20 THEN i.totalPrice ELSE 0 END) AS open'])
    		->from('App\Entity\Organization\Partner', 'c', 'c.id')
    		->leftJoin('App\Entity\Invoice\Invoice', 'i', 'WITH', 'i.recepient = c.id AND i.state IN (20, 30)')
    		->where('c.isClient = 1')
    		->andWhere('i.issuer = :orgId')
    		->addGroupBy('c.id')
    		->addOrderBy('total','DESC')
    		->setParameter('orgId', $organization->getId());
    		
    		$query = $qb->getQuery();
    		$result = $query->getArrayResult();
    		$totalNumber = 0;
    		$totalTotal = 0;
    		$totalPaid = 0;
    		$totalOpen = 0; 
    		$totalPercent = 0;
    		$clients = [];
    		foreach($result as $c)
    		{
    			$totalNumber += $c['number'];
    			$totalTotal += $c['total'];
    			$totalPaid += $c['paid'];
    			$totalOpen += $c['open'];
    		}
    		foreach($result as $c)
    		{
    			$c['percent'] = $c['total']/$totalTotal;
    			$totalPercent += $c['percent'];
    			array_push($clients, $c);
    		}
    		
    		$sum = ['number'=>$totalNumber, 'total'=>$totalTotal, 'paid'=>$totalPaid, 'open'=>$totalOpen, 'percent'=>$totalPercent];
    		
    		$report = ['clients' => $clients, 'sum' => $sum];
    		    		
    		return $report;
    }
}