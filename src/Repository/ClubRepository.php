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

    /**
     * Équipes du club engagées dans une saison, avec leurs poules (dans l'ordre des phases), triées par nom.
     *
     * @return list<array{equipe: \App\Entity\Equipe, poules: list<string>}>
     */
    public function equipesEngagees(Club $club, Saison $saison): array
    {
        $lignes = $this->getEntityManager()->createQueryBuilder()
            ->select('e.id AS equipe, p.nom AS poule')
            ->from(\App\Entity\Poule::class, 'p')
            ->innerJoin('p.phase', 'ph')
            ->innerJoin('p.equipes', 'e')
            ->andWhere('ph.saison = :saison')
            ->andWhere('e.club = :club')
            ->setParameter('saison', $saison)
            ->setParameter('club', $club)
            ->orderBy('ph.ordre', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $poules = [];
        foreach ($lignes as $ligne) {
            $poules[(int) $ligne['equipe']][] = $ligne['poule'];
        }

        $engagees = [];
        foreach ($club->getEquipes() as $equipe) {
            if (isset($poules[$equipe->getId()])) {
                $engagees[] = ['equipe' => $equipe, 'poules' => array_values(array_unique($poules[$equipe->getId()]))];
            }
        }
        usort($engagees, static fn (array $a, array $b) => strcasecmp((string) $a['equipe']->getNomSansClub(), (string) $b['equipe']->getNomSansClub()));

        return $engagees;
    }

    /** Clubs avec leurs équipes, gymnases et responsables, chargés en une requête (liste de l'admin). */
    public function findTousAvecRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.equipes', 'e')->addSelect('e')
            ->leftJoin('c.lieux', 'l')->addSelect('l')
            ->leftJoin('c.responsabilites', 'r')->addSelect('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
