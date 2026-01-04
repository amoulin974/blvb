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
    // Par défaut, le listener est actif
    public static bool $enabled = true;

    public function __construct(private CompetitionCreator $creator) { }

    public function postPersist(PostPersistEventArgs  $args): void
    {
        // Si le listener est désactivé manuellement, on ne fait rien
        if (!self::$enabled) {
            return;
        }
        $entity = $args->getObject();

        if (!$entity instanceof Saison) {
            return;
        }

        if (!$entity->getPhases()->isEmpty()) {
            return;
        }

        // On crée les phases/poules automatiquement après la création d'une saison
        $this->creator->creerPhasesEtPoules($entity);
    }
}
