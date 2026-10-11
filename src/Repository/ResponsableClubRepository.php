<?php

namespace App\Repository;

use App\Entity\ResponsableClub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponsableClub>
 */
class ResponsableClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponsableClub::class);
    }
}
