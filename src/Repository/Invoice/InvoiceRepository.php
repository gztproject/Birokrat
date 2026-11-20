<?php

namespace App\Repository\Invoice;

use App\Entity\Invoice\Invoice;
use App\Entity\Organization\Organization;
use App\Repository\OrganizationScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    use OrganizationScope;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }
    
    public function getQuery(): QueryBuilder
    {
    	return $this->createQueryBuilder('i')
    		->addSelect('i')
    		->orderBy('i.dateOfIssue', 'DESC')
    		->addOrderBy('i.number', 'DESC');    	 
    }
    
    public function getActive(): QueryBuilder
    {
    	return $this->createQueryBuilder('i')
    	->addSelect('i')
    	->where('i.state IN states')    	
    	->orderBy('i.dateOfIssue', 'DESC')
    	->addOrderBy('i.number', 'DESC')
    	->setParameter('states', '10,20,30');
    }

    /**
     * @param iterable<Organization> $issuers
     * @param int[] $states
     * @return Invoice[]
     */
    public function findRecent(iterable $issuers, array $states, int $limit, \DateTimeInterface $from): array
    {
        return $this->restrictToOrganizations($this->createQueryBuilder('i'), 'i.issuer', $issuers)
            ->andWhere('i.state IN (:states)')
            ->andWhere('i.dateOfIssue >= :from')
            ->setParameter('states', $states)
            ->setParameter('from', $from)
            ->orderBy('i.dateOfIssue', 'DESC')
            ->addOrderBy('i.number', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param iterable<Organization> $issuers
     * @return array{count: int, total: float}
     */
    public function summarizeIssued(iterable $issuers, ?\DateTimeInterface $from = null): array
    {
        return $this->summarizeByState($issuers, 20, $from);
    }

    /**
     * @param iterable<Organization> $issuers
     * @return array{count: int, total: float}
     */
    public function summarizeOverdue(iterable $issuers, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->restrictToOrganizations($this->createQueryBuilder('i'), 'i.issuer', $issuers)
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.totalPrice), 0) AS total')
            ->andWhere('i.state = :state')
            ->andWhere('i.dueDate < :today')
            ->setParameter('state', 20)
            ->setParameter('today', new \DateTimeImmutable('today'));
        $this->applyIssuedFrom($qb, $from);

        $row = $qb->getQuery()->getSingleResult();

        return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }

    /**
     * @param iterable<Organization> $issuers
     * @return array{count: int, total: float}
     */
    private function summarizeByState(iterable $issuers, int $state, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->restrictToOrganizations($this->createQueryBuilder('i'), 'i.issuer', $issuers)
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.totalPrice), 0) AS total')
            ->andWhere('i.state = :state')
            ->setParameter('state', $state);
        $this->applyIssuedFrom($qb, $from);

        $row = $qb->getQuery()->getSingleResult();

        return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }

    private function applyIssuedFrom(QueryBuilder $qb, ?\DateTimeInterface $from): void
    {
        if ($from === null) {
            return;
        }

        $qb->andWhere('i.dateOfIssue >= :from')->setParameter('from', $from);
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
