<?php

namespace App\Repository\LunchExpense;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\LunchExpense\LunchExpense;
use App\Entity\Organization\Organization;

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
     * @param iterable<Organization> $organizations Only lunch expenses of these organizations are returned.
     */
    public function getFilteredQuery(iterable $organizations, $from, $to, bool $unbooked, bool $booked): QueryBuilder
    {
    	$qb = $this
    	->createQueryBuilder('te')
    	->addSelect('te');

    	$ids = [];
    	foreach ($organizations as $organization) {
    		$ids[] = $organization->getId()->toString();
    	}
    	if ($ids === []) {
    		$qb->andWhere('1 = 0');
    	} else {
    		$qb
    		->andWhere('te.organization IN (:organizations)')
    		->setParameter('organizations', $ids, ArrayParameterType::STRING);
    	}

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
    
    public function countForOrganizationOnDay(Organization $organization, \DateTimeInterface $day): int
    {
    	return (int) $this->createQueryBuilder('le')
    	->select('COUNT(le.id)')
    	->where('le.organization = :organization')
    	->andWhere('le.date = :day')
    	->setParameter('organization', $organization)
    	->setParameter('day', $day->format('Y-m-d'))
    	->getQuery()
    	->getSingleScalarResult();
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
