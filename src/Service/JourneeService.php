<?php

namespace App\Service;

use App\Competition\Format\Formats;
use App\Entity\Poule;
use Doctrine\ORM\EntityManagerInterface;

class JourneeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Formats $formats,
    ) {
    }

    /** Recrée les journées de la poule selon le format de sa phase (App\Competition\Format) ; message d'erreur, ou null. */
    public function creerJournees(Poule $poule): ?string
    {
        $nbEquipe = count($poule->getEquipes());
        if ($nbEquipe < 2) {
            return "Il faut au moins deux équipes dans la poule";
        }

        // Supprime les anciennes journées
        foreach ($poule->getJournees() as $journee) {
            $this->em->remove($journee);
        }
        $poule->getJournees()->clear();
        $this->em->flush();

        $generateur = $this->formats->pourPhase($poule->getPhase());
        if ($generateur === null) {
            return "Type de phase inconnu";
        }

        return $generateur->creerJournees($poule);
    }
}
