<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Entity\Poule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipe>
 */
class EquipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipe::class);
    }

    /**
     * Récupère les poules d'une équipe pour une saison donnée.
     * @return Poule[]
     */
    public function findPoulesBySaison(Equipe $equipe, Saison $saison): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(Poule::class, 'p') // On cible l'entité Poule
            ->join('p.equipes', 'e')   // Jointure vers les équipes de la poule
            ->join('p.phase', 'ph')    // Jointure vers la phase
            ->where('e.id = :equipeId')
            ->andWhere('ph.saison = :saison') // Filtrage par saison
            ->setParameter('equipeId', $equipe->getId())
            ->setParameter('saison', $saison)
            ->orderBy('ph.ordre', 'ASC') // Tri par ordre de phase
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return Equipe[] Returns an array of Equipe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Equipe
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
