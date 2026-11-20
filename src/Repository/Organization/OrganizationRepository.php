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
     * Limits $qb to rows whose $organizationPath (an association to Organization, e.g. "i.issuer")
     * points at a company $member belongs to. Without a member nothing matches.
     */
    public static function restrictToMember(QueryBuilder $qb, string $organizationPath, ?User $member): QueryBuilder
    {
        if ($member === null) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->andWhere(sprintf(
                'IDENTITY(%s) IN (SELECT member_org.id FROM %s member_org INNER JOIN member_org.users member_user WHERE member_user = :member)',
                $organizationPath,
                Organization::class
            ))
            ->setParameter('member', $member);
    }

    public function memberQueryBuilder(?User $member): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')->orderBy('o.name', 'ASC');
        if ($member === null) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->innerJoin('o.users', 'member_user')
            ->andWhere('member_user = :member')
            ->setParameter('member', $member);
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
