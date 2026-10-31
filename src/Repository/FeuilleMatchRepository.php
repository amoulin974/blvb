<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\Partie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeuilleMatch>
 */
class FeuilleMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeuilleMatch::class);
    }

    public function pour(Partie $partie, Equipe $equipe): ?FeuilleMatch
    {
        return $this->findOneBy(['partie' => $partie, 'equipe' => $equipe]);
    }
}
