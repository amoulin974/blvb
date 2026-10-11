<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Saison;
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

    /**
     * Nombre d'équipes de chaque club engagées dans une saison (présentes dans une poule de la saison).
     *
     * @return array<int, int> identifiant du club => nombre d'équipes
     */
    public function nombreEquipesEngagees(Saison $saison): array
    {
        $lignes = $this->createQueryBuilder('c')
            ->select('c.id AS club, COUNT(DISTINCT e.id) AS nombre')
            ->innerJoin('c.equipes', 'e')
            ->innerJoin('e.Poules', 'p')
            ->innerJoin('p.phase', 'ph')
            ->andWhere('ph.saison = :saison')
            ->setParameter('saison', $saison)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $l) => ['club' => (int) $l['club'], 'nombre' => (int) $l['nombre']], $lignes), 'nombre', 'club');
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
