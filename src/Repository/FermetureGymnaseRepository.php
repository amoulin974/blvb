<?php

namespace App\Repository;

use App\Entity\FermetureGymnase;
use App\Entity\Lieu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FermetureGymnase>
 */
class FermetureGymnaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FermetureGymnase::class);
    }

    /**
     * Fermetures en cours ou à venir de ces gymnases, de la plus proche à la plus lointaine.
     *
     * @param iterable<Lieu> $lieux
     * @return list<FermetureGymnase>
     */
    public function enCoursOuAVenir(iterable $lieux): array
    {
        $lieux = [...$lieux];
        if ($lieux === []) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->andWhere('f.lieu IN (:lieux)')
            ->andWhere('f.dateFin >= :aujourdhui')
            ->setParameter('lieux', $lieux)
            ->setParameter('aujourdhui', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->orderBy('f.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fermetures en cours ou à venir de tous les gymnases, de la plus proche à la plus lointaine (tableau de bord, D64).
     *
     * @return list<FermetureGymnase>
     */
    public function prochaines(int $max): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('l')
            ->innerJoin('f.lieu', 'l')
            ->andWhere('f.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->orderBy('f.dateDebut', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }

    public function nombreEnCoursOuAVenir(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de gymnases fermés aujourd'hui. */
    public function nombreGymnasesFermesAujourdhui(): int
    {
        $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(DISTINCT IDENTITY(f.lieu))')
            ->andWhere('f.dateDebut <= :aujourdhui AND f.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', $aujourdhui)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Au changement de saison affichée (D60) : efface le motif et l'auteur des fermetures terminées. Les dates restent : le
     * calendrier des saisons passées affiche toujours « Gymnase fermé ».
     */
    public function effacerMotifsEtAuteursTermines(): void
    {
        $this->createQueryBuilder('f')
            ->update()
            ->set('f.motif', 'NULL')
            ->set('f.creePar', 'NULL')
            ->andWhere('f.dateFin < :aujourdhui')
            ->setParameter('aujourdhui', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->getQuery()
            ->execute();
    }
}
