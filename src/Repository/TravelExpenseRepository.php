<?php

namespace App\Repository;

use App\Entity\TravelExpense\TravelExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

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
