<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\Saison;
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

    /**
     * Matchs planifiés d'une équipe (à domicile ou à l'extérieur) pour une saison, toutes phases confondues, par date croissante.
     *
     * @return Partie[]
     */
    public function findByEquipeSaison(Equipe $equipe, Saison $saison): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.poule', 'p')
            ->innerJoin('p.phase', 'ph')
            ->andWhere('ph.saison = :saison')
            ->andWhere('m.id_equipe_recoit = :equipe OR m.id_equipe_deplace = :equipe')
            ->andWhere('m.date IS NOT NULL')
            ->setParameter('saison', $saison)
            ->setParameter('equipe', $equipe)
            ->orderBy('m.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Matchs d'une saison joués avant la date limite, entre deux équipes connues, dont aucun score n'est saisi
     * et pour lesquels aucun rappel n'est encore parti (app:scores:rappeler).
     *
     * @return Partie[]
     */
    public function findSansScoreARappeler(Saison $saison, \DateTimeImmutable $limite): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('er', 'ed', 'cr', 'cd', 'l')
            ->innerJoin('m.poule', 'p')
            ->innerJoin('p.phase', 'ph')
            ->innerJoin('m.id_equipe_recoit', 'er')
            ->innerJoin('m.id_equipe_deplace', 'ed')
            ->leftJoin('er.club', 'cr')
            ->leftJoin('ed.club', 'cd')
            ->leftJoin('m.lieu', 'l')
            ->andWhere('ph.saison = :saison')
            ->andWhere('m.date IS NOT NULL AND m.date <= :limite')
            ->andWhere('m.nb_set_gagnant_reception IS NULL AND m.nb_set_gagnant_deplacement IS NULL')
            ->andWhere('m.rappelScoreEnvoyeLe IS NULL')
            ->setParameter('saison', $saison)
            ->setParameter('limite', $limite)
            ->orderBy('m.date', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMatchsByEquipePhase(int $equipeId, int $pouleId): array
    {
        $matchs = $this->createQueryBuilder('m')
            ->select('m.id AS match_id')
            ->addSelect('m.date AS date_match')
            ->addSelect('m.nb_set_gagnant_reception AS score_reception_match')
            ->addSelect('m.nb_set_gagnant_deplacement AS score_deplacement_match')
            ->addSelect('er.id AS equipe_recoit_id')
            ->addSelect('ed.id AS equipe_deplace_id')
            // Noms bruts et nom court du club : mis en forme ci-dessous comme Equipe::getNom
            ->addSelect('er.nom AS equipe_recoit')
            ->addSelect('ed.nom AS equipe_deplace')
            ->addSelect('cr.nomCourt AS club_recoit')
            ->addSelect('cd.nomCourt AS club_deplace')
            ->addSelect('l.nom AS lieu_nom')
            ->addSelect('l.adresse AS lieu_adresse')
            ->addSelect('j.id AS journee_id')

            ->leftJoin('m.lieu', 'l')  // un match peut ne pas avoir (encore) de gymnase
            ->join('m.journee', 'j')
            ->join('m.poule', 'poule')

            // 🔥 Deux JOIN séparés pour les 2 équipes
            ->join('m.id_equipe_recoit', 'er')     // ou join('m.equipeRecoit', 'er') selon ton mapping
            ->join('m.id_equipe_deplace', 'ed')    // idem
            ->leftJoin('er.club', 'cr')
            ->leftJoin('ed.club', 'cd')

            ->where('poule.id = :pouleId')
            ->andWhere('er.id = :equipeId OR ed.id = :equipeId')

            ->setParameter('pouleId', $pouleId)
            ->setParameter('equipeId', $equipeId)

            ->orderBy('m.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Noms affichés, comme Equipe::getNom : « Eroak (AOVB) »
        return array_map(static function (array $match): array {
            $match['equipe_recoit'] = Equipe::formaterNom($match['equipe_recoit'], $match['club_recoit']);
            $match['equipe_deplace'] = Equipe::formaterNom($match['equipe_deplace'], $match['club_deplace']);

            return $match;
        }, $matchs);
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
