<?php

namespace App\Repository\Transaction;

use App\Entity\Transaction\Transaction;
use App\Entity\TravelExpense\TravelExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Transaction::class);
    }
    
    public function getQuery(): QueryBuilder
    {
    	return $this->createQueryBuilder('t')
    	->addSelect('t')
    	->orderBy('t.date', 'DESC');
    }

    // /**
    //  * @return Transaction[] Returns an array of Transaction objects
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

    /**
     * Finds a transaction of a TravelExpense
     * @param TravelExpense $te
     * @return Transaction[] Returns a Transaction object
     */
    public function findOneByTravelExpense(TravelExpense $te): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.travelExpense = :val')
            ->setParameter('val', $te)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
 
}
