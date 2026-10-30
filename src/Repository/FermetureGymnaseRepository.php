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
}
