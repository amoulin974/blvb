<?php

namespace App\Repository;

use App\Entity\Partie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partie>
 */
class PartieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partie::class);
    }
    public function getMatchsParLieuEtDatePourSemaine(array  $journeesIds): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('l.id AS lieu_id')
            ->addSelect('j.id AS journee_id')
            ->addSelect('DATE(p.date) AS date_match')
            ->addSelect('COUNT(p.id) AS nb_matchs')
            ->addSelect('l.nbTerrains AS nb_terrains')
            ->addSelect('l.nom AS lieu_nom')
            ->join('p.lieu', 'l')
            ->join('p.journee', 'j')
            ->where('j.id IN (:journeesIds)')
            ->setParameter('journeesIds', $journeesIds)
            ->groupBy('lieu_id, journee_id, date_match')
            ->orderBy('journee_id', 'ASC')
            ->addOrderBy('date_match', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function getMatchsByEquipePhase(int $equipeId, int $pouleId): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id AS match_id')
            ->addSelect('m.date AS date_match')
            ->addSelect('m.nb_set_gagnant_reception AS score_reception_match')
            ->addSelect('m.nb_set_gagnant_deplacement AS score_deplacement_match')
            ->addSelect('er.nom AS equipe_recoit')
            ->addSelect('ed.nom AS equipe_deplace')
            ->addSelect('l.nom AS lieu_nom')
            ->addSelect('l.adresse AS lieu_adresse')
            ->addSelect('j.id AS journee_id')

            ->join('m.lieu', 'l')
            ->join('m.journee', 'j')
            ->join('m.poule', 'poule')

            // 🔥 Deux JOIN séparés pour les 2 équipes
            ->join('m.id_equipe_recoit', 'er')     // ou join('m.equipeRecoit', 'er') selon ton mapping
            ->join('m.id_equipe_deplace', 'ed')    // idem

            ->where('poule.id = :pouleId')
            ->andWhere('er.id = :equipeId OR ed.id = :equipeId')

            ->setParameter('pouleId', $pouleId)
            ->setParameter('equipeId', $equipeId)

            ->orderBy('m.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
    //    /**
    //     * @return Partie[] Returns an array of Partie objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Partie
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
