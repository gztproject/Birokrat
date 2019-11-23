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
		    	
    	return $this->render('dashboard/report/index.html.twig', [
    			'clientReport' => $this->getClientReport($this->getUser()->getOrganizations()[0], $em),
    			
    	]);
    }     
    
    private function getClientReport(Organization $organization, EntityManagerInterface $em)
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
