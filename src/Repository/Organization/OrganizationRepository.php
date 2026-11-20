<?php

namespace App\Repository\Organization;

use App\Entity\Organization\Organization;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * @param array<string, 'ASC'|'DESC'> $orderBy
     * @return Organization[]
     */
    public function findByMember(User $user, array $orderBy = []): array
    {
        $qb = $this->memberQueryBuilder($user);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('o.'.$field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    public function memberQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.users', 'u')
            ->andWhere('u.id = :member')
            ->setParameter('member', $user->getId(), 'uuid');
    }

    // /**
    //  * @return Organization[] Returns an array of Organization objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
