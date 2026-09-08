<?php

namespace App\Repository\Organization;

use App\Entity\Organization\Partner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Partner|null find($id, $lockMode = null, $lockVersion = null)
 * @method Partner|null findOneBy(array $criteria, array $orderBy = null)
 * @method Partner[]    findAll()
 * @method Partner[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }

    /**
     * Clients/suppliers with the most recent invoice activity first, then name.
     *
     * @return Partner[]
     */
    public function findRecentFirst(bool $clients = false, bool $suppliers = false): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.invoices', 'i')
            ->leftJoin('p.incomingInvoices', 'ii')
            ->addSelect('MAX(i.dateOfIssue) AS HIDDEN lastOutgoing')
            ->addSelect('MAX(ii.dateOfIssue) AS HIDDEN lastIncoming')
            ->groupBy('p.id');

        if ($clients) {
            $qb->andWhere('p.isClient = 1');
        }
        if ($suppliers) {
            $qb->andWhere('p.isSupplier = 1');
        }

        return $qb
            ->orderBy('lastOutgoing', 'DESC')
            ->addOrderBy('lastIncoming', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function clientsRecentFirstQueryBuilder()
    {
        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.invoices', 'i')
            ->addSelect('MAX(i.dateOfIssue) AS HIDDEN lastUsed')
            ->andWhere('p.isClient = 1')
            ->groupBy('p.id')
            ->orderBy('lastUsed', 'DESC')
            ->addOrderBy('p.name', 'ASC');

        return $qb;
    }

    public function suppliersRecentFirstQueryBuilder()
    {
        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.incomingInvoices', 'ii')
            ->addSelect('MAX(ii.dateOfIssue) AS HIDDEN lastUsed')
            ->andWhere('p.isSupplier = 1')
            ->groupBy('p.id')
            ->orderBy('lastUsed', 'DESC')
            ->addOrderBy('p.name', 'ASC');

        return $qb;
    }
    

    /*
    public function findOneBySomeField($value): ?Partner
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
