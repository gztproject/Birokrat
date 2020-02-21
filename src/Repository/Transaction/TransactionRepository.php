<?php

namespace App\Repository\Transaction;

use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\TravelExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }
    
    public function getQuery(): QueryBuilder
    {
    	return $this->createQueryBuilder('t')
    	->addSelect('t')
    	->orderBy('t.date', 'DESC');
    }
    
    public function getFilteredQuery($from, $to, $orgId, $order = "DESC"): QueryBuilder
    {
    	$qb = $this
    	->createQueryBuilder('t')
    	->addSelect('t');
    	if($from)
    	{
    		$qb
    		->where('t.date >= :from')
    		->setParameter('from', date('Y-m-d G:i:s', $from));
    	}
    	
    	if($to)
    	{
    		$qb
    		->andWhere('t.date <= :to')
    		->setParameter('to', date('Y-m-d G:i:s', $to));
    	}
    	
    	if($orgId)
    	{
    		
    		$qb->andWhere('t.organization = :orgid')->setParameter('orgid', $orgId);
    	}
    	
    	return $qb->orderBy('t.date', $order);
    }

    // /**
    //  * @return Transaction[] Returns an array of Transaction objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /**
     * Finds a transaction of a TravelExpense
     * @param TravelExpense $te
     * @return Transaction[] Returns a Transaction object
     */
    public function findOneByTravelExpense(TravelExpense $te): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.travelExpense = :val')
            ->setParameter('val', $te)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
 
}
