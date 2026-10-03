<?php

namespace App\Repository;

use App\Entity\Lieu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lieu>
 */
class LieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lieu::class);
    }

    public function getLieuByDefaut(): ?Lieu
    {
        //TODO définir un paramètre de configuration
        $lieu = $this->findOneBy(['nom' => 'St-Jean-de-Luz, Gymnase du college Chantaco']);
        if ($lieu !== null) {
            return $lieu;
        }

        // Si le lieu par défaut configuré n'existe pas dans cette base (ex: données de
        // dev/import), on retombe sur le premier lieu qui a au moins un créneau défini,
        // plutôt que de renvoyer null et faire planter la génération des matchs.
        foreach ($this->findAll() as $candidat) {
            if (!$candidat->getCreneaux()->isEmpty()) {
                return $candidat;
            }
        }

        return null;
    }

//    /**
//     * @return Lieu[] Returns an array of Lieu objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Lieu
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
