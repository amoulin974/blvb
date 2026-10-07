<?php

namespace App\Repository;

use App\Entity\Joueur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Joueur>
 */
class JoueurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Joueur::class);
    }

    // Recherche insensible à la casse : les emails des fiches joueurs saisies à la main
    // ne sont pas forcément normalisés en minuscules comme ceux des comptes.
    public function findOneByEmail(string $email): ?Joueur
    {
        return $this->createQueryBuilder('j')
            ->andWhere('LOWER(j.email) = :email')
            ->setParameter('email', strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
