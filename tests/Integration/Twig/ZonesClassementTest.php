<?php

namespace App\Tests\Integration\Twig;

use App\Entity\Poule;
use App\Factory\ClassementFactory;
use App\Factory\EquipeFactory;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;
use Twig\Environment;

/** Zones de montée et de descente du classement : seulement vers un niveau qui existe dans la phase. */
final class ZonesClassementTest extends TestIntegration
{
    private function rendu(Poule $poule): string
    {
        return self::service(Environment::class)->render('front/fragments/_classement_poule_unique.html.twig', ['poule' => $poule]);
    }

    private function pouleAvecClassement(object $phase, int $niveau): Poule
    {
        $poule = PouleFactory::createOne(['phase' => $phase, 'niveau' => $niveau, 'nb_montee_defaut' => 1, 'nb_descente_defaut' => 1]);
        $equipes = EquipeFactory::createMany(3);
        foreach ($equipes as $i => $equipe) {
            ClassementFactory::createOne(['poule' => $poule, 'equipe' => $equipe, 'points' => 10 - $i, 'set_gagnes' => 0, 'set_perdus' => 0, 'position' => $i + 1]);
        }
        $journee = JourneeFactory::createOne(['poule' => $poule]);
        PartieFactory::createOne(['poule' => $poule, 'journee' => $journee, 'id_equipe_recoit' => $equipes[0], 'id_equipe_deplace' => $equipes[1]]);

        return $poule;
    }

    #[TestDox('CLA-17 Pas de zone de descente dans la poule du plus bas niveau, ni de zone de montée dans celle du plus haut')]
    public function test_zones_selon_les_niveaux_existants(): void
    {
        $phase = PhaseFactory::createOne();
        $haute = $this->pouleAvecClassement($phase, 1);
        $milieu = $this->pouleAvecClassement($phase, 2);
        $basse = $this->pouleAvecClassement($phase, 3);
        self::ensureKernelShutdown();
        self::bootKernel();
        $em = self::service('doctrine')->getManager();

        $html = $this->rendu($em->find(Poule::class, $haute->getId()));
        self::assertStringContainsString('zone de descente', $html);
        self::assertStringNotContainsString('zone de montée', $html, 'rien au-dessus de la poule de niveau 1');

        $html = $this->rendu($em->find(Poule::class, $milieu->getId()));
        self::assertStringContainsString('zone de descente', $html);
        self::assertStringContainsString('zone de montée', $html);

        $html = $this->rendu($em->find(Poule::class, $basse->getId()));
        self::assertStringContainsString('zone de montée', $html);
        self::assertStringNotContainsString('zone de descente', $html, 'rien en dessous de la poule du plus bas niveau');
        self::assertStringNotContainsString('Zone de descente', $html, 'ni dans la légende');
    }
}
