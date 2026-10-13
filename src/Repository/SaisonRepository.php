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
        return ($this->courante ??= [$this->findOneBy(['favori' => 1]) ?? $this->findPlusRecentePubliee()])[0];
    }

    /** Saisons visibles sur le site (hors brouillons du wizard), la saison affichée d'abord. @return list<Saison> */
    public function findPubliees(): array
    {
        return $this->findBy(['brouillon' => false], ['favori' => 'DESC']);
    }

    /** La saison de cet identifiant si elle est visible sur le site, sinon null (brouillon, inexistante). */
    public function findPubliee(mixed $id): ?Saison
    {
        return $id ? $this->findOneBy(['id' => $id, 'brouillon' => false]) : null;
    }

    public function findPlusRecentePubliee(): ?Saison
    {
        return $this->findOneBy(['brouillon' => false], ['date_debut' => 'DESC']);
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
