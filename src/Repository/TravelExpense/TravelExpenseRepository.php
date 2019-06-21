<?php

namespace App\Repository\TravelExpense;

use App\Entity\TravelExpense\TravelExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Common\Collections\Criteria;
/**
 * @method TravelExpense|null find($id, $lockMode = null, $lockVersion = null)
 * @method TravelExpense|null findOneBy(array $criteria, array $orderBy = null)
 * @method TravelExpense[]    findAll()
 * @method TravelExpense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TravelExpenseRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, TravelExpense::class);
    }
    
    
    public function getFilteredQuery($from, $to, bool $unbooked, bool $booked): QueryBuilder
    {
    	$qb = $this
    		->createQueryBuilder('te')
    		->addSelect('te');
    	if($from)
    	{
    	  	$qb
    	  	->where('te.date >= :from')
    	  	->setParameter('from', date('Y-m-d G:i:s', $from));
    	}
    	
    	if($to)
    	{
    		$qb    		
    		->andWhere('te.date <= :to')
    		->setParameter('to', date('Y-m-d G:i:s', $to+60*60*24));
    	}
    	
    	if($booked && $unbooked)
    	{
    		
    	}
    	elseif($unbooked)
    	{
    		$qb->andWhere('te.state <= 10');
    	}
    	elseif($booked)
    	{
    		$qb->andWhere('te.state > 10');
    	}
    	else
    	{
    		$qb
    			->andWhere('te.state <= 10')
    			->andWhere('te.state > 10');
    	}
    	
    	return $qb->orderBy('te.date', 'DESC');
    }

    // /**
    //  * @return TravelExpense[] Returns an array of TravelExpense objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TravelExpense
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
