<?php

namespace App\Repository;

use App\Entity\Reglement;
use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reglement>
 */
class ReglementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reglement::class);
    }

    public function findDeSaison(Saison $saison): ?Reglement
    {
        return $this->findOneBy(['saison' => $saison]);
    }
}
