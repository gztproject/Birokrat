<?php

namespace App\Repository\TravelExpense;

use App\Entity\TravelExpense\TravelStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TravelStop|null find($id, $lockMode = null, $lockVersion = null)
 * @method TravelStop|null findOneBy(array $criteria, array $orderBy = null)
 * @method TravelStop[]    findAll()
 * @method TravelStop[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TravelStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelStop::class);
    }

    // /**
    //  * @return TravelStop[] Returns an array of TravelStop objects
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
    public function findOneBySomeField($value): ?TravelStop
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
