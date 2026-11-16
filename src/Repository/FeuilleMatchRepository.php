<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\Partie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeuilleMatch>
 */
class FeuilleMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeuilleMatch::class);
    }

    public function pour(Partie $partie, Equipe $equipe): ?FeuilleMatch
    {
        return $this->findOneBy(['partie' => $partie, 'equipe' => $equipe]);
    }

    /** Dernière page du match de l'équipe avant ce match (joueurs présents repris d'office) */
    public function precedente(Equipe $equipe, Partie $partie): ?FeuilleMatch
    {
        if ($partie->getDate() === null) {
            return null;
        }

        return $this->createQueryBuilder('f')
            ->innerJoin('f.partie', 'p')
            ->andWhere('f.equipe = :equipe')
            ->andWhere('p.date < :date')
            ->setParameter('equipe', $equipe)
            ->setParameter('date', $partie->getDate())
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
