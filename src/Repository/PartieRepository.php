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
            ->join('p.id_lieu', 'l')
            ->join('p.id_journee', 'j')
            ->where('j.id IN (:journeesIds)')
            ->setParameter('journeesIds', $journeesIds)
            ->groupBy('lieu_id, journee_id, date_match')
            ->orderBy('journee_id', 'ASC')
            ->addOrderBy('date_match', 'ASC');

        return $qb->getQuery()->getResult();
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
