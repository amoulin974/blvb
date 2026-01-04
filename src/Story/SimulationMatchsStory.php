<?php
namespace App\Story;

use App\Entity\Saison;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Service\JourneeService;
use App\Service\PartieService;
use App\Service\ClassementService;
use App\Service\PhaseService;
use Zenstruck\Foundry\Story;
use function Zenstruck\Foundry\faker;
use Doctrine\ORM\EntityManagerInterface;

final class SimulationMatchsStory extends Story
{
    public function __construct(
        private JourneeService $journeeService,
        private PartieService $partieService,
        private ClassementService $classementService,
        private EntityManagerInterface $entityManager,
        private PhaseService $phaseService
    ) {}

    public function build(): void {}

    /**
     * Simule la saison jusqu'à la fin de la phase spécifiée (index 0, 1 ou 2)
     * Par défaut, cette méthode ne clôture pas la dernière phase simulée pour permettre
     * de visualiser les classements avant le basculement.
     */
    public function simulerJusquA(Saison $saison, int $indexPhaseMax): void
    {
        $phases = $saison->getPhases();
        for ($i = 0; $i <= $indexPhaseMax; $i++) {
            if (isset($phases[$i])) {
                $this->simulerPhaseEntiere($phases[$i]);

                // Si ce n'est pas la dernière phase demandée, on clôture automatiquement
                // pour permettre à la phase suivante d'avoir des équipes.
                if ($i < $indexPhaseMax) {
                    $this->cloturer($phases[$i]);
                }
            }
        }
    }

    /**
     * Étape 1 : Génère les journées, les matchs, saisit les scores et calcule le classement
     */
    public function simulerPhaseEntiere(Phase $phase): void
    {
        foreach ($phase->getPoules() as $poule) {
            // 1. Génération des journées
            $this->journeeService->creerJournees($poule);
            // 2. Génération des matchs
            $this->partieService->createCalendar($poule);
            // 3. Saisie de scores aléatoires
            foreach ($poule->getParties() as $partie) {
                $score = faker()->randomElement([[3,0], [3,1], [3,2], [2,3], [1,3], [0,3]]);
                $partie->setNbSetGagnantReception($score[0]);
                $partie->setNbSetGagnantDeplacement($score[1]);
            }

            $this->entityManager->flush();
            // 4. Mise à jour du classement de la poule (positions finales)
            $this->classementService->mettreAJourClassementPoule($poule);
        }
        $this->entityManager->flush();
    }

    /**
     * Étape 2 : Clôture physiquement la phase et bascule les équipes vers la suivante
     */
    public function cloturer(Phase $phase): void
    {
        // On s'assure que les classements sont à jour avant le basculement
        $this->entityManager->flush();

        // Appel du service pour le mouvement des équipes selon les montées/descentes
        $this->phaseService->cloturerEtBasculer($phase);

        // Le PhaseService gère déjà le flush final et l'initialisation de la phase suivante
    }
}
