<?php

namespace App\DataFixtures;

use App\Story\SaisonStructureStory;
use App\Story\SimulationMatchsStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Contracts\Cache\CacheInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private SimulationMatchsStory $simulationMatchsStory,
        private CacheInterface $cache) {}

    public function load(ObjectManager $manager): void
    {
        //Vide le cache
        $this->cache->delete('saisons_all');
        // Initialise la structure et les équipes
        $saison = SaisonStructureStory::load()->get('saison');
        // --- SCÉNARIO 1 : Tester à la fin de la Phase 1 ---
        $this->simulationMatchsStory->simulerJusquA($saison, 0);

        // --- SCÉNARIO 2 : Tester à la fin de la Phase 2 ---
        //$this->simulationMatchsStory->simulerJusquA($saison, 1);

        // --- SCÉNARIO 3 : Saison complète (Phase Finale incluse) ---
        // $this->simulationMatchsStory->simulerJusquA($saison, 2);
    }
}
