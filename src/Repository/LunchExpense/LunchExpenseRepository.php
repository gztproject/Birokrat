<?php

namespace App\Repository\LunchExpense;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\User\User;

/**
 * @method LunchExpense|null find($id, $lockMode = null, $lockVersion = null)
 * @method LunchExpense|null findOneBy(array $criteria, array $orderBy = null)
 * @method LunchExpense[]    findAll()
 * @method LunchExpense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LunchExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LunchExpense::class);
    }
    
    /**
     * Only returns claims of organizations $user is a member of.
     */
    public function getFilteredQuery($from, $to, bool $unbooked, bool $booked, User $user): QueryBuilder
    {
    	$qb = $this
    	->createQueryBuilder('te')
    	->addSelect('te')
    	->innerJoin('te.organization', 'org')
    	->andWhere(':user MEMBER OF org.users')
    	->setParameter('user', $user);
    	if($from)
    	{
    		$qb
    		->andWhere('te.date >= :from')
    		->setParameter('from', date('Y-m-d G:i:s', $from));
    	}
    	
    	if($to)
    	{
    		$qb
    		->andWhere('te.date <= :to')
    		->setParameter('to', date('Y-m-d G:i:s', $to));
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
    //  * @return LunchExpense[] Returns an array of LunchExpense objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LunchExpense
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
