<?php

namespace App\Repository;

use App\Entity\KontoCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method KontoCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method KontoCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method KontoCategory[]    findAll()
 * @method KontoCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KontoCategoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, KontoCategory::class);
    }

    // /**
    //  * @return KontoCategory[] Returns an array of KontoCategory objects
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
    public function findOneBySomeField($value): ?KontoCategory
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
