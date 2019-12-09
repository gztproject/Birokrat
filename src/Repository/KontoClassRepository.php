<?php

namespace App\Repository;

use App\Entity\Konto\KontoClass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method KontoClass|null find($id, $lockMode = null, $lockVersion = null)
 * @method KontoClass|null findOneBy(array $criteria, array $orderBy = null)
 * @method KontoClass[]    findAll()
 * @method KontoClass[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KontoClassRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KontoClass::class);
    }

    // /**
    //  * @return KontoClass[] Returns an array of KontoClass objects
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
    public function findOneBySomeField($value): ?KontoClass
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
