<?php

namespace App\Repository\Settings;

use App\Entity\Settings\KontoPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method KontoPreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method KontoPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method KontoPreference[]    findAll()
 * @method KontoPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KontoPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, KontoPreference::class);
    }

    // /**
    //  * @return KontoPreference[] Returns an array of KontoPreference objects
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
    public function findOneBySomeField($value): ?KontoPreference
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
