<?php
namespace App\Story;

use App\Entity\Saison;
use App\Entity\Phase;
use App\Factory\EquipeFactory;
use App\Factory\SaisonFactory;
use App\EventListener\SaisonListener;
use App\Service\ClassementService;
use App\Factory\LieuFactory;
use App\Factory\CreneauFactory;
use App\Service\CompetitionCreator;
use Zenstruck\Foundry\Story;
use function Zenstruck\Foundry\faker;
use Doctrine\ORM\EntityManagerInterface;
final class SaisonStructureStory extends Story
{
    public function __construct(
        private CompetitionCreator $competitionCreator,
        private EntityManagerInterface $entityManager,
        private ClassementService $classementService
    ) {}

    public function build(): void {
        //Désactivation du listener sur les saison qui crée automatiquement les phases et les poules à la création d'une saison
        SaisonListener::$enabled = false;

        //Création des lieux
        $lieuxTest = [];
        $jours = [1, 2, 3, 4]; // Lundi, Mardi, Mercredi, Jeudi pour varier

        for ($i = 1; $i <= 4; $i++) {
            $lieuxTest[] = LieuFactory::createOne([
                'nom' => "test_gymnase_$i",
                'creneaux' => CreneauFactory::new([
                    'jourSemaine' => $jours[$i-1],
                    'heureDebut' => new \DateTime('20:00'),
                    'prioritaire' => 1,
                    'capacite' => 2
                ])->many(1)
            ]);
        }

        //Création de la saison
        $saison = SaisonFactory::createOne([
            'nom' => 'test_Saison_2025-2026_TEST',
            'date_debut' => new \DateTimeImmutable('2025-09-01'),
            'favori' => 1,
        ]);

        //Création de la structure (Phases et Poules via YAML)
        $this->competitionCreator->creerPhasesEtPoules($saison, true);

        // Pour être sûr, on vide l'EntityManager et on recharge la saison
        $this->entityManager->flush();
        $this->entityManager->clear();
        $saison = $this->entityManager->find(Saison::class, $saison->getId());

        $equipesParPoule = [];
        foreach ($saison->getPhases() as $phase) {
            //Lors de la première phase on créé les équipes que l'on rajoute aux poules
            foreach ($phase->getPoules() as $poule) {
                $nomPoule = $poule->getNom();
                // Si c'est la première fois qu'on croise ce nom de poule (Phase 1)
                if (!isset($equipesParPoule[$nomPoule])) {
                    $equipesParPoule[$nomPoule] = [];

                    // On crée 8 équipes par poule avec le nom demandé
                    for ($i = 0; $i <= 7; $i++) {
                        $equipe = EquipeFactory::createOne([
                            'nom' => "test_" . str_replace(' ', '_', strtolower($nomPoule)) . "_num_" . $i,
                            'lieu' => faker()->randomElement($lieuxTest) // Affectation du lieu
                        ]);
                        $equipesParPoule[$nomPoule][] = $equipe;
                    }
                }

                // On ajoute les équipes à la poule (ManyToMany)
                foreach ($equipesParPoule[$nomPoule] as $equipe) {
                    $poule->addEquipe($equipe);
                    $this->entityManager->persist($equipe);
                }
                //Mise à jour du classement pour la poule
                $this->classementService->mettreAJourClassementPoule($poule);
            }
        }

        $this->entityManager->flush();
        SaisonListener::$enabled = true;
        $this->addState('saison', $saison);
    }


}
