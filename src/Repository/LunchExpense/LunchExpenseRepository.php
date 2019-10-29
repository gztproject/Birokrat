<?php

namespace App\Repository\LunchExpense;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use App\Entity\LunchExpense\LunchExpense;

/**
 * @method LunchExpense|null find($id, $lockMode = null, $lockVersion = null)
 * @method LunchExpense|null findOneBy(array $criteria, array $orderBy = null)
 * @method LunchExpense[]    findAll()
 * @method LunchExpense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LunchExpenseRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LunchExpense::class);
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
