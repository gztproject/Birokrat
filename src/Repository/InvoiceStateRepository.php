<?php

namespace App\Repository;

use App\Entity\InvoiceState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InvoiceState|null find($id, $lockMode = null, $lockVersion = null)
 * @method InvoiceState|null findOneBy(array $criteria, array $orderBy = null)
 * @method InvoiceState[]    findAll()
 * @method InvoiceState[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceStateRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InvoiceState::class);
    }

    // /**
    //  * @return InvoiceState[] Returns an array of InvoiceState objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InvoiceState
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
