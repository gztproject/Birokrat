<?php

namespace App\Repository\TravelExpense;

use App\Entity\TravelExpense\TravelExpenseBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TravelExpenseBundle|null find($id, $lockMode = null, $lockVersion = null)
 * @method TravelExpenseBundle|null findOneBy(array $criteria, array $orderBy = null)
 * @method TravelExpenseBundle[]    findAll()
 * @method TravelExpenseBundle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TravelExpenseBundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelExpenseBundle::class);
    }

    // /**
    //  * @return TravelExpenseBundle[] Returns an array of TravelExpenseBundle objects
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
    public function findOneBySomeField($value): ?TravelExpenseBundle
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
