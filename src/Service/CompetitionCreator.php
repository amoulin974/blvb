<?php
// src/Service/CompetitionCreator.php

namespace App\Service;

use App\Competition\Modele\ModeleBlvbActuel;
use App\Competition\Modele\ModeleSaison;
use App\Entity\Saison;
use Doctrine\ORM\EntityManagerInterface;

class CompetitionCreator
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Crée et enregistre les phases et poules d'une saison à partir d'un modèle (App\Competition\Modele), par défaut
     * le format BLVB actuel (ancien config/app/saison.yaml, D6). Utilisé par les jeux de données ; dans l'admin, une
     * saison se crée avec le wizard (SaisonWizardController).
     */
    public function creerPhasesEtPoules(Saison $saison, bool $isTest = false, ?ModeleSaison $modele = null): void
    {
        $modele ??= new ModeleBlvbActuel($isTest ? 'test_' : '');
        $modele->appliquer($saison);

        foreach ($saison->getPhases() as $phase) {
            $this->em->persist($phase); // les règles de passage suivent la phase
            foreach ($phase->getPoules() as $poule) {
                $this->em->persist($poule);
            }
        }
        $this->em->flush();
    }
}
