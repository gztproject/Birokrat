<?php

namespace App\Repository;

use App\Entity\Konto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Konto|null find($id, $lockMode = null, $lockVersion = null)
 * @method Konto|null findOneBy(array $criteria, array $orderBy = null)
 * @method Konto[]    findAll()
 * @method Konto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KontoRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
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
