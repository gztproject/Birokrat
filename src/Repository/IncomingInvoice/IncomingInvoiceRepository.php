<?php

namespace App\Repository\IncomingInvoice;

use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Organization\Partner;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncomingInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncomingInvoice::class);
    }
    
    /**
     * Bills addressed to an organization the user is not linked to are never returned.
     */
    public function createMemberQueryBuilder(User $member, string $alias = 'i'): QueryBuilder
    {
    	return $this->createQueryBuilder($alias)
    		->innerJoin($alias.'.recepient', $alias.'_recepient')
    		->andWhere(':member MEMBER OF '.$alias.'_recepient.users')
    		->setParameter('member', $member);
    }

    public function getQuery(User $member): QueryBuilder
    {
    	return $this->createMemberQueryBuilder($member)
    		->addSelect('i')
    		->orderBy('i.dateOfIssue', 'DESC')
    		->addOrderBy('i.number', 'DESC');    	 
    }
    
    public function getActive(User $member): QueryBuilder
    {
    	return $this->createMemberQueryBuilder($member)
    	->addSelect('i')
    	->andWhere('i.state IN (:states)')
    	->orderBy('i.dateOfIssue', 'DESC')
    	->addOrderBy('i.number', 'DESC')
    	->setParameter('states', [10, 20]);
    }

    /**
     * @return IncomingInvoice[]
     */
    public function findForPartner(Partner $issuer, User $member): array
    {
    	return $this->getQuery($member)
    		->andWhere('i.issuer = :issuer')
    		->setParameter('issuer', $issuer)
    		->getQuery()
    		->getResult();
    }

    /**
     * @return IncomingInvoice[]
     */
    public function findUnpaidReceived(User $member, int $limit = 5, ?\DateTimeInterface $from = null): array
    {
    	$qb = $this->createMemberQueryBuilder($member)
    		->andWhere('i.state = :state')
    		->setParameter('state', 10)
    		->orderBy('i.dueDate', 'ASC')
    		->setMaxResults($limit);
        if ($from !== null) {
            $qb->andWhere('i.dateOfIssue >= :from')->setParameter('from', $from);
        }

    	return $qb->getQuery()->getResult();
    }

    /**
     * @return array{count: int, total: float}
     */
    public function summarizeUnpaid(User $member, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->createMemberQueryBuilder($member)
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.price), 0) AS total')
            ->andWhere('i.state = :state')
            ->setParameter('state', 10);
        if ($from !== null) {
            $qb->andWhere('i.dateOfIssue >= :from')->setParameter('from', $from);
        }

        $row = $qb->getQuery()->getSingleResult();

        return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }

    // /**
    //  * @return Invoice[] Returns an array of Invoice objects
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
    public function findOneBySomeField($value): ?Invoice
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
