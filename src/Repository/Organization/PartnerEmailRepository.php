<?php

namespace App\Repository\Organization;

use App\Entity\Organization\PartnerEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PartnerEmail|null find($id, $lockMode = null, $lockVersion = null)
 * @method PartnerEmail|null findOneBy(array $criteria, array $orderBy = null)
 * @method PartnerEmail[]    findAll()
 * @method PartnerEmail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartnerEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerEmail::class);
    }
}
