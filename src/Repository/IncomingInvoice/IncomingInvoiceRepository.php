<?php

namespace App\Repository\IncomingInvoice;

use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Organization\Organization;
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
     * @param Organization[] $recipients
     */
    public function getQuery(array $recipients): QueryBuilder
    {
    	return $this->createQueryBuilder('i')
    		->addSelect('i')
    		->andWhere('i.recepient IN (:recipients)')
    		->setParameter('recipients', $recipients)
    		->orderBy('i.dateOfIssue', 'DESC')
    		->addOrderBy('i.number', 'DESC');    	 
    }
    
    public function getActive(): QueryBuilder
    {
    	return $this->createQueryBuilder('i')
    	->addSelect('i')
    	->where('i.state IN (:states)')
    	->orderBy('i.dateOfIssue', 'DESC')
    	->addOrderBy('i.number', 'DESC')
    	->setParameter('states', [10, 20]);
    }

    /**
     * @param Organization[] $recipients
     * @return IncomingInvoice[]
     */
    public function findUnpaidReceived(array $recipients, int $limit = 5, ?\DateTimeInterface $from = null): array
    {
    	$qb = $this->createQueryBuilder('i')
    		->andWhere('i.recepient IN (:recipients)')
    		->setParameter('recipients', $recipients)
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
     * @param Organization[] $recipients
     * @return array{count: int, total: float}
     */
    public function summarizeUnpaid(array $recipients, ?\DateTimeInterface $from = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id) AS cnt', 'COALESCE(SUM(i.price), 0) AS total')
            ->andWhere('i.recepient IN (:recipients)')
            ->setParameter('recipients', $recipients)
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
