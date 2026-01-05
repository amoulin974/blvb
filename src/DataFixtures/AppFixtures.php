<?php


namespace App\DataFixtures;

use App\Story\SaisonStructureStory;
use App\Story\SimulationMatchsStory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Contracts\Cache\CacheInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private SimulationMatchsStory $simulationMatchsStory,
        private CacheInterface $cache
    ) {}

    public function load(ObjectManager $manager): void
    {
        // =========================================================================
        // CONFIGURATION DU SCÉNARIO DE TEST
        // =========================================================================
        // 0 : Saison vierge (Structure créée, équipes affectées en P1, aucun match)
        // 1 : Phase 1 en cours (Matchs de P1 joués, classement calculé, NON clôturée)
        // 2 : Phase 2 débutée (P1 clôturée, équipes basculées en P2, aucun match en P2)
        // 3 : Phase 2 en cours (P1 clôturée, matchs de P2 joués, NON clôturée)
        // 4 : Saison complète (P1 et P2 clôturées, Phase Finale jouée)
        // =========================================================================
        $scenario = 3;

        // 1. Nettoyage initial
        $this->cache->delete('saisons_all');

        // 2. Création de l'Administrateur
        // La factory gère le hachage grâce au UserPasswordHasherInterface injecté
        UserFactory::createOne([
            'email' => 'admin@blvb.fr',
            'roles' => ['ROLE_ADMIN'],
            'password' => 'fakepassword123#',
            'nom' => 'Administrateur',
            'prenom' => 'Principal'
        ]);

        // 3. Initialisation de la structure (Phases, Poules et Equipes)
        $saison = SaisonStructureStory::load()->get('saison');
        $phases = $saison->getPhases();

        // 4. Exécution du scénario choisi
        switch ($scenario) {
            case 1:
                // Simule les scores de la Phase 1 (index 0).
                // simulerJusquA ne clôture pas la dernière phase de l'index.
                $this->simulationMatchsStory->simulerJusquA($saison, 0);
                break;

            case 2:
                // Simule P1 et force la clôture pour arriver au début de la Phase 2
                $this->simulationMatchsStory->simulerJusquA($saison, 0);
                $this->simulationMatchsStory->cloturer($phases[0]);
                break;

            case 3:
                // Simule P1, clôture P1, puis simule les scores de la Phase 2
                $this->simulationMatchsStory->simulerJusquA($saison, 1);
                break;

            case 4:
                // Simule P1 (clôturée), P2 (clôturée) et finit par la Phase Finale (index 2)
                $this->simulationMatchsStory->simulerJusquA($saison, 2);
                break;

            default:
                // Scenario 0 : on ne fait rien de plus, la saison reste vierge
                break;
        }
    }
}
