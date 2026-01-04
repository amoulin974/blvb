<?php
namespace App\Story;

use App\Entity\Saison;
use App\Entity\Poule;
use App\Service\JourneeService;
use App\Service\PartieService;
use App\Service\ClassementService;
use Zenstruck\Foundry\Story;
use function Zenstruck\Foundry\faker;
use Doctrine\ORM\EntityManagerInterface;
final class SimulationMatchsStory extends Story
{
    public function __construct(
        private JourneeService $journeeService,
        private PartieService $partieService,
        private ClassementService $classementService,
        private EntityManagerInterface $entityManager
    ) {}

    public function build(): void {}

    /**
     * Simule la saison jusqu'à la fin de la phase spécifiée (index 0, 1 ou 2)
     */
    public function simulerJusquA(Saison $saison, int $indexPhaseMax): void
    {
        $phases = $saison->getPhases();
        for ($i = 0; $i <= $indexPhaseMax; $i++) {
            if (isset($phases[$i])) {
                $this->simulerPhaseEntiere($phases[$i]);
            }
        }
    }

    private function simulerPhaseEntiere($phase): void
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

            // 4. Mise à jour du classement de la poule
            $this->classementService->mettreAJourClassementPoule($poule);
        }
    }
}
