<?php

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /** Clubs avec leurs équipes, gymnases et responsables, chargés en une requête (liste de l'admin). */
    public function findTousAvecRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.equipes', 'e')->addSelect('e')
            ->leftJoin('c.lieux', 'l')->addSelect('l')
            ->leftJoin('c.responsables', 'r')->addSelect('r')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
