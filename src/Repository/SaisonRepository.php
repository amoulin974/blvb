<?php

namespace App\Repository;

use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saison>
 */
class SaisonRepository extends ServiceEntityRepository
{
    /** @var array{0: ?Saison}|null mémoïsation le temps d'une requête */
    private ?array $courante = null;

    /**
     * Saison en cours : la saison favorite (celle affichée par défaut sur le site), sinon la plus récente.
     * Elle détermine les droits des responsables de club hors saisie de score (fiche du club, rôle).
     */
    public function findCourante(): ?Saison
    {
        return ($this->courante ??= [$this->findOneBy(['favori' => 1]) ?? $this->findOneBy([], ['date_debut' => 'DESC'])])[0];
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saison::class);
    }

    //    /**
    //     * @return Saison[] Returns an array of Saison objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Saison
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
