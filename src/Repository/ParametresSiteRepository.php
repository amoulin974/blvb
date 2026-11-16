<?php

namespace App\Repository;

use App\Entity\ParametresSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParametresSite>
 */
class ParametresSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametresSite::class);
    }

    /** La ligne des paramètres (créée par la migration), ou des valeurs par défaut non enregistrées si elle manque */
    public function courants(): ParametresSite
    {
        return $this->find(ParametresSite::ID) ?? new ParametresSite();
    }
}
