<?php
// src/Service/PhaseService.php
namespace App\Service;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Repository\PhaseRepository;
use App\Service\ClassementService;
use Doctrine\ORM\EntityManagerInterface;

class PhaseService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClassementService $classementService
    ) {}

    public function cloturerEtBasculer(Phase $phaseActuelle): void
    {
        $saison = $phaseActuelle->getSaison();

        // 1. Trouver la phase suivante par l'ordre
        $ordreSuivant = $phaseActuelle->getOrdre() + 1;
        $phaseSuivante = null;

        foreach ($saison->getPhases() as $p) {
            if ($p->getOrdre() === $ordreSuivant) {
                $phaseSuivante = $p;
                break;
            }
        }

        if (!$phaseSuivante) {
            return; // Pas de phase suivante, fin de saison
        }

        // 2. Préparer un accès rapide aux poules de destination par niveau
        $poulesDest = [];
        foreach ($phaseSuivante->getPoules() as $pDest) {
            $poulesDest[$pDest->getNiveau()] = $pDest;
        }

        // 3. Parcourir les résultats de la phase actuelle
        foreach ($phaseActuelle->getPoules() as $poule) {
            $nbMontee = $poule->getNbMonteeDefaut() ?? 0;
            $nbDescente = $poule->getNbDescenteDefaut() ?? 0;
            $classements = $poule->getClassements();
            $total = count($classements);

            foreach ($classements as $class) {
                $equipe = $class->getEquipe();
                $pos = $class->getPosition();
                $niveauActuel = $poule->getNiveau();

                // 1. Calcul du niveau théorique (Promotion / Relégation)
                $nouveauNiveau = $niveauActuel;
                if ($pos <= $nbMontee && $niveauActuel > 0) {
                    $nouveauNiveau--;
                } elseif ($pos > ($total - $nbDescente)) {
                    $nouveauNiveau++;
                }

                // 2. Recherche de la poule avec gestion du repli (Fallback)
                $pouleCible = null;

                if (isset($poulesDest[$nouveauNiveau])) {
                    // Cas idéal : le niveau de destination existe
                    $pouleCible = $poulesDest[$nouveauNiveau];
                } elseif (isset($poulesDest[$niveauActuel])) {
                    // Repli 1 : La montée/descente est impossible, on maintient l'équipe
                    $pouleCible = $poulesDest[$niveauActuel];
                } else {
                    // Repli 2 : Même le niveau actuel n'existe plus (ex: suppression d'une poule)
                    // On prend la première poule disponible de la phase suivante par défaut
                    $pouleCible = reset($poulesDest);
                }


                // 3. Affectation finale
                if ($pouleCible) {
                    $pouleCible->addEquipe($equipe);
                }
            }
        }

        // 4. Initialiser les classements de la phase suivante (à 0 points)
        $this->em->flush();
        foreach ($phaseSuivante->getPoules() as $pNext) {
            $this->classementService->mettreAJourClassementPoule($pNext);
        }

        //5 Cloture de la phase actuelle
        $phaseActuelle->setClose(1);
        $this->em->flush();
    }
}
