<?php

// src/EventListener/SaisonListener.php

namespace App\EventListener;

use App\Entity\Saison;
use App\Service\CompetitionCreator;
use Doctrine\Common\EventSubscriber;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;


class SaisonListener
{

    public function __construct(private CompetitionCreator $creator) { }

    public function postPersist(PostPersistEventArgs  $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Saison) {
            return;
        }


        // On crée les phases/poules automatiquement après la création d'une saison
        $this->creator->creerPhasesEtPoules($entity);
    }
}
