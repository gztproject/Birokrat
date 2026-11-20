<?php

namespace App\Repository\Invoice;

use App\Entity\Invoice\Invoice;
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
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }
    
    public function getQuery(User $member): QueryBuilder
    {
    	$qb = $this->createQueryBuilder('i')
    		->addSelect('i')
    		->orderBy('i.dateOfIssue', 'DESC')
    		->addOrderBy('i.number', 'DESC');
    	$this->restrictToMember($qb, $member);

    	return $qb;
    }

    /**
     * @return Invoice[]
     */
    public function findByRecepientForMember(Partner $recepient, User $member): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.recepient = :recepient')
            ->setParameter('recepient', $recepient)
            ->orderBy('i.dateOfIssue', 'DESC')
            ->addOrderBy('i.number', 'DESC');
        $this->restrictToMember($qb, $member);

        return $qb->getQuery()->getResult();
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
     * @param int[] $states
     * @return Invoice[]
     */
    public function findRecent(array $states, int $limit, \DateTimeInterface $from, User $member): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.state IN (:states)')
            ->andWhere('i.dateOfIssue >= :from')
            ->setParameter('states', $states)
            ->setParameter('from', $from)
            ->orderBy('i.dateOfIssue', 'DESC')
            ->addOrderBy('i.number', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToMember($qb, $member);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{count: int, total: float}
     */
    public function summarizeIssued(User $member, ?\DateTimeInterface $from = null): array
    {
        return $this->summarizeByState(20, $member, $from);
    }

    /**
     * @return array{count: int, total: float}
     */
    public function summarizeOverdue(User $member, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.totalPrice), 0) AS total')
            ->andWhere('i.state = :state')
            ->andWhere('i.dueDate < :today')
            ->setParameter('state', 20)
            ->setParameter('today', new \DateTimeImmutable('today'));
        $this->applyIssuedFrom($qb, $from);
        $this->restrictToMember($qb, $member);

        $row = $qb->getQuery()->getSingleResult();

        return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }

    /**
     * @return array{count: int, total: float}
     */
    private function summarizeByState(int $state, User $member, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.totalPrice), 0) AS total')
            ->andWhere('i.state = :state')
            ->setParameter('state', $state);
        $this->applyIssuedFrom($qb, $from);
        $this->restrictToMember($qb, $member);

        $row = $qb->getQuery()->getSingleResult();

        return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
    }

    private function restrictToMember(QueryBuilder $qb, User $member): void
    {
        $qb->innerJoin('i.issuer', 'memberIssuer')
            ->andWhere(':member MEMBER OF memberIssuer.users')
            ->setParameter('member', $member);
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
