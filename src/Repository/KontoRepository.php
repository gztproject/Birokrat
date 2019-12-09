<?php

namespace App\Repository;

use App\Entity\Konto\Konto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Konto|null find($id, $lockMode = null, $lockVersion = null)
 * @method Konto|null findOneBy(array $criteria, array $orderBy = null)
 * @method Konto[]    findAll()
 * @method Konto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KontoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Konto::class);
    }

    // /**
    //  * @return Konto[] Returns an array of Konto objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('k.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Konto
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
